<?php
// ============================================================
// models/marche.php — Fonctions DB : Marchés Publics (DAF)
// ============================================================

define('MARCHE_UPLOAD_DIR', UPLOAD_DIR . 'marches/');
define('MARCHE_MAX_SIZE',   10 * 1024 * 1024); // 10 Mo — PDF uniquement

function marche_fmt(float $v): string {
    return number_format($v, 0, ',', ' ') . ' ' . (defined('CURRENCY') ? CURRENCY : 'GNF');
}

// ──────────────────────────────────────────────────────────
// HELPERS INTERNES
// ──────────────────────────────────────────────────────────
function _marche_ref(): string {
    $annee = date('Y');
    $s = get_db()->prepare("SELECT COUNT(*) FROM dossiers_depenses_daf WHERE YEAR(date_creation)=?");
    $s->execute([$annee]);
    return 'CRICT-'.$annee.'-'.str_pad((int)$s->fetchColumn()+1, 4, '0', STR_PAD_LEFT);
}

function _marche_upload_pdf(string $champ_key, int $dossier_id, array $fichier): ?string {
    if (!$fichier || $fichier['error'] !== UPLOAD_ERR_OK) return null;
    if ($fichier['size'] > MARCHE_MAX_SIZE) return null;

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
    if ($mime !== 'application/pdf') return null;

    $dir = MARCHE_UPLOAD_DIR . $dossier_id . '/';
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $filename = $champ_key . '_' . bin2hex(random_bytes(8)) . '.pdf';
    if (!move_uploaded_file($fichier['tmp_name'], $dir . $filename)) return null;

    return 'marches/' . $dossier_id . '/' . $filename;
}

function _marche_col_path(string $key): ?string {
    static $map = [
        'facture_1' => 'facture_1_path',
        'facture_2' => 'facture_2_path',
        'facture_3' => 'facture_3_path',
        'f1_rccm'   => 'f1_rccm_path',   'f1_nif' => 'f1_nif_path',
        'f1_quittance'=>'f1_quittance_path','f1_quitus'=>'f1_quitus_path',
        'f2_rccm'   => 'f2_rccm_path',   'f2_nif' => 'f2_nif_path',
        'f2_quittance'=>'f2_quittance_path','f2_quitus'=>'f2_quitus_path',
        'f3_rccm'   => 'f3_rccm_path',   'f3_nif' => 'f3_nif_path',
        'f3_quittance'=>'f3_quittance_path','f3_quitus'=>'f3_quitus_path',
        'exec_lettre_paiement'  => 'exec_lettre_paiement_path',
        'exec_bon_engagement'   => 'exec_bon_engagement_path',
        'exec_mandat_paiement'  => 'exec_mandat_paiement_path',
        'exec_liquidation'      => 'exec_liquidation_path',
    ];
    return $map[$key] ?? null;
}

