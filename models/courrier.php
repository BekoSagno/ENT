<?php
// ============================================================
// models/courrier.php — Fonctions DB : Gestion des Courriers
// ============================================================

// ──────────────────────────────────────────────────────────
// CONSTANTES
// ──────────────────────────────────────────────────────────
define('COURRIER_UPLOAD_DIR', UPLOAD_DIR . 'courriers/');
define('COURRIER_MAX_SIZE',   15 * 1024 * 1024); // 15 Mo
define('COURRIER_MIME_OK', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg', 'image/png', 'image/webp', 'image/tiff',
]);

// Tables de référence (libellés + couleurs) réutilisées dans les vues
function courrier_statuts(): array {
    return [
        'RECU'          => ['label'=>'Reçu',          'bg'=>'#dbeafe','color'=>'#1d4ed8','bord'=>'#3b82f6','icon'=>'fa-inbox'],
        'AFFECTE'       => ['label'=>'Affecté',       'bg'=>'#ede9fe','color'=>'#6d28d9','bord'=>'#8b5cf6','icon'=>'fa-share-from-square'],
        'EN_TRAITEMENT' => ['label'=>'En traitement', 'bg'=>'#fef3c7','color'=>'#92400e','bord'=>'#f59e0b','icon'=>'fa-spinner'],
        'TRAITE'        => ['label'=>'Traité',        'bg'=>'#dcfce7','color'=>'#166534','bord'=>'#22c55e','icon'=>'fa-circle-check'],
        'CLOTURE'       => ['label'=>'Clôturé',       'bg'=>'#e0f2fe','color'=>'#075985','bord'=>'#0ea5e9','icon'=>'fa-flag-checkered'],
        'ARCHIVE'       => ['label'=>'Archivé',       'bg'=>'#f3f4f6','color'=>'#6b7280','bord'=>'#9ca3af','icon'=>'fa-box-archive'],
    ];
}
function courrier_priorites(): array {
    return [
        'FAIBLE'  => ['label'=>'Faible',  'bg'=>'#f3f4f6','color'=>'#6b7280'],
        'NORMALE' => ['label'=>'Normale', 'bg'=>'#dbeafe','color'=>'#1d4ed8'],
        'HAUTE'   => ['label'=>'Haute',   'bg'=>'#ffedd5','color'=>'#c2410c'],
        'URGENTE' => ['label'=>'Urgente', 'bg'=>'#fee2e2','color'=>'#991b1b'],
    ];
}
function courrier_canaux(): array {
    return [
        'PHYSIQUE'    => ['label'=>'Physique',     'icon'=>'fa-file-lines'],
        'EMAIL'       => ['label'=>'Email',        'icon'=>'fa-envelope'],
        'FAX'         => ['label'=>'Fax',          'icon'=>'fa-fax'],
        'MAIN_PROPRE' => ['label'=>'Main propre',  'icon'=>'fa-hand-holding'],
        'AUTRE'       => ['label'=>'Autre',        'icon'=>'fa-circle-question'],
    ];
}
function courrier_types(): array {
    return [
        'LETTRE'      => 'Lettre',
        'NOTE'        => 'Note',
        'FACTURE'     => 'Facture',
        'CONVOCATION' => 'Convocation',
        'DEMANDE'     => 'Demande',
        'RAPPORT'     => 'Rapport',
        'INVITATION'  => 'Invitation',
        'CIRCULAIRE'  => 'Circulaire',
        'CONTRAT'     => 'Contrat',
        'AUTRE'       => 'Autre',
    ];
}
function courrier_confidentialites(): array {
    return [
        'NORMAL'       => ['label'=>'Normal',       'bg'=>'#f3f4f6','color'=>'#6b7280','icon'=>'fa-lock-open'],
        'CONFIDENTIEL' => ['label'=>'Confidentiel', 'bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-user-lock'],
        'SECRET'       => ['label'=>'Secret',       'bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'fa-lock'],
    ];
}

// ──────────────────────────────────────────────────────────
// RÉFÉRENCE AUTOMATIQUE  (CR-A-2026-0001 / CR-D-2026-0001)
// ──────────────────────────────────────────────────────────
function courrier_generer_reference(string $sens): string {
    $db     = get_db();
    $annee  = date('Y');
    $prefixe = $sens === 'SORTANT' ? 'CR-D-' : 'CR-A-';
    $like   = $prefixe . $annee . '-%';

    $s = $db->prepare("SELECT reference FROM courriers WHERE reference LIKE ? ORDER BY id DESC LIMIT 1");
    $s->execute([$like]);
    $dernier = $s->fetchColumn();

    $num = 1;
    if ($dernier && preg_match('/-(\d+)$/', $dernier, $m)) {
        $num = (int)$m[1] + 1;
    }
    return $prefixe . $annee . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

// ──────────────────────────────────────────────────────────
// LISTER / RECHERCHER
// ──────────────────────────────────────────────────────────
function courrier_lister(array $f = [], int $limite = 15, int $offset = 0): array {
    $db     = get_db();
    [$acces, $params] = courrier_where_acces();
    $where  = [$acces];

    if (!empty($f['mes'])) {
        $where[]  = 'EXISTS (SELECT 1 FROM courriers_affectations m WHERE m.courrier_id=c.id AND m.utilisateur_id=?)';
        $params[] = (int)user_id();
    }
    if (!empty($f['a_valider'])) {
        $where[] = "EXISTS (SELECT 1 FROM courriers_affectations v WHERE v.courrier_id=c.id AND v.role='PILOTE' AND v.statut='SOUMIS')";
    }
    if (!empty($f['sens']))            { $where[] = 'c.sens = ?';            $params[] = $f['sens']; }
    if (!empty($f['statut']))          { $where[] = 'c.statut = ?';          $params[] = $f['statut']; }
    if (!empty($f['canal']))           { $where[] = 'c.canal = ?';           $params[] = $f['canal']; }
    if (!empty($f['priorite']))        { $where[] = 'c.priorite = ?';        $params[] = $f['priorite']; }
    if (!empty($f['confidentialite'])) { $where[] = 'c.confidentialite = ?'; $params[] = $f['confidentialite']; }
    if (!empty($f['service_id']))      { $where[] = 'c.service_id = ?';      $params[] = (int)$f['service_id']; }
    if (!empty($f['affecte_a']))       { $where[] = 'c.affecte_a = ?';       $params[] = (int)$f['affecte_a']; }
    if (!empty($f['type']))            { $where[] = 'c.type_courrier = ?';   $params[] = $f['type']; }
    if (!empty($f['date_debut']))      { $where[] = 'DATE(c.date_reception) >= ?'; $params[] = $f['date_debut']; }
    if (!empty($f['date_fin']))        { $where[] = 'DATE(c.date_reception) <= ?'; $params[] = $f['date_fin']; }
    if (!empty($f['q'])) {
        $where[] = '(c.reference LIKE ? OR c.objet LIKE ? OR c.correspondant_nom LIKE ? OR c.correspondant_organisation LIKE ? OR c.correspondant_email LIKE ?)';
        $q = '%'.$f['q'].'%';
        $params = array_merge($params, [$q, $q, $q, $q, $q]);
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM courriers c WHERE $cond");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT c.*,
               CONCAT(u.prenoms,' ',u.nom)  AS affecte_nom,
               CONCAT(e.prenoms,' ',e.nom)  AS enregistreur_nom,
               s.nom                        AS service_nom,
               COUNT(d.id)                  AS nb_documents
        FROM courriers c
        LEFT JOIN utilisateurs u             ON u.id = c.affecte_a
        LEFT JOIN utilisateurs e             ON e.id = c.enregistre_par
        LEFT JOIN unites_organisationnelles s ON s.id = c.service_id
        LEFT JOIN courriers_documents d      ON d.courrier_id = c.id
        WHERE $cond
        GROUP BY c.id
        ORDER BY c.date_reception DESC, c.id DESC
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($params, [$limite, $offset]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function courrier_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT c.*,
               CONCAT(u.prenoms,' ',u.nom) AS affecte_nom,
               u.email                     AS affecte_email,
               CONCAT(e.prenoms,' ',e.nom) AS enregistreur_nom,
               CONCAT(t.prenoms,' ',t.nom) AS traiteur_nom,
               s.nom                       AS service_nom
        FROM courriers c
        LEFT JOIN utilisateurs u             ON u.id = c.affecte_a
        LEFT JOIN utilisateurs e             ON e.id = c.enregistre_par
        LEFT JOIN utilisateurs t             ON t.id = c.traite_par
        LEFT JOIN unites_organisationnelles s ON s.id = c.service_id
        WHERE c.id = ?
    ");
    $s->execute([$id]);
    $c = $s->fetch();
    if (!$c) return null;

    // Documents
    $sd = $db->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS deposant
        FROM courriers_documents d
        LEFT JOIN utilisateurs u ON u.id = d.depose_par
        WHERE d.courrier_id = ?
        ORDER BY d.type_document, d.date_upload
    ");
    $sd->execute([$id]);
    $c['documents'] = $sd->fetchAll();

    // Suivi
    $ss = $db->prepare("
        SELECT s.*, CONCAT(u.prenoms,' ',u.nom) AS auteur
        FROM courriers_suivi s
        LEFT JOIN utilisateurs u ON u.id = s.par_utilisateur
        WHERE s.courrier_id = ?
        ORDER BY s.cree_le DESC, s.id DESC
    ");
    $ss->execute([$id]);
    $c['suivi'] = $ss->fetchAll();

    return $c;
}

// Compteurs pour les cartes statistiques (par statut + sens)
function courrier_compteurs(): array {
    $db = get_db();
    [$acces, $p] = courrier_where_acces();
    $r = [
        'TOTAL'=>0,'ENTRANT'=>0,'SORTANT'=>0,
        'RECU'=>0,'AFFECTE'=>0,'EN_TRAITEMENT'=>0,'TRAITE'=>0,'CLOTURE'=>0,'ARCHIVE'=>0,
        'URGENTS'=>0,'EN_RETARD'=>0,
    ];
    $q = function (string $sql) use ($db, $p) { $s = $db->prepare($sql); $s->execute($p); return $s; };
    foreach ($q("SELECT statut, COUNT(*) n FROM courriers c WHERE $acces GROUP BY statut")->fetchAll() as $row) {
        $r[$row['statut']] = (int)$row['n'];
        $r['TOTAL'] += (int)$row['n'];
    }
    foreach ($q("SELECT sens, COUNT(*) n FROM courriers c WHERE $acces GROUP BY sens")->fetchAll() as $row) {
        $r[$row['sens']] = (int)$row['n'];
    }
    $r['URGENTS'] = (int)$q("
        SELECT COUNT(*) FROM courriers c
        WHERE $acces AND priorite='URGENTE' AND statut NOT IN ('TRAITE','CLOTURE','ARCHIVE')
    ")->fetchColumn();
    $r['EN_RETARD'] = (int)$q("
        SELECT COUNT(*) FROM courriers c
        WHERE $acces AND date_echeance IS NOT NULL AND date_echeance < CURDATE()
          AND statut NOT IN ('TRAITE','CLOTURE','ARCHIVE')
    ")->fetchColumn();
    return $r;
}

// Liste des services (unités organisationnelles actives) pour les selects
function courrier_services(): array {
    return get_db()->query("
        SELECT id, nom FROM unites_organisationnelles WHERE actif=1 ORDER BY nom
    ")->fetchAll();
}

// Liste des utilisateurs actifs (pour affectation)
function courrier_utilisateurs(): array {
    return get_db()->query("
        SELECT u.id, CONCAT(u.prenoms,' ',u.nom) AS nom_complet, g.libelle AS groupe
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.statut='ACTIF'
        ORDER BY u.prenoms, u.nom
    ")->fetchAll();
}

// ──────────────────────────────────────────────────────────
// JOURNAL DE SUIVI
// ──────────────────────────────────────────────────────────
function courrier_suivi_ajouter(int $cid, string $action, string $commentaire = '',
                                 ?string $ancien = null, ?string $nouveau = null): void {
    try {
        get_db()->prepare("
            INSERT INTO courriers_suivi
              (courrier_id, action, commentaire, ancien_statut, nouveau_statut, par_utilisateur)
            VALUES (?,?,?,?,?,?)
        ")->execute([$cid, $action, $commentaire ?: null, $ancien, $nouveau, user_id()]);
    } catch (Throwable $e) {
        error_log('[CRICT courrier_suivi] '.$e->getMessage());
    }
}

// ──────────────────────────────────────────────────────────
// CRÉER / MODIFIER
// ──────────────────────────────────────────────────────────
function courrier_creer(array $d): array {
    $db   = get_db();
    $user = user_courant();

    $sens = in_array($d['sens'] ?? '', ['ENTRANT','SORTANT'], true) ? $d['sens'] : 'ENTRANT';
    $reference = !empty($d['reference']) ? $d['reference'] : courrier_generer_reference($sens);

    try {
        $statut = !empty($d['affecte_a']) ? 'AFFECTE' : 'RECU';

        $db->prepare("
            INSERT INTO courriers
              (reference,sens,canal,type_courrier,objet,contenu,
               correspondant_nom,correspondant_organisation,correspondant_email,
               correspondant_telephone,correspondant_adresse,
               date_courrier,date_reception,date_echeance,
               priorite,confidentialite,service_id,affecte_a,instruction,
               statut,enregistre_par)
            VALUES (?,?,?,?,?,?, ?,?,?, ?,?, ?,?,?, ?,?,?,?,?, ?,?)
        ")->execute([
            $reference, $sens, $d['canal'], $d['type_courrier'], $d['objet'], $d['contenu'] ?? null,
            $d['correspondant_nom'] ?? null, $d['correspondant_organisation'] ?? null, $d['correspondant_email'] ?? null,
            $d['correspondant_telephone'] ?? null, $d['correspondant_adresse'] ?? null,
            $d['date_courrier'] ?: null, $d['date_reception'] ?: date('Y-m-d H:i:s'), $d['date_echeance'] ?: null,
            $d['priorite'] ?? 'NORMALE', $d['confidentialite'] ?? 'NORMAL',
            !empty($d['service_id']) ? (int)$d['service_id'] : null,
            !empty($d['affecte_a'])  ? (int)$d['affecte_a']  : null,
            $d['instruction'] ?? null,
            $statut, (int)$user['id'],
        ]);
        $cid = (int)$db->lastInsertId();

        courrier_suivi_ajouter($cid, 'ENREGISTREMENT',
            'Courrier ' . ($sens === 'SORTANT' ? 'sortant' : 'entrant') . ' enregistré (' . $reference . ')',
            null, $statut);
        if (!empty($d['affecte_a'])) {
            courrier_suivi_ajouter($cid, 'AFFECTATION', 'Affectation à l\'enregistrement', 'RECU', 'AFFECTE');
        }

        log_activite((int)$user['id'], 'COURRIERS', 'CREATION',
            'Courrier '.$reference.' enregistré');
        return ['succes'=>true,'id'=>$cid,'reference'=>$reference];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_creer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'enregistrement.'];
    }
}

function courrier_modifier(int $id, array $d): array {
    $db   = get_db();
    $user = user_courant();
    try {
        $db->prepare("
            UPDATE courriers SET
              sens=?,canal=?,type_courrier=?,objet=?,contenu=?,
              correspondant_nom=?,correspondant_organisation=?,correspondant_email=?,
              correspondant_telephone=?,correspondant_adresse=?,
              date_courrier=?,date_reception=?,date_echeance=?,
              priorite=?,confidentialite=?,service_id=?,affecte_a=?,instruction=?
            WHERE id=?
        ")->execute([
            $d['sens'], $d['canal'], $d['type_courrier'], $d['objet'], $d['contenu'] ?? null,
            $d['correspondant_nom'] ?? null, $d['correspondant_organisation'] ?? null, $d['correspondant_email'] ?? null,
            $d['correspondant_telephone'] ?? null, $d['correspondant_adresse'] ?? null,
            $d['date_courrier'] ?: null, $d['date_reception'] ?: null, $d['date_echeance'] ?: null,
            $d['priorite'] ?? 'NORMALE', $d['confidentialite'] ?? 'NORMAL',
            !empty($d['service_id']) ? (int)$d['service_id'] : null,
            !empty($d['affecte_a'])  ? (int)$d['affecte_a']  : null,
            $d['instruction'] ?? null,
            $id,
        ]);
        courrier_suivi_ajouter($id, 'MODIFICATION', 'Informations du courrier mises à jour');
        log_activite((int)$user['id'], 'COURRIERS', 'MODIFICATION', 'Courrier ID '.$id.' modifié');
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_modifier] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

function courrier_supprimer(int $id): array {
    $db = get_db();
    try {
        // Supprimer les fichiers physiques
        $docs = $db->prepare('SELECT chemin_fichier FROM courriers_documents WHERE courrier_id=?');
        $docs->execute([$id]);
        foreach ($docs->fetchAll() as $doc) {
            $abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
            if (file_exists($abs)) @unlink($abs);
        }
        $dir = COURRIER_UPLOAD_DIR . $id;
        if (is_dir($dir)) @rmdir($dir);

        $db->prepare('DELETE FROM courriers_documents WHERE courrier_id=?')->execute([$id]);
        $db->prepare('DELETE FROM courriers_suivi WHERE courrier_id=?')->execute([$id]);
        foreach (['courriers_affectations', 'courriers_echanges', 'courriers_notifications'] as $t) {
            try { $db->prepare("DELETE FROM $t WHERE courrier_id=?")->execute([$id]); } catch (Throwable $e) {}
        }
        $db->prepare('DELETE FROM courriers WHERE id=?')->execute([$id]);

        log_activite(user_id(), 'COURRIERS', 'SUPPRESSION', "Courrier ID $id supprimé");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_supprimer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la suppression.'];
    }
}

// ──────────────────────────────────────────────────────────
// AFFECTER / ORIENTER
// ──────────────────────────────────────────────────────────
function courrier_affecter(int $id, ?int $service_id, ?int $affecte_a, string $instruction = ''): array {
    $db = get_db();
    try {
        $cur = $db->prepare('SELECT statut FROM courriers WHERE id=?');
        $cur->execute([$id]);
        $ancien = $cur->fetchColumn();
        if ($ancien === false) return ['succes'=>false,'message'=>'Courrier introuvable.'];

        $nouveau = in_array($ancien, ['RECU']) ? 'AFFECTE' : $ancien;

        $db->prepare("UPDATE courriers SET service_id=?, affecte_a=?, instruction=?, statut=? WHERE id=?")
           ->execute([
               $service_id ?: null,
               $affecte_a ?: null,
               $instruction ?: null,
               $nouveau, $id,
           ]);

        courrier_suivi_ajouter($id, 'AFFECTATION',
            $instruction ?: 'Courrier orienté vers le service / l\'agent concerné',
            $ancien, $nouveau);
        log_activite(user_id(), 'COURRIERS', 'AFFECTATION', "Courrier $id affecté");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_affecter] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'affectation.'];
    }
}

// ──────────────────────────────────────────────────────────
// CHANGER LE STATUT (traiter / clôturer / archiver…)
// ──────────────────────────────────────────────────────────
function courrier_changer_statut(int $id, string $statut, string $commentaire = ''): array {
    $valides = array_keys(courrier_statuts());
    if (!in_array($statut, $valides, true)) return ['succes'=>false,'message'=>'Statut invalide.'];

    $db = get_db();
    try {
        $cur = $db->prepare('SELECT statut FROM courriers WHERE id=?');
        $cur->execute([$id]);
        $ancien = $cur->fetchColumn();
        if ($ancien === false) return ['succes'=>false,'message'=>'Courrier introuvable.'];

        $extra  = '';
        $params = [$statut];
        if (in_array($statut, ['TRAITE','CLOTURE'], true)) {
            $extra = ', traite_par=?, date_traitement=NOW()';
            $params[] = user_id();
        }
        $params[] = $id;
        $db->prepare("UPDATE courriers SET statut=? $extra WHERE id=?")->execute($params);

        $labels = courrier_statuts();
        courrier_suivi_ajouter($id, 'STATUT',
            $commentaire ?: ('Statut changé : '.($labels[$statut]['label'] ?? $statut)),
            $ancien, $statut);
        log_activite(user_id(), 'COURRIERS', 'STATUT', "Courrier $id → $statut");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_statut] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur.'];
    }
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS (scans / pièces jointes)
// ──────────────────────────────────────────────────────────
function courrier_upload_erreur_texte(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop volumineux pour le serveur (max ' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_PARTIAL    => 'envoi interrompu, veuillez réessayer.',
        UPLOAD_ERR_NO_FILE    => 'aucun fichier reçu.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'le serveur n\'a pas pu enregistrer le fichier.',
        default               => 'erreur technique lors de l\'envoi (#' . $code . ').',
    };
}

function courrier_doc_ajouter(int $cid, array $fichier, string $type_doc = 'SCAN'): array {
    if ($fichier['error'] !== UPLOAD_ERR_OK)
        return ['succes'=>false,'message'=>ucfirst(courrier_upload_erreur_texte((int)$fichier['error']))];
    if ($fichier['size'] > COURRIER_MAX_SIZE)
        return ['succes'=>false,'message'=>'Fichier trop volumineux (max 15 Mo).'];

    $ext   = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fichier['tmp_name']);
    // Les fichiers Office sont souvent détectés comme archive zip / binaire générique
    $office = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc'  => 'application/msword',
        'xls'  => 'application/vnd.ms-excel',
    ];
    if (isset($office[$ext]) && in_array($mime, ['application/zip','application/x-zip-compressed','application/octet-stream','application/vnd.ms-office','application/CDFV2'], true)) {
        $mime = $office[$ext];
    }
    if (!in_array($mime, COURRIER_MIME_OK, true))
        return ['succes'=>false,'message'=>'Type de fichier non autorisé (PDF, Word, Excel, JPEG, PNG, WebP, TIFF).'];

    $type_doc = in_array($type_doc, ['SCAN','PIECE_JOINTE','REPONSE','AUTRE'], true) ? $type_doc : 'AUTRE';

    $dir  = COURRIER_UPLOAD_DIR . $cid . '/';
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    $abs      = $dir . $stockage;
    if (!move_uploaded_file($fichier['tmp_name'], $abs))
        return ['succes'=>false,'message'=>'Impossible de déplacer le fichier.'];

    $chemin_rel = 'uploads/courriers/' . $cid . '/' . $stockage;

    try {
        $db = get_db();
        $db->prepare("
            INSERT INTO courriers_documents
              (courrier_id,type_document,nom_original,nom_stockage,chemin_fichier,type_mime,taille_fichier,extension,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $cid, $type_doc, $fichier['name'], $stockage,
            $chemin_rel, $mime, $fichier['size'], $ext, user_id(),
        ]);
        courrier_suivi_ajouter($cid, 'NUMERISATION', 'Document ajouté : '.$fichier['name']);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId(),'chemin'=>$chemin_rel,'nom'=>$fichier['name'],'mime'=>$mime];
    } catch (Throwable $e) {
        @unlink($abs);
        error_log('[CRICT courrier_doc] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur base de données.'];
    }
}

function courrier_doc_supprimer(int $doc_id): array {
    $db = get_db();
    $s  = $db->prepare('SELECT * FROM courriers_documents WHERE id=?');
    $s->execute([$doc_id]);
    $doc = $s->fetch();
    if (!$doc) return ['succes'=>false,'message'=>'Document introuvable.'];

    $abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
    if (file_exists($abs)) @unlink($abs);
    $db->prepare('DELETE FROM courriers_documents WHERE id=?')->execute([$doc_id]);
    courrier_suivi_ajouter((int)$doc['courrier_id'], 'SUPPRESSION_DOC', 'Document supprimé : '.$doc['nom_original']);
    return ['succes'=>true];
}

// Marquer qu'un courrier sortant a été transmis par email
function courrier_marquer_envoye(int $id, ?string $message_id = null): void {
    try {
        get_db()->prepare("UPDATE courriers SET email_envoye=1, email_message_id=? WHERE id=?")
                ->execute([$message_id, $id]);
    } catch (Throwable $e) {
        error_log('[CRICT courrier_marquer_envoye] '.$e->getMessage());
    }
}

// ──────────────────────────────────────────────────────────
// Enregistrer une pièce jointe à partir d'un CONTENU BRUT
// (utilisé par la relève IMAP — pas de $_FILES disponible).
//   $contenu = octets bruts déjà décodés du fichier
// ──────────────────────────────────────────────────────────
function courrier_doc_enregistrer(int $cid, string $nom_original, string $contenu,
                                  ?string $mime = null, string $type_doc = 'PIECE_JOINTE'): array {
    if ($contenu === '') return ['succes'=>false,'message'=>'Contenu vide.'];
    if (strlen($contenu) > COURRIER_MAX_SIZE)
        return ['succes'=>false,'message'=>'Pièce jointe trop volumineuse.'];

    $type_doc = in_array($type_doc, ['SCAN','PIECE_JOINTE','REPONSE','AUTRE'], true) ? $type_doc : 'PIECE_JOINTE';

    // Nom / extension sûrs
    $nom_original = $nom_original !== '' ? $nom_original : 'piece-jointe';
    $ext = strtolower(pathinfo($nom_original, PATHINFO_EXTENSION));
    if ($ext === '' || strlen($ext) > 8) $ext = 'bin';

    // Déduire le MIME si absent
    if (!$mime) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($contenu) ?: 'application/octet-stream';
    }

    $dir = COURRIER_UPLOAD_DIR . $cid . '/';
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    $abs      = $dir . $stockage;
    if (file_put_contents($abs, $contenu) === false)
        return ['succes'=>false,'message'=>'Impossible d\'écrire la pièce jointe.'];

    $chemin_rel = 'uploads/courriers/' . $cid . '/' . $stockage;

    try {
        get_db()->prepare("
            INSERT INTO courriers_documents
              (courrier_id,type_document,nom_original,nom_stockage,chemin_fichier,type_mime,taille_fichier,extension,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $cid, $type_doc, $nom_original, $stockage,
            $chemin_rel, $mime, strlen($contenu), $ext,
            function_exists('user_id') ? user_id() : null,
        ]);
        return ['succes'=>true,'chemin'=>$chemin_rel,'nom'=>$nom_original];
    } catch (Throwable $e) {
        @unlink($abs);
        error_log('[CRICT courrier_doc_enregistrer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur base de données.'];
    }
}

// ══════════════════════════════════════════════════════════
// TRAITEMENT : affectation par le DG, pilote + copies,
// soumission / validation, fil d'échanges, notifications
// ══════════════════════════════════════════════════════════

// DG ou super administrateur : seuls habilités à affecter et valider
function courrier_est_dg(): bool {
    return in_array(user_groupe() ?? '', ['SUPER_ADMIN', 'DG'], true);
}

// Crée au besoin les tables du traitement (sans effet si elles existent)
function courrier_assurer_schema(): void {
    static $fait = false;
    if ($fait) return;
    $fait = true;
    $db = get_db();
    $tables = [
        'courriers_affectations' => "
            id             int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            courrier_id    int UNSIGNED NOT NULL,
            utilisateur_id int UNSIGNED NOT NULL,
            role           enum('PILOTE','COPIE') NOT NULL DEFAULT 'PILOTE',
            statut         enum('A_TRAITER','EN_COURS','SOUMIS','VALIDE') NOT NULL DEFAULT 'A_TRAITER',
            date_limite    date DEFAULT NULL,
            affecte_par    int UNSIGNED DEFAULT NULL,
            vue_le         datetime DEFAULT NULL,
            soumis_le      datetime DEFAULT NULL,
            cree_le        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ca_cour_user (courrier_id, utilisateur_id),
            KEY idx_ca_user (utilisateur_id, role)",
        'courriers_echanges' => "
            id          int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            courrier_id int UNSIGNED NOT NULL,
            auteur_id   int UNSIGNED DEFAULT NULL,
            type        enum('MESSAGE','AFFECTATION','SOUMISSION','VALIDATION','REJET','CLOTURE') NOT NULL DEFAULT 'MESSAGE',
            message     text DEFAULT NULL,
            cree_le     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ce_cour (courrier_id)",
        'courriers_notifications' => "
            id             int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            utilisateur_id int UNSIGNED NOT NULL,
            courrier_id    int UNSIGNED NOT NULL,
            titre          varchar(150) NOT NULL,
            message        varchar(500) DEFAULT NULL,
            lu             tinyint(1) NOT NULL DEFAULT 0,
            cree_le        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_cn_user (utilisateur_id, lu),
            KEY idx_cn_cour (courrier_id)",
    ];
    $fk = [
        'courriers_affectations'  => 'CONSTRAINT fk_ca_courrier FOREIGN KEY (courrier_id) REFERENCES courriers(id) ON DELETE CASCADE',
        'courriers_echanges'      => 'CONSTRAINT fk_ce_courrier FOREIGN KEY (courrier_id) REFERENCES courriers(id) ON DELETE CASCADE',
        'courriers_notifications' => 'CONSTRAINT fk_cn_courrier FOREIGN KEY (courrier_id) REFERENCES courriers(id) ON DELETE CASCADE',
    ];
    $suffixe = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    foreach ($tables as $nom => $cols) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS $nom ($cols, {$fk[$nom]}$suffixe");
        } catch (Throwable $e) {
            try { $db->exec("CREATE TABLE IF NOT EXISTS $nom ($cols$suffixe"); }
            catch (Throwable $e2) { error_log("[CRICT courriers] CREATE $nom : " . $e2->getMessage()); }
        }
    }
}

// ── Participants ─────────────────────────────────────────

function courrier_affectations(int $cid): array {
    courrier_assurer_schema();
    $s = get_db()->prepare("
        SELECT a.*, CONCAT(u.prenoms,' ',u.nom) AS nom_complet, u.prenoms, u.nom, u.email,
               g.libelle AS groupe
        FROM courriers_affectations a
        JOIN utilisateurs u              ON u.id = a.utilisateur_id
        LEFT JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE a.courrier_id=?
        ORDER BY a.role='COPIE', u.prenoms, u.nom
    ");
    $s->execute([$cid]);
    return $s->fetchAll();
}

function courrier_participation(int $cid, int $uid): ?array {
    if ($uid <= 0) return null;
    try {
        $s = get_db()->prepare('SELECT * FROM courriers_affectations WHERE courrier_id=? AND utilisateur_id=? LIMIT 1');
        $s->execute([$cid, $uid]);
        return $s->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

// L'utilisateur est-il pilote ou en copie d'au moins un courrier ? (menu / registre)
function courrier_nb_participations(int $uid): int {
    if ($uid <= 0) return 0;
    try {
        $s = get_db()->prepare('SELECT COUNT(*) FROM courriers_affectations WHERE utilisateur_id=?');
        $s->execute([$uid]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// ── Droits d'accès ───────────────────────────────────────
// Registre : permission VOIR. Agents affectés : leurs courriers seulement.
// « Secret » : DG, super admin, enregistreur et agents affectés uniquement.

function courrier_peut_voir(array $cour): bool {
    $uid = (int)user_id();
    if (courrier_est_dg()) return true;
    $participant = courrier_participation((int)$cour['id'], $uid) !== null;
    if (($cour['confidentialite'] ?? '') === 'SECRET') {
        return $participant || (int)($cour['enregistre_par'] ?? 0) === $uid;
    }
    return $participant || a_permission('COURRIERS', 'VOIR');
}

// Condition SQL d'accès pour la table aliasée « c »
function courrier_where_acces(): array {
    courrier_assurer_schema();
    if (courrier_est_dg()) return ['1=1', []];
    $uid  = (int)user_id();
    $part = 'EXISTS (SELECT 1 FROM courriers_affectations ca WHERE ca.courrier_id=c.id AND ca.utilisateur_id=?)';
    if (!a_permission('COURRIERS', 'VOIR')) return [$part, [$uid]];
    return ["(c.confidentialite<>'SECRET' OR c.enregistre_par=? OR $part)", [$uid, $uid]];
}

// ── Organigramme ─────────────────────────────────────────

// Unités actives à plat, dans l'ordre de l'arbre, avec profondeur et responsable (utilisateur)
function courrier_services_arbre(): array {
    $rows = get_db()->query("
        SELECT uo.id, uo.nom, uo.code, uo.type, uo.parent_id,
               rp.utilisateur_id AS resp_user_id,
               CONCAT_WS(' ', ru.prenoms, ru.nom) AS resp_nom
        FROM unites_organisationnelles uo
        LEFT JOIN personnel rp    ON rp.id = uo.responsable_id
        LEFT JOIN utilisateurs ru ON ru.id = rp.utilisateur_id AND ru.statut='ACTIF'
        WHERE uo.actif=1
        ORDER BY uo.nom
    ")->fetchAll();
    $par_parent = [];
    $ids = array_column($rows, 'id');
    foreach ($rows as $r) {
        $p = ($r['parent_id'] && in_array($r['parent_id'], $ids)) ? (int)$r['parent_id'] : 0;
        $par_parent[$p][] = $r;
    }
    $plat = [];
    $parcourir = function (int $parent, int $niveau) use (&$parcourir, &$plat, $par_parent) {
        foreach ($par_parent[$parent] ?? [] as $r) {
            if (empty($r['resp_nom'])) $r['resp_user_id'] = null;
            $r['niveau'] = $niveau;
            $plat[] = $r;
            $parcourir((int)$r['id'], $niveau + 1);
        }
    };
    $parcourir(0, 0);
    return $plat;
}

function courrier_utilisateurs_dg(): array {
    return get_db()->query("
        SELECT u.id, u.prenoms, u.nom, u.email
        FROM utilisateurs u JOIN groupes_utilisateurs g ON g.id=u.groupe_id
        WHERE g.code='DG' AND u.statut='ACTIF'
    ")->fetchAll();
}

// ── Fil d'échanges ───────────────────────────────────────

function courrier_echanges(int $cid): array {
    courrier_assurer_schema();
    $s = get_db()->prepare("
        SELECT ec.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom, g.code AS auteur_groupe
        FROM courriers_echanges ec
        LEFT JOIN utilisateurs u         ON u.id = ec.auteur_id
        LEFT JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE ec.courrier_id=?
        ORDER BY ec.cree_le, ec.id
    ");
    $s->execute([$cid]);
    return $s->fetchAll();
}

function courrier_echange_ajouter(int $cid, string $message, string $type = 'MESSAGE'): array {
    $message = trim($message);
    if ($message === '' && $type === 'MESSAGE') return ['succes'=>false,'message'=>'Le message est vide.'];
    courrier_assurer_schema();
    try {
        get_db()->prepare("INSERT INTO courriers_echanges (courrier_id, auteur_id, type, message) VALUES (?,?,?,?)")
                ->execute([$cid, user_id(), $type, $message !== '' ? $message : null]);
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT courrier_echange] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'envoi du message.'];
    }
}

// ── Affectation (DG) ─────────────────────────────────────
// Remplace l'équipe : 1 pilote (traite + soumet) et des agents en copie (information + messages).
// Retour : ['succes', 'nouveaux' => [user_id => 'PILOTE'|'COPIE']]
function courrier_affecter_equipe(int $cid, ?int $service_id, int $pilote, array $copies, ?string $date_limite, string $instruction): array {
    if (!courrier_est_dg()) return ['succes'=>false,'message'=>'Seuls le DG et le super administrateur peuvent affecter un courrier.'];
    if ($pilote <= 0)        return ['succes'=>false,'message'=>'Choisissez l\'agent pilote.'];
    if ($date_limite !== null && $date_limite !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_limite))
        return ['succes'=>false,'message'=>'Le délai de traitement est invalide.'];
    $copies = array_values(array_unique(array_filter(array_map('intval', $copies), fn($id) => $id > 0 && $id !== $pilote)));

    $db = get_db();
    courrier_assurer_schema();
    try {
        $s = $db->prepare('SELECT statut FROM courriers WHERE id=?');
        $s->execute([$cid]);
        $ancien = $s->fetchColumn();
        if ($ancien === false) return ['succes'=>false,'message'=>'Courrier introuvable.'];
        if (!in_array($ancien, ['RECU','AFFECTE','EN_TRAITEMENT'], true))
            return ['succes'=>false,'message'=>'Ce courrier est déjà traité : il ne peut plus être affecté.'];

        $avant = [];
        foreach (courrier_affectations($cid) as $a) $avant[(int)$a['utilisateur_id']] = $a;

        $db->beginTransaction();
        $garder = array_merge([$pilote], $copies);
        $place  = implode(',', array_fill(0, count($garder), '?'));
        $db->prepare("DELETE FROM courriers_affectations WHERE courrier_id=? AND utilisateur_id NOT IN ($place)")
           ->execute(array_merge([$cid], $garder));

        $nouveaux = [];
        $ins = $db->prepare("INSERT INTO courriers_affectations (courrier_id, utilisateur_id, role, statut, date_limite, affecte_par)
                             VALUES (?,?,?,?,?,?)
                             ON DUPLICATE KEY UPDATE role=VALUES(role), date_limite=VALUES(date_limite), affecte_par=VALUES(affecte_par),
                                                     statut=IF(VALUES(role)='COPIE', 'A_TRAITER', statut)");
        $dl = $date_limite ?: null;
        $ins->execute([$cid, $pilote, 'PILOTE', 'A_TRAITER', $dl, user_id()]);
        if (($avant[$pilote]['role'] ?? '') !== 'PILOTE') {
            $nouveaux[$pilote] = 'PILOTE';
            // Nouveau pilote : on repart de zéro
            $db->prepare("UPDATE courriers_affectations SET statut='A_TRAITER', soumis_le=NULL, vue_le=NULL WHERE courrier_id=? AND utilisateur_id=?")
               ->execute([$cid, $pilote]);
        }
        foreach ($copies as $uid) {
            $ins->execute([$cid, $uid, 'COPIE', 'A_TRAITER', null, user_id()]);
            if (!isset($avant[$uid])) $nouveaux[$uid] = 'COPIE';
        }

        $nouveau_statut = $ancien === 'RECU' ? 'AFFECTE' : $ancien;
        if (isset($nouveaux[$pilote]) && $ancien === 'EN_TRAITEMENT') $nouveau_statut = 'AFFECTE';
        $db->prepare("UPDATE courriers SET service_id=?, affecte_a=?, instruction=?, statut=? WHERE id=?")
           ->execute([$service_id ?: null, $pilote, trim($instruction) !== '' ? trim($instruction) : null, $nouveau_statut, $cid]);
        $db->commit();

        $noms = [];
        foreach (courrier_affectations($cid) as $a) {
            $noms[] = $a['nom_complet'] . ($a['role'] === 'PILOTE' ? ' (pilote)' : ' (copie)');
        }
        $resume = 'Affecté à : ' . implode(', ', $noms)
                . ($dl ? ' — délai : ' . date('d/m/Y', strtotime($dl)) : '')
                . (trim($instruction) !== '' ? "\nInstruction : " . trim($instruction) : '');
        courrier_suivi_ajouter($cid, empty($avant) ? 'AFFECTATION' : 'REAFFECTATION', $resume, $ancien, $nouveau_statut);
        courrier_echange_ajouter($cid, $resume, 'AFFECTATION');
        log_activite(user_id(), 'COURRIERS', 'AFFECTATION', "Courrier $cid affecté (pilote $pilote, " . count($copies) . ' copie(s))');
        return ['succes'=>true, 'nouveaux'=>$nouveaux];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT courrier_affecter_equipe] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'affectation.'];
    }
}

// ── Traitement par le pilote ─────────────────────────────

function courrier_marquer_vu(int $cid, int $uid): void {
    try {
        get_db()->prepare('UPDATE courriers_affectations SET vue_le=NOW() WHERE courrier_id=? AND utilisateur_id=? AND vue_le IS NULL')
                ->execute([$cid, $uid]);
    } catch (Throwable $e) {}
}

function courrier_commencer(int $cid, int $uid): array {
    $p = courrier_participation($cid, $uid);
    if (!$p || $p['role'] !== 'PILOTE') return ['succes'=>false,'message'=>'Seul l\'agent pilote peut démarrer le traitement.'];
    if ($p['statut'] !== 'A_TRAITER')   return ['succes'=>false,'message'=>'Le traitement est déjà en cours.'];
    $db = get_db();
    $db->prepare("UPDATE courriers_affectations SET statut='EN_COURS', vue_le=COALESCE(vue_le, NOW()) WHERE id=?")->execute([$p['id']]);
    $s = $db->prepare('SELECT statut FROM courriers WHERE id=?');
    $s->execute([$cid]);
    $ancien = (string)$s->fetchColumn();
    if (in_array($ancien, ['RECU','AFFECTE'], true)) {
        $db->prepare("UPDATE courriers SET statut='EN_TRAITEMENT' WHERE id=?")->execute([$cid]);
    }
    courrier_suivi_ajouter($cid, 'TRAITEMENT', 'Traitement démarré par l\'agent pilote', $ancien, 'EN_TRAITEMENT');
    return ['succes'=>true];
}

function courrier_soumettre(int $cid, int $uid, string $message): array {
    $message = trim($message);
    $p = courrier_participation($cid, $uid);
    if (!$p || $p['role'] !== 'PILOTE') return ['succes'=>false,'message'=>'Seul l\'agent pilote peut soumettre le traitement.'];
    if ($p['statut'] === 'SOUMIS')      return ['succes'=>false,'message'=>'Déjà soumis : en attente de la validation du DG.'];
    if ($p['statut'] === 'VALIDE')      return ['succes'=>false,'message'=>'Ce traitement est déjà validé.'];
    if ($message === '')                return ['succes'=>false,'message'=>'Décrivez le traitement effectué.'];
    $db = get_db();
    $db->prepare("UPDATE courriers_affectations SET statut='SOUMIS', soumis_le=NOW(), vue_le=COALESCE(vue_le, NOW()) WHERE id=?")
       ->execute([$p['id']]);
    $s = $db->prepare('SELECT statut FROM courriers WHERE id=?');
    $s->execute([$cid]);
    $ancien = (string)$s->fetchColumn();
    if (in_array($ancien, ['RECU','AFFECTE'], true)) {
        $db->prepare("UPDATE courriers SET statut='EN_TRAITEMENT' WHERE id=?")->execute([$cid]);
    }
    courrier_echange_ajouter($cid, $message, 'SOUMISSION');
    courrier_suivi_ajouter($cid, 'SOUMISSION', 'Traitement soumis au DG pour validation', $ancien, 'EN_TRAITEMENT');
    log_activite($uid, 'COURRIERS', 'SOUMISSION', "Courrier $cid soumis au DG");
    return ['succes'=>true];
}

// ── Décision du DG ───────────────────────────────────────

function courrier_pilote(int $cid): ?array {
    foreach (courrier_affectations($cid) as $a) if ($a['role'] === 'PILOTE') return $a;
    return null;
}

function courrier_valider(int $cid, string $message = ''): array {
    if (!courrier_est_dg()) return ['succes'=>false,'message'=>'Seul le DG peut valider le traitement.'];
    $p = courrier_pilote($cid);
    if (!$p || $p['statut'] !== 'SOUMIS') return ['succes'=>false,'message'=>'Aucun traitement en attente de validation.'];
    $db = get_db();
    $db->prepare("UPDATE courriers_affectations SET statut='VALIDE' WHERE id=?")->execute([$p['id']]);
    $s = $db->prepare('SELECT statut FROM courriers WHERE id=?');
    $s->execute([$cid]);
    $ancien = (string)$s->fetchColumn();
    $db->prepare("UPDATE courriers SET statut='TRAITE', traite_par=?, date_traitement=NOW() WHERE id=?")
       ->execute([(int)$p['utilisateur_id'], $cid]);
    courrier_echange_ajouter($cid, $message, 'VALIDATION');
    courrier_suivi_ajouter($cid, 'VALIDATION', 'Traitement validé par le DG' . (trim($message) !== '' ? ' : ' . trim($message) : ''), $ancien, 'TRAITE');
    log_activite(user_id(), 'COURRIERS', 'VALIDATION', "Courrier $cid validé");
    return ['succes'=>true, 'pilote'=>$p];
}

function courrier_rejeter(int $cid, string $motif): array {
    $motif = trim($motif);
    if (!courrier_est_dg()) return ['succes'=>false,'message'=>'Seul le DG peut renvoyer le traitement.'];
    if ($motif === '')      return ['succes'=>false,'message'=>'Précisez ce qui doit être revu.'];
    $p = courrier_pilote($cid);
    if (!$p || $p['statut'] !== 'SOUMIS') return ['succes'=>false,'message'=>'Aucun traitement en attente de validation.'];
    get_db()->prepare("UPDATE courriers_affectations SET statut='EN_COURS', soumis_le=NULL WHERE id=?")->execute([$p['id']]);
    courrier_echange_ajouter($cid, $motif, 'REJET');
    courrier_suivi_ajouter($cid, 'RENVOI', 'Traitement renvoyé au pilote : ' . $motif, 'EN_TRAITEMENT', 'EN_TRAITEMENT');
    log_activite(user_id(), 'COURRIERS', 'REJET', "Courrier $cid renvoyé au pilote");
    return ['succes'=>true, 'pilote'=>$p];
}

function courrier_cloturer(int $cid, string $commentaire = ''): array {
    if (!courrier_est_dg()) return ['succes'=>false,'message'=>'Seul le DG peut clôturer un courrier.'];
    $res = courrier_changer_statut($cid, 'CLOTURE', trim($commentaire) !== '' ? 'Clôturé : ' . trim($commentaire) : 'Courrier clôturé');
    if ($res['succes']) courrier_echange_ajouter($cid, $commentaire, 'CLOTURE');
    return $res;
}

// ── Notifications internes (badge du menu « Courriers ») ─

function courrier_notif_creer(int $uid, int $cid, string $titre, string $message = ''): void {
    if ($uid <= 0 || $cid <= 0) return;
    courrier_assurer_schema();
    try {
        get_db()->prepare("INSERT INTO courriers_notifications (utilisateur_id, courrier_id, titre, message) VALUES (?,?,?,?)")
                ->execute([$uid, $cid, mb_substr($titre, 0, 150), $message !== '' ? mb_substr($message, 0, 500) : null]);
    } catch (Throwable $e) {
        error_log('[CRICT courriers] notif : '.$e->getMessage());
    }
}

// Appelée par le menu sur chaque page : ne crée pas la table, renvoie 0 si elle n'existe pas encore
function courrier_notifs_non_lues(int $uid): int {
    if ($uid <= 0) return 0;
    try {
        $s = get_db()->prepare("SELECT COUNT(*) FROM courriers_notifications n JOIN courriers c ON c.id=n.courrier_id
                                WHERE n.utilisateur_id=? AND n.lu=0");
        $s->execute([$uid]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function courrier_notif_premiere_non_lue(int $uid): ?array {
    if ($uid <= 0) return null;
    try {
        $s = get_db()->prepare("SELECT n.* FROM courriers_notifications n JOIN courriers c ON c.id=n.courrier_id
                                WHERE n.utilisateur_id=? AND n.lu=0 ORDER BY n.cree_le DESC, n.id DESC LIMIT 1");
        $s->execute([$uid]);
        return $s->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function courrier_notif_marquer_lues(int $uid, int $cid): void {
    try {
        get_db()->prepare("UPDATE courriers_notifications SET lu=1 WHERE utilisateur_id=? AND courrier_id=? AND lu=0")
                ->execute([$uid, $cid]);
    } catch (Throwable $e) {}
}

// Nombre de traitements soumis en attente de la décision du DG
function courrier_nb_a_valider(): int {
    try {
        return (int)get_db()->query("SELECT COUNT(*) FROM courriers_affectations a JOIN courriers c ON c.id=a.courrier_id
                                     WHERE a.role='PILOTE' AND a.statut='SOUMIS'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
