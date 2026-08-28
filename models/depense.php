<?php
// ============================================================
// models/depense.php — Sous-module « Exécuter dépenses »
// Circuit simplifié : biens & services / personnel.
// L'exécution défalque la caisse choisie avec traçabilité.
// Dépend de models/subvention.php (sub_fmt, sub_categories).
// ============================================================

define('DEP_UPLOAD_DIR', UPLOAD_DIR . 'depenses/');
define('DEP_MAX_SIZE',   20 * 1024 * 1024); // 20 Mo
define('DEP_MIME_OK', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv',
    'image/jpeg', 'image/png', 'image/webp',
]);

// Types de pièces internes attendues pour une dépense biens/services
function depense_types_pieces(): array {
    return [
        'lettre_paiement' => 'Lettre de paiement',
        'bon_engagement'  => 'Bon d\'engagement',
        'mandat'          => 'Mandat de paiement',
        'liquidation'     => 'Liquidation',
        'autre'           => 'Autre pièce',
    ];
}

// Documents du fournisseur à joindre lors d'une dépense biens/services.
// (Saisis dans le formulaire car ils expirent — plus de reprise depuis la fiche.)
function depense_types_pieces_fournisseur(): array {
    return [
        'nif'       => 'NIF',
        'rccm'      => 'RCCM',
        'quittance' => 'Quittance',
        'quitus'    => 'Quitus',
    ];
}

// Régimes fiscaux (mêmes taux que Marchés Publics).
function depense_regimes(): array {
    return [
        'tva'       => ['Avec TVA (18 %)',       0.18],
        'precompte' => ['Avec précompte (20 %)', 0.20],
        'sans_tva'  => ['Sans taxe',             0.00],
    ];
}

/**
 * Calcule la taxe et le total TTC à partir d'un montant HT et d'un régime.
 * Algorithme identique à Marchés Publics : ttc = ht + (ht * taux).
 */
function depense_calc_fiscal(float $ht, string $regime): array {
    $taux = match ($regime) {
        'tva'       => 0.18,
        'precompte' => 0.20,
        default     => 0.00,
    };
    $taxe = round($ht * $taux, 2);
    return ['taxe' => $taxe, 'ttc' => round($ht + $taxe, 2)];
}

// ──────────────────────────────────────────────────────────
// LISTES POUR LES FORMULAIRES
// ──────────────────────────────────────────────────────────

