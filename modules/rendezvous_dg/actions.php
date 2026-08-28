<?php
// modules/rendezvous_dg/actions.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/rendezvous_dg.php';

session_demarrer();
exiger_connexion();
rdv_dg_assurer_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    set_flash('error', 'Session expirée.');
    redirect('/modules/rendezvous_dg/index.php');
}

$action = clean($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($action === 'creer') {
    exiger_permission(RDV_DG_MODULE, 'CREER');
    $u = user_courant();
    $pid = null;
    try { $pid = personnel_assurer_profil((int)user_id()); } catch (Throwable $e) {}
    $piece = null;
    if (!empty($_FILES['piece_jointe']['name'])) {
        $up = rdv_dg_upload_piece($_FILES['piece_jointe']);
        if (!$up['succes']) {
            set_flash('error', $up['message'] ?? 'Erreur fichier.');
            redirect('/modules/rendezvous_dg/form.php');
        }
        $piece = $up['chemin'];
    }
    $dispo = (int)($_POST['disponibilite_id'] ?? 0);
    $date = clean($_POST['date_confirmee'] ?? '');
    $heure = clean($_POST['heure_debut'] ?? '');
    $r = rdv_dg_creer([
        'type_rdv' => clean($_POST['type_rdv'] ?? ''),
        'type_autre' => clean($_POST['type_autre'] ?? ''),
        'demandeur_utilisateur_id' => (int)user_id(),
        'demandeur_personnel_id' => $pid,
        'nom' => clean($_POST['nom'] ?? ($u['nom'] ?? '')),
        'prenoms' => clean($_POST['prenoms'] ?? ($u['prenoms'] ?? '')),
        'email' => clean($_POST['email'] ?? ($u['email'] ?? '')),
        'telephone' => clean($_POST['telephone'] ?? ($u['telephone'] ?? '')),
        'organisation' => clean($_POST['organisation'] ?? 'CRICT'),
        'fonction' => clean($_POST['fonction'] ?? ($u['groupe_libelle'] ?? '')),
        'objet' => clean($_POST['objet'] ?? ''),
        'note_demandeur' => clean($_POST['note_demandeur'] ?? ''),
        'piece_jointe' => $piece,
        'disponibilite_id' => $dispo,
        'date_confirmee' => $date,
        'heure_debut' => $heure,
    ], 'INTERNE');
    if (!$r['succes']) {
        set_flash('error', $r['message'] ?? 'Erreur.');
        $q = http_build_query(['dispo' => $dispo, 'date' => $date, 'heure' => substr($heure, 0, 5)]);
        redirect('/modules/rendezvous_dg/form.php?' . $q);
    }
    set_flash('success', 'Créneau réservé (' . ($r['reference'] ?? '') . '). En attente de traitement.');
    redirect('/modules/rendezvous_dg/voir.php?id=' . (int)$r['id']);
}

if ($action === 'confirmer') {
    if (!rdv_dg_peut_gerer()) {
        set_flash('error', 'Action non autorisée.');
        redirect('/modules/rendezvous_dg/index.php');
    }
    $r = rdv_dg_confirmer($id, [
        'lieu' => clean($_POST['lieu'] ?? ''),
        'commentaire_gestion' => clean($_POST['commentaire_gestion'] ?? ''),
    ]);
    set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? 'Erreur.');
    redirect('/modules/rendezvous_dg/voir.php?id=' . $id);
}

if ($action === 'refuser') {
    if (!rdv_dg_peut_gerer()) {
        set_flash('error', 'Action non autorisée.');
        redirect('/modules/rendezvous_dg/index.php');
    }
    $r = rdv_dg_refuser($id, clean($_POST['motif_refus'] ?? ''));
    set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? 'Erreur.');
    redirect('/modules/rendezvous_dg/voir.php?id=' . $id);
}

if ($action === 'annuler') {
    $r = rdv_dg_annuler($id, clean($_POST['motif'] ?? ''));
    set_flash($r['succes'] ? 'success' : 'error', $r['message'] ?? 'Erreur.');
    redirect('/modules/rendezvous_dg/voir.php?id=' . $id);
}

if ($action === 'dispo_ajouter') {
    if (!rdv_dg_peut_gerer()) {
        set_flash('error', 'Action non autorisée.');
        redirect('/modules/rendezvous_dg/index.php');
    }
    $r = rdv_dg_dispo_ajouter($_POST);
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Créneau ajouté.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'dispo_modifier') {
    if (!rdv_dg_peut_gerer()) {
        set_flash('error', 'Action non autorisée.');
        redirect('/modules/rendezvous_dg/index.php');
    }
    $r = rdv_dg_dispo_modifier($id, $_POST);
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Créneau modifié.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'dispo_desactiver') {
    $r = rdv_dg_dispo_desactiver($id);
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Créneau suspendu.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'dispo_activer') {
    $r = rdv_dg_dispo_activer($id);
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Créneau réactivé.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'dispo_supprimer') {
    $r = rdv_dg_dispo_supprimer($id);
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Créneau supprimé.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'dispo_exception') {
    $r = rdv_dg_exception_ajouter(
        (int)($_POST['dispo_id'] ?? 0),
        clean($_POST['date_exception'] ?? ''),
        clean($_POST['motif'] ?? 'Indisponible')
    );
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Date bloquée pour ce créneau récurrent.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'dispo_exception_suppr') {
    $r = rdv_dg_exception_supprimer($id);
    set_flash($r['succes'] ? 'success' : 'error', $r['succes'] ? 'Exception retirée.' : ($r['message'] ?? 'Erreur.'));
    redirect('/modules/rendezvous_dg/disponibilites.php');
}

if ($action === 'notif_lu') {
    rdv_dg_notif_marquer_lu($id, (int)user_id());
    $goto = clean($_POST['redirect'] ?? '');
    redirect($goto !== '' ? $goto : '/modules/rendezvous_dg/notifications.php');
}

if ($action === 'notif_tout_lu') {
    rdv_dg_notif_tout_lu((int)user_id());
    set_flash('success', 'Notifications marquées comme lues.');
    redirect('/modules/rendezvous_dg/notifications.php');
}

set_flash('error', 'Action inconnue.');
redirect('/modules/rendezvous_dg/index.php');
