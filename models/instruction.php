<?php
// ============================================================
// models/instruction.php — Instructions (conteneur) + tâches
// Une instruction = 1 tâche (UNIQUE) ou N tâches (MULTIPLE).
// Chaque tâche : 1 responsable, 1 délai (min/h/j), dépendances.
// ============================================================

// ──────────────────────────────────────────────────────────
// DROITS / HELPERS
// ──────────────────────────────────────────────────────────

function _instr_peut_emettre(): bool {
    $u = user_courant();
    return a_permission('INSTRUCTIONS', 'CREER')
        || in_array($u['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG'], true);
}

function _instr_est_emetteur(int $instruction_id, int $user_id): bool {
    if (in_array(user_groupe() ?? '', ['SUPER_ADMIN', 'DG'], true)) return true;
    $s = get_db()->prepare('SELECT 1 FROM instructions WHERE id=? AND emetteur_id=? LIMIT 1');
    $s->execute([$instruction_id, $user_id]);
    return (bool)$s->fetch();
}

function _instr_est_responsable(int $tache_id, int $user_id): bool {
    $s = get_db()->prepare('SELECT 1 FROM instructions_taches WHERE id=? AND assigne_a=? LIMIT 1');
    $s->execute([$tache_id, $user_id]);
    return (bool)$s->fetch();
}

// L'utilisateur a-t-il au moins une tâche dans cette instruction ?
function _instr_a_une_tache(int $instruction_id, int $user_id): bool {
    $s = get_db()->prepare('SELECT 1 FROM instructions_taches WHERE instruction_id=? AND assigne_a=? LIMIT 1');
    $s->execute([$instruction_id, $user_id]);
    return (bool)$s->fetch();
}

