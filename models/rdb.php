<?php
// ============================================================
// models/rdb.php — Rapport d'Exécution Budgétaire (RDB)
// Tables : rapports_rdb, rdb_lignes, rdb_documents
// ============================================================

define('RDB_UPLOAD_DIR', UPLOAD_DIR . 'rdb/');
if (!defined('RDB_MAX_SIZE')) define('RDB_MAX_SIZE', 20 * 1024 * 1024);
if (!defined('RDB_MIME_OK')) define('RDB_MIME_OK', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv',
    'image/jpeg', 'image/png', 'image/webp',
]);

function _rdb_err(string $m): array { return ['succes' => false, 'message' => $m]; }

function rdb_fmt(?float $v): string {
    if ($v === null) return '—';
    return number_format($v, 0, ',', ' ') . ' GNF';
}

function rdb_trimestre_label(int $t): string {
    return 'T' . $t;
}

/** Année budgétaire N-1 par rapport à l'année du rapport. */
function rdb_annee_n_moins_1(int $annee): int {
    return $annee - 1;
}

/** Trimestre précédent (pour tableau 3). */
function rdb_trimestre_precedent(int $annee, int $trimestre): array {
    if ($trimestre <= 1) return [$annee - 1, 4];
    return [$annee, $trimestre - 1];
}

// ──────────────────────────────────────────────────────────
// DÉFINITION DES 9 TABLEAUX (colonnes affichées au PDF)
// ──────────────────────────────────────────────────────────

function rdb_tableaux_meta(): array {
    return [
        1 => [
            'titre'    => 'Activités programmées au titre de l\'année budgétaire N-1',
            'colonnes' => ['objectif' => 'Objectif', 'montant_budgete' => 'Montant budgété (GNF)',
                           'responsable' => 'Responsable'],
        ],
        2 => [
            'titre'    => 'Activités programmées par trimestre de l\'année budgétaire N-1',
            'colonnes' => ['t1' => 'T1 (GNF)', 't2' => 'T2 (GNF)', 't3' => 'T3 (GNF)', 't4' => 'T4 (GNF)'],
        ],
        3 => [
            'titre'    => 'Activités réalisées durant le trimestre précédent',
            'colonnes' => ['date_debut' => 'Date début', 'date_fin' => 'Date fin', 'etat' => 'État',
                           'resultats' => 'Résultats obtenus', 'montant_depense' => 'Montant dépensé (GNF)'],
        ],
        4 => [
            'titre'    => 'Ressources mobilisées et exécutées',
            'colonnes' => [
                'credit' => 'Crédit (GNF)',
                'paye'   => 'Payé (GNF)',
                'taux'   => 'Taux d\'exécution (%)',
            ],
            'cols_calc' => ['taux'],
        ],
        5 => [
            'titre'    => 'Situation détaillée des dépenses de personnel',
            'nature'   => 'personnel',
            'colonnes' => [
                'credit' => 'Crédit (GNF)',
                'paye'   => 'Payé (GNF)',
                'taux'   => 'Taux d\'exécution (%)',
            ],
            'cols_calc' => ['taux'],
        ],
        6 => [
            'titre'    => 'Situation détaillée des dépenses de fonctionnement',
            'nature'   => 'fonctionnement',
            'colonnes' => [
                'credit' => 'Crédit (GNF)',
                'paye'   => 'Payé (GNF)',
                'taux'   => 'Taux d\'exécution (%)',
            ],
            'cols_calc' => ['taux'],
        ],
        7 => [
            'titre'    => 'Situation détaillée des dépenses de transfert',
            'nature'   => 'transfert',
            'colonnes' => [
                'credit' => 'Crédit (GNF)',
                'paye'   => 'Payé (GNF)',
                'taux'   => 'Taux d\'exécution (%)',
            ],
            'cols_calc' => ['taux'],
        ],
        8 => [
            'titre'    => 'Situation détaillée des dépenses d\'investissement',
            'nature'   => 'investissement',
            'colonnes' => [
                'credit' => 'Crédit (GNF)',
                'paye'   => 'Payé (GNF)',
                'taux'   => 'Taux d\'exécution (%)',
            ],
            'cols_calc' => ['taux'],
        ],
        9 => [
            'titre'    => 'Récapitulatif par nature de dépenses',
            'readonly' => true,
            'colonnes' => [
                'credit' => 'Crédit (GNF)',
                'paye'   => 'Total payé (GNF)',
                'taux'   => 'Taux d\'exécution (%)',
            ],
            'cols_calc' => ['taux'],
        ],
    ];
}

