<?php
// ============================================================
// models/projet_article.php — Dossiers d'article scientifique
// ============================================================

const ARTICLE_MODULE = 'PROJETS';

function article_assurer_schema(): void {
    static $ok = false;
    if ($ok) return;
    $ok = true;
    $db = get_db();
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS article_dossiers (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              code VARCHAR(40) NOT NULL,
              titre VARCHAR(255) NOT NULL,
              statut ENUM('BROUILLON','EN_COURS','FINALISE') NOT NULL DEFAULT 'BROUILLON',
              contenu MEDIUMTEXT NULL,
              pct_complet TINYINT UNSIGNED NOT NULL DEFAULT 0,
              cree_par INT UNSIGNED NULL,
              modifie_par INT UNSIGNED NULL,
              cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              modifie_le DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_article_code (code),
              KEY idx_article_statut (statut),
              KEY idx_article_auteur (cree_par)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        error_log('[CRICT article] schema: ' . $e->getMessage());
    }
}

/** Blocs JSON (19 sections regroupées). */
function article_etapes(): array {
    return [
        1 => [
            'cle'   => 'identite',
            'titre' => 'Identité',
            'aide'  => 'Titre, auteurs et affiliations.',
            'icon'  => 'fa-id-card',
        ],
        2 => [
            'cle'   => 'resumes',
            'titre' => 'Résumés',
            'aide'  => 'Résumé, mots-clés, abstract, keywords.',
            'icon'  => 'fa-align-left',
        ],
        3 => [
            'cle'   => 'corps',
            'titre' => 'Corps',
            'aide'  => 'Introduction à conclusion.',
            'icon'  => 'fa-book',
        ],
        4 => [
            'cle'   => 'mentions',
            'titre' => 'Mentions',
            'aide'  => 'Contributions, financement, conflits, données, remerciements.',
            'icon'  => 'fa-handshake',
        ],
        5 => [
            'cle'   => 'fin',
            'titre' => 'Références & annexes',
            'aide'  => 'Bibliographie et annexes.',
            'icon'  => 'fa-paperclip',
        ],
    ];
}

/** Parcours UI en 5 étapes (dont synthèse). */
function article_parcours_ui(): array {
    return [
        1 => [
            'titre'        => 'Identité',
            'sous'         => 'Titre et auteurs',
            'cles'         => ['identite'],
            'titre_projet' => true,
        ],
        2 => [
            'titre' => 'Résumés',
            'sous'  => 'FR / EN',
            'cles'  => ['resumes'],
        ],
        3 => [
            'titre' => 'Corps',
            'sous'  => 'Introduction → Conclusion',
            'cles'  => ['corps'],
        ],
        4 => [
            'titre' => 'Mentions & fin',
            'sous'  => 'Mentions, refs, annexes',
            'cles'  => ['mentions', 'fin'],
        ],
        5 => [
            'titre'    => 'Synthèse',
            'sous'     => 'Aperçu A4',
            'cles'     => [],
            'synthese' => true,
        ],
    ];
}

function article_contenu_vide(): array {
    return [
        'identite' => [
            'auteurs' => [], // nom, affiliations, email, orcid
        ],
        'resumes' => [
            'resume'    => '',
            'mots_cles' => '',
            'abstract'  => '',
            'keywords'  => '',
        ],
        'corps' => [
            'introduction'  => '',
            'litterature'   => '',
            'methodes'      => '',
            'resultats'     => '',
            'discussion'    => '',
            'conclusion'    => '',
        ],
        'mentions' => [
            'contributions' => '',
            'financement'   => '',
            'conflits'      => '',
            'donnees'       => '',
            'remerciements' => '',
        ],
        'fin' => [
            'references'      => '',
            'annexes_fichiers'=> [],
        ],
    ];
}

function article_parcours_rempli(array $contenu, int $n): bool {
    $p = article_parcours_ui()[$n] ?? null;
    if (!$p || !empty($p['synthese'])) return false;
    foreach ($p['cles'] as $cle) {
        foreach ($contenu[$cle] ?? [] as $v) {
            if (article_est_rempli($v)) return true;
        }
    }
    return false;
}

