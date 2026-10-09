<?php
// ============================================================
// includes/courrier_imap.php
// Relève de la boîte institutionnelle info@crict.edu.gn (IMAP)
// → crée automatiquement des COURRIERS ENTRANTS (canal EMAIL).
//
// Lecture en PHP pur (includes/imap_client.php) : l'extension « imap »
// n'est pas nécessaire.
// Dépend de : models/courrier.php (déjà inclus par l'appelant)
//
// Constantes de config attendues (voir config_courriers.snippet.php) :
//   MAIL_INFO_ADDRESS, MAIL_INFO_PASS
//   IMAP_INFO_HOST, IMAP_INFO_PORT, IMAP_INFO_ENCRYPTION,
//   IMAP_INFO_FOLDER, IMAP_INFO_VALIDATE_CERT
// ============================================================

require_once __DIR__ . '/imap_client.php';

// Au-delà, seuls les en-têtes sont importés (mémoire du serveur)
define('COURRIER_IMAP_TAILLE_MAX', 20 * 1024 * 1024);

// Relève automatique à l'ouverture du registre.
// IMAP_RELEVE_AUTO = false sur une copie locale qui partage la boîte info@ avec la prod :
// sinon les emails seraient importés (et marqués lus) dans la base locale.
function courrier_releve_auto_active(): bool {
    return defined('MAIL_INFO_PASS') && MAIL_INFO_PASS !== ''
        && (!defined('IMAP_RELEVE_AUTO') || IMAP_RELEVE_AUTO);
}

// ──────────────────────────────────────────────────────────
// Point d'entrée : relève la boîte et crée les courriers.
//   $limite = nombre max de messages traités par appel
// Retour : ['succes'=>bool,'crees'=>int,'ignores'=>int,'erreurs'=>int,'message'=>string]
// ──────────────────────────────────────────────────────────
function courrier_relever_emails(int $limite = 50): array {
    $echec = ['succes'=>false,'crees'=>0,'ignores'=>0,'erreurs'=>0];
    if (!defined('MAIL_INFO_ADDRESS') || MAIL_INFO_ADDRESS === '' ||
        !defined('MAIL_INFO_PASS')    || MAIL_INFO_PASS === '') {
        return $echec + ['message'=>"La boîte info@ n'est pas configurée (MAIL_INFO_ADDRESS / MAIL_INFO_PASS)."];
    }

    $host    = defined('IMAP_INFO_HOST') ? IMAP_INFO_HOST : 'localhost';
    $port    = defined('IMAP_INFO_PORT') ? (int)IMAP_INFO_PORT : 993;
    $enc     = defined('IMAP_INFO_ENCRYPTION') ? IMAP_INFO_ENCRYPTION : 'ssl';
    $folder  = defined('IMAP_INFO_FOLDER') ? IMAP_INFO_FOLDER : 'INBOX';
    $valider = defined('IMAP_INFO_VALIDATE_CERT') ? (bool)IMAP_INFO_VALIDATE_CERT : true;

    $imap = new CrictImap();
    if (!$imap->connecter($host, $port, $enc, $valider)
        || !$imap->login(MAIL_INFO_ADDRESS, MAIL_INFO_PASS)
        || !$imap->selectionner($folder)) {
        $err = $imap->erreur ?: 'connexion refusée';
        $imap->deconnecter();
        error_log('[CRICT IMAP] '.$err);
        return $echec + ['message'=>'Connexion à la boîte info@ impossible : '.$err];
    }

    // On ne traite que les messages NON LUS pour éviter de re-balayer toute la boîte
    $uids = $imap->non_lus();
    if ($uids === null) {
        $err = $imap->erreur;
        $imap->deconnecter();
        error_log('[CRICT IMAP] recherche : '.$err);
        return $echec + ['message'=>'Lecture de la boîte info@ impossible : '.$err];
    }
    $crees = 0; $ignores = 0; $erreurs = 0;

    foreach (array_slice($uids, 0, $limite) as $uid) {
        try {
            $res = _courrier_imap_traiter_message($imap, $uid);
            if ($res === 'cree')        $crees++;
            elseif ($res === 'ignore')  $ignores++;
            else                        $erreurs++;
            // Marquer comme lu dans tous les cas (le courrier est désormais dans la plateforme)
            $imap->marquer_lu($uid);
        } catch (Throwable $e) {
            $erreurs++;
            error_log('[CRICT IMAP msg '.$uid.'] '.$e->getMessage());
        }
    }

    $imap->deconnecter();

    $msg = $crees > 0
        ? ($crees.' courrier(s) reçu(s)'.($ignores?' · '.$ignores.' déjà connu(s)':'').($erreurs?' · '.$erreurs.' en erreur':''))
        : ($ignores || $erreurs
            ? 'Aucun nouveau courrier'.($ignores?' ('.$ignores.' déjà connu(s))':'').($erreurs?' · '.$erreurs.' en erreur':'')
            : 'Aucun nouveau message dans la boîte info@.');

    return ['succes'=>true,'crees'=>$crees,'ignores'=>$ignores,'erreurs'=>$erreurs,'message'=>$msg];
}