function rdb_etats_activite(): array {
    return ['realisee' => 'Réalisée', 'en_cours' => 'En cours', 'non_realisee' => 'Non réalisée'];
}

/** Taux d'exécution : Payé ÷ Crédit (en %). */
function rdb_calc_taux(float $credit, float $paye): float {
    if ($credit <= 0) return 0.0;
    return round($paye / $credit * 100, 1);
}

function rdb_natures_recap(): array {
    return [
        5 => 'Dépenses de personnel',
        6 => 'Dépenses de fonctionnement',
        7 => 'Dépenses de transfert',
        8 => 'Dépenses d\'investissement',
    ];
}

// ──────────────────────────────────────────────────────────
// LECTURE
// ──────────────────────────────────────────────────────────

function rdb_lister(array $f = [], int $lim = 20, int $off = 0): array {
    $db = get_db();
    $where = ['1=1']; $p = [];
    if (!empty($f['annee']))     { $where[] = 'r.annee=?';     $p[] = (int)$f['annee']; }
    if (!empty($f['trimestre'])) { $where[] = 'r.trimestre=?'; $p[] = (int)$f['trimestre']; }
    if (!empty($f['statut']))    { $where[] = 'r.statut=?';    $p[] = $f['statut']; }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM rapports_rdb r WHERE $cond");
    $sc->execute($p);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT r.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom,
               (SELECT COUNT(*) FROM rdb_lignes l WHERE l.rapport_id=r.id) AS nb_lignes
        FROM rapports_rdb r
        LEFT JOIN utilisateurs u ON u.id = r.cree_par
        WHERE $cond
        ORDER BY r.annee DESC, r.trimestre DESC
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($p, [$lim, $off]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function rdb_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT r.*, CONCAT(u.prenoms,' ',u.nom) AS auteur_nom,
               CONCAT(uf.prenoms,' ',uf.nom) AS finalise_nom
        FROM rapports_rdb r
        LEFT JOIN utilisateurs u  ON u.id  = r.cree_par
        LEFT JOIN utilisateurs uf ON uf.id = r.finalise_par
        WHERE r.id=?
    ");
    $s->execute([$id]);
    $r = $s->fetch();
    if (!$r) return null;

    $r['annee_n1'] = rdb_annee_n_moins_1((int)$r['annee']);
    [$r['trim_prec_annee'], $r['trim_prec']] = rdb_trimestre_precedent((int)$r['annee'], (int)$r['trimestre']);

    $sl = $db->prepare("SELECT * FROM rdb_lignes WHERE rapport_id=? ORDER BY tableau_no, ordre, id");
    $sl->execute([$id]);
    $r['lignes'] = [];
    $r['lignes_par_tableau'] = [];
    foreach ($sl->fetchAll() as $l) {
        $l['donnees'] = $l['donnees'] ? (json_decode($l['donnees'], true) ?: []) : [];
        $tn = (int)$l['tableau_no'];
        $r['lignes_par_tableau'][$tn][] = $l;
        $r['lignes'][] = $l;
    }

    try {
        $sd = $db->prepare("SELECT * FROM rdb_documents WHERE rapport_id=? ORDER BY date_upload");
        $sd->execute([$id]);
        $r['documents'] = $sd->fetchAll();
    } catch (Throwable $e) {
        $r['documents'] = [];
    }

    return $r;
}

function rdb_existe(int $annee, int $trimestre, ?int $exclude_id = null): bool {
    $db = get_db();
    $sql = "SELECT id FROM rapports_rdb WHERE annee=? AND trimestre=?";
    $p   = [$annee, $trimestre];
    if ($exclude_id) { $sql .= ' AND id<>?'; $p[] = $exclude_id; }
    $s = $db->prepare($sql);
    $s->execute($p);
    return (bool)$s->fetchColumn();
}

// ──────────────────────────────────────────────────────────
// ÉCRITURE
// ──────────────────────────────────────────────────────────

function rdb_creer(int $annee, int $trimestre): array {
    if ($annee < 2000 || $annee > 2100) return _rdb_err('Année invalide.');
    if ($trimestre < 1 || $trimestre > 4) return _rdb_err('Trimestre invalide (1 à 4).');
    if (rdb_existe($annee, $trimestre)) return _rdb_err('Un RDB existe déjà pour cette période.');

    try {
        $db = get_db();
        $db->prepare("
            INSERT INTO rapports_rdb (annee, trimestre, statut, cree_par, date_creation)
            VALUES (?, ?, 'brouillon', ?, NOW())
        ")->execute([$annee, $trimestre, user_id()]);
        $id = (int)$db->lastInsertId();
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_CREATION', "RDB T$trimestre-$annee créé (#$id)");
        return ['succes' => true, 'id' => $id];
    } catch (Throwable $e) {
        error_log('[rdb_creer] ' . $e->getMessage());
        return _rdb_err('Impossible de créer le rapport.');
    }
}

function rdb_dupliquer(int $source_id, int $annee, int $trimestre): array {
    $src = rdb_charger($source_id);
    if (!$src) return _rdb_err('Rapport source introuvable.');
    $cr = rdb_creer($annee, $trimestre);
    if (!$cr['succes']) return $cr;
    $nid = (int)$cr['id'];

    $db = get_db();
    try {
        $db->prepare("
            UPDATE rapports_rdb SET
              acte_creation=?, missions=?, organisation=?, commentaire_personnel=?,
              suggestions=?, recommandations=?, conclusion=?, date_maj=NOW()
            WHERE id=?
        ")->execute([
            $src['acte_creation'], $src['missions'], $src['organisation'], $src['commentaire_personnel'],
            $src['suggestions'], $src['recommandations'], $src['conclusion'], $nid,
        ]);

        foreach ($src['lignes'] as $l) {
            if ((int)$l['tableau_no'] === 9) continue; // recalculé
            $db->prepare("
                INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees, commentaire, justification)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([
                $nid, $l['tableau_no'], $l['code_ligne'], $l['libelle'], $l['ordre'],
                json_encode($l['donnees'], JSON_UNESCAPED_UNICODE),
                $l['commentaire'], $l['justification'],
            ]);
        }
        rdb_recalculer_tableau9($nid);
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_DUPLIQUER', "RDB #$source_id dupliqué vers #$nid");
        return ['succes' => true, 'id' => $nid];
    } catch (Throwable $e) {
        error_log('[rdb_dupliquer] ' . $e->getMessage());
        return _rdb_err('Erreur lors de la duplication.');
    }
}

function _rdb_num($v): ?float {
    $v = trim(str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], (string)$v));
    if ($v === '') return null;
    return is_numeric($v) ? (float)$v : null;
}

