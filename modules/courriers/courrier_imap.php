<?php
// ============================================================
// includes/courrier_imap.php
// Relève de la boîte institutionnelle info@crict.edu.gn (IMAP)
// → crée automatiquement des COURRIERS ENTRANTS (canal EMAIL).
//
// Pré-requis : extension PHP « imap » activée (php-imap).
// Dépend de : models/courrier.php (déjà inclus par l'appelant)
//
// Constantes de config attendues (voir config_courriers.snippet.php) :
//   MAIL_INFO_ADDRESS, MAIL_INFO_PASS
//   IMAP_INFO_HOST, IMAP_INFO_PORT, IMAP_INFO_ENCRYPTION,
//   IMAP_INFO_FOLDER, IMAP_INFO_VALIDATE_CERT
// ============================================================

// ──────────────────────────────────────────────────────────
// Point d'entrée : relève la boîte et crée les courriers.
//   $limite = nombre max de messages traités par appel
// Retour : ['succes'=>bool,'crees'=>int,'ignores'=>int,'erreurs'=>int,'message'=>string]
// ──────────────────────────────────────────────────────────
function courrier_relever_emails(int $limite = 50): array {
    if (!function_exists('imap_open')) {
        return ['succes'=>false,'crees'=>0,'ignores'=>0,'erreurs'=>0,
                'message'=>"L'extension PHP « imap » n'est pas activée sur le serveur."];
    }
    if (!defined('MAIL_INFO_ADDRESS') || MAIL_INFO_ADDRESS === '' ||
        !defined('MAIL_INFO_PASS')    || MAIL_INFO_PASS === '') {
        return ['succes'=>false,'crees'=>0,'ignores'=>0,'erreurs'=>0,
                'message'=>"La boîte info@ n'est pas configurée (MAIL_INFO_ADDRESS / MAIL_INFO_PASS)."];
    }

    $host    = defined('IMAP_INFO_HOST') ? IMAP_INFO_HOST : 'localhost';
    $port    = defined('IMAP_INFO_PORT') ? (int)IMAP_INFO_PORT : 993;
    $enc     = defined('IMAP_INFO_ENCRYPTION') ? IMAP_INFO_ENCRYPTION : 'ssl';
    $folder  = defined('IMAP_INFO_FOLDER') ? IMAP_INFO_FOLDER : 'INBOX';
    $valider = defined('IMAP_INFO_VALIDATE_CERT') ? IMAP_INFO_VALIDATE_CERT : true;

    // Construction du flag de connexion : {host:port/imap/ssl/novalidate-cert}INBOX
    $flags  = '/imap';
    if ($enc === 'ssl')      $flags .= '/ssl';
    elseif ($enc === 'tls')  $flags .= '/tls';
    $flags .= $valider ? '/validate-cert' : '/novalidate-cert';
    $mailbox = '{' . $host . ':' . $port . $flags . '}' . $folder;

    $imap = @imap_open($mailbox, MAIL_INFO_ADDRESS, MAIL_INFO_PASS, 0, 1);
    if (!$imap) {
        $err = imap_last_error() ?: 'connexion refusée';
        imap_errors(); imap_alerts(); // purge
        error_log('[CRICT IMAP] '.$err);
        return ['succes'=>false,'crees'=>0,'ignores'=>0,'erreurs'=>0,
                'message'=>'Connexion à la boîte info@ impossible : '.$err];
    }

    // On ne traite que les messages NON LUS pour éviter de re-balayer toute la boîte
    $uids = imap_search($imap, 'UNSEEN', SE_UID) ?: [];
    $crees = 0; $ignores = 0; $erreurs = 0;

    foreach (array_slice($uids, 0, $limite) as $uid) {
        try {
            $res = _courrier_imap_traiter_message($imap, (int)$uid);
            if ($res === 'cree')        $crees++;
            elseif ($res === 'ignore')  $ignores++;
            else                        $erreurs++;
            // Marquer comme lu dans tous les cas (le courrier est désormais dans la plateforme)
            imap_setflag_full($imap, (string)$uid, "\\Seen", ST_UID);
        } catch (Throwable $e) {
            $erreurs++;
            error_log('[CRICT IMAP msg '.$uid.'] '.$e->getMessage());
        }
    }

    imap_errors(); imap_alerts(); // purge des avertissements
    imap_close($imap);

    $msg = $crees > 0
        ? ($crees.' courrier(s) reçu(s)'.($ignores?' · '.$ignores.' déjà connu(s)':'').($erreurs?' · '.$erreurs.' en erreur':''))
        : ($ignores || $erreurs
            ? 'Aucun nouveau courrier'.($ignores?' ('.$ignores.' déjà connu(s))':'').($erreurs?' · '.$erreurs.' en erreur':'')
            : 'Aucun nouveau message dans la boîte info@.');

    return ['succes'=>true,'crees'=>$crees,'ignores'=>$ignores,'erreurs'=>$erreurs,'message'=>$msg];
}