// ──────────────────────────────────────────────────────────
// Relève avec verrou : une seule à la fois, et au plus une
// toutes les $intervalle secondes (sauf $forcer = bouton / cron).
// Retour : résultat de $relever(), ou null si la relève est sautée.
// ──────────────────────────────────────────────────────────
function courrier_releve_verrouillee(callable $relever, int $intervalle, bool $forcer): ?array {
    if (!is_dir(COURRIER_UPLOAD_DIR)) @mkdir(COURRIER_UPLOAD_DIR, 0750, true);
    $h = @fopen(COURRIER_UPLOAD_DIR . '.releve_info', 'c+');
    if (!$h) return $forcer ? $relever() : null;

    if (!flock($h, $forcer ? LOCK_EX : LOCK_EX | LOCK_NB)) { fclose($h); return null; }
    $derniere = (int)stream_get_contents($h);
    if (!$forcer && time() - $derniere < $intervalle) {
        flock($h, LOCK_UN); fclose($h);
        return null;
    }
    ftruncate($h, 0); rewind($h); fwrite($h, (string)time()); fflush($h);

    try {
        return $relever();
    } finally {
        flock($h, LOCK_UN); fclose($h);
    }
}

// ──────────────────────────────────────────────────────────
// Traite un message individuel → 'cree' | 'ignore' | 'erreur'
// ──────────────────────────────────────────────────────────
function _courrier_imap_traiter_message(CrictImap $imap, int $uid): string {
    $taille    = $imap->taille($uid);
    $trop_gros = $taille > COURRIER_IMAP_TAILLE_MAX;
    $brut      = $trop_gros ? $imap->entete($uid) : $imap->message($uid);
    if ($brut === null || $brut === '') return 'erreur';

    $mail = mime_analyser($brut);
    $h    = $mail['entetes'];

    // Identifiant unique du message (dédoublonnage)
    $message_id = trim($h['message-id'] ?? '');
    if ($message_id === '') {
        // Repli : empreinte stable à partir de l'expéditeur + sujet + date
        $message_id = 'imap-' . md5(($h['from'] ?? '').'|'.($h['subject'] ?? '').'|'.($h['date'] ?? '').'|'.$uid);
    }
    $message_id = mb_substr($message_id, 0, 255);

    $db = get_db();
    $exist = $db->prepare('SELECT id FROM courriers WHERE email_message_id = ? LIMIT 1');
    $exist->execute([$message_id]);
    if ($exist->fetchColumn()) return 'ignore';

    // En-têtes décodés
    $sujet = trim(mime_decoder_entete($h['subject'] ?? ''));
    $sujet = mb_substr($sujet !== '' ? $sujet : '(sans objet)', 0, 255);

    [$exp_nom, $exp_mail] = mime_adresse($h['from'] ?? '');
    if ($exp_nom === '' && $exp_mail !== '') $exp_nom = $exp_mail;

    $ts = !empty($h['date']) ? strtotime(preg_replace('/\s*\([^)]*\)\s*$/', '', $h['date'])) : false;
    $date_reception = date('Y-m-d H:i:s', $ts ?: time());

    // Corps
    $corps = trim($mail['texte']);
    if ($corps === '' && $mail['html'] !== '') $corps = mime_html_texte($mail['html']);
    if ($trop_gros) {
        $corps = 'Email trop volumineux (' . number_format($taille / 1048576, 1, ',', ' ') . ' Mo) pour être importé automatiquement : '
               . 'consultez la boîte ' . MAIL_INFO_ADDRESS . ' pour le texte et les pièces jointes.';
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
    foreach ($mail['pieces'] as $pj) {
        $r = courrier_doc_enregistrer($cid, $pj['nom'], $pj['contenu'], $pj['mime'] ?? null, 'PIECE_JOINTE');
        if (!$r['succes']) error_log('[CRICT IMAP] '.$reference.' — pièce « '.$pj['nom'].' » : '.$r['message']);
    }

    if (function_exists('log_activite')) {
        log_activite((function_exists('user_id') ? user_id() : null), 'COURRIERS', 'RECEPTION_EMAIL',
            'Courrier email reçu : '.$reference.' (de '.$exp_mail.')');
    }
    return 'cree';
}