function _rdb_code_auto(int $tableau_no, int $ordre): string {
    $pfx = match ($tableau_no) {
        1, 2, 3 => 'ACT',
        4       => 'RES',
        5       => 'PERS',
        6       => 'FONC',
        7       => 'TRANS',
        8       => 'INV',
        9       => 'REC',
        default => 'L',
    };
    return sprintf('%s-%02d', $pfx, $ordre + 1);
}

/** Sauvegarde complète : textes + lignes des 9 tableaux. */
function rdb_sauver(int $id, array $d): array {
    $rap = rdb_charger($id);
    if (!$rap) return _rdb_err('Rapport introuvable.');
    if ($rap['statut'] === 'finalise') return _rdb_err('Rapport finalisé — modification impossible.');

    $db = get_db();
    try {
        $db->prepare("
            UPDATE rapports_rdb SET
              acte_creation=?, missions=?, organisation=?, commentaire_personnel=?,
              suggestions=?, recommandations=?, conclusion=?, date_maj=NOW()
            WHERE id=?
        ")->execute([
            trim((string)($d['acte_creation'] ?? '')),
            trim((string)($d['missions'] ?? '')),
            trim((string)($d['organisation'] ?? '')),
            trim((string)($d['commentaire_personnel'] ?? '')),
            trim((string)($d['suggestions'] ?? '')),
            trim((string)($d['recommandations'] ?? '')),
            trim((string)($d['conclusion'] ?? '')),
            $id,
        ]);
    } catch (Throwable $e) {
        error_log('[rdb_sauver] ' . $e->getMessage());
        return _rdb_err('Erreur lors de l\'enregistrement.');
    }

    // Lignes : supprimer et réinsérer (sauf tableau 9 recalculé après)
    $lignes_post = $d['lignes'] ?? [];
    try {
        $db->prepare("DELETE FROM rdb_lignes WHERE rapport_id=? AND tableau_no<>9")->execute([$id]);

        for ($tn = 1; $tn <= 8; $tn++) {
            $rows = $lignes_post[$tn] ?? [];
            if (!is_array($rows)) continue;
            $ordre = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $libelle = trim((string)($row['libelle'] ?? ''));
                if ($libelle === '' && empty(array_filter($row, fn($v, $k) => $k !== 'libelle' && trim((string)$v) !== '', ARRAY_FILTER_USE_BOTH)))
                    continue;

                $meta_cols = rdb_tableaux_meta()[$tn]['colonnes'] ?? [];
                $calc_cols = rdb_tableaux_meta()[$tn]['cols_calc'] ?? [];
                $donnees = [];
                foreach (array_keys($meta_cols) as $ck) {
                    if (in_array($ck, $calc_cols, true)) continue;
                    if (isset($row[$ck])) {
                        $num = _rdb_num($row[$ck]);
                        $donnees[$ck] = $num !== null ? $num : trim((string)$row[$ck]);
                    }
                }
                if (in_array($tn, [4, 5, 6, 7, 8], true)) {
                    $cred = (float)($donnees['credit'] ?? 0);
                    $paye = (float)($donnees['paye'] ?? 0);
                    $donnees['taux'] = rdb_calc_taux($cred, $paye);
                }

                $code = trim((string)($row['code_ligne'] ?? '')) ?: _rdb_code_auto($tn, $ordre);
                $db->prepare("
                    INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees, commentaire, justification)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([
                    $id, $tn, $code, $libelle ?: '—', $ordre,
                    json_encode($donnees, JSON_UNESCAPED_UNICODE),
                    trim((string)($row['commentaire'] ?? '')) ?: null,
                    trim((string)($row['justification'] ?? '')) ?: null,
                ]);
                $ordre++;
            }
        }

        rdb_recalculer_tableau9($id);
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_MAJ', "RDB #$id mis à jour");
        return ['succes' => true, 'id' => $id];
    } catch (Throwable $e) {
        error_log('[rdb_sauver_lignes] ' . $e->getMessage());
        return _rdb_err('Erreur lors de l\'enregistrement des tableaux.');
    }
}

