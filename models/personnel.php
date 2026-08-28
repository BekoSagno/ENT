<?php
// ============================================================
// models/personnel.php — Module RH / Personnel (Phase 1)
//
//   Règle : l'identité fait foi dans `utilisateurs`.
//   `personnel` ne contient que des attributs RH.
//   utilisateur_id est toujours présent (1-1 obligatoire).
// ============================================================

// ------------------------------------------------------------
//  Synchro statut RH -> statut du compte (connexion)
// ------------------------------------------------------------
function personnel_statut_compte(string $statut_rh): string {
    return match ($statut_rh) {
        'ACTIF', 'CONGE', 'MISSION' => 'ACTIF',     // connexion autorisée
        'SUSPENDU'                  => 'SUSPENDU',  // bloqué
        'DEMISSION', 'RETRAITE'     => 'ARCHIVE',   // bloqué
        default                     => 'ACTIF',
    };
}

// ------------------------------------------------------------
//  Lecture
// ------------------------------------------------------------
function personnel_par_id(int $id): ?array {
    $stmt = get_db()->prepare("
        SELECT p.*, u.matricule, u.nom, u.prenoms, u.email, u.telephone, u.photo,
               u.genre, u.date_naissance, u.adresse, u.grade, u.specialite,
               u.date_prise_poste, u.statut AS statut_compte, u.derniere_connexion,
               g.code AS groupe_code, g.libelle AS groupe_libelle, g.departement
        FROM personnel p
        JOIN utilisateurs u          ON u.id = p.utilisateur_id
        JOIN groupes_utilisateurs g  ON g.id = u.groupe_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function personnel_par_utilisateur(int $utilisateur_id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM personnel WHERE utilisateur_id = ?");
    $stmt->execute([$utilisateur_id]);
    return $stmt->fetch() ?: null;
}

// Garantit l'existence d'un profil (filet de sécurité hors création normale)
function personnel_assurer_profil(int $utilisateur_id): int {
    $db = get_db();
    $existe = $db->prepare("SELECT id FROM personnel WHERE utilisateur_id = ?");
    $existe->execute([$utilisateur_id]);
    $id = $existe->fetchColumn();
    if ($id) return (int)$id;

    $db->prepare("INSERT INTO personnel (utilisateur_id, statut_rh, cree_par) VALUES (?, 'ACTIF', ?)")
       ->execute([$utilisateur_id, user_id()]);
    return (int)$db->lastInsertId();
}

// ------------------------------------------------------------
//  Listing (via la vue v_personnel)
// ------------------------------------------------------------
function personnel_lister(array $filtres = [], int $limite = 20, int $offset = 0): array {
    $db  = get_db();
    $sql = "SELECT * FROM v_personnel WHERE 1=1";
    $params = [];

    if (!empty($filtres['recherche'])) {
        $sql .= " AND (nom LIKE ? OR prenoms LIKE ? OR email LIKE ? OR matricule LIKE ? OR matricule_rh LIKE ?)";
        $r = '%' . $filtres['recherche'] . '%';
        $params = array_merge($params, [$r, $r, $r, $r, $r]);
    }
    if (!empty($filtres['statut_rh'])) {
        $sql .= " AND statut_rh = ?";
        $params[] = $filtres['statut_rh'];
    }
    if (!empty($filtres['type_contrat'])) {
        $sql .= " AND type_contrat = ?";
        $params[] = $filtres['type_contrat'];
    }
    if (!empty($filtres['departement'])) {
        $sql .= " AND departement = ?";
        $params[] = $filtres['departement'];
    }
    if (!empty($filtres['groupe_code'])) {
        $sql .= " AND groupe_code = ?";
        $params[] = $filtres['groupe_code'];
    }

    // Total
    $count = $db->prepare("SELECT COUNT(*) FROM (" . $sql . ") t");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql .= " ORDER BY nom, prenoms LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['data' => $stmt->fetchAll(), 'total' => $total];
}

// ------------------------------------------------------------
//  Mise à jour des informations RH (non sensibles)
// ------------------------------------------------------------
function personnel_modifier(int $id, array $data): array {
    $db    = get_db();
    $avant = personnel_par_id($id);
    if (!$avant) return ['succes' => false, 'message' => 'Profil introuvable.'];

    // Unicité matricule RH (s'il est renseigné)
    if (!empty($data['matricule_rh'])) {
        $chk = $db->prepare("SELECT id FROM personnel WHERE matricule_rh = ? AND id != ?");
        $chk->execute([$data['matricule_rh'], $id]);
        if ($chk->fetch()) {
            return ['succes' => false, 'message' => 'Ce matricule RH est déjà utilisé.'];
        }
    }

    $sql = "UPDATE personnel SET
                matricule_rh = ?, lieu_naissance = ?, nationalite = ?,
                situation_matrimoniale = ?, nombre_enfants = ?, fonction = ?,
                date_embauche = ?, type_contrat = ?
            WHERE id = ?";

    $db->prepare($sql)->execute([
        !empty($data['matricule_rh']) ? $data['matricule_rh'] : null,
        !empty($data['lieu_naissance']) ? $data['lieu_naissance'] : null,
        !empty($data['nationalite']) ? $data['nationalite'] : null,
        !empty($data['situation_matrimoniale']) ? $data['situation_matrimoniale'] : null,
        (int)($data['nombre_enfants'] ?? 0),
        !empty($data['fonction']) ? $data['fonction'] : null,
        !empty($data['date_embauche']) ? $data['date_embauche'] : null,
        !empty($data['type_contrat']) ? $data['type_contrat'] : null,
        $id,
    ]);

    log_activite(user_id(), 'PERSONNEL', 'MODIFICATION', "Modification profil RH ID $id",
        'SUCCES', $avant, personnel_par_id($id));
    return ['succes' => true];
}

// ------------------------------------------------------------
//  Changement de statut RH + synchro du compte (transaction)
// ------------------------------------------------------------
function personnel_changer_statut(int $id, string $statut_rh): array {
    $valides = ['ACTIF', 'CONGE', 'MISSION', 'SUSPENDU', 'DEMISSION', 'RETRAITE'];
    if (!in_array($statut_rh, $valides, true)) {
        return ['succes' => false, 'message' => 'Statut RH invalide.'];
    }

    $db  = get_db();
    $p   = personnel_par_id($id);
    if (!$p) return ['succes' => false, 'message' => 'Profil introuvable.'];

    $statut_compte = personnel_statut_compte($statut_rh);

    try {
        $db->beginTransaction();
        $db->prepare("UPDATE personnel SET statut_rh = ? WHERE id = ?")
           ->execute([$statut_rh, $id]);
        $db->prepare("UPDATE utilisateurs SET statut = ? WHERE id = ?")
           ->execute([$statut_compte, $p['utilisateur_id']]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT] changement statut RH : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors du changement de statut.'];
    }

    log_activite(user_id(), 'PERSONNEL', "STATUT_$statut_rh",
        "Statut RH du profil ID $id -> $statut_rh (compte : $statut_compte)");
    return ['succes' => true, 'statut_compte' => $statut_compte];
}

// ============================================================
//  SATELLITES
// ============================================================

// ---- CV (1-1) ----
function personnel_cv_get(int $personnel_id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM personnel_cv WHERE personnel_id = ?");
    $stmt->execute([$personnel_id]);
    return $stmt->fetch() ?: null;
}

function personnel_cv_sauvegarder(int $personnel_id, string $resume, ?string $fichier = null): void {
    $db  = get_db();
    $cur = personnel_cv_get($personnel_id);
    if ($cur) {
        if ($fichier !== null) {
            $db->prepare("UPDATE personnel_cv SET resume_professionnel = ?, fichier_cv = ? WHERE personnel_id = ?")
               ->execute([$resume, $fichier, $personnel_id]);
        } else {
            $db->prepare("UPDATE personnel_cv SET resume_professionnel = ? WHERE personnel_id = ?")
               ->execute([$resume, $personnel_id]);
        }
    } else {
        $db->prepare("INSERT INTO personnel_cv (personnel_id, resume_professionnel, fichier_cv) VALUES (?, ?, ?)")
           ->execute([$personnel_id, $resume, $fichier]);
    }
    log_activite(user_id(), 'PERSONNEL', 'CV', "Mise à jour CV du profil ID $personnel_id");
}

// ---- Formations ----
function personnel_formations_lister(int $personnel_id): array {
    $stmt = get_db()->prepare("SELECT * FROM personnel_formations WHERE personnel_id = ? ORDER BY date_debut DESC, id DESC");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function personnel_formation_ajouter(int $personnel_id, array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO personnel_formations
            (personnel_id, diplome, etablissement, pays, date_debut, date_fin, fichier_diplome)
            VALUES (?, ?, ?, ?, ?, ?, ?)")
       ->execute([
            $personnel_id,
            $d['diplome'],
            $d['etablissement'] ?? null,
            $d['pays'] ?? null,
            !empty($d['date_debut']) ? $d['date_debut'] : null,
            !empty($d['date_fin'])   ? $d['date_fin']   : null,
            $d['fichier_diplome'] ?? null,
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'FORMATION_AJOUT', "Formation ID $id (profil $personnel_id)");
    return $id;
}

function personnel_formation_supprimer(int $id, int $personnel_id): void {
    get_db()->prepare("DELETE FROM personnel_formations WHERE id = ? AND personnel_id = ?")
            ->execute([$id, $personnel_id]);
    log_activite(user_id(), 'PERSONNEL', 'FORMATION_SUPPR', "Formation ID $id (profil $personnel_id)");
}

// ---- Expériences ----
function personnel_experiences_lister(int $personnel_id): array {
    $stmt = get_db()->prepare("SELECT * FROM personnel_experiences WHERE personnel_id = ? ORDER BY date_debut DESC, id DESC");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function personnel_experience_ajouter(int $personnel_id, array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO personnel_experiences
            (personnel_id, organisation, poste, date_debut, date_fin, description)
            VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([
            $personnel_id,
            $d['organisation'],
            $d['poste'] ?? null,
            !empty($d['date_debut']) ? $d['date_debut'] : null,
            !empty($d['date_fin'])   ? $d['date_fin']   : null,
            $d['description'] ?? null,
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'EXPERIENCE_AJOUT', "Expérience ID $id (profil $personnel_id)");
    return $id;
}

function personnel_experience_supprimer(int $id, int $personnel_id): void {
    get_db()->prepare("DELETE FROM personnel_experiences WHERE id = ? AND personnel_id = ?")
            ->execute([$id, $personnel_id]);
    log_activite(user_id(), 'PERSONNEL', 'EXPERIENCE_SUPPR', "Expérience ID $id (profil $personnel_id)");
}

// ---- Certifications ----
function personnel_certifications_lister(int $personnel_id): array {
    $stmt = get_db()->prepare("SELECT * FROM personnel_certifications WHERE personnel_id = ? ORDER BY date_obtention DESC, id DESC");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function personnel_certification_ajouter(int $personnel_id, array $d): int {
    $db = get_db();
    $db->prepare("INSERT INTO personnel_certifications
            (personnel_id, certification, organisme, date_obtention, date_expiration, document)
            VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([
            $personnel_id,
            $d['certification'],
            $d['organisme'] ?? null,
            !empty($d['date_obtention'])  ? $d['date_obtention']  : null,
            !empty($d['date_expiration']) ? $d['date_expiration'] : null,
            $d['document'] ?? null,
       ]);
    $id = (int)$db->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'CERTIF_AJOUT', "Certification ID $id (profil $personnel_id)");
    return $id;
}

function personnel_certification_supprimer(int $id, int $personnel_id): void {
    get_db()->prepare("DELETE FROM personnel_certifications WHERE id = ? AND personnel_id = ?")
            ->execute([$id, $personnel_id]);
    log_activite(user_id(), 'PERSONNEL', 'CERTIF_SUPPR', "Certification ID $id (profil $personnel_id)");
}

// ---- Compétences ----
function competences_lister(bool $actives_seulement = true): array {
    $sql = "SELECT * FROM competences";
    if ($actives_seulement) $sql .= " WHERE actif = 1";
    $sql .= " ORDER BY categorie, nom";
    return get_db()->query($sql)->fetchAll();
}

function personnel_competences_lister(int $personnel_id): array {
    $stmt = get_db()->prepare("
        SELECT pc.competence_id, pc.niveau, c.nom, c.categorie
        FROM personnel_competences pc
        JOIN competences c ON c.id = pc.competence_id
        WHERE pc.personnel_id = ?
        ORDER BY c.categorie, c.nom
    ");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function personnel_competence_definir(int $personnel_id, int $competence_id, string $niveau): void {
    $niveaux = ['DEBUTANT', 'INTERMEDIAIRE', 'AVANCE', 'EXPERT'];
    if (!in_array($niveau, $niveaux, true)) $niveau = 'INTERMEDIAIRE';
    // upsert
    get_db()->prepare("
        INSERT INTO personnel_competences (personnel_id, competence_id, niveau)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)
    ")->execute([$personnel_id, $competence_id, $niveau]);
}

function personnel_competence_retirer(int $personnel_id, int $competence_id): void {
    get_db()->prepare("DELETE FROM personnel_competences WHERE personnel_id = ? AND competence_id = ?")
            ->execute([$personnel_id, $competence_id]);
}

// ============================================================
//  STATISTIQUES
// ============================================================
function personnel_statistiques(): array {
    $db = get_db();
    $par_statut = $db->query("SELECT statut_rh, COUNT(*) AS n FROM personnel GROUP BY statut_rh")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'total'     => (int)$db->query("SELECT COUNT(*) FROM personnel")->fetchColumn(),
        'actifs'    => (int)($par_statut['ACTIF']    ?? 0),
        'conge'     => (int)($par_statut['CONGE']    ?? 0),
        'mission'   => (int)($par_statut['MISSION']  ?? 0),
        'suspendus' => (int)($par_statut['SUSPENDU'] ?? 0),
        'sortis'    => (int)(($par_statut['DEMISSION'] ?? 0) + ($par_statut['RETRAITE'] ?? 0)),
    ];
}

// ============================================================
//  UPLOAD sécurisé d'une pièce (CV, diplôme, certification)
//   Retourne le chemin RELATIF à stocker (servi via téléchargement contrôlé).
// ============================================================
function personnel_upload_piece(array $file, string $sous_dossier): array {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['succes' => false, 'message' => 'Aucun fichier.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['succes' => false, 'message' => 'Erreur de téléversement.'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['succes' => false, 'message' => 'Fichier trop volumineux (max 5 Mo).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $map   = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];
    if (!isset($map[$mime])) {
        return ['succes' => false, 'message' => 'Format non autorisé (PDF, JPEG, PNG, WebP).'];
    }

    $rel_dir = 'rh/' . trim($sous_dossier, '/') . '/';
    $abs_dir = UPLOAD_DIR . $rel_dir;
    if (!is_dir($abs_dir)) {
        @mkdir($abs_dir, 0775, true);
    }
    $nom = bin2hex(random_bytes(10)) . '.' . $map[$mime];
    if (!move_uploaded_file($file['tmp_name'], $abs_dir . $nom)) {
        return ['succes' => false, 'message' => 'Enregistrement impossible.'];
    }
    return ['succes' => true, 'chemin' => $rel_dir . $nom, 'mime' => $mime];
}
