<?php
// ============================================================
// models/contrats.php — Gestion des contrats (Phase 2)
// ============================================================

function contrats_personnel(int $personnel_id): array {
    $stmt = get_db()->prepare("SELECT * FROM contrats WHERE personnel_id = ? ORDER BY date_debut DESC, id DESC");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function contrat_par_id(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM contrats WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function contrat_creer(int $personnel_id, array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO contrats (personnel_id, reference, type_contrat, intitule, date_debut, date_fin, statut, document, notes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([
            $personnel_id,
            !empty($d['reference']) ? $d['reference'] : null,
            $d['type_contrat'] ?? 'CDD',
            !empty($d['intitule']) ? $d['intitule'] : null,
            $d['date_debut'],
            !empty($d['date_fin']) ? $d['date_fin'] : null,
            $d['statut'] ?? 'EN_COURS',
            !empty($d['document']) ? $d['document'] : null,
            !empty($d['notes']) ? $d['notes'] : null,
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'CONTRAT_AJOUT', "Contrat ID $id (profil $personnel_id)");
    return $id;
}

function contrat_modifier(int $id, array $d): void {
    get_db()->prepare("UPDATE contrats SET reference=?, type_contrat=?, intitule=?, date_debut=?, date_fin=?, statut=?, notes=? WHERE id=?")
            ->execute([
                !empty($d['reference']) ? $d['reference'] : null,
                $d['type_contrat'] ?? 'CDD',
                !empty($d['intitule']) ? $d['intitule'] : null,
                $d['date_debut'],
                !empty($d['date_fin']) ? $d['date_fin'] : null,
                $d['statut'] ?? 'EN_COURS',
                !empty($d['notes']) ? $d['notes'] : null,
                $id,
            ]);
    log_activite(user_id(), 'PERSONNEL', 'CONTRAT_MODIF', "Contrat ID $id");
}

function contrat_supprimer(int $id, int $personnel_id): void {
    get_db()->prepare("DELETE FROM contrats WHERE id = ? AND personnel_id = ?")->execute([$id, $personnel_id]);
    log_activite(user_id(), 'PERSONNEL', 'CONTRAT_SUPPR', "Contrat ID $id");
}

// Statut « calculé » pour l'affichage (tient compte de la date du jour)
function contrat_statut_effectif(array $c): string {
    if ($c['statut'] === 'RESILIE' || $c['statut'] === 'RENOUVELE') return $c['statut'];
    if (!empty($c['date_fin']) && $c['date_fin'] < date('Y-m-d'))   return 'EXPIRE';
    return 'EN_COURS';
}

// Contrats arrivant à échéance dans les N prochains jours (alertes)
function contrats_expirant(int $jours = 30): array {
    $stmt = get_db()->prepare("
        SELECT c.*, CONCAT_WS(' ', u.prenoms, u.nom) AS employe_nom, p.id AS pid
        FROM contrats c
        JOIN personnel p    ON p.id = c.personnel_id
        JOIN utilisateurs u ON u.id = p.utilisateur_id
        WHERE c.statut = 'EN_COURS'
          AND c.date_fin IS NOT NULL
          AND c.date_fin >= CURDATE()
          AND c.date_fin <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY c.date_fin ASC
    ");
    $stmt->execute([$jours]);
    return $stmt->fetchAll();
}
