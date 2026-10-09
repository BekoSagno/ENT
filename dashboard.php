<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/utilisateur.php';
require_once __DIR__ . '/models/suivi.php';
require_once __DIR__ . '/models/dashboard_stats.php';

session_demarrer();
exiger_connexion();

$user = user_courant();

// DG / Super Admin : ne remplissent pas de fiche → vue "équipe" au lieu de la vue personnelle
$est_gestionnaire = in_array($user['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG'], true);

// ── Stats admin ──
$stats = statistiques_utilisateurs();

// Seuil d'inactivité de connexion (en jours) — modifiable au besoin
$seuil_inactif = 7;

// Ces stats ne concernent que les administrateurs (carte + panneau)
$rapports_jour = ['soumis'=>[], 'manquants'=>[], 'nb_soumis'=>0, 'nb_manquants'=>0, 'total'=>0];
$inactifs_cnx  = [];
if (a_permission('UTILISATEURS','VOIR')) {
    $rapports_jour = suivi_rapports_du_jour(date('Y-m-d'));
    $inactifs_cnx  = utilisateurs_inactifs_connexion($seuil_inactif);
}

// Actifs réellement connectés = comptes actifs MOINS ceux sans connexion récente.
// (les inactifs de connexion sont un sous-ensemble des comptes au statut ACTIF)
$nb_actifs_connectes = max(0, (int)($stats['actifs'] ?? 0) - count($inactifs_cnx));

// ── Semaine de l'utilisateur connecté (vue personnelle) ──
$semaine = suivi_statistiques_semaine((int)$user['id']);
$semaine = is_array($semaine) ? $semaine : [];

// ── Vue équipe de la semaine (pour les gestionnaires) ──
$semaine_globale = [];
if ($est_gestionnaire && function_exists('suivi_statistiques_semaine_globale')) {
    $semaine_globale = suivi_statistiques_semaine_globale();
}

// ── Fiche du jour — TOUJOURS passer l'id et la date ──
$fiche_jour = suivi_fiche_du_jour((int)$user['id'], date('Y-m-d'));

// ── Fiches à valider ──
$a_valider = [];
if (a_permission('SUIVI_QUOTIDIEN', 'VALIDER') && function_exists('suivi_fiches_a_valider')) {
    $raw = suivi_fiches_a_valider();
    // Selon l'implémentation, la fonction peut retourner un tableau ou un résultat de requête
    $a_valider = is_array($raw) ? $raw : [];
}

$page_titre      = 'Tableau de bord';
$page_breadcrumb = 'Accueil';
$module_actif    = 'DASHBOARD';

include __DIR__ . '/views/layout/header.php';

// Petit rendu réutilisable d'une ligne "rapport du jour"
$ligne_rapport = function(array $r, string $type) {
    $parts = preg_split('/\s+/', trim($r['nom_complet'] ?? ''));
    $ini   = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));

    if ($type === 'soumis') {
        $badge_bg = '#dcfce7'; $badge_tx = '#15803d'; $badge_ico = 'fa-circle-check';
        $badge_lbl = ($r['statut'] === 'VALIDEE') ? 'Validée' : 'Envoyée';
        $av_bg = '#22c55e';
    } else {
        // Distinguer brouillon en cours / rejetée / rien
        if (($r['statut'] ?? '') === 'BROUILLON') { $badge_lbl = 'Brouillon'; }
        elseif (($r['statut'] ?? '') === 'REJETEE') { $badge_lbl = 'Rejetée'; }
        else { $badge_lbl = 'Non envoyée'; }
        $badge_bg = '#fee2e2'; $badge_tx = '#b91c1c'; $badge_ico = 'fa-circle-exclamation';
        $av_bg = '#f87171';
    }
    ?>
    <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
      <div style="width:32px;height:32px;border-radius:50%;background:<?= $av_bg ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;">
        <?= e($ini) ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.82rem;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['nom_complet']) ?></div>
        <div style="font-size:.72rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['groupe']) ?></div>
      </div>
      <span style="background:<?= $badge_bg ?>;color:<?= $badge_tx ?>;padding:3px 9px;border-radius:20px;font-size:.7rem;font-weight:700;white-space:nowrap;">
        <i class="fa-solid <?= $badge_ico ?>"></i> <?= $badge_lbl ?>
      </span>
    </div>
    <?php
};
?>

