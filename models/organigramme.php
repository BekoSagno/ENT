<?php
// ============================================================
// models/organigramme.php — Structure organisationnelle (Phase 2)
//   Unités auto-imbriquées, grades, postes, affectation.
// ============================================================

// ------------------------------------------------------------
//  UNITÉS ORGANISATIONNELLES
// ------------------------------------------------------------
function unites_lister(bool $actives_seulement = false): array {
    $sql = "SELECT uo.*,
                   CONCAT_WS(' ', ru.prenoms, ru.nom) AS responsable_nom,
                   par.nom AS parent_nom
            FROM unites_organisationnelles uo
            LEFT JOIN personnel rp             ON rp.id  = uo.responsable_id
            LEFT JOIN utilisateurs ru          ON ru.id  = rp.utilisateur_id
            LEFT JOIN unites_organisationnelles par ON par.id = uo.parent_id";
    if ($actives_seulement) $sql .= " WHERE uo.actif = 1";
    $sql .= " ORDER BY uo.nom";
    return get_db()->query($sql)->fetchAll();
}

// Construit l'arbre imbriqué à partir de la liste plate
function unites_arbre(): array {
    $plat = unites_lister();
    $index = [];
    foreach ($plat as $u) { $u['enfants'] = []; $index[$u['id']] = $u; }
    $racines = [];
    foreach ($index as $id => $u) {
        if ($u['parent_id'] && isset($index[$u['parent_id']])) {
            $index[$u['parent_id']]['enfants'][] = &$index[$id];
        } else {
            $racines[] = &$index[$id];
        }
    }
    return $racines;
}

