<?php
// ============================================================
// models/remuneration.php — Rémunération (confidentiel)
//   Accès gardé par PERSONNEL/GERER_REMUNERATION dans les pages.
// ============================================================

function remu_format(float $montant): string {
    return number_format($montant, 0, ',', ' ') . ' GNF';
}

// ------------------------------------------------------------
//  Salaire de base (historisé)
// ------------------------------------------------------------
function remuneration_salaire_actuel(int $personnel_id): ?array {
    $stmt = get_db()->prepare("
        SELECT * FROM remunerations
        WHERE personnel_id = ? AND date_effet <= CURDATE()
        ORDER BY date_effet DESC, id DESC LIMIT 1
    ");
    $stmt->execute([$personnel_id]);
    return $stmt->fetch() ?: null;
}

function remuneration_historique(int $personnel_id): array {
    $stmt = get_db()->prepare("SELECT * FROM remunerations WHERE personnel_id = ? ORDER BY date_effet DESC, id DESC");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function remuneration_definir_base(int $personnel_id, float $montant, string $date_effet, string $motif): array {
    if ($montant < 0)        return ['succes' => false, 'message' => 'Montant invalide.'];
    if ($date_effet === '')  return ['succes' => false, 'message' => "Date d'effet requise."];
    get_db()->prepare("INSERT INTO remunerations (personnel_id, salaire_base, date_effet, motif, cree_par)
                       VALUES (?, ?, ?, ?, ?)")
            ->execute([$personnel_id, $montant, $date_effet, $motif !== '' ? $motif : null, user_id()]);
    log_activite(user_id(), 'PERSONNEL', 'REMUNERATION_BASE', "Salaire de base défini (profil $personnel_id)");
    return ['succes' => true];
}

// ------------------------------------------------------------
//  Éléments récurrents
// ------------------------------------------------------------
function remuneration_elements(int $personnel_id, bool $actifs_seulement = false): array {
    $sql = "SELECT * FROM remuneration_elements WHERE personnel_id = ?";
    if ($actifs_seulement) $sql .= " AND actif = 1";
    $sql .= " ORDER BY FIELD(type,'PRIME','INDEMNITE','RETENUE'), libelle";
    $stmt = get_db()->prepare($sql);
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function remuneration_element_ajouter(int $personnel_id, array $d): array {
    $type = in_array($d['type'] ?? '', ['PRIME','INDEMNITE','RETENUE'], true) ? $d['type'] : 'PRIME';
    $mode = ($d['mode'] ?? 'FIXE') === 'POURCENTAGE' ? 'POURCENTAGE' : 'FIXE';
    if (($d['libelle'] ?? '') === '') return ['succes' => false, 'message' => 'Libellé requis.'];

    get_db()->prepare("INSERT INTO remuneration_elements (personnel_id, type, libelle, mode, montant, taux)
                       VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([
                $personnel_id, $type, $d['libelle'], $mode,
                $mode === 'FIXE'        ? (float)($d['montant'] ?? 0) : null,
                $mode === 'POURCENTAGE' ? (float)($d['taux'] ?? 0)    : null,
            ]);
    log_activite(user_id(), 'PERSONNEL', 'REMUNERATION_ELEMENT', "Élément de paie ajouté (profil $personnel_id)");
    return ['succes' => true];
}

function remuneration_element_supprimer(int $id, int $personnel_id): void {
    get_db()->prepare("DELETE FROM remuneration_elements WHERE id = ? AND personnel_id = ?")->execute([$id, $personnel_id]);
    log_activite(user_id(), 'PERSONNEL', 'REMUNERATION_ELEMENT', "Élément de paie retiré (profil $personnel_id)");
}

function remuneration_montant_element(array $el, float $base): float {
    if ($el['mode'] === 'POURCENTAGE') return round($base * (float)$el['taux'] / 100, 2);
    return (float)$el['montant'];
}

// ------------------------------------------------------------
//  Bulletins de paie
// ------------------------------------------------------------
function bulletins_lister(int $personnel_id): array {
    $stmt = get_db()->prepare("SELECT * FROM bulletins WHERE personnel_id = ? ORDER BY annee DESC, mois DESC");
    $stmt->execute([$personnel_id]);
    return $stmt->fetchAll();
}

function bulletin_par_id(int $id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM bulletins WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function bulletin_lignes(int $bulletin_id): array {
    $stmt = get_db()->prepare("SELECT * FROM bulletin_lignes WHERE bulletin_id = ? ORDER BY FIELD(type,'BASE','PRIME','INDEMNITE','RETENUE'), id");
    $stmt->execute([$bulletin_id]);
    return $stmt->fetchAll();
}

// Calcule (sans enregistrer) le bulletin d'une période — pour aperçu
function bulletin_calculer(int $personnel_id): array {
    $sal = remuneration_salaire_actuel($personnel_id);
    $base = $sal ? (float)$sal['salaire_base'] : 0.0;
    $lignes = [['type' => 'BASE', 'libelle' => 'Salaire de base', 'montant' => $base]];
    $brut = $base;
    $retenues = 0.0;
    foreach (remuneration_elements($personnel_id, true) as $el) {
        $m = remuneration_montant_element($el, $base);
        $lignes[] = ['type' => $el['type'], 'libelle' => $el['libelle'], 'montant' => $m];
        if ($el['type'] === 'RETENUE') $retenues += $m; else $brut += $m;
    }
    return [
        'base'        => $base,
        'lignes'      => $lignes,
        'total_brut'  => $brut,
        'total_reten' => $retenues,
        'net'         => $brut - $retenues,
        'a_salaire'   => $sal !== null,
    ];
}

function bulletin_generer(int $personnel_id, int $annee, int $mois): array {
    if ($mois < 1 || $mois > 12) return ['succes' => false, 'message' => 'Mois invalide.'];

    $db = get_db();
    $chk = $db->prepare("SELECT id FROM bulletins WHERE personnel_id=? AND annee=? AND mois=?");
    $chk->execute([$personnel_id, $annee, $mois]);
    if ($chk->fetch()) {
        return ['succes' => false, 'message' => 'Un bulletin existe déjà pour cette période.'];
    }

    $calc = bulletin_calculer($personnel_id);
    if (!$calc['a_salaire']) {
        return ['succes' => false, 'message' => "Définissez d'abord un salaire de base."];
    }

    try {
        $db->beginTransaction();
        $db->prepare("INSERT INTO bulletins (personnel_id, annee, mois, salaire_base, total_brut, total_retenues, net_a_payer, statut, genere_par)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'BROUILLON', ?)")
           ->execute([$personnel_id, $annee, $mois, $calc['base'], $calc['total_brut'], $calc['total_reten'], $calc['net'], user_id()]);
        $bid = (int)$db->lastInsertId();
        $ins = $db->prepare("INSERT INTO bulletin_lignes (bulletin_id, type, libelle, montant) VALUES (?, ?, ?, ?)");
        foreach ($calc['lignes'] as $l) {
            $ins->execute([$bid, $l['type'], $l['libelle'], $l['montant']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[CRICT] génération bulletin : ' . $e->getMessage());
        return ['succes' => false, 'message' => 'Erreur lors de la génération.'];
    }

    log_activite(user_id(), 'PERSONNEL', 'BULLETIN_GENERE', "Bulletin $mois/$annee généré (profil $personnel_id)");
    return ['succes' => true, 'id' => $bid];
}

function bulletin_changer_statut(int $id, string $statut): void {
    if (!in_array($statut, ['BROUILLON','VALIDE','PAYE'], true)) return;
    get_db()->prepare("UPDATE bulletins SET statut=? WHERE id=?")->execute([$statut, $id]);
    log_activite(user_id(), 'PERSONNEL', 'BULLETIN_STATUT', "Bulletin ID $id -> $statut");
}

function bulletin_supprimer(int $id): array {
    $b = bulletin_par_id($id);
    if (!$b) return ['succes' => false, 'message' => 'Bulletin introuvable.'];
    if ($b['statut'] !== 'BROUILLON') return ['succes' => false, 'message' => 'Seuls les brouillons peuvent être supprimés.'];
    get_db()->prepare("DELETE FROM bulletins WHERE id=?")->execute([$id]);
    log_activite(user_id(), 'PERSONNEL', 'BULLETIN_SUPPR', "Bulletin ID $id supprimé");
    return ['succes' => true];
}

// Journalise une consultation (lecture) de données de rémunération
function remuneration_log_consultation(int $personnel_id): void {
    log_activite(user_id(), 'PERSONNEL', 'REMUNERATION_CONSULT', "Consultation rémunération (profil $personnel_id)");
}
