<?php
// ============================================================
// models/ged.php — Fonctions DB : GED (Gestion Électronique de Documents)
// ============================================================

// ──────────────────────────────────────────────────────────
// CONSTANTES
// ──────────────────────────────────────────────────────────
define('GED_UPLOAD_DIR', UPLOAD_DIR . 'ged/');
define('GED_MAX_SIZE',   20 * 1024 * 1024); // 20 Mo
define('GED_MIME_OK', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'text/plain', 'text/csv',
    'application/zip', 'application/x-zip-compressed',
    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
]);

// Palette proposée pour les dépôts
define('GED_COULEURS', ['#1a5ccc','#00c6a2','#7c3aed','#059669','#f59e0b','#dc2626','#0891b2','#db2777']);
define('GED_ICONES',   ['fa-folder','fa-folder-tree','fa-box-archive','fa-shield-halved','fa-flask','fa-book','fa-database','fa-briefcase']);

// ──────────────────────────────────────────────────────────
// HELPERS INTERNES — contrôle d'accès
// ──────────────────────────────────────────────────────────

// Administrateur global du module (voit / gère tout)
function _ged_est_admin(): bool {
    return in_array(user_groupe() ?? '', ['SUPER_ADMIN', 'DG'], true);
}

function _ged_taille_lisible(int $octets): string {
    if ($octets >= 1048576) return round($octets / 1048576, 1) . ' Mo';
    if ($octets >= 1024)    return round($octets / 1024, 0) . ' Ko';
    return $octets . ' o';
}

function ged_icone_fichier(string $ext): string {
    $ext = strtolower($ext);
    return match (true) {
        $ext === 'pdf'                              => 'fa-file-pdf',
        in_array($ext, ['doc','docx','odt'])        => 'fa-file-word',
        in_array($ext, ['xls','xlsx','ods','csv'])  => 'fa-file-excel',
        in_array($ext, ['ppt','pptx'])              => 'fa-file-powerpoint',
        in_array($ext, ['jpg','jpeg','png','webp','gif']) => 'fa-file-image',
        in_array($ext, ['zip','rar','7z'])          => 'fa-file-zipper',
        $ext === 'txt'                              => 'fa-file-lines',
        default                                     => 'fa-file',
    };
}

// ──────────────────────────────────────────────────────────
// DÉPÔTS — LECTURE
// ──────────────────────────────────────────────────────────

// Un utilisateur "voit" un dépôt s'il en est propriétaire,
// s'il est membre (PARTAGE), si le dépôt est PUBLIC, ou s'il est admin.
function ged_depots_visibles(int $user_id, array $f = []): array {
    $db = get_db();

    $where  = [];
    $params = [];

    if (_ged_est_admin()) {
        $where[] = '1=1';
    } else {
        $where[] = '(d.proprietaire_id = ?
                     OR d.visibilite = "PUBLIC"
                     OR EXISTS (SELECT 1 FROM ged_depot_membres m
                                WHERE m.depot_id = d.id AND m.utilisateur_id = ?))';
        $params[] = $user_id;
        $params[] = $user_id;
    }

    if (!empty($f['visibilite'])) { $where[] = 'd.visibilite = ?'; $params[] = $f['visibilite']; }
    if (!empty($f['q'])) {
        $where[] = '(d.nom LIKE ? OR d.description LIKE ?)';
        $q = '%' . $f['q'] . '%'; $params[] = $q; $params[] = $q;
    }
    $cond = implode(' AND ', $where);

    $sql = "
        SELECT d.*,
               CONCAT(u.prenoms,' ',u.nom)               AS proprietaire_nom,
               (d.proprietaire_id = ?)                   AS est_proprietaire,
               COUNT(DISTINCT doc.id)                    AS nb_documents,
               COUNT(DISTINCT m.id)                      AS nb_membres
        FROM ged_depots d
        JOIN utilisateurs u            ON u.id = d.proprietaire_id
        LEFT JOIN ged_documents doc    ON doc.depot_id = d.id
        LEFT JOIN ged_depot_membres m  ON m.depot_id = d.id
        WHERE $cond
        GROUP BY d.id
        ORDER BY d.modifie_le DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$user_id], $params));
    return $stmt->fetchAll();
}