function article_sauver_parcours(int $id, int $ui_etape, array $post): array {
    $d = article_charger($id);
    if (!$d) return ['succes' => false, 'message' => 'Article introuvable.'];
    if (!article_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Vous ne pouvez pas modifier cet article.'];
    }
    if (($d['statut'] ?? '') === 'FINALISE' && !article_peut_gerer()) {
        return ['succes' => false, 'message' => 'Cet article est finalisé. Demandez une réouverture.'];
    }

    $parcours = article_parcours_ui();
    if (!isset($parcours[$ui_etape])) {
        return ['succes' => false, 'message' => 'Étape invalide.'];
    }
    $p = $parcours[$ui_etape];
    if (!empty($p['synthese'])) {
        return ['succes' => true, 'message' => 'OK', 'pct' => (int)$d['pct_complet']];
    }

    $contenu = $d['contenu_arr'];

    if (!empty($p['titre_projet']) && isset($post['titre'])) {
        $titre = trim((string)$post['titre']);
        if ($titre !== '') {
            get_db()->prepare('UPDATE article_dossiers SET titre=?, modifie_par=?, modifie_le=NOW() WHERE id=?')
                ->execute([$titre, (int)user_id(), $id]);
        }
    }

    foreach ($p['cles'] as $cle) {
        $def = article_contenu_vide()[$cle] ?? [];
        foreach ($def as $champ => $defval) {
            if ($champ === 'annexes_fichiers') continue;
            if (is_array($defval)) {
                $contenu[$cle][$champ] = article_extraire_tableau($post, $champ);
            } else {
                $contenu[$cle][$champ] = trim((string)($post[$champ] ?? ''));
            }
        }
    }

    if ($ui_etape === 4) {
        $contenu = article_gerer_annexes($id, $contenu, $post, $_FILES);
    }

    $pct = article_calculer_pct($contenu);
    $statut = $d['statut'];
    if ($statut === 'BROUILLON' && $pct > 0) $statut = 'EN_COURS';
    if (!empty($post['_finaliser'])) $statut = 'FINALISE';

    try {
        get_db()->prepare("
            UPDATE article_dossiers
            SET contenu = ?, pct_complet = ?, statut = ?, modifie_par = ?, modifie_le = NOW()
            WHERE id = ?
        ")->execute([
            json_encode($contenu, JSON_UNESCAPED_UNICODE),
            $pct,
            $statut,
            (int)user_id(),
            $id,
        ]);
        return ['succes' => true, 'message' => 'Étape enregistrée.', 'pct' => $pct, 'statut' => $statut];
    } catch (Throwable $e) {
        error_log('[CRICT article] sauver_parcours: ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
}

function article_decoder_contenu(?string $json): array {
    $base = article_contenu_vide();
    if (!$json) return $base;
    $data = json_decode($json, true);
    if (!is_array($data)) return $base;
    foreach ($base as $cle => $def) {
        if (!isset($data[$cle]) || !is_array($data[$cle])) continue;
        foreach ($def as $k => $v) {
            if (array_key_exists($k, $data[$cle])) {
                $base[$cle][$k] = $data[$cle][$k];
            }
        }
    }
    if (!isset($base['fin']['annexes_fichiers']) || !is_array($base['fin']['annexes_fichiers'])) {
        $base['fin']['annexes_fichiers'] = [];
    }
    return $base;
}

function article_annexe_dir(int $id): string {
    $dir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'projet_article' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function article_annexe_ext_ok(string $name): bool {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'webp', 'gif',
    ], true);
}

