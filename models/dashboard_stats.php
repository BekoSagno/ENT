<?php
// ============================================================
// models/dashboard_stats.php
// Statistiques dédiées au tableau de bord :
//   1) Rapports (fiches) du jour : envoyés vs en attente
//   2) Utilisateurs actifs sans connexion récente / jamais connectés
// ============================================================

/**
 * Rapports du jour.
 * Retourne la liste des utilisateurs devant remplir une fiche,
 * répartis entre ceux qui l'ont ENVOYÉE et ceux qui NE l'ont PAS encore envoyée.
 *
 * - "Envoyé"  = fiche au statut SOUMISE ou VALIDEE pour la date donnée.
 * - "En attente" = aucune fiche, brouillon non soumis, ou fiche rejetée.
 * - Les groupes qui ne remplissent pas de fiche (SUPER_ADMIN, DG) sont exclus.
 *
 * @param string|null $date  Date au format Y-m-d (aujourd'hui par défaut)
 */
function suivi_rapports_du_jour(?string $date = null): array {
    $date = $date ?? date('Y-m-d');
    $db   = get_db();

    $stmt = $db->prepare("
        SELECT u.id, u.matricule, u.photo,
               CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               g.libelle AS groupe, g.code AS groupe_code,
               f.id AS fiche_id, f.statut
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        LEFT JOIN suivi_fiches_journalieres f
               ON f.utilisateur_id = u.id AND f.date_fiche = ?
        WHERE u.statut = 'ACTIF'
          AND g.code NOT IN ('SUPER_ADMIN','DG')
        ORDER BY u.nom, u.prenoms
    ");
    $stmt->execute([$date]);

    $soumis    = [];
    $manquants = [];
    foreach ($stmt->fetchAll() as $r) {
        if (in_array($r['statut'] ?? '', ['SOUMISE','VALIDEE'], true)) {
            $soumis[] = $r;
        } else {
            $manquants[] = $r; // aucune fiche, brouillon, ou rejetée
        }
    }

    return [
        'date'         => $date,
        'soumis'       => $soumis,
        'manquants'    => $manquants,
        'nb_soumis'    => count($soumis),
        'nb_manquants' => count($manquants),
        'total'        => count($soumis) + count($manquants),
    ];
}

/**
 * Utilisateurs actifs "silencieux" :
 * jamais connectés (derniere_connexion IS NULL)
 * ou sans connexion depuis plus de $jours jours.
 *
 * @param int $jours  Seuil d'inactivité en jours (7 par défaut)
 * @return array      Liste des utilisateurs concernés (les NULL en premier)
 */
function utilisateurs_inactifs_connexion(int $jours = 7): array {
    $db    = get_db();
    $seuil = date('Y-m-d H:i:s', strtotime("-{$jours} days"));

    $stmt = $db->prepare("
        SELECT u.id, u.matricule,
               CONCAT(u.prenoms,' ',u.nom) AS nom_complet,
               u.derniere_connexion, g.libelle AS groupe
        FROM utilisateurs u
        JOIN groupes_utilisateurs g ON g.id = u.groupe_id
        WHERE u.statut = 'ACTIF'
          AND (u.derniere_connexion IS NULL OR u.derniere_connexion < ?)
        ORDER BY (u.derniere_connexion IS NULL) DESC, u.derniere_connexion ASC
    ");
    $stmt->execute([$seuil]);
    return $stmt->fetchAll();
}