function ged_depot_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT d.*, CONCAT(u.prenoms,' ',u.nom) AS proprietaire_nom, u.email AS proprietaire_email
        FROM ged_depots d
        JOIN utilisateurs u ON u.id = d.proprietaire_id
        WHERE d.id = ?
    ");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function ged_depot_membres(int $depot_id): array {
    $s = get_db()->prepare("
        SELECT m.*, CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               u.matricule, g.libelle AS groupe
        FROM ged_depot_membres m
        JOIN utilisateurs u         ON u.id = m.utilisateur_id
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE m.depot_id = ?
        ORDER BY u.nom, u.prenoms
    ");
    $s->execute([$depot_id]);
    return $s->fetchAll();
}

// Droits de l'utilisateur courant sur un dépôt
function ged_depot_droits(array $depot, int $user_id): array {
    $est_proprio = (int)$depot['proprietaire_id'] === $user_id;
    $admin       = _ged_est_admin();

    $role_membre = null;
    if (!$est_proprio && !$admin) {
        $s = get_db()->prepare("SELECT role FROM ged_depot_membres WHERE depot_id=? AND utilisateur_id=?");
        $s->execute([$depot['id'], $user_id]);
        $role_membre = $s->fetchColumn() ?: null;
    }

    $peut_voir = $est_proprio || $admin
              || $depot['visibilite'] === 'PUBLIC'
              || $role_membre !== null;

    $peut_deposer = $est_proprio || $admin || $role_membre === 'CONTRIBUTEUR';
    $peut_gerer   = $est_proprio || $admin; // renommer, partager, supprimer le dépôt

    return [
        'voir'        => $peut_voir,
        'deposer'     => $peut_deposer,
        'gerer'       => $peut_gerer,
        'proprietaire'=> $est_proprio,
        'admin'       => $admin,
        'role_membre' => $role_membre,
    ];
}

function ged_compteurs(int $user_id): array {
    $depots = ged_depots_visibles($user_id);
    $r = ['TOTAL' => 0, 'PRIVE' => 0, 'PARTAGE' => 0, 'PUBLIC' => 0, 'DOCS' => 0];
    foreach ($depots as $d) {
        $r['TOTAL']++;
        $r[$d['visibilite']]++;
        $r['DOCS'] += (int)$d['nb_documents'];
    }
    return $r;
}

// ──────────────────────────────────────────────────────────
// DÉPÔTS — ÉCRITURE
// ──────────────────────────────────────────────────────────