function _code_instruction(): string {
    $annee = date('Y');
    $s = get_db()->prepare('SELECT COUNT(*) FROM instructions WHERE YEAR(cree_le)=?');
    $s->execute([$annee]);
    return 'INST-' . $annee . '-' . str_pad((int)$s->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
}

function instr_calc_echeance(int $valeur, string $unite, ?DateTime $base = null): string {
    $base = $base ? clone $base : new DateTime();
    $map  = ['MINUTE' => 'minutes', 'HEURE' => 'hours', 'JOUR' => 'days'];
    $base->modify('+' . max(1, $valeur) . ' ' . ($map[$unite] ?? 'hours'));
    return $base->format('Y-m-d H:i:s');
}

function instr_delai_texte(int $valeur, string $unite): string {
    $lbl = ['MINUTE' => 'minute', 'HEURE' => 'heure', 'JOUR' => 'jour'][$unite] ?? 'heure';
    return $valeur . ' ' . $lbl . ($valeur > 1 ? 's' : '');
}

// ──────────────────────────────────────────────────────────
// LECTURE
// ──────────────────────────────────────────────────────────

function instruction_lister(array $f = [], int $limite = 15, int $offset = 0): array {
    $db = get_db(); $user = user_courant();
    $where = ['1=1']; $params = [];

    if (!_instr_peut_emettre()) {
        $where[]  = 'i.id IN (SELECT instruction_id FROM instructions_taches WHERE assigne_a=?)';
        $params[] = (int)$user['id'];
    }
    if (!empty($f['statut']))   { $where[] = 'i.statut=?';   $params[] = $f['statut']; }
    if (!empty($f['type']))     { $where[] = 'i.type=?';     $params[] = $f['type']; }
    if (!empty($f['priorite'])) { $where[] = 'i.priorite=?'; $params[] = $f['priorite']; }
    if (!empty($f['q'])) {
        $where[] = '(i.objet LIKE ? OR i.description LIKE ? OR i.code LIKE ?)';
        $q = '%' . $f['q'] . '%'; $params[] = $q; $params[] = $q; $params[] = $q;
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM instructions i WHERE $cond");
    $sc->execute($params);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT i.*,
               CONCAT(ue.prenoms,' ',ue.nom) AS emetteur_nom,
               COUNT(t.id)                                      AS nb_taches,
               SUM(t.statut='TERMINEE')                         AS nb_terminees,
               MIN(CASE WHEN t.statut NOT IN ('TERMINEE','ANNULEE') THEN t.date_echeance END) AS prochaine_echeance
        FROM instructions i
        JOIN utilisateurs ue              ON ue.id = i.emetteur_id
        LEFT JOIN instructions_taches t   ON t.instruction_id = i.id
        WHERE $cond
        GROUP BY i.id
        ORDER BY FIELD(i.statut,'EN_COURS','TERMINEE','ANNULEE'),
                 FIELD(i.priorite,'CRITIQUE','HAUTE','NORMALE','FAIBLE'),
                 MIN(CASE WHEN t.statut NOT IN ('TERMINEE','ANNULEE') THEN t.date_echeance END) IS NULL, prochaine_echeance
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($params, [$limite, $offset]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function instruction_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT i.*, CONCAT(ue.prenoms,' ',ue.nom) AS emetteur_nom, ue.email AS emetteur_email
        FROM instructions i JOIN utilisateurs ue ON ue.id = i.emetteur_id
        WHERE i.id=?
    ");
    $s->execute([$id]);
    $i = $s->fetch();
    if (!$i) return null;
    $i['taches'] = instruction_taches($id);
    return $i;
}

function instruction_taches(int $instruction_id): array {
    $db = get_db();
    $s = $db->prepare("
        SELECT t.*,
               CONCAT(u.prenoms,' ',u.nom) AS assigne_nom, u.matricule, u.email,
               g.libelle AS assigne_groupe,
               td.titre  AS depend_titre, td.code AS depend_code, td.statut AS depend_statut,
               TIMESTAMPDIFF(MINUTE, NOW(), t.date_echeance) AS minutes_restantes,
               (SELECT COUNT(*) FROM instructions_taches_outputs o WHERE o.tache_id=t.id) AS nb_outputs
        FROM instructions_taches t
        LEFT JOIN utilisateurs u          ON u.id  = t.assigne_a
        LEFT JOIN groupes_utilisateurs g  ON g.id  = u.groupe_id
        LEFT JOIN instructions_taches td  ON td.id = t.depend_de
        WHERE t.instruction_id=?
        ORDER BY t.ordre, t.id
    ");
    $s->execute([$instruction_id]);
    $taches = $s->fetchAll();

    // Attacher les outputs de chaque tâche
    $so = $db->prepare("
        SELECT o.*, CONCAT(u.prenoms,' ',u.nom) AS deposant
        FROM instructions_taches_outputs o
        LEFT JOIN utilisateurs u ON u.id=o.depose_par
        WHERE o.tache_id=? ORDER BY o.cree_le
    ");
    foreach ($taches as &$t) { $so->execute([$t['id']]); $t['outputs'] = $so->fetchAll(); }
    unset($t);
    return $taches;
}

function itache_charger(int $id): ?array {
    $s = get_db()->prepare("
        SELECT t.*, i.objet AS instruction_objet, i.code AS instruction_code,
               CONCAT(u.prenoms,' ',u.nom) AS assigne_nom
        FROM instructions_taches t
        JOIN instructions i        ON i.id = t.instruction_id
        LEFT JOIN utilisateurs u   ON u.id = t.assigne_a
        WHERE t.id=?
    ");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function instruction_compteurs(): array {
    $db = get_db(); $user = user_courant();
    $where = '1=1'; $params = [];
    if (!_instr_peut_emettre()) {
        $where  = 'id IN (SELECT instruction_id FROM instructions_taches WHERE assigne_a=?)';
        $params = [$user['id']];
    }
    $s = $db->prepare("
        SELECT COUNT(*) total,
               SUM(statut='EN_COURS') en_cours,
               SUM(statut='TERMINEE') terminees,
               SUM(statut='ANNULEE')  annulees,
               SUM(type='UNIQUE')     uniques,
               SUM(type='MULTIPLE')   multiples
        FROM instructions WHERE $where
    ");
    $s->execute($params);
    return $s->fetch() ?: [];
}

// Nombre d'instructions où l'utilisateur a une tâche non terminée (badge menu)
function instruction_nb_en_attente(int $user_id): int {
    $s = get_db()->prepare("
        SELECT COUNT(DISTINCT instruction_id) FROM instructions_taches
        WHERE assigne_a=? AND statut NOT IN ('TERMINEE','ANNULEE')
    ");
    $s->execute([$user_id]);
    return (int)$s->fetchColumn();
}

// ──────────────────────────────────────────────────────────
// ÉCRITURE — INSTRUCTION + TÂCHES (création en un seul formulaire)
// ──────────────────────────────────────────────────────────

function instruction_creer(array $d): array {
    $db = get_db(); $user = user_courant();

    $objet = trim($d['objet'] ?? '');
    if ($objet === '') return ['succes'=>false,'message'=>'L\'objet de l\'instruction est obligatoire.'];

    // Nettoyage des tâches reçues
    $taches = [];
    foreach ((array)($d['taches'] ?? []) as $idx => $t) {
        $titre = trim($t['titre'] ?? '');
        if ($titre === '') continue; // ignore les lignes vides
        $taches[] = [
            'row'             => (int)$idx,
            'titre'           => $titre,
            'description'     => trim($t['description'] ?? ''),
            'assigne_a'       => (int)($t['assigne_a'] ?? 0),
            'delai_valeur'    => max(1, (int)($t['delai_valeur'] ?? 1)),
            'delai_unite'     => in_array($t['delai_unite'] ?? '', ['MINUTE','HEURE','JOUR'], true) ? $t['delai_unite'] : 'HEURE',
            'priorite'        => in_array($t['priorite'] ?? '', ['FAIBLE','NORMALE','HAUTE','CRITIQUE'], true) ? $t['priorite'] : ($d['priorite'] ?? 'NORMALE'),
            'dependance_type' => in_array($t['dependance_type'] ?? '', ['INDEPENDANTE','APRES_FIN','APRES_DEBUT'], true) ? $t['dependance_type'] : 'INDEPENDANTE',
            'depend_row'      => (int)($t['depend_row'] ?? 0), // n° de ligne du prédécesseur (1-based), 0 = aucun
        ];
    }
    if (empty($taches))                 return ['succes'=>false,'message'=>'Ajoutez au moins une tâche.'];
    foreach ($taches as $t) if ($t['assigne_a'] < 1)
        return ['succes'=>false,'message'=>'Chaque tâche doit avoir un responsable.'];

    $type = count($taches) > 1 ? 'MULTIPLE' : 'UNIQUE';

    try {
        $db->beginTransaction();
        $code = _code_instruction();
        $db->prepare("
            INSERT INTO instructions (code, objet, description, type, priorite, statut, emetteur_id)
            VALUES (?,?,?,?,?, 'EN_COURS', ?)
        ")->execute([
            $code, $objet,
            ($d['description'] ?? '') !== '' ? clean($d['description']) : null,
            $type, $d['priorite'] ?? 'NORMALE', (int)$user['id'],
        ]);
        $iid  = (int)$db->lastInsertId();
        $base = new DateTime(); // émission = maintenant

        $ins = $db->prepare("
            INSERT INTO instructions_taches
              (instruction_id, code, titre, description, assigne_a,
               delai_valeur, delai_unite, date_echeance, dependance_type, depend_de,
               statut, priorite, ordre, cree_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        // 1-based row → id réel (pour résoudre les dépendances)
        $map_row_id = [];
        $ordre = 0;
        foreach ($taches as $t) {
            $ordre++;
            $dependant = $t['dependance_type'] !== 'INDEPENDANTE' && $t['depend_row'] > 0;
            $depend_de = ($dependant && isset($map_row_id[$t['depend_row']])) ? $map_row_id[$t['depend_row']] : null;
            if (!$depend_de) { $t['dependance_type'] = 'INDEPENDANTE'; $dependant = false; }

            // Tâche dépendante : bloquée, échéance fixée au déblocage. Sinon : échéance = émission + délai.
            $echeance = $dependant ? null : instr_calc_echeance($t['delai_valeur'], $t['delai_unite'], $base);
            $statut   = $dependant ? 'BLOQUEE' : 'A_FAIRE';

            $ins->execute([
                $iid, 'T-' . str_pad((string)$ordre, 2, '0', STR_PAD_LEFT),
                $t['titre'], $t['description'] ?: null, $t['assigne_a'],
                $t['delai_valeur'], $t['delai_unite'], $echeance,
                $t['dependance_type'], $depend_de, $statut, $t['priorite'], $ordre, (int)$user['id'],
            ]);
            $map_row_id[$t['row'] + 1] = (int)$db->lastInsertId(); // row est 0-based → +1
        }

        $db->commit();
        log_activite((int)$user['id'], 'INSTRUCTIONS', 'CREATION',
            "Instruction $code ($type) — " . count($taches) . " tâche(s)");
        return ['succes'=>true, 'instruction_id'=>$iid, 'code'=>$code];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT instruction_creer] ' . $e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'émission.'];
    }
}

// Modification de l'en-tête de l'instruction (les tâches se gèrent dans voir.php / tache_form.php)
function instruction_modifier_entete(int $id, array $d): array {
    $objet = trim($d['objet'] ?? '');
    if ($objet === '') return ['succes'=>false,'message'=>'L\'objet est obligatoire.'];
    try {
        get_db()->prepare("UPDATE instructions SET objet=?, description=?, priorite=? WHERE id=?")
            ->execute([$objet, ($d['description'] ?? '') !== '' ? clean($d['description']) : null, $d['priorite'] ?? 'NORMALE', $id]);
        log_activite(user_id(), 'INSTRUCTIONS', 'MODIFICATION', "Instruction ID $id modifiée");
        return ['succes'=>true];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

function instruction_annuler(int $id): array {
    try {
        $db = get_db();
        $db->prepare("UPDATE instructions SET statut='ANNULEE' WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE instructions_taches SET statut='ANNULEE' WHERE instruction_id=? AND statut NOT IN ('TERMINEE')")->execute([$id]);
        log_activite(user_id(), 'INSTRUCTIONS', 'ANNULATION', "Instruction ID $id annulée");
        return ['succes'=>true];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

function instruction_supprimer(int $id): array {
    try { get_db()->prepare('DELETE FROM instructions WHERE id=?')->execute([$id]); return ['succes'=>true]; }
    catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

// ──────────────────────────────────────────────────────────
// ÉCRITURE — TÂCHE (ajout/édition individuelle)
// ──────────────────────────────────────────────────────────

function itache_creer(int $instruction_id, array $d): array {
    $titre = trim($d['titre'] ?? '');
    if ($titre === '')                 return ['succes'=>false,'message'=>'Le libellé de la tâche est obligatoire.'];
    if ((int)($d['assigne_a'] ?? 0) < 1) return ['succes'=>false,'message'=>'Sélectionnez un responsable.'];

    $valeur = max(1, (int)($d['delai_valeur'] ?? 1));
    $unite  = in_array($d['delai_unite'] ?? '', ['MINUTE','HEURE','JOUR'], true) ? $d['delai_unite'] : 'HEURE';
    $dtype  = in_array($d['dependance_type'] ?? '', ['INDEPENDANTE','APRES_FIN','APRES_DEBUT'], true) ? $d['dependance_type'] : 'INDEPENDANTE';
    $dep    = (int)($d['depend_de'] ?? 0) ?: null;
    if ($dtype === 'INDEPENDANTE') $dep = null;
    if (!$dep) $dtype = 'INDEPENDANTE';

    $dependant = $dtype !== 'INDEPENDANTE';
    $echeance  = $dependant ? null : instr_calc_echeance($valeur, $unite);
    $statut    = $dependant ? 'BLOQUEE' : 'A_FAIRE';

    try {
        $db = get_db();
        $so = $db->prepare('SELECT COALESCE(MAX(ordre),0)+1 FROM instructions_taches WHERE instruction_id=?');
        $so->execute([$instruction_id]);
        $ordre = (int)$so->fetchColumn();

        $db->prepare("
            INSERT INTO instructions_taches
              (instruction_id, code, titre, description, assigne_a, delai_valeur, delai_unite,
               date_echeance, dependance_type, depend_de, statut, priorite, ordre, cree_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $instruction_id, 'T-' . str_pad((string)$ordre, 2, '0', STR_PAD_LEFT),
            $titre, trim($d['description'] ?? '') ?: null, (int)$d['assigne_a'],
            $valeur, $unite, $echeance, $dtype, $dep, $statut,
            in_array($d['priorite'] ?? '', ['FAIBLE','NORMALE','HAUTE','CRITIQUE'], true) ? $d['priorite'] : 'NORMALE',
            $ordre, user_id(),
        ]);
        $tid = (int)$db->lastInsertId();
        _instr_maj_type($instruction_id);
        _instr_recompute_statut($instruction_id);
        return ['succes'=>true, 'tache_id'=>$tid];
    } catch (Throwable $e) {
        error_log('[CRICT itache_creer] ' . $e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de l\'ajout de la tâche.'];
    }
}

function itache_modifier(int $id, array $d): array {
    $titre = trim($d['titre'] ?? '');
    if ($titre === '') return ['succes'=>false,'message'=>'Le libellé est obligatoire.'];
    $valeur = max(1, (int)($d['delai_valeur'] ?? 1));
    $unite  = in_array($d['delai_unite'] ?? '', ['MINUTE','HEURE','JOUR'], true) ? $d['delai_unite'] : 'HEURE';
    $dtype  = in_array($d['dependance_type'] ?? '', ['INDEPENDANTE','APRES_FIN','APRES_DEBUT'], true) ? $d['dependance_type'] : 'INDEPENDANTE';
    $dep    = (int)($d['depend_de'] ?? 0) ?: null;
    if ($dtype === 'INDEPENDANTE' || $dep === $id) { $dtype = 'INDEPENDANTE'; $dep = null; }

    try {
        $db = get_db();
        $cur = itache_charger($id);
        if (!$cur) return ['succes'=>false,'message'=>'Tâche introuvable.'];

        // Recalcule l'échéance si la tâche est active (non bloquée)
        $echeance = $cur['date_echeance'];
        if ($cur['statut'] !== 'BLOQUEE' && $dtype === 'INDEPENDANTE') {
            $echeance = instr_calc_echeance($valeur, $unite, new DateTime($cur['cree_le']));
        }

        $db->prepare("
            UPDATE instructions_taches SET
              titre=?, description=?, assigne_a=?, delai_valeur=?, delai_unite=?,
              date_echeance=?, dependance_type=?, depend_de=?, priorite=?
            WHERE id=?
        ")->execute([
            $titre, trim($d['description'] ?? '') ?: null, (int)$d['assigne_a'],
            $valeur, $unite, $echeance, $dtype, $dep,
            in_array($d['priorite'] ?? '', ['FAIBLE','NORMALE','HAUTE','CRITIQUE'], true) ? $d['priorite'] : 'NORMALE',
            $id,
        ]);
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT itache_modifier] ' . $e->getMessage());
        return ['succes'=>false,'message'=>'Erreur lors de la modification.'];
    }
}

// Le responsable met à jour SA tâche (statut + avancement + compte rendu)
function itache_changer_statut(int $id, int $user_id, string $statut, string $reponse = '', ?int $avancement = null): array {
    if (!in_array($statut, ['A_FAIRE','EN_COURS','EN_REVISION','TERMINEE','BLOQUEE'], true))
        return ['succes'=>false,'message'=>'Statut invalide.'];
    try {
        $db = get_db();
        $cur = itache_charger($id);
        if (!$cur) return ['succes'=>false,'message'=>'Tâche introuvable.'];
        if ($cur['statut'] === 'BLOQUEE')
            return ['succes'=>false,'message'=>'Cette tâche est en attente d\'une autre tâche.'];

        $pct = $avancement !== null ? max(0, min(100, $avancement)) : (int)$cur['avancement'];
        if ($statut === 'TERMINEE') $pct = 100;
        $termine = $statut === 'TERMINEE' ? 'NOW()' : 'NULL';

        $db->prepare("
            UPDATE instructions_taches
            SET statut=?, avancement=?, reponse=?, termine_le=$termine, vue_le=COALESCE(vue_le, NOW())
            WHERE id=?
        ")->execute([$statut, $pct, $reponse !== '' ? $reponse : null, $id]);

        _instr_debloquer_dependances($id);
        _instr_recompute_statut((int)$cur['instruction_id']);
        log_activite($user_id, 'INSTRUCTIONS', 'SUIVI', "Tâche $id -> $statut");
        return ['succes'=>true];
    } catch (Throwable $e) {
        error_log('[CRICT itache_changer_statut] ' . $e->getMessage());
        return ['succes'=>false,'message'=>'Erreur.'];
    }
}

function itache_supprimer(int $id): array {
    $db  = get_db();
    $s   = $db->prepare('SELECT instruction_id FROM instructions_taches WHERE id=?');
    $s->execute([$id]);
    $iid = (int)$s->fetchColumn();

    // Libère les tâches qui dépendaient de celle-ci
    $sd = $db->prepare("SELECT id, statut, delai_valeur, delai_unite FROM instructions_taches WHERE depend_de=?");
    $sd->execute([$id]);
    foreach ($sd->fetchAll() as $suiv) {
        $echeance = instr_calc_echeance((int)$suiv['delai_valeur'], $suiv['delai_unite']);
        $statut   = $suiv['statut'] === 'BLOQUEE' ? 'A_FAIRE' : $suiv['statut'];
        $db->prepare("UPDATE instructions_taches
                      SET depend_de=NULL, dependance_type='INDEPENDANTE', statut=?,
                          date_echeance=COALESCE(date_echeance, ?)
                      WHERE id=?")->execute([$statut, $echeance, $suiv['id']]);
    }

    $db->prepare('DELETE FROM instructions_taches WHERE id=?')->execute([$id]);
    if ($iid) { _instr_maj_type($iid); _instr_recompute_statut($iid); }
    return ['succes'=>true];
}

// Débloque les tâches dépendant de $pred_id si la condition est remplie
function _instr_debloquer_dependances(int $pred_id): void {
    $db = get_db();
    $s = $db->prepare('SELECT statut FROM instructions_taches WHERE id=?');
    $s->execute([$pred_id]);
    $pred_statut = $s->fetchColumn() ?: '';

    $sd = $db->prepare("SELECT id, dependance_type, delai_valeur, delai_unite
                        FROM instructions_taches WHERE depend_de=? AND statut='BLOQUEE'");
    $sd->execute([$pred_id]);
    foreach ($sd->fetchAll() as $suiv) {
        $declenche = ($suiv['dependance_type'] === 'APRES_FIN'   && $pred_statut === 'TERMINEE')
                  || ($suiv['dependance_type'] === 'APRES_DEBUT' && in_array($pred_statut, ['EN_COURS','EN_REVISION','TERMINEE'], true));
        if ($declenche) {
            $echeance = instr_calc_echeance((int)$suiv['delai_valeur'], $suiv['delai_unite']);
            $db->prepare("UPDATE instructions_taches SET statut='A_FAIRE', date_echeance=? WHERE id=?")
               ->execute([$echeance, $suiv['id']]);
        }
    }
}

function _instr_maj_type(int $instruction_id): void {
    $db = get_db();
    $s = $db->prepare('SELECT COUNT(*) FROM instructions_taches WHERE instruction_id=?');
    $s->execute([$instruction_id]);
    $type = (int)$s->fetchColumn() > 1 ? 'MULTIPLE' : 'UNIQUE';
    $db->prepare('UPDATE instructions SET type=? WHERE id=?')->execute([$type, $instruction_id]);
}

function _instr_recompute_statut(int $instruction_id): void {
    $db = get_db();
    $s = $db->prepare('SELECT statut FROM instructions WHERE id=?');
    $s->execute([$instruction_id]);
    if (($s->fetchColumn() ?: '') === 'ANNULEE') return;

    $c = $db->prepare("SELECT COUNT(*) tot, SUM(statut IN ('TERMINEE','ANNULEE')) fin
                       FROM instructions_taches WHERE instruction_id=?");
    $c->execute([$instruction_id]);
    $r = $c->fetch();
    $nouveau = ((int)$r['tot'] > 0 && (int)$r['tot'] === (int)$r['fin']) ? 'TERMINEE' : 'EN_COURS';
    $db->prepare('UPDATE instructions SET statut=? WHERE id=?')->execute([$nouveau, $instruction_id]);
}

// Candidats prédécesseurs pour une dépendance (tâches existantes de l'instruction)
function itache_pour_dependance(int $instruction_id, int $exclure = 0): array {
    $s = get_db()->prepare("SELECT id, code, titre, statut FROM instructions_taches
                            WHERE instruction_id=? AND id!=? ORDER BY ordre, id");
    $s->execute([$instruction_id, $exclure]);
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────
// OUTPUTS (par tâche)
// ──────────────────────────────────────────────────────────

function instr_mime_autorises(): array {
    return [
        'application/pdf','application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text','application/vnd.oasis.opendocument.spreadsheet',
        'text/plain','text/csv',
        'image/jpeg','image/png','image/webp','image/gif',
        'application/zip','application/x-zip-compressed',
    ];
}

function itache_output_fichier(int $tache_id, int $instruction_id, array $f, string $libelle = ''): array {
    if ($f['error'] !== UPLOAD_ERR_OK) return ['succes'=>false,'message'=>'Erreur upload (#'.$f['error'].')'];
    if ($f['size'] > 20*1024*1024)     return ['succes'=>false,'message'=>'Fichier trop volumineux (max 20 Mo).'];

    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, instr_mime_autorises(), true)) return ['succes'=>false,'message'=>'Type non autorisé.'];

    $type    = in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true) ? 'IMAGE' : 'FICHIER';
    $dir_rel = 'instructions/'.$instruction_id.'/taches/'.$tache_id.'/';
    $dir_abs = UPLOAD_DIR.$dir_rel;
    if (!is_dir($dir_abs)) mkdir($dir_abs, 0750, true);

    $stockage = bin2hex(random_bytes(16)).'.'.$ext;
    if (!move_uploaded_file($f['tmp_name'], $dir_abs.$stockage)) return ['succes'=>false,'message'=>'Déplacement impossible.'];

    try {
        $db = get_db();
        $db->prepare("INSERT INTO instructions_taches_outputs
            (tache_id,instruction_id,type,nom_original,nom_stockage,chemin,type_mime,taille,extension,libelle,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$tache_id,$instruction_id,$type,$f['name'],$stockage,$dir_rel.$stockage,$mime,$f['size'],$ext,$libelle?:$f['name'],user_id()]);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId()];
    } catch (Throwable $e) { @unlink($dir_abs.$stockage); return ['succes'=>false,'message'=>'Erreur base de données.']; }
}

function itache_output_lien(int $tache_id, int $instruction_id, string $url, string $libelle): array {
    if (!filter_var($url, FILTER_VALIDATE_URL)) return ['succes'=>false,'message'=>'URL invalide.'];
    try {
        $db = get_db();
        $db->prepare("INSERT INTO instructions_taches_outputs (tache_id,instruction_id,type,url,libelle,depose_par)
                      VALUES (?,?, 'LIEN', ?,?,?)")
           ->execute([$tache_id,$instruction_id,$url,$libelle?:$url,user_id()]);
        return ['succes'=>true,'id'=>(int)$db->lastInsertId()];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

function itache_output_supprimer(int $id): array {
    try {
        $db = get_db();
        $s = $db->prepare('SELECT * FROM instructions_taches_outputs WHERE id=?');
        $s->execute([$id]);
        $o = $s->fetch();
        if (!$o) return ['succes'=>false,'message'=>'Introuvable.'];
        if ($o['chemin'] && file_exists(UPLOAD_DIR.$o['chemin'])) @unlink(UPLOAD_DIR.$o['chemin']);
        $db->prepare('DELETE FROM instructions_taches_outputs WHERE id=?')->execute([$id]);
        return ['succes'=>true];
    } catch (Throwable $e) { return ['succes'=>false,'message'=>'Erreur.']; }
}

// ──────────────────────────────────────────────────────────
// UTILITAIRES
// ──────────────────────────────────────────────────────────

function instr_liste_users(): array {
    return get_db()->query("
        SELECT u.id, CONCAT(u.prenoms,' ',u.nom) AS nom_complet, u.matricule, g.libelle AS groupe
        FROM utilisateurs u JOIN groupes_utilisateurs g ON g.id=u.groupe_id
        WHERE u.statut='ACTIF' ORDER BY u.nom, u.prenoms
    ")->fetchAll();
}
