<?php
// modules/marches/voir.php — Détail d'un dossier exécuté
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/marche.php';

session_demarrer();
exiger_permission('MARCHES','VOIR');

$user    = user_courant();
$uid     = (int)$user['id'];
$is_chef = in_array($user['groupe_code']??'',['SUPER_ADMIN','DG','DAF','PRMP']);

$id = (int)($_GET['id']??0);
if (!$id) { redirect('/modules/marches/index.php'); }

$d = marche_get($id);
if (!$d || (!$is_chef && (int)$d['cree_par']!==$uid)) {
    set_flash('error','Dossier introuvable.'); redirect('/modules/marches/index.php');
}

$fav = (int)($d['fournisseur_favori']??0);
$regime_labels = ['tva'=>'TVA (18 %)','precompte'=>'Précompte (20 %)','sans_tva'=>'Sans taxe'];

$pieces_docs = [
    'exec_lettre_paiement' => ['Lettre de paiement','fa-envelope'],
    'exec_bon_engagement'  => ['Bon d\'engagement', 'fa-file-signature'],
    'exec_mandat_paiement' => ['Mandat de paiement','fa-file-contract'],
    'exec_liquidation'     => ['Liquidation',       'fa-calculator'],
];

$page_titre      = e($d['numero_dossier']??'Dossier');
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Marchés</a> · '.e($d['numero_dossier']??'');
$module_actif    = 'MARCHES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.dh{background:var(--surface);border:1.5px solid var(--border);border-left:5px solid #22c55e;border-radius:14px;padding:18px 22px;margin-bottom:18px;}
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:14px;}
.ib{background:var(--bg);border-radius:8px;padding:10px 12px;}
.ib-l{font-size:.67rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.ib-v{font-size:.85rem;font-weight:600;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}
.four-card{background:var(--bg);border:1.5px solid var(--border);border-radius:10px;padding:14px 16px;margin-bottom:10px;}
.doc-link{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border:1.5px solid var(--border);border-radius:7px;font-size:.78rem;font-weight:600;color:var(--blue-600);text-decoration:none;background:var(--surface);transition:all .13s;}
.doc-link:hover{background:var(--blue-50);border-color:var(--blue-400);}
.pieces-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
@media(max-width:540px){.pieces-grid{grid-template-columns:1fr;}}
</style>

<div style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <?php if ($d['statut'] === 'execute' && !marche_pieces_completes($d)): ?>
    <a href="wizard.php?id=<?= $id ?>&step=6" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-paperclip"></i> Compléter les pièces
    </a>
  <?php endif; ?>
</div>

<div class="dh">
  <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;">
    <div style="flex:1;min-width:0;">
      <div style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:3px;"><?= e($d['numero_dossier']??'') ?></div>
      <div style="font-size:1.05rem;font-weight:800;margin-bottom:8px;"><?= e($d['libelle']??'—') ?></div>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="badge" style="background:#dcfce7;color:#166534;"><i class="fa-solid fa-check"></i> Exécuté</span>
        <?php if ($d['code_caisse']): ?>
          <span class="badge" style="background:var(--blue-50);color:var(--blue-700);"><i class="fa-solid fa-piggy-bank"></i> <?= e($d['code_caisse']) ?></span>
        <?php endif; ?>
        <?php if ($d['code_categorie']): ?>
          <span class="badge" style="background:var(--bg);color:var(--text-muted);border:1px solid var(--border);"><?= e($d['code_categorie']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <div style="font-size:1.4rem;font-weight:800;color:var(--blue-700);"><?= marche_fmt((float)($d['montant_ttc']??0)) ?></div>
      <div style="font-size:.74rem;color:var(--text-muted);">TTC défalqué</div>
      <?php if ($d['date_execution']): ?>
        <div style="font-size:.71rem;color:var(--text-muted);margin-top:3px;"><?= format_datetime($d['date_execution']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="info-grid">
    <div class="ib"><div class="ib-l">Montant HT</div><div class="ib-v"><?= marche_fmt((float)($d['montant_ht']??0)) ?></div></div>
    <div class="ib"><div class="ib-l">Taxe</div><div class="ib-v"><?= marche_fmt((float)($d['montant_taxe']??0)) ?> <span style="font-size:.72rem;color:var(--text-muted);"><?= e($regime_labels[$d['regime_fiscal']??'']??'—') ?></span></div></div>
    <div class="ib"><div class="ib-l">Période</div><div class="ib-v"><?= $d['periode_annee']?'T'.$d['periode_trimestre'].' / '.$d['periode_annee']:'—' ?></div></div>
    <div class="ib"><div class="ib-l">Caisse</div><div class="ib-v"><?= e($d['caisse_libelle']??'—') ?></div></div>
  </div>
</div>

<!-- Fournisseurs -->
<div class="card" style="margin-bottom:18px;">
  <div class="card-header">
    <i class="fa-solid fa-building" style="color:var(--blue-500);"></i>
    <h2>Fournisseurs consultés</h2>
  </div>
  <div class="card-body" style="padding:14px 20px;">
    <?php for ($i=1;$i<=3;$i++):
      $nom_i = trim($d['fournisseur_'.$i.'_nom']??'');
      $est_fav = $fav === $i;
    ?>
    <div class="four-card" style="<?= $est_fav?'border-color:#22c55e;background:#f0fdf4;':'' ?>">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
        <div style="font-weight:700;font-size:.88rem;">
          <?php if ($est_fav): ?><i class="fa-solid fa-star" style="color:#f59e0b;margin-right:5px;"></i><?php endif; ?>
          <?= $nom_i ? e($nom_i) : 'Fournisseur '.$i ?>
        </div>
        <?php if ($est_fav): ?><span class="badge" style="background:#dcfce7;color:#166534;">Retenu</span><?php else: ?><span class="badge" style="background:#fee2e2;color:#991b1b;">Non retenu</span><?php endif; ?>
        <?php $motif_i = trim($d['motif_f'.$i]??''); if ($motif_i): ?>
          <span style="font-size:.78rem;color:var(--text-muted);">— <?= e($motif_i) ?></span>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
        $docs_fournisseur = ['facture_'.$i=>'Facture', 'f'.$i.'_rccm'=>'RCCM', 'f'.$i.'_nif'=>'NIF', 'f'.$i.'_quittance'=>'Quittance', 'f'.$i.'_quitus'=>'Quitus'];
        foreach ($docs_fournisseur as $dkey => $dlbl):
          $col = $dkey === 'facture_'.$i ? 'facture_'.$i.'_path' : $dkey.'_path';
          // Corriger la clé colonne pour les docs fournisseur
          if ($dkey !== 'facture_'.$i) $col = str_replace('f'.$i.'_','f'.$i.'_',$dkey).'_path';
          $has = !empty($d[$col]);
          if (!$has) continue;
        ?>
          <a href="file.php?id=<?= $id ?>&doc=<?= rawurlencode($dkey) ?>" target="_blank" class="doc-link">
            <i class="fa-solid fa-file-pdf"></i> <?= $dlbl ?>
          </a>
        <?php endforeach; ?>
        <a href="download_zip.php?id=<?= $id ?>&f=<?= $i ?>" class="doc-link" style="background:var(--blue-50);">
          <i class="fa-solid fa-download"></i> Tout télécharger (ZIP)
        </a>
      </div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Pièces d'exécution -->
<div class="card">
  <div class="card-header">
    <i class="fa-solid fa-file-contract" style="color:var(--blue-500);"></i>
    <h2>Pièces d'exécution</h2>
    <?php if (!marche_pieces_completes($d)): ?>
      <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:auto;"><i class="fa-solid fa-triangle-exclamation"></i> Incomplètes</span>
    <?php else: ?>
      <span class="badge" style="background:#dcfce7;color:#166534;margin-left:auto;"><i class="fa-solid fa-check"></i> Complètes</span>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:14px 20px;">
    <div class="pieces-grid">
      <?php foreach ($pieces_docs as $pkey => [$plbl,$pico]):
        $pcol = $pkey.'_path';
        $has  = !empty($d[$pcol]);
      ?>
      <div style="display:flex;align-items:center;gap:8px;padding:9px 11px;background:var(--bg);border-radius:8px;border:1.5px solid <?= $has?'var(--border)':'#fecaca' ?>;">
        <i class="fa-solid <?= $pico ?>" style="color:<?= $has?'var(--blue-500)':'#fca5a5' ?>;flex-shrink:0;"></i>
        <div style="flex:1;font-size:.82rem;font-weight:600;"><?= $plbl ?></div>
        <?php if ($has): ?>
          <a href="file.php?id=<?= $id ?>&doc=<?= rawurlencode($pkey) ?>" target="_blank" class="doc-link" style="padding:4px 9px;font-size:.73rem;">
            <i class="fa-solid fa-eye"></i> Voir
          </a>
        <?php else: ?>
          <span style="font-size:.72rem;color:#ef4444;font-style:italic;">Manquant</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