function unite_par_id(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM unites_organisationnelles WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function unite_creer(array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO unites_organisationnelles (code, nom, type, parent_id, responsable_id, description)
                  VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([
            !empty($d['code']) ? $d['code'] : null,
            $d['nom'],
            $d['type'] ?? 'SERVICE',
            !empty($d['parent_id']) ? (int)$d['parent_id'] : null,
            !empty($d['responsable_id']) ? (int)$d['responsable_id'] : null,
            !empty($d['description']) ? $d['description'] : null,
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'UNITE_AJOUT', "Unité « {$d['nom']} » (ID $id)");
    return $id;
}

function unite_modifier(int $id, array $d): array {
    // Empêcher une unité d'être son propre parent
    if (!empty($d['parent_id']) && (int)$d['parent_id'] === $id) {
        return ['succes' => false, 'message' => "Une unité ne peut pas être son propre parent."];
    }
    get_db()->prepare("UPDATE unites_organisationnelles
                       SET code=?, nom=?, type=?, parent_id=?, responsable_id=?, description=?, actif=?
                       WHERE id=?")
            ->execute([
                !empty($d['code']) ? $d['code'] : null,
                $d['nom'],
                $d['type'] ?? 'SERVICE',
                !empty($d['parent_id']) ? (int)$d['parent_id'] : null,
                !empty($d['responsable_id']) ? (int)$d['responsable_id'] : null,
                !empty($d['description']) ? $d['description'] : null,
                isset($d['actif']) ? (int)$d['actif'] : 1,
                $id,
            ]);
    log_activite(user_id(), 'PERSONNEL', 'UNITE_MODIF', "Unité ID $id");
    return ['succes' => true];
}

function unite_supprimer(int $id): void {
    // FK ON DELETE SET NULL : enfants et postes sont détachés, non supprimés
    get_db()->prepare("DELETE FROM unites_organisationnelles WHERE id = ?")->execute([$id]);
    log_activite(user_id(), 'PERSONNEL', 'UNITE_SUPPR', "Unité ID $id");
}

// ------------------------------------------------------------
//  GRADES
// ------------------------------------------------------------
function grades_lister(bool $actifs_seulement = true): array {
    $sql = "SELECT * FROM grades";
    if ($actifs_seulement) $sql .= " WHERE actif = 1";
    $sql .= " ORDER BY categorie, niveau DESC, libelle";
    return get_db()->query($sql)->fetchAll();
}

function grade_creer(array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO grades (code, libelle, categorie, niveau) VALUES (?, ?, ?, ?)")
       ->execute([
            !empty($d['code']) ? $d['code'] : null,
            $d['libelle'],
            $d['categorie'] ?? 'AUTRE',
            (int)($d['niveau'] ?? 0),
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'GRADE_AJOUT', "Grade « {$d['libelle']} » (ID $id)");
    return $id;
}

// ------------------------------------------------------------
//  POSTES
// ------------------------------------------------------------
function postes_lister(bool $actifs_seulement = false): array {
    $sql = "SELECT po.*, uo.nom AS unite_nom, gr.libelle AS grade_libelle
            FROM postes po
            LEFT JOIN unites_organisationnelles uo ON uo.id = po.unite_id
            LEFT JOIN grades gr ON gr.id = po.grade_id";
    if ($actifs_seulement) $sql .= " WHERE po.actif = 1";
    $sql .= " ORDER BY uo.nom, po.intitule";
    return get_db()->query($sql)->fetchAll();
}

function poste_par_id(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM postes WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function poste_creer(array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO postes (code, intitule, unite_id, grade_id, description) VALUES (?, ?, ?, ?, ?)")
       ->execute([
            !empty($d['code']) ? $d['code'] : null,
            $d['intitule'],
            !empty($d['unite_id']) ? (int)$d['unite_id'] : null,
            !empty($d['grade_id']) ? (int)$d['grade_id'] : null,
            !empty($d['description']) ? $d['description'] : null,
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'POSTE_AJOUT', "Poste « {$d['intitule']} » (ID $id)");
    return $id;
}

function poste_modifier(int $id, array $d): void {
    get_db()->prepare("UPDATE postes SET code=?, intitule=?, unite_id=?, grade_id=?, description=?, actif=? WHERE id=?")
            ->execute([
                !empty($d['code']) ? $d['code'] : null,
                $d['intitule'],
                !empty($d['unite_id']) ? (int)$d['unite_id'] : null,
                !empty($d['grade_id']) ? (int)$d['grade_id'] : null,
                !empty($d['description']) ? $d['description'] : null,
                isset($d['actif']) ? (int)$d['actif'] : 1,
                $id,
            ]);
    log_activite(user_id(), 'PERSONNEL', 'POSTE_MODIF', "Poste ID $id");
}

function poste_supprimer(int $id): void {
    get_db()->prepare("DELETE FROM postes WHERE id = ?")->execute([$id]);
    log_activite(user_id(), 'PERSONNEL', 'POSTE_SUPPR', "Poste ID $id");
}

// ------------------------------------------------------------
//  AFFECTATION D'UN EMPLOYÉ (poste / grade / responsable)
// ------------------------------------------------------------
function personnel_affecter(int $personnel_id, ?int $poste_id, ?int $grade_id, ?int $responsable_id): array {
    if ($responsable_id !== null && $responsable_id === $personnel_id) {
        return ['succes' => false, 'message' => "Un employé ne peut pas être son propre responsable."];
    }
    get_db()->prepare("UPDATE personnel SET poste_id = ?, grade_id = ?, responsable_id = ? WHERE id = ?")
            ->execute([
                $poste_id ?: null,
                $grade_id ?: null,
                $responsable_id ?: null,
                $personnel_id,
            ]);
    log_activite(user_id(), 'PERSONNEL', 'AFFECTATION', "Affectation du profil ID $personnel_id");
    return ['succes' => true];
}

// Candidats responsables (tout le personnel sauf soi-même)
function responsables_candidats(int $exclure_personnel_id = 0): array {
    $stmt = get_db()->prepare("
        SELECT p.id, CONCAT_WS(' ', u.prenoms, u.nom) AS nom, po.intitule AS poste
        FROM personnel p
        JOIN utilisateurs u ON u.id = p.utilisateur_id
        LEFT JOIN postes po ON po.id = p.poste_id
        WHERE p.id != ? AND u.statut != 'ARCHIVE'
        ORDER BY u.nom, u.prenoms
    ");
    $stmt->execute([$exclure_personnel_id]);
    return $stmt->fetchAll();
}
