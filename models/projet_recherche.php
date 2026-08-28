<?php
// ============================================================
// models/projet_recherche.php — Dossiers de projet de recherche
// ============================================================

const RECHERCHE_MODULE = 'PROJETS';

function recherche_assurer_schema(): void {
    static $ok = false;
    if ($ok) return;
    $ok = true;
    $db = get_db();
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS recherche_dossiers (
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
              UNIQUE KEY uq_recherche_code (code),
              KEY idx_recherche_statut (statut),
              KEY idx_recherche_auteur (cree_par)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        error_log('[CRICT recherche] schema: ' . $e->getMessage());
    }
}

/** Définition des 8 blocs de contenu (structure JSON — inchangée). */
function recherche_etapes(): array {
    return [
        1 => [
            'cle'   => 'presentation',
            'titre' => 'Présentation',
            'aide'  => 'Institution porteuse, ancrage et équipe de recherche.',
            'icon'  => 'fa-building-columns',
        ],
        2 => [
            'cle'   => 'contexte',
            'titre' => 'Contexte',
            'aide'  => 'Situation actuelle et justifications du projet.',
            'icon'  => 'fa-globe',
        ],
        3 => [
            'cle'   => 'litterature',
            'titre' => 'État de l\'art',
            'aide'  => 'Revue de littérature, lacune et cadres théorique / conceptuel.',
            'icon'  => 'fa-book-open',
        ],
        4 => [
            'cle'   => 'problematique',
            'titre' => 'Problématique',
            'aide'  => 'Constat, questions, hypothèses et délimitation.',
            'icon'  => 'fa-circle-question',
        ],
        5 => [
            'cle'   => 'objectifs',
            'titre' => 'Objectifs',
            'aide'  => 'Objectifs et matrice de cohérence scientifique.',
            'icon'  => 'fa-bullseye',
        ],
        6 => [
            'cle'   => 'methode',
            'titre' => 'Apport & méthode',
            'aide'  => 'Originalité, méthodologie, variables et éthique.',
            'icon'  => 'fa-flask',
        ],
        7 => [
            'cle'   => 'mise_en_oeuvre',
            'titre' => 'Mise en œuvre',
            'aide'  => 'Résultats attendus, chronogramme, gouvernance, risques.',
            'icon'  => 'fa-timeline',
        ],
        8 => [
            'cle'   => 'budget_fin',
            'titre' => 'Budget & fin',
            'aide'  => 'Budget, diffusion, durabilité, références et annexes.',
            'icon'  => 'fa-coins',
        ],
    ];
}

/**
 * Parcours UI en 6 étapes (regroupe les clés JSON existantes).
 * La base et le schéma JSON restent identiques.
 */
function recherche_parcours_ui(): array {
    return [
        1 => [
            'titre'        => 'Identité & Équipe',
            'sous'         => 'Présentation et ancrage',
            'emoji'        => '📌',
            'cles'         => ['presentation'],
            'titre_projet' => true,
        ],
        2 => [
            'titre' => 'Cadrage & Matrice',
            'sous'  => 'Contexte, problématique, objectifs',
            'emoji' => '🎯',
            'cles'  => ['contexte', 'litterature', 'problematique', 'objectifs'],
        ],
        3 => [
            'titre' => 'Méthodologie & Éthique',
            'sous'  => 'Approche, variables, éthique',
            'emoji' => '🧪',
            'cles'  => ['methode'],
        ],
        4 => [
            'titre'    => 'Planning & KPIs',
            'sous'     => 'Chronogramme et suivi',
            'emoji'    => '📅',
            'cles'     => ['mise_en_oeuvre'],
            'exclure'  => ['risques'],
        ],
        5 => [
            'titre' => 'Risques & Budget',
            'sous'  => 'Risques, budget, diffusion',
            'emoji' => '💰',
            'cles'  => ['budget_fin'],
            'extra' => ['mise_en_oeuvre' => ['risques']],
        ],
        6 => [
            'titre'    => 'Synthèse & Export A4',
            'sous'     => 'Aperçu officiel',
            'emoji'    => '📄',
            'cles'     => [],
            'synthese' => true,
        ],
    ];
}

