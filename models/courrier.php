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
    $where  = ['1=1'];
    $params = [];

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
    $r = [
        'TOTAL'=>0,'ENTRANT'=>0,'SORTANT'=>0,
        'RECU'=>0,'AFFECTE'=>0,'EN_TRAITEMENT'=>0,'TRAITE'=>0,'CLOTURE'=>0,'ARCHIVE'=>0,
        'URGENTS'=>0,'EN_RETARD'=>0,
    ];
    foreach ($db->query("SELECT statut, COUNT(*) n FROM courriers GROUP BY statut")->fetchAll() as $row) {
        $r[$row['statut']] = (int)$row['n'];
        $r['TOTAL'] += (int)$row['n'];
    }
    foreach ($db->query("SELECT sens, COUNT(*) n FROM courriers GROUP BY sens")->fetchAll() as $row) {
        $r[$row['sens']] = (int)$row['n'];
    }
    $r['URGENTS'] = (int)$db->query("
        SELECT COUNT(*) FROM courriers
        WHERE priorite='URGENTE' AND statut NOT IN ('TRAITE','CLOTURE','ARCHIVE')
    ")->fetchColumn();
    $r['EN_RETARD'] = (int)$db->query("
        SELECT COUNT(*) FROM courriers
        WHERE date_echeance IS NOT NULL AND date_echeance < CURDATE()
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
