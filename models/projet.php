<?php
// ============================================================
// models/projet.php  — Fonctions base de données : Projets
// ============================================================

// ──────────────────────────────────────────────────────────
// HELPERS INTERNES (non exposés en dehors du module)
// ──────────────────────────────────────────────────────────

function _projet_peut_gerer(): bool {
    $u = user_courant();
    return a_permission('PROJETS', 'CREER')
        || in_array($u['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG', 'DAF']);
}

function _projet_est_membre(int $projet_id, int $user_id): bool {
    $s = get_db()->prepare('SELECT 1 FROM projets_membres WHERE projet_id=? AND utilisateur_id=? LIMIT 1');
    $s->execute([$projet_id, $user_id]);
    return (bool)$s->fetch();
}

function _projet_est_chef(int $projet_id, int $user_id): bool {
    if (in_array(user_groupe() ?? '', ['SUPER_ADMIN', 'DG'])) return true;
    $db = get_db();
    // Chef via la table membres OU responsable direct du projet
    $s = $db->prepare("
        SELECT 1 FROM projets WHERE id=? AND (responsable_id=? OR cree_par=?)
        UNION
        SELECT 1 FROM projets_membres WHERE projet_id=? AND utilisateur_id=? AND role='CHEF'
        LIMIT 1
    ");
    $s->execute([$projet_id, $user_id, $user_id, $projet_id, $user_id]);
    return (bool)$s->fetch();
}

function _code_projet(): string {
    $annee = date('Y');
    $s = get_db()->prepare('SELECT COUNT(*) FROM projets WHERE YEAR(cree_le)=?');
    $s->execute([$annee]);
    return 'PROJ-' . $annee . '-' . str_pad((int)$s->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
}

function _code_tache(int $projet_id): string {
    $s = get_db()->prepare('SELECT COUNT(*) FROM projets_taches WHERE projet_id=?');
    $s->execute([$projet_id]);
    return 'T-' . str_pad((int)$s->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
}

// ──────────────────────────────────────────────────────────
// PROJETS — LECTURE
// ──────────────────────────────────────────────────────────

function projet_lister(array $f = [], int $limite = 15, int $offset = 0): array {
    $db     = get_db();
    $user   = user_courant();
    $where  = ['1=1'];
    $params = [];

    if (!_projet_peut_gerer()) {
        $where[]  = 'p.id IN (SELECT projet_id FROM projets_membres WHERE utilisateur_id=?)';
        $params[] = (int)$user['id'];
    }
    if (!empty($f['statut']))   { $where[] = 'p.statut=?';      $params[] = $f['statut']; }
    if (!empty($f['type']))     { $where[] = 'p.type_projet=?'; $params[] = $f['type']; }
    if (!empty($f['priorite'])) { $where[] = 'p.priorite=?';    $params[] = $f['priorite']; }
    if (!empty($f['q'])) {
        $where[] = '(p.titre LIKE ? OR p.code LIKE ?)';
        $q = '%'.$f['q'].'%'; $params[] = $q; $params[] = $q;
    }
    $cond = implode(' AND ', $where);

    $sc = get_db()->prepare("SELECT COUNT(DISTINCT p.id) FROM projets p WHERE $cond");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    $sd = get_db()->prepare("
        SELECT p.*,
               CONCAT(u.prenoms,' ',u.nom) AS responsable_nom,
               COUNT(DISTINCT pm.utilisateur_id)               AS nb_membres,
               COUNT(DISTINCT t.id)                            AS nb_taches,
               SUM(t.statut='TERMINEE')                        AS nb_terminees,
               ROUND(COALESCE(AVG(t.avancement),0))            AS avancement_global,
               DATEDIFF(p.date_fin_prevue, CURDATE())          AS jours_restants
        FROM projets p
        JOIN utilisateurs u          ON u.id = p.responsable_id
        LEFT JOIN projets_membres pm ON pm.projet_id = p.id
        LEFT JOIN projets_taches  t  ON t.projet_id  = p.id AND t.parent_id IS NULL
        WHERE $cond
        GROUP BY p.id
        ORDER BY FIELD(p.priorite,'CRITIQUE','HAUTE','NORMALE','FAIBLE'), p.date_fin_prevue
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($params, [$limite, $offset]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function projet_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT p.*, CONCAT(u.prenoms,' ',u.nom) AS responsable_nom, u.email AS responsable_email
        FROM projets p
        JOIN utilisateurs u ON u.id = p.responsable_id
        WHERE p.id=?
    ");
    $s->execute([$id]);
    $p = $s->fetch();
    if (!$p) return null;

    // Membres
    $sm = $db->prepare("
        SELECT pm.*, CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               u.matricule, g.libelle AS groupe, u.email
        FROM projets_membres pm
        JOIN utilisateurs u        ON u.id = pm.utilisateur_id
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE pm.projet_id=?
        ORDER BY pm.role, u.nom
    ");
    $sm->execute([$id]);
    $p['membres'] = $sm->fetchAll();

    // Documents
    $sdoc = $db->prepare("
        SELECT pd.*, CONCAT(u.prenoms,' ',u.nom) AS deposant
        FROM projets_documents pd
        LEFT JOIN utilisateurs u ON u.id = pd.depose_par
        WHERE pd.projet_id=?
        ORDER BY pd.categorie, pd.cree_le
    ");
    $sdoc->execute([$id]);
    $p['documents'] = $sdoc->fetchAll();

    return $p;
}

function projet_stats(int $projet_id): array {
    $s = get_db()->prepare("
        SELECT
            COUNT(*)                                               AS nb_total,
            SUM(statut='TERMINEE')                                 AS nb_terminees,
            SUM(statut='EN_COURS')                                 AS nb_en_cours,
            SUM(statut='BLOQUEE')                                  AS nb_bloquees,
            SUM(statut='A_FAIRE')                                  AS nb_a_faire,
            ROUND(COALESCE(AVG(avancement),0))                     AS avancement_moyen,
            SUM(date_fin_prevue < CURDATE()
                AND statut NOT IN ('TERMINEE','ANNULEE'))          AS nb_en_retard
        FROM projets_taches
        WHERE projet_id=? AND parent_id IS NULL
    ");
    $s->execute([$projet_id]);
    return $s->fetch() ?: [];
}

function projet_compteurs_statuts(): array {
    $db   = get_db();
    $user = user_courant();
    $where  = '1=1'; $params = [];
    if (!_projet_peut_gerer()) {
        $where  = 'id IN (SELECT projet_id FROM projets_membres WHERE utilisateur_id=?)';
        $params = [$user['id']];
    }
    $s = $db->prepare("SELECT statut, COUNT(*) n FROM projets WHERE $where GROUP BY statut");
    $s->execute($params);
    $r = ['TOTAL'=>0,'PLANIFIE'=>0,'EN_COURS'=>0,'SUSPENDU'=>0,'TERMINE'=>0,'ANNULE'=>0];
    foreach ($s->fetchAll() as $row) { $r[$row['statut']] += (int)$row['n']; $r['TOTAL'] += (int)$row['n']; }
    return $r;
}

// ──────────────────────────────────────────────────────────
// PROJETS — ÉCRITURE
// ──────────────────────────────────────────────────────────

function projet_creer(array $d): array {
    $db   = get_db();
    $user = user_courant();
    if (empty($d['titre']) || empty($d['date_debut']) || empty($d['date_fin_prevue']))
        return ['succes'=>false,'message'=>'Titre, date début et date fin sont obligatoires.'];
    if ($d['date_fin_prevue'] <= $d['date_debut'])
        return ['succes'=>false,'message'=>'La date de fin doit être après la date de début.'];
    try {
        $code = _code_projet();
        $db->prepare("
            INSERT INTO projets
              (code,titre,description,tdr,cahier_charges,type_projet,
               budget,devise,date_debut,date_fin_prevue,statut,priorite,
               responsable_id,cree_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $code, $d['titre'], $d['description']??null,
            $d['tdr']??null, $d['cahier_charges']??null,
            $d['type_projet']??'AUTRE',
            !empty($d['budget']) ? (float)$d['budget'] : null,
            $d['devise']??'GNF',
            $d['date_debut'], $d['date_fin_prevue'],
            $d['statut']??'PLANIFIE', $d['priorite']??'NORMALE',
            (int)$d['responsable_id'], (int)$user['id'],
        ]);
        $pid = (int)$db->lastInsertId();
        // Responsable → CHEF
        _proj_upsert_membre($pid, (int)$d['responsable_id'], 'CHEF');
        // Autres membres
        foreach ((array)($d['membres']??[]) as $uid) {
            $uid = (int)$uid;
            if ($uid && $uid !== (int)$d['responsable_id'])
                _proj_upsert_membre($pid, $uid, 'MEMBRE');
        }
        log_activite((int)$user['id'], 'PROJETS', 'CREATION', "Projet $code créé");
        return ['succes'=>true,'projet_id'=>$pid,'code'=>$code];
    } catch (Throwable $e) {
        error_log('[CRICT projet_creer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la création.'];
    }
}

function projet_modifier(int $id, array $d): array {
    $db = get_db();
    if (empty($d['titre'])) return ['succes'=>false,'message'=>'Le titre est obligatoire.'];
    try {
        $db->prepare("
            UPDATE projets SET
              titre=?,description=?,tdr=?,cahier_charges=?,type_projet=?,
              budget=?,devise=?,date_debut=?,date_fin_prevue=?,statut=?,priorite=?,
              responsable_id=?
            WHERE id=?
        ")->execute([
            $d['titre'], $d['description']??null,
            $d['tdr']??null, $d['cahier_charges']??null,
            $d['type_projet']??'AUTRE',
            !empty($d['budget']) ? (float)$d['budget'] : null,
            $d['devise']??'GNF',
            $d['date_debut'], $d['date_fin_prevue'],
            $d['statut'], $d['priorite'],
            (int)$d['responsable_id'], $id,
        ]);
        $db->prepare('DELETE FROM projets_membres WHERE projet_id=?')->execute([$id]);
        _proj_upsert_membre($id, (int)$d['responsable_id'], 'CHEF');
        foreach ((array)($d['membres']??[]) as $uid) {
            $uid = (int)$uid;
            if ($uid && $uid !== (int)$d['responsable_id'])
                _proj_upsert_membre($id, $uid, 'MEMBRE');
        }
        log_activite(user_id(), 'PROJETS', 'MODIFICATION', "Projet ID $id modifié");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT projet_modifier] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

function projet_changer_statut(int $id, string $statut): array {
    if (!in_array($statut, ['PLANIFIE','EN_COURS','SUSPENDU','TERMINE','ANNULE']))
        return ['succes'=>false,'message'=>'Statut invalide.'];
    try {
        $extra = $statut === 'TERMINE' ? ', date_fin_reelle=CURDATE()' : '';
        get_db()->prepare("UPDATE projets SET statut=? $extra WHERE id=?")->execute([$statut,$id]);
        return ['succes'=>true];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

function _proj_upsert_membre(int $pid, int $uid, string $role): void {
    get_db()->prepare("
        INSERT INTO projets_membres (projet_id,utilisateur_id,role) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE role=VALUES(role)
    ")->execute([$pid,$uid,$role]);
}

// ──────────────────────────────────────────────────────────
// TÂCHES — LECTURE
// ──────────────────────────────────────────────────────────

function tache_lister(int $projet_id, array $f = []): array {
    $db   = get_db();
    $user = user_courant();
    $where  = ['t.projet_id=?','t.parent_id IS NULL'];
    $params = [$projet_id];

    // Un simple membre ne voit que ses propres tâches
    if (!_projet_est_chef($projet_id,(int)$user['id'])) {
        $where[]  = '(t.assigne_a=? OR t.assigne_a IS NULL)';
        $params[] = (int)$user['id'];
    }
    if (!empty($f['statut'])) { $where[] = 't.statut=?'; $params[] = $f['statut']; }
    $cond = implode(' AND ', $where);

    $s = $db->prepare("
        SELECT t.*,
               CONCAT(u.prenoms,' ',u.nom)       AS assigne_nom,
               u.matricule                        AS assigne_matricule,
               td.titre                           AS depend_titre,
               COUNT(DISTINCT o.id)               AS nb_outputs,
               COUNT(DISTINCT c.id)               AS nb_commentaires,
               (SELECT COUNT(*) FROM projets_taches st WHERE st.parent_id=t.id) AS nb_sous
        FROM projets_taches t
        LEFT JOIN utilisateurs u                  ON u.id   = t.assigne_a
        LEFT JOIN projets_taches td               ON td.id  = t.depend_de
        LEFT JOIN projets_taches_outputs o        ON o.tache_id = t.id
        LEFT JOIN projets_taches_commentaires c   ON c.tache_id = t.id
        WHERE $cond
        GROUP BY t.id
        ORDER BY t.ordre, t.date_debut, t.id
    ");
    $s->execute($params);
    $taches = $s->fetchAll();

    foreach ($taches as &$t) {
        $ss = $db->prepare("
            SELECT st.*, CONCAT(u.prenoms,' ',u.nom) AS assigne_nom
            FROM projets_taches st
            LEFT JOIN utilisateurs u ON u.id=st.assigne_a
            WHERE st.parent_id=?
            ORDER BY st.ordre, st.id
        ");
        $ss->execute([$t['id']]);
        $t['sous_taches'] = $ss->fetchAll();
    }
    unset($t);
    return $taches;
}

function tache_charger(int $id): ?array {
    $s = get_db()->prepare("
        SELECT t.*,
               CONCAT(u.prenoms,' ',u.nom) AS assigne_nom,
               g.libelle AS assigne_groupe,
               td.titre AS depend_titre,
               p.type_projet, p.titre AS projet_titre
        FROM projets_taches t
        LEFT JOIN utilisateurs u         ON u.id  = t.assigne_a
        LEFT JOIN groupes_utilisateurs g ON g.id  = u.groupe_id
        LEFT JOIN projets_taches td      ON td.id = t.depend_de
        LEFT JOIN projets p              ON p.id  = t.projet_id
        WHERE t.id=?
    ");
    $s->execute([$id]);
    $t = $s->fetch();
    if (!$t) return null;

    $db = get_db();

    $so = $db->prepare("
        SELECT o.*, CONCAT(u.prenoms,' ',u.nom) AS deposant_nom
        FROM projets_taches_outputs o
        LEFT JOIN utilisateurs u ON u.id=o.depose_par
        WHERE o.tache_id=? ORDER BY o.cree_le
    ");
    $so->execute([$id]);
    $t['outputs'] = $so->fetchAll();

    $sc = $db->prepare("
        SELECT c.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom
        FROM projets_taches_commentaires c
        LEFT JOIN utilisateurs u ON u.id=c.auteur_id
        WHERE c.tache_id=? ORDER BY c.cree_le
    ");
    $sc->execute([$id]);
    $t['commentaires'] = $sc->fetchAll();

    $ss = $db->prepare("
        SELECT st.*, CONCAT(u.prenoms,' ',u.nom) AS assigne_nom
        FROM projets_taches st
        LEFT JOIN utilisateurs u ON u.id=st.assigne_a
        WHERE st.parent_id=? ORDER BY st.ordre,st.id
    ");
    $ss->execute([$id]);
    $t['sous_taches'] = $ss->fetchAll();

    return $t;
}

function tache_lister_gantt(int $projet_id): array {
    $s = get_db()->prepare("
        SELECT t.id, t.code, t.titre, t.statut, t.avancement, t.priorite,
               t.date_debut, t.date_fin_prevue, t.depend_de, t.parent_id, t.ordre,
               CONCAT(u.prenoms,' ',u.nom) AS assigne_nom
        FROM projets_taches t
        LEFT JOIN utilisateurs u ON u.id=t.assigne_a
        WHERE t.projet_id=?
        ORDER BY t.parent_id IS NOT NULL, t.ordre, t.date_debut
    ");
    $s->execute([$projet_id]);
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────
// TÂCHES — ÉCRITURE
// ──────────────────────────────────────────────────────────

function tache_creer(array $d): array {
    $db   = get_db();
    $user = user_courant();
    if (empty($d['titre'])) return ['succes'=>false,'message'=>'Le titre est obligatoire.'];
    if (!empty($d['date_fin_prevue']) && !empty($d['date_debut']) && $d['date_fin_prevue'] < $d['date_debut'])
        return ['succes'=>false,'message'=>'La deadline doit être après la date de début.'];
    try {
        $pid  = (int)$d['projet_id'];
        $code = _code_tache($pid);
        $so   = $db->prepare('SELECT COALESCE(MAX(ordre),0)+1 FROM projets_taches WHERE projet_id=?');
        $so->execute([$pid]);
        $ordre = (int)$so->fetchColumn();

        $db->prepare("
            INSERT INTO projets_taches
              (projet_id,parent_id,code,titre,description,assigne_a,
               date_debut,date_fin_prevue,duree_estimee,
               dependance_type,depend_de,statut,avancement,priorite,
               outputs_attendus,ordre,cree_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $pid,
            !empty($d['parent_id']) ? (int)$d['parent_id'] : null,
            $code, $d['titre'], $d['description']??null,
            !empty($d['assigne_a']) ? (int)$d['assigne_a'] : null,
            $d['date_debut']??null, $d['date_fin_prevue']??null,
            !empty($d['duree_estimee']) ? (int)$d['duree_estimee'] : null,
            $d['dependance_type']??'INDEPENDANTE',
            !empty($d['depend_de']) ? (int)$d['depend_de'] : null,
            $d['statut']??'A_FAIRE', (int)($d['avancement']??0),
            $d['priorite']??'NORMALE', $d['outputs_attendus']??null,
            $ordre, (int)$user['id'],
        ]);
        $tid = (int)$db->lastInsertId();
        log_activite((int)$user['id'], 'PROJETS', 'TACHE_CREATION', "Tâche $code — projet $pid");
        return ['succes'=>true,'tache_id'=>$tid,'code'=>$code];
    } catch (Throwable $e) {
        error_log('[CRICT tache_creer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la création.'];
    }
}

function tache_modifier(int $id, array $d): array {
    try {
        $db = get_db();
        $db->prepare("
            UPDATE projets_taches SET
              titre=?,description=?,assigne_a=?,
              date_debut=?,date_fin_prevue=?,duree_estimee=?,
              dependance_type=?,depend_de=?,
              statut=?,avancement=?,priorite=?,outputs_attendus=?
            WHERE id=?
        ")->execute([
            $d['titre'], $d['description']??null,
            !empty($d['assigne_a']) ? (int)$d['assigne_a'] : null,
            $d['date_debut']??null, $d['date_fin_prevue']??null,
            !empty($d['duree_estimee']) ? (int)$d['duree_estimee'] : null,
            $d['dependance_type']??'INDEPENDANTE',
            !empty($d['depend_de']) ? (int)$d['depend_de'] : null,
            $d['statut'], (int)($d['avancement']??0),
            $d['priorite'], $d['outputs_attendus']??null, $id,
        ]);
        if ($d['statut'] === 'TERMINEE') {
            $db->prepare("UPDATE projets_taches SET avancement=100, date_fin_reelle=COALESCE(date_fin_reelle,CURDATE()) WHERE id=?")->execute([$id]);
        }
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT tache_modifier] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

function tache_avancement(int $id, int $pct, string $statut = ''): void {
    $pct = max(0, min(100, $pct));
    $st  = $statut ?: ($pct === 0 ? 'A_FAIRE' : ($pct === 100 ? 'TERMINEE' : 'EN_COURS'));
    $db  = get_db();
    $db->prepare('UPDATE projets_taches SET avancement=?,statut=? WHERE id=?')->execute([$pct,$st,$id]);
    if ($pct === 100) $db->prepare('UPDATE projets_taches SET date_fin_reelle=COALESCE(date_fin_reelle,CURDATE()) WHERE id=?')->execute([$id]);
}

function tache_supprimer(int $id): array {
    $s = get_db()->prepare('SELECT COUNT(*) FROM projets_taches WHERE depend_de=?');
    $s->execute([$id]);
    if ((int)$s->fetchColumn() > 0)
        return ['succes'=>false,'message'=>'D\'autres tâches dépendent de celle-ci.'];
    get_db()->prepare('DELETE FROM projets_taches WHERE id=?')->execute([$id]);
    return ['succes'=>true];
}

// ──────────────────────────────────────────────────────────
// COMMENTAIRES
// ──────────────────────────────────────────────────────────

function commentaire_ajouter(int $tache_id, string $contenu): array {
    if (!trim($contenu)) return ['succes'=>false,'message'=>'Commentaire vide.'];
    try {
        $db = get_db();
        $db->prepare('INSERT INTO projets_taches_commentaires (tache_id,auteur_id,contenu) VALUES (?,?,?)')->execute([$tache_id,user_id(),trim($contenu)]);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId()];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

// ──────────────────────────────────────────────────────────
// OUTPUTS — TYPES MIME PAR TYPE DE PROJET
// ──────────────────────────────────────────────────────────

function output_mime_autorises(string $type_projet): array {
    $docs = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/plain','text/csv',
    ];
    $images   = ['image/jpeg','image/png','image/webp','image/gif'];
    $archives = ['application/zip','application/x-zip-compressed'];

    return match($type_projet) {
        'INFORMATIQUE' => array_merge($docs, $images, $archives),
        'TERRAIN'      => array_merge($docs, $images),
        'RECHERCHE'    => array_merge($docs, $images),
        default        => array_merge($docs, $images),
    };
}

function output_ajouter_fichier(int $tache_id, int $projet_id, string $type_projet, array $f, string $libelle = ''): array {
    if ($f['error'] !== UPLOAD_ERR_OK) return ['succes'=>false,'message'=>'Erreur upload (#'.$f['error'].')'];
    if ($f['size'] > 20*1024*1024)     return ['succes'=>false,'message'=>'Fichier trop volumineux (max 20 Mo).'];

    $ext   = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, output_mime_autorises($type_projet), true))
        return ['succes'=>false,'message'=>'Type de fichier non autorisé pour ce type de projet.'];

    $type_out = in_array($mime, ['image/jpeg','image/png','image/webp','image/gif']) ? 'IMAGE' : 'FICHIER';
    $dir_rel  = 'projets/'.$projet_id.'/taches/'.$tache_id.'/';
    $dir_abs  = UPLOAD_DIR . $dir_rel;
    if (!is_dir($dir_abs)) mkdir($dir_abs, 0750, true);

    $stockage = bin2hex(random_bytes(16)).'.'.$ext;
    if (!move_uploaded_file($f['tmp_name'], $dir_abs.$stockage))
        return ['succes'=>false,'message'=>'Impossible de déplacer le fichier.'];

    try {
        $db = get_db();
        $db->prepare("
            INSERT INTO projets_taches_outputs
              (tache_id,projet_id,type,nom_original,nom_stockage,chemin,type_mime,taille,extension,libelle,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([$tache_id,$projet_id,$type_out,$f['name'],$stockage,$dir_rel.$stockage,$mime,$f['size'],$ext,$libelle?:$f['name'],user_id()]);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId()];
    } catch (Throwable $e) {
        @unlink($dir_abs.$stockage);
        return ['succes'=>false,'message'=>'Erreur base de données.'];
    }
}

function output_ajouter_lien(int $tache_id, int $projet_id, string $url, string $libelle): array {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return ['succes'=>false,'message'=>'URL invalide.'];
    try {
        $db = get_db();
        $db->prepare("INSERT INTO projets_taches_outputs (tache_id,projet_id,type,url,libelle,depose_par) VALUES (?,?,'LIEN',?,?,?)")
           ->execute([$tache_id,$projet_id,$url,$libelle?:$url,user_id()]);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId()];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

function output_supprimer(int $id): array {
    try {
        $db = get_db();
        $s  = $db->prepare('SELECT * FROM projets_taches_outputs WHERE id=?');
        $s->execute([$id]);
        $o = $s->fetch();
        if (!$o) return ['succes'=>false,'message'=>'Introuvable.'];
        if ($o['chemin'] && file_exists(UPLOAD_DIR.$o['chemin'])) @unlink(UPLOAD_DIR.$o['chemin']);
        $db->prepare('DELETE FROM projets_taches_outputs WHERE id=?')->execute([$id]);
        return ['succes'=>true];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS PROJET
// ──────────────────────────────────────────────────────────

function projet_doc_ajouter(int $projet_id, array $f, string $categorie, string $libelle = ''): array {
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 20*1024*1024)
        return ['succes'=>false,'message'=>'Fichier invalide ou trop volumineux.'];

    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ok   = output_mime_autorises('ADMINISTRATIF');
    if (!in_array($mime, $ok, true)) return ['succes'=>false,'message'=>'Type non autorisé.'];

    $dir_rel = 'projets/'.$projet_id.'/documents/';
    $dir_abs = UPLOAD_DIR.$dir_rel;
    if (!is_dir($dir_abs)) mkdir($dir_abs, 0750, true);

    $stockage = bin2hex(random_bytes(16)).'.'.$ext;
    if (!move_uploaded_file($f['tmp_name'], $dir_abs.$stockage))
        return ['succes'=>false,'message'=>'Impossible de déplacer le fichier.'];

    try {
        $db = get_db();
        $db->prepare("
            INSERT INTO projets_documents
              (projet_id,categorie,nom_original,nom_stockage,chemin,type_mime,taille,extension,libelle,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ")->execute([$projet_id,$categorie,$f['name'],$stockage,$dir_rel.$stockage,$mime,$f['size'],$ext,$libelle?:$f['name'],user_id()]);
        return ['succes'=>true];
    } catch (Throwable $e) {
        @unlink($dir_abs.$stockage);
        return ['succes'=>false,'message'=>'Erreur base de données.'];
    }
}

// ──────────────────────────────────────────────────────────
// UTILITAIRES
// ──────────────────────────────────────────────────────────

function projet_liste_users(): array {
    $s = get_db()->query("
        SELECT u.id, CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               u.matricule, g.libelle AS groupe, g.departement
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id=u.groupe_id
        WHERE u.statut='ACTIF'
        ORDER BY u.nom, u.prenoms
    ");
    return $s->fetchAll();
}

function taches_pour_dependance(int $projet_id, int $exclure = 0): array {
    $s = get_db()->prepare("
        SELECT id, code, titre, statut
        FROM projets_taches
        WHERE projet_id=? AND id!=? AND parent_id IS NULL
        ORDER BY ordre, titre
    ");
    $s->execute([$projet_id, $exclure]);
    return $s->fetchAll();
}