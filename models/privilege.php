<?php
// ============================================================
// models/privilege.php — Fonctions DB : Gestion des Privilèges
// Tables : groupes_utilisateurs, modules, permissions,
//          groupes_permissions, permissions_specifiques
// ============================================================

// ──────────────────────────────────────────────────────────
// GROUPES
// ──────────────────────────────────────────────────────────
function priv_groupes_lister(bool $avec_stats = false): array {
    $db = get_db();
    if ($avec_stats) {
        $s = $db->query("
            SELECT g.*,
                   COUNT(DISTINCT u.id)  AS nb_utilisateurs,
                   COUNT(DISTINCT gp.permission_id) AS nb_permissions
            FROM groupes_utilisateurs g
            LEFT JOIN utilisateurs u  ON u.groupe_id = g.id AND u.statut = 'actif'
            LEFT JOIN groupes_permissions gp ON gp.groupe_id = g.id AND gp.accorde = 1
            GROUP BY g.id
            ORDER BY g.niveau ASC, g.departement, g.libelle
        ");
    } else {
        $s = $db->query("SELECT * FROM groupes_utilisateurs ORDER BY niveau ASC, libelle");
    }
    return $s->fetchAll();
}

function priv_groupe_charger(int $id): ?array {
    $s = get_db()->prepare("SELECT * FROM groupes_utilisateurs WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function priv_groupe_creer(array $d): array {
    $db = get_db();
    // Unicité code
    $sc = $db->prepare('SELECT id FROM groupes_utilisateurs WHERE code=? LIMIT 1');
    $sc->execute([strtoupper(trim($d['code']))]);
    if ($sc->fetch()) return ['succes'=>false,'message'=>'Ce code de groupe existe déjà.'];

    try {
        $db->prepare("
            INSERT INTO groupes_utilisateurs
              (code,libelle,departement,description,niveau,actif)
            VALUES (?,?,?,?,?,1)
        ")->execute([
            strtoupper(trim($d['code'])),
            trim($d['libelle']),
            $d['departement'],
            trim($d['description'] ?? ''),
            max(1, (int)($d['niveau'] ?? 5)),
        ]);
        $gid = (int)$db->lastInsertId();

        // Créer les entrées groupes_permissions pour toutes les permissions existantes (accorde=0)
        $db->prepare("
            INSERT IGNORE INTO groupes_permissions (groupe_id, permission_id, accorde)
            SELECT ?, id, 0 FROM permissions
        ")->execute([$gid]);

        log_activite(user_id(),'PARAMETRES','GROUPE_CREATION','Groupe '.$d['code'].' créé');
        return ['succes'=>true,'id'=>$gid];
    } catch (Throwable $e) {
        error_log('[priv_groupe_creer] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la création.'];
    }
}

function priv_groupe_modifier(int $id, array $d): array {
    $db = get_db();
    // Empêcher la modification du SUPER_ADMIN
    $g = priv_groupe_charger($id);
    if (!$g) return ['succes'=>false,'message'=>'Groupe introuvable.'];
    if ($g['code'] === 'SUPER_ADMIN') return ['succes'=>false,'message'=>'Le groupe Super Administrateur ne peut pas être modifié.'];

    try {
        $db->prepare("
            UPDATE groupes_utilisateurs SET
              libelle=?, departement=?, description=?, niveau=?, actif=?
            WHERE id=?
        ")->execute([
            trim($d['libelle']),
            $d['departement'],
            trim($d['description'] ?? ''),
            max(1,(int)($d['niveau']??5)),
            (int)($d['actif']??1),
            $id,
        ]);
        log_activite(user_id(),'PARAMETRES','GROUPE_MODIF',"Groupe $id modifié");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[priv_groupe_modifier] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

function priv_groupe_activer_desactiver(int $id, bool $actif): array {
    $g = priv_groupe_charger($id);
    if (!$g) return ['succes'=>false,'message'=>'Groupe introuvable.'];
    if ($g['code'] === 'SUPER_ADMIN') return ['succes'=>false,'message'=>'Impossible de désactiver le Super Administrateur.'];
    get_db()->prepare("UPDATE groupes_utilisateurs SET actif=? WHERE id=?")->execute([(int)$actif,$id]);
    log_activite(user_id(),'PARAMETRES','GROUPE_STATUT',"Groupe $id -> ".($actif?'actif':'inactif'));
    return ['succes'=>true];
}

function priv_groupe_supprimer(int $id): array {
    $g = priv_groupe_charger($id);
    if (!$g) return ['succes'=>false,'message'=>'Groupe introuvable.'];
    if ($g['code'] === 'SUPER_ADMIN') return ['succes'=>false,'message'=>'Le Super Administrateur ne peut pas être supprimé.'];

    $db = get_db();
    $su = $db->prepare('SELECT COUNT(*) FROM utilisateurs WHERE groupe_id=?');
    $su->execute([$id]);
    if ((int)$su->fetchColumn() > 0)
        return ['succes'=>false,'message'=>'Impossible : des utilisateurs appartiennent encore à ce groupe.'];

    $db->prepare('DELETE FROM groupes_permissions WHERE groupe_id=?')->execute([$id]);
    $db->prepare('DELETE FROM groupes_utilisateurs WHERE id=?')->execute([$id]);
    log_activite(user_id(),'PARAMETRES','GROUPE_SUPPR',"Groupe $id supprimé");
    return ['succes'=>true];
}

// ──────────────────────────────────────────────────────────
// MODULES & PERMISSIONS — LECTURE
// ──────────────────────────────────────────────────────────
function priv_modules_avec_permissions(): array {
    $db = get_db();
    $sm = $db->query("SELECT * FROM modules WHERE actif=1 ORDER BY ordre");
    $modules = $sm->fetchAll();

    $sp = $db->query("SELECT * FROM permissions ORDER BY module_id, action");
    $perms = $sp->fetchAll();

    // Grouper les permissions par module
    $par_module = [];
    foreach ($perms as $p) {
        $par_module[(int)$p['module_id']][] = $p;
    }

    foreach ($modules as &$m) {
        $m['permissions'] = $par_module[(int)$m['id']] ?? [];
    }
    return $modules;
}

// ──────────────────────────────────────────────────────────
// MATRICE PERMISSIONS GROUPE
// ──────────────────────────────────────────────────────────
function priv_matrice_groupe(int $groupe_id): array {
    // Retourne [permission_id => accorde(0|1)]
    $s = get_db()->prepare("SELECT permission_id, accorde FROM groupes_permissions WHERE groupe_id=?");
    $s->execute([$groupe_id]);
    $r = [];
    foreach ($s->fetchAll() as $row) {
        $r[(int)$row['permission_id']] = (int)$row['accorde'];
    }
    return $r;
}

function priv_sauvegarder_matrice_groupe(int $groupe_id, array $permissions_accordees): array {
    // $permissions_accordees = tableau d'IDs de permissions à accorder
    $g = priv_groupe_charger($groupe_id);
    if (!$g) return ['succes'=>false,'message'=>'Groupe introuvable.'];
    if ($g['code'] === 'SUPER_ADMIN') return ['succes'=>false,'message'=>'Les permissions du Super Administrateur sont gérées automatiquement.'];

    $db = get_db();
    try {
        $db->beginTransaction();

        // Récupérer toutes les permissions existantes
        $sp = $db->query("SELECT id FROM permissions");
        $toutes = array_column($sp->fetchAll(),'id');

        $set_accordees = array_map('intval', $permissions_accordees);

        foreach ($toutes as $pid) {
            $accorde = in_array($pid, $set_accordees) ? 1 : 0;
            $db->prepare("
                INSERT INTO groupes_permissions (groupe_id, permission_id, accorde)
                VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE accorde=VALUES(accorde)
            ")->execute([$groupe_id, $pid, $accorde]);
        }

        $db->commit();
        log_activite(user_id(),'PARAMETRES','MATRICE_SAVE',"Matrice groupe $groupe_id sauvegardée");
        return ['succes'=>true,'message'=>'Permissions enregistrées.'];
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[priv_sauvegarder_matrice_groupe] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la sauvegarde.'];
    }
}

// Copier toutes les permissions d'un groupe source vers un groupe cible
function priv_copier_permissions(int $source_id, int $cible_id): array {
    if ($source_id === $cible_id) return ['succes'=>false,'message'=>'Source et cible identiques.'];
    $g = priv_groupe_charger($cible_id);
    if (!$g) return ['succes'=>false,'message'=>'Groupe cible introuvable.'];
    if ($g['code'] === 'SUPER_ADMIN') return ['succes'=>false,'message'=>'Impossible de modifier le Super Administrateur.'];

    $db = get_db();
    $db->prepare("
        INSERT INTO groupes_permissions (groupe_id, permission_id, accorde)
        SELECT ?, permission_id, accorde FROM groupes_permissions WHERE groupe_id=?
        ON DUPLICATE KEY UPDATE accorde=VALUES(accorde)
    ")->execute([$cible_id, $source_id]);

    log_activite(user_id(),'PARAMETRES','PERM_COPIE',"Permissions copiées du groupe $source_id vers $cible_id");
    return ['succes'=>true];
}

// ──────────────────────────────────────────────────────────
// PERMISSIONS SPÉCIFIQUES PAR UTILISATEUR
// ──────────────────────────────────────────────────────────
function priv_perms_specifiques(int $user_id): array {
    $s = get_db()->prepare("
        SELECT ps.*, p.libelle AS perm_libelle, p.action,
               m.libelle AS module_libelle, m.code AS module_code
        FROM permissions_specifiques ps
        JOIN permissions p ON p.id = ps.permission_id
        JOIN modules m     ON m.id = p.module_id
        WHERE ps.utilisateur_id = ?
        ORDER BY m.ordre, p.action
    ");
    $s->execute([$user_id]);
    return $s->fetchAll();
}

function priv_sauvegarder_perms_specifiques(int $user_id, array $overrides, int $accordeur_id): array {
    // $overrides = [permission_id => ['accorde'=>1|0, 'raison'=>'...'], ...]
    $db = get_db();
    try {
        // Supprimer les overrides existants
        $db->prepare("DELETE FROM permissions_specifiques WHERE utilisateur_id=?")->execute([$user_id]);

        foreach ($overrides as $perm_id => $data) {
            $accorde = (int)($data['accorde'] ?? 1);
            $raison  = trim($data['raison'] ?? '');
            $db->prepare("
                INSERT INTO permissions_specifiques (utilisateur_id,permission_id,accorde,raison,accorde_par)
                VALUES (?,?,?,?,?)
            ")->execute([$user_id,(int)$perm_id,$accorde,$raison,$accordeur_id]);
        }

        log_activite($accordeur_id,'PARAMETRES','PERM_SPEC_SAVE',"Overrides utilisateur $user_id sauvegardés");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[priv_sauvegarder_perms_specifiques] '.$e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la sauvegarde.'];
    }
}

function priv_perm_spec_supprimer(int $id): array {
    get_db()->prepare("DELETE FROM permissions_specifiques WHERE id=?")->execute([$id]);
    return ['succes'=>true];
}

// ──────────────────────────────────────────────────────────
// STATS POUR LE TABLEAU DE BORD
// ──────────────────────────────────────────────────────────
function priv_stats(): array {
    $db = get_db();
    $nb_groupes = (int)$db->query("SELECT COUNT(*) FROM groupes_utilisateurs WHERE actif=1")->fetchColumn();
    $nb_modules = (int)$db->query("SELECT COUNT(*) FROM modules WHERE actif=1")->fetchColumn();
    $nb_perms   = (int)$db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    $nb_spec    = (int)$db->query("SELECT COUNT(*) FROM permissions_specifiques")->fetchColumn();
    return compact('nb_groupes','nb_modules','nb_perms','nb_spec');
}
