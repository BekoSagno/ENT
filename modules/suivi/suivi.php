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

    // Supprimer anciennes activités
    $db->prepare("DELETE FROM suivi_activites_journalieres WHERE fiche_id=?")->execute([$fiche_id]);

    // Réinsérer
    $stmt = $db->prepare("
        INSERT INTO suivi_activites_journalieres
        (fiche_id, ordre, objectif_tache, duree_estimee, atteint, difficultes, solutions, documents_sortie)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($activites as $i => $act) {
        if (empty(trim($act['objectif_tache'] ?? ''))) continue;
        $atteint = isset($act['atteint']) ? ($act['atteint'] === 'oui' ? 1 : ($act['atteint'] === 'non' ? 0 : null)) : null;
        $stmt->execute([
            $fiche_id,
            $i + 1,
            $act['objectif_tache'],
            !empty($act['duree_estimee']) ? (int)$act['duree_estimee'] : null,
            $atteint,
            $act['difficultes'] ?? null,
            $act['solutions'] ?? null,
            $act['documents_sortie'] ?? null,
        ]);
    }

    // Mettre à jour objectifs lendemain
    $db->prepare("UPDATE suivi_fiches_journalieres SET objectifs_lendemain=?, modifie_le=NOW() WHERE id=?")
       ->execute([$objectifs_lendemain ?: null, $fiche_id]);

    log_activite(user_id(), 'SUIVI_QUOTIDIEN', 'SAUVEGARDE', "Fiche ID $fiche_id sauvegardée");
    return true;
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

/**
 * Rattache un lot de fichiers téléversés (issus de $_FILES) à une activité.
 * Utilisé lors de la soumission unique du formulaire (plus besoin de brouillon préalable).
 *
 * @param int   $fiche_id
 * @param int   $activite_id  activité déjà enregistrée en base (id valide obligatoire)
 * @param array $files        un élément de $_FILES, ex: $_FILES['pj_0']
 * @param int   $user_id
 * @return array  ['ok' => int (nb réussis), 'erreurs' => string[]]
 */
function suivi_attacher_fichiers(int $fiche_id, int $activite_id, array $files, int $user_id): array {
    if (!defined('SUIVI_UPLOAD_BASE_DIR')) {
        define('SUIVI_UPLOAD_BASE_DIR', __DIR__ . '/../uploads/suivi/');
    }
    $max_size  = 10 * 1024 * 1024; // 10 Mo
    $ext_ok    = ['pdf','doc','docx','xls','xlsx','odt','ods'];
    $mime_ok   = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    // Normaliser : on peut recevoir un seul fichier ou un tableau (input multiple)
    $noms = $files['name'];
    if (!is_array($noms)) {
        $files = array_map(fn($v) => [$v], $files); // transformer en tableaux à 1 élément
        $noms  = $files['name'];
    }

    $db      = get_db();
    $ok      = 0;
    $erreurs = [];

    for ($i = 0; $i < count($noms); $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $erreurs[] = $files['name'][$i] . ' : erreur de transfert.';
            continue;
        }
        $nom_orig = $files['name'][$i];
        $tmp      = $files['tmp_name'][$i];
        $taille   = (int)$files['size'][$i];

        if ($taille > $max_size) { $erreurs[] = "$nom_orig : trop volumineux (max 10 Mo)."; continue; }

        $ext = strtolower(pathinfo($nom_orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $ext_ok, true)) { $erreurs[] = "$nom_orig : format non autorisé."; continue; }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp);
        if (!in_array($mime, $mime_ok, true)) { $erreurs[] = "$nom_orig : type de fichier non autorisé."; continue; }

        $sous_dossier = date('Y') . '/' . date('m') . '/fiche_' . $fiche_id . '/';
        $dossier_abs  = SUIVI_UPLOAD_BASE_DIR . $sous_dossier;
        if (!is_dir($dossier_abs) && !mkdir($dossier_abs, 0750, true)) {
            $erreurs[] = "$nom_orig : impossible de créer le dossier de stockage.";
            continue;
        }

        $nom_stockage = bin2hex(random_bytes(16)) . '.' . $ext;
        $chemin_rel   = $sous_dossier . $nom_stockage;
        $chemin_abs   = SUIVI_UPLOAD_BASE_DIR . $chemin_rel;

        if (!move_uploaded_file($tmp, $chemin_abs)) {
            $erreurs[] = "$nom_orig : échec de l'enregistrement.";
            continue;
        }

        try {
            $db->prepare("
                INSERT INTO suivi_pieces_jointes
                    (activite_id, fiche_id, nom_original, nom_stockage, chemin, type_mime, taille, extension, televerse_par)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $activite_id, $fiche_id, $nom_orig, $nom_stockage,
                $chemin_rel, $mime, $taille, $ext, $user_id,
            ]);
            $ok++;
        } catch (Throwable $e) {
            @unlink($chemin_abs);
            error_log('[CRICT suivi_attacher_fichiers] ' . $e->getMessage());
            $erreurs[] = "$nom_orig : erreur base de données.";
        }
    }

    return ['ok' => $ok, 'erreurs' => $erreurs];
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
    $debut = date('Y-m-d', strtotime('monday this week'));
    $fin   = date('Y-m-d', strtotime('friday this week'));

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