// ──────────────────────────────────────────────────────────
// LECTURE
// ──────────────────────────────────────────────────────────
function marche_get(int $id): ?array {
    $s = get_db()->prepare("
        SELECT d.*, c.libelle AS caisse_libelle, c.code_caisse,
               cat.libelle AS categorie_libelle, cat.code_categorie
        FROM dossiers_depenses_daf d
        LEFT JOIN caisses_subvention c  ON c.id = d.caisse_id
        LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
        WHERE d.id = ?
    ");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function marche_lister(array $f = [], int $lim = 20, int $off = 0): array {
    $db     = get_db();
    $user   = user_courant();
    $is_chef = in_array($user['groupe_code']??'', ['SUPER_ADMIN','DG','DAF','PRMP']);
    $where  = ['1=1']; $p = [];

    if (!$is_chef) {
        $where[] = 'd.cree_par = ?'; $p[] = (int)$user['id'];
    }
    if (!empty($f['statut'])) { $where[] = 'd.statut=?'; $p[] = $f['statut']; }
    if (!empty($f['annee']))  { $where[] = 'd.periode_annee=?'; $p[] = (int)$f['annee']; }
    if (!empty($f['q'])) {
        $where[] = '(d.numero_dossier LIKE ? OR d.libelle LIKE ? OR d.fournisseur_1_nom LIKE ? OR d.fournisseur_2_nom LIKE ? OR d.fournisseur_3_nom LIKE ?)';
        $q = '%'.$f['q'].'%';
        $p = array_merge($p, [$q,$q,$q,$q,$q]);
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM dossiers_depenses_daf d WHERE $cond");
    $sc->execute($p); $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT d.*,
               CONCAT(u.prenoms,' ',u.nom) AS createur_nom,
               c.libelle AS caisse_libelle, c.code_caisse,
               cat.code_categorie
        FROM dossiers_depenses_daf d
        LEFT JOIN utilisateurs u      ON u.id = d.cree_par
        LEFT JOIN caisses_subvention c ON c.id = d.caisse_id
        LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
        WHERE $cond
        ORDER BY d.date_creation DESC, d.id DESC
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($p, [$lim, $off]));
    return ['data'=>$sd->fetchAll(),'total'=>$total];
}

function marche_compteurs(): array {
    $s = get_db()->query("SELECT statut, COUNT(*) n FROM dossiers_depenses_daf GROUP BY statut");
    $r = ['brouillon'=>0,'en_attente_execution'=>0,'execute'=>0,'TOTAL'=>0];
    foreach ($s->fetchAll() as $row) { $r[$row['statut']] = (int)$row['n']; $r['TOTAL'] += (int)$row['n']; }
    return $r;
}

function marche_annuaire_fournisseurs(): array {
    $s = get_db()->query("SELECT id, nom_entreprise FROM fournisseurs WHERE statut='valide' ORDER BY nom_entreprise");
    return $s->fetchAll();
}

function marche_periodes_eligibles(): array {
    $s = get_db()->query("SELECT id, code_caisse, libelle, annee, trimestre, montant_disponible
                          FROM caisses_subvention WHERE statut='active' AND montant_disponible>0
                          ORDER BY annee DESC, trimestre");
    return $s->fetchAll();
}

function marche_categories(): array {
    $s = get_db()->query("SELECT * FROM categories_depenses WHERE actif=1 ORDER BY ordre_affichage");
    return $s->fetchAll();
}

function marche_get_fav(int $id): int {
    $s = get_db()->prepare("SELECT fournisseur_favori FROM dossiers_depenses_daf WHERE id=?");
    $s->execute([$id]);
    return (int)($s->fetchColumn() ?: 0);
}

function marche_etape_a_reprendre(array $d): int {
    if (empty($d['libelle']))                       return 1;
    if (empty($d['facture_1_path']))                return 2;
    if (empty($d['fournisseur_favori']))            return 3;
    $fav = (int)$d['fournisseur_favori'];
    if (empty($d['motif_f'.$fav]))                  return 4;
    if (empty($d['regime_fiscal']))                 return 5;
    return 6;
}

function marche_pieces_completes(array $d): bool {
    return !empty($d['exec_lettre_paiement_path'])
        && !empty($d['exec_bon_engagement_path'])
        && !empty($d['exec_mandat_paiement_path'])
        && !empty($d['exec_liquidation_path']);
}

// ──────────────────────────────────────────────────────────
// ÉCRITURE — BROUILLON
// ──────────────────────────────────────────────────────────
function marche_creer_brouillon(int $user_id): int {
    $db  = get_db();
    $ref = _marche_ref();
    $db->prepare("INSERT INTO dossiers_depenses_daf (numero_dossier,statut,cree_par) VALUES (?,?,?)")
       ->execute([$ref, 'brouillon', $user_id]);
    return (int)$db->lastInsertId();
}

// ──────────────────────────────────────────────────────────
// ÉTAPES WIZARD
// ──────────────────────────────────────────────────────────
function marche_save_step1(int $id, int $uid, string $libelle, string $categorie_id): array {
    if (empty(trim($libelle))) return ['succes'=>false,'message'=>'Le libellé est obligatoire.'];
    try {
        $cat = (int)$categorie_id ?: null;
        get_db()->prepare("UPDATE dossiers_depenses_daf SET libelle=?, categorie_id=? WHERE id=?")
                ->execute([trim($libelle), $cat, $id]);
        log_activite($uid,'MARCHES','STEP1',"Dossier $id — étape 1 sauvegardée");
        return ['succes'=>true,'message'=>'Étape 1 enregistrée.'];
    } catch (Throwable $e) {
        error_log('[marche_save_step1] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la sauvegarde.'];
    }
}

function marche_save_step2(int $id, int $uid, array $ids, array $files): array {
    $db = get_db();
    $sets = []; $params = [];
    for ($i=1; $i<=3; $i++) {
        $fid = (int)($ids[$i] ?? 0);
        if (!$fid) return ['succes'=>false,'message'=>"Sélectionnez le fournisseur $i."];

        // Récupérer nom
        $s = $db->prepare("SELECT nom_entreprise FROM fournisseurs WHERE id=? LIMIT 1");
        $s->execute([$fid]);
        $nom = $s->fetchColumn() ?: ('Fournisseur '.$i);

        $sets[] = "fournisseur_{$i}_id=?, fournisseur_{$i}_nom=?";
        $params[] = $fid; $params[] = $nom;

        // Upload facture
        $f = $files['facture_'.$i] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            $path = _marche_upload_pdf('facture_'.$i, $id, $f);
            if ($path === null) return ['succes'=>false,'message'=>"Facture $i : format invalide (PDF, max 10 Mo)."];
            $sets[] = "facture_{$i}_path=?";
            $params[] = $path;
        }
    }
    $params[] = $id;
    $db->prepare("UPDATE dossiers_depenses_daf SET ".implode(',',$sets)." WHERE id=?")->execute($params);
    log_activite($uid,'MARCHES','STEP2',"Dossier $id — étape 2 sauvegardée");
    return ['succes'=>true,'message'=>'Étape 2 enregistrée.'];
}

function marche_save_step3(int $id, int $uid, int $fav): array {
    if ($fav < 1 || $fav > 3) return ['succes'=>false,'message'=>'Sélectionnez le fournisseur retenu.'];
    get_db()->prepare("UPDATE dossiers_depenses_daf SET fournisseur_favori=? WHERE id=?")->execute([$fav,$id]);
    log_activite($uid,'MARCHES','STEP3',"Dossier $id — fournisseur favori: F$fav");
    return ['succes'=>true,'message'=>'Fournisseur retenu enregistré.'];
}

function marche_save_step4(int $id, int $uid, int $fav, array $files, string $motif): array {
    if (empty(trim($motif))) return ['succes'=>false,'message'=>'Le motif de sélection est obligatoire.'];
    $db   = get_db();
    $sets = ["motif_f{$fav}=?"]; $params = [trim($motif)];

    foreach (['rccm','nif','quittance','quitus'] as $key) {
        $fkey = 'f'.$fav.'_'.$key;
        $f = $files[$fkey] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            $path = _marche_upload_pdf($fkey, $id, $f);
            if ($path === null) return ['succes'=>false,'message'=>strtoupper($key).' : format invalide (PDF, max 10 Mo).'];
            $col = 'f'.$fav.'_'.$key.'_path';
            $sets[] = "$col=?"; $params[] = $path;
        }
    }
    $params[] = $id;
    $db->prepare("UPDATE dossiers_depenses_daf SET ".implode(',',$sets)." WHERE id=?")->execute($params);
    log_activite($uid,'MARCHES','STEP4',"Dossier $id — pièces F$fav sauvegardées");
    return ['succes'=>true,'message'=>'Pièces et motif enregistrés.'];
}

