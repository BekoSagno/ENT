<?php
// ============================================================
// models/utilisateur.php  — CRUD Utilisateurs
//   (révisé : création transactionnelle utilisateur + profil RH)
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

    // Générer matricule (lecture seule, hors transaction)
    $matricule = generer_matricule($data['groupe_code'] ?? 'USR');

    $sel  = bin2hex(random_bytes(16));
    $hash = hash_password($data['mot_de_passe']);

    // ── INVARIANT : tout utilisateur possède un profil RH ──
    //    Création atomique (utilisateur + personnel) : si l'un
    //    échoue, on annule tout (rollback). Aucun trigger requis.
    try {
        $db->beginTransaction();

        $sql = "INSERT INTO utilisateurs
                (groupe_id, matricule, nom, prenoms, email, telephone, mot_de_passe, sel,
                 genre, date_naissance, adresse, grade, specialite, date_prise_poste,
                 statut, force_mdp, cree_par)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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

        // Profil RH minimal (le RH l'enrichira ensuite)
        $db->prepare("INSERT INTO personnel (utilisateur_id, statut_rh, cree_par)
                      VALUES (?, 'ACTIF', ?)")
           ->execute([$new_id, user_id()]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[CRICT] Création utilisateur+profil : ' . $e->getMessage());
        return ['succes' => false, 'message' => "Erreur lors de la création de l'utilisateur."];
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
