<?php
// ============================================================
// includes/auth.php  — Authentification & Contrôle d'accès
// ============================================================

function session_demarrer(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,   // true en HTTPS production
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// --- Connexion utilisateur ---
function auth_connecter(string $email, string $password): array {
    $db = get_db();

    $stmt = $db->prepare("
        SELECT u.*, g.code AS groupe_code, g.libelle AS groupe_libelle, g.niveau AS groupe_niveau
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        log_activite(null, 'AUTH', 'CONNEXION_ECHEC', "Email inconnu: $email", 'ECHEC');
        return ['succes' => false, 'message' => 'Identifiants incorrects.'];
    }

    // Vérifier verrouillage
    if (!empty($user['verrouille_jusqua'])) {
        $verrouille = new DateTime($user['verrouille_jusqua']);
        if (new DateTime() < $verrouille) {
            $restant = (new DateTime())->diff($verrouille);
            return ['succes' => false, 'message' => "Compte verrouillé. Réessayez dans {$restant->i} minute(s)."];
        }
    }

    // Vérifier statut
    if ($user['statut'] !== 'ACTIF') {
        log_activite($user['id'], 'AUTH', 'CONNEXION_ECHEC', 'Compte non actif', 'ECHEC');
        return ['succes' => false, 'message' => 'Compte désactivé. Contactez l\'administrateur.'];
    }

    // Vérifier mot de passe
    if (!verify_password($password, $user['mot_de_passe'])) {
        $tentatives = $user['tentatives_echec'] + 1;
        $verrouillage = null;
        if ($tentatives >= MAX_LOGIN_ATTEMPTS) {
            $verrouillage = (new DateTime())->modify('+' . LOCKOUT_DURATION . ' seconds')->format('Y-m-d H:i:s');
            $tentatives = 0;
        }
        $db->prepare("UPDATE utilisateurs SET tentatives_echec=?, verrouille_jusqua=? WHERE id=?")
           ->execute([$tentatives, $verrouillage, $user['id']]);
        log_activite($user['id'], 'AUTH', 'CONNEXION_ECHEC', 'Mot de passe incorrect', 'ECHEC');
        return ['succes' => false, 'message' => 'Identifiants incorrects.'];
    }

    // Réinitialiser les tentatives
    $db->prepare("UPDATE utilisateurs SET tentatives_echec=0, verrouille_jusqua=NULL, derniere_connexion=NOW() WHERE id=?")
       ->execute([$user['id']]);

    // Stocker en session
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'              => $user['id'],
        'matricule'       => $user['matricule'],
        'nom'             => $user['nom'],
        'prenoms'         => $user['prenoms'],
        'email'           => $user['email'],
        'photo'           => $user['photo'],
        'groupe_id'       => $user['groupe_id'],
        'groupe_code'     => $user['groupe_code'],
        'groupe_libelle'  => $user['groupe_libelle'],
        'groupe_niveau'   => $user['groupe_niveau'],
        'force_mdp'       => $user['force_mdp'],
        'connecte_le'     => time(),
    ];

    log_activite($user['id'], 'AUTH', 'CONNEXION', 'Connexion réussie');

    return ['succes' => true, 'force_mdp' => (bool)$user['force_mdp']];
}

// --- Déconnexion ---
function auth_deconnecter(): void {
    if (!empty($_SESSION['user'])) {
        log_activite($_SESSION['user']['id'], 'AUTH', 'DECONNEXION');
    }
    session_unset();
    session_destroy();
}

// --- Vérifier si connecté ---
function est_connecte(): bool {
    return !empty($_SESSION['user']['id']);
}

// --- Utilisateur courant ---
function user_courant(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_id(): ?int {
    return $_SESSION['user']['id'] ?? null;
}

function user_groupe(): ?string {
    return $_SESSION['user']['groupe_code'] ?? null;
}

// --- Exiger connexion ---
function exiger_connexion(): void {
    if (!est_connecte()) {
        redirect('/login.php');
    }
    // Expiration de session
    if (!empty($_SESSION['user']['connecte_le'])) {
        if (time() - $_SESSION['user']['connecte_le'] > SESSION_LIFETIME) {
            auth_deconnecter();
            redirect('/login.php?expire=1');
        }
        // Renouveler le timestamp
        $_SESSION['user']['connecte_le'] = time();
    }
}

// --- Vérification d'une permission ---
function a_permission(string $module_code, string $action): bool {
    if (!est_connecte()) return false;
    $user = user_courant();

    // Super Admin : accès total
    if ($user['groupe_code'] === 'SUPER_ADMIN') return true;

    $db = get_db();

    // 1. Chercher une permission spécifique (override)
    $stmt = $db->prepare("
        SELECT ps.accorde
        FROM permissions_specifiques ps
        JOIN permissions p ON p.id = ps.permission_id
        JOIN modules m ON m.id = p.module_id
        WHERE ps.utilisateur_id = ? AND m.code = ? AND p.action = ?
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $module_code, $action]);
    $specifique = $stmt->fetch();
    if ($specifique !== false) {
        return (bool)$specifique['accorde'];
    }

    // 2. Chercher la permission de groupe
    $stmt = $db->prepare("
        SELECT gp.accorde
        FROM groupes_permissions gp
        JOIN permissions p ON p.id = gp.permission_id
        JOIN modules m ON m.id = p.module_id
        WHERE gp.groupe_id = ? AND m.code = ? AND p.action = ? AND gp.accorde = 1
        LIMIT 1
    ");
    $stmt->execute([$user['groupe_id'], $module_code, $action]);
    return (bool)$stmt->fetch();
}

// --- Exiger une permission (sinon 403) ---
function exiger_permission(string $module_code, string $action): void {
    exiger_connexion();
    if (!a_permission($module_code, $action)) {
        http_response_code(403);
        include APP_ROOT . '/views/errors/403.php';
        exit;
    }
}

// --- Charger toutes les permissions de l'utilisateur (pour sidebar) ---
function charger_permissions_utilisateur(): array {
    $user = user_courant();
    if (!$user) return [];
    if ($user['groupe_code'] === 'SUPER_ADMIN') {
        // Retourner tous les modules
        $db = get_db();
        return $db->query("SELECT code FROM modules WHERE actif=1")->fetchAll(PDO::FETCH_COLUMN);
    }
    $db = get_db();
    $stmt = $db->prepare("
        SELECT DISTINCT m.code
        FROM groupes_permissions gp
        JOIN permissions p ON p.id = gp.permission_id
        JOIN modules m ON m.id = p.module_id
        WHERE gp.groupe_id = ? AND gp.accorde = 1 AND p.action = 'VOIR' AND m.actif = 1
    ");
    $stmt->execute([$user['groupe_id']]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