function marche_save_step5(int $id, int $uid, float $ht, string $regime): array {
    if ($ht <= 0)     return ['succes'=>false,'message'=>'Le montant HT est invalide.'];
    if (!$regime)     return ['succes'=>false,'message'=>'Choisissez un régime fiscal.'];
    if (!in_array($regime,['tva','precompte','sans_tva'])) return ['succes'=>false,'message'=>'Régime fiscal invalide.'];

    $tax = match($regime) {
        'tva'       => round($ht * 0.18, 2),
        'precompte' => round($ht * 0.20, 2),
        default     => 0.0,
    };
    $ttc = round($ht + $tax, 2);

    $db = get_db();
    $db->prepare("UPDATE dossiers_depenses_daf SET montant_ht=?, montant_taxe=?, montant_ttc=?, regime_fiscal=?, statut='en_attente_execution' WHERE id=?")
       ->execute([$ht, $tax, $ttc, $regime, $id]);
    log_activite($uid,'MARCHES','STEP5',"Dossier $id — HT=$ht TTC=$ttc regime=$regime");
    return ['succes'=>true,'message'=>'Montants enregistrés.'];
}

function marche_save_exec_pieces(int $id, int $uid, array $files): array {
    $db = get_db(); $sets = []; $params = [];
    $keys = ['exec_lettre_paiement','exec_bon_engagement','exec_mandat_paiement','exec_liquidation'];
    foreach ($keys as $key) {
        $f = $files[$key] ?? null;
        if ($f && $f['error'] === UPLOAD_ERR_OK) {
            $path = _marche_upload_pdf($key, $id, $f);
            if ($path) { $col = $key.'_path'; $sets[] = "$col=?"; $params[] = $path; }
        }
    }
    if (!$sets) return ['succes'=>true,'message'=>'Aucun fichier fourni.'];

    // Vérifier si toutes les pièces seront complètes après mise à jour
    $params[] = $id;
    $db->prepare("UPDATE dossiers_depenses_daf SET ".implode(',',$sets)." WHERE id=?")->execute($params);

    $d = marche_get($id);
    if ($d && marche_pieces_completes($d)) {
        $db->prepare("UPDATE dossiers_depenses_daf SET exec_pieces_completes=1 WHERE id=?")->execute([$id]);
    }
    return ['succes'=>true,'message'=>'Pièces d\'exécution enregistrées.'];
}

