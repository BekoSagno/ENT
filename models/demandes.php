<?php
// ============================================================
// models/demandes.php — Demandes RH (congés, absences, missions)
// Indépendant de models/conges.php (calculs de jours inclus ici).
// ============================================================

require_once __DIR__ . '/personnel.php';

const DEMANDES_CATEGORIES = ['CONGE', 'ABSENCE', 'MISSION'];

// Quota annuel unique de repos (en jours ouvrés), identique pour tous les employés.
// Congés + absences se décomptent sur ce quota ; les missions n'y entrent pas.
const DEMANDES_QUOTA_ANNUEL = 30;

const DEMANDES_TYPES_CONGE = [
    'ANNUEL'       => 'Congé annuel',
    'MALADIE'      => 'Congé maladie',
    'MATERNITE'    => 'Congé maternité',
    'EXCEPTIONNEL' => 'Congé exceptionnel',
    'AUTRE'        => 'Autre (saisie libre)',
];

const DEMANDES_TYPES_ABSENCE = [
    'MEDICAL'   => 'Rendez-vous médical',
    'FAMILLE'   => 'Urgence familiale',
    'PERSONNEL' => 'Permission personnelle',
    'AUTRE'     => 'Autre (saisie libre)',
];

// Prochaine référence officielle à attribuer : 077/CRICT/DG/AAAA
const DEMANDES_OM_REF_DEPART = 77;
const DEMANDES_OM_QR_URL     = 'https://www.crict.edu.gn/';

// ── Calculs de durée (ex-conges.php) — autonomes pour déploiement isolé ──

/** Jours calendaires (bornes incluses). */
if (!function_exists('conges_nb_jours')) {
    function conges_nb_jours(string $debut, string $fin): float {
        $d = strtotime($debut);
        $f = strtotime($fin);
        if (!$d || !$f || $f < $d) return 0;
        return (float)(floor(($f - $d) / 86400) + 1);
    }
}

/** Jours ouvrés lundi→vendredi (week-ends exclus, bornes incluses). Jours fériés non déduits. */
if (!function_exists('demandes_nb_jours_ouvres')) {
    function demandes_nb_jours_ouvres(string $debut, string $fin): float {
        $d = strtotime($debut);
        $f = strtotime($fin);
        if (!$d || !$f || $f < $d) return 0;
        $n = 0;
        for ($t = $d; $t <= $f; $t += 86400) {
            if ((int)date('N', $t) <= 5) $n++;
        }
        return (float)$n;
    }
}

/**
 * Nettoie le HTML provenant de l'éditeur riche de l'objet de mission.
 * Seules les balises de mise en forme utiles au document officiel sont gardées.
 */
function demandes_nettoyer_html_riche(string $html): string {
    $html = trim($html);
    if ($html === '') return '';

    if (!class_exists('DOMDocument')) {
        $clean = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote>');
        return (string)preg_replace('/<([a-z0-9]+)\b[^>]*>/i', '<$1>', $clean);
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div id="dm-rich-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $dom->getElementById('dm-rich-root');
    if (!$root) return '';

    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'span'];
    $walk = function (DOMNode $parent) use (&$walk, $allowed): void {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
                $node = $next;
                continue;
            }

            if ($node->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($node->nodeName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }
                if (!in_array($tag, $allowed, true)) {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;
                    continue;
                }

                $style = '';
                if ($node instanceof DOMElement) {
                    $rawStyle = strtolower($node->getAttribute('style'));
                    foreach (explode(';', $rawStyle) as $declaration) {
                        [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
                        $property = trim($property);
                        $value = trim($value);
                        if ($property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) {
                            $style .= 'text-align:' . $value . ';';
                        } elseif ($property === 'font-style' && $value === 'italic') {
                            $style .= 'font-style:italic;';
                        } elseif ($property === 'font-weight' && in_array($value, ['bold', '600', '700'], true)) {
                            $style .= 'font-weight:bold;';
                        } elseif ($property === 'text-decoration' && str_contains($value, 'underline')) {
                            $style .= 'text-decoration:underline;';
                        }
                    }

                    while ($node->attributes->length > 0) {
                        $node->removeAttributeNode($node->attributes->item(0));
                    }
                    if ($style !== '') $node->setAttribute('style', $style);
                }
                $walk($node);
            }
            $node = $next;
        }
    };
    $walk($root);

    $clean = '';
    foreach ($root->childNodes as $child) {
        $clean .= $dom->saveHTML($child);
    }
    return trim($clean);
}

