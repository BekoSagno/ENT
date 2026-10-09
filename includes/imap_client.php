<?php
// ============================================================
// includes/imap_client.php
// Lecture d'une boîte IMAP en PHP pur (connexion SSL/TLS par socket)
// + décodage des emails MIME (en-têtes, corps, pièces jointes).
//
// Ne nécessite PAS l'extension « imap » : seulement openssl,
// déjà utilisée pour l'envoi SMTP (PHPMailer).
// ============================================================

class CrictImap {
    private $sock = null;
    private int $num = 0;
    public string $erreur = '';

    public function connecter(string $hote, int $port, string $chiffrement = 'ssl', bool $valider_cert = true, int $delai = 15): bool {
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => $valider_cert,
            'verify_peer_name'  => $valider_cert,
            'allow_self_signed' => !$valider_cert,
            'SNI_enabled'       => true,
            'peer_name'         => $hote,
        ]]);
        $proto = $chiffrement === 'ssl' ? 'ssl://' : 'tcp://';
        $this->sock = @stream_socket_client($proto . $hote . ':' . $port, $errno, $errstr, $delai, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) {
            $this->erreur = $errstr !== '' ? $errstr : 'serveur injoignable (code ' . $errno . ')';
            return false;
        }
        stream_set_timeout($this->sock, 30);

        $accueil = $this->ligne();
        if ($accueil === null || strncmp($accueil, '* OK', 4) !== 0) {
            $this->erreur = 'réponse inattendue du serveur';
            return false;
        }
        if ($chiffrement === 'tls') {
            if (!$this->commande('STARTTLS')['ok']) return false;
            if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->erreur = 'échec du chiffrement STARTTLS';
                return false;
            }
        }
        return true;
    }

    public function login(string $utilisateur, string $mot_de_passe): bool {
        $r = $this->commande('LOGIN ' . $this->chaine($utilisateur) . ' ' . $this->chaine($mot_de_passe));
        if (!$r['ok']) $this->erreur = 'identifiants refusés (' . $this->erreur . ')';
        return $r['ok'];
    }

    public function selectionner(string $dossier): bool {
        return $this->commande('SELECT ' . $this->chaine($dossier))['ok'];
    }

    // UID des messages non lus, du plus ancien au plus récent ; null en cas d'erreur
    public function non_lus(): ?array {
        $r = $this->commande('UID SEARCH UNSEEN');
        if (!$r['ok']) return null;
        $uids = [];
        foreach ($r['rep'] as $e) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $e['ligne'], $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $u) if (ctype_digit($u)) $uids[] = (int)$u;
            }
        }
        sort($uids);
        return $uids;
    }

    public function taille(int $uid): int {
        $r = $this->commande('UID FETCH ' . $uid . ' (RFC822.SIZE)');
        foreach ($r['rep'] as $e) {
            if (preg_match('/RFC822\.SIZE (\d+)/i', $e['ligne'], $m)) return (int)$m[1];
        }
        return 0;
    }

    // Message brut complet, sans le marquer comme lu (PEEK)
    public function message(int $uid): ?string {
        return $this->litteral('UID FETCH ' . $uid . ' (BODY.PEEK[])');
    }

    // En-têtes seuls (messages trop volumineux)
    public function entete(int $uid): ?string {
        return $this->litteral('UID FETCH ' . $uid . ' (BODY.PEEK[HEADER])');
    }

    public function marquer_lu(int $uid): bool {
        return $this->commande('UID STORE ' . $uid . ' +FLAGS.SILENT (\\Seen)')['ok'];
    }

    public function deconnecter(): void {
        if (!$this->sock) return;
        @$this->commande('LOGOUT');
        @fclose($this->sock);
        $this->sock = null;
    }

    // ── Protocole ────────────────────────────────────────

    private function litteral(string $cmd): ?string {
        $r = $this->commande($cmd);
        if (!$r['ok']) return null;
        foreach ($r['rep'] as $e) {
            if (!empty($e['lit'])) return $e['lit'][0];
        }
        return null;
    }

    private function chaine(string $v): string {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
    }

    private function ligne(): ?string {
        $l = fgets($this->sock);
        return $l === false ? null : $l;
    }

    private function lire(int $n): ?string {
        $data = '';
        while (strlen($data) < $n) {
            $morceau = fread($this->sock, min(65536, $n - strlen($data)));
            if ($morceau === false || ($morceau === '' && (feof($this->sock) || stream_get_meta_data($this->sock)['timed_out']))) return null;
            $data .= $morceau;
        }
        return $data;
    }

    // Envoie une commande et lit la réponse : ['ok'=>bool, 'rep'=>[['ligne'=>…, 'lit'=>[…]], …]]
    private function commande(string $cmd): array {
        if (!$this->sock) { $this->erreur = 'non connecté'; return ['ok' => false, 'rep' => []]; }
        $tag = 'C' . (++$this->num);
        if (@fwrite($this->sock, $tag . ' ' . $cmd . "\r\n") === false) {
            $this->erreur = 'connexion perdue';
            return ['ok' => false, 'rep' => []];
        }
        $rep = [];
        while (true) {
            $l = $this->ligne();
            if ($l === null) {
                $this->erreur = 'connexion interrompue (délai dépassé)';
                return ['ok' => false, 'rep' => $rep];
            }
            if (strncmp($l, $tag . ' ', strlen($tag) + 1) === 0) {
                $statut = trim(substr($l, strlen($tag) + 1));
                $ok = strncasecmp($statut, 'OK', 2) === 0;
                if (!$ok) $this->erreur = $statut;
                return ['ok' => $ok, 'rep' => $rep];
            }
            $texte = rtrim($l, "\r\n");
            $lits  = [];
            while (preg_match('/\{(\d+)\}$/', $texte, $m)) {
                $data = $this->lire((int)$m[1]);
                $suite = $data === null ? null : $this->ligne();
                if ($suite === null) {
                    $this->erreur = 'lecture du message interrompue';
                    return ['ok' => false, 'rep' => $rep];
                }
                $lits[] = $data;
                $texte .= rtrim($suite, "\r\n");
            }
            $rep[] = ['ligne' => $texte, 'lit' => $lits];
        }
    }
}