function rdb_recalculer_tableau9(int $rapport_id): void {
    $db = get_db();
    $db->prepare("DELETE FROM rdb_lignes WHERE rapport_id=? AND tableau_no=9")->execute([$rapport_id]);

    $natures = rdb_natures_recap();
    $ordre = 0;
    foreach ($natures as $tn_src => $libelle_nat) {
        $s = $db->prepare("SELECT donnees FROM rdb_lignes WHERE rapport_id=? AND tableau_no=?");
        $s->execute([$rapport_id, $tn_src]);
        $cred = 0.0; $paye = 0.0;
        foreach ($s->fetchAll() as $row) {
            $d = json_decode($row['donnees'], true) ?: [];
            $cred += (float)($d['credit'] ?? 0);
            $paye += (float)($d['paye'] ?? 0);
        }
        $taux = rdb_calc_taux($cred, $paye);
        $donnees = ['credit' => $cred, 'paye' => $paye, 'taux' => $taux];
        $db->prepare("
            INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees)
            VALUES (?,?,?,?,?,?)
        ")->execute([
            $rapport_id, 9, 'REC-' . ($ordre + 1), $libelle_nat, $ordre,
            json_encode($donnees, JSON_UNESCAPED_UNICODE),
        ]);
        $ordre++;
    }
}