// ──────────────────────────────────────────────────────────
// Traite un message individuel → 'cree' | 'ignore' | 'erreur'
// ──────────────────────────────────────────────────────────
function _courrier_imap_traiter_message($imap, int $uid): string {
    $overview = imap_fetch_overview($imap, (string)$uid, FT_UID);
    if (!$overview || empty($overview[0])) return 'erreur';
    $ov = $overview[0];

    // Identifiant unique du message (dédoublonnage)
    $message_id = isset($ov->message_id) ? trim($ov->message_id) : '';
    if ($message_id === '') {
        // Repli : empreinte stable à partir de l'expéditeur + sujet + date
        $message_id = 'imap-' . md5(($ov->from ?? '').'|'.($ov->subject ?? '').'|'.($ov->date ?? '').'|'.$uid);
    }

    $db = get_db();
    $exist = $db->prepare('SELECT id FROM courriers WHERE email_message_id = ? LIMIT 1');
    $exist->execute([$message_id]);
    if ($exist->fetchColumn()) return 'ignore';

    // En-têtes décodés
    $sujet = _courrier_imap_decode_mime($ov->subject ?? '(sans objet)');
    $sujet = mb_substr(trim($sujet) ?: '(sans objet)', 0, 255);

    $header   = imap_headerinfo($imap, imap_msgno($imap, $uid));
    $exp_nom  = '';
    $exp_mail = '';
    if (!empty($header->from[0])) {
        $f = $header->from[0];
        $exp_mail = strtolower(trim(($f->mailbox ?? '').'@'.($f->host ?? '')), " @");
        $exp_nom  = isset($f->personal) ? _courrier_imap_decode_mime($f->personal) : '';
    }
    if ($exp_nom === '' && $exp_mail !== '') $exp_nom = $exp_mail;

    $date_reception = isset($ov->date) ? date('Y-m-d H:i:s', strtotime($ov->date)) : date('Y-m-d H:i:s');

    // Corps + pièces jointes
    $parse = _courrier_imap_extraire_corps($imap, $uid);
    $corps = trim($parse['texte']);
    if ($corps === '' && $parse['html'] !== '') {
        $corps = trim(strip_tags(str_replace(['<br>','<br/>','<br />','</p>'], "\n", $parse['html'])));
    }
    $corps = mb_substr($corps, 0, 20000);

    // ── Création du courrier entrant ──
    $reference = courrier_generer_reference('ENTRANT');
    $db->prepare("
        INSERT INTO courriers
          (reference, sens, canal, type_courrier, objet, contenu,
           correspondant_nom, correspondant_email,
           date_reception, priorite, confidentialite,
           statut, email_message_id, enregistre_par)
        VALUES (?, 'ENTRANT', 'EMAIL', 'LETTRE', ?, ?, ?, ?, ?, 'NORMALE', 'NORMAL', 'RECU', ?, ?)
    ")->execute([
        $reference, $sujet, ($corps !== '' ? $corps : null),
        ($exp_nom ?: null), ($exp_mail ?: null),
        $date_reception, $message_id,
        (function_exists('user_id') ? user_id() : null),
    ]);
    $cid = (int)$db->lastInsertId();

    // Journal de suivi
    courrier_suivi_ajouter($cid, 'RECEPTION_EMAIL',
        'Email reçu sur info@ — de ' . ($exp_mail ?: 'expéditeur inconnu'), null, 'RECU');

    // Pièces jointes
    foreach ($parse['pieces'] as $pj) {
        courrier_doc_enregistrer($cid, $pj['nom'], $pj['contenu'], $pj['mime'] ?? null, 'PIECE_JOINTE');
    }

    if (function_exists('log_activite')) {
        log_activite((function_exists('user_id') ? user_id() : 0), 'COURRIERS', 'RECEPTION_EMAIL',
            'Courrier email reçu : '.$reference.' (de '.$exp_mail.')');
    }
    return 'cree';
}

// ──────────────────────────────────────────────────────────
// Décode un en-tête MIME encodé (=?UTF-8?...?=) vers UTF-8
// ──────────────────────────────────────────────────────────
function _courrier_imap_decode_mime(string $valeur): string {
    if ($valeur === '') return '';
    $out = '';
    foreach (imap_mime_header_decode($valeur) as $part) {
        $charset = strtoupper($part->charset ?? 'default');
        $txt = $part->text;
        if ($charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($txt, 'UTF-8', $charset);
            if ($conv !== false) $txt = $conv;
        }
        $out .= $txt;
    }
    return $out;
}