function article_gerer_annexes(int $id, array $contenu, array $post, array $files): array {
    $liste = $contenu['fin']['annexes_fichiers'] ?? [];
    if (!is_array($liste)) $liste = [];

    $suppr = $post['annexes_suppr'] ?? [];
    if (!is_array($suppr)) $suppr = [];
    $suppr = array_map('strval', $suppr);
    if ($suppr) {
        $dir = article_annexe_dir($id);
        $reste = [];
        foreach ($liste as $f) {
            $fn = (string)($f['fichier'] ?? '');
            if ($fn !== '' && in_array($fn, $suppr, true)) {
                $path = $dir . basename($fn);
                if (is_file($path)) @unlink($path);
                continue;
            }
            $reste[] = $f;
        }
        $liste = $reste;
    }

    $up = $files['annexes'] ?? null;
    if (is_array($up) && !empty($up['name']) && is_array($up['name'])) {
        $dir = article_annexe_dir($id);
        $n = count($up['name']);
        for ($i = 0; $i < $n; $i++) {
            if ((int)($up['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $orig = (string)$up['name'][$i];
            if ($orig === '' || !article_annexe_ext_ok($orig)) continue;
            $tmp = (string)($up['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;
            $taille = (int)($up['size'][$i] ?? 0);
            if ($taille <= 0 || $taille > 20 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $stock = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
            if (!@move_uploaded_file($tmp, $dir . $stock)) continue;
            $liste[] = [
                'nom'     => $orig,
                'fichier' => $stock,
                'taille'  => $taille,
                'mime'    => (string)($up['type'][$i] ?? ''),
            ];
        }
    }

    $contenu['fin']['annexes_fichiers'] = $liste;
    return $contenu;
}

function article_est_rempli($val): bool {
    if (is_array($val)) {
        foreach ($val as $row) {
            if (is_array($row)) {
                foreach ($row as $cell) {
                    if (trim((string)$cell) !== '') return true;
                }
            } elseif (trim((string)$row) !== '') {
                return true;
            }
        }
        return false;
    }
    return trim((string)$val) !== '';
}

function article_calculer_pct(array $contenu): int {
    $etapes = article_etapes();
    $ok = 0;
    $total = count($etapes);
    foreach ($etapes as $e) {
        $bloc = $contenu[$e['cle']] ?? [];
        $rempli = false;
        foreach ($bloc as $v) {
            if (article_est_rempli($v)) { $rempli = true; break; }
        }
        if ($rempli) $ok++;
    }
    return $total > 0 ? (int)round(($ok / $total) * 100) : 0;
}

function article_code_nouveau(): string {
    $annee = date('Y');
    $s = get_db()->prepare('SELECT COUNT(*) FROM article_dossiers WHERE YEAR(cree_le)=?');
    $s->execute([$annee]);
    return 'AS-' . $annee . '-' . str_pad((int)$s->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
}

function article_peut_gerer(): bool {
    $u = user_courant();
    return a_permission(ARTICLE_MODULE, 'CREER')
        || in_array($u['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG', 'DAF'], true);
}

function article_peut_modifier(array $d): bool {
    if (article_peut_gerer()) return true;
    return (int)($d['cree_par'] ?? 0) === (int)user_id()
        && a_permission(ARTICLE_MODULE, 'CREER');
}

function article_lister(array $f = [], int $limite = 20, int $offset = 0): array {
    article_assurer_schema();
    $db = get_db();
    $where = ['1=1'];
    $params = [];

    if (!article_peut_gerer()) {
        $where[] = 'd.cree_par = ?';
        $params[] = (int)user_id();
    }
    if (!empty($f['statut'])) {
        $where[] = 'd.statut = ?';
        $params[] = $f['statut'];
    }
    if (!empty($f['q'])) {
        $where[] = '(d.titre LIKE ? OR d.code LIKE ?)';
        $q = '%' . $f['q'] . '%';
        $params[] = $q;
        $params[] = $q;
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM article_dossiers d WHERE $cond");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom
        FROM article_dossiers d
        LEFT JOIN utilisateurs u ON u.id = d.cree_par
        WHERE $cond
        ORDER BY d.modifie_le DESC, d.cree_le DESC
        LIMIT $limite OFFSET $offset
    ");
    $sd->execute($params);
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function article_charger(int $id): ?array {
    article_assurer_schema();
    $s = get_db()->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom
        FROM article_dossiers d
        LEFT JOIN utilisateurs u ON u.id = d.cree_par
        WHERE d.id = ?
    ");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return null;
    $row['contenu_arr'] = article_decoder_contenu($row['contenu'] ?? null);
    return $row;
}

function article_creer(string $titre): array {
    article_assurer_schema();
    $titre = trim($titre);
    if ($titre === '') {
        return ['succes' => false, 'message' => 'Indiquez le titre de l\'article.'];
    }
    if (!a_permission(ARTICLE_MODULE, 'CREER') && !article_peut_gerer()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    $contenu = article_contenu_vide();
    $pct = article_calculer_pct($contenu);
    $code = article_code_nouveau();
    $uid = (int)user_id();
    try {
        get_db()->prepare("
            INSERT INTO article_dossiers (code, titre, statut, contenu, pct_complet, cree_par, modifie_par, modifie_le)
            VALUES (?, ?, 'BROUILLON', ?, ?, ?, ?, NOW())
        ")->execute([$code, $titre, json_encode($contenu, JSON_UNESCAPED_UNICODE), $pct, $uid, $uid]);
        $id = (int)get_db()->lastInsertId();
        log_activite($uid, ARTICLE_MODULE, 'CREATION', "Article scientifique $code créé");
        return ['succes' => true, 'id' => $id, 'message' => 'Article créé.'];
    } catch (Throwable $e) {
        error_log('[CRICT article] creer: ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Impossible de créer l\'article.'];
    }
}

function article_extraire_tableau(array $post, string $prefix): array {
    $raw = $post[$prefix] ?? null;
    if (!is_array($raw)) return [];
    $out = [];
    $keys = array_keys($raw);
    if ($keys && (is_int($keys[0]) || ctype_digit((string)$keys[0]))) {
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $clean = [];
            $any = false;
            foreach ($row as $k => $v) {
                $v = trim((string)$v);
                $clean[$k] = $v;
                if ($v !== '') $any = true;
            }
            if ($any) $out[] = $clean;
        }
        return $out;
    }
    $cols = $raw;
    $n = 0;
    foreach ($cols as $col => $vals) {
        if (is_array($vals)) $n = max($n, count($vals));
    }
    for ($i = 0; $i < $n; $i++) {
        $row = [];
        $any = false;
        foreach ($cols as $col => $vals) {
            $v = is_array($vals) ? trim((string)($vals[$i] ?? '')) : '';
            $row[$col] = $v;
            if ($v !== '') $any = true;
        }
        if ($any) $out[] = $row;
    }
    return $out;
}

function article_mettre_a_jour_titre(int $id, string $titre): array {
    $d = article_charger($id);
    if (!$d || !article_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    $titre = trim($titre);
    if ($titre === '') return ['succes' => false, 'message' => 'Titre requis.'];
    get_db()->prepare('UPDATE article_dossiers SET titre=?, modifie_par=?, modifie_le=NOW() WHERE id=?')
        ->execute([$titre, (int)user_id(), $id]);
    return ['succes' => true, 'message' => 'Titre mis à jour.'];
}

function article_changer_statut(int $id, string $statut): array {
    $d = article_charger($id);
    if (!$d || !article_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    if (!in_array($statut, ['BROUILLON', 'EN_COURS', 'FINALISE'], true)) {
        return ['succes' => false, 'message' => 'Statut invalide.'];
    }
    get_db()->prepare('UPDATE article_dossiers SET statut=?, modifie_par=?, modifie_le=NOW() WHERE id=?')
        ->execute([$statut, (int)user_id(), $id]);
    return ['succes' => true, 'message' => 'Statut mis à jour.'];
}

function article_supprimer(int $id): array {
    $d = article_charger($id);
    if (!$d) return ['succes' => false, 'message' => 'Introuvable.'];
    if (!article_peut_gerer() && (int)$d['cree_par'] !== (int)user_id()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    if (!a_permission(ARTICLE_MODULE, 'CREER') && !article_peut_gerer()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    get_db()->prepare('DELETE FROM article_dossiers WHERE id=?')->execute([$id]);
    log_activite((int)user_id(), ARTICLE_MODULE, 'SUPPRESSION', 'Article #' . $id . ' supprimé');
    return ['succes' => true, 'message' => 'Article supprimé.'];
}

function article_statut_cfg(): array {
    return [
        'BROUILLON' => ['Brouillon', '#f1f5f9', '#64748b'],
        'EN_COURS'  => ['En cours',  '#dbeafe', '#1e40af'],
        'FINALISE'  => ['Finalisé',  '#dcfce7', '#166534'],
    ];
}