function rdb_finaliser(int $id): array {
    $rap = rdb_charger($id);
    if (!$rap) return _rdb_err('Rapport introuvable.');
    if ($rap['statut'] === 'finalise') return _rdb_err('Déjà finalisé.');

    try {
        get_db()->prepare("
            UPDATE rapports_rdb SET statut='finalise', finalise_par=?, date_finalisation=NOW(), date_maj=NOW()
            WHERE id=?
        ")->execute([user_id(), $id]);
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_FINALISE', "RDB #$id finalisé");
        return ['succes' => true];
    } catch (Throwable $e) {
        return _rdb_err('Erreur lors de la finalisation.');
    }
}

function rdb_rouvrir(int $id): array {
    $rap = rdb_charger($id);
    if (!$rap) return _rdb_err('Rapport introuvable.');
    if ($rap['statut'] !== 'finalise') return _rdb_err('Ce rapport n\'est pas finalisé.');

    try {
        get_db()->prepare("
            UPDATE rapports_rdb SET statut='brouillon', finalise_par=NULL, date_finalisation=NULL, date_maj=NOW()
            WHERE id=?
        ")->execute([$id]);
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_ROUVRIR', "RDB #$id rouvert pour modification");
        return ['succes' => true];
    } catch (Throwable $e) {
        return _rdb_err('Erreur lors de la réouverture.');
    }
}

function rdb_supprimer(int $id): array {
    $rap = rdb_charger($id);
    if (!$rap) return _rdb_err('Rapport introuvable.');
    if ($rap['statut'] === 'finalise') return _rdb_err('Impossible de supprimer un rapport finalisé.');

    try {
        get_db()->prepare("DELETE FROM rapports_rdb WHERE id=?")->execute([$id]);
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_SUPPR', "RDB #$id supprimé");
        return ['succes' => true];
    } catch (Throwable $e) {
        return _rdb_err('Erreur lors de la suppression.');
    }
}

// ──────────────────────────────────────────────────────────
// PRÉ-REMPLISSAGE DEPUIS SUBVENTIONS
// ──────────────────────────────────────────────────────────

function rdb_prefill_subventions(int $rapport_id): array {
    $rap = rdb_charger($rapport_id);
    if (!$rap) return _rdb_err('Rapport introuvable.');
    if ($rap['statut'] === 'finalise') return _rdb_err('Rapport finalisé.');

    $db   = get_db();
    $an   = (int)$rap['annee'];
    $trim = (int)$rap['trimestre'];
    $added = 0;

    try {
        $db->beginTransaction();

        // Remplacement : pas d'ajout / doublons
        $db->prepare("DELETE FROM rdb_lignes WHERE rapport_id=? AND tableau_no IN (4,5,6)")
           ->execute([$rapport_id]);

        // Tableau 4 — toutes les caisses de la période (année + trimestre)
        $sc = $db->prepare("
            SELECT code_caisse, libelle, montant_initial, montant_utilise, trimestre
            FROM caisses_subvention
            WHERE annee=? AND trimestre=?
            ORDER BY code_caisse
        ");
        $sc->execute([$an, $trim]);
        $ordre = 0;
        foreach ($sc->fetchAll() as $c) {
            $cred = (float)$c['montant_initial'];
            $paye = (float)$c['montant_utilise'];
            $lib  = trim(($c['code_caisse'] ?? '') . ' — ' . ($c['libelle'] ?? ''), ' —');
            $donnees = ['credit' => $cred, 'paye' => $paye, 'taux' => rdb_calc_taux($cred, $paye)];
            $db->prepare("
                INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees)
                VALUES (?,?,?,?,?,?)
            ")->execute([
                $rapport_id, 4, _rdb_code_auto(4, $ordre), $lib ?: 'Caisse', $ordre,
                json_encode($donnees, JSON_UNESCAPED_UNICODE),
            ]);
            $ordre++; $added++;
        }

        // Tableau 5 — dépenses personnel (année + trimestre)
        $sp = $db->prepare("
            SELECT libelle, SUM(montant) AS total FROM depenses_executees
            WHERE statut='execute' AND type_depense='personnel'
              AND periode_annee=? AND periode_trimestre=?
            GROUP BY libelle ORDER BY total DESC
        ");
        $sp->execute([$an, $trim]);
        $ordre = 0;
        foreach ($sp->fetchAll() as $row) {
            $paye = (float)$row['total'];
            $cred = $paye;
            $donnees = ['credit' => $cred, 'paye' => $paye, 'taux' => rdb_calc_taux($cred, $paye)];
            $db->prepare("
                INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees)
                VALUES (?,?,?,?,?,?)
            ")->execute([
                $rapport_id, 5, _rdb_code_auto(5, $ordre),
                $row['libelle'] ?: 'Dépense personnel', $ordre,
                json_encode($donnees, JSON_UNESCAPED_UNICODE),
            ]);
            $ordre++; $added++;
        }

        // Tableau 6 — biens & services
        $sb = $db->prepare("
            SELECT COALESCE(fournisseur_nom, libelle) AS lib, SUM(montant) AS total
            FROM depenses_executees
            WHERE statut='execute' AND type_depense='biens_services'
              AND periode_annee=? AND periode_trimestre=?
            GROUP BY lib ORDER BY total DESC
        ");
        $sb->execute([$an, $trim]);
        $ordre = 0;
        foreach ($sb->fetchAll() as $row) {
            $paye = (float)$row['total'];
            $cred = $paye;
            $donnees = ['credit' => $cred, 'paye' => $paye, 'taux' => rdb_calc_taux($cred, $paye)];
            $db->prepare("
                INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees)
                VALUES (?,?,?,?,?,?)
            ")->execute([
                $rapport_id, 6, _rdb_code_auto(6, $ordre),
                $row['lib'] ?: 'Biens & services', $ordre,
                json_encode($donnees, JSON_UNESCAPED_UNICODE),
            ]);
            $ordre++; $added++;
        }

        // Dossiers DAF exécutés sur la période → fonctionnement
        try {
            $sd = $db->prepare("
                SELECT libelle, montant_ttc FROM dossiers_depenses_daf
                WHERE statut='execute' AND periode_annee=? AND periode_trimestre=?
                ORDER BY date_execution DESC
            ");
            $sd->execute([$an, $trim]);
            foreach ($sd->fetchAll() as $row) {
                $paye = (float)$row['montant_ttc'];
                $cred = $paye;
                $donnees = ['credit' => $cred, 'paye' => $paye, 'taux' => rdb_calc_taux($cred, $paye)];
                $db->prepare("
                    INSERT INTO rdb_lignes (rapport_id, tableau_no, code_ligne, libelle, ordre, donnees)
                    VALUES (?,?,?,?,?,?)
                ")->execute([
                    $rapport_id, 6, _rdb_code_auto(6, $ordre),
                    ($row['libelle'] ?: 'Marché public') . ' (DAF)', $ordre,
                    json_encode($donnees, JSON_UNESCAPED_UNICODE),
                ]);
                $ordre++; $added++;
            }
        } catch (Throwable $e) { /* table absente */ }

        rdb_recalculer_tableau9($rapport_id);
        $db->commit();
        log_activite(user_id(), 'SUBVENTIONS', 'RDB_PREFILL', "RDB #$rapport_id — $added ligne(s) importée(s) (remplacement)");
        return ['succes' => true, 'added' => $added];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[rdb_prefill] ' . $e->getMessage());
        return _rdb_err('Erreur lors de l\'import Subventions.');
    }
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS
// ──────────────────────────────────────────────────────────

