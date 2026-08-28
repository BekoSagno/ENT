<?php
// ============================================================
// models/utilisateur.php  — CRUD Utilisateurs
// ============================================================

function utilisateur_lister(array $filtres = [], int $limite = 20, int $offset = 0): array {
    $db  = get_db();
    $sql = "SELECT u.id, u.matricule, u.nom, u.prenoms, u.email, u.telephone,
                   u.photo, u.statut, u.genre, u.grade, u.specialite,
                   u.date_prise_poste, u.derniere_connexion, u.cree_le,
                   g.code AS groupe_code, g.libelle AS groupe_libelle, g.departement
            FROM utilisateurs u
            JOIN groupes_utilisateurs g ON g.id = u.groupe_id
            WHERE 1=1";
    $params = [];

    if (!empty($filtres['recherche'])) {
        $sql .= " AND (u.nom LIKE ? OR u.prenoms LIKE ? OR u.email LIKE ? OR u.matricule LIKE ?)";
        $r = '%' . $filtres['recherche'] . '%';
        $params = array_merge($params, [$r, $r, $r, $r]);
    }
    if (!empty($filtres['groupe_id'])) {
        $sql .= " AND u.groupe_id = ?";
        $params[] = (int)$filtres['groupe_id'];
    }
    if (!empty($filtres['statut'])) {
        $sql .= " AND u.statut = ?";
        $params[] = $filtres['statut'];
    }
    if (!empty($filtres['departement'])) {
        $sql .= " AND g.departement = ?";
        $params[] = $filtres['departement'];
    }

    // Compter total
    $count_sql = str_replace(
        "SELECT u.id, u.matricule, u.nom, u.prenoms, u.email, u.telephone,
                   u.photo, u.statut, u.genre, u.grade, u.specialite,
                   u.date_prise_poste, u.derniere_connexion, u.cree_le,
                   g.code AS groupe_code, g.libelle AS groupe_libelle, g.departement",
        "SELECT COUNT(*)",
        $sql
    );
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $sql .= " ORDER BY u.nom, u.prenoms LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['data' => $stmt->fetchAll(), 'total' => $total];
}