// ============================================================
// Décodage MIME
// ============================================================

// Analyse un email brut → ['entetes'=>[…], 'texte'=>str, 'html'=>str, 'pieces'=>[['nom','contenu','mime'],…]]
function mime_analyser(string $brut): array {
    $brut = str_replace("\r\n", "\n", $brut);
    [$h, $corps] = mime_separer($brut);
    $entetes = mime_entetes($h);
    $acc = ['texte' => '', 'html' => '', 'pieces' => []];
    mime_parcourir($entetes, $corps, $acc, 0);
    return ['entetes' => $entetes] + $acc;
}

function mime_separer(string $brut): array {
    $p = strpos($brut, "\n\n");
    if ($p === false) return [$brut, ''];
    return [substr($brut, 0, $p), substr($brut, $p + 2)];
}

// En-têtes « dépliés », clés en minuscules (première occurrence conservée)
function mime_entetes(string $bloc): array {
    $bloc = preg_replace("/\n[ \t]+/", ' ', $bloc);
    $out = [];
    foreach (explode("\n", $bloc) as $l) {
        $p = strpos($l, ':');
        if (!$p) continue;
        $k = strtolower(trim(substr($l, 0, $p)));
        if (!isset($out[$k])) $out[$k] = trim(substr($l, $p + 1));
    }
    return $out;
}

// « text/plain; charset="utf-8" » → ['valeur'=>'text/plain', 'params'=>['charset'=>'utf-8']]
// Gère les paramètres RFC 2231 (filename*=UTF-8''…, filename*0*=…, filename*1*=…)
function mime_valeur(string $v): array {
    $p = strpos($v, ';');
    $principal = strtolower(trim($p === false ? $v : substr($v, 0, $p)));
    $params = [];
    $morceaux = [];
    if ($p !== false && preg_match_all('/;\s*([^=;\s]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/', substr($v, $p), $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) {
            $k   = strtolower($m[1]);
            $val = trim($m[2]);
            if ($val !== '' && $val[0] === '"') $val = preg_replace('/\\\\(.)/', '$1', substr($val, 1, -1));
            if (preg_match('/^([^*]+)\*(\d+)?(\*)?$/', $k, $e)) {
                $num = ($e[2] ?? '') === '' ? 0 : (int)$e[2];
                $enc = ($e[2] ?? '') === '' ? true : !empty($e[3]);
                $morceaux[$e[1]][$num] = [$val, $enc];
            } else {
                $params[$k] = $val;
            }
        }
    }
    foreach ($morceaux as $nom => $parts) {
        ksort($parts);
        $charset = 'UTF-8';
        $txt = '';
        $premier = true;
        foreach ($parts as [$val, $enc]) {
            if ($enc) {
                if ($premier && preg_match("/^([^']*)'[^']*'(.*)$/s", $val, $c)) {
                    if ($c[1] !== '') $charset = $c[1];
                    $val = $c[2];
                }
                $val = rawurldecode($val);
            }
            $txt .= $val;
            $premier = false;
        }
        $params[$nom] = mime_utf8($txt, $charset);
    }
    return ['valeur' => $principal, 'params' => $params];
}

// Décode un en-tête (« =?UTF-8?B?…?= », « =?iso-8859-1?Q?…?= ») en UTF-8
function mime_decoder_entete(string $v): string {
    if ($v === '') return '';
    $v = mime_utf8($v);
    if (strpos($v, '=?') === false) return $v;
    $mot = '=\?([^?\s]+)\?([QqBb])\?([^?\s]*)\?=';
    return preg_replace_callback('/' . $mot . '(?:\s+' . $mot . ')*/', function ($run) use ($mot) {
        preg_match_all('/' . $mot . '/', $run[0], $mots, PREG_SET_ORDER);
        $out = '';
        $cs = null;
        $octets = '';
        foreach ($mots as $m) {
            $charset = strtoupper(preg_replace('/\*.*$/', '', $m[1]));
            $data = strtoupper($m[2]) === 'B' ? (string)base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
            if ($cs !== null && $charset !== $cs) { $out .= mime_utf8($octets, $cs); $octets = ''; }
            $cs = $charset;
            $octets .= $data;
        }
        return $out . mime_utf8($octets, (string)$cs);
    }, $v) ?? $v;
}