function rdb_doc_ajouter(int $rapport_id, ?array $fichier, string $type = 'annexe'): array {
    if (!$fichier || empty($fichier['name']) || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
        return _rdb_err('Aucun fichier reçu ou erreur d\'upload.');
    if ($fichier['size'] > RDB_MAX_SIZE) return _rdb_err('Fichier trop volumineux (max 20 Mo).');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fichier['tmp_name']);
    if (!in_array($mime, RDB_MIME_OK, true)) return _rdb_err('Type de fichier non autorisé (' . $mime . ').');

    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $dir = RDB_UPLOAD_DIR . $rapport_id . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return _rdb_err('Impossible de créer le dossier d\'upload.');
    }

    $stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($fichier['tmp_name'], $dir . $stockage))
        return _rdb_err('Impossible de déplacer le fichier.');

    $chemin_rel = 'uploads/rdb/' . $rapport_id . '/' . $stockage;
    try {
        get_db()->prepare("
            INSERT INTO rdb_documents
              (rapport_id,type_document,nom_original,nom_stockage,chemin_fichier,type_mime,taille_fichier,extension,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $rapport_id, $type, $fichier['name'], $stockage, $chemin_rel,
            $mime, $fichier['size'], $ext, user_id(),
        ]);
        return ['succes' => true, 'nom' => $fichier['name']];
    } catch (Throwable $e) {
        error_log('[rdb_doc_ajouter] ' . $e->getMessage());
        @unlink($dir . $stockage);
        return _rdb_err('Erreur base de données (document).');
    }
}

function rdb_doc_supprimer(int $doc_id, int $rapport_id): array {
    $doc = rdb_doc_get($doc_id);
    if (!$doc || (int)$doc['rapport_id'] !== $rapport_id) return _rdb_err('Document introuvable.');

    $rap = rdb_charger($rapport_id);
    if ($rap && ($rap['statut'] ?? '') === 'finalise') return _rdb_err('Rapport finalisé.');

    $abs = APP_ROOT . '/' . ltrim((string)$doc['chemin_fichier'], '/');
    try {
        get_db()->prepare("DELETE FROM rdb_documents WHERE id=? AND rapport_id=?")->execute([$doc_id, $rapport_id]);
        if (is_file($abs)) @unlink($abs);
        return ['succes' => true];
    } catch (Throwable $e) {
        return _rdb_err('Suppression impossible.');
    }
}

function rdb_doc_get(int $doc_id): ?array {
    try {
        $s = get_db()->prepare("SELECT * FROM rdb_documents WHERE id=?");
        $s->execute([$doc_id]);
        return $s->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}