function utilisateur_par_id(int $id): ?array {
    $db   = get_db();
    $stmt = $db->prepare("
        SELECT u.*, g.code AS groupe_code, g.libelle AS groupe_libelle, g.departement
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function utilisateur_creer(array $data): array {
    $db = get_db();

    // Unicité email
    $check = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        return ['succes' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    // Générer matricule
    $matricule = generer_matricule($data['groupe_code'] ?? 'USR');

    $sel = bin2hex(random_bytes(16));
    $hash = hash_password($data['mot_de_passe']);

    $sql = "INSERT INTO utilisateurs
            (groupe_id, matricule, nom, prenoms, email, telephone, mot_de_passe, sel,
             genre, date_naissance, adresse, grade, specialite, date_prise_poste,
             statut, force_mdp, cree_par)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $db->beginTransaction();
    try {
        $db->prepare($sql)->execute([
            $data['groupe_id'],
            $matricule,
            strtoupper($data['nom']),
            ucwords(strtolower($data['prenoms'])),
            strtolower($data['email']),
            $data['telephone'] ?? null,
            $hash,
            $sel,
            $data['genre'] ?? null,
            !empty($data['date_naissance']) ? $data['date_naissance'] : null,
            $data['adresse'] ?? null,
            $data['grade'] ?? null,
            $data['specialite'] ?? null,
            !empty($data['date_prise_poste']) ? $data['date_prise_poste'] : null,
            $data['statut'] ?? 'ACTIF',
            1, // force changement mdp à 1ère connexion
            user_id(),
        ]);

        $new_id = (int)$db->lastInsertId();
        $statut_rh = ($data['statut'] ?? 'ACTIF') === 'ACTIF' ? 'ACTIF' : 'SUSPENDU';
        $db->prepare("
            INSERT INTO personnel (utilisateur_id, statut_rh, cree_par)
            VALUES (?, ?, ?)
        ")->execute([$new_id, $statut_rh, user_id()]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT] création utilisateur : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Impossible de créer cet utilisateur.'];
    }

    log_activite(user_id(), 'UTILISATEURS', 'CREATION', "Création utilisateur ID $new_id – $matricule");
    return ['succes' => true, 'id' => $new_id, 'matricule' => $matricule];
}

function utilisateur_modifier(int $id, array $data): array {
    $db = get_db();

    $avant = utilisateur_par_id($id);
    if (!$avant) return ['succes' => false, 'message' => 'Utilisateur introuvable.'];

    // Unicité email (sauf lui-même)
    $check = $db->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
    $check->execute([$data['email'], $id]);
    if ($check->fetch()) {
        return ['succes' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    $sql = "UPDATE utilisateurs SET
                groupe_id=?, nom=?, prenoms=?, email=?, telephone=?,
                genre=?, date_naissance=?, adresse=?, grade=?, specialite=?,
                date_prise_poste=?, statut=?
            WHERE id=?";

    $db->prepare($sql)->execute([
        $data['groupe_id'],
        strtoupper($data['nom']),
        ucwords(strtolower($data['prenoms'])),
        strtolower($data['email']),
        $data['telephone'] ?? null,
        $data['genre'] ?? null,
        !empty($data['date_naissance']) ? $data['date_naissance'] : null,
        $data['adresse'] ?? null,
        $data['grade'] ?? null,
        $data['specialite'] ?? null,
        !empty($data['date_prise_poste']) ? $data['date_prise_poste'] : null,
        $data['statut'],
        $id,
    ]);

    log_activite(user_id(), 'UTILISATEURS', 'MODIFICATION', "Modification utilisateur ID $id",
        'SUCCES', $avant, utilisateur_par_id($id));
    return ['succes' => true];
}

function utilisateur_changer_mdp(int $id, string $nouveau_mdp): bool {
    $db = get_db();
    $hash = hash_password($nouveau_mdp);
    $db->prepare("UPDATE utilisateurs SET mot_de_passe=?, force_mdp=0, sel=? WHERE id=?")
       ->execute([$hash, bin2hex(random_bytes(16)), $id]);
    log_activite($id, 'UTILISATEURS', 'CHANGEMENT_MDP', "Changement de mot de passe ID $id");
    return true;
}

function utilisateur_changer_statut(int $id, string $statut): bool {
    $db = get_db();
    $db->prepare("UPDATE utilisateurs SET statut=? WHERE id=?")->execute([$statut, $id]);
    log_activite(user_id(), 'UTILISATEURS', "STATUT_$statut", "Changement statut utilisateur ID $id");
    return true;
}

function generer_matricule(string $groupe_code): string {
    $db     = get_db();
    $prefix = strtoupper(substr($groupe_code, 0, 3));
    $stmt   = $db->prepare("SELECT COUNT(*) FROM utilisateurs u JOIN groupes_utilisateurs g ON g.id=u.groupe_id WHERE g.code=?");
    $stmt->execute([$groupe_code]);
    $count = (int)$stmt->fetchColumn() + 1;
    return $prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function groupes_lister(): array {
    return get_db()->query("SELECT * FROM groupes_utilisateurs WHERE actif=1 ORDER BY niveau, libelle")->fetchAll();
}

function permissions_utilisateur_specifiques(int $user_id): array {
    $stmt = get_db()->prepare("
        SELECT ps.*, p.action, m.code AS module_code, m.libelle AS module_libelle, ps.accorde
        FROM permissions_specifiques ps
        JOIN permissions p ON p.id = ps.permission_id
        JOIN modules m ON m.id = p.module_id
        WHERE ps.utilisateur_id = ?
        ORDER BY m.ordre, p.action
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function sauvegarder_permissions_specifiques(int $user_id, array $permissions): void {
    $db = get_db();
    $db->prepare("DELETE FROM permissions_specifiques WHERE utilisateur_id=?")->execute([$user_id]);
    $stmt = $db->prepare("INSERT INTO permissions_specifiques (utilisateur_id, permission_id, accorde, accorde_par) VALUES (?,?,?,?)");
    foreach ($permissions as $perm_id => $accorde) {
        $stmt->execute([$user_id, (int)$perm_id, $accorde ? 1 : 0, user_id()]);
    }
    log_activite(user_id(), 'UTILISATEURS', 'PERMISSIONS_MAJ', "Mise à jour permissions spécifiques user ID $user_id");
}

function statistiques_utilisateurs(): array {
    $db = get_db();
    return [
        'total'      => (int)$db->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn(),
        'actifs'     => (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='ACTIF'")->fetchColumn(),
        'inactifs'   => (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='INACTIF'")->fetchColumn(),
        'suspendus'  => (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='SUSPENDU'")->fetchColumn(),
        'par_groupe' => $db->query("
            SELECT g.libelle, g.code, COUNT(u.id) AS total
            FROM groupes_utilisateurs g
            LEFT JOIN utilisateurs u ON u.groupe_id = g.id AND u.statut != 'ARCHIVE'
            GROUP BY g.id ORDER BY g.niveau
        ")->fetchAll(),
    ];
}

// ============================================================
// À AJOUTER DANS : models/utilisateur.php
// (à coller à la fin du fichier, avant la balise fermante si présente)
// ============================================================

// Tous les modules actifs avec leurs permissions, regroupés par module.
// Utilisé par la page voir.php pour afficher la matrice des droits spécifiques.
function modules_avec_permissions(): array {
    $rows = get_db()->query("
        SELECT m.id AS module_id, m.code AS module_code, m.libelle AS module_libelle,
               m.icone AS module_icone, m.ordre,
               p.id AS permission_id, p.action, p.libelle AS permission_libelle
        FROM modules m
        JOIN permissions p ON p.module_id = m.id
        WHERE m.actif = 1
        ORDER BY m.ordre, FIELD(p.action,'VOIR','CREER','MODIFIER','SUPPRIMER','VALIDER','EXPORTER'), p.action
    ")->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $code = $r['module_code'];
        if (!isset($out[$code])) {
            $out[$code] = [
                'code'        => $code,
                'libelle'     => $r['module_libelle'],
                'icone'       => $r['module_icone'],
                'permissions' => [],
            ];
        }
        $out[$code]['permissions'][] = [
            'id'      => (int)$r['permission_id'],
            'action'  => $r['action'],
            'libelle' => $r['permission_libelle'],
        ];
    }
    return array_values($out);
}

// Permissions de groupe (héritées) d'un utilisateur, indexées par permission_id.
// Permet d'afficher le droit "de base" à côté de l'override spécifique.
function permissions_groupe_utilisateur(int $groupe_id): array {
    $stmt = get_db()->prepare("
        SELECT gp.permission_id, gp.accorde
        FROM groupes_permissions gp
        WHERE gp.groupe_id = ?
    ");
    $stmt->execute([$groupe_id]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['permission_id']] = (bool)$r['accorde'];
    }
    return $map;
}

