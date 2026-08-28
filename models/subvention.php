<?php
// ============================================================
// models/subvention.php — Fonctions DB : Subventions
// Compatible migration_subventions_v2.sql
// Tables : caisses_subvention, lignes_budgetaires,
//          categories_depenses, dossiers_depenses_daf
// ============================================================
if (!function_exists('categories_lister')) {
    function categories_lister(): array {
        return sub_categories();
    }
}
// ──────────────────────────────────────────────────────────
// FORMAT MONÉTAIRE
// ──────────────────────────────────────────────────────────
function sub_fmt(float $v): string {
    return number_format($v, 0, ',', ' ') . ' GNF';
}

// ──────────────────────────────────────────────────────────
// CATÉGORIES
// ──────────────────────────────────────────────────────────
function sub_categories(): array {
    $s = get_db()->query("SELECT * FROM categories_depenses WHERE actif=1 ORDER BY ordre_affichage");
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────
// CAISSES — LECTURE
// ──────────────────────────────────────────────────────────
function caisse_lister(array $f = [], int $lim = 20, int $off = 0): array {
    $db = get_db();
    $where = ['1=1']; $p = [];
    if (!empty($f['statut'])) { $where[] = 'c.statut=?';   $p[] = $f['statut']; }
    if (!empty($f['annee']))  { $where[] = 'c.annee=?';    $p[] = (int)$f['annee']; }
    if (!empty($f['q'])) {
        $where[] = '(c.libelle LIKE ? OR c.code_caisse LIKE ?)';
        $q = '%'.$f['q'].'%'; $p[] = $q; $p[] = $q;
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM caisses_subvention c WHERE $cond");
    $sc->execute($p); $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT c.*,
               CONCAT(u.prenoms,' ',u.nom)   AS createur_nom,
               COUNT(DISTINCT d.id)           AS nb_dossiers,
               SUM(CASE WHEN d.statut='execute' THEN d.montant_ttc ELSE 0 END) AS total_execute,
               ROUND((c.montant_utilise / NULLIF(c.montant_initial,0))*100,1)  AS taux
        FROM caisses_subvention c
        LEFT JOIN utilisateurs u         ON u.id = c.cree_par
        LEFT JOIN dossiers_depenses_daf d ON d.caisse_id = c.id
        WHERE $cond
        GROUP BY c.id
        ORDER BY c.annee DESC, c.trimestre DESC
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($p, [$lim, $off]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function caisse_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT c.*, CONCAT(u.prenoms,' ',u.nom) AS createur_nom
        FROM caisses_subvention c
        LEFT JOIN utilisateurs u ON u.id = c.cree_par
        WHERE c.id=?
    ");
    $s->execute([$id]);
    $c = $s->fetch();
    if (!$c) return null;

    // Lignes budgétaires
    $sl = $db->prepare("
        SELECT lb.*, cd.code_categorie, cd.libelle AS cat_libelle
        FROM lignes_budgetaires lb
        JOIN categories_depenses cd ON cd.id = lb.categorie_id
        WHERE lb.caisse_id=?
        ORDER BY cd.ordre_affichage
    ");
    $sl->execute([$id]);
    $c['lignes'] = $sl->fetchAll();

    // Dossiers exécutés sur cette caisse
    $sd = $db->prepare("
        SELECT d.id, d.numero_dossier, d.libelle, d.montant_ttc,
               d.periode_annee, d.periode_trimestre, d.date_execution,
               CONCAT(u.prenoms,' ',u.nom) AS executeur_nom,
               cat.code_categorie
        FROM dossiers_depenses_daf d
        LEFT JOIN utilisateurs u        ON u.id = d.execute_par
        LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
        WHERE d.caisse_id=? AND d.statut='execute'
        ORDER BY d.date_execution DESC
        LIMIT 10
    ");
    $sd->execute([$id]);
    $c['dossiers_recents'] = $sd->fetchAll();

    return $c;
}

function caisse_compteurs(): array {
    $s = get_db()->query("SELECT statut, COUNT(*) n FROM caisses_subvention GROUP BY statut");
    $r = ['active'=>0,'epuisee'=>0,'cloturee'=>0,'suspendue'=>0,'TOTAL'=>0];
    foreach ($s->fetchAll() as $row) {
        $r[$row['statut']] = (int)$row['n'];
        $r['TOTAL'] += (int)$row['n'];
    }
    return $r;
}

function caisse_stats_globales(): array {
    $s = get_db()->query("
        SELECT
            COUNT(*)              AS nb_caisses,
            SUM(statut='active')  AS nb_actives,
            SUM(montant_initial)  AS budget_total,
            SUM(montant_utilise)  AS consomme_total,
            SUM(montant_disponible) AS disponible_total
        FROM caisses_subvention
    ");
    return $s->fetch() ?: [];
}

function caisse_stats_depenses(): array {
    $db = get_db();
    // Dossiers DAF
    $r = $db->query("
        SELECT
            COUNT(*)                                    AS nb_total,
            SUM(statut='execute')                       AS nb_executes,
            SUM(statut='brouillon')                     AS nb_brouillons,
            SUM(statut='en_attente_execution')          AS nb_prets,
            SUM(CASE WHEN statut='execute' THEN montant_ttc ELSE 0 END) AS montant_execute
        FROM dossiers_depenses_daf
    ")->fetch() ?: [];

    // Dépenses directes (table optionnelle)
    $nb_directes = 0; $montant_directes = 0.0;
    try {
        $d = $db->query("SELECT COUNT(*) AS nb, COALESCE(SUM(montant),0) AS total FROM depenses_executees WHERE statut='execute'")->fetch();
        $nb_directes      = (int)($d['nb'] ?? 0);
        $montant_directes = (float)($d['total'] ?? 0);
    } catch (Throwable $e) { /* table absente */ }

    $r['nb_directes']        = $nb_directes;
    $r['montant_directes']   = $montant_directes;
    $r['nb_operations']      = (int)($r['nb_executes'] ?? 0) + $nb_directes;
    $r['montant_operations'] = (float)($r['montant_execute'] ?? 0) + $montant_directes;
    return $r;
}

function caisse_repartition_par_categorie(): array {
    $db = get_db();
    // DAF + dépenses directes regroupés par catégorie
    try {
        $s = $db->query("
            SELECT cat.code_categorie, cat.libelle,
                   COUNT(*) AS nb,
                   SUM(x.montant) AS total
            FROM (
                SELECT categorie_id, montant_ttc AS montant
                FROM dossiers_depenses_daf WHERE statut='execute'
                UNION ALL
                SELECT categorie_id, montant
                FROM depenses_executees WHERE statut='execute'
            ) x
            JOIN categories_depenses cat ON cat.id = x.categorie_id
            GROUP BY cat.id
            ORDER BY total DESC
        ");
        return $s->fetchAll();
    } catch (Throwable $e) {
        // Repli DAF seul si la table des dépenses directes est absente
        return $db->query("
            SELECT cat.code_categorie, cat.libelle, COUNT(d.id) AS nb, SUM(d.montant_ttc) AS total
            FROM dossiers_depenses_daf d
            JOIN categories_depenses cat ON cat.id = d.categorie_id
            WHERE d.statut = 'execute'
            GROUP BY cat.id ORDER BY total DESC
        ")->fetchAll();
    }
}

function caisse_dossiers_recents_global(int $lim = 5): array {
    $db = get_db();
    // Dernières opérations toutes origines (DAF + dépenses directes)
    try {
        $s = $db->prepare("
            SELECT * FROM (
                SELECT d.numero_dossier, d.libelle, d.montant_ttc, d.date_execution,
                       cat.code_categorie, c.code_caisse, 'Marché' AS origine_lbl
                FROM dossiers_depenses_daf d
                JOIN caisses_subvention c          ON c.id = d.caisse_id
                LEFT JOIN categories_depenses cat  ON cat.id = d.categorie_id
                WHERE d.statut='execute'
                UNION ALL
                SELECT e.numero AS numero_dossier, e.libelle, e.montant AS montant_ttc, e.date_execution,
                       cat.code_categorie, c.code_caisse,
                       CASE WHEN e.type_depense='personnel' THEN 'Personnel' ELSE 'Biens & services' END AS origine_lbl
                FROM depenses_executees e
                JOIN caisses_subvention c          ON c.id = e.caisse_id
                LEFT JOIN categories_depenses cat  ON cat.id = e.categorie_id
                WHERE e.statut='execute'
            ) u
            ORDER BY u.date_execution DESC
            LIMIT ?
        ");
        $s->execute([$lim]);
        return $s->fetchAll();
    } catch (Throwable $e) {
        $s = $db->prepare("
            SELECT d.numero_dossier, d.libelle, d.montant_ttc, d.date_execution,
                   cat.code_categorie, c.code_caisse, 'Marché' AS origine_lbl
            FROM dossiers_depenses_daf d
            JOIN caisses_subvention c      ON c.id = d.caisse_id
            LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
            WHERE d.statut='execute'
            ORDER BY d.date_execution DESC LIMIT ?
        ");
        $s->execute([$lim]);
        return $s->fetchAll();
    }
}

// ──────────────────────────────────────────────────────────
// CAISSES — ÉCRITURE
// ──────────────────────────────────────────────────────────
function caisse_creer(array $d): array {
    $db = get_db();
    $sc = $db->prepare('SELECT id FROM caisses_subvention WHERE code_caisse=? LIMIT 1');
    $sc->execute([$d['code_caisse']]);
    if ($sc->fetch()) return ['succes'=>false,'message'=>'Ce code caisse existe déjà.'];
    try {
        $db->prepare("
            INSERT INTO caisses_subvention
              (code_caisse,libelle,description,source_financement,
               montant_initial,montant_disponible,
               trimestre,annee,date_debut,date_fin,statut,cree_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $d['code_caisse'], $d['libelle'], $d['description']??null,
            $d['source_financement']??null,
            (float)$d['montant_initial'], (float)$d['montant_initial'],
            (int)$d['trimestre'], (int)$d['annee'],
            $d['date_debut']??null, $d['date_fin']??null,
            $d['statut']??'active', user_id(),
        ]);
        $cid = (int)$db->lastInsertId();
        _caisse_save_lignes($cid, $d['lignes'] ?? []);
        log_activite(user_id(),'SUBVENTIONS','CAISSE_CREATION','Caisse '.$d['code_caisse'].' créée');
        return ['succes'=>true,'id'=>$cid];
    } catch (Throwable $e) {
        error_log('[caisse_creer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la création.'];
    }
}

function caisse_modifier(int $id, array $d): array {
    $db = get_db();
    try {
        $db->prepare("
            UPDATE caisses_subvention SET
              libelle=?,description=?,source_financement=?,
              montant_initial=?,trimestre=?,annee=?,
              date_debut=?,date_fin=?,statut=?
            WHERE id=?
        ")->execute([
            $d['libelle'], $d['description']??null, $d['source_financement']??null,
            (float)$d['montant_initial'],
            (int)$d['trimestre'], (int)$d['annee'],
            $d['date_debut']??null, $d['date_fin']??null,
            $d['statut'], $id,
        ]);
        $db->prepare("UPDATE caisses_subvention SET montant_disponible=montant_initial-montant_utilise WHERE id=?")->execute([$id]);
        _caisse_save_lignes($id, $d['lignes'] ?? []);
        log_activite(user_id(),'SUBVENTIONS','CAISSE_MODIF',"Caisse $id modifiée");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[caisse_modifier] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

function caisse_changer_statut(int $id, string $statut): array {
    if (!in_array($statut,['active','epuisee','cloturee','suspendue']))
        return ['succes'=>false,'message'=>'Statut invalide.'];
    try {
        get_db()->prepare("UPDATE caisses_subvention SET statut=? WHERE id=?")->execute([$statut,$id]);
        return ['succes'=>true];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

function _caisse_save_lignes(int $caisse_id, array $lignes): void {
    $db = get_db();
    foreach ($lignes as $cat_id => $montant) {
        $montant = (float)$montant;
        if ($montant > 0) {
            $db->prepare("
                INSERT INTO lignes_budgetaires (caisse_id,categorie_id,montant_alloue)
                VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE montant_alloue=VALUES(montant_alloue)
            ")->execute([$caisse_id,(int)$cat_id,$montant]);
        }
    }
    // Mettre à jour montant_consomme depuis les dossiers exécutés
    $db->prepare("
        UPDATE lignes_budgetaires lb
        SET montant_consomme = (
            SELECT COALESCE(SUM(d.montant_ttc),0)
            FROM dossiers_depenses_daf d
            WHERE d.caisse_id = lb.caisse_id
              AND d.categorie_id = lb.categorie_id
              AND d.statut = 'execute'
        )
        WHERE lb.caisse_id = ?
    ")->execute([$caisse_id]);
}

// ──────────────────────────────────────────────────────────
// MOUVEMENTS UNIFIÉS D'UNE CAISSE
// Fusionne les dossiers DAF (module Marchés) et les dépenses
// directes (sous-module « Exécuter dépenses ») en une seule liste.
// ──────────────────────────────────────────────────────────
function caisse_mouvements(int $caisse_id, int $limite = 0): array {
    $db   = get_db();
    $rows = [];

    // 1) Dossiers DAF exécutés
    $sd = $db->prepare("
        SELECT d.id, d.numero_dossier AS numero, d.libelle, d.montant_ttc AS montant,
               d.periode_trimestre, d.periode_annee, d.date_execution,
               COALESCE(d.fournisseur_1_nom,'') AS beneficiaire
        FROM dossiers_depenses_daf d
        WHERE d.caisse_id = ? AND d.statut = 'execute'
    ");
    $sd->execute([$caisse_id]);
    foreach ($sd->fetchAll() as $r) {
        $rows[] = [
            'origine'      => 'DAF',
            'origine_lbl'  => 'Marché',
            'numero'       => $r['numero'],
            'libelle'      => $r['libelle'] ?: '—',
            'beneficiaire' => $r['beneficiaire'],
            'montant'      => (float)$r['montant'],
            'trimestre'    => (int)$r['periode_trimestre'],
            'annee'        => (int)$r['periode_annee'],
            'date'         => $r['date_execution'],
            'lien'         => APP_URL . '/modules/marches/voir.php?id=' . (int)$r['id'],
        ];
    }

    // 2) Dépenses directes (table optionnelle : migration peut ne pas être jouée)
    try {
        $se = $db->prepare("
            SELECT id, numero, type_depense, libelle, montant,
                   fournisseur_nom, employe_nom,
                   periode_trimestre, periode_annee, date_execution
            FROM depenses_executees
            WHERE caisse_id = ? AND statut = 'execute'
        ");
        $se->execute([$caisse_id]);
        foreach ($se->fetchAll() as $r) {
            $perso = ($r['type_depense'] === 'personnel');
            $rows[] = [
                'origine'      => $perso ? 'PERSONNEL' : 'BIENS',
                'origine_lbl'  => $perso ? 'Personnel' : 'Biens & services',
                'numero'       => $r['numero'],
                'libelle'      => $r['libelle'] ?: '—',
                'beneficiaire' => $perso ? (string)$r['employe_nom'] : (string)$r['fournisseur_nom'],
                'montant'      => (float)$r['montant'],
                'trimestre'    => (int)$r['periode_trimestre'],
                'annee'        => (int)$r['periode_annee'],
                'date'         => $r['date_execution'],
                'lien'         => APP_URL . '/modules/subventions/depenses.php?id=' . (int)$r['id'],
            ];
        }
    } catch (Throwable $e) {
        // Table absente → on garde seulement les dossiers DAF.
    }

    usort($rows, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    if ($limite > 0) $rows = array_slice($rows, 0, $limite);
    return $rows;
}