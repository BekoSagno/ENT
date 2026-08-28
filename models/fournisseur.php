<?php
// ============================================================
// models/fournisseur.php — Fonctions DB : Fournisseurs
// ============================================================

// ──────────────────────────────────────────────────────────
// CONSTANTES
// ──────────────────────────────────────────────────────────
define('FOUR_UPLOAD_DIR', UPLOAD_DIR . 'fournisseurs/');
define('FOUR_MAX_SIZE',   10 * 1024 * 1024); // 10 Mo
define('FOUR_MIME_OK', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg', 'image/png', 'image/webp',
]);

// ──────────────────────────────────────────────────────────
// LISTER / RECHERCHER
// ──────────────────────────────────────────────────────────
function fournisseur_lister(array $f = [], int $limite = 15, int $offset = 0): array {
    $db     = get_db();
    $where  = ['1=1'];
    $params = [];

    if (!empty($f['statut']))  { $where[] = 'f.statut = ?';           $params[] = $f['statut']; }
    if (!empty($f['type']))    { $where[] = 'f.type_fournisseur = ?';  $params[] = $f['type']; }
    if (!empty($f['categorie'])){ $where[] = 'f.categorie = ?';        $params[] = $f['categorie']; }
    if (!empty($f['q'])) {
        $where[] = '(f.nom_entreprise LIKE ? OR f.email LIKE ? OR f.nom_gerant LIKE ? OR f.numero_rccm LIKE ?)';
        $q = '%'.$f['q'].'%';
        $params = array_merge($params, [$q, $q, $q, $q]);
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM fournisseurs f WHERE $cond");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT f.*,
               CONCAT(u.prenoms,' ',u.nom) AS validateur_nom,
               COUNT(d.id)                 AS nb_documents
        FROM fournisseurs f
        LEFT JOIN utilisateurs u         ON u.id = f.valide_par
        LEFT JOIN documents_fournisseurs d ON d.fournisseur_id = f.id
        WHERE $cond
        GROUP BY f.id
        ORDER BY f.date_inscription DESC
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($params, [$limite, $offset]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function fournisseur_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT f.*, CONCAT(u.prenoms,' ',u.nom) AS validateur_nom
        FROM fournisseurs f
        LEFT JOIN utilisateurs u ON u.id = f.valide_par
        WHERE f.id = ?
    ");
    $s->execute([$id]);
    $f = $s->fetch();
    if (!$f) return null;

    // Documents
    $sd = $db->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS deposant
        FROM documents_fournisseurs d
        LEFT JOIN utilisateurs u ON u.id = d.depose_par
        WHERE d.fournisseur_id = ?
        ORDER BY d.type_document, d.date_upload
    ");
    $sd->execute([$id]);
    $f['documents'] = $sd->fetchAll();

    return $f;
}

function fournisseur_compteurs(): array {
    $s = get_db()->query("
        SELECT statut, COUNT(*) n FROM fournisseurs GROUP BY statut
    ");
    $r = ['en_attente'=>0,'valide'=>0,'rejete'=>0,'suspendu'=>0,'TOTAL'=>0];
    foreach ($s->fetchAll() as $row) {
        $r[$row['statut']] = (int)$row['n'];
        $r['TOTAL'] += (int)$row['n'];
    }
    return $r;
}

function fournisseur_categories(): array {
    $s = get_db()->query("SELECT DISTINCT categorie FROM fournisseurs ORDER BY categorie");
    return array_column($s->fetchAll(), 'categorie');
}

// ──────────────────────────────────────────────────────────
// CRÉER / MODIFIER
// ──────────────────────────────────────────────────────────
function fournisseur_creer(array $d): array {
    $db   = get_db();
    $user = user_courant();
    $uid  = $user ? (int)$user['id'] : null; // null = auto-inscription publique

    // Unicité email
    $se = $db->prepare('SELECT id FROM fournisseurs WHERE email = ? LIMIT 1');
    $se->execute([$d['email']]);
    if ($se->fetch()) return ['succes'=>false,'message'=>'Cet email est déjà utilisé.'];

    // Unicité nom_utilisateur
    $su = $db->prepare('SELECT id FROM fournisseurs WHERE nom_utilisateur = ? LIMIT 1');
    $su->execute([$d['nom_utilisateur']]);
    if ($su->fetch()) return ['succes'=>false,'message'=>'Ce nom d\'utilisateur est déjà pris.'];

    try {
        $mdp = password_hash($d['mot_de_passe'], PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("
            INSERT INTO fournisseurs
              (nom_entreprise,telephone,site_web,email,type_fournisseur,categorie,
               nom_gerant,telephone_gerant,numero_rccm,quittance,nif,
               nom_utilisateur,mot_de_passe,statut,cree_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $d['nom_entreprise'], $d['telephone'], $d['site_web']??null,
            $d['email'], $d['type_fournisseur'], $d['categorie'],
            $d['nom_gerant'], $d['telephone_gerant'],
            $d['numero_rccm'], $d['quittance']??null, $d['nif'],
            $d['nom_utilisateur'], $mdp,
            $d['statut'] ?? 'en_attente',
            $uid,
        ]);
        $fid = (int)$db->lastInsertId();
        log_activite($uid, 'FOURNISSEURS', 'CREATION',
            'Fournisseur '.$d['nom_entreprise'].($uid === null ? ' (auto-inscription)' : '').' créé');
        return ['succes'=>true,'id'=>$fid];
    } catch (Throwable $e) {
        error_log('[CRICT four_creer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la création.'];
    }
}

function fournisseur_modifier(int $id, array $d): array {
    $db   = get_db();
    $user = user_courant();

    // Unicité email (sauf soi-même)
    $se = $db->prepare('SELECT id FROM fournisseurs WHERE email = ? AND id != ? LIMIT 1');
    $se->execute([$d['email'], $id]);
    if ($se->fetch()) return ['succes'=>false,'message'=>'Cet email est déjà utilisé.'];

    try {
        $db->prepare("
            UPDATE fournisseurs SET
              nom_entreprise=?,telephone=?,site_web=?,email=?,
              type_fournisseur=?,categorie=?,
              nom_gerant=?,telephone_gerant=?,
              numero_rccm=?,quittance=?,nif=?,statut=?
            WHERE id=?
        ")->execute([
            $d['nom_entreprise'], $d['telephone'], $d['site_web']??null,
            $d['email'], $d['type_fournisseur'], $d['categorie'],
            $d['nom_gerant'], $d['telephone_gerant'],
            $d['numero_rccm'], $d['quittance']??null, $d['nif'],
            $d['statut'], $id,
        ]);
        // Changer le mot de passe seulement si renseigné
        if (!empty($d['mot_de_passe'])) {
            $mdp = password_hash($d['mot_de_passe'], PASSWORD_BCRYPT, ['cost'=>12]);
            $db->prepare('UPDATE fournisseurs SET mot_de_passe=? WHERE id=?')->execute([$mdp,$id]);
        }
        log_activite((int)$user['id'], 'FOURNISSEURS', 'MODIFICATION',
            'Fournisseur ID '.$id.' modifié');
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT four_modifier] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

function fournisseur_supprimer(int $id): array {
    $db = get_db();
    try {
        // Supprimer les fichiers physiques
        $docs = $db->prepare('SELECT chemin_fichier FROM documents_fournisseurs WHERE fournisseur_id=?');
        $docs->execute([$id]);
        foreach ($docs->fetchAll() as $doc) {
            $abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
            if (file_exists($abs)) @unlink($abs);
        }
        // Dossier fournisseur
        $dir = FOUR_UPLOAD_DIR . $id;
        if (is_dir($dir)) @rmdir($dir);

        $db->prepare('DELETE FROM fournisseurs WHERE id=?')->execute([$id]);
        log_activite(user_id(), 'FOURNISSEURS', 'SUPPRESSION', "Fournisseur ID $id supprimé");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT four_supprimer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la suppression.'];
    }
}

// ──────────────────────────────────────────────────────────
// VALIDER / REJETER / SUSPENDRE
// ──────────────────────────────────────────────────────────
function fournisseur_changer_statut(int $id, string $statut, string $motif = ''): array {
    $ok = ['en_attente','valide','rejete','suspendu'];
    if (!in_array($statut, $ok)) return ['succes'=>false,'message'=>'Statut invalide.'];

    $db   = get_db();
    $user = user_courant();
    try {
        $extra_sql = '';
        $params    = [$statut];

        if ($statut === 'valide') {
            $extra_sql = ', valide_par=?, date_validation=NOW()';
            $params[]  = (int)$user['id'];
        }
        if ($statut === 'rejete' && $motif) {
            $extra_sql .= ', motif_rejet=?';
            $params[]   = $motif;
        }
        $params[] = $id;
        $db->prepare("UPDATE fournisseurs SET statut=? $extra_sql WHERE id=?")->execute($params);
        log_activite((int)$user['id'], 'FOURNISSEURS', 'STATUT',
            "Fournisseur $id → $statut");
        return ['succes'=>true];
    } catch (Throwable $e) {
        return ['succes'=>false,'message'=>'Erreur.'];
    }
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS
// ──────────────────────────────────────────────────────────
function fournisseur_doc_ajouter(int $fid, array $fichier, string $type_doc): array {
    if ($fichier['error'] !== UPLOAD_ERR_OK)
        return ['succes'=>false,'message'=>'Erreur upload (#'.$fichier['error'].')'];
    if ($fichier['size'] > FOUR_MAX_SIZE)
        return ['succes'=>false,'message'=>'Fichier trop volumineux (max 10 Mo).'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fichier['tmp_name']);
    if (!in_array($mime, FOUR_MIME_OK, true))
        return ['succes'=>false,'message'=>'Type de fichier non autorisé (PDF, Word, JPEG, PNG, WebP).'];

    $ext  = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $dir  = FOUR_UPLOAD_DIR . $fid . '/';
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    $abs      = $dir . $stockage;
    if (!move_uploaded_file($fichier['tmp_name'], $abs))
        return ['succes'=>false,'message'=>'Impossible de déplacer le fichier.'];

    $chemin_rel = 'uploads/fournisseurs/' . $fid . '/' . $stockage;

    try {
        $db = get_db();
        $db->prepare("
            INSERT INTO documents_fournisseurs
              (fournisseur_id,type_document,nom_original,nom_stockage,chemin_fichier,type_mime,taille_fichier,extension,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $fid, $type_doc, $fichier['name'], $stockage,
            $chemin_rel, $mime, $fichier['size'], $ext, user_id(),
        ]);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId()];
    } catch (Throwable $e) {
        @unlink($abs);
        return ['succes'=>false,'message'=>'Erreur base de données.'];
    }
}

function fournisseur_doc_supprimer(int $doc_id): array {
    $db = get_db();
    $s  = $db->prepare('SELECT * FROM documents_fournisseurs WHERE id=?');
    $s->execute([$doc_id]);
    $doc = $s->fetch();
    if (!$doc) return ['succes'=>false,'message'=>'Document introuvable.'];

    $abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
    if (file_exists($abs)) @unlink($abs);
    $db->prepare('DELETE FROM documents_fournisseurs WHERE id=?')->execute([$doc_id]);
    return ['succes'=>true];
}
