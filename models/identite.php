<?php
// ============================================================
// models/identite.php — Identité institutionnelle du CRICT
// ============================================================

const IDENTITE_UPLOAD_SUBDIR = 'identite';
const IDENTITE_A_COMPLETER   = 'À compléter';
const IDENTITE_MAX_PIECE_SIZE = 20 * 1024 * 1024; // 20 Mo par pièce justificative

function identite_peut_modifier(): bool {
    // Droit d'écriture délégable par le Super Admin via la matrice des permissions
    // (a_permission laisse déjà passer le groupe SUPER_ADMIN).
    return a_permission('IDENTITE_CRICT', 'MODIFIER');
}

function identite_upload_dir(): string {
    $dir = UPLOAD_DIR . IDENTITE_UPLOAD_SUBDIR . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

// ── Identification ───────────────────────────────────────────

function identite_identification_lister(): array {
    $db = get_db();
    return $db->query("
        SELECT i.*, u.prenoms, u.nom
        FROM identite_identification i
        LEFT JOIN utilisateurs u ON u.id = i.modifie_par
        ORDER BY i.ordre
    ")->fetchAll();
}

function identite_identification_sauver(array $valeurs): bool {
    $db  = get_db();
    $uid = user_id();
    $ok  = true;
    foreach ($valeurs as $cle => $valeur) {
        $cle = preg_replace('/[^a-z_]/', '', $cle);
        if ($cle === '') continue;
        $stmt = $db->prepare("
            UPDATE identite_identification
            SET valeur = ?, modifie_par = ?, modifie_le = NOW()
            WHERE cle = ?
        ");
        $ok = $stmt->execute([trim($valeur) ?: null, $uid, $cle]) && $ok;
    }
    if ($ok) {
        log_activite($uid, 'IDENTITE_CRICT', 'MODIFICATION', 'Mise à jour identification institutionnelle');
    }
    return $ok;
}

// ── Champs & critères ────────────────────────────────────────

function identite_champs_lister(bool $actifs_seulement = false): array {
    $db = get_db();
    $sql = "SELECT * FROM identite_champs";
    if ($actifs_seulement) {
        $sql .= " WHERE actif = 1";
    }
    $sql .= " ORDER BY ordre";
    return $db->query($sql)->fetchAll();
}

function identite_champ_par_id(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM identite_champs WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function identite_criteres_par_champ(int $champ_id): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT c.*,
               ct.reponse_officielle, ct.preuves_disponibles, ct.score, ct.valeur_numerique,
               ct.modifie_le AS contenu_modifie_le, ct.modifie_par AS contenu_modifie_par,
               u.prenoms AS mod_prenoms, u.nom AS mod_nom,
               (SELECT COUNT(*) FROM identite_pieces p WHERE p.critere_id = c.id) AS nb_pieces
        FROM identite_criteres c
        LEFT JOIN identite_contenu ct ON ct.critere_id = c.id
        LEFT JOIN utilisateurs u ON u.id = ct.modifie_par
        WHERE c.champ_id = ?
        ORDER BY c.ordre
    ");
    $stmt->execute([$champ_id]);
    return $stmt->fetchAll();
}

function identite_critere_par_id(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT c.*, ch.libelle AS champ_libelle, ch.type_notation,
               ct.reponse_officielle, ct.preuves_disponibles, ct.score, ct.valeur_numerique,
               ct.modifie_le AS contenu_modifie_le
        FROM identite_criteres c
        JOIN identite_champs ch ON ch.id = c.champ_id
        LEFT JOIN identite_contenu ct ON ct.critere_id = c.id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function identite_recherche(string $q, int $limit = 40): array {
    return identite_recherche_etendue($q, $limit);
}

function identite_recherche_etendue(string $q, int $limit = 40): array {
    $q = trim($q);
    if ($q === '' || mb_strlen($q) < 1) {
        return [];
    }
    $db   = get_db();
    $like = '%' . $q . '%';
    $out  = [];

    $stmt = $db->prepare("
        SELECT c.id, c.code, c.libelle, c.champ_id, ch.libelle AS champ_libelle,
               ct.reponse_officielle, ct.preuves_disponibles, ct.score,
               c.indicateurs_cles, c.sources_verification
        FROM identite_criteres c
        JOIN identite_champs ch ON ch.id = c.champ_id AND ch.actif = 1
        LEFT JOIN identite_contenu ct ON ct.critere_id = c.id
        WHERE c.code LIKE ? OR c.libelle LIKE ? OR c.indicateurs_cles LIKE ?
              OR c.sources_verification LIKE ?
              OR ct.reponse_officielle LIKE ? OR ct.preuves_disponibles LIKE ?
        ORDER BY ch.ordre, c.ordre
        LIMIT ?
    ");
    $stmt->execute([$like, $like, $like, $like, $like, $like, $limit]);
    foreach ($stmt->fetchAll() as $row) {
        $snippet = identite_recherche_extrait($row, $q);
        $out[] = [
            'type'     => 'critere',
            'id'       => (int)$row['id'],
            'code'     => $row['code'],
            'titre'    => $row['libelle'],
            'sous'     => $row['champ_libelle'],
            'url'      => 'champ.php?id=' . (int)$row['champ_id'] . '#critere-' . (int)$row['id'],
            'snippet'  => $snippet,
            'score'    => $row['score'] !== null ? (int)$row['score'] : null,
            'champ_id' => (int)$row['champ_id'],
        ];
    }

    if (count($out) < $limit) {
        $rest = $limit - count($out);
        $stmt = $db->prepare("
            SELECT cle, libelle, valeur FROM identite_identification
            WHERE libelle LIKE ? OR valeur LIKE ?
            LIMIT ?
        ");
        $stmt->execute([$like, $like, $rest]);
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'type'    => 'identification',
                'id'      => $row['cle'],
                'code'    => 'ID',
                'titre'   => $row['libelle'],
                'sous'    => 'Identification institutionnelle',
                'url'     => 'identification.php',
                'snippet' => identite_recherche_tronquer($row['valeur'] ?? '', $q),
                'score'   => null,
            ];
        }
    }

    if (count($out) < $limit) {
        $rest = $limit - count($out);
        $stmt = $db->prepare("
            SELECT id, libelle, description FROM identite_champs
            WHERE actif = 1 AND (libelle LIKE ? OR description LIKE ?)
            LIMIT ?
        ");
        $stmt->execute([$like, $like, $rest]);
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'type'     => 'champ',
                'id'       => (int)$row['id'],
                'code'     => 'Champ ' . $row['id'],
                'titre'    => $row['libelle'],
                'sous'     => 'Champ thématique',
                'url'      => 'champ.php?id=' . (int)$row['id'],
                'snippet'  => identite_recherche_tronquer($row['description'] ?? '', $q),
                'score'    => null,
                'champ_id' => (int)$row['id'],
            ];
        }
    }

    return $out;
}

function identite_recherche_tronquer(?string $texte, string $q, int $len = 120): string {
    $texte = trim((string)$texte);
    if ($texte === '') return '';
    $pos = mb_stripos($texte, $q);
    if ($pos === false) {
        return mb_strlen($texte) > $len ? mb_substr($texte, 0, $len) . '…' : $texte;
    }
    $start = max(0, $pos - 40);
    $extrait = ($start > 0 ? '…' : '') . mb_substr($texte, $start, $len);
    if ($start + $len < mb_strlen($texte)) {
        $extrait .= '…';
    }
    return $extrait;
}

function identite_recherche_extrait(array $row, string $q): string {
    foreach (['reponse_officielle', 'preuves_disponibles', 'libelle', 'indicateurs_cles', 'sources_verification'] as $k) {
        $v = $row[$k] ?? '';
        if ($v !== '' && mb_stripos($v, $q) !== false) {
            return identite_recherche_tronquer($v, $q);
        }
    }
    return identite_recherche_tronquer($row['reponse_officielle'] ?? $row['libelle'] ?? '', $q);
}

function identite_champs_moyennes(): array {
    $db = get_db();
    $rows = $db->query("
        SELECT c.champ_id, ROUND(AVG(ct.score), 1) AS moy
        FROM identite_criteres c
        JOIN identite_contenu ct ON ct.critere_id = c.id
        WHERE c.type_saisie = 'SCORE' AND ct.score IS NOT NULL
        GROUP BY c.champ_id
    ")->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['champ_id']] = (float)$r['moy'];
    }
    return $map;
}

// ── Échelles ─────────────────────────────────────────────────

function identite_echelle_libelle(string $type_notation, ?int $score): ?array {
    if ($score === null) return null;
    $db = get_db();
    $stmt = $db->prepare("
        SELECT score, libelle, signification
        FROM identite_echelles
        WHERE type_notation = ? AND score = ?
        LIMIT 1
    ");
    $stmt->execute([$type_notation, $score]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function identite_echelles_par_type(string $type_notation): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT score, libelle, signification
        FROM identite_echelles
        WHERE type_notation = ?
        ORDER BY score
    ");
    $stmt->execute([$type_notation]);
    return $stmt->fetchAll();
}

// ── Contenu critère ──────────────────────────────────────────

function identite_echelle_type_critere(array $critere, array $champ): string {
    if (($critere['type_saisie'] ?? 'SCORE') === 'NOMBRE') {
        return 'NOMBRE';
    }
    $tn = $champ['type_notation'] ?? 'DISPOSITIF';
    if (in_array($tn, ['DISPOSITIF', 'MIXTE'], true)) {
        return 'DISPOSITIF';
    }
    if ($tn === 'RH') {
        return 'RH';
    }
    return 'SCORE';
}

function identite_contenu_sauver(
    int $critere_id,
    ?string $reponse,
    ?int $score,
    ?string $preuves = null,
    ?float $valeur_numerique = null
): bool {
    $db  = get_db();
    $uid = user_id();
    $score = ($score !== null && $score >= 0 && $score <= 5) ? $score : null;

    $stmt = $db->prepare("
        INSERT INTO identite_contenu
            (critere_id, reponse_officielle, preuves_disponibles, score, valeur_numerique, modifie_par, modifie_le)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            reponse_officielle = VALUES(reponse_officielle),
            preuves_disponibles = VALUES(preuves_disponibles),
            score = VALUES(score),
            valeur_numerique = VALUES(valeur_numerique),
            modifie_par = VALUES(modifie_par),
            modifie_le = NOW()
    ");
    $ok = $stmt->execute([
        $critere_id,
        trim($reponse ?? '') ?: null,
        trim($preuves ?? '') ?: null,
        $score,
        $valeur_numerique,
        $uid,
    ]);

    if ($ok) {
        $crit = identite_critere_par_id($critere_id);
        log_activite($uid, 'IDENTITE_CRICT', 'MODIFICATION',
            'Critère ' . ($crit['code'] ?? $critere_id) . ' mis à jour');
    }
    return $ok;
}

// ── Référentiels d'évaluation (DGRS, auto-évaluation) ──
// Le contenu institutionnel (réponses + preuves + documents) est un SOCLE COMMUN.
// Chaque référentiel pose par-dessus sa propre note + commentaire par critère.

function identite_referentiels(): array {
    return [
        'DGRS' => [
            'code'        => 'DGRS',
            'libelle'     => 'DGRS',
            'nom_complet' => 'Direction Générale de la Recherche Scientifique',
            'description' => 'Évaluation selon le référentiel de la DGRS.',
            'icone'       => 'fa-building-columns',
        ],
        'AUTO_EVALUATION_ANAQ' => [
            'code'        => 'AUTO_EVALUATION_ANAQ',
           
            'description' => 'Auto-évaluation interne préparatoire pour anticiper l\'évaluation ANAQ.',
            'icone'       => 'fa-shield-halved',
        ],
    ];
}

function identite_referentiel(?string $code): ?array {
    return identite_referentiels()[$code] ?? null;
}

function identite_assurer_schema_referentiels(): void {
    $db = get_db();
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS identite_referentiel_contenu (
              referentiel varchar(30) NOT NULL,
              critere_id smallint UNSIGNED NOT NULL,
              score tinyint UNSIGNED DEFAULT NULL,
              commentaire text DEFAULT NULL,
              modifie_par int UNSIGNED DEFAULT NULL,
              modifie_le datetime DEFAULT NULL,
              PRIMARY KEY (referentiel, critere_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        // table déjà présente ou droits insuffisants
    }
}

function identite_referentiel_scores_par_champ(string $referentiel, int $champ_id): array {
    $db = get_db();
    try {
        $stmt = $db->prepare("
            SELECT rc.critere_id, rc.score, rc.commentaire, rc.modifie_le
            FROM identite_referentiel_contenu rc
            JOIN identite_criteres c ON c.id = rc.critere_id
            WHERE rc.referentiel = ? AND c.champ_id = ?
        ");
        $stmt->execute([$referentiel, $champ_id]);
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['critere_id']] = [
            'score'       => $r['score'] !== null ? (int)$r['score'] : null,
            'commentaire' => $r['commentaire'],
            'modifie_le'  => $r['modifie_le'],
        ];
    }
    return $map;
}

function identite_referentiel_sauver(string $referentiel, int $critere_id, ?int $score, ?string $commentaire): bool {
    if (!identite_referentiel($referentiel) || $critere_id <= 0) {
        return false;
    }
    $db = get_db();
    $score = ($score !== null && $score >= 0 && $score <= 5) ? $score : null;
    try {
        $stmt = $db->prepare("
            INSERT INTO identite_referentiel_contenu
                (referentiel, critere_id, score, commentaire, modifie_par, modifie_le)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                commentaire = VALUES(commentaire),
                modifie_par = VALUES(modifie_par),
                modifie_le = NOW()
        ");
        return $stmt->execute([
            $referentiel,
            $critere_id,
            $score,
            trim((string)$commentaire) ?: null,
            user_id(),
        ]);
    } catch (Throwable $e) {
        return false;
    }
}

function identite_referentiel_moyennes(string $referentiel): array {
    $db = get_db();
    try {
        $stmt = $db->prepare("
            SELECT c.champ_id, ROUND(AVG(rc.score), 1) AS moy
            FROM identite_referentiel_contenu rc
            JOIN identite_criteres c ON c.id = rc.critere_id
            WHERE rc.referentiel = ? AND rc.score IS NOT NULL
            GROUP BY c.champ_id
        ");
        $stmt->execute([$referentiel]);
    } catch (Throwable $e) {
        return [];
    }
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['champ_id']] = (float)$r['moy'];
    }
    return $map;
}

function identite_referentiel_moyenne_globale(string $referentiel): ?float {
    $db = get_db();
    try {
        $stmt = $db->prepare("
            SELECT AVG(score) FROM identite_referentiel_contenu
            WHERE referentiel = ? AND score IS NOT NULL
        ");
        $stmt->execute([$referentiel]);
        $m = $stmt->fetchColumn();
        return ($m !== false && $m !== null) ? round((float)$m, 1) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function identite_referentiel_completude(string $referentiel): array {
    $db = get_db();
    $total = (int)$db->query("SELECT COUNT(*) FROM identite_criteres c JOIN identite_champs ch ON ch.id = c.champ_id WHERE ch.actif = 1")->fetchColumn();
    $notes = 0;
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM identite_referentiel_contenu rc
            JOIN identite_criteres c ON c.id = rc.critere_id
            JOIN identite_champs ch ON ch.id = c.champ_id
            WHERE rc.referentiel = ? AND rc.score IS NOT NULL AND ch.actif = 1
        ");
        $stmt->execute([$referentiel]);
        $notes = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $notes = 0;
    }
    return ['notes' => $notes, 'total' => $total];
}

// ── Observations champ ───────────────────────────────────────

function identite_observations_lire(int $champ_id): array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM identite_observations WHERE champ_id = ? LIMIT 1");
    $stmt->execute([$champ_id]);
    $row = $stmt->fetch();
    return $row ?: ['champ_id' => $champ_id, 'forces' => null, 'faiblesses' => null, 'niveau_global' => null];
}

function identite_observations_sauver(int $champ_id, ?string $forces, ?string $faiblesses, ?string $niveau): bool {
    $db  = get_db();
    $uid = user_id();
    $stmt = $db->prepare("
        INSERT INTO identite_observations (champ_id, forces, faiblesses, niveau_global, modifie_par, modifie_le)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            forces = VALUES(forces),
            faiblesses = VALUES(faiblesses),
            niveau_global = VALUES(niveau_global),
            modifie_par = VALUES(modifie_par),
            modifie_le = NOW()
    ");
    return $stmt->execute([
        $champ_id,
        trim($forces ?? '') ?: null,
        trim($faiblesses ?? '') ?: null,
        trim($niveau ?? '') ?: null,
        $uid,
    ]);
}

// ── Pièces jointes ───────────────────────────────────────────

function identite_pieces_lister(int $critere_id): array {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT p.*, u.prenoms, u.nom
        FROM identite_pieces p
        LEFT JOIN utilisateurs u ON u.id = p.depose_par
        WHERE p.critere_id = ?
        ORDER BY p.cree_le DESC
    ");
    $stmt->execute([$critere_id]);
    return $stmt->fetchAll();
}

function identite_piece_par_id(int $id): ?array {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM identite_pieces WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function identite_piece_ajouter(int $critere_id, array $file): array {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['succes' => false, 'message' => 'Fichier invalide ou absent.'];
    }
    if ($file['size'] > IDENTITE_MAX_PIECE_SIZE) {
        return ['succes' => false, 'message' => 'Fichier trop volumineux (max 20 Mo).'];
    }

    $ext_ok = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx',
               'odt', 'ods', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_ok, true)) {
        return ['succes' => false, 'message' => 'Format non autorisé (PDF, Word, Excel, texte, images).'];
    }

    $stockage = 'id_' . $critere_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest     = identite_upload_dir() . $stockage;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['succes' => false, 'message' => 'Échec de l\'enregistrement du fichier.'];
    }

    $db = get_db();
    $db->prepare("
        INSERT INTO identite_pieces
        (critere_id, nom_original, nom_stockage, chemin_fichier, type_mime, taille_fichier, depose_par)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $critere_id,
        $file['name'],
        $stockage,
        $dest,
        $file['type'] ?? null,
        (int)$file['size'],
        user_id(),
    ]);

    log_activite(user_id(), 'IDENTITE_CRICT', 'CREATION', 'Pièce jointe critère ID ' . $critere_id);
    return ['succes' => true, 'id' => (int)$db->lastInsertId()];
}

function identite_piece_supprimer(int $piece_id): bool {
    $piece = identite_piece_par_id($piece_id);
    if (!$piece) return false;
    if (is_file($piece['chemin_fichier'])) {
        @unlink($piece['chemin_fichier']);
    }
    get_db()->prepare("DELETE FROM identite_pieces WHERE id = ?")->execute([$piece_id]);
    log_activite(user_id(), 'IDENTITE_CRICT', 'SUPPRESSION', 'Pièce ID ' . $piece_id);
    return true;
}

function identite_servir_fichier(int $piece_id, bool $download = false): void {
    $piece = identite_piece_par_id($piece_id);
    if (!$piece || !is_file($piece['chemin_fichier'])) {
        http_response_code(404);
        exit('Fichier introuvable.');
    }
    $mime = $piece['type_mime'] ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($piece['chemin_fichier']));
    $disp = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($piece['nom_original']) . '"');
    readfile($piece['chemin_fichier']);
    exit;
}

// ── Statistiques / affichage ─────────────────────────────────

function identite_est_complete(?string $texte): bool {
    $t = trim((string)$texte);
    return $t !== '' && $t !== IDENTITE_A_COMPLETER;
}

function identite_score_couleur(?int $score): string {
    if ($score === null) return '#94a3b8';
    if ($score >= 4) return '#22c55e';
    if ($score >= 3) return '#3b82f6';
    if ($score >= 2) return '#f59e0b';
    return '#ef4444';
}

function identite_champ_score_moyen(int $champ_id): ?float {
    $db = get_db();
    $stmt = $db->prepare("
        SELECT AVG(ct.score) AS moy
        FROM identite_criteres c
        JOIN identite_contenu ct ON ct.critere_id = c.id
        WHERE c.champ_id = ? AND c.type_saisie = 'SCORE' AND ct.score IS NOT NULL
    ");
    $stmt->execute([$champ_id]);
    $m = $stmt->fetchColumn();
    return $m !== false && $m !== null ? round((float)$m, 1) : null;
}

function identite_completude(): array {
    $id_fields = identite_identification_lister();
    $id_ok = 0;
    foreach ($id_fields as $f) {
        if (identite_est_complete($f['valeur'] ?? '')) $id_ok++;
    }

    $criteres = identite_criteres_par_champ(1);
    $rep_ok = 0;
    $score_ok = 0;
    foreach ($criteres as $c) {
        if (identite_est_complete($c['reponse_officielle'] ?? '')) $rep_ok++;
        if ($c['score'] !== null) $score_ok++;
    }

    return [
        'identification' => ['ok' => $id_ok, 'total' => count($id_fields)],
        'reponses'       => ['ok' => $rep_ok, 'total' => count($criteres)],
        'scores'         => ['ok' => $score_ok, 'total' => count($criteres)],
    ];
}

function identite_derniere_maj(): ?string {
    $db = get_db();
    $row = $db->query("
        SELECT MAX(d) AS derniere FROM (
            SELECT modifie_le AS d FROM identite_identification WHERE modifie_le IS NOT NULL
            UNION ALL
            SELECT modifie_le FROM identite_contenu WHERE modifie_le IS NOT NULL
            UNION ALL
            SELECT modifie_le FROM identite_observations WHERE modifie_le IS NOT NULL
        ) t
    ")->fetch();
    return $row['derniere'] ?? null;
}

// ── Indicateurs quantitatifs (Champ 3) ───────────────────────

function identite_indicateurs_lister(): array {
    $db = get_db();
    try {
        return $db->query("SELECT * FROM identite_indicateurs ORDER BY ordre")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function identite_indicateurs_sauver(array $valeurs): bool {
    $db = get_db();
    try {
        try { $db->exec('ALTER TABLE identite_indicateurs MODIFY valeur TEXT'); } catch (Throwable $e) {}
        $stmt = $db->prepare("UPDATE identite_indicateurs SET valeur = ? WHERE cle = ?");
        foreach ($valeurs as $cle => $val) {
            $cle = preg_replace('/[^a-z_0-9]/', '', $cle);
            if ($cle === '') continue;
            $stmt->execute([trim($val) ?: null, $cle]);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

// ── SWOT & recommandations ───────────────────────────────────

function identite_swot_lister(): array {
    $db = get_db();
    try {
        $rows = $db->query("SELECT * FROM identite_swot ORDER BY ligne")->fetchAll();
        if (count($rows) < 3) {
            for ($i = 1; $i <= 3; $i++) {
                $rows[] = ['ligne' => $i, 'forces' => null, 'faiblesses' => null, 'opportunites' => null, 'menaces' => null];
            }
        }
        return $rows;
    } catch (Throwable $e) {
        return [
            ['ligne' => 1, 'forces' => null, 'faiblesses' => null, 'opportunites' => null, 'menaces' => null],
            ['ligne' => 2, 'forces' => null, 'faiblesses' => null, 'opportunites' => null, 'menaces' => null],
            ['ligne' => 3, 'forces' => null, 'faiblesses' => null, 'opportunites' => null, 'menaces' => null],
        ];
    }
}

function identite_swot_sauver(array $lignes): bool {
    $db = get_db();
    try {
        $stmt = $db->prepare("
            INSERT INTO identite_swot (ligne, forces, faiblesses, opportunites, menaces)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                forces = VALUES(forces), faiblesses = VALUES(faiblesses),
                opportunites = VALUES(opportunites), menaces = VALUES(menaces)
        ");
        foreach ($lignes as $n => $row) {
            $ligne = (int)$n;
            if ($ligne < 1 || $ligne > 3) continue;
            $stmt->execute([
                $ligne,
                trim($row['forces'] ?? '') ?: null,
                trim($row['faiblesses'] ?? '') ?: null,
                trim($row['opportunites'] ?? '') ?: null,
                trim($row['menaces'] ?? '') ?: null,
            ]);
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function identite_recommandations_lire(): ?string {
    $db = get_db();
    try {
        $row = $db->query("SELECT contenu FROM identite_recommandations WHERE id = 1 LIMIT 1")->fetch();
        return $row['contenu'] ?? null;
    } catch (Throwable $e) {
        return null;
    }
}

function identite_recommandations_sauver(?string $contenu): bool {
    $db = get_db();
    try {
        // Assurer la capacité pour les longs textes
        try { $db->exec('ALTER TABLE identite_recommandations MODIFY contenu MEDIUMTEXT'); } catch (Throwable $e) {}
        $db->prepare("
            INSERT INTO identite_recommandations (id, contenu) VALUES (1, ?)
            ON DUPLICATE KEY UPDATE contenu = VALUES(contenu)
        ")->execute([trim($contenu ?? '') ?: null]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function identite_tronquer(?string $texte, int $max = 48): string {
    $texte = trim(preg_replace('/\s+/u', ' ', (string)$texte));
    if ($texte === '' || !identite_est_complete($texte)) {
        return '';
    }
    return mb_strlen($texte) > $max ? mb_substr($texte, 0, $max) . '…' : $texte;
}

function identite_swot_ligne_pour_apercu(array $swot): array {
    foreach ($swot as $row) {
        foreach (['forces', 'faiblesses', 'opportunites', 'menaces'] as $k) {
            if (identite_est_complete($row[$k] ?? '')) {
                return $row;
            }
        }
    }
    return $swot[0] ?? [
        'ligne' => 1, 'forces' => null, 'faiblesses' => null,
        'opportunites' => null, 'menaces' => null,
    ];
}

function identite_swot_texte_recherche(array $swot, ?string $recommandations): string {
    $parts = ['swot forces faiblesses opportunités menaces recommandations analyse'];
    foreach ($swot as $row) {
        foreach (['forces', 'faiblesses', 'opportunites', 'menaces'] as $k) {
            if (!empty($row[$k])) {
                $parts[] = $row[$k];
            }
        }
    }
    if ($recommandations) {
        $parts[] = $recommandations;
    }
    return mb_strtolower(implode(' ', $parts));
}

// ── Préremplissage initial ────────────────────────────────────

function identite_assurer_schema_extension(): void {
    $db = get_db();
    try {
        $db->query("SELECT preuves_disponibles FROM identite_contenu LIMIT 1");
    } catch (Throwable $e) {
        try {
            $db->exec("ALTER TABLE identite_contenu ADD COLUMN preuves_disponibles text DEFAULT NULL AFTER reponse_officielle");
        } catch (Throwable $e2) {
            // colonne déjà présente ou droits insuffisants
        }
    }
    $db->exec("UPDATE identite_champs SET actif = 1");
}

function identite_inserer_criteres_extension(): void {
    $db = get_db();
    $criteres = [
        [2,'2.1','Cohérence des axes de recherche','Alignement avec les priorités nationales','Agendas scientifiques','SCORE',1],
        [2,'2.2','Existence de programmes/projets actifs','Nombre de projets exécutés','Rapports de projets','SCORE',2],
        [2,'2.3','Dimension pluridisciplinaire','Collaboration entre disciplines','Programmes de recherche','SCORE',3],
        [2,'2.4','Production scientifique','Articles, ouvrages, communications','Liste des publications','SCORE',4],
        [2,'2.5','Publications indexées','Présence dans Scopus, WoS, DOAJ','Bases de données','SCORE',5],
        [2,'2.6','Encadrement scientifique','Masters et doctorats encadrés','Registres académiques','SCORE',6],
        [2,'2.7','Partenariats scientifiques','Coopérations nationales et internationales','Conventions','SCORE',7],
        [2,'2.8','Innovation et valorisation','Brevets, prototypes, innovations','Rapports techniques','SCORE',8],
        [2,'2.9','Diffusion des résultats','Colloques, vulgarisation, médias','Rapports d\'activités','SCORE',9],
        [3,'3.1','Adéquation profils/missions','Qualification des chercheurs','CV, listes du personnel','SCORE',1],
        [3,'3.2','Suffisance des effectifs','Ratio chercheurs/projets','Base RH','SCORE',2],
        [3,'3.3','Présence de chercheurs seniors','Rang magistral, HDR','Dossiers administratifs','SCORE',3],
        [3,'3.4','Formation continue','Plan de renforcement des capacités','Plan de formation','SCORE',4],
        [3,'3.5','Gestion prévisionnelle des RH','GPEC disponible','Documents RH','SCORE',5],
        [3,'3.6','Disponibilité du personnel technique','Techniciens, ingénieurs de labo','Organigramme','SCORE',6],
        [3,'3.7','Motivation et stabilité du personnel','Rotation, satisfaction','Enquêtes internes','SCORE',7],
        [4,'4.1','Disponibilité des laboratoires','Nombre et fonctionnalité','Visites terrain','NOMBRE',1],
        [4,'4.2','État des équipements scientifiques','Fonctionnels / non fonctionnels','Inventaire','NOMBRE',2],
        [4,'4.3','Maintenance des équipements','Plan de maintenance','Rapports techniques','NOMBRE',3],
        [4,'4.4','Connectivité et numérique','Internet, bases de données','Observation','NOMBRE',4],
        [4,'4.5','Sécurité / HSE','Politique sécurité/laboratoire','Rapports HSE','SCORE',5],
        [4,'4.6','Moyens logistiques','Véhicules, groupes électrogènes','Inventaire','SCORE',6],
        [4,'4.7','Documentation scientifique','Bibliothèque, plateformes','Abonnements','SCORE',7],
        [5,'5.1','Stratégie de mobilisation des ressources','Diversification des financements','Plans financiers','SCORE',1],
        [5,'5.2','Niveau de financement des activités','Budget recherche','Budget annuel','SCORE',2],
        [5,'5.3','Diversité des partenaires financiers','Public / privé / international','Conventions','SCORE',3],
        [5,'5.4','Procédures financières','Manuel de procédures','Audit','SCORE',4],
        [5,'5.5','Transparence financière','Rapports financiers disponibles','États financiers','SCORE',5],
        [5,'5.6','Viabilité économique','Pérennité des financements','Analyse financière','SCORE',6],
        [6,'6.1','Culture de l\'innovation','Activités de sensibilisation','Rapports','SCORE',1],
        [6,'6.2','Collaboration avec le secteur productif','Partenariats industrie/entreprises','Accords','SCORE',2],
        [6,'6.3','Valorisation des résultats','Brevets, incubations','Rapports','SCORE',3],
        [6,'6.4','Contribution aux politiques publiques','Études stratégiques','Notes techniques','SCORE',4],
        [6,'6.5','Impact socio-économique','Adoption des résultats','Témoignages, études','SCORE',5],
        [6,'6.6','Rayonnement national/international','Réseaux scientifiques','Consortiums','SCORE',6],
    ];
    $stmt = $db->prepare("
        INSERT IGNORE INTO identite_criteres
        (champ_id, code, libelle, indicateurs_cles, sources_verification, type_saisie, ordre)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($criteres as $c) {
        $stmt->execute($c);
    }
    $db->exec("
        INSERT IGNORE INTO identite_contenu (critere_id, reponse_officielle, score)
        SELECT c.id, NULL, NULL FROM identite_criteres c
        LEFT JOIN identite_contenu ct ON ct.critere_id = c.id
        WHERE ct.critere_id IS NULL
    ");
    for ($ch = 2; $ch <= 6; $ch++) {
        $db->prepare("INSERT IGNORE INTO identite_observations (champ_id) VALUES (?)")->execute([$ch]);
    }
    $echelles_rh = [
        [0,'Inexistant','Absence d\'organisation administrative fonctionnelle.'],
        [1,'Très faible','Les insuffisances organisationnelles compromettent les activités de l\'institution.'],
        [2,'Faible','Le fonctionnement administratif est perturbé par un déficit important de ressources humaines.'],
        [3,'Moyen / Satisfaisant','Les structures existent mais présentent des insuffisances importantes en personnel ou en organisation.'],
        [4,'Très satisfaisant','L\'organisation fonctionne correctement malgré quelques insuffisances limitées.'],
        [5,'Excellent','L\'organisation administrative est structurée, les postes sont pourvus et les ressources humaines sont qualifiées et suffisantes.'],
    ];
    $stmtRh = $db->prepare("
        INSERT INTO identite_echelles (type_notation, score, libelle, signification) VALUES ('RH', ?, ?, ?)
        ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), signification = VALUES(signification)
    ");
    foreach ($echelles_rh as $e) {
        $stmtRh->execute($e);
    }
    $echelles_score = [
        [0,'Inexistant','Aucun dispositif ou preuve disponible'],
        [1,'Très faible','Dispositif embryonnaire ou non fonctionnel'],
        [2,'Faible','Dispositif existant mais insuffisant'],
        [3,'Moyen','Dispositif fonctionnel mais perfectible'],
        [4,'Bon','Dispositif satisfaisant et opérationnel'],
        [5,'Excellent','Bonne pratique institutionnalisée'],
    ];
    $stmtSc = $db->prepare("
        INSERT INTO identite_echelles (type_notation, score, libelle, signification) VALUES ('SCORE', ?, ?, ?)
        ON DUPLICATE KEY UPDATE libelle = VALUES(libelle), signification = VALUES(signification)
    ");
    foreach ($echelles_score as $e) {
        $stmtSc->execute($e);
    }
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS identite_indicateurs (
              cle varchar(50) NOT NULL PRIMARY KEY,
              libelle varchar(200) NOT NULL,
              valeur text DEFAULT NULL,
              ordre tinyint UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $inds = [
            ['nb_chercheurs', 'Nombre total de chercheurs', 1],
            ['nb_phd', 'Nombre de PhD', 2],
            ['nb_publications', 'Nombre de publications 2024–2025', 3],
            ['nb_publications_indexees', 'Nombre de publications indexées', 4],
            ['nb_projets_finances', 'Nombre de projets financés', 5],
            ['nb_partenariats', 'Nombre de partenaires actifs', 6],
            ['nb_brevets', 'Nombre de brevets/prototypes', 7],
        ];
        $si = $db->prepare("INSERT IGNORE INTO identite_indicateurs (cle, libelle, ordre) VALUES (?, ?, ?)");
        foreach ($inds as $i) {
            $si->execute($i);
        }
        $db->exec("
            CREATE TABLE IF NOT EXISTS identite_swot (
              ligne tinyint UNSIGNED NOT NULL PRIMARY KEY,
              forces text, faiblesses text, opportunites text, menaces text
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("INSERT IGNORE INTO identite_swot (ligne) VALUES (1),(2),(3)");
        $db->exec("
            CREATE TABLE IF NOT EXISTS identite_recommandations (
              id tinyint UNSIGNED NOT NULL PRIMARY KEY,
              contenu text
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("INSERT IGNORE INTO identite_recommandations (id, contenu) VALUES (1, NULL)");
    } catch (Throwable $e) {
        // tables optionnelles
    }
}

function identite_reponses_types(): array {
    return [
        '1.1' => 'Le CRICT est une institution de recherche régie par un cadre juridique comprenant l\'acte de création, le statut juridique et les textes organiques. Les références officielles (décrets, arrêtés, règlement intérieur) sont tenues à la disposition des partenaires et des missions d\'évaluation.',
        '1.2' => 'Le CRICT dispose d\'un organigramme fonctionnel validé par la direction, reflétant la structure des départements, des fonctions de pilotage et des unités opérationnelles. L\'organigramme officiel est appliqué au quotidien au sein de l\'institution.',
        '1.3' => 'Les organes de gouvernance du CRICT (conseil scientifique, instances de direction, réunions de pilotage) se réunissent régulièrement pour orienter les activités. Les procès-verbaux et décisions officielles attestent du fonctionnement de ces instances.',
        '1.4' => 'Le CRICT s\'appuie sur un plan stratégique aligné sur les priorités nationales en matière de recherche, d\'innovation et de transformation numérique. Ce plan guide les axes de développement scientifique et institutionnel du centre.',
        '1.5' => 'Un dispositif interne de suivi-évaluation permet de mesurer l\'avancement des activités du centre. Des rapports périodiques et des outils de suivi sont produits pour le pilotage institutionnel.',
        '1.6' => 'Le CRICT met en œuvre une démarche d\'assurance qualité incluant l\'autoévaluation et des plans d\'amélioration continue. Les rapports qualité documentent cette démarche au sein de l\'institution.',
        '1.7' => 'Le CRICT promeut l\'éthique et l\'intégrité scientifique à travers une politique institutionnelle, un code déontologique et, le cas échéant, un comité d\'éthique. Les chartes et décisions afférentes encadrent la conduite de la recherche.',
        '1.8' => 'Le CRICT assure sa communication institutionnelle via son site web, sa visibilité scientifique et la diffusion de ses activités (publications, événements, réseaux professionnels). Ces canaux renforcent la transparence et le rayonnement du centre.',
        '2.1' => 'Les axes de recherche du CRICT sont alignés sur les priorités nationales en cybersécurité, intelligence artificielle et transformation numérique. Cette cohérence est documentée dans les agendas scientifiques et le plan stratégique du centre.',
        '2.2' => 'Le CRICT mène des programmes et projets de recherche actifs dans ses domaines prioritaires. Les rapports de projets attestent de l\'exécution et du suivi des activités scientifiques.',
        '2.3' => 'Le centre favorise la collaboration pluridisciplinaire entre informatique, cybersécurité, sciences des données et ingénierie. Les programmes de recherche intègrent cette dimension interdisciplinaire.',
        '2.4' => 'Le CRICT produit des articles, communications et ouvrages dans ses champs d\'expertise. Une liste des publications est tenue à jour et disponible pour les partenaires.',
        '2.5' => 'Le centre vise la visibilité internationale de ses travaux via des publications indexées (Scopus, WoS, DOAJ selon disponibilité). Les références sont recensées dans la base documentaire du CRICT.',
        '2.6' => 'Le CRICT encadre des étudiants en master et doctorat dans le cadre de ses activités de recherche. Les registres académiques documentent cet encadrement scientifique.',
        '2.7' => 'Le centre développe des partenariats scientifiques nationaux et internationaux. Les conventions et accords de coopération sont archivés et consultables.',
        '2.8' => 'Le CRICT accompagne l\'innovation technologique (prototypes, solutions logicielles, études appliquées). Les rapports techniques et dossiers de valorisation en témoignent.',
        '2.9' => 'Les résultats de recherche sont diffusés via colloques, séminaires, vulgarisation et médias institutionnels. Les rapports d\'activités documentent ces actions de diffusion.',
        '3.1' => 'Les profils des chercheurs et personnels du CRICT correspondent aux missions de recherche et d\'innovation du centre. Les CV et listes du personnel sont disponibles en interne.',
        '3.2' => 'Les effectifs du CRICT sont dimensionnés pour assurer la conduite des projets en cours, avec un suivi régulier du ratio chercheurs/projets dans la base RH.',
        '3.3' => 'Le centre compte des chercheurs expérimentés et des profils qualifiés (magistère, HDR selon disponibilité). Les dossiers administratifs attestent de ces qualifications.',
        '3.4' => 'Un plan de formation continue et de renforcement des capacités est mis en œuvre pour le personnel scientifique et technique du CRICT.',
        '3.5' => 'Le CRICT dispose d\'éléments de gestion prévisionnelle des emplois et compétences (GPEC) adaptés à sa taille institutionnelle.',
        '3.6' => 'Le personnel technique (techniciens, ingénieurs) contribue au fonctionnement des laboratoires et plateformes du centre, conformément à l\'organigramme.',
        '3.7' => 'Le CRICT veille à la stabilité et à la motivation de son personnel par des dispositifs de reconnaissance et un suivi des conditions de travail.',
        '4.1' => 'Le CRICT dispose de laboratoires et espaces de travail dédiés à la recherche. Le nombre et l\'état de fonctionnalité sont évalués lors des visites et inventaires.',
        '4.2' => 'Les équipements scientifiques et informatiques du centre font l\'objet d\'un inventaire régulier distinguant matériels fonctionnels et non fonctionnels.',
        '4.3' => 'Un plan de maintenance des équipements est appliqué ou en cours de déploiement. Les rapports techniques documentent les interventions.',
        '4.4' => 'Le CRICT bénéficie d\'une connectivité Internet et d\'accès aux ressources numériques nécessaires à la recherche (bases de données, outils collaboratifs).',
        '4.5' => 'Des mesures de sécurité et d\'hygiène (HSE) encadrent les activités en laboratoire et sur les sites du centre. Les rapports HSE sont tenus à jour.',
        '4.6' => 'Les moyens logistiques (véhicules, groupes électrogènes, etc.) sont recensés et gérés selon les besoins opérationnels du centre.',
        '4.7' => 'Le CRICT met à disposition des ressources documentaires et plateformes scientifiques (bibliothèque numérique, abonnements) pour ses chercheurs.',
        '5.1' => 'Le CRICT dispose d\'une stratégie de mobilisation des ressources visant à diversifier ses financements (public, partenarial, projets).',
        '5.2' => 'Le niveau de financement des activités de recherche est suivi via le budget annuel et les rapports financiers du centre.',
        '5.3' => 'Le centre entretient des relations avec des partenaires financiers publics, privés et internationaux, formalisées par des conventions.',
        '5.4' => 'Les procédures financières du CRICT sont encadrées par un manuel de procédures et soumises à des contrôles internes et audits.',
        '5.5' => 'Le CRICT assure la transparence financière par la production et la diffusion des rapports et états financiers aux instances compétentes.',
        '5.6' => 'La viabilité économique du centre est analysée régulièrement pour assurer la pérennité des financements et des activités.',
        '6.1' => 'Le CRICT promeut une culture de l\'innovation à travers des activités de sensibilisation, formations et initiatives internes.',
        '6.2' => 'Le centre collabore avec le secteur productif et les entreprises via des partenariats et accords de coopération technologique.',
        '6.3' => 'Les résultats de recherche sont valorisés (prototypes, brevets, incubation de projets) selon les opportunités identifiées.',
        '6.4' => 'Le CRICT contribue aux politiques publiques par des études stratégiques et notes techniques à destination des décideurs.',
        '6.5' => 'L\'impact socio-économique des travaux du centre est documenté par des témoignages d\'usage et études d\'adoption des résultats.',
        '6.6' => 'Le CRICT participe à des réseaux scientifiques nationaux et internationaux, consortiums et instances de coopération.',
    ];
}

function identite_preuves_types(): array {
    return [
        '1.1' => 'Décrets, arrêtés, règlement intérieur (archives direction)',
        '1.2' => 'Organigramme officiel validé',
        '1.3' => 'Procès-verbaux et décisions des instances',
        '1.4' => 'Document du plan stratégique',
        '1.5' => 'Rapports et outils de suivi-évaluation',
        '1.6' => 'Rapports assurance qualité',
        '1.7' => 'Charte éthique, décisions du comité',
        '1.8' => 'Site web, publications, réseaux sociaux',
        '2.1' => 'Agendas scientifiques, plan stratégique',
        '2.2' => 'Rapports de projets en cours',
        '2.3' => 'Programmes de recherche pluridisciplinaires',
        '2.4' => 'Liste des publications',
        '2.5' => 'Références indexées Scopus/WoS/DOAJ',
        '2.6' => 'Registres académiques encadrement',
        '2.7' => 'Conventions de partenariat',
        '2.8' => 'Rapports techniques, dossiers innovation',
        '2.9' => 'Rapports d\'activités, supports colloques',
        '3.1' => 'CV et listes du personnel',
        '3.2' => 'Base RH, tableau de bord effectifs',
        '3.3' => 'Dossiers administratifs chercheurs seniors',
        '3.4' => 'Plan de formation continue',
        '3.5' => 'Documents GPEC',
        '3.6' => 'Organigramme technique',
        '3.7' => 'Enquêtes internes, indicateurs rotation',
        '4.5' => 'Rapports HSE, politique sécurité',
        '4.6' => 'Inventaire moyens logistiques',
        '4.7' => 'Abonnements, catalogue bibliothèque',
        '5.1' => 'Plans financiers, stratégie mobilisation',
        '5.2' => 'Budget annuel recherche',
        '5.3' => 'Conventions partenaires financiers',
        '5.4' => 'Manuel de procédures, rapports audit',
        '5.5' => 'États financiers, rapports annuels',
        '5.6' => 'Analyses de viabilité financière',
        '6.1' => 'Rapports activités innovation',
        '6.2' => 'Accords secteur productif',
        '6.3' => 'Rapports valorisation, brevets',
        '6.4' => 'Notes techniques, études stratégiques',
        '6.5' => 'Témoignages, études d\'impact',
        '6.6' => 'Consortiums, réseaux scientifiques',
    ];
}

function identite_appliquer_preremplissage(): void {
    $db = get_db();
    identite_assurer_schema_extension();
    identite_inserer_criteres_extension();
    identite_assurer_schema_referentiels();

    $identification = [
        'denomination'   => 'Centre de Recherche en Informatique et Cyber-Technologie (CRICT)',
        'domaines'       => 'Recherche scientifique et technologique en informatique, cybersécurité, systèmes d\'information et technologies numériques au service du développement national.',
        'axes_recherche' => "Cybersécurité et résilience numérique\nIntelligence artificielle et sciences des données\nSystèmes d'information et réseaux\nInnovation technologique et transfert de compétences",
        'adresse'        => 'Conakry, République de Guinée',
        'site_web'       => 'https://crict.edu.gn',
        'contact'        => 'Courriel : info@crict.edu.gn — Direction du CRICT',
    ];
    foreach ($identification as $cle => $valeur) {
        $db->prepare("UPDATE identite_identification SET valeur = ? WHERE cle = ? AND (valeur IS NULL OR TRIM(valeur) = '')")
           ->execute([$valeur, $cle]);
    }

    $reponses = identite_reponses_types();
    $preuves  = identite_preuves_types();
    foreach ($reponses as $code => $texte) {
        $db->prepare("
            UPDATE identite_contenu ct
            JOIN identite_criteres c ON c.id = ct.critere_id
            SET ct.reponse_officielle = ?
            WHERE c.code = ? AND (ct.reponse_officielle IS NULL OR TRIM(ct.reponse_officielle) = '')
        ")->execute([$texte, $code]);
    }
    foreach ($preuves as $code => $texte) {
        try {
            $db->prepare("
                UPDATE identite_contenu ct
                JOIN identite_criteres c ON c.id = ct.critere_id
                SET ct.preuves_disponibles = ?
                WHERE c.code = ? AND (ct.preuves_disponibles IS NULL OR TRIM(ct.preuves_disponibles) = '')
            ")->execute([$texte, $code]);
        } catch (Throwable $e) {
            // colonne absente
        }
    }

    $db->exec("
        UPDATE identite_contenu ct
        JOIN identite_criteres c ON c.id = ct.critere_id
        SET ct.score = 3
        WHERE c.type_saisie = 'SCORE' AND ct.score IS NULL
    ");

    $db->exec("
        UPDATE identite_contenu ct
        JOIN identite_criteres c ON c.id = ct.critere_id
        SET ct.valeur_numerique = CASE c.code
            WHEN '4.1' THEN 2
            WHEN '4.2' THEN 1
            WHEN '4.3' THEN 1
            WHEN '4.4' THEN 1
            ELSE ct.valeur_numerique
        END
        WHERE c.type_saisie = 'NOMBRE' AND ct.valeur_numerique IS NULL
    ");
}