// ──────────────────────────────────────────────────────────
// Parcourt récursivement la structure MIME du message :
//   retourne ['texte'=>str, 'html'=>str, 'pieces'=>[['nom','contenu','mime'],…]]
// ──────────────────────────────────────────────────────────
function _courrier_imap_extraire_corps($imap, int $uid): array {
    $struct = imap_fetchstructure($imap, $uid, FT_UID);
    $acc = ['texte'=>'', 'html'=>'', 'pieces'=>[]];

    if (!$struct) return $acc;

    if (empty($struct->parts)) {
        // Message simple (une seule partie)
        $body = imap_body($imap, $uid, FT_UID | FT_PEEK);
        $body = _courrier_imap_decode_part($body, $struct->encoding ?? 0);
        $charset = _courrier_imap_charset($struct);
        $body = _courrier_imap_to_utf8($body, $charset);
        if (isset($struct->subtype) && strtoupper($struct->subtype) === 'HTML') $acc['html'] = $body;
        else $acc['texte'] = $body;
        return $acc;
    }

    _courrier_imap_walk($imap, $uid, $struct->parts, '', $acc);
    return $acc;
}

function _courrier_imap_walk($imap, int $uid, array $parts, string $prefix, array &$acc): void {
    foreach ($parts as $i => $part) {
        $section = $prefix === '' ? (string)($i + 1) : $prefix . '.' . ($i + 1);

        // Pièce jointe ? (disposition attachment OU nom de fichier présent)
        $nom = _courrier_imap_nom_fichier($part);
        $is_attach = false;
        if (!empty($part->disposition) && strtoupper($part->disposition) === 'ATTACHMENT') $is_attach = true;
        if ($nom !== '' && empty($part->parts)) $is_attach = $is_attach || ($part->type != 0 || $nom !== '');

        if ($nom !== '' && $is_attach) {
            $raw = imap_fetchbody($imap, $uid, $section, FT_UID | FT_PEEK);
            $raw = _courrier_imap_decode_part($raw, $part->encoding ?? 0);
            $mime = _courrier_imap_mime($part);
            $acc['pieces'][] = ['nom'=>$nom, 'contenu'=>$raw, 'mime'=>$mime];
            continue;
        }

        // Sous-parties (multipart imbriqué)
        if (!empty($part->parts)) {
            _courrier_imap_walk($imap, $uid, $part->parts, $section, $acc);
            continue;
        }

        // Corps texte / html
        if (isset($part->type) && $part->type == 0) { // TEXT
            $raw = imap_fetchbody($imap, $uid, $section, FT_UID | FT_PEEK);
            $raw = _courrier_imap_decode_part($raw, $part->encoding ?? 0);
            $raw = _courrier_imap_to_utf8($raw, _courrier_imap_charset($part));
            $sub = strtoupper($part->subtype ?? '');
            if ($sub === 'HTML') { if ($acc['html'] === '') $acc['html'] = $raw; }
            else                 { if ($acc['texte'] === '') $acc['texte'] = $raw; }
        }
    }
}

// Décode selon l'encodage de transfert (base64 / quoted-printable)
function _courrier_imap_decode_part(string $data, int $encoding): string {
    switch ($encoding) {
        case 3: return base64_decode($data);            // BASE64
        case 4: return quoted_printable_decode($data);  // QUOTED-PRINTABLE
        default: return $data;                          // 7BIT / 8BIT / BINARY / OTHER
    }
}

// Récupère le charset d'une partie MIME
function _courrier_imap_charset($part): string {
    if (!empty($part->parameters)) {
        foreach ($part->parameters as $p) {
            if (strtoupper($p->attribute) === 'CHARSET') return $p->value;
        }
    }
    if (!empty($part->dparameters)) {
        foreach ($part->dparameters as $p) {
            if (strtoupper($p->attribute) === 'CHARSET') return $p->value;
        }
    }
    return 'UTF-8';
}

function _courrier_imap_to_utf8(string $txt, string $charset): string {
    $charset = strtoupper($charset);
    if ($charset === '' || $charset === 'UTF-8' || $charset === 'US-ASCII') return $txt;
    if (function_exists('mb_convert_encoding')) {
        $conv = @mb_convert_encoding($txt, 'UTF-8', $charset);
        if ($conv !== false) return $conv;
    }
    return $txt;
}

// Nom de fichier d'une pièce jointe (paramètres « name » ou « filename »)
function _courrier_imap_nom_fichier($part): string {
    $nom = '';
    if (!empty($part->dparameters)) {
        foreach ($part->dparameters as $p) {
            if (in_array(strtoupper($p->attribute), ['FILENAME','NAME'], true)) { $nom = $p->value; break; }
        }
    }
    if ($nom === '' && !empty($part->parameters)) {
        foreach ($part->parameters as $p) {
            if (in_array(strtoupper($p->attribute), ['NAME','FILENAME'], true)) { $nom = $p->value; break; }
        }
    }
    return $nom !== '' ? _courrier_imap_decode_mime($nom) : '';
}

// Type MIME reconstruit d'une partie
function _courrier_imap_mime($part): ?string {
    $primaires = ['text','multipart','message','application','audio','image','video','other'];
    $type = $primaires[$part->type ?? 7] ?? 'application';
    $sub  = strtolower($part->subtype ?? 'octet-stream');
    return $type . '/' . $sub;
}