function demandes_texte_depuis_html(string $html): string {
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

// Solde de repos calculé dynamiquement à partir des demandes approuvées
// (congés + absences) de l'année. Aucun compteur à maintenir.
function demandes_solde_repos(int $personnel_id, int $annee): array {
    demandes_assurer_schema();
    $stmt = get_db()->prepare("
        SELECT COALESCE(SUM(nombre_jours), 0)
        FROM demandes_conges
        WHERE personnel_id = ?
          AND statut = 'APPROUVE'
          AND categorie IN ('CONGE', 'ABSENCE')
          AND YEAR(date_debut) = ?
    ");
    $stmt->execute([$personnel_id, $annee]);
    $pris = (float)$stmt->fetchColumn();
    return [
        'droit'    => (float)DEMANDES_QUOTA_ANNUEL,
        'pris'     => $pris,
        'restants' => max(0.0, (float)DEMANDES_QUOTA_ANNUEL - $pris),
    ];
}

function demandes_assurer_schema(): void {
    $db = get_db();
    try {
        $db->query("SELECT categorie FROM demandes_conges LIMIT 1");
    } catch (Throwable $e) {
        try {
            $db->exec("ALTER TABLE demandes_conges
                ADD COLUMN categorie enum('CONGE','ABSENCE','MISSION') NOT NULL DEFAULT 'CONGE' AFTER personnel_id");
        } catch (Throwable $e2) {}
        try {
            $db->exec("ALTER TABLE demandes_conges ADD COLUMN reference varchar(30) DEFAULT NULL AFTER categorie");
        } catch (Throwable $e2) {}
        try {
            $db->exec("ALTER TABLE demandes_conges ADD COLUMN sous_type varchar(150) DEFAULT NULL AFTER type_id");
        } catch (Throwable $e2) {}
        try {
            $db->exec("ALTER TABLE demandes_conges ADD COLUMN valide_resp_par int UNSIGNED DEFAULT NULL");
            $db->exec("ALTER TABLE demandes_conges ADD COLUMN valide_resp_le datetime DEFAULT NULL");
            $db->exec("ALTER TABLE demandes_conges ADD COLUMN valide_dg_par int UNSIGNED DEFAULT NULL");
            $db->exec("ALTER TABLE demandes_conges ADD COLUMN valide_dg_le datetime DEFAULT NULL");
        } catch (Throwable $e2) {}
        try {
            $db->exec("ALTER TABLE demandes_conges MODIFY type_id int UNSIGNED NULL");
        } catch (Throwable $e2) {}
        try {
            $db->exec("ALTER TABLE demandes_conges MODIFY statut enum('EN_ATTENTE','EN_ATTENTE_DG','APPROUVE','REFUSE','ANNULE') NOT NULL DEFAULT 'EN_ATTENTE'");
        } catch (Throwable $e2) {}
    }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS demandes_mission (
            demande_id int UNSIGNED NOT NULL PRIMARY KEY,
            destination varchar(255) NOT NULL,
            objet_mission text NOT NULL,
            moyen_transport varchar(150) DEFAULT NULL,
            frais_estimes varchar(150) DEFAULT NULL,
            prise_en_charge text DEFAULT NULL,
            ordre_mission_numero varchar(80) DEFAULT NULL,
            CONSTRAINT fk_dm_demande FOREIGN KEY (demande_id) REFERENCES demandes_conges(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE demandes_mission MODIFY ordre_mission_numero varchar(80) DEFAULT NULL");
    } catch (Throwable $e) {}
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS demandes_mission_agents (
            id int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            demande_id int UNSIGNED NOT NULL,
            personnel_id int UNSIGNED NOT NULL,
            ordre smallint UNSIGNED NOT NULL DEFAULT 1,
            UNIQUE KEY uq_dma (demande_id, personnel_id),
            KEY idx_dma_pers (personnel_id),
            CONSTRAINT fk_dma_demande FOREIGN KEY (demande_id) REFERENCES demandes_conges(id) ON DELETE CASCADE,
            CONSTRAINT fk_dma_pers FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('[CRICT demandes] CREATE demandes_mission_agents : ' . $e->getMessage());
    }
    // Vérifier l'accès réel (détecte table orpheline / tablespace #1813 / #1932)
    try {
        $db->query('SELECT 1 FROM demandes_mission_agents LIMIT 1');
    } catch (Throwable $e) {
        error_log('[CRICT demandes] Table demandes_mission_agents inaccessible : ' . $e->getMessage()
            . ' — supprimer le fichier orphelin data/crict_ent/demandes_mission_agents.ibd (MySQL arrêté) puis relancer.');
    }
}

function demandes_est_dg(): bool {
    $u = user_courant();
    if (!$u) return false;
    if (($u['groupe_code'] ?? '') === 'SUPER_ADMIN') return true;
    if (($u['groupe_code'] ?? '') === 'DG') return true;
    return a_permission('PERSONNEL', 'VALIDER');
}

function demandes_generer_reference(string $prefix): string {
    $db = get_db();
    $annee = date('Y');
    $stmt = $db->prepare("SELECT COUNT(*) FROM demandes_conges WHERE reference LIKE ?");
    $stmt->execute([$prefix . '-' . $annee . '-%']);
    $n = (int)$stmt->fetchColumn() + 1;
    return sprintf('%s-%s-%04d', $prefix, $annee, $n);
}

/** Référence officielle type 077/CRICT/DG/2026 (incrément à chaque validation DG). */
function demandes_generer_numero_officiel_om(?int $annee = null): string {
    $annee = $annee ?: (int)date('Y');
    $db = get_db();
    $stmt = $db->prepare("
        SELECT ordre_mission_numero FROM demandes_mission
        WHERE ordre_mission_numero LIKE ?
        ORDER BY CAST(SUBSTRING_INDEX(ordre_mission_numero, '/', 1) AS UNSIGNED) DESC
        LIMIT 1
    ");
    $stmt->execute(['%/CRICT/DG/' . $annee]);
    $last = (string)($stmt->fetchColumn() ?: '');
    $n = DEMANDES_OM_REF_DEPART;
    if (preg_match('/^(\d+)\s*\/CRICT\/DG\/' . preg_quote((string)$annee, '/') . '$/', $last, $m)) {
        $n = ((int)$m[1] >= DEMANDES_OM_REF_DEPART - 1)
            ? ((int)$m[1] + 1)
            : DEMANDES_OM_REF_DEPART;
    }
    return sprintf('%03d/CRICT/DG/%d', $n, $annee);
}

function demandes_fmt_date_long(string $date): string {
    $ts = strtotime($date);
    if (!$ts) return $date;
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $j = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    $jour = ($j === 1) ? '1er' : (string)$j;
    return $jour . ' ' . ($mois[$m] ?? '') . ' ' . $y;
}

function demandes_civilite(?string $genre, ?string $fonction = null): string {
    $f = mb_strtolower((string)$fonction);
    if (str_contains($f, 'directeur') || str_contains($f, 'dr ') || str_starts_with($f, 'dr.')) {
        return 'Dr';
    }
    $g = strtoupper(trim((string)$genre));
    if (in_array($g, ['F', 'FEMININ', 'FEMME', 'MADAME', 'MLLE', 'MME'], true)) {
        return 'Mme';
    }
    return 'M.';
}

function demandes_ligne_agent(array $p): string {
    $civ = demandes_civilite($p['genre'] ?? null, $p['fonction'] ?? null);
    $nom = trim(($p['prenoms'] ?? '') . ' ' . ($p['nom'] ?? ''));
    $fonc = trim((string)($p['fonction'] ?? $p['grade'] ?? ''));
    return $fonc !== '' ? ($civ . ' ' . $nom . ', ' . $fonc) : ($civ . ' ' . $nom);
}

function demandes_mission_agents(int $demande_id): array {
    demandes_assurer_schema();
    $stmt = get_db()->prepare("
        SELECT a.personnel_id, a.ordre, p.fonction, p.nationalite, p.matricule_rh,
               u.nom, u.prenoms, u.genre, u.grade, u.matricule
        FROM demandes_mission_agents a
        JOIN personnel p ON p.id = a.personnel_id
        JOIN utilisateurs u ON u.id = p.utilisateur_id
        WHERE a.demande_id = ?
        ORDER BY a.ordre, a.id
    ");
    $stmt->execute([$demande_id]);
    $rows = $stmt->fetchAll();
    if ($rows) return $rows;

    // Rétrocompatibilité : anciennes missions sans table agents
    $stmt2 = get_db()->prepare("SELECT personnel_id, categorie FROM demandes_conges WHERE id = ?");
    $stmt2->execute([$demande_id]);
    $d0 = $stmt2->fetch();
    if ($d0 && ($d0['categorie'] ?? '') === 'MISSION') {
        $p = personnel_par_id((int)$d0['personnel_id']);
        if ($p) {
            $p['personnel_id'] = (int)$p['id'];
            $p['ordre'] = 1;
            return [$p];
        }
    }
    return [];
}

function demandes_mission_peut_acceder(array $d, int $personnel_id, bool $peut_rh, bool $est_dg): bool {
    if ($peut_rh || $est_dg) return true;
    if ((int)($d['personnel_id'] ?? 0) === $personnel_id) return true;
    foreach (demandes_mission_agents((int)$d['id']) as $a) {
        if ((int)$a['personnel_id'] === $personnel_id) return true;
    }
    return false;
}

function demandes_personnel_actifs_pour_mission(): array {
    $db = get_db();

    // Certains anciens comptes ont été créés sans leur profil RH 1-1.
    // Le réparer ici permet de proposer tous les utilisateurs actifs.
    $db->prepare("
        INSERT INTO personnel (utilisateur_id, statut_rh, cree_par)
        SELECT u.id, 'ACTIF', ?
        FROM utilisateurs u
        LEFT JOIN personnel p ON p.utilisateur_id = u.id
        WHERE u.statut = 'ACTIF' AND p.id IS NULL
    ")->execute([user_id()]);

    // Eligible : compte actif et statut RH autorisant une mission.
    $stmt = $db->query("
        SELECT * FROM v_personnel
        WHERE statut_compte = 'ACTIF'
          AND statut_rh IN ('ACTIF', 'CONGE', 'MISSION')
        ORDER BY nom ASC, prenoms ASC
        LIMIT 500
    ");
    return $stmt ? ($stmt->fetchAll() ?: []) : [];
}

function demandes_libelle_categorie(string $cat): string {
    return match ($cat) {
        'CONGE'   => 'Congé',
        'ABSENCE' => 'Absence',
        'MISSION' => 'Ordre de mission',
        default   => $cat,
    };
}

function demandes_libelle_statut(string $statut): string {
    return match ($statut) {
        'EN_ATTENTE'    => 'En attente responsable',
        'EN_ATTENTE_DG' => 'En attente DG',
        'APPROUVE'      => 'Approuvé',
        'REFUSE'        => 'Refusé',
        'ANNULE'        => 'Annulé',
        default         => $statut,
    };
}

function demandes_select_base(): string {
    return "
        SELECT d.*,
               t.libelle AS type_conge_libelle, t.jours_par_an,
               m.destination, m.objet_mission, m.moyen_transport, m.frais_estimes,
               m.prise_en_charge, m.ordre_mission_numero,
               p.responsable_id,
               CONCAT_WS(' ', u.prenoms, u.nom) AS employe_nom,
               COALESCE(t.libelle, d.sous_type, demandes_libelle_fallback(d.categorie)) AS type_libelle
        FROM demandes_conges d
        LEFT JOIN types_conges t ON t.id = d.type_id
        LEFT JOIN demandes_mission m ON m.demande_id = d.id
        JOIN personnel p ON p.id = d.personnel_id
        JOIN utilisateurs u ON u.id = p.utilisateur_id
    ";
}

function demandes_par_id(int $id): ?array {
    demandes_assurer_schema();
    $sql = str_replace('demandes_libelle_fallback(d.categorie)', "'Congé'", demandes_select_base());
    $stmt = get_db()->prepare($sql . " WHERE d.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $row['type_libelle'] = demandes_type_libelle($row);
    }
    return $row ?: null;
}

function demandes_type_libelle(array $d): string {
    if ($d['categorie'] === 'CONGE') {
        $code = $d['sous_type'] ?? '';
        if ($code !== '' && isset(DEMANDES_TYPES_CONGE[$code])) {
            return DEMANDES_TYPES_CONGE[$code];
        }
        if (!empty($d['type_conge_libelle'])) {
            return $d['type_conge_libelle']; // anciennes demandes liées à types_conges
        }
        return $code !== '' ? $code : 'Congé';
    }
    if ($d['categorie'] === 'ABSENCE') {
        $code = $d['sous_type'] ?? '';
        return DEMANDES_TYPES_ABSENCE[$code] ?? ($code ?: 'Absence');
    }
    if ($d['categorie'] === 'MISSION') {
        return 'Ordre de mission';
    }
    return demandes_libelle_categorie($d['categorie'] ?? 'CONGE');
}

function demandes_lister_personnel(int $personnel_id, ?string $categorie = null): array {
    demandes_assurer_schema();
    $sql = "SELECT d.*, t.libelle AS type_conge_libelle, m.destination, m.objet_mission, m.ordre_mission_numero
            FROM demandes_conges d
            LEFT JOIN types_conges t ON t.id = d.type_id
            LEFT JOIN demandes_mission m ON m.demande_id = d.id
            WHERE (d.personnel_id = ?
               OR (d.categorie = 'MISSION' AND EXISTS (
                    SELECT 1 FROM demandes_mission_agents a
                    WHERE a.demande_id = d.id AND a.personnel_id = ?
               )))";
    $params = [$personnel_id, $personnel_id];
    if ($categorie && in_array($categorie, DEMANDES_CATEGORIES, true)) {
        $sql .= " AND d.categorie = ?";
        $params[] = $categorie;
    }
    $sql .= " ORDER BY d.cree_le DESC";
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['type_libelle'] = demandes_type_libelle($r);
    }
    return $rows;
}

function demandes_creer_conge(int $personnel_id, string $type_code, string $debut, string $fin, string $motif, string $type_autre = ''): array {
    demandes_assurer_schema();
    if (!isset(DEMANDES_TYPES_CONGE[$type_code])) {
        return ['succes' => false, 'message' => 'Type de congé invalide.'];
    }
    if ($type_code === 'AUTRE') {
        $sous_type = trim($type_autre);
        if ($sous_type === '') {
            return ['succes' => false, 'message' => 'Précisez le type de congé.'];
        }
        if (mb_strlen($sous_type) > 150) {
            return ['succes' => false, 'message' => 'Le type personnalisé ne doit pas dépasser 150 caractères.'];
        }
    } else {
        $sous_type = $type_code;
    }
    $jours = demandes_nb_jours_ouvres($debut, $fin);
    if ($jours <= 0) return ['succes' => false, 'message' => 'Dates invalides (aucun jour ouvré sur la période).'];

    $ref = demandes_generer_reference('CNG');
    get_db()->prepare("
        INSERT INTO demandes_conges (personnel_id, categorie, reference, sous_type, date_debut, date_fin, nombre_jours, motif, statut)
        VALUES (?, 'CONGE', ?, ?, ?, ?, ?, ?, 'EN_ATTENTE')
    ")->execute([$personnel_id, $ref, $sous_type, $debut, $fin, $jours, $motif !== '' ? $motif : null]);
    $id = (int)get_db()->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'DEMANDE_CONGE', "Demande congé ID $id");
    return ['succes' => true, 'id' => $id];
}

function demandes_creer_absence(int $personnel_id, string $type_code, string $debut, string $fin, string $motif, string $type_autre = ''): array {
    demandes_assurer_schema();
    if (!isset(DEMANDES_TYPES_ABSENCE[$type_code])) {
        return ['succes' => false, 'message' => 'Type d\'absence invalide.'];
    }
    if ($type_code === 'AUTRE') {
        $sous_type = trim($type_autre);
        if ($sous_type === '') {
            return ['succes' => false, 'message' => 'Précisez le type d\'absence.'];
        }
        if (mb_strlen($sous_type) > 150) {
            return ['succes' => false, 'message' => 'Le type personnalisé ne doit pas dépasser 150 caractères.'];
        }
    } else {
        $sous_type = $type_code;
    }
    $jours = demandes_nb_jours_ouvres($debut, $fin);
    if ($jours <= 0) return ['succes' => false, 'message' => 'Dates invalides (aucun jour ouvré sur la période).'];

    $ref = demandes_generer_reference('ABS');
    get_db()->prepare("
        INSERT INTO demandes_conges (personnel_id, categorie, reference, sous_type, date_debut, date_fin, nombre_jours, motif, statut)
        VALUES (?, 'ABSENCE', ?, ?, ?, ?, ?, ?, 'EN_ATTENTE')
    ")->execute([$personnel_id, $ref, $sous_type, $debut, $fin, $jours, $motif !== '' ? $motif : null]);
    $id = (int)get_db()->lastInsertId();
    log_activite(user_id(), 'PERSONNEL', 'DEMANDE_ABSENCE', "Demande absence ID $id");
    return ['succes' => true, 'id' => $id];
}

function demandes_creer_mission(int $personnel_id, array $data): array {
    demandes_assurer_schema();
    $debut = trim($data['date_debut'] ?? '');
    $fin   = trim($data['date_fin'] ?? '');
    $objet = demandes_nettoyer_html_riche((string)($data['objet_mission'] ?? ''));
    $jours = conges_nb_jours($debut, $fin);
    if ($jours <= 0) return ['succes' => false, 'message' => 'Dates invalides.'];
    if (trim($data['destination'] ?? '') === '' || demandes_texte_depuis_html($objet) === '') {
        return ['succes' => false, 'message' => 'Destination et objet de mission requis.'];
    }
    if (mb_strlen(demandes_texte_depuis_html($objet)) > 10000) {
        return ['succes' => false, 'message' => 'L’objet de mission est trop long.'];
    }

    // Agents : demandeur + éventuels collègues sélectionnés
    $agent_ids = [$personnel_id];
    $extra = $data['agents'] ?? [];
    if (!is_array($extra)) $extra = [];
    foreach ($extra as $aid) {
        $aid = (int)$aid;
        if ($aid > 0 && $aid !== $personnel_id && !in_array($aid, $agent_ids, true)) {
            if (personnel_par_id($aid)) $agent_ids[] = $aid;
        }
    }

    $db = get_db();
    $ref = demandes_generer_reference('OM');
    try {
        $db->beginTransaction();
        $db->prepare("
            INSERT INTO demandes_conges (personnel_id, categorie, reference, date_debut, date_fin, nombre_jours, motif, statut)
            VALUES (?, 'MISSION', ?, ?, ?, ?, ?, 'EN_ATTENTE')
        ")->execute([
            $personnel_id, $ref, $debut, $fin, $jours,
            trim($data['motif'] ?? '') ?: null,
        ]);
        $id = (int)$db->lastInsertId();
        $db->prepare("
            INSERT INTO demandes_mission (demande_id, destination, objet_mission, moyen_transport, frais_estimes, prise_en_charge)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $id,
            trim($data['destination']),
            $objet,
            trim($data['moyen_transport'] ?? '') ?: null,
            trim($data['frais_estimes'] ?? '') ?: null,
            trim($data['prise_en_charge'] ?? '') ?: null,
        ]);
        $insA = $db->prepare("
            INSERT INTO demandes_mission_agents (demande_id, personnel_id, ordre) VALUES (?, ?, ?)
        ");
        $ordre = 1;
        foreach ($agent_ids as $aid) {
            $insA->execute([$id, $aid, $ordre++]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['succes' => false, 'message' => 'Erreur lors de l\'enregistrement.'];
    }
    log_activite(user_id(), 'PERSONNEL', 'DEMANDE_MISSION', "Ordre de mission ID $id (" . count($agent_ids) . " agent(s))");
    return ['succes' => true, 'id' => $id];
}

function demandes_a_valider(int $validateur_pid, bool $peut_rh, bool $est_dg): array {
    demandes_assurer_schema();
    $out = [];

    $sql = "SELECT d.*, t.libelle AS type_conge_libelle, m.destination, m.objet_mission,
                   CONCAT_WS(' ', u.prenoms, u.nom) AS employe_nom, p.responsable_id
            FROM demandes_conges d
            LEFT JOIN types_conges t ON t.id = d.type_id
            LEFT JOIN demandes_mission m ON m.demande_id = d.id
            JOIN personnel p ON p.id = d.personnel_id
            JOIN utilisateurs u ON u.id = p.utilisateur_id
            WHERE d.statut = 'EN_ATTENTE'";
    $params = [];
    if (!$peut_rh) {
        $sql .= " AND p.responsable_id = ?";
        $params[] = $validateur_pid;
    }
    $stmt = get_db()->prepare($sql . " ORDER BY d.cree_le ASC");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        $r['type_libelle'] = demandes_type_libelle($r);
        $r['action_requise'] = 'RESPONSABLE';
        $out[] = $r;
    }

    if ($est_dg) {
        $stmt = get_db()->query("
            SELECT d.*, m.destination, m.objet_mission,
                   CONCAT_WS(' ', u.prenoms, u.nom) AS employe_nom, p.responsable_id
            FROM demandes_conges d
            LEFT JOIN demandes_mission m ON m.demande_id = d.id
            JOIN personnel p ON p.id = d.personnel_id
            JOIN utilisateurs u ON u.id = p.utilisateur_id
            WHERE d.statut = 'EN_ATTENTE_DG' AND d.categorie = 'MISSION'
            ORDER BY d.cree_le ASC
        ");
        foreach ($stmt->fetchAll() as $r) {
            $r['type_libelle'] = 'Ordre de mission';
            $r['action_requise'] = 'DG';
            $out[] = $r;
        }
    }

    return $out;
}

function demandes_peut_valider_resp(array $d, int $validateur_pid, bool $peut_rh): bool {
    if ($d['statut'] !== 'EN_ATTENTE') return false;
    if ($peut_rh) return true;
    return (int)($d['responsable_id'] ?? 0) === $validateur_pid && $validateur_pid > 0;
}

function demandes_peut_valider_dg(array $d): bool {
    return $d['statut'] === 'EN_ATTENTE_DG' && $d['categorie'] === 'MISSION' && demandes_est_dg();
}

function demandes_valider_resp(int $id, string $commentaire = ''): array {
    $db = get_db();
    $d  = demandes_par_id($id);
    if (!$d) return ['succes' => false, 'message' => 'Demande introuvable.'];
    if ($d['statut'] !== 'EN_ATTENTE') return ['succes' => false, 'message' => 'Demande déjà traitée.'];

    $uid = user_id();

    if ($d['categorie'] === 'MISSION') {
        $db->prepare("
            UPDATE demandes_conges SET statut='EN_ATTENTE_DG', valide_resp_par=?, valide_resp_le=NOW(),
                commentaire_validation=? WHERE id=?
        ")->execute([$uid, $commentaire !== '' ? $commentaire : null, $id]);
        log_activite($uid, 'PERSONNEL', 'DEMANDE_VISA_RESP', "Mission ID $id visée responsable");
        return ['succes' => true, 'message' => 'Visa responsable enregistré — en attente du DG.'];
    }

    if ($d['categorie'] === 'CONGE') {
        return demandes_approuver_conge($id, $uid, $commentaire);
    }

    return demandes_approuver_absence($id, $uid, $commentaire);
}

// Approbation d'une absence : décompte sur le quota unique de repos.
function demandes_approuver_absence(int $id, int $validateur_uid, string $commentaire): array {
    $db = get_db();
    $d  = demandes_par_id($id);
    if (!$d || $d['categorie'] !== 'ABSENCE') return ['succes' => false, 'message' => 'Demande invalide.'];

    $annee = (int)date('Y', strtotime($d['date_debut']));
    $solde = demandes_solde_repos((int)$d['personnel_id'], $annee);
    if (($solde['pris'] + (float)$d['nombre_jours']) > $solde['droit']) {
        return ['succes' => false, 'message' => 'Quota de repos dépassé (' . number_format($solde['restants'], 0) . ' j. restants sur ' . DEMANDES_QUOTA_ANNUEL . ').'];
    }

    $db->prepare("UPDATE demandes_conges SET statut='APPROUVE', valide_par=?, valide_resp_par=?, valide_resp_le=NOW(), commentaire_validation=? WHERE id=?")
       ->execute([$validateur_uid, $validateur_uid, $commentaire !== '' ? $commentaire : null, $id]);
    log_activite($validateur_uid, 'PERSONNEL', 'DEMANDE_ABSENCE_OK', "Absence ID $id approuvée");
    return ['succes' => true];
}

function demandes_approuver_conge(int $id, int $validateur_uid, string $commentaire): array {
    $db = get_db();
    $d  = demandes_par_id($id);
    if (!$d || $d['categorie'] !== 'CONGE') return ['succes' => false, 'message' => 'Demande invalide.'];

    $annee = (int)date('Y', strtotime($d['date_debut']));
    $solde = demandes_solde_repos((int)$d['personnel_id'], $annee);
    if (($solde['pris'] + (float)$d['nombre_jours']) > $solde['droit']) {
        return ['succes' => false, 'message' => 'Quota de repos dépassé (' . number_format($solde['restants'], 0) . ' j. restants sur ' . DEMANDES_QUOTA_ANNUEL . ').'];
    }

    $db->prepare("UPDATE demandes_conges SET statut='APPROUVE', valide_par=?, valide_resp_par=?, valide_resp_le=NOW(), commentaire_validation=? WHERE id=?")
       ->execute([$validateur_uid, $validateur_uid, $commentaire !== '' ? $commentaire : null, $id]);

    $auj = date('Y-m-d');
    if ($d['date_debut'] <= $auj && $auj <= $d['date_fin']) {
        personnel_changer_statut((int)$d['personnel_id'], 'CONGE');
    }
    log_activite($validateur_uid, 'PERSONNEL', 'CONGE_APPROUVE', "Congé ID $id approuvé");
    return ['succes' => true];
}

function demandes_valider_dg(int $id, string $commentaire = ''): array {
    $db = get_db();
    $d  = demandes_par_id($id);
    if (!$d || $d['categorie'] !== 'MISSION' || $d['statut'] !== 'EN_ATTENTE_DG') {
        return ['succes' => false, 'message' => 'Demande non éligible à la validation DG.'];
    }

    $uid = user_id();
    $num = !empty($d['ordre_mission_numero']) && str_contains((string)$d['ordre_mission_numero'], '/CRICT/DG/')
        ? (string)$d['ordre_mission_numero']
        : demandes_generer_numero_officiel_om((int)date('Y', strtotime($d['date_debut'] ?: 'now')));

    $db->prepare("
        UPDATE demandes_conges SET statut='APPROUVE', valide_par=?, valide_dg_par=?, valide_dg_le=NOW(), commentaire_validation=? WHERE id=?
    ")->execute([$uid, $uid, $commentaire !== '' ? $commentaire : null, $id]);
    $db->prepare("UPDATE demandes_mission SET ordre_mission_numero = ? WHERE demande_id = ?")->execute([$num, $id]);

    $auj = date('Y-m-d');
    if ($d['date_debut'] <= $auj && $auj <= $d['date_fin']) {
        foreach (demandes_mission_agents($id) as $a) {
            personnel_changer_statut((int)$a['personnel_id'], 'MISSION');
        }
    }
    log_activite($uid, 'PERSONNEL', 'MISSION_APPROUVE', "Mission ID $id approuvée par DG — $num");
    return ['succes' => true, 'ordre_numero' => $num];
}

function demandes_refuser(int $id, string $commentaire = ''): array {
    $d = demandes_par_id($id);
    if (!$d) return ['succes' => false, 'message' => 'Demande introuvable.'];
    if (!in_array($d['statut'], ['EN_ATTENTE', 'EN_ATTENTE_DG'], true)) {
        return ['succes' => false, 'message' => 'Demande déjà traitée.'];
    }
    get_db()->prepare("UPDATE demandes_conges SET statut='REFUSE', valide_par=?, commentaire_validation=? WHERE id=?")
            ->execute([user_id(), $commentaire !== '' ? $commentaire : null, $id]);
    log_activite(user_id(), 'PERSONNEL', 'DEMANDE_REFUSE', "Demande ID $id refusée");
    return ['succes' => true];
}

function demandes_annuler(int $id, int $personnel_id): array {
    $db = get_db();
    $d  = demandes_par_id($id);
    if (!$d) {
        return ['succes' => false, 'message' => 'Demande introuvable.'];
    }
    $ok_owner = (int)$d['personnel_id'] === $personnel_id;
    if (!$ok_owner && ($d['categorie'] ?? '') === 'MISSION') {
        foreach (demandes_mission_agents($id) as $a) {
            if ((int)$a['personnel_id'] === $personnel_id) { $ok_owner = true; break; }
        }
    }
    if (!$ok_owner) {
        return ['succes' => false, 'message' => 'Demande introuvable.'];
    }

    // Le quota de repos étant recalculé dynamiquement à partir des demandes
    // approuvées, l'annulation se limite à basculer le statut.
    $annulable = in_array($d['statut'], ['EN_ATTENTE', 'EN_ATTENTE_DG'], true)
        || ($d['statut'] === 'APPROUVE' && $d['date_debut'] > date('Y-m-d'));

    if (!$annulable) {
        return ['succes' => false, 'message' => 'Cette demande ne peut plus être annulée.'];
    }

    $db->prepare("UPDATE demandes_conges SET statut='ANNULE' WHERE id=?")->execute([$id]);
    log_activite(user_id(), 'PERSONNEL', 'DEMANDE_ANNULE', "Demande ID $id annulée");
    return ['succes' => true];
}

function demandes_compte_a_valider(int $validateur_pid, bool $peut_rh, bool $est_dg): int {
    return count(demandes_a_valider($validateur_pid, $peut_rh, $est_dg));
}
