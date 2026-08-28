<?php
// modules/suivi/apercu.php — Aperçu d'une fiche + validation (DG, validateurs)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/suivi.php';

session_demarrer();
exiger_connexion();

// On exige soit VOIR soit VALIDER
if (!a_permission('SUIVI_QUOTIDIEN', 'VOIR') && !a_permission('SUIVI_QUOTIDIEN', 'VALIDER')) {
    http_response_code(403);
    include APP_ROOT . '/views/errors/403.php';
    exit;
}

$user         = user_courant();
$peut_valider = a_permission('SUIVI_QUOTIDIEN', 'VALIDER');

// Récupérer la fiche par ID
$fiche_id = (int)($_GET['id'] ?? 0);
if (!$fiche_id) {
    set_flash('error', 'Fiche introuvable.');
    redirect('/modules/suivi/index.php');
}

// Charger la fiche avec ses activités et infos auteur
$db = get_db();
$stmt = $db->prepare("
    SELECT f.*,
           CONCAT(u.prenoms,' ',u.nom)    AS auteur_nom,
           u.matricule                     AS auteur_matricule,
           u.photo                         AS auteur_photo,
           g.libelle                       AS auteur_groupe,
           CONCAT(v.prenoms,' ',v.nom)    AS validateur_nom
    FROM suivi_fiches_journalieres f
    JOIN utilisateurs u               ON u.id = f.utilisateur_id
    JOIN groupes_utilisateurs g       ON g.id = u.groupe_id
    LEFT JOIN utilisateurs v          ON v.id = f.valide_par
    WHERE f.id = ?
");
$stmt->execute([$fiche_id]);
$fiche = $stmt->fetch();

if (!$fiche) {
    set_flash('error', 'Fiche introuvable.');
    redirect('/modules/suivi/index.php');
}

// Charger les activités
$stmtA = $db->prepare("
    SELECT * FROM suivi_activites_journalieres
    WHERE fiche_id = ?
    ORDER BY ordre
");
$stmtA->execute([$fiche_id]);
$activites = $stmtA->fetchAll();

// Charger les pièces jointes groupées par activité
$pj_par_activite = [];
try {
    $stmtPJ = $db->prepare('SELECT * FROM suivi_pieces_jointes WHERE fiche_id = ? ORDER BY activite_id, cree_le');
    $stmtPJ->execute([$fiche_id]);
    $icones_ext = [
        'pdf'  => 'fa-file-pdf',
        'doc'  => 'fa-file-word', 'docx' => 'fa-file-word',
        'xls'  => 'fa-file-excel','xlsx' => 'fa-file-excel',
        'odt'  => 'fa-file-lines','ods'  => 'fa-file-lines',
    ];
    foreach ($stmtPJ->fetchAll() as $pj) {
        $t = (int)$pj['taille'];
        $pj['taille_aff'] = $t < 1048576 ? round($t/1024, 1).' Ko' : round($t/1048576, 2).' Mo';
        $pj['icone']      = $icones_ext[$pj['extension']] ?? 'fa-file';
        $pj_par_activite[(int)$pj['activite_id']][] = $pj;
    }
} catch (Throwable $e) {
    error_log('[CRICT apercu pj] ' . $e->getMessage());
}

// ── Traitement action de validation / rejet ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && $peut_valider) {
    $action      = clean($_POST['action']      ?? '');
    $commentaire = clean($_POST['commentaire'] ?? '');

    if ($action === 'VALIDEE') {
        $res = suivi_valider_fiche($fiche_id, $commentaire);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fiche validée avec succès.' : ($res['message'] ?? 'Erreur.'));
        header('Location: apercu.php?id=' . $fiche_id);
        exit;
    }

    if ($action === 'REJETEE') {
        if (empty(trim($commentaire))) {
            set_flash('error', 'Le motif du rejet est obligatoire.');
            header('Location: apercu.php?id=' . $fiche_id);
            exit;
        }
        $res = suivi_rejeter_fiche($fiche_id, $commentaire);
        set_flash($res['succes'] ? 'success' : 'error',
            $res['succes'] ? 'Fiche rejetée.' : ($res['message'] ?? 'Erreur.'));
        header('Location: apercu.php?id=' . $fiche_id);
        exit;
    }
}

// ── Mise en forme date ──
$date_obj   = new DateTime($fiche['date_fiche']);
$jours_fr   = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi',
               'Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$mois_fr    = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
               7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$date_label = $jours_fr[$date_obj->format('l')] . ' ' . $date_obj->format('d') . ' '
            . $mois_fr[(int)$date_obj->format('n')] . ' ' . $date_obj->format('Y');

// Statistiques rapides
$nb_total   = count($activites);
$nb_att     = count(array_filter($activites, fn($a) => ($a['atteint'] ?? null) == 1));
$nb_non     = count(array_filter($activites, fn($a) => ($a['atteint'] ?? null) == 0));
$taux       = $nb_total > 0 ? round($nb_att / $nb_total * 100) : 0;

$statut_meta = [
    'BROUILLON' => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-pen-to-square','label'=>'Brouillon'],
    'SOUMISE'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'fa-clock',        'label'=>'Soumise'],
    'VALIDEE'   => ['bg'=>'#dcfce7','color'=>'#166534','icon'=>'fa-circle-check', 'label'=>'Validée'],
    'REJETEE'   => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'fa-circle-xmark', 'label'=>'Rejetée'],
];
$sm = $statut_meta[$fiche['statut']] ?? $statut_meta['BROUILLON'];

