<?php
// ============================================================
// models/conges.php — Gestion des congés (Phase 2)
// ============================================================

function conges_types_lister(bool $actifs = true): array {
    $sql = "SELECT * FROM types_conges";
    if ($actifs) $sql .= " WHERE actif = 1";
    $sql .= " ORDER BY libelle";
    return get_db()->query($sql)->fetchAll();
}

function conges_type_par_id(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM types_conges WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Nombre de jours (calendaires, bornes incluses).
// Aussi défini dans demandes.php (function_exists) pour déploiement isolé.
if (!function_exists('conges_nb_jours')) {
    function conges_nb_jours(string $debut, string $fin): float {
        $d = strtotime($debut);
        $f = strtotime($fin);
        if (!$d || !$f || $f < $d) return 0;
        return (float)(floor(($f - $d) / 86400) + 1);
    }
}

// Nombre de jours ouvrés (lundi → vendredi), week-ends exclus, bornes incluses.
// NB : les jours fériés ne sont pas encore pris en compte (phase 1).
// Aussi défini dans demandes.php (function_exists) pour déploiement isolé.
if (!function_exists('demandes_nb_jours_ouvres')) {
    function demandes_nb_jours_ouvres(string $debut, string $fin): float {
        $d = strtotime($debut);
        $f = strtotime($fin);
        if (!$d || !$f || $f < $d) return 0;
        $n = 0;
        for ($t = $d; $t <= $f; $t += 86400) {
            if ((int)date('N', $t) <= 5) $n++;
        }
        return (float)$n;
    }
}

// Solde (créé à la volée si absent), pour une année donnée
function conges_solde(int $personnel_id, int $type_id, int $annee): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM soldes_conges WHERE personnel_id=? AND type_id=? AND annee=?");
    $stmt->execute([$personnel_id, $type_id, $annee]);
    $solde = $stmt->fetch();
    if (!$solde) {
        $type = conges_type_par_id($type_id);
        $acquis = $type ? (float)$type['jours_par_an'] : 0;
        $db->prepare("INSERT INTO soldes_conges (personnel_id, type_id, annee, jours_acquis, jours_pris) VALUES (?, ?, ?, ?, 0)")
           ->execute([$personnel_id, $type_id, $annee, $acquis]);
        $stmt->execute([$personnel_id, $type_id, $annee]);
        $solde = $stmt->fetch();
    }
    $solde['jours_restants'] = (float)$solde['jours_acquis'] - (float)$solde['jours_pris'];
    return $solde;
}

// Tableau des soldes (tous types actifs) pour l'année
function conges_soldes_personnel(int $personnel_id, int $annee): array {
    $out = [];
    foreach (conges_types_lister(true) as $t) {
        $s = conges_solde($personnel_id, (int)$t['id'], $annee);
        $out[] = [
            'type_id'   => (int)$t['id'],
            'libelle'   => $t['libelle'],
            'plafonne'  => (float)$t['jours_par_an'] > 0,
            'acquis'    => (float)$s['jours_acquis'],
            'pris'      => (float)$s['jours_pris'],
            'restants'  => (float)$s['jours_restants'],
        ];
    }
    return $out;
}

// ------------------------------------------------------------
//  Demandes
// ------------------------------------------------------------
function conges_demande_creer(int $personnel_id, int $type_id, string $debut, string $fin, string $motif): array {
    if (!$type_id)                       return ['succes' => false, 'message' => 'Type de congé requis.'];
    $jours = conges_nb_jours($debut, $fin);
    if ($jours <= 0)                     return ['succes' => false, 'message' => 'Dates invalides.'];

    get_db()->prepare("INSERT INTO demandes_conges (personnel_id, type_id, date_debut, date_fin, nombre_jours, motif)
                       VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$personnel_id, $type_id, $debut, $fin, $jours, $motif !== '' ? $motif : null]);
    $id = (int)get_db()->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'CONGE_DEMANDE', "Demande de congé ID $id ($jours j.)");
    return ['succes' => true, 'id' => $id, 'jours' => $jours];
}