function recherche_parcours_rempli(array $contenu, int $n): bool {
    $p = recherche_parcours_ui()[$n] ?? null;
    if (!$p || !empty($p['synthese'])) return false;

    if ($n === 5) {
        if (recherche_est_rempli($contenu['mise_en_oeuvre']['risques'] ?? [])) return true;
        foreach ($contenu['budget_fin'] ?? [] as $v) {
            if (recherche_est_rempli($v)) return true;
        }
        return false;
    }

    $exclure = $p['exclure'] ?? [];
    foreach ($p['cles'] as $cle) {
        foreach ($contenu[$cle] ?? [] as $champ => $v) {
            if (in_array($champ, $exclure, true)) continue;
            if (recherche_est_rempli($v)) return true;
        }
    }
    return false;
}

/** Sauvegarde une étape du parcours UI (peut toucher plusieurs clés JSON). */
function recherche_sauver_parcours(int $id, int $ui_etape, array $post): array {
    $d = recherche_charger($id);
    if (!$d) return ['succes' => false, 'message' => 'Projet introuvable.'];
    if (!recherche_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Vous ne pouvez pas modifier ce projet.'];
    }
    if (($d['statut'] ?? '') === 'FINALISE' && !recherche_peut_gerer()) {
        return ['succes' => false, 'message' => 'Ce projet est finalisé. Demandez une réouverture.'];
    }

    $parcours = recherche_parcours_ui();
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
            get_db()->prepare('UPDATE recherche_dossiers SET titre=?, modifie_par=?, modifie_le=NOW() WHERE id=?')
                ->execute([$titre, (int)user_id(), $id]);
        }
    }

    $exclure = $p['exclure'] ?? [];
    foreach ($p['cles'] as $cle) {
        $def = recherche_contenu_vide()[$cle] ?? [];
        foreach ($def as $champ => $defval) {
            if (in_array($champ, $exclure, true)) continue;
            if ($champ === 'annexes_fichiers') continue; // géré à part (upload)
            if (is_array($defval)) {
                $contenu[$cle][$champ] = recherche_extraire_tableau($post, $champ);
            } else {
                $contenu[$cle][$champ] = trim((string)($post[$champ] ?? ''));
            }
        }
    }

    foreach ($p['extra'] ?? [] as $cle => $champs) {
        foreach ($champs as $champ) {
            $defval = recherche_contenu_vide()[$cle][$champ] ?? [];
            if (is_array($defval)) {
                $contenu[$cle][$champ] = recherche_extraire_tableau($post, $champ);
            } else {
                $contenu[$cle][$champ] = trim((string)($post[$champ] ?? ''));
            }
        }
    }

    // Annexes : suppression + upload multi-fichiers (étape 5)
    if ($ui_etape === 5) {
        $contenu = recherche_gerer_annexes($id, $contenu, $post, $_FILES);
    }

    $pct = recherche_calculer_pct($contenu);
    $statut = $d['statut'];
    if ($statut === 'BROUILLON' && $pct > 0) $statut = 'EN_COURS';
    if (!empty($post['_finaliser'])) $statut = 'FINALISE';

    try {
        get_db()->prepare("
            UPDATE recherche_dossiers
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
        error_log('[CRICT recherche] sauver_parcours: ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
}

/** Contenu vide par défaut (structure des champs). */
function recherche_contenu_vide(): array {
    return [
        'presentation' => [
            'institution_porteuse' => '',
            'ancrage'              => '',
            'equipe'               => [],
        ],
        'contexte' => [
            'situation_actuelle'       => '',
            'justification_scientifique' => '',
            'justification_socio'      => '',
        ],
        'litterature' => [
            'revue_critique'   => '',
            'lacune'           => '',
            'cadre_theorique'  => '',
            'cadre_conceptuel' => '',
        ],
        'problematique' => [
            'constat'              => '',
            'probleme_scientifique'=> '',
            'question_principale'  => '',
            'questions_specifiques'=> '',
            'hypothese_generale'   => '',
            'hypotheses_specifiques'=> '',
            'delimitation'         => '',
        ],
        'objectifs' => [
            'objectif_general'    => '',
            'objectifs_specifiques'=> '',
            'matrice'             => [],
        ],
        'methode' => [
            'originalite'           => '',
            'contribution_theorique'=> '',
            'contribution_methodo'  => '',
            'contribution_pratique' => '',
            'type_approche'         => '',
            'site_cadre'            => '',
            'population'            => '',
            'echantillonnage'       => '',
            'variables'             => [],
            'techniques_collecte'   => '',
            'procedure_collecte'    => '',
            'methodes_analyse'      => '',
            'validite'              => '',
            'limites'               => '',
            'ethique'               => '',
        ],
        'mise_en_oeuvre' => [
            'resultats_scientifiques' => '',
            'livrables'               => '',
            'effets_attendus'         => '',
            'impact_attendu'          => '',
            'chronogramme'            => [],
            'organisation'            => '',
            'coordination'            => '',
            'partenariats'            => '',
            'suivi_eval'              => [],
            'risques'                 => [],
        ],
        'budget_fin' => [
            'budget'           => [],
            'publications'     => '',
            'communications'   => '',
            'vulgarisation'    => '',
            'valorisation_pi'  => '',
            'durabilite'       => '',
            'references'       => '',
            'annexes_fichiers' => [],
        ],
    ];
}

/** Préremplit institution / ancrage depuis DGRS Identification. */
function recherche_preremplir_dgrs(array $contenu): array {
    if (!function_exists('identite_identification_lister')) {
        $path = __DIR__ . '/identite.php';
        if (is_file($path)) require_once $path;
    }
    if (!function_exists('identite_identification_lister')) {
        return $contenu;
    }
    try {
        $map = [];
        foreach (identite_identification_lister() as $f) {
            $map[$f['cle']] = trim((string)($f['valeur'] ?? ''));
        }
        $inst = $map['denomination'] ?? '';
        $ancrage_parts = array_filter([
            $map['domaines'] ?? '',
            $map['axes_recherche'] ?? '',
            $map['adresse'] ?? '',
        ], static fn($v) => $v !== '');
        $ancrage = implode("\n\n", $ancrage_parts);

        if ($inst !== '' && trim((string)($contenu['presentation']['institution_porteuse'] ?? '')) === '') {
            $contenu['presentation']['institution_porteuse'] = $inst;
        }
        if ($ancrage !== '' && trim((string)($contenu['presentation']['ancrage'] ?? '')) === '') {
            $contenu['presentation']['ancrage'] = $ancrage;
        }
    } catch (Throwable $e) {
        // DGRS indisponible : on ignore
    }
    return $contenu;
}

function recherche_decoder_contenu(?string $json): array {
    $base = recherche_contenu_vide();
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
    // Migration douce : anciennes notes texte → ignorées ; fichiers déjà listés
    if (!isset($base['budget_fin']['annexes_fichiers']) || !is_array($base['budget_fin']['annexes_fichiers'])) {
        $base['budget_fin']['annexes_fichiers'] = [];
    }
    return $base;
}

function recherche_annexe_dir(int $id): string {
    $dir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'projet_recherche' . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function recherche_annexe_ext_ok(string $name): bool {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'webp', 'gif',
    ], true);
}

/** Supprime / ajoute des fichiers annexes (multi-upload). */
function recherche_gerer_annexes(int $id, array $contenu, array $post, array $files): array {
    $liste = $contenu['budget_fin']['annexes_fichiers'] ?? [];
    if (!is_array($liste)) $liste = [];

    // Suppressions demandées
    $suppr = $post['annexes_suppr'] ?? [];
    if (!is_array($suppr)) $suppr = [];
    $suppr = array_map('strval', $suppr);
    if ($suppr) {
        $dir = recherche_annexe_dir($id);
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

    // Nouveaux uploads
    $up = $files['annexes'] ?? null;
    if (is_array($up) && !empty($up['name']) && is_array($up['name'])) {
        $dir = recherche_annexe_dir($id);
        $n = count($up['name']);
        for ($i = 0; $i < $n; $i++) {
            if ((int)($up['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $orig = (string)$up['name'][$i];
            if ($orig === '' || !recherche_annexe_ext_ok($orig)) continue;
            $tmp = (string)($up['tmp_name'][$i] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) continue;
            $taille = (int)($up['size'][$i] ?? 0);
            if ($taille <= 0 || $taille > 20 * 1024 * 1024) continue; // 20 Mo
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

    $contenu['budget_fin']['annexes_fichiers'] = $liste;
    return $contenu;
}

function recherche_est_rempli($val): bool {
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

function recherche_calculer_pct(array $contenu): int {
    $etapes = recherche_etapes();
    $ok = 0;
    $total = count($etapes);
    foreach ($etapes as $e) {
        $bloc = $contenu[$e['cle']] ?? [];
        $rempli = false;
        foreach ($bloc as $v) {
            if (recherche_est_rempli($v)) { $rempli = true; break; }
        }
        if ($rempli) $ok++;
    }
    return $total > 0 ? (int)round(($ok / $total) * 100) : 0;
}

function recherche_code_nouveau(): string {
    $annee = date('Y');
    $s = get_db()->prepare('SELECT COUNT(*) FROM recherche_dossiers WHERE YEAR(cree_le)=?');
    $s->execute([$annee]);
    return 'PR-' . $annee . '-' . str_pad((int)$s->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
}

function recherche_peut_gerer(): bool {
    $u = user_courant();
    return a_permission(RECHERCHE_MODULE, 'CREER')
        || in_array($u['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG', 'DAF'], true);
}

function recherche_peut_modifier(array $d): bool {
    if (recherche_peut_gerer()) return true;
    return (int)($d['cree_par'] ?? 0) === (int)user_id()
        && a_permission(RECHERCHE_MODULE, 'CREER');
}

function recherche_lister(array $f = [], int $limite = 20, int $offset = 0): array {
    recherche_assurer_schema();
    $db = get_db();
    $where = ['1=1'];
    $params = [];

    if (!recherche_peut_gerer()) {
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

    $sc = $db->prepare("SELECT COUNT(*) FROM recherche_dossiers d WHERE $cond");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom
        FROM recherche_dossiers d
        LEFT JOIN utilisateurs u ON u.id = d.cree_par
        WHERE $cond
        ORDER BY d.modifie_le DESC, d.cree_le DESC
        LIMIT $limite OFFSET $offset
    ");
    $sd->execute($params);
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function recherche_charger(int $id): ?array {
    recherche_assurer_schema();
    $s = get_db()->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom
        FROM recherche_dossiers d
        LEFT JOIN utilisateurs u ON u.id = d.cree_par
        WHERE d.id = ?
    ");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return null;
    $row['contenu_arr'] = recherche_decoder_contenu($row['contenu'] ?? null);
    return $row;
}

function recherche_creer(string $titre): array {
    recherche_assurer_schema();
    $titre = trim($titre);
    if ($titre === '') {
        return ['succes' => false, 'message' => 'Indiquez le titre du projet de recherche.'];
    }
    if (!a_permission(RECHERCHE_MODULE, 'CREER') && !recherche_peut_gerer()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    $contenu = recherche_preremplir_dgrs(recherche_contenu_vide());
    $pct = recherche_calculer_pct($contenu);
    $code = recherche_code_nouveau();
    $uid = (int)user_id();
    try {
        get_db()->prepare("
            INSERT INTO recherche_dossiers (code, titre, statut, contenu, pct_complet, cree_par, modifie_par, modifie_le)
            VALUES (?, ?, 'BROUILLON', ?, ?, ?, ?, NOW())
        ")->execute([$code, $titre, json_encode($contenu, JSON_UNESCAPED_UNICODE), $pct, $uid, $uid]);
        $id = (int)get_db()->lastInsertId();
        log_activite($uid, RECHERCHE_MODULE, 'CREATION', "Projet recherche $code créé");
        return ['succes' => true, 'id' => $id, 'message' => 'Projet créé.'];
    } catch (Throwable $e) {
        error_log('[CRICT recherche] creer: ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Impossible de créer le projet.'];
    }
}

function recherche_sauver_etape(int $id, string $cle_etape, array $post): array {
    $d = recherche_charger($id);
    if (!$d) return ['succes' => false, 'message' => 'Projet introuvable.'];
    if (!recherche_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Vous ne pouvez pas modifier ce projet.'];
    }
    if (($d['statut'] ?? '') === 'FINALISE' && !recherche_peut_gerer()) {
        return ['succes' => false, 'message' => 'Ce projet est finalisé. Demandez une réouverture.'];
    }

    $etapes = recherche_etapes();
    $cles = array_column($etapes, 'cle');
    if (!in_array($cle_etape, $cles, true)) {
        return ['succes' => false, 'message' => 'Étape invalide.'];
    }

    $contenu = $d['contenu_arr'];
    $def = recherche_contenu_vide()[$cle_etape];

    foreach ($def as $champ => $defval) {
        if (is_array($defval)) {
            $contenu[$cle_etape][$champ] = recherche_extraire_tableau($post, $champ);
        } else {
            $contenu[$cle_etape][$champ] = trim((string)($post[$champ] ?? ''));
        }
    }

    $pct = recherche_calculer_pct($contenu);
    $statut = $d['statut'];
    if ($statut === 'BROUILLON' && $pct > 0) $statut = 'EN_COURS';
    if ($pct >= 100 && !empty($post['_finaliser'])) $statut = 'FINALISE';

    try {
        get_db()->prepare("
            UPDATE recherche_dossiers
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
        error_log('[CRICT recherche] sauver: ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
}

function recherche_extraire_tableau(array $post, string $prefix): array {
    $raw = $post[$prefix] ?? null;
    if (!is_array($raw)) return [];
    $out = [];
    // Format: champ[0][col] ou champ[col][]
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
    // Format colonnes: equipe[membre][] = [...]
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

function recherche_mettre_a_jour_titre(int $id, string $titre): array {
    $d = recherche_charger($id);
    if (!$d || !recherche_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    $titre = trim($titre);
    if ($titre === '') return ['succes' => false, 'message' => 'Titre requis.'];
    get_db()->prepare('UPDATE recherche_dossiers SET titre=?, modifie_par=?, modifie_le=NOW() WHERE id=?')
        ->execute([$titre, (int)user_id(), $id]);
    return ['succes' => true, 'message' => 'Titre mis à jour.'];
}

function recherche_changer_statut(int $id, string $statut): array {
    $d = recherche_charger($id);
    if (!$d || !recherche_peut_modifier($d)) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    if (!in_array($statut, ['BROUILLON', 'EN_COURS', 'FINALISE'], true)) {
        return ['succes' => false, 'message' => 'Statut invalide.'];
    }
    get_db()->prepare('UPDATE recherche_dossiers SET statut=?, modifie_par=?, modifie_le=NOW() WHERE id=?')
        ->execute([$statut, (int)user_id(), $id]);
    return ['succes' => true, 'message' => 'Statut mis à jour.'];
}

function recherche_supprimer(int $id): array {
    $d = recherche_charger($id);
    if (!$d) return ['succes' => false, 'message' => 'Introuvable.'];
    if (!recherche_peut_gerer() && (int)$d['cree_par'] !== (int)user_id()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    if (!a_permission(RECHERCHE_MODULE, 'CREER') && !recherche_peut_gerer()) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    get_db()->prepare('DELETE FROM recherche_dossiers WHERE id=?')->execute([$id]);
    log_activite((int)user_id(), RECHERCHE_MODULE, 'SUPPRESSION', 'Projet recherche #' . $id . ' supprimé');
    return ['succes' => true, 'message' => 'Projet supprimé.'];
}

function recherche_statut_cfg(): array {
    return [
        'BROUILLON' => ['Brouillon', '#f1f5f9', '#64748b'],
        'EN_COURS'  => ['En cours',  '#dbeafe', '#1e40af'],
        'FINALISE'  => ['Finalisé',  '#dcfce7', '#166534'],
    ];
}
