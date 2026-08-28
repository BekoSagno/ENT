<?php
// modules/subventions/caisse_voir.php — Détail d'une caisse
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';

session_demarrer();
exiger_permission('SUBVENTIONS','VOIR');

$cid    = (int)($_GET['id'] ?? 0);
$caisse = $cid ? caisse_charger($cid) : null;
if (!$caisse) { set_flash('error','Caisse introuvable.'); redirect('/modules/subventions/index.php'); }

$peut_creer   = a_permission('SUBVENTIONS','CREER');
$peut_valider = a_permission('SUBVENTIONS','VALIDER');

$mouvements   = caisse_mouvements($cid);
$mvt_total    = array_sum(array_column($mouvements, 'montant'));

$sc_cfg = [
    'active'   => ['Active',   '#dcfce7','#166534','#22c55e'],
    'epuisee'  => ['Épuisée',  '#fee2e2','#991b1b','#ef4444'],
    'cloturee' => ['Clôturée', '#f3f4f6','#6b7280','#9ca3af'],
    'suspendue'=> ['Suspendue','#fef3c7','#92400e','#f59e0b'],
];
$sc   = $sc_cfg[$caisse['statut']] ?? $sc_cfg['active'];
$taux = (float)$caisse['montant_initial'] > 0
      ? min(100, round((float)$caisse['montant_utilise']/(float)$caisse['montant_initial']*100,1)) : 0;
$pfill = $taux>=90?'#ef4444':($taux>=70?'#f59e0b':'#22c55e');