// ──────────────────────────────────────────────────────────
// EXÉCUTION
// ──────────────────────────────────────────────────────────
function marche_executer(int $id, int $uid, int $annee, int $trimestre): array {
    if ($annee < 2020 || $trimestre < 1 || $trimestre > 4)
        return ['succes'=>false,'message'=>'Période d\'exécution invalide.'];

    $db  = get_db();
    $d   = marche_get($id);
    if (!$d) return ['succes'=>false,'message'=>'Dossier introuvable.'];
    if ($d['statut'] === 'execute') return ['succes'=>false,'message'=>'Dossier déjà exécuté.'];
    if ($d['statut'] !== 'en_attente_execution') return ['succes'=>false,'message'=>'Le dossier n\'est pas prêt (complétez les étapes 1 à 5).'];

    $ttc = (float)($d['montant_ttc'] ?? 0);
    if ($ttc <= 0) return ['succes'=>false,'message'=>'Montant TTC invalide.'];

    // Chercher la caisse la plus adaptée pour cette période
    $sc = $db->prepare("
        SELECT id FROM caisses_subvention
        WHERE statut='active' AND annee=? AND trimestre=? AND montant_disponible >= ?
        ORDER BY montant_disponible ASC
        LIMIT 1
    ");
    $sc->execute([$annee, $trimestre, $ttc]);
    $caisse = $sc->fetch();

    if (!$caisse) {
        // Essayer sans contrainte de trimestre
        $sc2 = $db->prepare("SELECT id FROM caisses_subvention WHERE statut='active' AND annee=? AND montant_disponible>=? ORDER BY trimestre ASC LIMIT 1");
        $sc2->execute([$annee, $ttc]);
        $caisse = $sc2->fetch();
    }
    if (!$caisse) return ['succes'=>false,'message'=>"Aucune caisse disponible avec suffisamment de fonds pour l'année $annee (TTC : ".marche_fmt($ttc).")."];

    $caisse_id = (int)$caisse['id'];

    try {
        $db->beginTransaction();

        // Marquer le dossier comme exécuté
        $db->prepare("
            UPDATE dossiers_depenses_daf SET
              statut='execute', caisse_id=?, execute_par=?, date_execution=NOW(),
              periode_annee=?, periode_trimestre=?
            WHERE id=?
        ")->execute([$caisse_id, $uid, $annee, $trimestre, $id]);

        // Défalquer la caisse
        $db->prepare("
            UPDATE caisses_subvention
            SET montant_utilise    = montant_utilise + ?,
                montant_disponible = montant_disponible - ?
            WHERE id=?
        ")->execute([$ttc, $ttc, $caisse_id]);

        // Marquer épuisée si nécessaire
        $db->prepare("UPDATE caisses_subvention SET statut='epuisee' WHERE id=? AND montant_disponible<=0")->execute([$caisse_id]);

        $db->commit();
        log_activite($uid,'MARCHES','EXECUTION',"Dossier $id exécuté — TTC=$ttc sur caisse $caisse_id");
        return ['succes'=>true,'message'=>'Dossier exécuté avec succès. Montant défalqué sur la caisse.'];
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[marche_executer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'exécution : '.$e->getMessage()];
    }
}

// ──────────────────────────────────────────────────────────
// TÉLÉCHARGEMENT SÉCURISÉ
// ──────────────────────────────────────────────────────────
function marche_serve_file(int $id, string $doc_key, int $user_id, bool $is_chef): void {
    $d   = marche_get($id);
    if (!$d) { http_response_code(404); exit('Dossier introuvable.'); }
    if (!$is_chef && (int)$d['cree_par'] !== $user_id) { http_response_code(403); exit('Accès refusé.'); }

    $col = _marche_col_path($doc_key);
    if (!$col) { http_response_code(400); exit('Clé de document inconnue.'); }

    $rel = trim($d[$col] ?? '');
    if (!$rel) { http_response_code(404); exit('Document absent.'); }

    $abs = UPLOAD_DIR . ltrim($rel, '/');
    if (!file_exists($abs)) { http_response_code(404); exit('Fichier absent du serveur.'); }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.rawurlencode(basename($abs)).'"');
    header('Content-Length: '.filesize($abs));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    while (ob_get_level()) ob_end_clean();
    readfile($abs);
    exit;
}