function conges_demande_par_id(int $id): ?array {
    $stmt = get_db()->prepare("
        SELECT d.*, t.libelle AS type_libelle, t.jours_par_an,
               p.responsable_id, CONCAT_WS(' ', u.prenoms, u.nom) AS employe_nom
        FROM demandes_conges d
        JOIN types_conges t ON t.id = d.type_id
        JOIN personnel p    ON p.id = d.personnel_id
        JOIN utilisateurs u ON u.id = p.utilisateur_id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function conges_demandes_personnel(int $personnel_id): array {
    $stmt = get_db()->prepare("
        SELECT d.*, t.libelle AS type_libelle
        FROM demandes_conges d
        JOIN types_conges t ON t.id = d.type_id
        WHERE d.personnel_id = ?
        ORDER BY d.cree_le DESC
    ");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

// Demandes en attente que ce validateur peut traiter
//  - responsable direct (owner.responsable_id == validateur_pid)
//  - OU droit RH global (PERSONNEL/VALIDER) -> toutes
function conges_demandes_a_valider(int $validateur_pid, bool $peut_rh): array {
    $db = get_db();
    $sql = "SELECT d.*, t.libelle AS type_libelle,
                   CONCAT_WS(' ', u.prenoms, u.nom) AS employe_nom, p.responsable_id
            FROM demandes_conges d
            JOIN types_conges t ON t.id = d.type_id
            JOIN personnel p    ON p.id = d.personnel_id
            JOIN utilisateurs u ON u.id = p.utilisateur_id
            WHERE d.statut = 'EN_ATTENTE'";
    $params = [];
    if (!$peut_rh) {
        $sql .= " AND p.responsable_id = ?";
        $params[] = $validateur_pid;
    }
    $sql .= " ORDER BY d.cree_le ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Autorisation de valider une demande donnée
function conges_peut_valider(array $demande, int $validateur_pid, bool $peut_rh): bool {
    if ($peut_rh) return true;
    return (int)($demande['responsable_id'] ?? 0) === $validateur_pid && $validateur_pid > 0;
}

function conges_demande_valider(int $id, string $commentaire = ''): array {
    $db = get_db();
    $d  = conges_demande_par_id($id);
    if (!$d)                              return ['succes' => false, 'message' => 'Demande introuvable.'];
    if ($d['statut'] !== 'EN_ATTENTE')    return ['succes' => false, 'message' => 'Demande déjà traitée.'];

    $annee  = (int)date('Y', strtotime($d['date_debut']));
    $solde  = conges_solde((int)$d['personnel_id'], (int)$d['type_id'], $annee);
    $plafonne = (float)$d['jours_par_an'] > 0;

    if ($plafonne && ((float)$solde['jours_pris'] + (float)$d['nombre_jours']) > (float)$solde['jours_acquis']) {
        return ['succes' => false, 'message' => 'Solde insuffisant (' . $solde['jours_restants'] . ' j. restants).'];
    }

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE demandes_conges SET statut='APPROUVE', valide_par=?, commentaire_validation=? WHERE id=?")
           ->execute([user_id(), $commentaire !== '' ? $commentaire : null, $id]);
        $db->prepare("UPDATE soldes_conges SET jours_pris = jours_pris + ? WHERE id = ?")
           ->execute([(float)$d['nombre_jours'], $solde['id']]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT] validation congé : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la validation.'];
    }

    // Bascule du statut RH si le congé couvre aujourd'hui
    $auj = date('Y-m-d');
    if ($d['date_debut'] <= $auj && $auj <= $d['date_fin']) {
        // nécessite personnel.php (statut sync)
        if (function_exists('personnel_changer_statut')) {
            personnel_changer_statut((int)$d['personnel_id'], 'CONGE');
        }
    }

    log_activite(user_id(), 'PERSONNEL', 'CONGE_APPROUVE', "Congé ID $id approuvé");
    return ['succes' => true];
}

function conges_demande_refuser(int $id, string $commentaire = ''): array {
    $d = conges_demande_par_id($id);
    if (!$d)                           return ['succes' => false, 'message' => 'Demande introuvable.'];
    if ($d['statut'] !== 'EN_ATTENTE') return ['succes' => false, 'message' => 'Demande déjà traitée.'];

    get_db()->prepare("UPDATE demandes_conges SET statut='REFUSE', valide_par=?, commentaire_validation=? WHERE id=?")
            ->execute([user_id(), $commentaire !== '' ? $commentaire : null, $id]);
    log_activite(user_id(), 'PERSONNEL', 'CONGE_REFUSE', "Congé ID $id refusé");
    return ['succes' => true];
}

// Annulation par l'employé (en attente, ou approuvé à venir -> restitue le solde)
function conges_demande_annuler(int $id, int $personnel_id): array {
    $db = get_db();
    $d  = conges_demande_par_id($id);
    if (!$d || (int)$d['personnel_id'] !== $personnel_id) return ['succes' => false, 'message' => 'Demande introuvable.'];

    if ($d['statut'] === 'EN_ATTENTE') {
        $db->prepare("UPDATE demandes_conges SET statut='ANNULE' WHERE id=?")->execute([$id]);
        log_activite(user_id(), 'PERSONNEL', 'CONGE_ANNULE', "Congé ID $id annulé");
        return ['succes' => true];
    }

    if ($d['statut'] === 'APPROUVE' && $d['date_debut'] > date('Y-m-d')) {
        $annee = (int)date('Y', strtotime($d['date_debut']));
        $solde = conges_solde($personnel_id, (int)$d['type_id'], $annee);
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE demandes_conges SET statut='ANNULE' WHERE id=?")->execute([$id]);
            $db->prepare("UPDATE soldes_conges SET jours_pris = GREATEST(0, jours_pris - ?) WHERE id = ?")
               ->execute([(float)$d['nombre_jours'], $solde['id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['succes' => false, 'message' => 'Erreur lors de l\'annulation.'];
        }
        log_activite(user_id(), 'PERSONNEL', 'CONGE_ANNULE', "Congé ID $id annulé (solde restitué)");
        return ['succes' => true];
    }

    return ['succes' => false, 'message' => 'Cette demande ne peut plus être annulée.'];
}

// Pour le tableau de bord : nombre de demandes en attente à traiter
function conges_compte_a_valider(int $validateur_pid, bool $peut_rh): int {
    return count(conges_demandes_a_valider($validateur_pid, $peut_rh));
}