$page_titre      = e($caisse['libelle']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Subventions</a> · '.e($caisse['code_caisse']);
$module_actif    = 'SUBVENTIONS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:18px;}
.kcard{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:12px 15px;}
.kcard .v{font-size:1.3rem;font-weight:800;line-height:1;margin-bottom:2px;}
.kcard .l{font-size:.71rem;color:var(--text-muted);}
.prog{height:8px;background:#e5e7eb;border-radius:20px;overflow:hidden;}
.prog-f{height:100%;border-radius:20px;}
.lb-item{margin-bottom:12px;}
.lb-head{display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:3px;}
.lb-prog{height:6px;background:#e5e7eb;border-radius:20px;overflow:hidden;}
.lb-prog-f{height:100%;border-radius:20px;}

/* Grille responsive des 2 cartes */
.caisse-cols{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;}

/* Mouvements de la caisse */
.mvt{display:flex;align-items:center;gap:10px;padding:11px 18px;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.mvt:last-child{border-bottom:none;}
.mvt-badge{font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;padding:3px 8px;border-radius:20px;flex-shrink:0;}
.mvt-main{flex:1;min-width:140px;}
.mvt-lib{font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.mvt-sub{font-size:.69rem;color:var(--text-muted);}
.mvt-mt{font-size:.86rem;font-weight:800;white-space:nowrap;color:#ef4444;}
@media(max-width:480px){
  .mvt-main{flex:1 1 100%;order:3;}
  .mvt-mt{margin-left:auto;}
}
</style>

<div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <?php if ($peut_creer): ?>
    <a href="caisse_form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
  <?php endif; ?>
  <a href="<?= APP_URL ?>/modules/marches/index.php?caisse=<?= $cid ?>" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-file-contract"></i> Dossiers DAF liés
  </a>
</div>

<!-- Header caisse -->
<div style="background:var(--surface);border:1.5px solid var(--border);border-left:5px solid <?= $sc[3] ?>;border-radius:14px;padding:20px 24px;margin-bottom:20px;">
  <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
    <div style="flex:1;min-width:0;">
      <div style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px;"><?= e($caisse['code_caisse']) ?> · T<?= $caisse['trimestre'] ?>-<?= $caisse['annee'] ?></div>
      <div style="font-size:1.1rem;font-weight:800;margin-bottom:7px;"><?= e($caisse['libelle']) ?></div>
      <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:10px;">
        <span class="badge" style="background:<?= $sc[1] ?>;color:<?= $sc[2] ?>;"><?= $sc[0] ?></span>
        <?php if ($caisse['source_financement']): ?>
          <span class="badge" style="background:var(--blue-50);color:var(--blue-600);"><?= e($caisse['source_financement']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($caisse['description']): ?>
        <div style="font-size:.83rem;color:var(--text-muted);"><?= nl2br(e($caisse['description'])) ?></div>
      <?php endif; ?>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <div style="font-size:1.4rem;font-weight:800;color:var(--text);"><?= sub_fmt((float)$caisse['montant_initial']) ?></div>
      <div style="font-size:.74rem;color:var(--text-muted);">Budget total</div>
      <?php if ($caisse['date_debut'] || $caisse['date_fin']): ?>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:3px;">
          <?= $caisse['date_debut']?format_date($caisse['date_debut']):'—' ?> → <?= $caisse['date_fin']?format_date($caisse['date_fin']):'—' ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <!-- Barre progression -->
  <div style="margin-top:14px;">
    <div class="prog"><div class="prog-f" style="width:<?= $taux ?>%;background:<?= $pfill ?>;"></div></div>
    <div style="display:flex;justify-content:space-between;font-size:.74rem;color:var(--text-muted);margin-top:4px;">
      <span>Consommé : <strong style="color:<?= $pfill ?>;"><?= $taux ?>%</strong></span>
      <span>Disponible : <strong style="color:<?= (float)$caisse['montant_disponible']<0?'var(--danger)':'#22c55e' ?>;"><?= sub_fmt((float)$caisse['montant_disponible']) ?></strong></span>
    </div>
  </div>
</div>

<!-- KPI -->
<div class="kpi-row">
  <div class="kcard"><div class="v" style="color:#ef4444;"><?= sub_fmt((float)$caisse['montant_utilise']) ?></div><div class="l">Consommé (exécuté)</div></div>
  <div class="kcard"><div class="v" style="color:#22c55e;"><?= sub_fmt((float)$caisse['montant_disponible']) ?></div><div class="l">Disponible</div></div>
  <div class="kcard"><div class="v" style="color:var(--blue-600);"><?= (int)($caisse['nb_dossiers']??0) ?></div><div class="l">Dossiers exécutés</div></div>
</div>

<div class="caisse-cols">

  <!-- Lignes budgétaires -->
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-table-cells-large" style="color:var(--blue-500);"></i>
      <h2>Lignes budgétaires</h2>
      <?php if ($peut_creer): ?>
        <a href="caisse_form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm" style="margin-left:auto;">Modifier</a>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding:14px 20px;">
      <?php if (empty($caisse['lignes'])): ?>
        <div style="text-align:center;padding:16px;color:var(--text-muted);font-size:.83rem;">Aucune ligne budgétaire définie.</div>
      <?php else: ?>
        <?php foreach ($caisse['lignes'] as $lb):
          $pct_lb = (float)$lb['montant_alloue']>0 ? min(100,round((float)$lb['montant_consomme']/(float)$lb['montant_alloue']*100)) : 0;
          $cf = $pct_lb>=90?'#ef4444':($pct_lb>=70?'#f59e0b':'#22c55e');
        ?>
        <div class="lb-item">
          <div class="lb-head">
            <span>
              <strong style="background:var(--blue-100);color:var(--blue-700);padding:1px 6px;border-radius:4px;font-size:.7rem;"><?= e($lb['code_categorie']) ?></strong>
              <?= e(mb_strimwidth($lb['cat_libelle'],0,28,'…')) ?>
            </span>
            <span style="font-weight:700;color:<?= $cf ?>;"><?= $pct_lb ?>%</span>
          </div>
          <div class="lb-prog"><div class="lb-prog-f" style="width:<?= $pct_lb ?>%;background:<?= $cf ?>;"></div></div>
          <div style="display:flex;justify-content:space-between;font-size:.69rem;color:var(--text-muted);margin-top:2px;">
            <span>Consommé : <?= sub_fmt((float)$lb['montant_consomme']) ?></span>
            <span>/ <?= sub_fmt((float)$lb['montant_alloue']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mouvements de la caisse (DAF + dépenses directes) -->
  <?php
    $mvt_styles = [
      'DAF'       => ['bg'=>'#dbeafe','co'=>'#1d4ed8'],
      'BIENS'     => ['bg'=>'#e0f2fe','co'=>'#0369a1'],
      'PERSONNEL' => ['bg'=>'#dcfce7','co'=>'#166534'],
    ];
  ?>
  <div class="card">
    <div class="card-header">
      <i class="fa-solid fa-receipt" style="color:var(--blue-500);"></i>
      <h2>Mouvements de la caisse</h2>
      <a href="<?= APP_URL ?>/modules/subventions/depenses.php?caisse=<?= $cid ?>" class="btn btn-secondary btn-sm" style="margin-left:auto;">Tous</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($mouvements)): ?>
        <div style="text-align:center;padding:20px;color:var(--text-muted);font-size:.83rem;">Aucun mouvement sur cette caisse.</div>
      <?php else: ?>
        <?php foreach ($mouvements as $m): $ms = $mvt_styles[$m['origine']] ?? $mvt_styles['DAF']; ?>
        <div class="mvt">
          <span class="mvt-badge" style="background:<?= $ms['bg'] ?>;color:<?= $ms['co'] ?>;"><?= e($m['origine_lbl']) ?></span>
          <div class="mvt-main">
            <div class="mvt-lib"><?= e($m['libelle']) ?><?= $m['beneficiaire'] ? ' — '.e($m['beneficiaire']) : '' ?></div>
            <div class="mvt-sub"><?= e($m['numero']) ?> · T<?= (int)$m['trimestre'] ?>-<?= (int)$m['annee'] ?> · <?= $m['date'] ? format_date(substr($m['date'],0,10)) : '—' ?></div>
          </div>
          <div class="mvt-mt"><?= sub_fmt((float)$m['montant']) ?></div>
          <a href="<?= e($m['lien']) ?>" class="btn btn-secondary btn-sm btn-icon" title="Détail"><i class="fa-solid fa-eye"></i></a>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;padding:11px 18px;font-size:.8rem;background:var(--bg);">
          <span style="color:var(--text-muted);font-weight:600;"><?= count($mouvements) ?> mouvement<?= count($mouvements)>1?'s':'' ?></span>
          <span style="font-weight:800;color:#ef4444;">Total : <?= sub_fmt((float)$mvt_total) ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Changer statut -->
<?php if ($peut_valider): ?>
<div style="background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;margin-top:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
  <span style="font-size:.84rem;font-weight:600;"><i class="fa-solid fa-exchange-alt" style="color:var(--blue-500);margin-right:6px;"></i>Changer le statut</span>
  <form method="POST" action="actions.php" style="display:flex;gap:8px;margin-left:auto;align-items:center;">
    <?= csrf_field() ?>
    <input type="hidden" name="action"    value="changer_statut_caisse">
    <input type="hidden" name="caisse_id" value="<?= $cid ?>">
    <select name="statut" class="form-control" style="max-width:160px;">
      <?php foreach ($sc_cfg as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $caisse['statut']===$k?'selected':'' ?>><?= $v[0] ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Confirmer</button>
  </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
