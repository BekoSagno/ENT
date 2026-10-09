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
        $res = courrier_doc_supprimer((int)($_POST['doc_id'] ?? 0));
        $flash($res, 'Document supprimé.');
        break;

    // ── Affecter : DG / super admin uniquement ──
    case 'affecter':
        if (!courrier_est_dg()) { set_flash('error', 'Seuls le DG et le super administrateur peuvent affecter un courrier.'); break; }
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

    // ── DG : valider le traitement ──
    case 'valider':
        $msg = trim($_POST['message'] ?? '');
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

    // ── DG : renvoyer au pilote ──
    case 'rejeter':
        $motif = trim($_POST['motif'] ?? '');
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
        if (!in_array($cour['statut'], ['TRAITE', 'CLOTURE'], true)) { set_flash('error', 'Seul un courrier traité ou clôturé peut être archivé.'); break; }
        $res = courrier_changer_statut($cid, 'ARCHIVE', 'Courrier archivé');
        $flash($res, 'Courrier archivé.');
        break;

    // ── Fil d'échanges : DG, super admin et agents affectés ──
    case 'message':
        if (!courrier_est_dg() && !courrier_participation($cid, $uid)) { set_flash('error', 'Seuls le DG et les agents affectés peuvent écrire ici.'); break; }
        $msg = trim($_POST['message'] ?? '');
        $res = courrier_echange_ajouter($cid, $msg);
        if (!$res['succes']) { set_flash('error', $res['message']); }
        else {
            $mailer();
            $dest = courrier_affectations($cid);
            $p = courrier_pilote($cid);
            $s = get_db()->prepare('SELECT id, prenoms, nom, email FROM utilisateurs WHERE id=?');
            if (!empty($p['affecte_par'])) { $s->execute([(int)$p['affecte_par']]); if ($u = $s->fetch()) $dest[] = $u; }
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