$page_titre      = 'Aperçu de fiche — ' . e($fiche['auteur_nom']);
$page_breadcrumb = 'Suivi & Évaluation · Aperçu';
$module_actif    = 'SUIVI_QUOTIDIEN';

include __DIR__ . '/../../views/layout/header.php';
?>

<style>
/* ── En-tête fiche ── */
.fiche-header {
  background:var(--surface); border:1.5px solid var(--border);
  border-radius:14px; padding:20px 24px; margin-bottom:20px;
  display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap;
}
.fh-avatar {
  width:52px; height:52px; border-radius:50%; flex-shrink:0;
  background:var(--blue-100); color:var(--blue-700);
  display:flex; align-items:center; justify-content:center;
  font-size:1rem; font-weight:800;
}
.fh-info { flex:1; min-width:0; }
.fh-nom  { font-size:1rem; font-weight:800; color:var(--text); }
.fh-sub  { font-size:.78rem; color:var(--text-muted); margin-top:2px; }
.fh-date { text-align:right; flex-shrink:0; }
.fh-date .date-lbl { font-size:.68rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; }
.fh-date .date-val { font-size:.9rem; font-weight:800; color:var(--blue-700); margin-top:2px; }

/* Statut pill */
.statut-pill {
  display:inline-flex; align-items:center; gap:5px;
  padding:4px 12px; border-radius:20px;
  font-size:.75rem; font-weight:700; margin-top:6px;
}

/* ── Stats rapides ── */
.stats-row {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
  gap:10px; margin-bottom:20px;
}
.stat-mini {
  background:var(--surface); border:1.5px solid var(--border);
  border-radius:10px; padding:12px 16px;
}
.stat-mini .sm-val { font-size:1.5rem; font-weight:800; line-height:1; }
.stat-mini .sm-lbl { font-size:.72rem; color:var(--text-muted); margin-top:3px; }