<!-- Bienvenue -->
<div style="background:linear-gradient(135deg,var(--blue-700),var(--blue-500));border-radius:var(--radius);padding:24px 28px;margin-bottom:24px;display:flex;align-items:center;gap:20px;color:#fff;box-shadow:0 8px 24px rgba(26,92,204,.3);">
  <div style="flex:1;">
    <div style="font-size:.85rem;opacity:.8;margin-bottom:4px;">Bonjour 👋</div>
    <h2 style="font-size:1.4rem;font-weight:800;margin-bottom:4px;"><?= e($user['prenoms'] . ' ' . $user['nom']) ?></h2>
    <div style="font-size:.82rem;opacity:.75;"><?= e($user['groupe_libelle'] ?? '') ?> · <?= strftime_fr(date('Y-m-d')) ?></div>
  </div>
  <div style="text-align:right;">
    <?php if ($est_gestionnaire): ?>
      <div style="font-size:.82rem;opacity:.8;margin-bottom:8px;">Espace de pilotage du suivi</div>
      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/modules/suivi/index.php" style="background:rgba(255,255,255,.2);color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600;border:1px solid rgba(255,255,255,.3);">
          <i class="fa-solid fa-clipboard-check"></i> Fiches à valider
        </a>
        <a href="<?= APP_URL ?>/modules/suivi/statistiques.php" style="background:rgba(255,255,255,.2);color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600;border:1px solid rgba(255,255,255,.3);">
          <i class="fa-solid fa-chart-pie"></i> Statistiques
        </a>
      </div>
    <?php elseif (!$fiche_jour): ?>
      <div style="font-size:.82rem;opacity:.8;margin-bottom:8px;">Vous n'avez pas encore rempli votre fiche du jour</div>
      <a href="<?= APP_URL ?>/modules/suivi/fiche.php" style="background:rgba(255,255,255,.2);color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600;border:1px solid rgba(255,255,255,.3);">
        <i class="fa-solid fa-plus"></i> Créer ma fiche
      </a>
    <?php else: ?>
      <div style="font-size:.82rem;opacity:.8;margin-bottom:8px;">Fiche du jour : <?= badge_statut($fiche_jour['statut']) ?></div>
      <a href="<?= APP_URL ?>/modules/suivi/fiche.php" style="background:rgba(255,255,255,.2);color:#fff;padding:9px 18px;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:600;border:1px solid rgba(255,255,255,.3);">
        <i class="fa-solid fa-pen"></i> Voir / Modifier
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- Raccourcis modules (DG / Super Admin) -->
<?php if (in_array($user['groupe_code'] ?? '', ['SUPER_ADMIN', 'DG'], true)):
  $rc_acces   = charger_permissions_utilisateur();
  $rc_uid     = (int)$user['id'];
  $rc_compter = function (callable $f): int { try { return (int)$f(); } catch (Throwable $e) { return 0; } };
  $raccourcis = [];

  if (is_file(__DIR__ . '/models/demandes.php')) {
      require_once __DIR__ . '/models/demandes.php';
      $raccourcis[] = [
          'url' => '/modules/demandes/demandes.php', 'icone' => 'fa-inbox', 'couleur' => 'yellow',
          'titre' => 'Mes demandes', 'detail' => 'à valider',
          'valeur' => $rc_compter(fn() => demandes_compte_a_valider(personnel_assurer_profil($rc_uid), a_permission('PERSONNEL', 'VALIDER'), demandes_est_dg())),
      ];
  }
  if (in_array('GED', $rc_acces, true) && is_file(__DIR__ . '/models/ged.php')) {
      require_once __DIR__ . '/models/ged.php';
      $raccourcis[] = [
          'url' => '/modules/ged/index.php', 'icone' => 'fa-folder-tree', 'couleur' => 'teal',
          'titre' => 'Documents (GED)', 'detail' => 'documents',
          'valeur' => $rc_compter(fn() => ged_compteurs($rc_uid)['DOCS']),
      ];
  }
  if (in_array('COURRIERS', $rc_acces, true) && is_file(__DIR__ . '/models/courrier.php')) {
      require_once __DIR__ . '/models/courrier.php';
      $rc_orienter = $rc_compter(fn() => get_db()->query("SELECT COUNT(*) FROM courriers WHERE sens='ENTRANT' AND statut='RECU'")->fetchColumn());
      $rc_valider  = $rc_compter(fn() => courrier_nb_a_valider());
      $raccourcis[] = [
          'url' => '/modules/courriers/index.php', 'icone' => 'fa-envelopes-bulk', 'couleur' => 'blue',
          'titre' => 'Courriers', 'detail' => $rc_orienter . ' à orienter · ' . $rc_valider . ' à valider',
          'valeur' => $rc_orienter + $rc_valider,
      ];
  }
  if (in_array('RENDEZVOUS_DG', $rc_acces, true) && is_file(__DIR__ . '/models/rendezvous_dg.php')) {
      require_once __DIR__ . '/models/rendezvous_dg.php';
      $rc_rdv_jour    = $rc_compter(fn() => get_db()->query("SELECT COUNT(*) FROM dg_rendezvous WHERE statut='CONFIRME' AND date_confirmee=CURDATE()")->fetchColumn());
      $rc_rdv_attente = $rc_compter(fn() => rdv_dg_stats()['EN_ATTENTE']);
      $raccourcis[] = [
          'url' => '/modules/rendezvous_dg/index.php', 'icone' => 'fa-calendar-check', 'couleur' => 'green',
          'titre' => 'Rendez-vous DG', 'detail' => "aujourd'hui · " . $rc_rdv_attente . ' en attente',
          'valeur' => $rc_rdv_jour,
      ];
  }
  if (in_array('PERSONNEL', $rc_acces, true) && is_file(__DIR__ . '/models/personnel.php')) {
      require_once __DIR__ . '/models/personnel.php';
      $raccourcis[] = [
          'url' => '/modules/personnel/index.php', 'icone' => 'fa-id-card', 'couleur' => 'violet',
          'titre' => 'Personnel', 'detail' => 'agents actifs',
          'valeur' => $rc_compter(fn() => personnel_statistiques()['actifs']),
      ];
  }
