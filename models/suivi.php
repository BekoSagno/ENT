<?php
// ============================================================
// models/suivi.php  — Suivi & Évaluation Quotidien
// ============================================================

function suivi_fiche_du_jour(?int $user_id = null, ?string $date = null): ?array {
    $user_id = $user_id ?? user_id();
    $date    = $date ?? date('Y-m-d');
    $db      = get_db();
    $stmt    = $db->prepare("
        SELECT f.*, CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               CONCAT(v.prenoms,' ',v.nom) AS validateur_nom
        FROM suivi_fiches_journalieres f
        JOIN utilisateurs u ON u.id = f.utilisateur_id
        LEFT JOIN utilisateurs v ON v.id = f.valide_par
        WHERE f.utilisateur_id = ? AND f.date_fiche = ?
    ");
    $stmt->execute([$user_id, $date]);
    $fiche = $stmt->fetch();
    if (!$fiche) return null;
    $fiche['activites'] = suivi_activites_par_fiche((int)$fiche['id']);
    return $fiche;
}

function suivi_activites_par_fiche(int $fiche_id): array {
    $stmt = get_db()->prepare("
        SELECT * FROM suivi_activites_journalieres
        WHERE fiche_id = ? ORDER BY ordre
    ");
    $stmt->execute([$fiche_id]);
    return $stmt->fetchAll();
}

function suivi_creer_ou_recuperer_fiche(int $user_id, string $date): int {
    $db   = get_db();
    $stmt = $db->prepare("SELECT id FROM suivi_fiches_journalieres WHERE utilisateur_id=? AND date_fiche=?");
    $stmt->execute([$user_id, $date]);
    $fiche = $stmt->fetch();
    if ($fiche) return (int)$fiche['id'];

    $db->prepare("INSERT INTO suivi_fiches_journalieres (utilisateur_id, date_fiche, statut) VALUES (?,?,'BROUILLON')")
       ->execute([$user_id, $date]);
    return (int)$db->lastInsertId();
}

function suivi_sauvegarder_activites(int $fiche_id, array $activites, string $objectifs_lendemain = ''): bool {
    $db = get_db();

    // Vérifier fiche encore modifiable (uniquement BROUILLON ou REJETEE)
    $stmt = $db->prepare("SELECT statut FROM suivi_fiches_journalieres WHERE id=?");
    $stmt->execute([$fiche_id]);
    $fiche = $stmt->fetch();
    if (!$fiche || in_array($fiche['statut'], ['SOUMISE','VALIDEE'])) return false;

    // ── UPSERT : on préserve les id des activités existantes ──
    // (un DELETE + réinsertion casserait les liens des pièces jointes → activités orphelines)
    $stmt = $db->prepare("SELECT id FROM suivi_activites_journalieres WHERE fiche_id=?");
    $stmt->execute([$fiche_id]);
    $ids_existants = array_map('intval', array_column($stmt->fetchAll(), 'id'));

    $up = $db->prepare("
        UPDATE suivi_activites_journalieres
        SET ordre=?, objectif_tache=?, duree_estimee=?, atteint=?, difficultes=?, solutions=?, documents_sortie=?, modifie_le=NOW()
        WHERE id=? AND fiche_id=?
    ");
    $ins = $db->prepare("
        INSERT INTO suivi_activites_journalieres
        (fiche_id, ordre, objectif_tache, duree_estimee, atteint, difficultes, solutions, documents_sortie)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $ids_conserves = [];
    $ordre = 0;

    foreach ($activites as $act) {
        if (empty(trim($act['objectif_tache'] ?? ''))) continue;
        $ordre++;
        $atteint = isset($act['atteint']) ? ($act['atteint'] === 'oui' ? 1 : ($act['atteint'] === 'non' ? 0 : null)) : null;
        $duree   = !empty($act['duree_estimee']) ? (int)$act['duree_estimee'] : null;
        $aid     = (int)($act['id'] ?? 0);

        if ($aid && in_array($aid, $ids_existants, true)) {
            // Activité existante → mise à jour (l'id ne change pas, les PJ restent rattachées)
            $up->execute([
                $ordre, $act['objectif_tache'], $duree, $atteint,
                $act['difficultes'] ?? null, $act['solutions'] ?? null, $act['documents_sortie'] ?? null,
                $aid, $fiche_id,
            ]);
            $ids_conserves[] = $aid;
        } else {
            // Nouvelle activité → insertion
            $ins->execute([
                $fiche_id, $ordre, $act['objectif_tache'], $duree, $atteint,
                $act['difficultes'] ?? null, $act['solutions'] ?? null, $act['documents_sortie'] ?? null,
            ]);
            $ids_conserves[] = (int)$db->lastInsertId();
        }
    }

    // Supprimer proprement les activités retirées (et leurs pièces jointes : base + fichiers)
    $a_supprimer = array_diff($ids_existants, $ids_conserves);
    if (!empty($a_supprimer)) {
        suivi_supprimer_activites_et_pj(array_values($a_supprimer));
    }

    // Mettre à jour objectifs lendemain
    $db->prepare("UPDATE suivi_fiches_journalieres SET objectifs_lendemain=?, modifie_le=NOW() WHERE id=?")
       ->execute([$objectifs_lendemain ?: null, $fiche_id]);

    log_activite(user_id(), 'SUIVI_QUOTIDIEN', 'SAUVEGARDE', "Fiche ID $fiche_id sauvegardée");
    return true;
}

/**
 * Supprime une liste d'activités ainsi que leurs pièces jointes (lignes en base + fichiers sur disque).
 */
function suivi_supprimer_activites_et_pj(array $activite_ids): void {
    $activite_ids = array_values(array_filter(array_map('intval', $activite_ids)));
    if (empty($activite_ids)) return;

    if (!defined('SUIVI_UPLOAD_BASE_DIR')) {
        define('SUIVI_UPLOAD_BASE_DIR', __DIR__ . '/../uploads/suivi/');
    }
    $db          = get_db();
    $placeholders = implode(',', array_fill(0, count($activite_ids), '?'));

    try {
        // Récupérer les fichiers à effacer du disque
        $stmt = $db->prepare("SELECT id, chemin FROM suivi_pieces_jointes WHERE activite_id IN ($placeholders)");
        $stmt->execute($activite_ids);
        foreach ($stmt->fetchAll() as $pj) {
            $abs = SUIVI_UPLOAD_BASE_DIR . $pj['chemin'];
            if (is_file($abs)) @unlink($abs);
        }
        // Supprimer les lignes PJ puis les activités
        $db->prepare("DELETE FROM suivi_pieces_jointes WHERE activite_id IN ($placeholders)")->execute($activite_ids);
        $db->prepare("DELETE FROM suivi_activites_journalieres WHERE id IN ($placeholders)")->execute($activite_ids);
    } catch (Throwable $e) {
        error_log('[CRICT suivi_supprimer_activites_et_pj] ' . $e->getMessage());
    }
}

function suivi_soumettre_fiche(int $fiche_id): array {
    $db   = get_db();
    $stmt = $db->prepare("SELECT * FROM suivi_fiches_journalieres WHERE id=? AND utilisateur_id=?");
    $stmt->execute([$fiche_id, user_id()]);
    $fiche = $stmt->fetch();

    if (!$fiche) return ['succes' => false, 'message' => 'Fiche introuvable.'];
    if (in_array($fiche['statut'], ['SOUMISE','VALIDEE'])) {
        return ['succes' => false, 'message' => 'Cette fiche a déjà été soumise.'];
    }

    // Vérifier qu'il y a au moins une activité
    $stmt2 = $db->prepare("SELECT COUNT(*) FROM suivi_activites_journalieres WHERE fiche_id=?");
    $stmt2->execute([$fiche_id]);
    if ((int)$stmt2->fetchColumn() === 0) {
        return ['succes' => false, 'message' => 'Veuillez saisir au moins une activité avant de soumettre.'];
    }

    $db->prepare("UPDATE suivi_fiches_journalieres SET statut='SOUMISE', modifie_le=NOW() WHERE id=?")
       ->execute([$fiche_id]);
    log_activite(user_id(), 'SUIVI_QUOTIDIEN', 'SOUMISSION', "Fiche ID $fiche_id soumise");
    return ['succes' => true];
}

function suivi_valider_fiche(int $fiche_id, string $commentaire = ''): array {
    $db = get_db();
    $db->prepare("UPDATE suivi_fiches_journalieres SET statut='VALIDEE', valide_par=?, valide_le=NOW(), commentaire_validateur=? WHERE id=?")
       ->execute([user_id(), $commentaire, $fiche_id]);
    log_activite(user_id(), 'SUIVI_QUOTIDIEN', 'VALIDATION', "Fiche ID $fiche_id validée");
    return ['succes' => true];
}

function suivi_rejeter_fiche(int $fiche_id, string $commentaire): array {
    $db = get_db();
    $db->prepare("UPDATE suivi_fiches_journalieres SET statut='REJETEE', valide_par=?, valide_le=NOW(), commentaire_validateur=? WHERE id=?")
       ->execute([user_id(), $commentaire, $fiche_id]);
    log_activite(user_id(), 'SUIVI_QUOTIDIEN', 'REJET', "Fiche ID $fiche_id rejetée");
    return ['succes' => true];
}

function suivi_lister_fiches(array $filtres = [], int $limite = 20, int $offset = 0): array {
    $db  = get_db();
    $sql = "SELECT f.id, f.date_fiche, f.statut, f.valide_le,
                   CONCAT(u.prenoms,' ',u.nom) AS nom_complet, u.matricule,
                   g.libelle AS groupe, g.departement,
                   COUNT(a.id) AS nb_activites,
                   SUM(CASE WHEN a.atteint=1 THEN 1 ELSE 0 END) AS nb_atteintes
            FROM suivi_fiches_journalieres f
            JOIN utilisateurs u ON u.id = f.utilisateur_id
            JOIN groupes_utilisateurs g ON g.id = u.groupe_id
            LEFT JOIN suivi_activites_journalieres a ON a.fiche_id = f.id
            WHERE 1=1";
    $params = [];

    if (!empty($filtres['utilisateur_id'])) {
        $sql .= " AND f.utilisateur_id = ?";
        $params[] = (int)$filtres['utilisateur_id'];
    }
    if (!empty($filtres['statut'])) {
        $sql .= " AND f.statut = ?";
        $params[] = $filtres['statut'];
    }
    if (!empty($filtres['date_debut'])) {
        $sql .= " AND f.date_fiche >= ?";
        $params[] = $filtres['date_debut'];
    }
    if (!empty($filtres['date_fin'])) {
        $sql .= " AND f.date_fiche <= ?";
        $params[] = $filtres['date_fin'];
    }
    if (!empty($filtres['departement'])) {
        $sql .= " AND g.departement = ?";
        $params[] = $filtres['departement'];
    }

    $sql .= " GROUP BY f.id ORDER BY f.date_fiche DESC, u.nom";

    // Compter
    $count = $db->prepare("SELECT COUNT(*) FROM ($sql) t");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return ['data' => $stmt->fetchAll(), 'total' => $total];
}

function suivi_statistiques_semaine(int $user_id): array {
    $db    = get_db();
    // Semaine complète : lundi → dimanche (inclut le week-end, donc le jour même)
    $debut = date('Y-m-d', strtotime('monday this week'));
    $fin   = date('Y-m-d', strtotime($debut . ' +6 days'));

    $stmt = $db->prepare("
        SELECT f.date_fiche, f.statut,
               COUNT(a.id) AS nb_activites,
               SUM(CASE WHEN a.atteint=1 THEN 1 ELSE 0 END) AS nb_atteintes
        FROM suivi_fiches_journalieres f
        LEFT JOIN suivi_activites_journalieres a ON a.fiche_id = f.id
        WHERE f.utilisateur_id=? AND f.date_fiche BETWEEN ? AND ?
        GROUP BY f.id ORDER BY f.date_fiche
    ");
    $stmt->execute([$user_id, $debut, $fin]);
    return $stmt->fetchAll();
}

/**
 * Vue d'équipe pour la semaine en cours (lundi → dimanche).
 * Destinée au DG / Super Admin qui ne remplissent pas de fiche personnelle.
 * Retourne un total par jour : nb de fiches, soumises, validées.
 */
function suivi_statistiques_semaine_globale(): array {
    $db    = get_db();
    $debut = date('Y-m-d', strtotime('monday this week'));
    $fin   = date('Y-m-d', strtotime($debut . ' +6 days'));

    $stmt = $db->prepare("
        SELECT f.date_fiche,
               COUNT(*) AS nb_fiches,
               SUM(CASE WHEN f.statut='SOUMISE' THEN 1 ELSE 0 END) AS nb_soumises,
               SUM(CASE WHEN f.statut='VALIDEE' THEN 1 ELSE 0 END) AS nb_validees
        FROM suivi_fiches_journalieres f
        WHERE f.date_fiche BETWEEN ? AND ?
        GROUP BY f.date_fiche
        ORDER BY f.date_fiche
    ");
    $stmt->execute([$debut, $fin]);

    // Indexer par date pour combler les jours manquants côté affichage
    $par_date = [];
    foreach ($stmt->fetchAll() as $r) {
        $par_date[$r['date_fiche']] = [
            'nb_fiches'   => (int)$r['nb_fiches'],
            'nb_soumises' => (int)$r['nb_soumises'],
            'nb_validees' => (int)$r['nb_validees'],
        ];
    }
    return $par_date;
}

function suivi_fiches_a_valider(): array {
    try {
        $stmt = get_db()->query("
            SELECT f.id, f.date_fiche, f.cree_le,
                   CONCAT(u.prenoms,' ',u.nom) AS nom_complet, u.matricule,
                   g.libelle AS groupe, COUNT(a.id) AS nb_activites
            FROM suivi_fiches_journalieres f
            JOIN utilisateurs u ON u.id = f.utilisateur_id
            JOIN groupes_utilisateurs g ON g.id = u.groupe_id
            LEFT JOIN suivi_activites_journalieres a ON a.fiche_id = f.id
            WHERE f.statut = 'SOUMISE'
            GROUP BY f.id ORDER BY f.date_fiche ASC
        ");
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        error_log('[CRICT] suivi_fiches_a_valider: ' . $e->getMessage());
        return [];
    }
}