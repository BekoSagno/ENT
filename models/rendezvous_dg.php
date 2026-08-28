<?php
// ============================================================
// models/rendezvous_dg.php — Rendez-vous & visites du DG
// Disponibilités récurrentes + ponctuelles, réservation instantanée
// ============================================================

const RDV_DG_MODULE = 'RENDEZVOUS_DG';
const RDV_DG_DUREE_DEFAUT = 30;
const RDV_DG_UPLOAD_SUBDIR = 'rendezvous_dg';
const RDV_DG_TEL_ASSISTANTE = '626867672';

const RDV_DG_TYPES = [
    'RENDEZVOUS' => 'Prendre rendez-vous',
    'VISITE'     => 'Visite',
    'AUTRE'      => 'Autre (saisie libre)',
];

const RDV_DG_STATUTS = [
    'EN_ATTENTE' => 'En attente',
    'CONFIRME'   => 'Confirmé',
    'REFUSE'     => 'Refusé',
    'ANNULE'     => 'Annulé',
    'TERMINE'    => 'Terminé',
];

const RDV_DG_JOURS = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    7 => 'Dimanche',
];

function rdv_dg_assurer_schema(): void {
    $db = get_db();
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS dg_rendezvous (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            reference VARCHAR(30) NOT NULL,
            source ENUM('INTERNE','PUBLIC') NOT NULL DEFAULT 'INTERNE',
            type_rdv ENUM('RENDEZVOUS','VISITE','AUTRE') NOT NULL DEFAULT 'RENDEZVOUS',
            type_autre VARCHAR(150) DEFAULT NULL,
            demandeur_utilisateur_id INT UNSIGNED DEFAULT NULL,
            demandeur_personnel_id INT UNSIGNED DEFAULT NULL,
            nom VARCHAR(100) NOT NULL,
            prenoms VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            telephone VARCHAR(40) DEFAULT NULL,
            organisation VARCHAR(200) DEFAULT NULL,
            fonction VARCHAR(200) DEFAULT NULL,
            objet TEXT NOT NULL,
            date_souhaitee DATE DEFAULT NULL,
            creneau_souhaite TIME DEFAULT NULL,
            note_demandeur TEXT DEFAULT NULL,
            piece_jointe VARCHAR(500) DEFAULT NULL,
            statut ENUM('EN_ATTENTE','CONFIRME','REFUSE','ANNULE','TERMINE') NOT NULL DEFAULT 'EN_ATTENTE',
            disponibilite_id INT UNSIGNED DEFAULT NULL,
            date_confirmee DATE DEFAULT NULL,
            heure_debut TIME DEFAULT NULL,
            heure_fin TIME DEFAULT NULL,
            duree_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            lieu VARCHAR(255) DEFAULT NULL,
            hors_plage TINYINT(1) NOT NULL DEFAULT 0,
            commentaire_gestion TEXT DEFAULT NULL,
            motif_refus TEXT DEFAULT NULL,
            motif_annulation TEXT DEFAULT NULL,
            planifie_par INT UNSIGNED DEFAULT NULL,
            planifie_le DATETIME DEFAULT NULL,
            rappel_j1_envoye_le DATETIME DEFAULT NULL,
            cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modifie_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rdv_ref (reference),
            KEY idx_rdv_statut (statut),
            KEY idx_rdv_date (date_confirmee),
            KEY idx_rdv_email (email),
            KEY idx_rdv_user (demandeur_utilisateur_id),
            KEY idx_rdv_dispo (disponibilite_id),
            KEY idx_rdv_creneau (date_confirmee, heure_debut, statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[CRICT RDV] schema rdv : ' . $e->getMessage());
    }

    // Colonnes éventuelles manquantes (installations antérieures)
    foreach ([
        "ALTER TABLE dg_rendezvous ADD COLUMN disponibilite_id INT UNSIGNED DEFAULT NULL AFTER piece_jointe",
        "ALTER TABLE dg_rendezvous ADD COLUMN motif_annulation TEXT DEFAULT NULL AFTER motif_refus",
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS dg_disponibilites (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type_dispo ENUM('RECURRENT','PONCTUEL') NOT NULL DEFAULT 'RECURRENT',
            jour_semaine TINYINT UNSIGNED DEFAULT NULL COMMENT '1=lundi … 7=dimanche',
            date_ponctuelle DATE DEFAULT NULL,
            heure_debut TIME NOT NULL,
            heure_fin TIME NOT NULL,
            duree_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            actif TINYINT(1) NOT NULL DEFAULT 1,
            note VARCHAR(255) DEFAULT NULL,
            cree_par INT UNSIGNED DEFAULT NULL,
            cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_dispo_type (type_dispo, actif),
            KEY idx_dispo_jour (jour_semaine),
            KEY idx_dispo_date (date_ponctuelle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[CRICT RDV] schema dispo : ' . $e->getMessage());
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS dg_dispo_exceptions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            disponibilite_id INT UNSIGNED NOT NULL,
            date_exception DATE NOT NULL,
            motif VARCHAR(255) DEFAULT NULL,
            cree_par INT UNSIGNED DEFAULT NULL,
            cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_dispo_exc (disponibilite_id, date_exception),
            KEY idx_exc_date (date_exception)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[CRICT RDV] schema exc : ' . $e->getMessage());
    }

    try {
        $db->exec("CREATE TABLE IF NOT EXISTS dg_rdv_notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            utilisateur_id INT UNSIGNED NOT NULL,
            rendezvous_id INT UNSIGNED DEFAULT NULL,
            titre VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            lu TINYINT(1) NOT NULL DEFAULT 0,
            cree_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notif_user (utilisateur_id, lu),
            KEY idx_notif_rdv (rendezvous_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[CRICT RDV] schema notif : ' . $e->getMessage());
    }

    rdv_dg_assurer_module();
}

function rdv_dg_assurer_module(): void {
    $db = get_db();
    try {
        $db->exec("INSERT INTO modules (code, libelle, icone, url_base, ordre, actif)
            SELECT 'RENDEZVOUS_DG', 'Rendez-vous DG', 'fa-calendar-check', '/modules/rendezvous_dg/', 8, 1
            WHERE NOT EXISTS (SELECT 1 FROM modules WHERE code = 'RENDEZVOUS_DG')");
        $mid = (int)$db->query("SELECT id FROM modules WHERE code = 'RENDEZVOUS_DG' LIMIT 1")->fetchColumn();
        if ($mid <= 0) return;

        foreach ([
            'VOIR' => 'Consulter les rendez-vous DG',
            'CREER' => 'Créer une demande de rendez-vous',
            'VALIDER' => 'Gérer disponibilités / confirmer / refuser',
            'MODIFIER' => 'Modifier une demande',
        ] as $action => $lib) {
            $chk = $db->prepare("SELECT id FROM permissions WHERE module_id = ? AND action = ?");
            $chk->execute([$mid, $action]);
            if (!$chk->fetchColumn()) {
                $db->prepare("INSERT INTO permissions (module_id, action, libelle) VALUES (?,?,?)")
                   ->execute([$mid, $action, $lib]);
            }
        }

        $db->exec("INSERT INTO groupes_permissions (groupe_id, permission_id, accorde)
            SELECT g.id, p.id, 1 FROM groupes_utilisateurs g
            JOIN permissions p ON p.module_id = $mid AND p.action IN ('VOIR','CREER')
            WHERE g.actif = 1 AND NOT EXISTS (
                SELECT 1 FROM groupes_permissions gp WHERE gp.groupe_id = g.id AND gp.permission_id = p.id)");

        $db->exec("INSERT INTO groupes_permissions (groupe_id, permission_id, accorde)
            SELECT g.id, p.id, 1 FROM groupes_utilisateurs g
            JOIN permissions p ON p.module_id = $mid AND p.action IN ('VALIDER','MODIFIER')
            WHERE g.code IN ('SUPER_ADMIN','DG') AND NOT EXISTS (
                SELECT 1 FROM groupes_permissions gp WHERE gp.groupe_id = g.id AND gp.permission_id = p.id)");
    } catch (Throwable $e) {
        error_log('[CRICT RDV] module perms : ' . $e->getMessage());
    }
}

function rdv_dg_peut_gerer(): bool {
    $u = user_courant();
    if (!$u) return false;
    $g = $u['groupe_code'] ?? '';
    if ($g === 'SUPER_ADMIN' || $g === 'DG') return true;
    return a_permission(RDV_DG_MODULE, 'VALIDER');
}

function rdv_dg_libelle_type(array $r): string {
    if (($r['type_rdv'] ?? '') === 'AUTRE') {
        $a = trim((string)($r['type_autre'] ?? ''));
        return $a !== '' ? $a : 'Autre';
    }
    return RDV_DG_TYPES[$r['type_rdv'] ?? ''] ?? ($r['type_rdv'] ?? '—');
}

function rdv_dg_libelle_statut(string $s): string {
    return RDV_DG_STATUTS[$s] ?? $s;
}

function rdv_dg_generer_reference(): string {
    $annee = date('Y');
    $stmt = get_db()->prepare("SELECT COUNT(*) FROM dg_rendezvous WHERE reference LIKE ?");
    $stmt->execute(['RDV-' . $annee . '-%']);
    return sprintf('RDV-%s-%04d', $annee, (int)$stmt->fetchColumn() + 1);
}

function rdv_dg_fmt_heure(?string $h): string {
    return $h ? substr($h, 0, 5) : '—';
}

function rdv_dg_fmt_date(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    if (!$ts) return $d;
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $j = (int)date('j', $ts);
    return (($j === 1) ? '1er' : (string)$j) . ' ' . ($mois[(int)date('n', $ts)] ?? '') . ' ' . date('Y', $ts);
}

function rdv_dg_heure_fin(string $heure_debut, int $duree = RDV_DG_DUREE_DEFAUT): string {
    $ts = strtotime('1970-01-01 ' . substr($heure_debut, 0, 8));
    return $ts ? date('H:i:s', $ts + $duree * 60) : '';
}

function rdv_dg_normalize_time(string $h): string {
    $h = trim($h);
    if (preg_match('/^\d{2}:\d{2}$/', $h)) return $h . ':00';
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) return $h;
    return '';
}

// ─── Disponibilités ────────────────────────────────────────

function rdv_dg_dispo_lister(bool $actifs_seulement = false): array {
    rdv_dg_assurer_schema();
    $sql = "SELECT * FROM dg_disponibilites";
    if ($actifs_seulement) $sql .= " WHERE actif = 1";
    $sql .= " ORDER BY type_dispo, jour_semaine, date_ponctuelle, heure_debut";
    return get_db()->query($sql)->fetchAll();
}

function rdv_dg_dispo_par_id(int $id): ?array {
    $st = get_db()->prepare("SELECT * FROM dg_disponibilites WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function rdv_dg_dispo_ajouter(array $data): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];

    // Uniquement les créneaux récurrents (hebdomadaires)
    $debut = rdv_dg_normalize_time((string)($data['heure_debut'] ?? ''));
    if ($debut === '') return ['succes' => false, 'message' => 'Heure de début invalide.'];
    $duree = (int)($data['duree_minutes'] ?? RDV_DG_DUREE_DEFAUT);
    if ($duree < 15 || $duree > 240) $duree = RDV_DG_DUREE_DEFAUT;
    $fin = rdv_dg_normalize_time((string)($data['heure_fin'] ?? ''));
    if ($fin === '') $fin = rdv_dg_heure_fin($debut, $duree);
    if (strtotime($fin) <= strtotime($debut)) {
        return ['succes' => false, 'message' => 'L\'heure de fin doit être après le début.'];
    }

    $jour = (int)($data['jour_semaine'] ?? 0);
    if ($jour < 1 || $jour > 7) return ['succes' => false, 'message' => 'Jour de la semaine invalide.'];

    get_db()->prepare("
        INSERT INTO dg_disponibilites
            (type_dispo, jour_semaine, date_ponctuelle, heure_debut, heure_fin, duree_minutes, actif, note, cree_par)
        VALUES ('RECURRENT',?,NULL,?,?,?,1,?,?)
    ")->execute([
        $jour, $debut, $fin, $duree,
        trim((string)($data['note'] ?? '')) ?: null,
        user_id(),
    ]);
    return ['succes' => true, 'id' => (int)get_db()->lastInsertId()];
}

function rdv_dg_dispo_modifier(int $id, array $data): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $d = rdv_dg_dispo_par_id($id);
    if (!$d) return ['succes' => false, 'message' => 'Créneau introuvable.'];
    if (($d['type_dispo'] ?? '') !== 'RECURRENT') {
        return ['succes' => false, 'message' => 'Seuls les créneaux récurrents peuvent être modifiés.'];
    }

    $debut = rdv_dg_normalize_time((string)($data['heure_debut'] ?? ''));
    if ($debut === '') return ['succes' => false, 'message' => 'Heure de début invalide.'];
    $duree = (int)($data['duree_minutes'] ?? $d['duree_minutes'] ?? RDV_DG_DUREE_DEFAUT);
    if ($duree < 15 || $duree > 240) $duree = RDV_DG_DUREE_DEFAUT;
    $fin = rdv_dg_normalize_time((string)($data['heure_fin'] ?? ''));
    if ($fin === '') $fin = rdv_dg_heure_fin($debut, $duree);
    if (strtotime($fin) <= strtotime($debut)) {
        return ['succes' => false, 'message' => 'L\'heure de fin doit être après le début.'];
    }

    $jour = (int)($data['jour_semaine'] ?? $d['jour_semaine'] ?? 0);
    if ($jour < 1 || $jour > 7) return ['succes' => false, 'message' => 'Jour de la semaine invalide.'];

    get_db()->prepare("
        UPDATE dg_disponibilites
        SET jour_semaine = ?, date_ponctuelle = NULL, heure_debut = ?, heure_fin = ?,
            duree_minutes = ?, note = ?
        WHERE id = ?
    ")->execute([
        $jour, $debut, $fin, $duree,
        trim((string)($data['note'] ?? '')) ?: null,
        $id,
    ]);
    return ['succes' => true];
}

function rdv_dg_dispo_desactiver(int $id): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    get_db()->prepare("UPDATE dg_disponibilites SET actif = 0 WHERE id = ?")->execute([$id]);
    return ['succes' => true];
}

function rdv_dg_dispo_activer(int $id): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    get_db()->prepare("UPDATE dg_disponibilites SET actif = 1 WHERE id = ?")->execute([$id]);
    return ['succes' => true];
}

function rdv_dg_dispo_supprimer(int $id): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    get_db()->prepare("DELETE FROM dg_dispo_exceptions WHERE disponibilite_id = ?")->execute([$id]);
    get_db()->prepare("DELETE FROM dg_disponibilites WHERE id = ?")->execute([$id]);
    return ['succes' => true];
}

function rdv_dg_exception_ajouter(int $dispo_id, string $date, string $motif = ''): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $d = rdv_dg_dispo_par_id($dispo_id);
    if (!$d || $d['type_dispo'] !== 'RECURRENT') {
        return ['succes' => false, 'message' => 'Disponibilité récurrente introuvable.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['succes' => false, 'message' => 'Date invalide.'];
    }
    if ((int)date('N', strtotime($date)) !== (int)$d['jour_semaine']) {
        return ['succes' => false, 'message' => 'Cette date ne correspond pas au jour de ce créneau récurrent.'];
    }
    if ($date < date('Y-m-d')) {
        return ['succes' => false, 'message' => 'Impossible de bloquer une date passée.'];
    }
    $debut = rdv_dg_normalize_time($d['heure_debut']);
    if ($date === date('Y-m-d') && $debut <= date('H:i:s')) {
        return ['succes' => false, 'message' => 'Ce créneau est déjà passé pour aujourd\'hui.'];
    }
    $autorisees = array_column(rdv_dg_prochaines_occurrences($d, 52), 'date');
    if ($autorisees && !in_array($date, $autorisees, true)) {
        return ['succes' => false, 'message' => 'Date non valide pour ce créneau.'];
    }
    try {
        get_db()->prepare("
            INSERT INTO dg_dispo_exceptions (disponibilite_id, date_exception, motif, cree_par)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE motif = VALUES(motif)
        ")->execute([$dispo_id, $date, trim($motif) ?: null, user_id()]);
    } catch (Throwable $e) {
        return ['succes' => false, 'message' => 'Impossible d\'enregistrer l\'exception.'];
    }
    return ['succes' => true];
}

/**
 * Prochaines occurrences d'un créneau récurrent (dates futures, non déjà bloquées).
 * @return list<array{date:string,label:string}>
 */
function rdv_dg_prochaines_occurrences(array $dispo, int $limit = 12): array {
    if (($dispo['type_dispo'] ?? '') !== 'RECURRENT') return [];

    $jour = (int)($dispo['jour_semaine'] ?? 0);
    if ($jour < 1 || $jour > 7) return [];

    $debut = rdv_dg_normalize_time($dispo['heure_debut']);
    $fin = rdv_dg_normalize_time($dispo['heure_fin']);
    $dispo_id = (int)$dispo['id'];

    $bloquees = [];
    foreach (rdv_dg_exceptions_lister($dispo_id) as $e) {
        $bloquees[$e['date_exception']] = true;
    }

    $aujourdhui = date('Y-m-d');
    $maintenant = date('H:i:s');
    $js_actuel = (int)date('N');
    $decal = ($jour - $js_actuel + 7) % 7;
    if ($decal === 0 && $debut <= $maintenant) {
        $decal = 7;
    }

    $out = [];
    $t = strtotime($aujourdhui . " +$decal days");
    $jour_lbl = RDV_DG_JOURS[$jour] ?? '';

    while (count($out) < $limit && $t <= strtotime('+2 years')) {
        $date = date('Y-m-d', $t);
        if ((int)date('N', $t) === $jour && !isset($bloquees[$date])) {
            $out[] = [
                'date'  => $date,
                'label' => $jour_lbl . ' ' . date('d/m/Y', $t)
                    . ' — ' . rdv_dg_fmt_heure($debut) . ' – ' . rdv_dg_fmt_heure($fin),
            ];
        }
        $t = strtotime($date . ' +7 days');
    }

    return $out;
}

function rdv_dg_exception_supprimer(int $id): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    get_db()->prepare("DELETE FROM dg_dispo_exceptions WHERE id = ?")->execute([$id]);
    return ['succes' => true];
}

function rdv_dg_exceptions_lister(?int $dispo_id = null): array {
    $sql = "SELECT e.*, d.heure_debut, d.heure_fin, d.jour_semaine
            FROM dg_dispo_exceptions e
            JOIN dg_disponibilites d ON d.id = e.disponibilite_id
            WHERE e.date_exception >= CURDATE()";
    $params = [];
    if ($dispo_id) {
        $sql .= " AND e.disponibilite_id = ?";
        $params[] = $dispo_id;
    }
    $sql .= " ORDER BY e.date_exception, d.heure_debut";
    $st = get_db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Occupe le créneau si EN_ATTENTE ou CONFIRME. */
function rdv_dg_creneau_occupe(string $date, string $heure_debut, ?int $exclure_id = null): bool {
    $h = rdv_dg_normalize_time($heure_debut);
    $sql = "SELECT id FROM dg_rendezvous
            WHERE statut IN ('EN_ATTENTE','CONFIRME')
              AND date_confirmee = ?
              AND heure_debut = ?";
    $params = [$date, $h];
    if ($exclure_id) {
        $sql .= " AND id != ?";
        $params[] = $exclure_id;
    }
    $st = get_db()->prepare($sql . " LIMIT 1");
    $st->execute($params);
    return (bool)$st->fetchColumn();
}

/** Rendez-vous en cours (en attente ou confirmé) pour un même demandeur. */
function rdv_dg_rdv_en_cours_demandeur(?int $utilisateur_id, string $email, ?int $personnel_id = null): ?array {
    rdv_dg_assurer_schema();
    $email = strtolower(trim($email));
    $clauses = ['statut IN (\'EN_ATTENTE\',\'CONFIRME\')'];
    $params = [];

    if ($utilisateur_id !== null && $utilisateur_id > 0) {
        $clauses[] = '(demandeur_utilisateur_id = ? OR email = ?)';
        $params[] = $utilisateur_id;
        $params[] = $email;
    } elseif ($personnel_id !== null && $personnel_id > 0) {
        $clauses[] = '(demandeur_personnel_id = ? OR email = ?)';
        $params[] = $personnel_id;
        $params[] = $email;
    } else {
        $clauses[] = 'email = ?';
        $params[] = $email;
    }

    $st = get_db()->prepare('
        SELECT * FROM dg_rendezvous
        WHERE ' . implode(' AND ', $clauses) . '
        ORDER BY FIELD(statut, \'CONFIRME\', \'EN_ATTENTE\'), cree_le DESC
        LIMIT 1
    ');
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

function rdv_dg_email_contact_assistante(): string {
    return 'l\'assistante du Directeur Général (Tél. : ' . RDV_DG_TEL_ASSISTANTE . ')';
}

/**
 * Créneaux libres pour une plage de dates.
 * @return list<array{dispo_id:int,date:string,heure_debut:string,heure_fin:string,duree:int,type:string}>
 */
function rdv_dg_creneaux_plage(string $date_debut, string $date_fin): array {
    rdv_dg_assurer_schema();
    $dispos = rdv_dg_dispo_lister(true);
    if (!$dispos) return [];

    $exc = [];
    foreach (rdv_dg_exceptions_lister() as $e) {
        $exc[(int)$e['disponibilite_id'] . '|' . $e['date_exception']] = true;
    }

    $out = [];
    $ts0 = strtotime($date_debut);
    $ts1 = strtotime($date_fin);
    if (!$ts0 || !$ts1 || $ts1 < $ts0) return [];

    $aujourdhui = date('Y-m-d');
    $maintenant = date('H:i:s');

    for ($t = $ts0; $t <= $ts1; $t += 86400) {
        $date = date('Y-m-d', $t);
        $js = (int)date('N', $t);

        foreach ($dispos as $d) {
            // Programmation : uniquement les créneaux récurrents
            if (($d['type_dispo'] ?? '') !== 'RECURRENT') continue;
            if ((int)$d['jour_semaine'] !== $js) continue;

            $debut = rdv_dg_normalize_time($d['heure_debut']);
            $fin = rdv_dg_normalize_time($d['heure_fin']);
            $bloque = isset($exc[(int)$d['id'] . '|' . $date]);

            if ($bloque) {
                $etat = 'bloque';
            } elseif ($date < $aujourdhui || ($date === $aujourdhui && $debut <= $maintenant)) {
                $etat = 'passe';
            } elseif (rdv_dg_creneau_occupe($date, $debut)) {
                $etat = 'pris';
            } else {
                $etat = 'libre';
            }

            $out[] = [
                'dispo_id'     => (int)$d['id'],
                'date'         => $date,
                'heure_debut'  => $debut,
                'heure_fin'    => $fin,
                'duree'        => (int)$d['duree_minutes'],
                'type'         => $d['type_dispo'],
                'etat'         => $etat,
                'selectable'   => $etat === 'libre',
                'label'        => rdv_dg_fmt_heure($debut) . ' – ' . rdv_dg_fmt_heure($fin),
            ];
        }
    }

    usort($out, static function ($a, $b) {
        return strcmp($a['date'] . $a['heure_debut'], $b['date'] . $b['heure_debut']);
    });
    return $out;
}

function rdv_dg_creneaux_libres(string $date_debut, string $date_fin): array {
    return array_values(array_filter(
        rdv_dg_creneaux_plage($date_debut, $date_fin),
        static fn($c) => ($c['etat'] ?? '') === 'libre'
    ));
}

/** Créneaux de la semaine en cours (lundi → dimanche), y compris passés / pris. */
function rdv_dg_creneaux_semaine(?string $lundi = null, int $nb_semaines = 1): array {
    // Toujours la semaine calendaire courante (navigation interdite)
    $n = (int)date('N');
    $lundi = date('Y-m-d', strtotime('-' . ($n - 1) . ' days'));
    $fin = date('Y-m-d', strtotime($lundi . ' +6 days'));
    $tous = rdv_dg_creneaux_plage($lundi, $fin);
    $par_jour = [];
    foreach ($tous as $c) {
        $par_jour[$c['date']][] = $c;
    }
    return [
        'lundi' => $lundi,
        'fin' => $fin,
        'par_jour' => $par_jour,
        'tous' => $tous,
    ];
}

function rdv_dg_creneau_est_libre(int $dispo_id, string $date, string $heure_debut): bool {
    $d = rdv_dg_dispo_par_id($dispo_id);
    if (!$d || !(int)$d['actif']) return false;
    if (($d['type_dispo'] ?? '') !== 'RECURRENT') return false;

    $heure_debut = rdv_dg_normalize_time($heure_debut);
    if ($heure_debut !== rdv_dg_normalize_time($d['heure_debut'])) return false;
    if ((int)date('N', strtotime($date)) !== (int)$d['jour_semaine']) return false;

    $st = get_db()->prepare("SELECT id FROM dg_dispo_exceptions WHERE disponibilite_id = ? AND date_exception = ?");
    $st->execute([$dispo_id, $date]);
    if ($st->fetchColumn()) return false;

    if ($date < date('Y-m-d')) return false;
    if ($date === date('Y-m-d') && $heure_debut <= date('H:i:s')) return false;

    return !rdv_dg_creneau_occupe($date, $heure_debut);
}

// ─── Demandes ──────────────────────────────────────────────

function rdv_dg_par_id(int $id): ?array {
    rdv_dg_assurer_schema();
    $st = get_db()->prepare("SELECT * FROM dg_rendezvous WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function rdv_dg_peut_voir(array $r): bool {
    if (rdv_dg_peut_gerer()) return true;
    $uid = (int)(user_id() ?? 0);
    return $uid > 0 && (int)($r['demandeur_utilisateur_id'] ?? 0) === $uid;
}

function rdv_dg_lister(array $filtres = [], int $limite = 50, int $offset = 0): array {
    rdv_dg_assurer_schema();
    $db = get_db();
    $sql = "SELECT * FROM dg_rendezvous WHERE 1=1";
    $params = [];

    if (!empty($filtres['statut'])) {
        $sql .= " AND statut = ?";
        $params[] = $filtres['statut'];
    }
    if (!empty($filtres['q'])) {
        $sql .= " AND (nom LIKE ? OR prenoms LIKE ? OR email LIKE ? OR reference LIKE ? OR objet LIKE ? OR organisation LIKE ?)";
        $q = '%' . $filtres['q'] . '%';
        $params = array_merge($params, [$q, $q, $q, $q, $q, $q]);
    }
    if (!empty($filtres['utilisateur_id'])) {
        $sql .= " AND demandeur_utilisateur_id = ?";
        $params[] = (int)$filtres['utilisateur_id'];
    }

    $count = $db->prepare("SELECT COUNT(*) FROM ($sql) t");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    $sql .= " ORDER BY
        CASE statut WHEN 'EN_ATTENTE' THEN 0 WHEN 'CONFIRME' THEN 1 ELSE 2 END,
        COALESCE(date_confirmee, date_souhaitee) DESC, cree_le DESC
        LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);
    return ['data' => $st->fetchAll(), 'total' => $total];
}

function rdv_dg_stats(?int $utilisateur_id = null): array {
    rdv_dg_assurer_schema();
    $db = get_db();
    $sql = "SELECT statut, COUNT(*) n FROM dg_rendezvous";
    $params = [];
    if ($utilisateur_id) {
        $sql .= " WHERE demandeur_utilisateur_id = ?";
        $params[] = $utilisateur_id;
    }
    $sql .= " GROUP BY statut";
    $st = $db->prepare($sql);
    $st->execute($params);
    $out = ['EN_ATTENTE' => 0, 'CONFIRME' => 0, 'TERMINE' => 0, 'REFUSE' => 0, 'ANNULE' => 0];
    foreach ($st->fetchAll() as $r) {
        $out[$r['statut']] = (int)$r['n'];
    }
    return $out;
}

function rdv_dg_valider_type(string $type, string $autre): array {
    if (!isset(RDV_DG_TYPES[$type])) {
        return ['ok' => false, 'message' => 'Type invalide.', 'type' => $type, 'autre' => null];
    }
    $autre = trim($autre);
    if ($type === 'AUTRE') {
        if ($autre === '') return ['ok' => false, 'message' => 'Précisez le type.', 'type' => $type, 'autre' => null];
        if (mb_strlen($autre) > 150) return ['ok' => false, 'message' => 'Type trop long (150 car. max).', 'type' => $type, 'autre' => null];
        return ['ok' => true, 'type' => $type, 'autre' => $autre];
    }
    return ['ok' => true, 'type' => $type, 'autre' => null];
}

/**
 * Crée une demande en réservant immédiatement le créneau choisi.
 * Le créneau disparaît des disponibilités tant que EN_ATTENTE ou CONFIRME.
 */
function rdv_dg_creer(array $data, string $source = 'INTERNE'): array {
    rdv_dg_assurer_schema();
    $tv = rdv_dg_valider_type((string)($data['type_rdv'] ?? ''), (string)($data['type_autre'] ?? ''));
    if (!$tv['ok']) return ['succes' => false, 'message' => $tv['message']];

    $nom = trim((string)($data['nom'] ?? ''));
    $prenoms = trim((string)($data['prenoms'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $objet = trim((string)($data['objet'] ?? ''));
    if ($nom === '' || $prenoms === '') return ['succes' => false, 'message' => 'Nom et prénoms obligatoires.'];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['succes' => false, 'message' => 'E-mail invalide.'];
    }
    if ($objet === '') return ['succes' => false, 'message' => 'Objet obligatoire.'];

    $uid = !empty($data['demandeur_utilisateur_id']) ? (int)$data['demandeur_utilisateur_id'] : null;
    $pid = !empty($data['demandeur_personnel_id']) ? (int)$data['demandeur_personnel_id'] : null;
    $existant = rdv_dg_rdv_en_cours_demandeur($uid, $email, $pid);
    if ($existant) {
        return [
            'succes' => false,
            'message' => 'Vous avez déjà un rendez-vous en cours (' . $existant['reference']
                . ', ' . rdv_dg_libelle_statut((string)$existant['statut'])
                . ' pour le ' . rdv_dg_fmt_date($existant['date_confirmee'])
                . ' à ' . rdv_dg_fmt_heure($existant['heure_debut'])
                . '). Veuillez attendre son traitement ou l\'annuler avant d\'en demander un autre.',
        ];
    }

    $dispo_id = (int)($data['disponibilite_id'] ?? 0);
    $date = trim((string)($data['date_confirmee'] ?? $data['creneau_date'] ?? ''));
    $heure = rdv_dg_normalize_time((string)($data['heure_debut'] ?? $data['creneau_debut'] ?? ''));

    if ($dispo_id <= 0 || $date === '' || $heure === '') {
        return ['succes' => false, 'message' => 'Veuillez sélectionner un créneau disponible.'];
    }
    if (!rdv_dg_creneau_est_libre($dispo_id, $date, $heure)) {
        return ['succes' => false, 'message' => 'Ce créneau n\'est plus disponible. Choisissez-en un autre.'];
    }

    $dispo = rdv_dg_dispo_par_id($dispo_id);
    $fin = rdv_dg_normalize_time($dispo['heure_fin']);
    $duree = (int)$dispo['duree_minutes'];
    $lieu = trim((string)($data['lieu'] ?? '')) ?: 'CRICT — Direction Générale, Conakry';

    $ref = rdv_dg_generer_reference();
    $db = get_db();

    try {
        $db->beginTransaction();
        // Verrou anti double-réservation
        if (rdv_dg_creneau_occupe($date, $heure)) {
            $db->rollBack();
            return ['succes' => false, 'message' => 'Ce créneau vient d\'être pris. Choisissez-en un autre.'];
        }

        $db->prepare("
            INSERT INTO dg_rendezvous (
                reference, source, type_rdv, type_autre,
                demandeur_utilisateur_id, demandeur_personnel_id,
                nom, prenoms, email, telephone, organisation, fonction,
                objet, date_souhaitee, creneau_souhaite, note_demandeur, piece_jointe,
                disponibilite_id, date_confirmee, heure_debut, heure_fin, duree_minutes, lieu, statut
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'EN_ATTENTE')
        ")->execute([
            $ref,
            $source === 'PUBLIC' ? 'PUBLIC' : 'INTERNE',
            $tv['type'],
            $tv['autre'],
            !empty($data['demandeur_utilisateur_id']) ? (int)$data['demandeur_utilisateur_id'] : null,
            !empty($data['demandeur_personnel_id']) ? (int)$data['demandeur_personnel_id'] : null,
            mb_strtoupper($nom),
            $prenoms,
            $email,
            trim((string)($data['telephone'] ?? '')) ?: null,
            trim((string)($data['organisation'] ?? '')) ?: null,
            trim((string)($data['fonction'] ?? '')) ?: null,
            $objet,
            $date,
            $heure,
            trim((string)($data['note_demandeur'] ?? '')) ?: null,
            !empty($data['piece_jointe']) ? $data['piece_jointe'] : null,
            $dispo_id,
            $date,
            $heure,
            $fin,
            $duree,
            $lieu,
        ]);
        $id = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT RDV] creer : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Impossible d\'enregistrer la demande.'];
    }

    if (function_exists('log_activite') && user_id()) {
        log_activite((int)user_id(), RDV_DG_MODULE, 'CREATION', "Demande RDV $ref");
    }

    rdv_dg_notifier_nouvelle_demande($id);
    return ['succes' => true, 'id' => $id, 'reference' => $ref];
}

function rdv_dg_confirmer(int $id, array $data = []): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $r = rdv_dg_par_id($id);
    if (!$r) return ['succes' => false, 'message' => 'Demande introuvable.'];
    if ($r['statut'] !== 'EN_ATTENTE') {
        return ['succes' => false, 'message' => 'Cette demande ne peut plus être confirmée.'];
    }
    if (empty($r['date_confirmee']) || empty($r['heure_debut'])) {
        return ['succes' => false, 'message' => 'Aucun créneau réservé sur cette demande.'];
    }

    $lieu = trim((string)($data['lieu'] ?? $r['lieu'] ?? '')) ?: 'CRICT — Direction Générale, Conakry';
    $commentaire = trim((string)($data['commentaire_gestion'] ?? ''));

    get_db()->prepare("
        UPDATE dg_rendezvous SET
            statut = 'CONFIRME', lieu = ?,
            commentaire_gestion = COALESCE(NULLIF(?, ''), commentaire_gestion),
            motif_refus = NULL, planifie_par = ?, planifie_le = NOW(),
            rappel_j1_envoye_le = NULL
        WHERE id = ?
    ")->execute([$lieu, $commentaire, user_id(), $id]);

    if (function_exists('log_activite')) {
        log_activite((int)user_id(), RDV_DG_MODULE, 'CONFIRMATION', "RDV {$r['reference']} confirmé");
    }
    rdv_dg_notifier_confirmation($id);
    return ['succes' => true, 'message' => 'Rendez-vous confirmé. Le demandeur a été notifié.'];
}

function rdv_dg_refuser(int $id, string $motif): array {
    if (!rdv_dg_peut_gerer()) return ['succes' => false, 'message' => 'Action non autorisée.'];
    $r = rdv_dg_par_id($id);
    if (!$r) return ['succes' => false, 'message' => 'Demande introuvable.'];
    if (!in_array($r['statut'], ['EN_ATTENTE', 'CONFIRME'], true)) {
        return ['succes' => false, 'message' => 'Cette demande ne peut plus être refusée.'];
    }
    $motif = trim($motif);
    if ($motif === '') return ['succes' => false, 'message' => 'Indiquez un motif de refus.'];

    // Libère le créneau (statut REFUSE → plus compté comme occupé)
    get_db()->prepare("
        UPDATE dg_rendezvous SET statut = 'REFUSE', motif_refus = ?,
            planifie_par = ?, planifie_le = NOW()
        WHERE id = ?
    ")->execute([$motif, user_id(), $id]);

    if (function_exists('log_activite')) {
        log_activite((int)user_id(), RDV_DG_MODULE, 'REFUS', "RDV {$r['reference']} refusé");
    }
    rdv_dg_notifier_refus($id);
    return ['succes' => true, 'message' => 'Demande refusée. Le créneau est de nouveau disponible.'];
}

function rdv_dg_annuler(int $id, string $motif = ''): array {
    $r = rdv_dg_par_id($id);
    if (!$r) return ['succes' => false, 'message' => 'Demande introuvable.'];
    $uid = (int)(user_id() ?? 0);
    $est_auteur = $uid > 0 && (int)($r['demandeur_utilisateur_id'] ?? 0) === $uid;
    if (!rdv_dg_peut_gerer() && !($est_auteur && $r['statut'] === 'EN_ATTENTE')) {
        return ['succes' => false, 'message' => 'Action non autorisée.'];
    }
    if (!in_array($r['statut'], ['EN_ATTENTE', 'CONFIRME'], true)) {
        return ['succes' => false, 'message' => 'Cette demande ne peut plus être annulée.'];
    }
    $motif = trim($motif);
    if ($motif === '') {
        return ['succes' => false, 'message' => 'Indiquez le motif de l\'annulation.'];
    }

    get_db()->prepare("
        UPDATE dg_rendezvous SET statut = 'ANNULE',
            motif_annulation = ?,
            planifie_par = ?, planifie_le = NOW()
        WHERE id = ?
    ")->execute([$motif, $uid ?: null, $id]);

    if (function_exists('log_activite') && $uid) {
        log_activite($uid, RDV_DG_MODULE, 'ANNULATION', "RDV {$r['reference']} annulé");
    }
    rdv_dg_notifier_annulation($id);
    return ['succes' => true, 'message' => 'Rendez-vous annulé. Le créneau est de nouveau disponible.'];
}

function rdv_dg_marquer_termines(): int {
    rdv_dg_assurer_schema();
    $st = get_db()->prepare("
        UPDATE dg_rendezvous SET statut = 'TERMINE'
        WHERE statut = 'CONFIRME'
          AND date_confirmee IS NOT NULL
          AND CONCAT(date_confirmee, ' ', COALESCE(heure_fin, '23:59:59')) < NOW()
    ");
    $st->execute();
    return $st->rowCount();
}

function rdv_dg_upload_piece(array $fichier): array {
    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['succes' => true, 'chemin' => null];
    }
    if (($fichier['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['succes' => false, 'message' => 'Erreur lors du téléversement.'];
    }
    if (($fichier['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['succes' => false, 'message' => 'Fichier trop volumineux (max 5 Mo).'];
    }
    $ext = strtolower(pathinfo($fichier['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
        return ['succes' => false, 'message' => 'Formats acceptés : PDF, JPG, PNG, DOC, DOCX.'];
    }
    $dir = UPLOAD_DIR . RDV_DG_UPLOAD_SUBDIR . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $stock = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($fichier['tmp_name'], $dir . $stock)) {
        return ['succes' => false, 'message' => 'Impossible d\'enregistrer le fichier.'];
    }
    return ['succes' => true, 'chemin' => 'uploads/' . RDV_DG_UPLOAD_SUBDIR . '/' . $stock];
}

// ─── Notifications internes ────────────────────────────────

function rdv_dg_notif_creer(int $utilisateur_id, string $titre, string $message, ?int $rdv_id = null): void {
    if ($utilisateur_id <= 0) return;
    try {
        get_db()->prepare("
            INSERT INTO dg_rdv_notifications (utilisateur_id, rendezvous_id, titre, message)
            VALUES (?,?,?,?)
        ")->execute([$utilisateur_id, $rdv_id, $titre, $message]);
    } catch (Throwable $e) {}
}

function rdv_dg_notifs_non_lues(int $utilisateur_id): int {
    rdv_dg_assurer_schema();
    $st = get_db()->prepare("SELECT COUNT(*) FROM dg_rdv_notifications WHERE utilisateur_id = ? AND lu = 0");
    $st->execute([$utilisateur_id]);
    return (int)$st->fetchColumn();
}

function rdv_dg_notifs_lister(int $utilisateur_id, int $limite = 20): array {
    rdv_dg_assurer_schema();
    $st = get_db()->prepare("
        SELECT * FROM dg_rdv_notifications
        WHERE utilisateur_id = ?
        ORDER BY cree_le DESC LIMIT ?
    ");
    $st->execute([$utilisateur_id, $limite]);
    return $st->fetchAll();
}

function rdv_dg_notif_marquer_lu(int $id, int $utilisateur_id): void {
    get_db()->prepare("UPDATE dg_rdv_notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?")
        ->execute([$id, $utilisateur_id]);
}

function rdv_dg_notif_tout_lu(int $utilisateur_id): void {
    get_db()->prepare("UPDATE dg_rdv_notifications SET lu = 1 WHERE utilisateur_id = ? AND lu = 0")
        ->execute([$utilisateur_id]);
}

function rdv_dg_ids_gestionnaires(): array {
    $ids = [];
    try {
        $sql = "
            SELECT DISTINCT u.id FROM utilisateurs u
            JOIN groupes_utilisateurs g ON g.id = u.groupe_id
            WHERE u.statut = 'ACTIF' AND (
                g.code IN ('SUPER_ADMIN','DG')
                OR EXISTS (
                    SELECT 1 FROM groupes_permissions gp
                    JOIN permissions p ON p.id = gp.permission_id
                    JOIN modules m ON m.id = p.module_id
                    WHERE gp.groupe_id = g.id AND gp.accorde = 1
                      AND m.code = 'RENDEZVOUS_DG' AND p.action = 'VALIDER'
                )
            )";
        foreach (get_db()->query($sql) as $row) {
            $ids[] = (int)$row['id'];
        }
    } catch (Throwable $e) {}
    return $ids;
}

function rdv_dg_emails_gestionnaires(): array {
    $emails = [];
    try {
        $sql = "
            SELECT DISTINCT u.email, CONCAT(u.prenoms,' ',u.nom) AS nom
            FROM utilisateurs u
            JOIN groupes_utilisateurs g ON g.id = u.groupe_id
            WHERE u.statut = 'ACTIF' AND u.email != '' AND (
                g.code IN ('SUPER_ADMIN','DG')
                OR EXISTS (
                    SELECT 1 FROM groupes_permissions gp
                    JOIN permissions p ON p.id = gp.permission_id
                    JOIN modules m ON m.id = p.module_id
                    WHERE gp.groupe_id = g.id AND gp.accorde = 1
                      AND m.code = 'RENDEZVOUS_DG' AND p.action = 'VALIDER'
                )
            )";
        foreach (get_db()->query($sql) as $row) {
            $emails[] = ['email' => $row['email'], 'nom' => trim($row['nom'])];
        }
    } catch (Throwable $e) {}
    return $emails;
}

function rdv_dg_mail_charger(): void {
    if (!function_exists('envoyer_email')) {
        require_once APP_ROOT . '/includes/mailer.php';
    }
}

/**
 * Gabarit HTML des e-mails du module (styles inline, compatibles messageries).
 * $badge : ['label' => ..., 'bg' => ..., 'color' => ...] ou null.
 */
function rdv_dg_email_gabarit(string $titre, string $contenu, ?array $badge = null): string {
    $badge_html = '';
    if ($badge) {
        $badge_html = '<tr><td style="padding:0 32px;">'
            . '<span style="display:inline-block;padding:6px 16px;border-radius:20px;'
            . 'font-size:12px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;'
            . 'background:' . $badge['bg'] . ';color:' . $badge['color'] . ';">'
            . htmlspecialchars($badge['label']) . '</span></td></tr>'
            . '<tr><td style="height:14px;"></td></tr>';
    }
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
        . '<body style="margin:0;padding:0;background:#eef2f9;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f9;padding:28px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" '
        . 'style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;'
        . 'font-family:\'Segoe UI\',Arial,sans-serif;box-shadow:0 12px 40px rgba(3,20,74,.12);">'
        // En-tête bleu
        . '<tr><td style="background:#0a2158;background:linear-gradient(135deg,#0a2158,#1347a8);padding:26px 32px;">'
        . '<div style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:.5px;">CRICT</div>'
        . '<div style="font-size:12px;color:#c7d7f5;margin-top:4px;">Centre de Recherche en Informatique et Cyber-Technologie</div>'
        . '<div style="font-size:11px;color:#8fb0e8;margin-top:2px;">Direction Générale — Rendez-vous &amp; visites</div>'
        . '</td></tr>'
        . '<tr><td style="height:24px;"></td></tr>'
        . $badge_html
        . '<tr><td style="padding:0 32px;">'
        . '<div style="font-size:17px;font-weight:800;color:#0a2158;margin-bottom:14px;">' . htmlspecialchars($titre) . '</div>'
        . '<div style="font-size:14px;color:#16213e;line-height:1.65;">' . $contenu . '</div>'
        . '</td></tr>'
        . '<tr><td style="height:26px;"></td></tr>'
        // Pied de page
        . '<tr><td style="background:#f6f8fc;border-top:1px solid #e4e9f2;padding:18px 32px;">'
        . '<div style="font-size:12px;color:#64748b;line-height:1.6;">'
        . 'Assistante du Directeur Général — CRICT<br>'
        . 'Tél. : ' . RDV_DG_TEL_ASSISTANTE . '<br>'
        . 'Conakry, République de Guinée · www.crict.edu.gn<br>'
        . '<span style="color:#94a3b8;">Ce message est envoyé automatiquement, merci de ne pas y répondre.</span>'
        . '</div></td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Bloc récapitulatif (date / heure / lieu / référence) inséré dans les e-mails. */
function rdv_dg_email_bloc_details(array $lignes): string {
    $rows = '';
    foreach ($lignes as $label => $valeur) {
        if ($valeur === '' || $valeur === null) continue;
        $rows .= '<tr>'
            . '<td style="padding:9px 14px;font-size:12px;font-weight:700;color:#64748b;'
            . 'text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;vertical-align:top;">'
            . htmlspecialchars($label) . '</td>'
            . '<td style="padding:9px 14px;font-size:14px;font-weight:600;color:#0a2158;">'
            . htmlspecialchars($valeur) . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        . 'style="background:#eef4ff;border:1px solid #dbe7ff;border-radius:12px;margin:16px 0;">'
        . $rows . '</table>';
}

function rdv_dg_notifier_gestionnaires_interne(string $titre, string $message, ?int $rdv_id = null): void {
    foreach (rdv_dg_ids_gestionnaires() as $uid) {
        rdv_dg_notif_creer($uid, $titre, $message, $rdv_id);
    }
}

function rdv_dg_envoyer_mail_gestionnaires(string $sujet, string $titre, string $contenu, ?array $badge = null): void {
    rdv_dg_mail_charger();
    $html = rdv_dg_email_gabarit($titre, $contenu, $badge);
    foreach (rdv_dg_emails_gestionnaires() as $dest) {
        @envoyer_email($dest['email'], $dest['nom'], $sujet, $html);
    }
}

function rdv_dg_notifier_nouvelle_demande(int $id): void {
    $r = rdv_dg_par_id($id);
    if (!$r) return;

    $titre = 'Nouvelle demande ' . $r['reference'];
    $msg = trim($r['prenoms'] . ' ' . $r['nom']) . ' — '
        . rdv_dg_fmt_date($r['date_confirmee']) . ' '
        . rdv_dg_fmt_heure($r['heure_debut']);
    rdv_dg_notifier_gestionnaires_interne($titre, $msg, $id);

    $lien = rtrim(APP_URL, '/') . '/modules/rendezvous_dg/voir.php?id=' . $id;
    $contenu_gest = '<p style="margin:0 0 6px;">Une nouvelle demande de rendez-vous vient d\'être enregistrée.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Demandeur' => trim($r['prenoms'] . ' ' . $r['nom']),
            'Organisation' => (string)($r['organisation'] ?? ''),
            'Créneau' => rdv_dg_fmt_date($r['date_confirmee']) . ' à ' . rdv_dg_fmt_heure($r['heure_debut']),
            'Objet' => $r['objet'],
        ])
        . '<p style="margin:14px 0 0;"><a href="' . htmlspecialchars($lien) . '" '
        . 'style="display:inline-block;background:#1347a8;color:#ffffff;text-decoration:none;'
        . 'font-weight:700;font-size:14px;padding:11px 22px;border-radius:10px;">Traiter la demande</a></p>';
    rdv_dg_envoyer_mail_gestionnaires(
        '[CRICT] ' . $titre,
        'Nouvelle demande de rendez-vous',
        $contenu_gest
    );

    $contenu2 = '<p style="margin:0 0 6px;">Bonjour <strong>' . htmlspecialchars($r['prenoms']) . '</strong>,</p>'
        . '<p style="margin:0 0 6px;">Votre demande de rendez-vous auprès du Directeur Général est bien '
        . '<strong>enregistrée</strong>. Le créneau ci-dessous vous est réservé en attendant le traitement '
        . 'de votre demande.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Date' => rdv_dg_fmt_date($r['date_confirmee']),
            'Heure' => rdv_dg_fmt_heure($r['heure_debut']),
        ])
        . '<p style="margin:0;">Vous recevrez un e-mail dès que votre demande sera acceptée ou refusée. '
        . 'Aucune autre démarche n\'est nécessaire de votre part.</p>';
    $html2 = rdv_dg_email_gabarit('Accusé de réception de votre demande', $contenu2);
    @envoyer_email($r['email'], trim($r['prenoms'] . ' ' . $r['nom']),
        '[CRICT] Accusé de réception — ' . $r['reference'], $html2);

    // Notification interne si personnel
    if (!empty($r['demandeur_utilisateur_id'])) {
        rdv_dg_notif_creer(
            (int)$r['demandeur_utilisateur_id'],
            'Demande envoyée — ' . $r['reference'],
            'Votre demande pour le ' . rdv_dg_fmt_date($r['date_confirmee'])
                . ' à ' . rdv_dg_fmt_heure($r['heure_debut']) . ' est en attente de traitement.',
            $id
        );
    }
}

function rdv_dg_notifier_confirmation(int $id): void {
    $r = rdv_dg_par_id($id);
    if (!$r) return;
    rdv_dg_mail_charger();
    $nom_complet = trim($r['prenoms'] . ' ' . $r['nom']);
    $contenu = '<p style="margin:0 0 6px;">Bonjour <strong>' . htmlspecialchars($r['prenoms']) . '</strong>,</p>'
        . '<p style="margin:0 0 6px;">Votre demande de rendez-vous auprès du Directeur Général a été '
        . '<strong>acceptée</strong>. Le rendez-vous ci-dessous est désormais confirmé.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Date' => rdv_dg_fmt_date($r['date_confirmee']),
            'Heure' => rdv_dg_fmt_heure($r['heure_debut']) . ' – ' . rdv_dg_fmt_heure($r['heure_fin']),
            'Lieu' => (string)($r['lieu'] ?: 'Direction Générale CRICT, Conakry'),
            'Objet' => $r['objet'],
        ])
        . '<p style="margin:0 0 6px;">Vous êtes prié(e) de vous présenter à l\'heure indiquée. '
        . '<strong>Aucun retard ne sera toléré.</strong> '
        . 'En cas d\'empêchement, veuillez prévenir ' . rdv_dg_email_contact_assistante()
        . ' dans les meilleurs délais.</p>'
        . '<p style="margin:0;color:#64748b;font-size:13px;">Ce message tient lieu de confirmation officielle.</p>';
    $html = rdv_dg_email_gabarit('Rendez-vous accepté', $contenu);
    @envoyer_email($r['email'], $nom_complet,
        '[CRICT] Rendez-vous accepté le ' . rdv_dg_fmt_date($r['date_confirmee'])
            . ' (' . $r['reference'] . ')', $html);

    rdv_dg_notifier_gestionnaires_interne(
        'Rendez-vous confirmé — ' . $r['reference'],
        trim($r['prenoms'] . ' ' . $r['nom']) . ' confirmé pour le '
            . rdv_dg_fmt_date($r['date_confirmee']) . ' à ' . rdv_dg_fmt_heure($r['heure_debut']),
        $id
    );
    rdv_dg_envoyer_mail_gestionnaires(
        '[CRICT] Rendez-vous confirmé — ' . $r['reference'],
        'Rendez-vous confirmé',
        '<p style="margin:0 0 6px;">Le rendez-vous ci-dessous a été <strong>confirmé</strong>.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Demandeur' => $nom_complet,
            'Organisation' => (string)($r['organisation'] ?? ''),
            'Date' => rdv_dg_fmt_date($r['date_confirmee']),
            'Heure' => rdv_dg_fmt_heure($r['heure_debut']) . ' – ' . rdv_dg_fmt_heure($r['heure_fin']),
            'Lieu' => (string)($r['lieu'] ?: 'Direction Générale CRICT, Conakry'),
        ]),
    );

    if (!empty($r['demandeur_utilisateur_id'])) {
        rdv_dg_notif_creer(
            (int)$r['demandeur_utilisateur_id'],
            'Rendez-vous confirmé — ' . $r['reference'],
            'Confirmé le ' . rdv_dg_fmt_date($r['date_confirmee'])
                . ' à ' . rdv_dg_fmt_heure($r['heure_debut'])
                . '. Un e-mail de confirmation vous a été envoyé.',
            $id
        );
    }
}

function rdv_dg_notifier_refus(int $id): void {
    $r = rdv_dg_par_id($id);
    if (!$r) return;
    rdv_dg_mail_charger();
    $motif = trim((string)($r['motif_refus'] ?? ''));
    $contenu = '<p style="margin:0 0 6px;">Bonjour <strong>' . htmlspecialchars($r['prenoms']) . '</strong>,</p>'
        . '<p style="margin:0 0 6px;">Nous vous informons que votre demande de rendez-vous '
        . '<strong>' . htmlspecialchars($r['reference']) . '</strong> auprès du Directeur Général '
        . 'n\'a pas pu être acceptée.</p>'
        . ($motif !== ''
            ? '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;'
              . 'padding:14px 16px;margin:14px 0;">'
              . '<div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;'
              . 'letter-spacing:.4px;margin-bottom:5px;">Motif</div>'
              . '<div style="font-size:14px;color:#7f1d1d;line-height:1.6;">'
              . nl2br(htmlspecialchars($motif)) . '</div></div>'
            : '')
        . '<p style="margin:0;">Le créneau que vous aviez choisi est de nouveau disponible. '
        . 'Vous pouvez soumettre une nouvelle demande sur un autre créneau si vous le souhaitez.</p>';
    $html = rdv_dg_email_gabarit('Demande de rendez-vous non acceptée', $contenu);
    @envoyer_email($r['email'], trim($r['prenoms'] . ' ' . $r['nom']),
        '[CRICT] Demande refusée — ' . $r['reference'], $html);

    rdv_dg_notifier_gestionnaires_interne(
        'Demande refusée — ' . $r['reference'],
        trim($r['prenoms'] . ' ' . $r['nom']) . ' · créneau refusé pour le '
            . rdv_dg_fmt_date($r['date_confirmee']) . ' à ' . rdv_dg_fmt_heure($r['heure_debut']),
        $id
    );
    rdv_dg_envoyer_mail_gestionnaires(
        '[CRICT] Demande refusée — ' . $r['reference'],
        'Demande refusée',
        '<p style="margin:0 0 6px;">La demande ci-dessous a été <strong>refusée</strong>.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Demandeur' => trim($r['prenoms'] . ' ' . $r['nom']),
            'Organisation' => (string)($r['organisation'] ?? ''),
            'Date' => rdv_dg_fmt_date($r['date_confirmee']),
            'Heure' => rdv_dg_fmt_heure($r['heure_debut']),
            'Motif' => $motif !== '' ? $motif : '—',
        ])
    );

    if (!empty($r['demandeur_utilisateur_id'])) {
        rdv_dg_notif_creer(
            (int)$r['demandeur_utilisateur_id'],
            'Demande refusée — ' . $r['reference'],
            (string)($r['motif_refus'] ?? 'Votre demande a été refusée.'),
            $id
        );
    }
}

function rdv_dg_notifier_annulation(int $id): void {
    $r = rdv_dg_par_id($id);
    if (!$r) return;
    rdv_dg_mail_charger();
    $motif = trim((string)($r['motif_annulation'] ?? ''));
    $contenu = '<p style="margin:0 0 6px;">Bonjour <strong>' . htmlspecialchars($r['prenoms']) . '</strong>,</p>'
        . '<p style="margin:0 0 6px;">Nous vous informons que le rendez-vous ci-dessous a été <strong>annulé</strong>.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Date' => $r['date_confirmee'] ? rdv_dg_fmt_date($r['date_confirmee']) : '',
            'Heure' => $r['heure_debut'] ? rdv_dg_fmt_heure($r['heure_debut']) : '',
        ])
        . ($motif !== ''
            ? '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;'
              . 'padding:14px 16px;margin:14px 0;">'
              . '<div style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'
              . 'letter-spacing:.4px;margin-bottom:5px;">Motif de l\'annulation</div>'
              . '<div style="font-size:14px;color:#334155;line-height:1.6;">'
              . nl2br(htmlspecialchars($motif)) . '</div></div>'
            : '')
        . '<p style="margin:0;">Le créneau est de nouveau disponible. Vous pouvez soumettre une '
        . 'nouvelle demande si vous le souhaitez.</p>';
    $html = rdv_dg_email_gabarit('Rendez-vous annulé', $contenu);
    @envoyer_email($r['email'], trim($r['prenoms'] . ' ' . $r['nom']),
        '[CRICT] Rendez-vous annulé — ' . $r['reference'], $html);

    rdv_dg_notifier_gestionnaires_interne(
        'Rendez-vous annulé — ' . $r['reference'],
        trim($r['prenoms'] . ' ' . $r['nom']) . ' · créneau annulé du '
            . rdv_dg_fmt_date((string)$r['date_confirmee']) . ' à ' . rdv_dg_fmt_heure((string)$r['heure_debut'])
            . ($motif !== '' ? ' · ' . $motif : ''),
        $id
    );
    rdv_dg_envoyer_mail_gestionnaires(
        '[CRICT] Rendez-vous annulé — ' . $r['reference'],
        'Rendez-vous annulé',
        '<p style="margin:0 0 6px;">Le rendez-vous ci-dessous a été <strong>annulé</strong>.</p>'
        . rdv_dg_email_bloc_details([
            'Référence' => $r['reference'],
            'Demandeur' => trim($r['prenoms'] . ' ' . $r['nom']),
            'Organisation' => (string)($r['organisation'] ?? ''),
            'Date' => $r['date_confirmee'] ? rdv_dg_fmt_date($r['date_confirmee']) : '',
            'Heure' => $r['heure_debut'] ? rdv_dg_fmt_heure($r['heure_debut']) : '',
            'Motif' => $motif !== '' ? $motif : '—',
        ])
    );

    if (!empty($r['demandeur_utilisateur_id'])) {
        rdv_dg_notif_creer(
            (int)$r['demandeur_utilisateur_id'],
            'Rendez-vous annulé — ' . $r['reference'],
            $motif !== '' ? $motif : 'Le créneau a été libéré.',
            $id
        );
    }
}

function rdv_dg_envoyer_rappels_j1(): int {
    rdv_dg_assurer_schema();
    rdv_dg_mail_charger();
    $demain = date('Y-m-d', strtotime('+1 day'));
    $st = get_db()->prepare("
        SELECT * FROM dg_rendezvous
        WHERE statut = 'CONFIRME' AND date_confirmee = ? AND rappel_j1_envoye_le IS NULL
    ");
    $st->execute([$demain]);
    $n = 0;
    foreach ($st->fetchAll() as $r) {
        $contenu = '<p style="margin:0 0 6px;">Bonjour <strong>' . htmlspecialchars($r['prenoms']) . '</strong>,</p>'
            . '<p style="margin:0 0 6px;">Nous vous rappelons que votre rendez-vous avec le '
            . 'Directeur Général du CRICT est prévu <strong>demain</strong>.</p>'
            . rdv_dg_email_bloc_details([
                'Référence' => $r['reference'],
                'Date' => rdv_dg_fmt_date($r['date_confirmee']),
                'Heure' => rdv_dg_fmt_heure($r['heure_debut']) . ' – ' . rdv_dg_fmt_heure($r['heure_fin']),
                'Lieu' => (string)($r['lieu'] ?: 'Direction Générale CRICT, Conakry'),
            ])
            . '<p style="margin:0;">Merci de vous présenter à l\'heure indiquée. '
            . 'En cas d\'empêchement, prévenez ' . rdv_dg_email_contact_assistante() . '.</p>';
        $html = rdv_dg_email_gabarit('Rappel — rendez-vous demain', $contenu);
        $res = @envoyer_email($r['email'], trim($r['prenoms'] . ' ' . $r['nom']),
            '[CRICT] Rappel — rendez-vous demain à ' . rdv_dg_fmt_heure($r['heure_debut'])
                . ' (' . $r['reference'] . ')', $html);
        if (!empty($res['succes'])) {
            get_db()->prepare("UPDATE dg_rendezvous SET rappel_j1_envoye_le = NOW() WHERE id = ?")
                ->execute([(int)$r['id']]);

            rdv_dg_notifier_gestionnaires_interne(
                'Rappel J-1 — ' . $r['reference'],
                trim($r['prenoms'] . ' ' . $r['nom']) . ' attendu demain à '
                    . rdv_dg_fmt_heure($r['heure_debut']),
                (int)$r['id']
            );
            rdv_dg_envoyer_mail_gestionnaires(
                '[CRICT] Rappel J-1 — ' . $r['reference'],
                'Récapitulatif — rendez-vous de demain',
                '<p style="margin:0 0 6px;">Rappel : le rendez-vous ci-dessous est prévu <strong>demain</strong>.</p>'
                . rdv_dg_email_bloc_details([
                    'Référence' => $r['reference'],
                    'Demandeur' => trim($r['prenoms'] . ' ' . $r['nom']),
                    'Organisation' => (string)($r['organisation'] ?? ''),
                    'Date' => rdv_dg_fmt_date($r['date_confirmee']),
                    'Heure' => rdv_dg_fmt_heure($r['heure_debut']) . ' – ' . rdv_dg_fmt_heure($r['heure_fin']),
                    'Lieu' => (string)($r['lieu'] ?: 'Direction Générale CRICT, Conakry'),
                ])
            );

            if (!empty($r['demandeur_utilisateur_id'])) {
                rdv_dg_notif_creer(
                    (int)$r['demandeur_utilisateur_id'],
                    'Rappel J-1 — ' . $r['reference'],
                    'Rendez-vous demain à ' . rdv_dg_fmt_heure($r['heure_debut']),
                    (int)$r['id']
                );
            }
            $n++;
        }
    }
    return $n;
}
