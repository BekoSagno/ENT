<?php
// ============================================================
// models/auto_anaq.php — Auto-évaluation ANAQ (indépendant du DGRS)
// ============================================================

const AUTO_ANAQ_UPLOAD_SUBDIR = 'auto_anaq';
const AUTO_ANAQ_MAX_PIECE     = 20 * 1024 * 1024;
const AUTO_ANAQ_CANEVAS_CODE_DEFAUT = 'ANAQ_2025';

function auto_anaq_assurer_schema(): void {
    $db = get_db();
    $stmts = [
        "CREATE TABLE IF NOT EXISTS identite_auto_canevas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(40) NOT NULL,
            titre VARCHAR(255) NOT NULL,
            version VARCHAR(50) DEFAULT NULL,
            description TEXT,
            fichier_guide VARCHAR(500) DEFAULT NULL,
            fichier_nom VARCHAR(255) DEFAULT NULL,
            actif TINYINT(1) NOT NULL DEFAULT 0,
            date_publication DATE DEFAULT NULL,
            cree_par INT UNSIGNED DEFAULT NULL,
            cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modifie_par INT UNSIGNED DEFAULT NULL,
            modifie_le DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_auto_canevas_code (code),
            KEY idx_auto_canevas_actif (actif)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS identite_auto_champs (
            id tinyint UNSIGNED NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL, libelle_court varchar(120) NOT NULL,
            libelle varchar(255) NOT NULL, description text, icone varchar(50) NOT NULL DEFAULT 'fa-folder',
            ordre tinyint UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY uq_auto_champ_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS identite_auto_references (
            id smallint UNSIGNED NOT NULL AUTO_INCREMENT, champ_id tinyint UNSIGNED NOT NULL,
            code varchar(15) NOT NULL, libelle varchar(500) NOT NULL, ordre tinyint UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id), UNIQUE KEY uq_auto_ref_code (code), KEY fk_auto_ref_champ (champ_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS identite_auto_criteres (
            id smallint UNSIGNED NOT NULL AUTO_INCREMENT, reference_id smallint UNSIGNED NOT NULL,
            champ_id tinyint UNSIGNED NOT NULL, code varchar(15) NOT NULL,
            ligne_directrice text NOT NULL, question text NOT NULL, elements_preuves text,
            type_saisie enum('SCORE','NOMBRE') NOT NULL DEFAULT 'SCORE',
            ordre smallint UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id), UNIQUE KEY uq_auto_crit_code (code),
            KEY fk_auto_crit_ref (reference_id), KEY fk_auto_crit_champ (champ_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS identite_auto_contenu (
            critere_id smallint UNSIGNED NOT NULL, reponse text, preuves_disponibles text,
            score tinyint UNSIGNED DEFAULT NULL, commentaire text,
            modifie_par int UNSIGNED DEFAULT NULL, modifie_le datetime DEFAULT NULL,
            PRIMARY KEY (critere_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS identite_auto_pieces (
            id int UNSIGNED NOT NULL AUTO_INCREMENT, critere_id smallint UNSIGNED NOT NULL,
            nom_original varchar(255) NOT NULL, nom_stockage varchar(255) NOT NULL,
            chemin_fichier varchar(500) NOT NULL, type_mime varchar(100) DEFAULT NULL,
            taille_fichier int UNSIGNED DEFAULT NULL, depose_par int UNSIGNED DEFAULT NULL,
            cree_le datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), KEY idx_auto_piece_critere (critere_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS identite_auto_swot (
            champ_id tinyint UNSIGNED NOT NULL, forces text, faiblesses text,
            opportunites text, menaces text, modifie_par int UNSIGNED DEFAULT NULL,
            modifie_le datetime DEFAULT NULL, PRIMARY KEY (champ_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($stmts as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    // Liaison canevas ↔ champs (installations existantes)
    try { $db->exec("ALTER TABLE identite_auto_champs ADD COLUMN canevas_id INT UNSIGNED NULL AFTER id"); } catch (Throwable $e) {}
    try { $db->exec("ALTER TABLE identite_auto_champs ADD KEY idx_auto_champ_canevas (canevas_id)"); } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE identite_auto_champs DROP INDEX uq_auto_champ_code");
        $db->exec("ALTER TABLE identite_auto_champs ADD UNIQUE KEY uq_auto_champ_canevas_code (canevas_id, code)");
    } catch (Throwable $e) {}
    // Codes répétés autorisés d'un canevas à l'autre (même modèle)
    try {
        $db->exec("ALTER TABLE identite_auto_references DROP INDEX uq_auto_ref_code");
        $db->exec("ALTER TABLE identite_auto_references ADD UNIQUE KEY uq_auto_ref_champ_code (champ_id, code)");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE identite_auto_criteres DROP INDEX uq_auto_crit_code");
        $db->exec("ALTER TABLE identite_auto_criteres ADD UNIQUE KEY uq_auto_crit_ref_code (reference_id, code)");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE identite_auto_criteres ADD COLUMN type_saisie ENUM('SCORE','NOMBRE') NOT NULL DEFAULT 'SCORE' AFTER elements_preuves");
    } catch (Throwable $e) {}
}

function auto_anaq_assurer_donnees(): void {
    auto_anaq_assurer_schema();
    auto_anaq_assurer_canevas_defaut();
    auto_anaq_seed_grille_officielle_si_vide();
    auto_anaq_reparer_libelles_vides();
}

/** Crée le canevas ANAQ 2025 et y rattache la grille existante si besoin. */
function auto_anaq_assurer_canevas_defaut(): void {
    $db = get_db();
    $st = $db->prepare('SELECT id FROM identite_auto_canevas WHERE code = ? LIMIT 1');
    $st->execute([AUTO_ANAQ_CANEVAS_CODE_DEFAUT]);
    $id = (int)($st->fetchColumn() ?: 0);

    if ($id <= 0) {
        $nb_actifs = (int)$db->query('SELECT COUNT(*) FROM identite_auto_canevas WHERE actif = 1')->fetchColumn();
        $db->prepare('
            INSERT INTO identite_auto_canevas
                (code, titre, version, description, actif, date_publication, cree_le)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ')->execute([
            AUTO_ANAQ_CANEVAS_CODE_DEFAUT,
            'Auto-évaluation ANAQ / CIAQ',
            '2025',
            'Référentiel officiel d\'auto-évaluation interne préparatoire à l\'évaluation ANAQ.',
            $nb_actifs > 0 ? 0 : 1,
            '2025-01-01',
        ]);
        $id = (int)$db->lastInsertId();
    }

    if ($id <= 0) return;

    // Rattacher la grille orpheline (canevas_id NULL) au canevas par défaut
    try {
        $db->prepare('UPDATE identite_auto_champs SET canevas_id = ? WHERE canevas_id IS NULL')->execute([$id]);
    } catch (Throwable $e) {}

    $stN = $db->prepare('SELECT COUNT(*) FROM identite_auto_champs WHERE canevas_id = ?');
    $stN->execute([$id]);
    $n = (int)$stN->fetchColumn();
    if ($n === 0) {
        try {
            $db->prepare('
                UPDATE identite_auto_champs ch
                LEFT JOIN identite_auto_canevas c ON c.id = ch.canevas_id
                SET ch.canevas_id = ?
                WHERE c.id IS NULL
            ')->execute([$id]);
        } catch (Throwable $e) {}
    }

    // Un seul référentiel « en service » (actif) : si aucun, prendre le défaut
    $actifs = (int)$db->query('SELECT COUNT(*) FROM identite_auto_canevas WHERE actif = 1')->fetchColumn();
    if ($actifs === 0) {
        $db->prepare('UPDATE identite_auto_canevas SET actif = 1 WHERE id = ?')->execute([$id]);
    }
}

// ── Canevas / référentiels ───────────────────────────────────

function auto_anaq_canevas_lister(bool $actifs_seulement = false): array {
    auto_anaq_assurer_donnees();
    $sql = 'SELECT * FROM identite_auto_canevas';
    if ($actifs_seulement) $sql .= ' WHERE actif = 1';
    $sql .= ' ORDER BY actif DESC, titre ASC';
    return get_db()->query($sql)->fetchAll();
}

function auto_anaq_canevas_par_id(int $id): ?array {
    auto_anaq_assurer_donnees();
    $s = get_db()->prepare('SELECT * FROM identite_auto_canevas WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function auto_anaq_canevas_actif(): ?array {
    auto_anaq_assurer_donnees();
    $r = get_db()->query('SELECT * FROM identite_auto_canevas WHERE actif = 1 ORDER BY id ASC LIMIT 1')->fetch();
    if ($r) return $r;
    $r = get_db()->query('SELECT * FROM identite_auto_canevas ORDER BY id ASC LIMIT 1')->fetch();
    return $r ?: null;
}

/** Résout le canevas courant (GET/session) avec repli sur le canevas actif. */
function auto_anaq_canevas_courant(?int $force_id = null): ?array {
    auto_anaq_assurer_donnees();
    if ($force_id && $force_id > 0) {
        $c = auto_anaq_canevas_par_id($force_id);
        if ($c) {
            $_SESSION['auto_anaq_canevas_id'] = (int)$c['id'];
            return $c;
        }
    }
    $sid = (int)($_SESSION['auto_anaq_canevas_id'] ?? 0);
    if ($sid > 0) {
        $c = auto_anaq_canevas_par_id($sid);
        if ($c) return $c;
    }
    $c = auto_anaq_canevas_actif();
    if ($c) $_SESSION['auto_anaq_canevas_id'] = (int)$c['id'];
    return $c;
}

function auto_anaq_canevas_stats(int $canevas_id): array {
    $s = get_db()->prepare('
        SELECT
            (SELECT COUNT(*) FROM identite_auto_champs WHERE canevas_id = ?) AS n_champs,
            (SELECT COUNT(*) FROM identite_auto_references r
             JOIN identite_auto_champs ch ON ch.id = r.champ_id WHERE ch.canevas_id = ?) AS n_refs,
            (SELECT COUNT(*) FROM identite_auto_criteres c
             JOIN identite_auto_champs ch ON ch.id = c.champ_id WHERE ch.canevas_id = ?) AS n_criteres
    ');
    $s->execute([$canevas_id, $canevas_id, $canevas_id]);
    $r = $s->fetch() ?: [];
    return [
        'n_champs'   => (int)($r['n_champs'] ?? 0),
        'n_refs'     => (int)($r['n_refs'] ?? 0),
        'n_criteres' => (int)($r['n_criteres'] ?? 0),
    ];
}

function auto_anaq_canevas_ajouter(array $data): array {
    if (!identite_peut_modifier()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $titre = trim((string)($data['titre'] ?? ''));
    $code  = strtoupper(trim((string)($data['code'] ?? '')));
    $code  = preg_replace('/[^A-Z0-9_]/', '_', $code) ?: '';
    if ($titre === '') return ['succes' => false, 'message' => 'Le titre est obligatoire.'];
    if ($code === '' || strlen($code) < 3) {
        $code = 'CANEVAS_' . date('Ymd_His');
    }
    $version = trim((string)($data['version'] ?? '')) ?: null;
    $desc = trim((string)($data['description'] ?? '')) ?: null;
    $date_pub = trim((string)($data['date_publication'] ?? ''));
    if ($date_pub !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_pub)) {
        return ['succes' => false, 'message' => 'Date de publication invalide.'];
    }
    if ($date_pub === '') $date_pub = null;

    $chk = get_db()->prepare('SELECT id FROM identite_auto_canevas WHERE code = ?');
    $chk->execute([$code]);
    if ($chk->fetchColumn()) {
        return ['succes' => false, 'message' => 'Ce code de canevas existe déjà.'];
    }

    get_db()->prepare('
        INSERT INTO identite_auto_canevas
            (code, titre, version, description, actif, date_publication, cree_par, cree_le)
        VALUES (?, ?, ?, ?, 0, ?, ?, NOW())
    ')->execute([$code, $titre, $version, $desc, $date_pub, user_id()]);
    $id = (int)get_db()->lastInsertId();
    log_activite(user_id(), 'IDENTITE_CRICT', 'CREATION', 'ANAQ canevas ' . $code . ' créé');
    return ['succes' => true, 'id' => $id];
}

function auto_anaq_canevas_modifier(int $id, array $data): array {
    if (!identite_peut_modifier()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $c = auto_anaq_canevas_par_id($id);
    if (!$c) return ['succes' => false, 'message' => 'Canevas introuvable.'];

    $titre = trim((string)($data['titre'] ?? ''));
    if ($titre === '') return ['succes' => false, 'message' => 'Le titre est obligatoire.'];
    $version = trim((string)($data['version'] ?? '')) ?: null;
    $desc = trim((string)($data['description'] ?? '')) ?: null;
    $date_pub = trim((string)($data['date_publication'] ?? ''));
    if ($date_pub !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_pub)) {
        return ['succes' => false, 'message' => 'Date de publication invalide.'];
    }
    if ($date_pub === '') $date_pub = null;

    get_db()->prepare('
        UPDATE identite_auto_canevas
        SET titre = ?, version = ?, description = ?, date_publication = ?,
            modifie_par = ?, modifie_le = NOW()
        WHERE id = ?
    ')->execute([$titre, $version, $desc, $date_pub, user_id(), $id]);
    log_activite(user_id(), 'IDENTITE_CRICT', 'MODIFICATION', 'ANAQ canevas ID ' . $id . ' modifié');
    return ['succes' => true];
}

/**
 * Met ce référentiel en service : défaut pour tous + chargé sur le portail (session).
 * Remplace l'ancien duo Activer / Désactiver.
 */
function auto_anaq_canevas_utiliser(int $id): array {
    if (!identite_peut_modifier()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $c = auto_anaq_canevas_par_id($id);
    if (!$c) return ['succes' => false, 'message' => 'Canevas introuvable.'];
    $db = get_db();
    $db->exec('UPDATE identite_auto_canevas SET actif = 0');
    $db->prepare('UPDATE identite_auto_canevas SET actif = 1, modifie_par = ?, modifie_le = NOW() WHERE id = ?')
       ->execute([user_id(), $id]);
    $_SESSION['auto_anaq_canevas_id'] = $id;
    log_activite(user_id(), 'IDENTITE_CRICT', 'MODIFICATION', 'ANAQ canevas en service : ' . $c['code']);
    return ['succes' => true, 'message' => 'Référentiel « ' . $c['titre'] . ' » en service sur le portail.'];
}

/** Alias conservé pour compatibilité éventuelle. */
function auto_anaq_canevas_activer(int $id): array {
    return auto_anaq_canevas_utiliser($id);
}

/**
 * Supprime un canevas, sa grille et le fichier guide.
 * Si c'était le référentiel en service, bascule vers un autre (ou le défaut).
 */
function auto_anaq_canevas_supprimer(int $id): array {
    if (!identite_peut_modifier()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $c = auto_anaq_canevas_par_id($id);
    if (!$c) return ['succes' => false, 'message' => 'Canevas introuvable.'];

    $db = get_db();
    $etait_actif = (int)($c['actif'] ?? 0) === 1;
    $titre = (string)$c['titre'];

    try {
        $db->beginTransaction();
        auto_anaq_supprimer_grille_canevas($id, false);

        if (!empty($c['fichier_guide'])) {
            $old = APP_ROOT . '/' . ltrim((string)$c['fichier_guide'], '/');
            if (is_file($old)) @unlink($old);
        }
        $dir = auto_anaq_canevas_upload_dir() . $id;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f)) @unlink($f);
            }
            @rmdir($dir);
        }

        $db->prepare('DELETE FROM identite_auto_canevas WHERE id = ?')->execute([$id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT ANAQ] suppression canevas : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Impossible de supprimer ce référentiel.'];
    }

    if ((int)($_SESSION['auto_anaq_canevas_id'] ?? 0) === $id) {
        unset($_SESSION['auto_anaq_canevas_id']);
    }

    if ($etait_actif) {
        $autre = $db->query('SELECT id FROM identite_auto_canevas ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($autre) {
            $db->prepare('UPDATE identite_auto_canevas SET actif = 1 WHERE id = ?')->execute([(int)$autre]);
            $_SESSION['auto_anaq_canevas_id'] = (int)$autre;
        }
    }

    log_activite(user_id(), 'IDENTITE_CRICT', 'SUPPRESSION', 'ANAQ canevas supprimé : ' . ($c['code'] ?? $id));
    return ['succes' => true, 'message' => 'Référentiel « ' . $titre . ' » supprimé.'];
}

function auto_anaq_canevas_upload_dir(): string {
    $dir = UPLOAD_DIR . AUTO_ANAQ_UPLOAD_SUBDIR . '/canevas/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function auto_anaq_canevas_upload_guide(int $id, array $fichier): array {
    if (!identite_peut_modifier()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $c = auto_anaq_canevas_par_id($id);
    if (!$c) return ['succes' => false, 'message' => 'Canevas introuvable.'];
    if (empty($fichier['tmp_name']) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['succes' => false, 'message' => 'Fichier invalide ou absent.'];
    }
    if (($fichier['size'] ?? 0) > AUTO_ANAQ_MAX_PIECE) {
        return ['succes' => false, 'message' => 'Fichier trop volumineux (max 20 Mo).'];
    }
    $ext_ok = ['pdf', 'doc', 'docx', 'odt', 'txt'];
    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_ok, true)) {
        return ['succes' => false, 'message' => 'Format non autorisé (PDF, Word, TXT).'];
    }
    $dir = auto_anaq_canevas_upload_dir() . $id . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $stock = 'guide_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($fichier['tmp_name'], $dir . $stock)) {
        return ['succes' => false, 'message' => 'Impossible d\'enregistrer le fichier.'];
    }
    if (!empty($c['fichier_guide'])) {
        $old = APP_ROOT . '/' . ltrim($c['fichier_guide'], '/');
        if (is_file($old)) @unlink($old);
    }
    $chemin = 'uploads/' . AUTO_ANAQ_UPLOAD_SUBDIR . '/canevas/' . $id . '/' . $stock;
    get_db()->prepare('
        UPDATE identite_auto_canevas
        SET fichier_guide = ?, fichier_nom = ?, modifie_par = ?, modifie_le = NOW()
        WHERE id = ?
    ')->execute([$chemin, $fichier['name'], user_id(), $id]);
    log_activite(user_id(), 'IDENTITE_CRICT', 'MODIFICATION', 'ANAQ guide canevas ID ' . $id);

    // Import dynamique de la grille depuis le document
    $abs = APP_ROOT . '/' . ltrim($chemin, '/');
    $stats = auto_anaq_canevas_stats($id);
    $import = auto_anaq_importer_fichier_referentiel($id, $abs, $fichier['name']);
    // Si le canevas avait déjà une grille et l'import échoue, on garde l'ancien + le fichier
    if (!$import['succes'] && ($stats['n_criteres'] ?? 0) > 0) {
        return [
            'succes' => true,
            'message' => 'Guide enregistré. Import grille non effectué : ' . ($import['message'] ?? ''),
            'import' => $import,
        ];
    }
    return [
        'succes' => (bool)($import['succes'] ?? false),
        'message' => $import['succes']
            ? ('Guide enregistré. ' . ($import['message'] ?? ''))
            : ('Guide enregistré, mais import grille échoué : ' . ($import['message'] ?? '')),
        'import' => $import,
    ];
}

function auto_anaq_champs_lister(?int $canevas_id = null): array {
    auto_anaq_assurer_donnees();
    if ($canevas_id === null) {
        $c = auto_anaq_canevas_courant();
        $canevas_id = $c ? (int)$c['id'] : 0;
    }
    if ($canevas_id <= 0) {
        return get_db()->query('SELECT * FROM identite_auto_champs ORDER BY ordre')->fetchAll();
    }
    $s = get_db()->prepare('SELECT * FROM identite_auto_champs WHERE canevas_id = ? ORDER BY ordre');
    $s->execute([$canevas_id]);
    return $s->fetchAll();
}

function auto_anaq_champ_par_id(int $id): ?array {
    auto_anaq_assurer_donnees();
    $s = get_db()->prepare('SELECT * FROM identite_auto_champs WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function auto_anaq_references_par_champ(int $champ_id): array {
    auto_anaq_assurer_donnees();
    $s = get_db()->prepare('SELECT * FROM identite_auto_references WHERE champ_id = ? ORDER BY ordre');
    $s->execute([$champ_id]);
    return $s->fetchAll();
}

function auto_anaq_criteres_par_reference(int $ref_id): array {
    $s = get_db()->prepare('
        SELECT c.*, ct.reponse, ct.preuves_disponibles, ct.score, ct.commentaire, ct.modifie_le
        FROM identite_auto_criteres c
        LEFT JOIN identite_auto_contenu ct ON ct.critere_id = c.id
        WHERE c.reference_id = ?
        ORDER BY c.ordre
    ');
    $s->execute([$ref_id]);
    return $s->fetchAll();
}

function auto_anaq_critere_par_id(int $id): ?array {
    $s = get_db()->prepare('
        SELECT c.*, r.code AS ref_code, r.libelle AS ref_libelle,
               ch.libelle_court AS champ_libelle, ch.id AS champ_id,
               ct.reponse, ct.preuves_disponibles, ct.score, ct.commentaire, ct.modifie_le
        FROM identite_auto_criteres c
        JOIN identite_auto_references r ON r.id = c.reference_id
        JOIN identite_auto_champs ch ON ch.id = c.champ_id
        LEFT JOIN identite_auto_contenu ct ON ct.critere_id = c.id
        WHERE c.id = ?
    ');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function auto_anaq_critere_voisins(int $critere_id): array {
    $c = auto_anaq_critere_par_id($critere_id);
    if (!$c) return ['prev' => null, 'next' => null];
    $db = get_db();
    $prev = $db->prepare('SELECT id, code, ligne_directrice FROM identite_auto_criteres WHERE champ_id = ? AND ordre < ? ORDER BY ordre DESC LIMIT 1');
    $prev->execute([(int)$c['champ_id'], (int)$c['ordre']]);
    $next = $db->prepare('SELECT id, code, ligne_directrice FROM identite_auto_criteres WHERE champ_id = ? AND ordre > ? ORDER BY ordre ASC LIMIT 1');
    $next->execute([(int)$c['champ_id'], (int)$c['ordre']]);
    return ['prev' => $prev->fetch() ?: null, 'next' => $next->fetch() ?: null];
}

/** Type NOMBRE : pas de niveau 0–5 (même logique que DGRS). */
function auto_anaq_critere_type_nombre(array $c): bool {
    return ($c['type_saisie'] ?? 'SCORE') === 'NOMBRE';
}

/** Complété = réponse remplie + (score renseigné si type SCORE). Identique à champ.php DGRS. */
function auto_anaq_critere_est_complete(array $c): bool {
    if (!identite_est_complete($c['reponse'] ?? '')) {
        return false;
    }
    if (auto_anaq_critere_type_nombre($c)) {
        return true;
    }
    return array_key_exists('score', $c) && $c['score'] !== null && $c['score'] !== '';
}

function auto_anaq_sql_critere_complet(): string {
    $ph = str_replace("'", "''", IDENTITE_A_COMPLETER);
    return "TRIM(COALESCE(ct.reponse, '')) <> ''
            AND TRIM(COALESCE(ct.reponse, '')) <> '{$ph}'
            AND (COALESCE(c.type_saisie, 'SCORE') = 'NOMBRE' OR ct.score IS NOT NULL)";
}

function auto_anaq_statut_critere(array $c): string {
    return auto_anaq_critere_est_complete($c) ? 'complete' : 'a_completer';
}

function auto_anaq_completude_champ(int $champ_id): array {
    $cond = auto_anaq_sql_critere_complet();
    $s = get_db()->prepare("
        SELECT COUNT(*) AS total,
               SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS faits
        FROM identite_auto_criteres c
        LEFT JOIN identite_auto_contenu ct ON ct.critere_id = c.id
        WHERE c.champ_id = ?
    ");
    $s->execute([$champ_id]);
    $r = $s->fetch();
    $total = (int)($r['total'] ?? 0);
    $faits = (int)($r['faits'] ?? 0);
    return ['total' => $total, 'faits' => $faits, 'pct' => $total > 0 ? round(100 * $faits / $total) : 0];
}

function auto_anaq_completude_globale(?int $canevas_id = null): array {
    if ($canevas_id === null) {
        $c = auto_anaq_canevas_courant();
        $canevas_id = $c ? (int)$c['id'] : 0;
    }
    $cond = auto_anaq_sql_critere_complet();
    if ($canevas_id > 0) {
        $s = get_db()->prepare("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS faits
            FROM identite_auto_criteres c
            JOIN identite_auto_champs ch ON ch.id = c.champ_id
            LEFT JOIN identite_auto_contenu ct ON ct.critere_id = c.id
            WHERE ch.canevas_id = ?
        ");
        $s->execute([$canevas_id]);
        $r = $s->fetch();
    } else {
        $s = get_db()->query("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN {$cond} THEN 1 ELSE 0 END) AS faits
            FROM identite_auto_criteres c
            LEFT JOIN identite_auto_contenu ct ON ct.critere_id = c.id
        ");
        $r = $s->fetch();
    }
    $total = (int)($r['total'] ?? 0);
    $faits = (int)($r['faits'] ?? 0);
    return ['total' => $total, 'faits' => $faits, 'pct' => $total > 0 ? round(100 * $faits / $total) : 0];
}

function auto_anaq_contenu_sauver(int $critere_id, string $reponse, string $preuves, ?int $score, string $commentaire, bool $log = true): bool {
    $db = get_db();
    $uid = user_id();
    if ($score !== null && ($score < 0 || $score > 5)) $score = null;
    try {
        $db->prepare('
            INSERT INTO identite_auto_contenu (critere_id, reponse, preuves_disponibles, score, commentaire, modifie_par, modifie_le)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                reponse = VALUES(reponse), preuves_disponibles = VALUES(preuves_disponibles),
                score = VALUES(score), commentaire = VALUES(commentaire),
                modifie_par = VALUES(modifie_par), modifie_le = NOW()
        ')->execute([
            $critere_id,
            $reponse !== '' ? $reponse : null,
            $preuves !== '' ? $preuves : null,
            $score,
            $commentaire !== '' ? $commentaire : null,
            $uid ?: null,
        ]);
        if ($log) {
            $crit = auto_anaq_critere_par_id($critere_id);
            log_activite($uid, 'IDENTITE_CRICT', 'MODIFICATION',
                'ANAQ critère ' . ($crit['code'] ?? $critere_id) . ' mis à jour');
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Score POST : conserve l'existant si le champ n'est pas envoyé (auto-save partiel). */
function auto_anaq_point_score_from_post(?array $existant): ?int {
    if (!array_key_exists('score', $_POST)) {
        $s = $existant['score'] ?? null;
        return ($s !== null && $s !== '') ? (int)$s : null;
    }
    $raw = $_POST['score'] ?? '';
    if ($raw === '' || $raw === null) {
        return null;
    }
    $score = (int)$raw;
    return ($score >= 0 && $score <= 5) ? $score : null;
}

function auto_anaq_point_pieces_uploader(int $critere_id): array {
    $erreurs = [];
    if (empty($_FILES['pieces']['name'][0])) {
        return $erreurs;
    }
    foreach ($_FILES['pieces']['name'] as $k => $nom) {
        if (($_FILES['pieces']['error'][$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        $fk = [
            'name'     => $nom,
            'tmp_name' => $_FILES['pieces']['tmp_name'][$k],
            'error'    => $_FILES['pieces']['error'][$k],
            'size'     => $_FILES['pieces']['size'][$k],
        ];
        $r = auto_anaq_piece_ajouter($critere_id, $fk);
        if (!$r['succes']) {
            $erreurs[] = $nom . ' : ' . ($r['message'] ?? 'erreur');
        }
    }
    return $erreurs;
}

function auto_anaq_critere_question_sauver(int $critere_id, string $question): bool {
    try {
        $st = get_db()->prepare('UPDATE identite_auto_criteres SET question = ? WHERE id = ?');
        return $st->execute([trim($question), $critere_id]);
    } catch (Throwable $e) {
        error_log('auto_anaq_critere_question_sauver: ' . $e->getMessage());
        return false;
    }
}

function auto_anaq_point_sauver(int $critere_id, bool $with_uploads = false, bool $log = true): array {
    if ($critere_id <= 0) {
        return ['ok' => false, 'message' => 'Point invalide.'];
    }
    $existant = auto_anaq_critere_par_id($critere_id);
    if (!$existant) {
        return ['ok' => false, 'message' => 'Point introuvable.'];
    }

    // Mise à jour de la question si le champ est soumis (mode édition)
    if (array_key_exists('question', $_POST)) {
        if (!auto_anaq_critere_question_sauver($critere_id, (string)$_POST['question'])) {
            return ['ok' => false, 'message' => 'Erreur lors de l\'enregistrement de la question.'];
        }
    }

    $score = auto_anaq_critere_type_nombre($existant) ? null : auto_anaq_point_score_from_post($existant);

    if (!auto_anaq_contenu_sauver(
        $critere_id,
        $_POST['reponse'] ?? '',
        $_POST['preuves_disponibles'] ?? '',
        $score,
        $existant['commentaire'] ?? '',
        $log
    )) {
        return ['ok' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }

    $upload_errs = $with_uploads ? auto_anaq_point_pieces_uploader($critere_id) : [];

    $crit = auto_anaq_critere_par_id($critere_id);
    $st   = auto_anaq_statut_critere($crit ?: []);
    $bd   = auto_anaq_badge_statut($st);

    return [
        'ok'           => true,
        'message'      => 'Point enregistré.',
        'upload_errs'  => $upload_errs,
        'statut'       => $st,
        'statut_label' => $bd[0],
        'statut_class' => $bd[1],
    ];
}

function auto_anaq_swot_lire(int $champ_id): array {
    $s = get_db()->prepare('SELECT * FROM identite_auto_swot WHERE champ_id = ?');
    $s->execute([$champ_id]);
    $r = $s->fetch();
    return $r ?: ['forces' => '', 'faiblesses' => '', 'opportunites' => '', 'menaces' => ''];
}

function auto_anaq_swot_sauver(int $champ_id, array $data): bool {
    $db = get_db();
    $uid = user_id();
    try {
        $db->prepare('
            INSERT INTO identite_auto_swot (champ_id, forces, faiblesses, opportunites, menaces, modifie_par, modifie_le)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                forces = VALUES(forces), faiblesses = VALUES(faiblesses),
                opportunites = VALUES(opportunites), menaces = VALUES(menaces),
                modifie_par = VALUES(modifie_par), modifie_le = NOW()
        ')->execute([
            $champ_id,
            trim($data['forces'] ?? '') ?: null,
            trim($data['faiblesses'] ?? '') ?: null,
            trim($data['opportunites'] ?? '') ?: null,
            trim($data['menaces'] ?? '') ?: null,
            $uid ?: null,
        ]);
        $champ = auto_anaq_champ_par_id($champ_id);
        log_activite($uid, 'IDENTITE_CRICT', 'MODIFICATION',
            'ANAQ SWOT thème ' . ($champ['libelle_court'] ?? $champ_id) . ' mis à jour');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function auto_anaq_upload_dir(): string {
    $dir = UPLOAD_DIR . AUTO_ANAQ_UPLOAD_SUBDIR . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function auto_anaq_pieces_lister(int $critere_id): array {
    $s = get_db()->prepare('SELECT * FROM identite_auto_pieces WHERE critere_id = ? ORDER BY cree_le DESC');
    $s->execute([$critere_id]);
    return $s->fetchAll();
}

function auto_anaq_piece_ajouter(int $critere_id, array $fichier): array {
    if (empty($fichier['tmp_name']) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['succes' => false, 'message' => 'Fichier invalide ou absent.'];
    }
    if (($fichier['size'] ?? 0) > AUTO_ANAQ_MAX_PIECE) {
        return ['succes' => false, 'message' => 'Fichier trop volumineux (max 20 Mo).'];
    }
    $ext_ok = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx',
               'odt', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_ok, true)) {
        return ['succes' => false, 'message' => 'Format non autorisé (PDF, Word, Excel, texte, images).'];
    }
    $dir  = auto_anaq_upload_dir() . $critere_id . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $stock = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($fichier['tmp_name'], $dir . $stock)) {
        return ['succes' => false, 'message' => 'Impossible de déplacer le fichier.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($dir . $stock) ?: null;
    $chemin = 'uploads/' . AUTO_ANAQ_UPLOAD_SUBDIR . '/' . $critere_id . '/' . $stock;
    try {
        get_db()->prepare('
            INSERT INTO identite_auto_pieces (critere_id, nom_original, nom_stockage, chemin_fichier, type_mime, taille_fichier, depose_par)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ')->execute([$critere_id, $fichier['name'], $stock, $chemin, $mime, $fichier['size'], user_id()]);
        $crit = auto_anaq_critere_par_id($critere_id);
        log_activite(user_id(), 'IDENTITE_CRICT', 'CREATION',
            'ANAQ pièce jointe critère ' . ($crit['code'] ?? $critere_id));
        return ['succes' => true, 'id' => (int)get_db()->lastInsertId()];
    } catch (Throwable $e) {
        @unlink($dir . $stock);
        return ['succes' => false, 'message' => 'Erreur base de données.'];
    }
}

function auto_anaq_piece_supprimer(int $piece_id): bool {
    $db = get_db();
    $s  = $db->prepare('SELECT * FROM identite_auto_pieces WHERE id = ?');
    $s->execute([$piece_id]);
    $p = $s->fetch();
    if (!$p) return false;
    $abs = APP_ROOT . '/' . ltrim($p['chemin_fichier'], '/');
    if (file_exists($abs)) @unlink($abs);
    $db->prepare('DELETE FROM identite_auto_pieces WHERE id = ?')->execute([$piece_id]);
    log_activite(user_id(), 'IDENTITE_CRICT', 'SUPPRESSION', 'ANAQ pièce ID ' . $piece_id);
    return true;
}

function auto_anaq_recherche(string $q, int $limit = 20, bool $mode_edit = false, ?int $canevas_id = null): array {
    $q = trim($q);
    if (mb_strlen($q) < 2) return [];
    auto_anaq_assurer_donnees();
    if ($canevas_id === null) {
        $c = auto_anaq_canevas_courant();
        $canevas_id = $c ? (int)$c['id'] : 0;
    }
    $db   = get_db();
    $like = '%' . $q . '%';
    $sql = '
        SELECT c.id, c.code, c.ligne_directrice, c.question, c.elements_preuves, c.champ_id,
               ch.libelle_court AS champ_libelle, r.code AS ref_code,
               ct.reponse, ct.preuves_disponibles
        FROM identite_auto_criteres c
        JOIN identite_auto_champs ch ON ch.id = c.champ_id
        JOIN identite_auto_references r ON r.id = c.reference_id
        LEFT JOIN identite_auto_contenu ct ON ct.critere_id = c.id
        WHERE (c.code LIKE ? OR c.ligne_directrice LIKE ? OR c.question LIKE ?
              OR c.elements_preuves LIKE ? OR ct.reponse LIKE ? OR ct.preuves_disponibles LIKE ?
              OR r.libelle LIKE ?)
    ';
    $params = [$like, $like, $like, $like, $like, $like, $like];
    if ($canevas_id > 0) {
        $sql .= ' AND ch.canevas_id = ?';
        $params[] = $canevas_id;
    }
    $sql .= ' ORDER BY c.ordre LIMIT ?';
    $params[] = $limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $snippet = auto_anaq_recherche_extrait($row, $q);
        $out[] = [
            'code'    => $row['code'],
            'titre'   => auto_anaq_titre_court($row['ligne_directrice']),
            'sous'    => $row['champ_libelle'] . ' · Réf. ' . $row['ref_code'],
            'url'     => 'auto_point.php?id=' . (int)$row['id'] . auto_anaq_query_edit($mode_edit),
            'snippet' => $snippet,
        ];
    }
    return $out;
}

function auto_anaq_titre_court(string $texte, int $max = 72): string {
    $t = trim(preg_replace('/\s+/u', ' ', $texte) ?? $texte);
    return mb_strlen($t) > $max ? mb_substr($t, 0, $max) . '…' : $t;
}

/** Nettoie un libellé extrait (point orphelin, espaces). */
function auto_anaq_nettoyer_libelle(string $lib): string {
    $lib = trim(preg_replace('/\s+/u', ' ', $lib) ?? $lib);
    return trim(preg_replace('/^[.\s\-–:]+/u', '', $lib) ?? $lib);
}

/** Titre court pour les cartes portail (ex. « Gouvernance & organisation »). */
function auto_anaq_deriver_libelle_court(string $lib): string {
    $lib = auto_anaq_nettoyer_libelle($lib);
    if ($lib === '') return '';
    $upper = mb_strtoupper($lib);
    $aliases = [
        'GOUVERNANCE'  => 'Gouvernance & organisation',
        'VIE SCIENTIFIQUE' => 'Vie scientifique',
        'RESSOURCES'   => 'Ressources',
        'INNOVATION'   => 'Innovation & partenariats',
    ];
    foreach ($aliases as $prefix => $court) {
        if (mb_strpos($upper, $prefix) === 0) return $court;
    }
    if (preg_match('/^([^,]+)/u', $lib, $m)) {
        $seg = trim($m[1]);
        if (mb_strlen($seg) <= 80) {
            return mb_convert_case(mb_strtolower($seg), MB_CASE_TITLE, 'UTF-8');
        }
    }
    return mb_strlen($lib) > 80 ? mb_substr($lib, 0, 77) . '…' : $lib;
}

/** Description carte : phrase lisible, sans doublon du titre court. */
function auto_anaq_deriver_description_champ(string $lib, string $libelle_court): string {
    $lib = auto_anaq_nettoyer_libelle($lib);
    if ($lib === '') return '';
    $desc = mb_convert_case(mb_strtolower($lib), MB_CASE_TITLE, 'UTF-8');
    if (!str_ends_with($desc, '.')) $desc .= '.';
    if (mb_strtolower($desc) === mb_strtolower($libelle_court . '.')) return '';
    if (mb_strtolower($libelle_court) === mb_strtolower($desc)) return '';
    if (mb_strlen($lib) <= mb_strlen($libelle_court) + 8) return '';
    return $desc;
}

/** Construit les métadonnées d'un champ d'évaluation à partir du titre brut. */
function auto_anaq_champ_meta(string $lib, int $ordre, array $icone): array {
    $lib = auto_anaq_nettoyer_libelle($lib);
    if ($lib === '') $lib = 'Champ d\'évaluation ' . $ordre;
    $court = auto_anaq_deriver_libelle_court($lib);
    return [
        'code' => 'CH' . $ordre,
        'libelle_court' => $court,
        'libelle' => mb_strtoupper($lib),
        'description' => auto_anaq_deriver_description_champ($lib, $court),
        'icone' => $icone[($ordre - 1) % count($icone)] ?? 'fa-folder',
        'ordre' => $ordre,
    ];
}

/** Libellé affiché d'un point : ligne directrice, sinon question. */
function auto_anaq_libelle_critere(array $c): string {
    $ligne = trim((string)($c['ligne_directrice'] ?? ''));
    if ($ligne !== '') return $ligne;
    return trim((string)($c['question'] ?? ''));
}

/** Complète les lignes directrices vides à partir de la question (données déjà en base). */
function auto_anaq_reparer_libelles_vides(): void {
    try {
        get_db()->exec("
            UPDATE identite_auto_criteres
            SET ligne_directrice = question
            WHERE (ligne_directrice IS NULL OR TRIM(ligne_directrice) = '')
              AND question IS NOT NULL AND TRIM(question) != ''
        ");
    } catch (Throwable $e) {}
}

/**
 * Normalise une grille importée : aucun point sans texte.
 * @return array{ok:bool,data:array,vides:int,message?:string}
 */
function auto_anaq_normaliser_grille(array $data): array {
    $refs_out = [];
    $vides = 0;
    foreach ($data['references'] ?? [] as $ref) {
        $criteres = [];
        foreach ($ref['criteres'] ?? [] as $cr) {
            $code = trim((string)($cr['code'] ?? ''));
            $ligne = trim((string)($cr['ligne_directrice'] ?? ''));
            $question = trim((string)($cr['question'] ?? ''));
            $preuves = trim((string)($cr['elements_preuves'] ?? ''));
            if ($ligne === '' && $question !== '') $ligne = $question;
            if ($question === '' && $ligne !== '') $question = $ligne;
            if ($code === '' || ($ligne === '' && $question === '')) {
                $vides++;
                continue;
            }
            $criteres[] = [
                'code' => $code,
                'ligne_directrice' => $ligne,
                'question' => $question,
                'elements_preuves' => $preuves !== '' ? $preuves : null,
                'ordre' => (int)($cr['ordre'] ?? count($criteres) + 1),
            ];
        }
        if ($criteres === []) continue;
        $refs_out[] = [
            'champ_ordre' => (int)($ref['champ_ordre'] ?? 0),
            'code' => (string)($ref['code'] ?? ''),
            'libelle' => trim((string)($ref['libelle'] ?? '')),
            'ordre' => (int)($ref['ordre'] ?? 0),
            'criteres' => $criteres,
        ];
    }
    $n_crit = 0;
    foreach ($refs_out as $r) $n_crit += count($r['criteres']);
    $data['references'] = $refs_out;
    $data['n_criteres'] = $n_crit;
    if (empty($data['champs']) || $refs_out === [] || $n_crit < 1) {
        return [
            'ok' => false,
            'data' => $data,
            'vides' => $vides,
            'message' => 'Impossible de lire le référentiel. Essayez de coller le contenu dans le champ prévu, ou utilisez un fichier Word.',
        ];
    }
    return ['ok' => true, 'data' => $data, 'vides' => $vides];
}

function auto_anaq_recherche_extrait(array $row, string $q): string {
    foreach (['question', 'ligne_directrice', 'elements_preuves', 'reponse', 'preuves_disponibles'] as $k) {
        $v = trim((string)($row[$k] ?? ''));
        if ($v !== '' && mb_stripos($v, $q) !== false) {
            return auto_anaq_recherche_tronquer($v, $q);
        }
    }
    return auto_anaq_recherche_tronquer($row['ligne_directrice'] ?? '', $q);
}

function auto_anaq_recherche_tronquer(string $texte, string $q, int $len = 140): string {
    $texte = trim($texte);
    if ($texte === '') return '';
    $pos = mb_stripos($texte, $q);
    if ($pos === false) {
        return mb_strlen($texte) > $len ? mb_substr($texte, 0, $len) . '…' : $texte;
    }
    $start = max(0, $pos - 40);
    $chunk = mb_substr($texte, $start, $len);
    if ($start > 0) $chunk = '…' . $chunk;
    if ($start + $len < mb_strlen($texte)) $chunk .= '…';
    return $chunk;
}

function auto_anaq_score_labels(): array {
    return [
        0 => 'Inexistant',
        1 => 'Très faible',
        2 => 'Faible',
        3 => 'Moyen',
        4 => 'Bon',
        5 => 'Excellent',
    ];
}

/** Libellé + classe CSS id-status (aligné DGRS : Complété / À compléter). */
function auto_anaq_badge_statut(string $statut): array {
    return match ($statut) {
        'complete' => ['Complété', 'ok'],
        default    => ['À compléter', 'todo'],
    };
}

function auto_anaq_derniere_maj(): ?string {
    $row = get_db()->query("
        SELECT MAX(d) AS derniere FROM (
            SELECT modifie_le AS d FROM identite_auto_contenu WHERE modifie_le IS NOT NULL
            UNION ALL
            SELECT modifie_le FROM identite_auto_swot WHERE modifie_le IS NOT NULL
        ) t
    ")->fetch();
    return $row['derniere'] ?? null;
}

function auto_anaq_query_edit(bool $mode_edit, bool $first = false): string {
    if (!$mode_edit) return '';
    return $first ? '?edit=1' : '&edit=1';
}

function auto_anaq_preuves_liste(string $texte): array {
    $texte = trim($texte);
    if ($texte === '') return [];
    $parts = preg_split('/\s*[-•;]\s+/', $texte);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && mb_strlen($p) > 2) $out[] = $p;
    }
    return $out ?: [$texte];
}

// ── Seed + import dynamique de grille ────────────────────────

/** Si aucune grille en base : charge le référentiel officiel ANAQ sur le canevas par défaut. */
function auto_anaq_seed_grille_officielle_si_vide(): void {
    $db = get_db();
    $n = (int)$db->query('SELECT COUNT(*) FROM identite_auto_champs')->fetchColumn();
    if ($n > 0) return;

    // Préférer le canevas officiel ANAQ, puis l'actif, puis le premier
    $st = $db->prepare('SELECT * FROM identite_auto_canevas WHERE code = ? LIMIT 1');
    $st->execute([AUTO_ANAQ_CANEVAS_CODE_DEFAUT]);
    $c = $st->fetch() ?: null;
    if (!$c) {
        $c = $db->query('SELECT * FROM identite_auto_canevas WHERE actif = 1 ORDER BY id ASC LIMIT 1')->fetch() ?: null;
    }
    if (!$c) {
        $c = $db->query('SELECT * FROM identite_auto_canevas ORDER BY id ASC LIMIT 1')->fetch() ?: null;
    }
    if (!$c) return;

    $data = auto_anaq_referentiel_officiel_data();
    if (!$data) return;
    $r = auto_anaq_importer_grille_data((int)$c['id'], $data, true);
    if (!empty($r['succes'])) {
        $db->exec('UPDATE identite_auto_canevas SET actif = 0');
        $db->prepare('UPDATE identite_auto_canevas SET actif = 1 WHERE id = ?')->execute([(int)$c['id']]);
    }
}

function auto_anaq_referentiel_officiel_data(): ?array {
    $candidats = [
        APP_ROOT . '/modules/identite/auto_anaq_referentiel.php',
        __DIR__ . '/../modules/identite/auto_anaq_referentiel.php',
        APP_ROOT . '/modules/subventions/auto_anaq_referentiel.php',
        __DIR__ . '/../modules/subventions/auto_anaq_referentiel.php',
    ];
    $path = null;
    foreach ($candidats as $p) {
        if (is_file($p)) { $path = $p; break; }
    }
    if (!$path) return null;
    require_once $path;
    if (!function_exists('auto_anaq_referentiel_data')) return null;
    $data = auto_anaq_referentiel_data();
    if (!is_array($data)) return null;
    $norm = auto_anaq_normaliser_grille($data);
    return $norm['ok'] ? $norm['data'] : $data;
}

/**
 * Importe une grille structurée dans un canevas.
 * @param array $data format auto_anaq_referentiel_data()
 */
function auto_anaq_importer_grille_data(int $canevas_id, array $data, bool $remplacer = false): array {
    if ($canevas_id <= 0) return ['succes' => false, 'message' => 'Canevas invalide.'];
    $norm = auto_anaq_normaliser_grille($data);
    if (!$norm['ok']) {
        return ['succes' => false, 'message' => $norm['message'] ?? 'Données de référentiel invalides.'];
    }
    $data = $norm['data'];
    if (empty($data['champs']) || empty($data['references'])) {
        return ['succes' => false, 'message' => 'Données de référentiel invalides.'];
    }
    $db = get_db();
    try {
        $db->beginTransaction();
        if ($remplacer) {
            auto_anaq_supprimer_grille_canevas($canevas_id, false);
        }

        $champ_ids = []; // ordre => id
        $insCh = $db->prepare('
            INSERT INTO identite_auto_champs (canevas_id, code, libelle_court, libelle, description, icone, ordre)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($data['champs'] as $ch) {
            $ordre = (int)($ch['ordre'] ?? 0);
            $insCh->execute([
                $canevas_id,
                $ch['code'],
                $ch['libelle_court'],
                $ch['libelle'],
                $ch['description'] ?? null,
                $ch['icone'] ?? 'fa-folder',
                $ordre,
            ]);
            $champ_ids[$ordre] = (int)$db->lastInsertId();
        }

        $insRef = $db->prepare('
            INSERT INTO identite_auto_references (champ_id, code, libelle, ordre) VALUES (?, ?, ?, ?)
        ');
        $insCrit = $db->prepare('
            INSERT INTO identite_auto_criteres
                (reference_id, champ_id, code, ligne_directrice, question, elements_preuves, ordre)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $n_refs = 0;
        $n_crit = 0;
        $ordre_crit_global = 0;
        foreach ($data['references'] as $ref) {
            $champ_ordre = (int)($ref['champ_ordre'] ?? 0);
            $champ_id = $champ_ids[$champ_ordre] ?? null;
            if (!$champ_id) continue;
            $insRef->execute([$champ_id, $ref['code'], $ref['libelle'], (int)($ref['ordre'] ?? 0)]);
            $ref_id = (int)$db->lastInsertId();
            $n_refs++;
            foreach ($ref['criteres'] ?? [] as $cr) {
                $ordre_crit_global++;
                $insCrit->execute([
                    $ref_id,
                    $champ_id,
                    $cr['code'],
                    $cr['ligne_directrice'],
                    $cr['question'],
                    $cr['elements_preuves'] ?? null,
                    (int)($cr['ordre'] ?? $ordre_crit_global),
                ]);
                $n_crit++;
            }
        }
        $db->commit();
        return [
            'succes'     => true,
            'n_champs'   => count($champ_ids),
            'n_refs'     => $n_refs,
            'n_criteres' => $n_crit,
            'message'    => count($champ_ids) . ' thèmes, ' . $n_refs . ' références, ' . $n_crit . ' points importés.',
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT ANAQ import] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de l\'import de la grille.'];
    }
}

function auto_anaq_supprimer_grille_canevas(int $canevas_id, bool $transaction = true): void {
    $db = get_db();
    $own = $transaction && !$db->inTransaction();
    if ($own) $db->beginTransaction();
    $ids = $db->prepare('SELECT id FROM identite_auto_champs WHERE canevas_id = ?');
    $ids->execute([$canevas_id]);
    $champ_ids = array_map('intval', $ids->fetchAll(PDO::FETCH_COLUMN));
    if ($champ_ids) {
        $in = implode(',', $champ_ids);
        $crit_ids = $db->query("SELECT id FROM identite_auto_criteres WHERE champ_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        if ($crit_ids) {
            $cin = implode(',', array_map('intval', $crit_ids));
            $db->exec("DELETE FROM identite_auto_pieces WHERE critere_id IN ($cin)");
            $db->exec("DELETE FROM identite_auto_contenu WHERE critere_id IN ($cin)");
        }
        $db->exec("DELETE FROM identite_auto_criteres WHERE champ_id IN ($in)");
        $db->exec("DELETE FROM identite_auto_references WHERE champ_id IN ($in)");
        $db->exec("DELETE FROM identite_auto_swot WHERE champ_id IN ($in)");
        $db->exec("DELETE FROM identite_auto_champs WHERE canevas_id = $canevas_id");
    }
    if ($own) $db->commit();
}

/** Extrait le texte brut d'un PDF, DOCX, DOC, TXT. */
function auto_anaq_extraire_texte(string $chemin, string $ext): string {
    $ext = strtolower($ext);
    if ($ext === 'txt') {
        $t = @file_get_contents($chemin);
        return is_string($t) ? $t : '';
    }
    if ($ext === 'docx') {
        return auto_anaq_extraire_docx($chemin);
    }
    if ($ext === 'pdf') {
        return auto_anaq_extraire_pdf($chemin);
    }
    if ($ext === 'doc') {
        // Ancien Word binaire : extraction limitée
        $raw = @file_get_contents($chemin);
        if (!is_string($raw)) return '';
        $raw = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{FF}]/u', ' ', $raw) ?? '';
        return trim(preg_replace('/\s+/', ' ', $raw) ?? '');
    }
    return '';
}

function auto_anaq_extraire_docx(string $chemin): string {
    if (!class_exists('ZipArchive')) return '';
    $zip = new ZipArchive();
    if ($zip->open($chemin) !== true) return '';
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) return '';
    $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
    $xml = preg_replace('/<w:tab[^\/]*\/>/', "\t", $xml) ?? $xml;
    $texte = strip_tags($xml);
    $texte = html_entity_decode($texte, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return trim(preg_replace("/[ \t]+/", ' ', $texte) ?? $texte);
}

function auto_anaq_extraire_pdf(string $chemin): string {
    // 1) pdftotext si disponible
    if (function_exists('shell_exec')) {
        $bin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'pdftotext' : 'pdftotext';
        $cmd = $bin . ' -enc UTF-8 -q ' . escapeshellarg($chemin) . ' - 2>nul';
        $out = @shell_exec($cmd);
        if (is_string($out) && trim($out) !== '') return trim($out);
    }
    // 2) Extraction rudimentaire des flux texte PDF
    $raw = @file_get_contents($chemin);
    if (!is_string($raw) || $raw === '') return '';
    $texte = '';
    if (preg_match_all('/\((\\\\.|[^\\\\)])*\)\s*Tj/s', $raw, $m)) {
        foreach ($m[0] as $frag) {
            if (preg_match('/^\((.*)\)\s*Tj$/s', $frag, $mm)) {
                $s = $mm[1];
                $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $s);
                $texte .= $s . ' ';
            }
        }
    }
    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $raw, $m2)) {
        foreach ($m2[1] as $arr) {
            if (preg_match_all('/\((\\\\.|[^\\\\)])*\)/', $arr, $parts)) {
                foreach ($parts[0] as $p) {
                    $s = substr($p, 1, -1);
                    $s = str_replace(['\\(', '\\)'], ['(', ')'], $s);
                    $texte .= $s;
                }
                $texte .= "\n";
            }
        }
    }
    $texte = trim(preg_replace('/[^\P{C}\n\t]+/u', ' ', $texte) ?? $texte);
    return $texte;
}

/**
 * Parse un texte de référentiel selon le modèle ANAQ :
 * Champ d'évaluation / CH1, Référence 1.1, point 1.1.a
 * Gère aussi le texte multi-lignes (code seul puis contenu en dessous).
 */
function auto_anaq_parser_texte_referentiel(string $texte): array {
    $texte = str_replace(["\r\n", "\r"], "\n", $texte);
    $texte = preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte;
    $lines = preg_split('/\n/', $texte) ?: [];

    $champs = [];
    $references = [];
    $champ_courant = 0;
    $ref_courante = null;
    $attente_texte = null; // critere en cours d'accumulation multi-lignes
    $icone = ['fa-landmark', 'fa-flask', 'fa-users-gear', 'fa-lightbulb', 'fa-folder'];

    $flush_crit = static function () use (&$ref_courante, &$attente_texte) {
        if (!$attente_texte || !$ref_courante) {
            $attente_texte = null;
            return;
        }
        $bloc = trim(preg_replace('/\s+/u', ' ', $attente_texte['texte']) ?? '');
        $ligne = '';
        $question = '';
        $preuves = '';
        if ($bloc !== '') {
            if (preg_match('/^(.+?\?)\s*(.*)$/u', $bloc, $qm)) {
                $question = trim($qm[1]);
                $ligne = $question;
                $preuves = trim($qm[2]);
            } else {
                $ligne = $bloc;
                $question = $bloc;
            }
        }
        if ($ligne !== '' || $question !== '') {
            $ref_courante['criteres'][] = [
                'code' => $attente_texte['code'],
                'ligne_directrice' => $ligne !== '' ? $ligne : $question,
                'question' => $question !== '' ? $question : $ligne,
                'elements_preuves' => $preuves !== '' ? $preuves : null,
                'ordre' => count($ref_courante['criteres']) + 1,
            ];
        }
        $attente_texte = null;
    };

    $flush_ref = static function () use (&$references, &$ref_courante, &$flush_crit) {
        $flush_crit();
        if ($ref_courante && !empty($ref_courante['criteres'])) {
            $references[] = $ref_courante;
        }
        $ref_courante = null;
    };

    $est_structure = static function (string $line): bool {
        return (bool)(
            preg_match('/^(?:CHAMP\s*D[\'’]?\s*ÉVALUATION|CHAMP)\s*\d+/iu', $line)
            || preg_match('/^CH\s*\d+\b/iu', $line)
            || preg_match('/^(?:Référence|Reference|Réf\.?)\s*\d+\.\d+/iu', $line)
            || preg_match('/^\d+\.\d+\s*[:\-–.]/u', $line)
            || preg_match('/^\d+\.\d+\.(?:[a-z0-9]+|\d+)\b/iu', $line)
        );
    };

    $est_bruit = static function (string $line): bool {
        return (bool)(
            preg_match('/^(?:Numéro|Numero|N°|Lignes directrices|Questions|Eléments|Elements)\b/iu', $line)
            || preg_match('/^Forces,\s*faiblesses,\s*opportunités\s*et\s*menaces\s*\(SWOT\)/iu', $line)
            || preg_match('/^(?:Forces|Faiblesses|Opportunités|Menaces)\s*$/iu', $line)
            || preg_match('/^•\s*\.+/u', $line)
        );
    };

    foreach ($lines as $raw) {
        $line = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        if ($line === '' || $est_bruit($line)) continue;

        // Champ : "Champ d'évaluation 1. GOUVERNANCE…" / "Champ 1 :" / "CH1"
        if (preg_match('/^(?:CHAMP\s*D[\'’]?\s*ÉVALUATION|CHAMP)\s*(\d+)\s*[.\-–:\s]+\s*(.+)$/iu', $line, $m)
            || preg_match('/^CH\s*(\d+)\s*[.\-–:\s]+\s*(.+)$/iu', $line, $m)) {
            $flush_ref();
            $champ_courant = (int)$m[1];
            $champs[$champ_courant] = auto_anaq_champ_meta(trim($m[2]), $champ_courant, $icone);
            continue;
        }

        // Référence 1.1. Titre (point ou tiret après le code)
        if (preg_match('/^(?:Référence|Reference|Réf\.?)\s*(\d+\.\d+)\s*[.\-–:\s]+\s*(.+)$/iu', $line, $m)
            || preg_match('/^(\d+\.\d+)(?!\.[a-z0-9])\s*[.\-–:\s]+\s*(.{8,})$/u', $line, $m)) {
            $flush_ref();
            $code = $m[1];
            if ($champ_courant <= 0) {
                $champ_courant = (int)explode('.', $code)[0];
                if (!isset($champs[$champ_courant])) {
                    $champs[$champ_courant] = auto_anaq_champ_meta('', $champ_courant, $icone);
                }
            }
            $ref_courante = [
                'champ_ordre' => $champ_courant,
                'code' => $code,
                'libelle' => auto_anaq_nettoyer_libelle(trim($m[2])),
                'ordre' => (int)(explode('.', $code)[1] ?? count($references) + 1),
                'criteres' => [],
            ];
            continue;
        }

        // Point 1.1.a ou 4.1.1 (texte sur la même ligne OU code seul)
        if (preg_match('/^(\d+\.\d+\.(?:[a-z]+|\d+))\s*[:\-–]?\s*(.*)$/iu', $line, $m)) {
            $flush_crit();
            if (!$ref_courante) {
                $parts = explode('.', $m[1]);
                $parent = $parts[0] . '.' . $parts[1];
                if ($champ_courant <= 0) $champ_courant = (int)$parts[0];
                if (!isset($champs[$champ_courant])) {
                    $champs[$champ_courant] = auto_anaq_champ_meta('', $champ_courant, $icone);
                }
                $ref_courante = [
                    'champ_ordre' => $champ_courant,
                    'code' => $parent,
                    'libelle' => 'Référence ' . $parent,
                    'ordre' => (int)$parts[1],
                    'criteres' => [],
                ];
            }
            $reste = trim($m[2] ?? '');
            $attente_texte = ['code' => $m[1], 'texte' => $reste];
            // Si déjà assez de texte sur la même ligne, on peut finaliser plus tard (flush au prochain marqueur)
            continue;
        }

        // Suite de texte d'un critère multi-lignes
        if ($attente_texte !== null && !$est_structure($line)) {
            $attente_texte['texte'] .= ($attente_texte['texte'] !== '' ? ' ' : '') . $line;
            continue;
        }
    }
    $flush_ref();

    ksort($champs);
    $out = [
        'champs' => array_values($champs),
        'references' => $references,
        'n_criteres' => array_sum(array_map(static fn($r) => count($r['criteres'] ?? []), $references)),
    ];
    $norm = auto_anaq_normaliser_grille($out);
    return $norm['data'];
}

/**
 * Pipeline commun : texte brut → grille en base pour le canevas.
 */
function auto_anaq_importer_texte_referentiel(int $canevas_id, string $texte): array {
    if (!identite_peut_modifier()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    $texte = trim($texte);
    if ($texte === '') {
        return ['succes' => false, 'message' => 'Aucun contenu à importer. Collez le texte du référentiel ou choisissez un fichier.'];
    }

    $parsed = auto_anaq_parser_texte_referentiel($texte);
    $norm = auto_anaq_normaliser_grille($parsed);
    $source = 'parse';

    if (!$norm['ok'] || (int)($norm['data']['n_criteres'] ?? 0) < 3) {
        // Repli : modèle officiel seulement si le parse est vraiment trop faible
        $officiel = auto_anaq_referentiel_officiel_data();
        if (!$officiel) {
            return [
                'succes' => false,
                'message' => 'Impossible de lire le référentiel. Essayez de coller le contenu dans le champ prévu, ou utilisez un fichier Word.',
            ];
        }
        $parsed = $officiel;
        $source = 'modele_anaq';
    } else {
        $parsed = $norm['data'];
        // Si trop de points sans texte ont été écartés, mieux vaut le modèle officiel
        $vides = (int)($norm['vides'] ?? 0);
        $n = (int)($parsed['n_criteres'] ?? 0);
        if ($vides > 0 && $n > 0 && ($vides / ($n + $vides)) > 0.25) {
            $officiel = auto_anaq_referentiel_officiel_data();
            if ($officiel) {
                $parsed = $officiel;
                $source = 'modele_anaq';
            }
        }
    }

    $r = auto_anaq_importer_grille_data($canevas_id, $parsed, true);
    if (!$r['succes']) return $r;

    $msg = $r['message'];
    if ($source === 'modele_anaq') {
        $msg = 'Référentiel chargé avec le modèle complet ANAQ (' . ($r['n_criteres'] ?? 0) . ' points).';
    } else {
        $msg = 'Référentiel importé : ' . ($r['n_champs'] ?? 0) . ' thèmes, '
            . ($r['n_refs'] ?? 0) . ' références, ' . ($r['n_criteres'] ?? 0) . ' points.';
    }
    $r['message'] = $msg;
    $r['source'] = $source;
    log_activite(user_id(), 'IDENTITE_CRICT', 'CREATION',
        'ANAQ import grille canevas #' . $canevas_id . ' (' . $source . ')');
    return $r;
}

/**
 * Pipeline : fichier PDF/Word/TXT → grille en base pour le canevas.
 */
function auto_anaq_importer_fichier_referentiel(int $canevas_id, string $chemin_absolu, string $nom_original): array {
    if (!identite_peut_modifier()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    if (!is_file($chemin_absolu)) {
        return ['succes' => false, 'message' => 'Fichier introuvable.'];
    }
    $ext = strtolower(pathinfo($nom_original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'doc', 'docx', 'odt', 'txt'], true)) {
        return ['succes' => false, 'message' => 'Format non supporté. Préférez un fichier Word (.docx) ou collez le texte.'];
    }

    $texte = auto_anaq_extraire_texte($chemin_absolu, $ext);
    if (trim($texte) === '') {
        return [
            'succes' => false,
            'message' => 'Le fichier n\'a pas pu être lu. Essayez de coller le contenu du référentiel dans le champ prévu.',
        ];
    }
    return auto_anaq_importer_texte_referentiel($canevas_id, $texte);
}

