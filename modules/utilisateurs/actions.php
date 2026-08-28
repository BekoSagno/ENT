<?php
// modules/utilisateurs/actions.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';        // ← envoi d'emails
require_once __DIR__ . '/../../models/utilisateur.php';

session_demarrer();
exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/modules/utilisateurs/index.php'); }
if (!csrf_verify()) { set_flash('error', 'Requête invalide.'); redirect('/modules/utilisateurs/index.php'); }

$action = clean($_POST['action'] ?? '');

switch ($action) {

    case 'creer':
        exiger_permission('UTILISATEURS', 'CREER');
        $data = [
            'groupe_id'        => (int)$_POST['groupe_id'],
            'groupe_code'      => '', // sera résolu
            'nom'              => clean($_POST['nom'] ?? ''),
            'prenoms'          => clean($_POST['prenoms'] ?? ''),
            'email'            => clean($_POST['email'] ?? ''),
            'telephone'        => clean($_POST['telephone'] ?? ''),
            'genre'            => clean($_POST['genre'] ?? ''),
            'date_naissance'   => clean($_POST['date_naissance'] ?? ''),
            'adresse'          => clean($_POST['adresse'] ?? ''),
            'grade'            => clean($_POST['grade'] ?? ''),
            'specialite'       => clean($_POST['specialite'] ?? ''),
            'date_prise_poste' => clean($_POST['date_prise_poste'] ?? ''),
            'mot_de_passe'     => $_POST['mot_de_passe'] ?? '',
            'statut'           => 'ACTIF',
        ];

        // Récupérer le code du groupe
        $db = get_db();
        $g = $db->prepare("SELECT code FROM groupes_utilisateurs WHERE id=?");
        $g->execute([$data['groupe_id']]);
        $grp = $g->fetch();
        if ($grp) $data['groupe_code'] = $grp['code'];

        if (empty($data['nom']) || empty($data['prenoms']) || empty($data['email']) || empty($data['mot_de_passe'])) {
            set_flash('error', 'Veuillez remplir tous les champs obligatoires.');
            redirect('/modules/utilisateurs/index.php');
        }

        $res = utilisateur_creer($data);
        if ($res['succes']) {
            // ── Notification des identifiants par email (non bloquant) ──
            $mail = notifier_identifiants_utilisateur([
                'prenoms'   => $data['prenoms'],
                'nom'       => $data['nom'],
                'email'     => $data['email'],
                'matricule' => $res['matricule'],
            ], $data['mot_de_passe']);

            if ($mail['succes']) {
                set_flash('success',
                    'Utilisateur créé (matricule ' . $res['matricule'] . '). '
                    . 'Les identifiants ont été envoyés à ' . $data['email'] . '.');
            } else {
                // L'utilisateur est bien créé, on signale juste l'échec d'envoi.
                set_flash('warn',
                    'Utilisateur créé (matricule ' . $res['matricule'] . '), '
                    . 'mais l\'email n\'a pas pu être envoyé : ' . $mail['message']);
            }
        } else {
            set_flash('error', $res['message']);
        }
        break;

    case 'changer_statut':
        exiger_permission('UTILISATEURS', 'MODIFIER');
        $id     = (int)$_POST['id'];
        $statut = clean($_POST['statut'] ?? '');
        $allowed = ['ACTIF','INACTIF','SUSPENDU','ARCHIVE'];
        if (!in_array($statut, $allowed)) {
            set_flash('error', 'Statut invalide.');
            break;
        }
        if ($id === user_id()) {
            set_flash('error', 'Vous ne pouvez pas modifier votre propre statut.');
            break;
        }
        utilisateur_changer_statut($id, $statut);
        $labels = ['ACTIF'=>'réactivé','INACTIF'=>'désactivé','SUSPENDU'=>'suspendu','ARCHIVE'=>'archivé'];
        set_flash('success', 'Utilisateur ' . ($labels[$statut] ?? $statut) . ' avec succès.');
        break;

    case 'reset_mdp':
        exiger_permission('UTILISATEURS', 'MODIFIER');
        $id  = (int)$_POST['id'];
        $mdp = $_POST['nouveau_mdp'] ?? '';
        if (strlen($mdp) < 6) {
            set_flash('error', 'Le mot de passe doit comporter au moins 6 caractères.');
            redirect('/modules/utilisateurs/voir.php?id=' . $id);
        }
        utilisateur_changer_mdp($id, $mdp);
        // Forcer changement à la prochaine connexion
        get_db()->prepare("UPDATE utilisateurs SET force_mdp=1 WHERE id=?")->execute([$id]);

        // ── Notifier le nouveau mot de passe par email (non bloquant) ──
        $u = utilisateur_par_id($id);
        if ($u) {
            notifier_identifiants_utilisateur([
                'prenoms'   => $u['prenoms'],
                'nom'       => $u['nom'],
                'email'     => $u['email'],
                'matricule' => $u['matricule'],
            ], $mdp);
        }

        set_flash('success', 'Mot de passe réinitialisé. L\'utilisateur devra le changer à sa prochaine connexion.');
        redirect('/modules/utilisateurs/voir.php?id=' . $id);

    case 'sauvegarder_permissions':
        exiger_permission('UTILISATEURS', 'MODIFIER');
        $id = (int)$_POST['utilisateur_id'];
        $perms = $_POST['permissions'] ?? [];
        sauvegarder_permissions_specifiques($id, $perms);
        set_flash('success', 'Permissions spécifiques mises à jour.');
        redirect('/modules/utilisateurs/voir.php?id=' . $id);
     case 'modifier':
        exiger_permission('UTILISATEURS', 'MODIFIER');
        $id = (int)($_POST['id'] ?? 0);

        $data = [
            'groupe_id'        => (int)$_POST['groupe_id'],
            'nom'              => clean($_POST['nom'] ?? ''),
            'prenoms'          => clean($_POST['prenoms'] ?? ''),
            'email'            => clean($_POST['email'] ?? ''),
            'telephone'        => clean($_POST['telephone'] ?? ''),
            'genre'            => clean($_POST['genre'] ?? ''),
            'date_naissance'   => clean($_POST['date_naissance'] ?? ''),
            'adresse'          => clean($_POST['adresse'] ?? ''),
            'grade'            => clean($_POST['grade'] ?? ''),
            'specialite'       => clean($_POST['specialite'] ?? ''),
            'date_prise_poste' => clean($_POST['date_prise_poste'] ?? ''),
            'statut'           => clean($_POST['statut'] ?? 'ACTIF'),
        ];

        // Sécurité : on ne change pas son propre statut
        if ($id === user_id()) {
            $moi = utilisateur_par_id($id);
            if ($moi) $data['statut'] = $moi['statut'];
        }

        if (empty($data['nom']) || empty($data['prenoms']) || empty($data['email']) || empty($data['groupe_id'])) {
            set_flash('error', 'Veuillez remplir tous les champs obligatoires.');
            redirect('/modules/utilisateurs/modifier.php?id=' . $id);
        }

        $res = utilisateur_modifier($id, $data);
        if ($res['succes']) {
            set_flash('success', 'Utilisateur mis à jour avec succès.');
            redirect('/modules/utilisateurs/voir.php?id=' . $id);
        } else {
            set_flash('error', $res['message']);
            redirect('/modules/utilisateurs/modifier.php?id=' . $id);
        }
        break;

    default:
        set_flash('error', 'Action non reconnue.');
}

redirect('/modules/utilisateurs/index.php');