// « "Fatoumata Barry" <f.barry@exemple.gn> » → ['Fatoumata Barry', 'f.barry@exemple.gn']
function mime_adresse(string $v): array {
    $v = trim($v);
    $mail = '';
    $nom  = $v;
    if (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $v, $m)) {
        $mail = $m[1];
        $nom  = str_replace($m[0], '', $v);
    } elseif (preg_match('/[^\s<>"()]+@[^\s<>"()]+/', $v, $m)) {
        $mail = $m[0];
        $nom  = str_replace($m[0], '', $v);
    }
    $nom = trim(mime_decoder_entete(trim($nom, " \t()")), " \t\"'");
    return [$nom, strtolower(trim($mail))];
}

function mime_utf8(string $txt, string $charset = 'UTF-8'): string {
    if ($txt === '') return '';
    $cs = strtoupper(trim($charset, " \t\"'"));
    if (in_array($cs, ['', 'US-ASCII', 'ASCII', 'DEFAULT', 'UTF8'], true)) $cs = 'UTF-8';
    if ($cs !== 'UTF-8') {
        $conv = false;
        try { $conv = @mb_convert_encoding($txt, 'UTF-8', $cs); } catch (Throwable $e) { $conv = false; }
        if (!is_string($conv) || $conv === '') $conv = function_exists('iconv') ? @iconv($cs, 'UTF-8//IGNORE', $txt) : false;
        if (is_string($conv) && $conv !== '') return $conv;
    }
    if (mb_check_encoding($txt, 'UTF-8')) return $txt;
    return mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
}

function mime_decoder_corps(string $corps, string $encodage): string {
    switch (strtolower(trim($encodage))) {
        case 'base64':           return (string)base64_decode($corps);
        case 'quoted-printable': return quoted_printable_decode($corps);
        default:                 return $corps;
    }
}

// Parties d'un multipart (préambule et épilogue ignorés)
function mime_sous_parties(string $corps, string $frontiere): array {
    $morceaux = explode("\n--" . $frontiere, "\n" . $corps);
    array_shift($morceaux);
    $parts = [];
    foreach ($morceaux as $m) {
        if (strncmp($m, '--', 2) === 0) break;
        $nl = strpos($m, "\n");
        $parts[] = $nl === false ? '' : substr($m, $nl + 1);
    }
    return $parts;
}

function mime_parcourir(array $h, string $corps, array &$acc, int $profondeur): void {
    $ct   = mime_valeur($h['content-type'] ?? 'text/plain');
    $type = $ct['valeur'] !== '' ? $ct['valeur'] : 'text/plain';
    $disp = mime_valeur($h['content-disposition'] ?? '');

    if (strncmp($type, 'multipart/', 10) === 0 && !empty($ct['params']['boundary']) && $profondeur < 10) {
        foreach (mime_sous_parties($corps, $ct['params']['boundary']) as $part) {
            [$ph, $pc] = mime_separer($part);
            mime_parcourir(mime_entetes($ph), $pc, $acc, $profondeur + 1);
        }
        return;
    }

    $nom = trim(mime_decoder_entete($disp['params']['filename'] ?? ($ct['params']['name'] ?? '')));
    if ($nom === '' && $type === 'message/rfc822') $nom = 'message-joint.eml';
    if ($nom === '' && $disp['valeur'] === 'attachment') $nom = 'piece-jointe.' . (explode('/', $type)[1] ?? 'bin');

    $data = mime_decoder_corps($corps, $h['content-transfer-encoding'] ?? '');
    if ($nom !== '') {
        $acc['pieces'][] = ['nom' => $nom, 'contenu' => $data, 'mime' => $type];
        return;
    }
    if ($type === 'text/html') {
        if ($acc['html'] === '') $acc['html'] = mime_utf8($data, $ct['params']['charset'] ?? 'UTF-8');
    } elseif ($type === 'text/plain') {
        if ($acc['texte'] === '') $acc['texte'] = mime_utf8($data, $ct['params']['charset'] ?? 'UTF-8');
    }
}

// Version texte lisible d'un corps HTML
function mime_html_texte(string $html): string {
    $html = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
    $html = preg_replace('#</(p|div|tr|li|h[1-6]|blockquote)>#i', "\n", $html) ?? $html;
    $txt  = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt  = preg_replace("/[ \t\x{00A0}]+/u", ' ', $txt) ?? $txt;
    $txt  = preg_replace("/ *\n */", "\n", $txt) ?? $txt;
    $txt  = preg_replace("/\n{3,}/", "\n\n", $txt) ?? $txt;
    return trim($txt);
}