/** Caisses actives disposant d'un solde > 0 (choix du « trimestre » à débiter). */
function depense_caisses_eligibles(): array {
    $s = get_db()->query("
        SELECT id, code_caisse, libelle, annee, trimestre, montant_disponible
        FROM caisses_subvention
        WHERE statut='active' AND montant_disponible > 0
        ORDER BY annee DESC, trimestre ASC
    ");
    return $s->fetchAll();
}

/** Fournisseurs validés sélectionnables. */
function depense_fournisseurs(): array {
    $s = get_db()->query("
        SELECT id, nom_entreprise, type_fournisseur, categorie, statut
        FROM fournisseurs
        WHERE statut IN ('valide','en_attente')
        ORDER BY nom_entreprise
    ");
    return $s->fetchAll();
}

/** Pièces déjà enregistrées sur la fiche d'un fournisseur (reprises en lecture). */
function depense_fournisseur_pieces(int $fournisseur_id): array {
    $s = get_db()->prepare("
        SELECT id, type_document, nom_original
        FROM documents_fournisseurs
        WHERE fournisseur_id = ?
        ORDER BY type_document, date_upload
    ");
    $s->execute([$fournisseur_id]);
    return $s->fetchAll();
}

/** Employés actifs (pour les dépenses de personnel). */
function depense_employes_actifs(): array {
    $s = get_db()->query("
        SELECT u.id, CONCAT(u.prenoms,' ',u.nom) AS nom_complet, u.matricule,
               g.libelle AS groupe
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.statut = 'ACTIF'
        ORDER BY u.nom, u.prenoms
    ");
    return $s->fetchAll();
}

// ──────────────────────────────────────────────────────────
// EXÉCUTION D'UNE DÉPENSE
// ──────────────────────────────────────────────────────────
function _dep_err(string $m): array { return ['succes' => false, 'message' => $m]; }

/** Retrouve une catégorie de dépense par libellé, ou la crée (saisie manuelle). */
function depense_categorie_trouver_ou_creer(string $libelle): ?int {
    $libelle = trim($libelle);
    if ($libelle === '') return null;
    $db = get_db();
    $s = $db->prepare("SELECT id FROM categories_depenses WHERE libelle = ? LIMIT 1");
    $s->execute([$libelle]);
    $id = $s->fetchColumn();
    if ($id) return (int)$id;

    $code = 'L' . substr(bin2hex(random_bytes(4)), 0, 6); // code unique court
    $ordre = (int)$db->query("SELECT COALESCE(MAX(ordre_affichage),0)+1 FROM categories_depenses")->fetchColumn();
    try {
        $db->prepare("INSERT INTO categories_depenses (code_categorie,libelle,ordre_affichage,actif) VALUES (?,?,?,1)")
           ->execute([$code, $libelle, $ordre]);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        error_log('[depense_categorie_creer] ' . $e->getMessage());
        return null;
    }
}

/** Génère le prochain numéro DEP-AAAA-NNNN (dépenses exécutées de l'année). */
function _depense_numero_suivant(PDO $db): string {
    $an  = (int)date('Y');
    $cnt = (int)$db->query("SELECT COUNT(*) FROM depenses_executees WHERE statut='execute' AND YEAR(date_execution) = $an")->fetchColumn();
    return sprintf('DEP-%d-%04d', $an, $cnt + 1);
}

// ──────────────────────────────────────────────────────────
// BROUILLONS (enregistrer / reprendre plus tard)
// ──────────────────────────────────────────────────────────

/** Crée un brouillon vide pour le type choisi et renvoie son id. */
function depense_creer_brouillon(string $type): array {
    if (!in_array($type, ['biens_services', 'personnel'], true)) return _dep_err('Type de dépense invalide.');
    $db  = get_db();
    $uid = user_id();
    try {
        $db->prepare("
            INSERT INTO depenses_executees (type_depense, statut, cree_par, date_creation)
            VALUES (?, 'brouillon', ?, NOW())
        ")->execute([$type, $uid]);
        return ['succes' => true, 'id' => (int)$db->lastInsertId()];
    } catch (Throwable $e) {
        error_log('[depense_creer_brouillon] ' . $e->getMessage());
        return _dep_err('Impossible de créer le brouillon.');
    }
}

/** Enregistre les champs saisis d'un brouillon (sans débiter la caisse) + pièces jointes. */
function depense_sauver_brouillon(int $id, array $d, array $files = []): array {
    $db = get_db();

    $s = $db->prepare("SELECT * FROM depenses_executees WHERE id=? AND statut='brouillon'");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return _dep_err('Brouillon introuvable.');

    $type      = $row['type_depense'];
    $caisse_id = (int)($d['caisse_id'] ?? 0) ?: null;
    $cat_id    = (int)($d['categorie_id'] ?? 0) ?: null;
    $motif     = trim((string)($d['motif'] ?? '')) ?: null;

    if (!$cat_id && !empty($d['categorie_libelle'])) {
        $cat_id = depense_categorie_trouver_ou_creer((string)$d['categorie_libelle']);
    }

    // ── Montant : biens/services = HT + régime fiscal ; personnel = montant direct ──
    $montant_ht = null; $montant_taxe = null; $regime = null; $montant = null;
    if ($type === 'biens_services') {
        $regime = in_array($d['regime_fiscal'] ?? '', ['tva','precompte','sans_tva'], true)
                  ? $d['regime_fiscal'] : null;
        $montant_ht = ($d['montant_ht'] ?? '') !== '' ? round((float)$d['montant_ht'], 2) : null;
        if ($montant_ht !== null && $regime !== null) {
            $calc = depense_calc_fiscal($montant_ht, $regime);
            $montant_taxe = $calc['taxe'];
            $montant      = $calc['ttc'];
        } elseif ($montant_ht !== null) {
            $montant = $montant_ht; // régime pas encore choisi (brouillon)
        }
    } else {
        $montant = ($d['montant'] ?? '') !== '' ? round((float)$d['montant'], 2) : null;
    }

    // Période issue de la caisse sélectionnée (si choisie)
    $an = null; $tr = null;
    if ($caisse_id) {
        $sc = $db->prepare("SELECT annee, trimestre FROM caisses_subvention WHERE id=?");
        $sc->execute([$caisse_id]);
        if ($c = $sc->fetch()) { $an = (int)$c['annee']; $tr = (int)$c['trimestre']; }
    }

    // Champs spécifiques + libellé
    $fournisseur_id = null; $fournisseur_nom = null;
    $employe_id = null; $employe_nom = null; $compte = null; $libelle = null;

    if ($type === 'biens_services') {
        $fournisseur_id = (int)($d['fournisseur_id'] ?? 0) ?: null;
        if ($fournisseur_id) {
            $sf = $db->prepare("SELECT nom_entreprise FROM fournisseurs WHERE id=?");
            $sf->execute([$fournisseur_id]);
            $fournisseur_nom = $sf->fetchColumn() ?: null;
            $libelle = $motif ?: ('Dépense biens & services — ' . $fournisseur_nom);
        }
    } else {
        $employe_id = (int)($d['employe_id'] ?? 0) ?: null;
        $compte     = trim((string)($d['compte_credite'] ?? '')) ?: null;
        if ($employe_id) {
            $su = $db->prepare("SELECT CONCAT(prenoms,' ',nom) FROM utilisateurs WHERE id=?");
            $su->execute([$employe_id]);
            $employe_nom = $su->fetchColumn() ?: null;
            $libelle = 'Dépense personnel — ' . $employe_nom;
        }
    }

    try {
        $db->prepare("
            UPDATE depenses_executees SET
                caisse_id=?, categorie_id=?, libelle=?, montant=?,
                montant_ht=?, montant_taxe=?, regime_fiscal=?,
                fournisseur_id=?, fournisseur_nom=?, employe_id=?, employe_nom=?,
                compte_credite=?, motif=?, periode_annee=?, periode_trimestre=?
            WHERE id=? AND statut='brouillon'
        ")->execute([
            $caisse_id, $cat_id, $libelle, $montant,
            $montant_ht, $montant_taxe, $regime,
            $fournisseur_id, $fournisseur_nom, $employe_id, $employe_nom,
            $compte, $motif, $an, $tr, $id,
        ]);
    } catch (Throwable $e) {
        error_log('[depense_sauver_brouillon] ' . $e->getMessage());
        return _dep_err('Erreur lors de l\'enregistrement du brouillon.');
    }

    // Pièces ajoutées au fil de l'eau (fournisseur + internes pour biens/services,
    // élément de preuve pour le personnel).
    if (!empty($files)) {
        $types_attendus = $type === 'biens_services'
            ? (depense_types_pieces_fournisseur() + depense_types_pieces())
            : ['preuve' => 'Élément de preuve'];
        foreach ($types_attendus as $key => $lbl) {
            if (!empty($files[$key]['name']) && ($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                depense_doc_ajouter($id, $files[$key], $key);
            }
        }
    }

    return ['succes' => true, 'id' => $id];
}

/** Finalise un brouillon : valide, débite la caisse et passe en « execute ». */
function depense_finaliser(int $id, array $d, array $files = []): array {
    // 1) Persister la dernière saisie + pièces
    $sv = depense_sauver_brouillon($id, $d, $files);
    if (!$sv['succes']) return $sv;

    $db  = get_db();
    $uid = user_id();

    $row = $db->prepare("SELECT * FROM depenses_executees WHERE id=? AND statut='brouillon'");
    $row->execute([$id]);
    $r = $row->fetch();
    if (!$r) return _dep_err('Brouillon introuvable ou déjà exécuté.');

    $type      = $r['type_depense'];
    $caisse_id = (int)$r['caisse_id'];
    $montant   = round((float)$r['montant'], 2);
    $cat_id    = $r['categorie_id'] ? (int)$r['categorie_id'] : null;

    // Validations métier
    if ($caisse_id < 1) return _dep_err('Veuillez choisir la caisse (trimestre) à débiter.');
    if ($montant <= 0)  return _dep_err('Le montant doit être supérieur à 0.');
    if ($type === 'biens_services') {
        if (!$r['fournisseur_id']) return _dep_err('Veuillez sélectionner un fournisseur.');
        if ((float)($r['montant_ht'] ?? 0) <= 0) return _dep_err('Veuillez saisir le montant hors taxes.');
        if (!in_array($r['regime_fiscal'] ?? '', ['tva','precompte','sans_tva'], true))
            return _dep_err('Veuillez choisir le régime (TVA, précompte ou sans taxe).');
    } else {
        if (!$r['employe_id'])                  return _dep_err('Veuillez sélectionner un employé.');
        if (trim((string)$r['compte_credite'])==='') return _dep_err('Veuillez renseigner le compte à créditer.');
        if (trim((string)$r['motif'])==='')     return _dep_err('Veuillez renseigner le motif du paiement.');
    }

    // Caisse
    $sc = $db->prepare("SELECT * FROM caisses_subvention WHERE id=?");
    $sc->execute([$caisse_id]);
    $caisse = $sc->fetch();
    if (!$caisse) return _dep_err('Caisse introuvable.');
    if ($caisse['statut'] !== 'active') return _dep_err('Cette caisse n\'est pas active.');
    if ((float)$caisse['montant_disponible'] < $montant)
        return _dep_err('Solde insuffisant : ' . sub_fmt((float)$caisse['montant_disponible']) . ' disponible.');

    try {
        $db->beginTransaction();

        $numero = _depense_numero_suivant($db);
        $db->prepare("
            UPDATE depenses_executees
            SET statut='execute', numero=?, execute_par=?, date_execution=NOW(),
                periode_annee=?, periode_trimestre=?
            WHERE id=? AND statut='brouillon'
        ")->execute([$numero, $uid, (int)$caisse['annee'], (int)$caisse['trimestre'], $id]);

        // Défalquer la caisse
        $db->prepare("
            UPDATE caisses_subvention
            SET montant_utilise    = montant_utilise + ?,
                montant_disponible = montant_disponible - ?
            WHERE id=?
        ")->execute([$montant, $montant, $caisse_id]);
        $db->prepare("UPDATE caisses_subvention SET statut='epuisee' WHERE id=? AND montant_disponible<=0")
           ->execute([$caisse_id]);

        if ($cat_id) {
            $db->prepare("
                UPDATE lignes_budgetaires SET montant_consomme = montant_consomme + ?
                WHERE caisse_id=? AND categorie_id=?
            ")->execute([$montant, $caisse_id, $cat_id]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[depense_finaliser] ' . $e->getMessage());
        return _dep_err('Erreur lors de l\'exécution de la dépense.');
    }

    log_activite($uid, 'SUBVENTIONS', 'DEPENSE_EXECUTION',
        "Dépense $numero ($type) — " . sub_fmt($montant) . " sur caisse " . $caisse['code_caisse']);

    return ['succes' => true, 'id' => $id, 'numero' => $numero];
}

/** Liste les brouillons d'un utilisateur (pour reprise). */
function depense_brouillons_lister(?int $uid = null): array {
    $db = get_db();
    $sql = "
        SELECT d.*, c.code_caisse, cat.libelle AS categorie_libelle,
               (SELECT COUNT(*) FROM depenses_documents dd WHERE dd.depense_id=d.id) AS nb_docs
        FROM depenses_executees d
        LEFT JOIN caisses_subvention c    ON c.id = d.caisse_id
        LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
        WHERE d.statut='brouillon'";
    $p = [];
    if ($uid !== null) { $sql .= ' AND d.cree_par=?'; $p[] = $uid; }
    $sql .= ' ORDER BY d.date_creation DESC';
    $s = $db->prepare($sql);
    $s->execute($p);
    return $s->fetchAll();
}

/** Supprime un brouillon (et ses pièces) appartenant à l'utilisateur. */
function depense_brouillon_supprimer(int $id, ?int $uid = null): array {
    $db = get_db();
    $s = $db->prepare("SELECT cree_par FROM depenses_executees WHERE id=? AND statut='brouillon'");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return _dep_err('Brouillon introuvable.');
    if ($uid !== null && (int)$row['cree_par'] !== $uid) return _dep_err('Accès refusé à ce brouillon.');

    // Suppression des fichiers physiques
    $sd = $db->prepare("SELECT chemin_fichier FROM depenses_documents WHERE depense_id=?");
    $sd->execute([$id]);
    foreach ($sd->fetchAll() as $doc) {
        $abs = APP_ROOT . '/' . ltrim($doc['chemin_fichier'], '/');
        if (is_file($abs)) @unlink($abs);
    }
    // depenses_documents supprimés en cascade (FK ON DELETE CASCADE)
    $db->prepare("DELETE FROM depenses_executees WHERE id=? AND statut='brouillon'")->execute([$id]);
    return ['succes' => true];
}

function depense_executer(array $d, array $files = []): array {
    $db  = get_db();
    $uid = user_id();

    $type      = $d['type_depense'] ?? '';
    $caisse_id = (int)($d['caisse_id'] ?? 0);
    $montant   = round((float)($d['montant'] ?? 0), 2);
    $cat_id    = (int)($d['categorie_id'] ?? 0) ?: null;
    $motif     = trim((string)($d['motif'] ?? ''));

    // Catégorie saisie manuellement (« Autre… ») : on la retrouve ou on la crée.
    if (!$cat_id && !empty($d['categorie_libelle'])) {
        $cat_id = depense_categorie_trouver_ou_creer((string)$d['categorie_libelle']);
    }

    if (!in_array($type, ['biens_services', 'personnel'], true)) return _dep_err('Type de dépense invalide.');
    if ($caisse_id < 1) return _dep_err('Veuillez choisir la caisse (trimestre) à débiter.');
    if ($montant <= 0)  return _dep_err('Le montant doit être supérieur à 0.');

    // Caisse
    $sc = $db->prepare("SELECT * FROM caisses_subvention WHERE id=?");
    $sc->execute([$caisse_id]);
    $caisse = $sc->fetch();
    if (!$caisse) return _dep_err('Caisse introuvable.');
    if ($caisse['statut'] !== 'active') return _dep_err('Cette caisse n\'est pas active.');
    if ((float)$caisse['montant_disponible'] < $montant)
        return _dep_err('Solde insuffisant : ' . sub_fmt((float)$caisse['montant_disponible']) . ' disponible.');

    // Champs spécifiques par type
    $fournisseur_id = null; $fournisseur_nom = null;
    $employe_id = null; $employe_nom = null; $compte = null; $libelle = null;

    if ($type === 'biens_services') {
        $fournisseur_id = (int)($d['fournisseur_id'] ?? 0) ?: null;
        if (!$fournisseur_id) return _dep_err('Veuillez sélectionner un fournisseur.');
        $sf = $db->prepare("SELECT nom_entreprise FROM fournisseurs WHERE id=?");
        $sf->execute([$fournisseur_id]);
        $fournisseur_nom = $sf->fetchColumn();
        if ($fournisseur_nom === false) return _dep_err('Fournisseur introuvable.');
        $libelle = $motif !== '' ? $motif : ('Dépense biens & services — ' . $fournisseur_nom);
    } else {
        $employe_id = (int)($d['employe_id'] ?? 0) ?: null;
        if (!$employe_id) return _dep_err('Veuillez sélectionner un employé.');
        $compte = trim((string)($d['compte_credite'] ?? ''));
        if ($compte === '') return _dep_err('Veuillez renseigner le compte à créditer.');
        if ($motif === '')  return _dep_err('Veuillez renseigner le motif du paiement.');
        $su = $db->prepare("SELECT CONCAT(prenoms,' ',nom) FROM utilisateurs WHERE id=?");
        $su->execute([$employe_id]);
        $employe_nom = $su->fetchColumn();
        if ($employe_nom === false) return _dep_err('Employé introuvable.');
        $libelle = 'Dépense personnel — ' . $employe_nom;
    }

    try {
        $db->beginTransaction();

        // Numéro séquentiel par année
        $numero = _depense_numero_suivant($db);

        $db->prepare("
            INSERT INTO depenses_executees
              (numero,type_depense,caisse_id,categorie_id,libelle,montant,
               fournisseur_id,fournisseur_nom,employe_id,employe_nom,compte_credite,motif,
               periode_annee,periode_trimestre,execute_par,date_execution)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ")->execute([
            $numero, $type, $caisse_id, $cat_id, $libelle, $montant,
            $fournisseur_id, $fournisseur_nom, $employe_id, $employe_nom, $compte, ($motif !== '' ? $motif : null),
            (int)$caisse['annee'], (int)$caisse['trimestre'], $uid,
        ]);
        $id = (int)$db->lastInsertId();

        // Défalquer la caisse
        $db->prepare("
            UPDATE caisses_subvention
            SET montant_utilise    = montant_utilise + ?,
                montant_disponible = montant_disponible - ?
            WHERE id=?
        ")->execute([$montant, $montant, $caisse_id]);
        $db->prepare("UPDATE caisses_subvention SET statut='epuisee' WHERE id=? AND montant_disponible<=0")
           ->execute([$caisse_id]);

        // Mise à jour de la ligne budgétaire consommée (si catégorie connue)
        if ($cat_id) {
            $db->prepare("
                UPDATE lignes_budgetaires SET montant_consomme = montant_consomme + ?
                WHERE caisse_id=? AND categorie_id=?
            ")->execute([$montant, $caisse_id, $cat_id]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[depense_executer] ' . $e->getMessage());
        return _dep_err('Erreur lors de l\'exécution de la dépense.');
    }

    // Pièces internes (hors transaction : un échec de fichier ne doit pas annuler le paiement)
    if ($type === 'biens_services' && !empty($files)) {
        foreach (depense_types_pieces() as $key => $lbl) {
            if (!empty($files[$key]['name']) && ($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                depense_doc_ajouter($id, $files[$key], $key);
            }
        }
    }

    log_activite($uid, 'SUBVENTIONS', 'DEPENSE_EXECUTION',
        "Dépense $numero ($type) — " . sub_fmt($montant) . " sur caisse " . $caisse['code_caisse']);

    return ['succes' => true, 'id' => $id, 'numero' => $numero];
}

// ──────────────────────────────────────────────────────────
// DOCUMENTS
// ──────────────────────────────────────────────────────────
function depense_doc_ajouter(int $depense_id, array $fichier, string $type_doc): array {
    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
        return _dep_err('Erreur upload (#' . ($fichier['error'] ?? '?') . ')');
    if ($fichier['size'] > DEP_MAX_SIZE)
        return _dep_err('Fichier trop volumineux (max 20 Mo).');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fichier['tmp_name']);
    if (!in_array($mime, DEP_MIME_OK, true))
        return _dep_err('Type de fichier non autorisé.');

    $ext = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    $dir = DEP_UPLOAD_DIR . $depense_id . '/';
    if (!is_dir($dir)) mkdir($dir, 0750, true);

    $stockage = bin2hex(random_bytes(16)) . '.' . $ext;
    $abs      = $dir . $stockage;
    if (!move_uploaded_file($fichier['tmp_name'], $abs))
        return _dep_err('Impossible de déplacer le fichier.');

    $chemin_rel = 'uploads/depenses/' . $depense_id . '/' . $stockage;

    try {
        get_db()->prepare("
            INSERT INTO depenses_documents
              (depense_id,type_document,nom_original,nom_stockage,chemin_fichier,type_mime,taille_fichier,extension,depose_par)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $depense_id, $type_doc, $fichier['name'], $stockage,
            $chemin_rel, $mime, $fichier['size'], $ext, user_id(),
        ]);
        return ['succes' => true];
    } catch (Throwable $e) {
        @unlink($abs);
        return _dep_err('Erreur base de données.');
    }
}

function depense_doc_get(int $doc_id): ?array {
    $s = get_db()->prepare("SELECT * FROM depenses_documents WHERE id=?");
    $s->execute([$doc_id]);
    return $s->fetch() ?: null;
}

/**
 * Dépenses biens & services exécutées dont il manque au moins un document
 * fournisseur (NIF, RCCM, quittance, quitus). Sert au rappel automatique.
 */
function depense_documents_fournisseur_manquants(): array {
    $db    = get_db();
    $types = array_keys(depense_types_pieces_fournisseur());
    $in    = "'" . implode("','", $types) . "'";
    $sql = "
        SELECT d.id, d.numero, d.libelle, d.fournisseur_nom, d.montant, d.date_execution,
               (SELECT COUNT(DISTINCT dd.type_document)
                  FROM depenses_documents dd
                 WHERE dd.depense_id = d.id AND dd.type_document IN ($in)) AS nb_pieces_fourn
        FROM depenses_executees d
        WHERE d.statut='execute' AND d.type_depense='biens_services'
        ORDER BY d.date_execution DESC
    ";
    try {
        $rows = $db->query($sql)->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $nb_total = count($types);
    $out = [];
    foreach ($rows as $r) {
        $present = (int)$r['nb_pieces_fourn'];
        if ($present < $nb_total) {
            $r['nb_manquants'] = $nb_total - $present;
            $out[] = $r;
        }
    }
    return $out;
}

/**
 * Ajoute après coup les documents fournisseur (NIF, RCCM, quittance, quitus)
 * à une dépense déjà exécutée. Renvoie le nombre de pièces ajoutées.
 */
function depense_completer_documents(int $id, array $files): array {
    $db = get_db();
    $s  = $db->prepare("SELECT id FROM depenses_executees WHERE id=? AND type_depense='biens_services'");
    $s->execute([$id]);
    if (!$s->fetchColumn()) return _dep_err('Dépense introuvable.');

    $added = 0;
    foreach (array_keys(depense_types_pieces_fournisseur()) as $key) {
        if (!empty($files[$key]['name']) && ($files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $r = depense_doc_ajouter($id, $files[$key], $key);
            if (!empty($r['succes'])) $added++;
        }
    }
    if ($added > 0) {
        log_activite(user_id(), 'SUBVENTIONS', 'DEPENSE_DOCUMENTS',
            "Documents fournisseur complétés ($added) — dépense #$id");
    }
    return ['succes' => true, 'added' => $added];
}

// ──────────────────────────────────────────────────────────
// LECTURE / HISTORIQUE
// ──────────────────────────────────────────────────────────
function depense_lister(array $f = [], int $lim = 20, int $off = 0): array {
    $db = get_db();
    $where = ["d.statut='execute'"]; $p = [];
    if (!empty($f['type']))   { $where[] = 'd.type_depense=?'; $p[] = $f['type']; }
    if (!empty($f['caisse'])) { $where[] = 'd.caisse_id=?';    $p[] = (int)$f['caisse']; }
    if (!empty($f['q'])) {
        $where[] = '(d.numero LIKE ? OR d.libelle LIKE ? OR d.fournisseur_nom LIKE ? OR d.employe_nom LIKE ?)';
        $q = '%'.$f['q'].'%'; $p = array_merge($p, [$q,$q,$q,$q]);
    }
    $cond = implode(' AND ', $where);

    $sc = $db->prepare("SELECT COUNT(*) FROM depenses_executees d WHERE $cond");
    $sc->execute($p);
    $total = (int)$sc->fetchColumn();

    $sd = $db->prepare("
        SELECT d.*,
               c.code_caisse, c.libelle AS caisse_libelle,
               cat.code_categorie, cat.libelle AS categorie_libelle,
               CONCAT(u.prenoms,' ',u.nom) AS executeur_nom
        FROM depenses_executees d
        LEFT JOIN caisses_subvention c    ON c.id = d.caisse_id
        LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
        LEFT JOIN utilisateurs u          ON u.id = d.execute_par
        WHERE $cond
        ORDER BY d.date_execution DESC, d.id DESC
        LIMIT ? OFFSET ?
    ");
    $sd->execute(array_merge($p, [$lim, $off]));
    return ['data' => $sd->fetchAll(), 'total' => $total];
}

function depense_charger(int $id): ?array {
    $db = get_db();
    $s = $db->prepare("
        SELECT d.*,
               c.code_caisse, c.libelle AS caisse_libelle,
               cat.code_categorie, cat.libelle AS categorie_libelle,
               CONCAT(u.prenoms,' ',u.nom) AS executeur_nom
        FROM depenses_executees d
        LEFT JOIN caisses_subvention c    ON c.id = d.caisse_id
        LEFT JOIN categories_depenses cat ON cat.id = d.categorie_id
        LEFT JOIN utilisateurs u          ON u.id = d.execute_par
        WHERE d.id=?
    ");
    $s->execute([$id]);
    $d = $s->fetch();
    if (!$d) return null;

    $sd = $db->prepare("SELECT * FROM depenses_documents WHERE depense_id=? ORDER BY type_document, date_upload");
    $sd->execute([$id]);
    $d['documents'] = $sd->fetchAll();
    return $d;
}

function depense_stats(): array {
    $db = get_db();
    return [
        'nb'                => (int)$db->query("SELECT COUNT(*) FROM depenses_executees WHERE statut='execute'")->fetchColumn(),
        'total'             => (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM depenses_executees WHERE statut='execute'")->fetchColumn(),
        'total_personnel'   => (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM depenses_executees WHERE statut='execute' AND type_depense='personnel'")->fetchColumn(),
        'total_biens'       => (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM depenses_executees WHERE statut='execute' AND type_depense='biens_services'")->fetchColumn(),
    ];
}
