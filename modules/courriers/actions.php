<?php
// modules/courriers/actions.php — Traitement des actions POST
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Requête invalide.');
    redirect('/modules/courriers/index.php');
}

$action = clean($_POST['action']      ?? '');
$cid    = (int)($_POST['courrier_id'] ?? 0);
$uid    = (int)user_id();

$cour = $cid ? courrier_charger($cid) : null;
if (!$cour || !courrier_peut_voir($cour)) {
    set_flash('error', 'Courrier introuvable ou accès non autorisé.');
    redirect('/modules/courriers/index.php');
}

$retour  = '/modules/courriers/voir.php?id=' . $cid;
$fil     = $retour . '&focus=echanges';
$flash   = fn(array $res, string $ok) => set_flash($res['succes'] ? 'success' : 'error', $res['succes'] ? $ok : ($res['message'] ?? 'Erreur.'));
$mailer  = function () { require_once APP_ROOT . '/includes/mailer_courriers.php'; };
$courant = fn() => courrier_charger($cid) ?: $cour;
$sortant = $cour['sens'] === 'SORTANT';
$utilisateur = function (int $id): ?array {
    if ($id <= 0) return null;
    $s = get_db()->prepare('SELECT id, prenoms, nom, email FROM utilisateurs WHERE id=?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
};
$enregistreur = fn() => array_filter([$utilisateur((int)($cour['enregistre_par'] ?? 0))]);

switch ($action) {

    // ── Supprimer un courrier ──
    case 'supprimer':
        exiger_permission('COURRIERS', 'SUPPRIMER');
        $res = courrier_supprimer($cid);
        $flash($res, 'Courrier supprimé.');
        $retour = '/modules/courriers/index.php';
        break;

    // ── Supprimer un document ──
    case 'supprimer_doc':
        exiger_permission('COURRIERS', 'MODIFIER');
        if (courrier_sortant_verrouille($cour)) { set_flash('error', 'Ce courrier a été validé par le DG : ses documents ne peuvent plus être modifiés.'); break; }
        $res = courrier_doc_supprimer((int)($_POST['doc_id'] ?? 0));
        $flash($res, 'Document supprimé.');
        break;

    // ── Affecter : DG / super admin uniquement ──
    case 'affecter':
        if (!courrier_est_dg()) { set_flash('error', 'Seuls le DG et le super administrateur peuvent affecter un courrier.'); break; }
        if ($sortant) { set_flash('error', 'Un courrier sortant ne s\'affecte pas : il est validé par le DG puis envoyé.'); break; }
        $copies = array_map('intval', (array)($_POST['copies'] ?? []));
        $res = courrier_affecter_equipe($cid, (int)($_POST['service_id'] ?? 0) ?: null, (int)($_POST['pilote'] ?? 0),
            $copies, clean($_POST['date_limite'] ?? '') ?: null, trim($_POST['instruction'] ?? ''));
        $flash($res, 'Affectation enregistrée.');
        if ($res['succes'] && !empty($res['nouveaux'])) {
            $mailer();
            $c = $courant();
            $pilote = courrier_pilote($cid);
            $c['delai_pilote'] = $pilote['date_limite'] ?? null;
            $copies_new = [];
            foreach (courrier_affectations($cid) as $a) {
                $role = $res['nouveaux'][(int)$a['utilisateur_id']] ?? null;
                if ($role === 'PILOTE') {
                    courrier_notif_creer((int)$a['utilisateur_id'], $cid, 'Courrier à traiter',
                        'Vous êtes l\'agent pilote du courrier « ' . $c['objet'] . ' »' . ($c['delai_pilote'] ? ' — délai : ' . date('d/m/Y', strtotime($c['delai_pilote'])) : '') . '.');
                    courrier_notifier_affectation($a, $c);
                } elseif ($role === 'COPIE') {
                    $copies_new[] = $a;
                }
            }
            courrier_diffuser($copies_new, $c, 'Courrier en copie',
                'Le DG vous a mis en copie du courrier « ' . $c['objet'] . ' » pour information. Vous pouvez le consulter et échanger avec l\'agent pilote.',
                (string)($c['instruction'] ?? ''));
        }
        break;

    // ── Pilote : démarrer le traitement ──
    case 'commencer':
        $res = courrier_commencer($cid, $uid);
        $flash($res, 'Traitement démarré.');
        break;

    // ── Pilote : soumettre au DG (compte rendu + pièces de réponse) ──
    case 'soumettre':
        $res = courrier_soumettre($cid, $uid, trim($_POST['message'] ?? ''));
        $flash($res, 'Traitement soumis au DG pour validation.');
        if ($res['succes']) {
            if (!empty($_FILES['reponses']['name']) && is_array($_FILES['reponses']['name'])) {
                foreach ($_FILES['reponses']['name'] as $k => $nom) {
                    $err = (int)($_FILES['reponses']['error'][$k] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    $r = courrier_doc_ajouter($cid, [
                        'name' => $nom, 'tmp_name' => $_FILES['reponses']['tmp_name'][$k],
                        'error' => $err, 'size' => $_FILES['reponses']['size'][$k],
                    ], 'REPONSE');
                    if (!$r['succes']) set_flash('error', 'Document non enregistré — « ' . $nom . ' » : ' . $r['message']);
                }
            }
            $mailer();
            $p = courrier_pilote($cid);
            $dest = courrier_utilisateurs_dg();
            if (!empty($p['affecte_par'])) {
                $s = get_db()->prepare('SELECT id, prenoms, nom, email FROM utilisateurs WHERE id=?');
                $s->execute([(int)$p['affecte_par']]);
                if ($u = $s->fetch()) $dest[] = $u;
            }
            courrier_diffuser($dest, $courant(), 'Traitement soumis pour validation',
                trim(($p['nom_complet'] ?? 'L\'agent pilote')) . ' a terminé le traitement et attend votre validation.',
                trim($_POST['message'] ?? ''));
        }
        $retour = $fil;
        break;

    // ── DG : valider un courrier sortant avant envoi ──
    case 'valider':
        $msg = trim($_POST['message'] ?? '');
        if ($sortant) {
            $res = courrier_sortant_valider($cid, $msg);
            $flash($res, 'Courrier validé : il peut maintenant être envoyé au destinataire.');
            if ($res['succes']) {
                $mailer();
                courrier_diffuser($enregistreur(), $courant(), 'Courrier sortant validé',
                    'Le DG a validé le courrier sortant. Vous pouvez maintenant l\'envoyer au destinataire depuis sa fiche.', $msg);
            }
            break;
        }
        // DG : valider le traitement du pilote
        $res = courrier_valider($cid, $msg);
        $flash($res, 'Traitement validé : le courrier est marqué « Traité ».');
        if ($res['succes']) {
            $mailer();
            $c = $courant();
            courrier_diffuser([$res['pilote']], $c, 'Traitement validé', 'Le DG a validé votre traitement du courrier.', $msg);
            $copies = array_filter(courrier_affectations($cid), fn($a) => $a['role'] === 'COPIE');
            courrier_diffuser($copies, $c, 'Courrier traité', 'Le traitement du courrier a été validé par le DG.', '', false);
        }
        $retour = $fil;
        break;

    // ── DG : renvoyer un sortant en correction, ou un traitement au pilote ──
    case 'rejeter':
        $motif = trim($_POST['motif'] ?? '');
        if ($sortant) {
            $res = courrier_sortant_renvoyer($cid, $motif);
            $flash($res, 'Courrier renvoyé pour correction.');
            if ($res['succes']) {
                $mailer();
                courrier_diffuser($enregistreur(), $courant(), 'Courrier sortant à corriger',
                    'Le DG demande des corrections avant l\'envoi. Modifiez le courrier puis soumettez-le à nouveau.', $motif);
            }
            $retour = $fil;
            break;
        }
        $res = courrier_rejeter($cid, $motif);
        $flash($res, 'Traitement renvoyé à l\'agent pilote.');
        if ($res['succes']) {
            $mailer();
            courrier_diffuser([$res['pilote']], $courant(), 'Traitement à revoir',
                'Le DG vous renvoie le courrier : des compléments sont attendus avant validation.', $motif);
        }
        $retour = $fil;
        break;

    // ── DG : clôturer ──
    case 'cloturer':
        if ($sortant) { set_flash('error', 'Un courrier sortant se termine par son envoi au destinataire.'); break; }
        $com = trim($_POST['commentaire'] ?? '');
        $res = courrier_cloturer($cid, $com);
        $flash($res, 'Courrier clôturé.');
        if ($res['succes']) {
            $mailer();
            courrier_diffuser(courrier_affectations($cid), $courant(), 'Courrier clôturé', 'Le DG a clôturé ce courrier.', $com, false);
        }
        break;

    // ── Archiver (DG, ou secrétariat avec la permission VALIDER) ──
    case 'archiver':
        if (!courrier_est_dg() && !a_permission('COURRIERS', 'VALIDER')) { set_flash('error', 'Action non autorisée.'); break; }
        if ($sortant && $cour['statut'] !== 'CLOTURE') { set_flash('error', 'Un courrier sortant ne peut être archivé qu\'après son envoi.'); break; }
        if (!in_array($cour['statut'], ['TRAITE', 'CLOTURE'], true)) { set_flash('error', 'Seul un courrier traité ou clôturé peut être archivé.'); break; }
        $res = courrier_changer_statut($cid, 'ARCHIVE', 'Courrier archivé');
        $flash($res, 'Courrier archivé.');
        break;

    // ── Sortant corrigé : soumis à nouveau au DG ──
    case 'resoumettre':
        $msg = trim($_POST['message'] ?? '');
        $res = courrier_sortant_resoumettre($cour, $msg);
        $flash($res, 'Courrier soumis à nouveau au DG pour validation.');
        if ($res['succes']) {
            $mailer();
            courrier_notifier_sortant_a_valider($courant(), true, $msg);
        }
        $retour = $fil;
        break;

    // ── Sortant validé, canal Email : envoi au destinataire depuis info@ ──
    case 'envoyer':
        if (!courrier_peut_expedier($cour)) { set_flash('error', 'Ce courrier doit être validé par le DG avant l\'envoi, par le DG ou la personne qui l\'a enregistré.'); break; }
        $to    = strtolower(trim($_POST['destinataire'] ?? ''));
        $sujet = clean($_POST['sujet'] ?? '');
        $corps = trim($_POST['message'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { set_flash('error', 'L\'adresse email du destinataire est invalide.'); break; }
        if ($sujet === '' || $corps === '')         { set_flash('error', 'L\'objet et le message de l\'email sont obligatoires.'); break; }

        $choisis = array_map('intval', (array)($_POST['docs'] ?? []));
        $fichiers = [];
        $poids = 0;
        foreach ($cour['documents'] as $d) {
            if (!in_array((int)$d['id'], $choisis, true)) continue;
            $abs = APP_ROOT . '/' . ltrim($d['chemin_fichier'], '/');
            if (!is_file($abs)) { set_flash('error', 'Pièce jointe introuvable sur le serveur : « ' . $d['nom_original'] . ' ».'); break 2; }
            $poids += filesize($abs);
            $fichiers[] = ['chemin' => $abs, 'nom' => $d['nom_original']];
        }
        if ($poids > 20 * 1024 * 1024) { set_flash('error', 'Les pièces jointes dépassent 20 Mo au total : décochez-en une partie.'); break; }

        $mailer();
        $env = courrier_envoyer_reponse($cour, $to, $sujet, $corps, $fichiers);
        if (!$env['succes']) { set_flash('error', 'Le courrier n\'a pas été envoyé : ' . $env['message']); break; }

        $res = courrier_sortant_expedier($cour, 'EMAIL',
            'Envoyé par email à ' . $to . ' depuis ' . (defined('MAIL_INFO_ADDRESS') && MAIL_INFO_ADDRESS !== '' ? MAIL_INFO_ADDRESS : MAIL_FROM)
            . ' — ' . count($fichiers) . ' pièce' . (count($fichiers) > 1 ? 's jointes' : ' jointe') . '. Objet : ' . $sujet,
            $env['message_id'] ?? null);
        $flash($res, 'Courrier envoyé à ' . $to . '.');
        if ($res['succes']) {
            courrier_diffuser(array_merge(courrier_utilisateurs_dg(), $enregistreur()), $courant(),
                'Courrier sortant envoyé', 'Le courrier a été envoyé par email à ' . $to . '.', '', false);
        }
        break;

    // ── Sortant validé, envoi papier : lettre remise ou postée ──
    case 'expedier':
        if (!courrier_peut_expedier($cour)) { set_flash('error', 'Ce courrier doit être validé par le DG avant l\'expédition, par le DG ou la personne qui l\'a enregistré.'); break; }
        $date = clean($_POST['date_expedition'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) { set_flash('error', 'La date d\'expédition est invalide (elle ne peut pas être dans le futur).'); break; }
        $comment = clean($_POST['commentaire'] ?? '');
        $res = courrier_sortant_expedier($cour, 'PHYSIQUE',
            'Expédié le ' . date('d/m/Y', strtotime($date)) . ($comment !== '' ? ' — ' . $comment : ''));
        $flash($res, 'Courrier marqué comme expédié.');
        if ($res['succes']) {
            $mailer();
            courrier_diffuser(array_merge(courrier_utilisateurs_dg(), $enregistreur()), $courant(),
                'Courrier sortant expédié', 'Le courrier a été expédié le ' . date('d/m/Y', strtotime($date)) . '.', $comment, false);
        }
        break;

    // ── Fil d'échanges : DG, agents affectés et enregistreur d'un sortant ──
    case 'message':
        if (!courrier_peut_ecrire($cour)) { set_flash('error', 'Seuls le DG et les personnes concernées peuvent écrire ici.'); break; }
        $msg = trim($_POST['message'] ?? '');
        $res = courrier_echange_ajouter($cid, $msg);
        if (!$res['succes']) { set_flash('error', $res['message']); }
        else {
            $mailer();
            $dest = courrier_affectations($cid);
            $p = courrier_pilote($cid);
            if ($sortant) { $dest = array_merge($dest, courrier_utilisateurs_dg(), $enregistreur()); }
            elseif (!empty($p['affecte_par']) && ($u = $utilisateur((int)$p['affecte_par']))) { $dest[] = $u; }
            else { $dest = array_merge($dest, courrier_utilisateurs_dg()); }
            $moi = user_courant();
            courrier_diffuser($dest, $courant(), 'Nouveau message',
                trim(($moi['prenoms'] ?? '') . ' ' . ($moi['nom'] ?? '')) . ' a écrit sur le courrier.', $msg);
        }
        $retour = $fil;
        break;

    default:
        set_flash('error', 'Action non reconnue.');
}

redirect($retour);