?>
<?php if ($raccourcis): ?>
<style>
.raccourcis-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;}
.raccourci{text-decoration:none;color:inherit;position:relative;}
.raccourci:hover{border-color:var(--blue-300);}
.raccourci .rc-fleche{position:absolute;top:14px;right:14px;font-size:.72rem;color:var(--text-muted);opacity:.5;transition:opacity .15s,transform .15s;}
.raccourci:hover .rc-fleche{opacity:1;color:var(--blue-600);transform:translateX(2px);}
.raccourci .rc-detail{font-size:.7rem;color:var(--text-muted);margin-top:2px;}
.stat-icon.violet{background:#ede9fe;color:#7c3aed;}
</style>
<div class="raccourcis-grid">
  <?php foreach ($raccourcis as $rc): ?>
    <a href="<?= APP_URL . $rc['url'] ?>" class="stat-card raccourci" title="Ouvrir <?= e($rc['titre']) ?>">
      <div class="stat-icon <?= $rc['couleur'] ?>"><i class="fa-solid <?= $rc['icone'] ?>"></i></div>
      <div>
        <div class="stat-value"><?= (int)$rc['valeur'] ?></div>
        <div class="stat-label"><?= e($rc['titre']) ?></div>
        <div class="rc-detail"><?= e($rc['detail']) ?></div>
      </div>
      <i class="fa-solid fa-arrow-right rc-fleche"></i>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Stats utilisateurs (admins seulement) -->
<?php if (a_permission('UTILISATEURS','VOIR')): ?>
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
    <div>
      <div class="stat-value"><?= (int)($stats['total'] ?? 0) ?></div>
      <div class="stat-label">Utilisateurs total</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
    <div>
      <div class="stat-value"><?= $nb_actifs_connectes ?></div>
      <div class="stat-label">Utilisateurs actifs</div>
    </div>
  </div>
  <div class="stat-card" title="Comptes actifs jamais connectés ou sans connexion depuis plus de <?= (int)$seuil_inactif ?> jours">
    <div class="stat-icon yellow"><i class="fa-solid fa-user-clock"></i></div>
    <div>
      <div class="stat-value"><?= count($inactifs_cnx) ?></div>
      <div class="stat-label">Inactifs (<?= (int)$seuil_inactif ?> j+ sans connexion)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa-solid fa-user-xmark"></i></div>
    <div>
      <div class="stat-value"><?= (int)($stats['suspendus'] ?? 0) ?></div>
      <div class="stat-label">Suspendus</div>
    </div>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">

  <!-- ════════════ L'équipe cette semaine (CONSERVÉE) ════════════ -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-chart-line" style="color:var(--blue-500);"></i>
      <h2><?= $est_gestionnaire ? "L'équipe cette semaine" : 'Ma semaine en cours' ?></h2>
      <a href="<?= APP_URL ?>/modules/suivi/index.php" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <div class="card-body" style="padding:16px 22px;">

      <?php if ($est_gestionnaire): ?>
        <?php
          // Vue équipe : lundi → dimanche, comblé jour par jour
          $debut_sem = date('Y-m-d', strtotime('monday this week'));
          $jours_sem = [];
          for ($d = 0; $d < 7; $d++) { $jours_sem[] = date('Y-m-d', strtotime("$debut_sem +$d days")); }
          $total_sem = 0;
          foreach ($semaine_globale as $v) { $total_sem += (int)$v['nb_fiches']; }
        ?>
        <?php if ($total_sem === 0): ?>
          <div style="text-align:center;color:var(--text-muted);padding:20px 0;">
            <i class="fa-regular fa-calendar-xmark" style="font-size:2rem;margin-bottom:8px;display:block;opacity:.4;"></i>
            Aucune fiche déposée par l'équipe cette semaine
          </div>
        <?php else: ?>
          <?php
            $max_sem = 1;
            foreach ($semaine_globale as $v) { $max_sem = max($max_sem, (int)$v['nb_fiches']); }
            foreach ($jours_sem as $jour):
              $v        = $semaine_globale[$jour] ?? ['nb_fiches'=>0,'nb_soumises'=>0,'nb_validees'=>0];
              $nb       = (int)$v['nb_fiches'];
              $largeur  = round($nb / $max_sem * 100);
              $est_auj  = ($jour === date('Y-m-d'));
          ?>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
              <div style="width:74px;font-size:.75rem;color:<?= $est_auj ? 'var(--blue-600)' : 'var(--text-muted)' ?>;font-weight:<?= $est_auj ? '800' : '600' ?>;">
                <?= format_date($jour, 'D d/m') ?>
              </div>
              <div style="flex:1;background:var(--bg);border-radius:20px;height:8px;overflow:hidden;">
                <div style="width:<?= max(2, $largeur) ?>%;background:var(--blue-500);height:100%;border-radius:20px;transition:width .5s;"></div>
              </div>
              <div style="width:30px;text-align:right;font-size:.82rem;font-weight:700;color:var(--text);"><?= $nb ?></div>
              <div style="width:96px;text-align:right;font-size:.72rem;color:var(--text-muted);">
                <span title="Validées" style="color:#16a34a;font-weight:700;"><?= (int)$v['nb_validees'] ?></span> val. ·
                <span title="En attente" style="color:#d97706;font-weight:700;"><?= (int)$v['nb_soumises'] ?></span> att.
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      <?php elseif (empty($semaine)): ?>
        <div style="text-align:center;color:var(--text-muted);padding:20px 0;">
          <i class="fa-regular fa-calendar-xmark" style="font-size:2rem;margin-bottom:8px;display:block;opacity:.4;"></i>
          Aucune fiche cette semaine
        </div>
      <?php else: ?>
        <?php foreach ($semaine as $j): ?>
          <?php
            $nb_act  = (int)($j['nb_activites'] ?? 0);
            $nb_att  = (int)($j['nb_atteintes']  ?? 0);
            $taux    = $nb_act > 0 ? round(($nb_att / $nb_act) * 100) : 0;
            $couleur = $taux >= 80 ? '#22c55e' : ($taux >= 50 ? '#f59e0b' : '#ef4444');
          ?>
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
            <div style="width:70px;font-size:.75rem;color:var(--text-muted);font-weight:600;">
              <?= format_date($j['date_fiche'], 'D d/m') ?>
            </div>
            <div style="flex:1;background:var(--bg);border-radius:20px;height:8px;overflow:hidden;">
              <div style="width:<?= $taux ?>%;background:<?= $couleur ?>;height:100%;border-radius:20px;transition:width .5s;"></div>
            </div>
            <div style="width:38px;text-align:right;font-size:.78rem;font-weight:700;color:<?= $couleur ?>;">
              <?= $taux ?>%
            </div>
            <div style="width:70px;"><?= badge_statut($j['statut']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════════════ Rapports du jour (REMPLACE "Répartition par groupe") ════════════ -->
  <?php if (a_permission('UTILISATEURS','VOIR')): ?>
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-clipboard-check" style="color:var(--blue-500);"></i>
      <h2>Rapports du jour</h2>
      <a href="<?= APP_URL ?>/modules/suivi/index.php" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>
    <div class="card-body" style="padding:16px 22px;">

      <?php if ($rapports_jour['total'] === 0): ?>
        <div style="text-align:center;color:var(--text-muted);padding:20px 0;">
          <i class="fa-regular fa-clipboard" style="font-size:2rem;margin-bottom:8px;display:block;opacity:.4;"></i>
          Aucun utilisateur concerné aujourd'hui
        </div>
      <?php else: ?>

        <!-- Résumé -->
        <div style="display:flex;gap:12px;margin-bottom:14px;">
          <div style="flex:1;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:10px 14px;">
            <div style="font-size:1.35rem;font-weight:800;color:#059669;line-height:1;"><?= $rapports_jour['nb_soumis'] ?></div>
            <div style="font-size:.72rem;color:#047857;font-weight:600;margin-top:3px;">Ont envoyé</div>
          </div>
          <div style="flex:1;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;">
            <div style="font-size:1.35rem;font-weight:800;color:#dc2626;line-height:1;"><?= $rapports_jour['nb_manquants'] ?></div>
            <div style="font-size:.72rem;color:#b91c1c;font-weight:600;margin-top:3px;">En attente</div>
          </div>
        </div>

        <!-- Liste -->
        <div style="max-height:288px;overflow-y:auto;padding-right:4px;">

          <?php if (!empty($rapports_jour['soumis'])): ?>
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#059669;margin:4px 0 2px;">
              <i class="fa-solid fa-check"></i> Ont envoyé (<?= $rapports_jour['nb_soumis'] ?>)
            </div>
            <?php foreach ($rapports_jour['soumis'] as $r) $ligne_rapport($r, 'soumis'); ?>
          <?php endif; ?>

          <?php if (!empty($rapports_jour['manquants'])): ?>
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc2626;margin:12px 0 2px;">
              <i class="fa-solid fa-hourglass-half"></i> En attente (<?= $rapports_jour['nb_manquants'] ?>)
            </div>
            <?php foreach ($rapports_jour['manquants'] as $r) $ligne_rapport($r, 'manquant'); ?>
          <?php else: ?>
            <div style="text-align:center;color:#059669;padding:14px 0;font-size:.82rem;font-weight:600;">
              🎉 Tous les rapports du jour ont été envoyés
            </div>
          <?php endif; ?>

        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-bolt" style="color:var(--blue-500);"></i>
      <h2>Accès rapide</h2>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <a href="<?= APP_URL ?>/modules/suivi/fiche.php" style="background:var(--blue-50);border:1px solid var(--blue-200);border-radius:10px;padding:16px;text-decoration:none;text-align:center;">
          <i class="fa-solid fa-calendar-check" style="font-size:1.4rem;color:var(--blue-600);margin-bottom:8px;display:block;"></i>
          <div style="font-size:.82rem;font-weight:600;color:var(--blue-700);">Fiche du jour</div>
        </a>
        <a href="<?= APP_URL ?>/modules/suivi/index.php" style="background:var(--blue-50);border:1px solid var(--blue-200);border-radius:10px;padding:16px;text-decoration:none;text-align:center;">
          <i class="fa-solid fa-clock-rotate-left" style="font-size:1.4rem;color:var(--blue-600);margin-bottom:8px;display:block;"></i>
          <div style="font-size:.82rem;font-weight:600;color:var(--blue-700);">Mon historique</div>
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ════════════ Fiches en attente de validation (CONSERVÉE) ════════════ -->
<?php if (!empty($a_valider)): ?>
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-clipboard-list" style="color:var(--warn);"></i>
    <h2>Fiches en attente de validation</h2>
    <span style="background:var(--warn);color:#fff;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;"><?= count($a_valider) ?></span>
  </div>
  <div class="table-wrap">
    <table class="crict-table">
      <thead><tr>
        <th>Agent</th><th>Matricule</th><th>Groupe</th><th>Date</th><th>Activités</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach (array_slice($a_valider, 0, 5) as $f): ?>
        <tr>
          <td><strong><?= e($f['nom_complet'] ?? '') ?></strong></td>
          <td><code style="font-size:.78rem;background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($f['matricule'] ?? '') ?></code></td>
          <td><?= e($f['groupe'] ?? '') ?></td>
          <td><?= format_date($f['date_fiche'] ?? '') ?></td>
          <td><?= (int)($f['nb_activites'] ?? 0) ?> activité<?= (int)($f['nb_activites'] ?? 0) > 1 ? 's' : '' ?></td>
          <td>
            <a href="<?= APP_URL ?>/modules/suivi/apercu.php?id=<?= (int)$f['id'] ?>" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-eye"></i> Voir
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (is_array($a_valider) && count($a_valider) > 5): ?>
  <div style="padding:12px 22px;text-align:center;border-top:1px solid var(--border);">
    <a href="<?= APP_URL ?>/modules/suivi/index.php?statut=SOUMISE" class="btn btn-secondary btn-sm">
      Voir toutes les <?= count($a_valider) ?> fiches
    </a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/views/layout/footer.php'; ?>