/* Barre progression */
.prog-wrap {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius); padding:14px 20px;
  margin-bottom:18px;
  display:flex; align-items:center; gap:14px; flex-wrap:wrap;
}
.prog-track { flex:1; min-width:100px; height:8px; background:#e5e7eb; border-radius:20px; overflow:hidden; }
.prog-fill  { height:100%; border-radius:20px; background:linear-gradient(90deg,var(--blue-500),var(--accent)); }

/* ── Carte activité (lecture seule) ── */
.act-card {
  background:var(--surface); border:1.5px solid var(--border);
  border-radius:12px; overflow:hidden; margin-bottom:12px;
}
.act-hd {
  display:flex; align-items:center; gap:10px;
  padding:11px 16px; background:var(--bg); border-bottom:1px solid var(--border);
}
.act-num {
  width:26px; height:26px; border-radius:50%;
  background:var(--blue-200); color:var(--blue-700);
  display:flex; align-items:center; justify-content:center;
  font-size:.72rem; font-weight:800; flex-shrink:0;
}
.act-titre { flex:1; font-size:.82rem; font-weight:600; color:var(--text-muted); }
.badge-atteint {
  display:inline-flex; align-items:center; gap:5px;
  padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700;
}
.badge-atteint.oui { background:#dcfce7; color:#166534; }
.badge-atteint.non { background:#fee2e2; color:#991b1b; }
.badge-atteint.nd  { background:var(--bg); color:var(--text-muted); border:1px solid var(--border); }

.act-body {
  padding:16px;
  display:grid; grid-template-columns:1fr;
  gap:14px;
}
@media(min-width:600px) {
  .act-body { grid-template-columns:1fr 1fr; }
  .f-obj, .f-docs { grid-column:1/-1; }
}
@media(min-width:900px) {
  .act-body { grid-template-columns:2fr 110px 140px; }
  .f-obj   { grid-column:1; grid-row:1; }
  .f-dur   { grid-column:2; grid-row:1; }
  .f-att   { grid-column:3; grid-row:1; }
  .f-diff  { grid-column:1; }
  .f-sol   { grid-column:2/4; }
  .f-docs  { grid-column:1/-1; }
}
.f-label {
  font-size:.71rem; font-weight:700; color:var(--text-muted);
  text-transform:uppercase; letter-spacing:.07em;
  margin-bottom:6px; display:flex; align-items:center; gap:5px;
}
.f-val { font-size:.87rem; color:var(--text); line-height:1.55; min-height:22px; }
.f-val.empty { color:var(--text-muted); font-style:italic; }

/* PJ */
.pj-list { display:flex; flex-wrap:wrap; gap:7px; margin-top:4px; }
.pj-item {
  display:inline-flex; align-items:center; gap:6px;
  padding:5px 10px; background:var(--bg);
  border:1px solid var(--border); border-radius:7px;
  font-size:.75rem; text-decoration:none; color:var(--text);
  transition:background .14s;
}
.pj-item:hover { background:var(--blue-50); border-color:var(--blue-300); }
.pj-item i { color:var(--blue-500); }

/* Section objectifs lendemain */
.lendemain-section {
  background:linear-gradient(135deg,#f0f9ff,#e0f2fe);
  border:1.5px solid #bae6fd; border-radius:12px; overflow:hidden; margin-bottom:20px;
}
.lendemain-hd {
  display:flex; align-items:center; gap:10px;
  padding:12px 18px; border-bottom:1px solid #bae6fd;
}
.lendemain-hd h3 { font-size:.9rem; font-weight:700; color:#0c4a6e; }
.lendemain-bd { padding:14px 18px; font-size:.87rem; line-height:1.6; color:var(--text); white-space:pre-wrap; }
.lendemain-bd.empty { color:var(--text-muted); font-style:italic; }

/* ── Panneau de validation ── */
.validation-panel {
  background:var(--surface); border:2px solid var(--blue-300);
  border-radius:14px; padding:20px 24px; margin-bottom:20px;
}
.validation-panel h3 {
  font-size:.92rem; font-weight:700; color:var(--text); margin-bottom:14px;
  display:flex; align-items:center; gap:8px;
}
.val-btns { display:flex; gap:10px; margin-top:14px; flex-wrap:wrap; }
</style>

<!-- Bouton retour -->
<div style="margin-bottom:16px;display:flex;align-items:center;gap:10px;">
  <a href="<?= isset($_SERVER['HTTP_REFERER']) ? e($_SERVER['HTTP_REFERER']) : APP_URL.'/modules/suivi/index.php' ?>"
     class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Retour
  </a>
  <span style="font-size:.78rem;color:var(--text-muted);">
    Fiche de <strong><?= e($fiche['auteur_nom']) ?></strong> · <?= e($date_label) ?>
  </span>
</div>

<!-- En-tête auteur -->
<div class="fiche-header">
  <?php
    $initiales = strtoupper(
        mb_substr(explode(' ', $fiche['auteur_nom'])[0] ?? 'U', 0, 1) .
        mb_substr(explode(' ', $fiche['auteur_nom'])[1] ?? 'X', 0, 1)
    );
  ?>
  <div class="fh-avatar"><?= e($initiales) ?></div>
  <div class="fh-info">
    <div class="fh-nom"><?= e($fiche['auteur_nom']) ?></div>
    <div class="fh-sub">
      <?= e($fiche['auteur_groupe'] ?? '') ?> · <?= e($fiche['auteur_matricule'] ?? '') ?>
    </div>
    <div class="statut-pill" style="background:<?= $sm['bg'] ?>;color:<?= $sm['color'] ?>;">
      <i class="fa-solid <?= $sm['icon'] ?>"></i> <?= $sm['label'] ?>
    </div>
  </div>
  <div class="fh-date">
    <div class="date-lbl">Date de la fiche</div>
    <div class="date-val"><?= e($date_label) ?></div>
    <?php if ($fiche['statut'] === 'VALIDEE' && !empty($fiche['valide_le'])): ?>
      <div style="font-size:.72rem;color:var(--success);margin-top:4px;">
        <i class="fa-solid fa-check-circle"></i>
        Validée par <?= e($fiche['validateur_nom'] ?? '—') ?>
        le <?= format_datetime($fiche['valide_le']) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Alertes statut -->
<?php if ($fiche['statut'] === 'REJETEE' && !empty($fiche['commentaire_validateur'])): ?>
<div class="alert alert-error" style="margin-bottom:16px;">
  <i class="fa-solid fa-circle-xmark" style="flex-shrink:0;margin-top:2px;"></i>
  <div>
    <strong>Fiche rejetée</strong> — <?= e($fiche['commentaire_validateur']) ?>
    <?php if (!empty($fiche['validateur_nom'])): ?>
      <div style="font-size:.77rem;opacity:.75;margin-top:3px;">
        Par <?= e($fiche['validateur_nom']) ?> · <?= format_datetime($fiche['valide_le']) ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php elseif ($fiche['statut'] === 'VALIDEE' && !empty($fiche['commentaire_validateur'])): ?>
<div class="alert alert-success" style="margin-bottom:16px;">
  <i class="fa-solid fa-circle-check" style="flex-shrink:0;"></i>
  <div>«<?= e($fiche['commentaire_validateur']) ?>»</div>
</div>
<?php elseif ($fiche['statut'] === 'SOUMISE'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="fa-solid fa-clock" style="flex-shrink:0;"></i>
  Cette fiche est en attente de validation.
</div>
<?php endif; ?>

<!-- Stats rapides -->
<div class="stats-row">
  <div class="stat-mini">
    <div class="sm-val" style="color:var(--blue-600);"><?= $nb_total ?></div>
    <div class="sm-lbl">Activité<?= $nb_total > 1 ? 's' : '' ?> déclarée<?= $nb_total > 1 ? 's' : '' ?></div>
  </div>
  <div class="stat-mini">
    <div class="sm-val" style="color:#22c55e;"><?= $nb_att ?></div>
    <div class="sm-lbl">Atteinte<?= $nb_att > 1 ? 's' : '' ?></div>
  </div>
  <div class="stat-mini">
    <div class="sm-val" style="color:var(--danger);"><?= $nb_non ?></div>
    <div class="sm-lbl">Non atteinte<?= $nb_non > 1 ? 's' : '' ?></div>
  </div>
  <div class="stat-mini">
    <div class="sm-val" style="color:var(--blue-700);"><?= $taux ?>%</div>
    <div class="sm-lbl">Taux de réalisation</div>
  </div>
</div>

<!-- Barre de progression -->
<?php if ($nb_total > 0): ?>
<div class="prog-wrap">
  <span style="font-size:.82rem;font-weight:600;color:var(--text-muted);white-space:nowrap;">
    <i class="fa-solid fa-chart-pie" style="color:var(--blue-500);"></i> Taux de réalisation
  </span>
  <div class="prog-track"><div class="prog-fill" style="width:<?= $taux ?>%;"></div></div>
  <span style="font-size:.85rem;font-weight:800;color:var(--blue-600);white-space:nowrap;"><?= $taux ?>%</span>
  <span style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;">
    <?= $nb_att ?>/<?= $nb_total ?> atteint<?= $nb_att > 1 ? 's' : '' ?>
    <?php if ($nb_non > 0): ?>
      · <span style="color:var(--danger);"><?= $nb_non ?> non atteint<?= $nb_non > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </span>
</div>
<?php endif; ?>

<!-- ── Panneau de validation (si SOUMISE et peut valider) ── -->
<?php if ($peut_valider && $fiche['statut'] === 'SOUMISE'): ?>
<div class="validation-panel">
  <h3>
    <i class="fa-solid fa-gavel" style="color:var(--blue-500);"></i>
    Décision de validation
  </h3>
  <div style="font-size:.83rem;color:var(--text-muted);margin-bottom:12px;">
    Vous pouvez valider ou rejeter cette fiche. En cas de rejet, le motif est obligatoire.
  </div>

  <form method="POST" action="" id="formValider">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label">Commentaire pour l'agent (optionnel pour validation, obligatoire pour rejet)</label>
      <textarea name="commentaire" class="form-control" rows="3"
                placeholder="Ex: Bon travail, les objectifs sont bien détaillés… / Veuillez préciser les difficultés rencontrées…"
                id="commentaireField"></textarea>
    </div>
    <div class="val-btns">
      <button type="button" class="btn btn-success"
              onclick="soumettrDecision('VALIDEE')">
        <i class="fa-solid fa-circle-check"></i> Valider la fiche
      </button>
      <button type="button" class="btn btn-danger"
              onclick="soumettrDecision('REJETEE')">
        <i class="fa-solid fa-circle-xmark"></i> Rejeter
      </button>
    </div>
    <input type="hidden" name="action" id="actionField" value="">
  </form>
</div>
<?php endif; ?>

<!-- ── Activités ── -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <i class="fa-solid fa-list-check" style="color:var(--blue-500);"></i>
    <h2>Activités de la journée</h2>
    <span style="margin-left:auto;font-size:.78rem;color:var(--text-muted);">
      <?= $nb_total ?> activité<?= $nb_total > 1 ? 's' : '' ?>
    </span>
  </div>
  <div class="card-body">
    <?php if (empty($activites)): ?>
      <div style="text-align:center;padding:20px;color:var(--text-muted);">
        <i class="fa-regular fa-clipboard" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
        Aucune activité déclarée.
      </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
      <?php foreach ($activites as $i => $act):
        $at_val = $act['atteint'] ?? null;
        $at_str = $at_val === 1 ? 'oui' : ($at_val === 0 ? 'non' : '');

        // Durée
        $dm = (int)($act['duree_estimee'] ?? 0);
        $dh = intdiv($dm, 60); $dmin = $dm % 60;
        $dur_aff = $dm > 0 ? ($dh > 0 ? $dh.'h' : '').($dmin > 0 ? str_pad($dmin,2,'0',STR_PAD_LEFT).'min' : ($dh > 0 ? '' : '—')) : '—';
      ?>
      <div class="act-card">
        <div class="act-hd">
          <div class="act-num"><?= $i+1 ?></div>
          <div class="act-titre">
            <?= !empty($act['objectif_tache'])
              ? e(mb_strimwidth($act['objectif_tache'], 0, 60, '…'))
              : 'Activité '.($i+1) ?>
          </div>
          <?php if ($at_str === 'oui'): ?>
            <span class="badge-atteint oui"><i class="fa-solid fa-check"></i> Atteint</span>
          <?php elseif ($at_str === 'non'): ?>
            <span class="badge-atteint non"><i class="fa-solid fa-xmark"></i> Non atteint</span>
          <?php else: ?>
            <span class="badge-atteint nd">—</span>
          <?php endif; ?>
        </div>
        <div class="act-body">

          <!-- Objectif -->
          <div class="f-obj">
            <div class="f-label"><i class="fa-solid fa-bullseye"></i> Objectif / Tâche</div>
            <div class="f-val <?= empty($act['objectif_tache'])?'empty':'' ?>">
              <?= !empty($act['objectif_tache']) ? nl2br(e($act['objectif_tache'])) : 'Non renseigné' ?>
            </div>
          </div>

          <!-- Durée -->
          <div class="f-dur">
            <div class="f-label"><i class="fa-regular fa-clock"></i> Durée</div>
            <div class="f-val <?= $dm===0?'empty':'' ?>"><?= $dur_aff ?></div>
          </div>

          <!-- Atteint -->
          <div class="f-att">
            <div class="f-label"><i class="fa-solid fa-circle-check"></i> Atteint ?</div>
            <?php if ($at_str === 'oui'): ?>
              <div class="f-val" style="color:var(--success);font-weight:700;"><i class="fa-solid fa-check-circle"></i> Oui</div>
            <?php elseif ($at_str === 'non'): ?>
              <div class="f-val" style="color:var(--danger);font-weight:700;"><i class="fa-solid fa-times-circle"></i> Non</div>
            <?php else: ?>
              <div class="f-val empty">Non renseigné</div>
            <?php endif; ?>
          </div>

          <!-- Difficultés -->
          <div class="f-diff">
            <div class="f-label"><i class="fa-solid fa-triangle-exclamation"></i> Difficultés</div>
            <div class="f-val <?= empty($act['difficultes'])?'empty':'' ?>">
              <?= !empty($act['difficultes']) ? nl2br(e($act['difficultes'])) : '—' ?>
            </div>
          </div>

          <!-- Solutions -->
          <div class="f-sol">
            <div class="f-label"><i class="fa-solid fa-lightbulb"></i> Solutions apportées</div>
            <div class="f-val <?= empty($act['solutions'])?'empty':'' ?>">
              <?= !empty($act['solutions']) ? nl2br(e($act['solutions'])) : '—' ?>
            </div>
          </div>

          <!-- Documents outputs -->
          <div class="f-docs">
            <div class="f-label"><i class="fa-solid fa-file-lines"></i> Documents produits</div>
            <div class="f-val <?= empty($act['documents_sortie'])?'empty':'' ?>">
              <?= !empty($act['documents_sortie']) ? nl2br(e($act['documents_sortie'])) : '—' ?>
            </div>
          </div>

          <!-- Pièces jointes -->
          <?php $pjs = $pj_par_activite[(int)$act['id']] ?? []; ?>
          <?php if (!empty($pjs)): ?>
          <div style="grid-column:1/-1;">
            <div class="f-label"><i class="fa-solid fa-paperclip"></i> Pièces jointes (<?= count($pjs) ?>)</div>
            <div class="pj-list">
              <?php foreach ($pjs as $pj): ?>
              <a href="<?= APP_URL ?>/modules/suivi/upload_suivi.php?action=download&id=<?= $pj['id'] ?>"
                 target="_blank" class="pj-item" title="<?= e($pj['nom_original']) ?>">
                <i class="fa-solid <?= $pj['icone'] ?>"></i>
                <?= e(mb_strimwidth($pj['nom_original'], 0, 28, '…')) ?>
                <span style="color:var(--text-muted);font-size:.68rem;"><?= $pj['taille_aff'] ?></span>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Objectifs du lendemain -->
<div class="lendemain-section">
  <div class="lendemain-hd">
    <i class="fa-solid fa-forward-fast" style="color:#0ea5e9;"></i>
    <h3>Objectifs du lendemain</h3>
  </div>
  <div class="lendemain-bd <?= empty($fiche['objectifs_lendemain'])?'empty':'' ?>">
    <?= !empty($fiche['objectifs_lendemain']) ? e($fiche['objectifs_lendemain']) : 'Aucun objectif renseigné.' ?>
  </div>
</div>

<!-- Ré-afficher le panneau de validation en bas aussi si la page est longue -->
<?php if ($peut_valider && $fiche['statut'] === 'SOUMISE'): ?>
<div style="background:var(--surface);border:1.5px solid var(--blue-300);border-radius:12px;padding:16px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <span style="flex:1;font-size:.85rem;font-weight:600;color:var(--text);">
    <i class="fa-solid fa-gavel" style="color:var(--blue-500);margin-right:6px;"></i>
    Prendre une décision sur cette fiche
  </span>
  <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('formValider').scrollIntoView({behavior:'smooth',block:'center'})">
    <i class="fa-solid fa-arrow-up"></i> Aller au formulaire de validation
  </button>
</div>
<?php endif; ?>

<?php
$extra_scripts = '<script>
function soumettrDecision(action) {
  const commentaire = document.getElementById("commentaireField").value.trim();
  if (action === "REJETEE" && !commentaire) {
    alert("Le motif du rejet est obligatoire.");
    document.getElementById("commentaireField").focus();
    return;
  }
  const msg = action === "VALIDEE"
    ? "Valider cette fiche ?"
    : "Rejeter cette fiche ?\nMotif : " + commentaire;
  if (confirm(msg)) {
    document.getElementById("actionField").value = action;
    document.getElementById("formValider").submit();
  }
}
</script>';

include __DIR__ . '/../../views/layout/footer.php';
?>