function ged_depot_creer(array $d, int $user_id): array {
    $nom = trim($d['nom'] ?? '');
    if ($nom === '') return ['succes' => false, 'message' => 'Le nom du dépôt est obligatoire.'];

    $visibilite = in_array($d['visibilite'] ?? '', ['PRIVE','PARTAGE','PUBLIC'], true)
                ? $d['visibilite'] : 'PRIVE';
    $couleur = in_array($d['couleur'] ?? '', GED_COULEURS, true) ? $d['couleur'] : '#1a5ccc';
    $icone   = in_array($d['icone'] ?? '', GED_ICONES, true)     ? $d['icone']   : 'fa-folder';

    try {
        $db = get_db();
        $db->prepare("
            INSERT INTO ged_depots (nom, description, couleur, icone, visibilite, proprietaire_id)
            VALUES (?,?,?,?,?,?)
        ")->execute([$nom, $d['description'] ?? null, $couleur, $icone, $visibilite, $user_id]);
        $id = (int)$db->lastInsertId();

        if ($visibilite === 'PARTAGE') {
            ged_depot_definir_membres($id, (array)($d['membres'] ?? []), (array)($d['roles'] ?? []));
        }
        log_activite($user_id, 'GED', 'CREATION', "Dépôt « $nom » créé ($visibilite)");
        return ['succes' => true, 'id' => $id];
    } catch (Throwable $e) {
        error_log('[CRICT ged_depot_creer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la création du dépôt.'];
    }
}

function ged_depot_modifier(int $id, array $d, int $user_id): array {
    $nom = trim($d['nom'] ?? '');
    if ($nom === '') return ['succes' => false, 'message' => 'Le nom du dépôt est obligatoire.'];

    $visibilite = in_array($d['visibilite'] ?? '', ['PRIVE','PARTAGE','PUBLIC'], true)
                ? $d['visibilite'] : 'PRIVE';
    $couleur = in_array($d['couleur'] ?? '', GED_COULEURS, true) ? $d['couleur'] : '#1a5ccc';
    $icone   = in_array($d['icone'] ?? '', GED_ICONES, true)     ? $d['icone']   : 'fa-folder';

    try {
        $db = get_db();
        $db->prepare("
            UPDATE ged_depots SET nom=?, description=?, couleur=?, icone=?, visibilite=?
            WHERE id=?
        ")->execute([$nom, $d['description'] ?? null, $couleur, $icone, $visibilite, $id]);

        // Réinitialiser les membres
        $db->prepare("DELETE FROM ged_depot_membres WHERE depot_id=?")->execute([$id]);
        if ($visibilite === 'PARTAGE') {
            ged_depot_definir_membres($id, (array)($d['membres'] ?? []), (array)($d['roles'] ?? []));
        }
        log_activite($user_id, 'GED', 'MODIFICATION', "Dépôt ID $id modifié");
        return ['succes' => true];
    } catch (Throwable $e) {
        error_log('[CRICT ged_depot_modifier] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la modification.'];
    }
}

function ged_depot_definir_membres(int $depot_id, array $membres, array $roles): void {
    $db = get_db();
    foreach ($membres as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0) continue;
        $role = ($roles[$uid] ?? 'LECTEUR') === 'CONTRIBUTEUR' ? 'CONTRIBUTEUR' : 'LECTEUR';
        $db->prepare("
            INSERT INTO ged_depot_membres (depot_id, utilisateur_id, role)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE role=VALUES(role)
        ")->execute([$depot_id, $uid, $role]);
    }
}

function ged_depot_supprimer(int $id, int $user_id): array {
    try {
        $db = get_db();
        // Supprimer les fichiers physiques
        $docs = $db->prepare("SELECT chemin FROM ged_documents WHERE depot_id=?");
        $docs->execute([$id]);
        foreach ($docs->fetchAll() as $doc) {
            $abs = GED_UPLOAD_DIR . ltrim($doc['chemin'], '/');
            if (is_file($abs)) @unlink($abs);
        }
        $dir = GED_UPLOAD_DIR . $id;
        if (is_dir($dir)) @rmdir($dir);

        // ON DELETE CASCADE nettoie documents, accès, groupes, membres
        $db->prepare("DELETE FROM ged_depots WHERE id=?")->execute([$id]);
        log_activite($user_id, 'GED', 'SUPPRESSION', "Dépôt ID $id supprimé");
        return ['succes' => true];
    } catch (Throwable $e) {
        error_log('[CRICT ged_depot_supprimer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS — LECTURE
// ──────────────────────────────────────────────────────────

// Documents visibles par l'utilisateur dans un dépôt donné.
// $dossier_id : 0 = racine du dépôt (documents sans dossier), >0 = un sous-dossier précis.
// Le dépôt doit déjà avoir été jugé visible (cf. ged_depot_droits).
function ged_documents_lister(int $depot_id, array $depot, int $user_id, int $dossier_id = 0): array {
    $db = get_db();
    $droits = ged_depot_droits($depot, $user_id);

    // Condition d'emplacement : racine (aucun dossier) ou dossier précis.
    if ($dossier_id > 0) { $cond = 'emp.dossier_id = ?';   $params = [$depot_id, $dossier_id]; }
    else                 { $cond = 'emp.dossier_id IS NULL'; $params = [$depot_id]; }

    try {
        $s = $db->prepare("
            SELECT doc.*, CONCAT(u.prenoms,' ',u.nom) AS deposant,
                   (SELECT COUNT(*) FROM ged_document_acces  a WHERE a.document_id = doc.id) AS nb_users_autorises,
                   (SELECT COUNT(*) FROM ged_document_groupes gp WHERE gp.document_id = doc.id) AS nb_groupes_autorises
            FROM ged_documents doc
            LEFT JOIN utilisateurs u ON u.id = doc.depose_par
            LEFT JOIN ged_document_emplacement emp ON emp.document_id = doc.id
            WHERE doc.depot_id = ? AND $cond
            ORDER BY doc.cree_le DESC
        ");
        $s->execute($params);
        $docs = $s->fetchAll();
    } catch (Throwable $e) {
        // Repli si la table des emplacements n'existe pas encore (migration non jouée) :
        // tout est considéré à la racine.
        if ($dossier_id > 0) return [];
        $s = $db->prepare("
            SELECT doc.*, CONCAT(u.prenoms,' ',u.nom) AS deposant,
                   (SELECT COUNT(*) FROM ged_document_acces  a WHERE a.document_id = doc.id) AS nb_users_autorises,
                   (SELECT COUNT(*) FROM ged_document_groupes gp WHERE gp.document_id = doc.id) AS nb_groupes_autorises
            FROM ged_documents doc
            LEFT JOIN utilisateurs u ON u.id = doc.depose_par
            WHERE doc.depot_id = ?
            ORDER BY doc.cree_le DESC
        ");
        $s->execute([$depot_id]);
        $docs = $s->fetchAll();
    }

    // Filtrer les documents RESTREINT auxquels l'utilisateur n'a pas accès
    $out = [];
    foreach ($docs as $doc) {
        if ($droits['gerer'] || ged_document_accessible($doc, $user_id)) {
            $out[] = $doc;
        }
    }
    return $out;
}

// ──────────────────────────────────────────────────────────
// SOUS-DOSSIERS D'UN DÉPÔT (1 niveau)
// ──────────────────────────────────────────────────────────

// Liste les sous-dossiers d'un niveau donné (arborescence illimitée).
// $parent_id : null = dossiers à la racine du dépôt ; un id = sous-dossiers de ce dossier.
function ged_dossiers_lister(int $depot_id, ?int $parent_id = null): array {
    try {
        $s = get_db()->prepare("
            SELECT d.*,
                   (SELECT COUNT(*) FROM ged_document_emplacement e WHERE e.dossier_id = d.id) AS nb_documents,
                   (SELECT COUNT(*) FROM ged_dossiers s WHERE s.parent_id = d.id)               AS nb_sous_dossiers
            FROM ged_dossiers d
            WHERE d.depot_id = ? AND d.parent_id <=> ?
            ORDER BY d.nom
        ");
        $s->execute([$depot_id, $parent_id]);
        return $s->fetchAll();
    } catch (Throwable $e) {
        return []; // table absente (migration non jouée)
    }
}

// Fil d'Ariane : liste des dossiers ancêtres, de la racine jusqu'au dossier courant inclus.
function ged_dossier_fil_ariane(int $id): array {
    $chaine = [];
    $garde  = 0;
    $courant = ged_dossier_charger($id);
    while ($courant && $garde < 50) {
        array_unshift($chaine, $courant);
        $pid = $courant['parent_id'] ?? null;
        $courant = $pid ? ged_dossier_charger((int)$pid) : null;
        $garde++;
    }
    return $chaine;
}

function ged_dossier_charger(int $id): ?array {
    try {
        $s = get_db()->prepare("SELECT * FROM ged_dossiers WHERE id = ?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function ged_dossier_creer(int $depot_id, ?int $parent_id, string $nom, int $user_id): array {
    $nom = trim($nom);
    if ($nom === '') return ['succes' => false, 'message' => 'Le nom du dossier est obligatoire.'];
    if (mb_strlen($nom) > 150) $nom = mb_substr($nom, 0, 150);
    $parent_id = $parent_id ?: null;

    try {
        $db = get_db();
        // Le dossier parent doit appartenir au même dépôt
        if ($parent_id) {
            $p = $db->prepare("SELECT depot_id FROM ged_dossiers WHERE id = ?");
            $p->execute([$parent_id]);
            $pd = $p->fetchColumn();
            if ($pd === false || (int)$pd !== $depot_id)
                return ['succes' => false, 'message' => 'Dossier parent invalide.'];
        }

        // Anti-doublon au même niveau (même dépôt + même parent)
        $chk = $db->prepare("SELECT COUNT(*) FROM ged_dossiers WHERE depot_id = ? AND parent_id <=> ? AND nom = ?");
        $chk->execute([$depot_id, $parent_id, $nom]);
        if ((int)$chk->fetchColumn() > 0)
            return ['succes' => false, 'message' => 'Un dossier portant ce nom existe déjà à cet emplacement.'];

        $db->prepare("INSERT INTO ged_dossiers (depot_id, parent_id, nom, cree_par) VALUES (?,?,?,?)")
           ->execute([$depot_id, $parent_id, $nom, $user_id]);
        $id = (int)$db->lastInsertId();
        log_activite($user_id, 'GED', 'CREATION', "Dossier « $nom » créé (dépôt $depot_id, parent " . ($parent_id ?: 'racine') . ")");
        return ['succes' => true, 'id' => $id];
    } catch (Throwable $e) {
        error_log('[CRICT ged_dossier_creer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la création du dossier.'];
    }
}

function ged_dossier_renommer(int $id, string $nom, int $user_id): array {
    $nom = trim($nom);
    if ($nom === '') return ['succes' => false, 'message' => 'Le nom du dossier est obligatoire.'];
    if (mb_strlen($nom) > 150) $nom = mb_substr($nom, 0, 150);

    try {
        $db = get_db();
        $dossier = ged_dossier_charger($id);
        if (!$dossier) return ['succes' => false, 'message' => 'Dossier introuvable.'];

        $chk = $db->prepare("SELECT COUNT(*) FROM ged_dossiers WHERE depot_id = ? AND parent_id <=> ? AND nom = ? AND id <> ?");
        $chk->execute([(int)$dossier['depot_id'], $dossier['parent_id'] ?? null, $nom, $id]);
        if ((int)$chk->fetchColumn() > 0)
            return ['succes' => false, 'message' => 'Un dossier portant ce nom existe déjà à cet emplacement.'];

        $db->prepare("UPDATE ged_dossiers SET nom = ? WHERE id = ?")->execute([$nom, $id]);
        log_activite($user_id, 'GED', 'MODIFICATION', "Dossier ID $id renommé en « $nom »");
        return ['succes' => true];
    } catch (Throwable $e) {
        error_log('[CRICT ged_dossier_renommer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors du renommage.'];
    }
}

// Supprime un dossier. Par sécurité, les documents qu'il contient ne sont PAS
// supprimés : ils retournent à la racine du dépôt (on retire juste leur emplacement).
function ged_dossier_supprimer(int $id, int $user_id): array {
    try {
        $db = get_db();
        $dossier = ged_dossier_charger($id);
        if (!$dossier) return ['succes' => false, 'message' => 'Dossier introuvable.'];

        // Les documents repassent à la racine (suppression des liaisons uniquement)
        $db->prepare("DELETE FROM ged_document_emplacement WHERE dossier_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM ged_dossiers WHERE id = ?")->execute([$id]);
        log_activite($user_id, 'GED', 'SUPPRESSION', "Dossier ID $id supprimé (documents renvoyés à la racine)");
        return ['succes' => true];
    } catch (Throwable $e) {
        error_log('[CRICT ged_dossier_supprimer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la suppression du dossier.'];
    }
}

function ged_document_charger(int $id): ?array {
    $s = get_db()->prepare("
        SELECT doc.*, CONCAT(u.prenoms,' ',u.nom) AS deposant
        FROM ged_documents doc
        LEFT JOIN utilisateurs u ON u.id = doc.depose_par
        WHERE doc.id = ?
    ");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

// Cœur de l'ACL : l'utilisateur peut-il consulter/télécharger CE document ?
function ged_document_accessible(array $doc, int $user_id): bool {
    if (_ged_est_admin()) return true;

    $depot = ged_depot_charger((int)$doc['depot_id']);
    if (!$depot) return false;

    // Le propriétaire du dépôt accède toujours
    if ((int)$depot['proprietaire_id'] === $user_id) return true;
    // Le déposant accède à son document
    if ((int)($doc['depose_par'] ?? 0) === $user_id) return true;

    if ($doc['portee'] === 'HERITE') {
        // Suit la visibilité du dépôt
        if ($depot['visibilite'] === 'PUBLIC') return true;
        if ($depot['visibilite'] === 'PARTAGE') {
            $s = get_db()->prepare("SELECT 1 FROM ged_depot_membres WHERE depot_id=? AND utilisateur_id=? LIMIT 1");
            $s->execute([$depot['id'], $user_id]);
            return (bool)$s->fetch();
        }
        return false; // PRIVE
    }

    // RESTREINT : autorisation explicite par utilisateur OU par groupe
    $db = get_db();
    $s = $db->prepare("SELECT 1 FROM ged_document_acces WHERE document_id=? AND utilisateur_id=? LIMIT 1");
    $s->execute([$doc['id'], $user_id]);
    if ($s->fetch()) return true;

    $user = user_courant();
    $s = $db->prepare("SELECT 1 FROM ged_document_groupes WHERE document_id=? AND groupe_id=? LIMIT 1");
    $s->execute([$doc['id'], (int)($user['groupe_id'] ?? 0)]);
    return (bool)$s->fetch();
}

// Listes d'autorisations d'un document (pour l'écran de partage)
function ged_document_users_autorises(int $doc_id): array {
    $s = get_db()->prepare("SELECT utilisateur_id FROM ged_document_acces WHERE document_id=?");
    $s->execute([$doc_id]);
    return array_map('intval', array_column($s->fetchAll(), 'utilisateur_id'));
}

function ged_document_groupes_autorises(int $doc_id): array {
    $s = get_db()->prepare("SELECT groupe_id FROM ged_document_groupes WHERE document_id=?");
    $s->execute([$doc_id]);
    return array_map('intval', array_column($s->fetchAll(), 'groupe_id'));
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS — ÉCRITURE
// ──────────────────────────────────────────────────────────

function ged_document_deposer(int $depot_id, array $f, array $d, int $user_id): array {
    if (empty($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
        return ['succes' => false, 'message' => 'Aucun fichier sélectionné.'];
    if ($f['error'] !== UPLOAD_ERR_OK)
        return ['succes' => false, 'message' => 'Erreur de téléversement (#' . $f['error'] . ').'];
    if ($f['size'] > GED_MAX_SIZE)
        return ['succes' => false, 'message' => 'Fichier trop volumineux (max 20 Mo).'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, GED_MIME_OK, true))
        return ['succes' => false, 'message' => 'Type de fichier non autorisé.'];

    $ext     = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $dir_rel = $depot_id . '/';
    $dir_abs = GED_UPLOAD_DIR . $dir_rel;
    if (!is_dir($dir_abs)) @mkdir($dir_abs, 0750, true);

    $stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir_abs . $stockage))
        return ['succes' => false, 'message' => 'Impossible d\'enregistrer le fichier.'];

    $portee = ($d['portee'] ?? 'HERITE') === 'RESTREINT' ? 'RESTREINT' : 'HERITE';
    $titre  = trim($d['titre'] ?? '') ?: $f['name'];

    try {
        $db = get_db();
        $db->beginTransaction();
        $db->prepare("
            INSERT INTO ged_documents
              (depot_id,titre,description,nom_original,nom_stockage,chemin,type_mime,taille,extension,portee,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $depot_id, $titre, $d['description'] ?? null,
            $f['name'], $stockage, $dir_rel . $stockage,
            $mime, $f['size'], $ext, $portee, $user_id,
        ]);
        $doc_id = (int)$db->lastInsertId();

        if ($portee === 'RESTREINT') {
            ged_document_definir_acces($doc_id, (array)($d['users'] ?? []), (array)($d['groupes'] ?? []));
        }

        // Rangement dans un sous-dossier (si précisé et valide pour ce dépôt)
        $dossier_id = (int)($d['dossier_id'] ?? 0);
        if ($dossier_id > 0) {
            $chk = $db->prepare("SELECT 1 FROM ged_dossiers WHERE id = ? AND depot_id = ? LIMIT 1");
            $chk->execute([$dossier_id, $depot_id]);
            if ($chk->fetch()) {
                $db->prepare("INSERT INTO ged_document_emplacement (document_id, dossier_id) VALUES (?,?)")
                   ->execute([$doc_id, $dossier_id]);
            }
        }
        $db->commit();
        log_activite($user_id, 'GED', 'DEPOT', "Document « $titre » déposé (dépôt $depot_id)");
        return ['succes' => true, 'id' => $doc_id];
    } catch (Throwable $e) {
        if (get_db()->inTransaction()) get_db()->rollBack();
        @unlink($dir_abs . $stockage);
        error_log('[CRICT ged_document_deposer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur base de données.'];
    }
}

// Met à jour la portée et la liste des autorisés d'un document existant
function ged_document_modifier_acces(int $doc_id, array $d, int $user_id): array {
    $portee = ($d['portee'] ?? 'HERITE') === 'RESTREINT' ? 'RESTREINT' : 'HERITE';
    try {
        $db = get_db();
        $db->beginTransaction();
        $db->prepare("UPDATE ged_documents SET portee=?, titre=COALESCE(NULLIF(?,''),titre), description=? WHERE id=?")
           ->execute([$portee, trim($d['titre'] ?? ''), $d['description'] ?? null, $doc_id]);

        $db->prepare("DELETE FROM ged_document_acces   WHERE document_id=?")->execute([$doc_id]);
        $db->prepare("DELETE FROM ged_document_groupes WHERE document_id=?")->execute([$doc_id]);
        if ($portee === 'RESTREINT') {
            ged_document_definir_acces($doc_id, (array)($d['users'] ?? []), (array)($d['groupes'] ?? []));
        }
        $db->commit();
        log_activite($user_id, 'GED', 'PARTAGE', "Accès du document $doc_id mis à jour ($portee)");
        return ['succes' => true];
    } catch (Throwable $e) {
        if (get_db()->inTransaction()) get_db()->rollBack();
        error_log('[CRICT ged_document_modifier_acces] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la mise à jour des accès.'];
    }
}

function ged_document_definir_acces(int $doc_id, array $users, array $groupes): void {
    $db = get_db();
    foreach ($users as $uid) {
        $uid = (int)$uid;
        if ($uid <= 0) continue;
        $db->prepare("INSERT IGNORE INTO ged_document_acces (document_id, utilisateur_id) VALUES (?,?)")
           ->execute([$doc_id, $uid]);
    }
    foreach ($groupes as $gid) {
        $gid = (int)$gid;
        if ($gid <= 0) continue;
        $db->prepare("INSERT IGNORE INTO ged_document_groupes (document_id, groupe_id) VALUES (?,?)")
           ->execute([$doc_id, $gid]);
    }
}

function ged_document_supprimer(int $doc_id, int $user_id): array {
    try {
        $db  = get_db();
        $doc = ged_document_charger($doc_id);
        if (!$doc) return ['succes' => false, 'message' => 'Document introuvable.'];

        $abs = GED_UPLOAD_DIR . ltrim($doc['chemin'], '/');
        if (is_file($abs)) @unlink($abs);
        $db->prepare("DELETE FROM ged_documents WHERE id=?")->execute([$doc_id]);
        log_activite($user_id, 'GED', 'SUPPRESSION', "Document ID $doc_id supprimé");
        return ['succes' => true];
    } catch (Throwable $e) {
        error_log('[CRICT ged_document_supprimer] ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la suppression.'];
    }
}

// ──────────────────────────────────────────────────────────
// TÉLÉCHARGEMENT SÉCURISÉ
// ──────────────────────────────────────────────────────────
function ged_servir_fichier(int $doc_id, int $user_id, bool $forcer = false): void {
    $doc = ged_document_charger($doc_id);
    if (!$doc) { http_response_code(404); exit('Document introuvable.'); }

    if (!ged_document_accessible($doc, $user_id)) {
        http_response_code(403); exit('Accès refusé.');
    }

    $abs = GED_UPLOAD_DIR . ltrim($doc['chemin'], '/');
    if (!is_file($abs)) { http_response_code(404); exit('Fichier absent du serveur.'); }

    log_activite($user_id, 'GED', 'TELECHARGEMENT', "Document ID $doc_id consulté");

    $disp = $forcer ? 'attachment' : 'inline';
    header('Content-Type: ' . $doc['type_mime']);
    header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($doc['nom_original']) . '"');
    header('Content-Length: ' . filesize($abs));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');
    while (ob_get_level()) ob_end_clean();
    readfile($abs);
    exit;
}

// ──────────────────────────────────────────────────────────
// UTILITAIRES
// ──────────────────────────────────────────────────────────
function ged_liste_users(int $exclure = 0): array {
    $s = get_db()->prepare("
        SELECT u.id, CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               u.matricule, g.libelle AS groupe
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.statut = 'ACTIF' AND u.id != ?
        ORDER BY u.nom, u.prenoms
    ");
    $s->execute([$exclure]);
    return $s->fetchAll();
}

function ged_liste_groupes(): array {
    return get_db()->query("
        SELECT id, libelle, departement FROM groupes_utilisateurs
        WHERE actif = 1 ORDER BY niveau, libelle
    ")->fetchAll();
}
