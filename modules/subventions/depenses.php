<?php
// modules/subventions/depenses.php — Historique des dépenses exécutées (+ détail)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/subvention.php';
require_once __DIR__ . '/../../models/depense.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'VOIR');

$peut_executer = a_permission('SUBVENTIONS', 'EXECUTER');

// ── Complétion des documents fournisseur après coup ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && clean($_POST['action'] ?? '') === 'completer_documents') {
    exiger_permission('SUBVENTIONS', 'EXECUTER');
    if (!csrf_verify()) { set_flash('error', 'Session expirée.'); redirect('/modules/subventions/depenses.php'); }
    $dep_id = (int)($_POST['id'] ?? 0);
    $files  = [];
    foreach (array_keys(depense_types_pieces_fournisseur()) as $k) {
        if (isset($_FILES[$k])) $files[$k] = $_FILES[$k];
    }
    $res = depense_completer_documents($dep_id, $files);
    set_flash($res['succes'] ? 'success' : 'error',
        $res['succes'] ? (($res['added'] ?? 0) . ' document(s) ajouté(s).') : ($res['message'] ?? 'Erreur.'));
    redirect('/modules/subventions/depenses.php?id=' . $dep_id);
}

$detail_id     = (int)($_GET['id'] ?? 0);

$type_cfg = [
    'biens_services' => ['Biens & services', '#dbeafe', '#1d4ed8', 'fa-truck-fast'],
    'personnel'      => ['Personnel',         '#dcfce7', '#166534', 'fa-user-group'],
];

$page_titre      = 'Dépenses exécutées';
$page_breadcrumb = 'Subventions · Historique des dépenses';
$module_actif    = 'SUBVENTIONS';
$subvention_sous_actif = 'HISTORIQUE';

// ─────────── DÉTAIL ───────────
if ($detail_id > 0) {
    $dep = depense_charger($detail_id);
    if (!$dep) { set_flash('error', 'Dépense introuvable.'); redirect('/modules/subventions/depenses.php'); }
    if (($dep['statut'] ?? 'execute') === 'brouillon') { redirect('/modules/subventions/executer.php?id=' . $detail_id); }
    include __DIR__ . '/../../views/layout/header.php';
    [$t_lbl,$t_bg,$t_co,$t_ic] = $type_cfg[$dep['type_depense']] ?? ['Dépense','#eee','#333','fa-file'];
    ?>
    <div style="margin-bottom:16px;">
      <a href="depenses.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Historique</a>
    </div>

    <div class="card" style="margin-bottom:18px;">
      <div class="card-header">
        <i class="fa-solid <?= $t_ic ?>" style="color:<?= $t_co ?>;"></i>
        <h2><?= e($dep['numero']) ?> — <?= e($dep['libelle'] ?? '') ?></h2>
        <span class="badge" style="margin-left:auto;background:<?= $t_bg ?>;color:<?= $t_co ?>;padding:4px 12px;border-radius:20px;font-weight:700;font-size:.74rem;"><?= $t_lbl ?></span>
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
          <?php
            $regimes_lbl = ['tva'=>'TVA (18 %)','precompte'=>'Précompte (20 %)','sans_tva'=>'Sans taxe'];
            $a_fiscal = $dep['type_depense']==='biens_services' && $dep['montant_ht']!==null && $dep['montant_ht']!=='';
          ?>
          <?php if ($a_fiscal): ?>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Montant hors taxes</div>
               <div style="font-weight:700;"><?= sub_fmt((float)$dep['montant_ht']) ?></div></div>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;"><?= e($regimes_lbl[$dep['regime_fiscal']] ?? 'Taxe') ?></div>
               <div style="font-weight:700;"><?= sub_fmt((float)($dep['montant_taxe'] ?? 0)) ?></div></div>
          <?php endif; ?>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;"><?= $a_fiscal ? 'Total payé (TTC)' : 'Montant' ?></div>
               <div style="font-size:1.2rem;font-weight:800;color:#ef4444;"><?= sub_fmt((float)$dep['montant']) ?></div></div>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Caisse débitée</div>
               <div style="font-weight:700;"><?= e($dep['code_caisse'] ?? '—') ?> · T<?= (int)$dep['periode_trimestre'] ?>-<?= (int)$dep['periode_annee'] ?></div></div>
          <?php if ($dep['type_depense']==='biens_services'): ?>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Fournisseur</div>
               <div style="font-weight:700;"><?= e($dep['fournisseur_nom'] ?? '—') ?></div></div>
          <?php else: ?>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Employé</div>
               <div style="font-weight:700;"><?= e($dep['employe_nom'] ?? '—') ?></div></div>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Compte crédité</div>
               <div style="font-weight:700;"><?= e($dep['compte_credite'] ?? '—') ?></div></div>
          <?php endif; ?>
          <?php if (!empty($dep['categorie_libelle'])): ?>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Catégorie</div>
               <div style="font-weight:700;"><?= e($dep['code_categorie']) ?> — <?= e($dep['categorie_libelle']) ?></div></div>
          <?php endif; ?>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Date du transfert</div>
               <div style="font-weight:700;"><?= format_datetime($dep['date_execution']) ?></div></div>
          <div><div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Exécuté par</div>
               <div style="font-weight:700;"><?= e($dep['executeur_nom'] ?? '—') ?></div></div>
        </div>
        <?php if (!empty($dep['motif'])): ?>
        <div style="margin-top:16px;">
          <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;">Motif</div>
          <div style="font-size:.88rem;"><?= nl2br(e($dep['motif'])) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($dep['documents'])): ?>
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-paperclip" style="color:var(--blue-500);"></i><h2>Pièces justificatives</h2></div>
      <div class="card-body" style="padding:0;">
        <?php $tp = depense_types_pieces() + depense_types_pieces_fournisseur() + ['preuve'=>'Élément de preuve']; foreach ($dep['documents'] as $doc): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:11px 18px;border-bottom:1px solid var(--border);">
          <i class="fa-solid fa-file" style="color:var(--blue-600);"></i>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.82rem;font-weight:600;"><?= e($tp[$doc['type_document']] ?? $doc['type_document']) ?></div>
            <div style="font-size:.72rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($doc['nom_original']) ?></div>
          </div>
          <a href="download.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fa-solid fa-download"></i></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php
    // ── Documents fournisseur : présents / manquants (biens & services) ──
    if ($dep['type_depense'] === 'biens_services'):
        $types_fourn = depense_types_pieces_fournisseur();
        $presents = [];
        foreach (($dep['documents'] ?? []) as $doc) { $presents[$doc['type_document']] = true; }
        $manquants = array_diff(array_keys($types_fourn), array_keys($presents));
    ?>
    <div class="card" style="margin-top:18px;">
      <div class="card-header">
        <i class="fa-solid fa-building" style="color:<?= $manquants ? '#f59e0b' : '#16a34a' ?>;"></i>
        <h2>Documents du fournisseur</h2>
      </div>
      <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:<?= ($manquants && $peut_executer) ? '16px' : '0' ?>;">
          <?php foreach ($types_fourn as $k => $lbl): $ok = isset($presents[$k]); ?>
            <span style="display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:600;<?= $ok ? 'background:#dcfce7;color:#166534;' : 'background:#fef3c7;color:#92400e;' ?>">
              <i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i> <?= e($lbl) ?>
            </span>
          <?php endforeach; ?>
        </div>
        <?php if ($manquants && $peut_executer): ?>
        <form method="POST" action="depenses.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="completer_documents">
          <input type="hidden" name="id" value="<?= (int)$detail_id ?>">
          <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:10px;">Ajoutez les documents manquants :</div>
          <?php foreach ($manquants as $k): ?>
          <div style="margin-bottom:10px;">
            <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:4px;"><?= e($types_fourn[$k]) ?></label>
            <input type="file" name="<?= e($k) ?>" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.webp" class="form-control">
          </div>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px;"><i class="fa-solid fa-cloud-arrow-up"></i> Enregistrer les documents</button>
        </form>
        <?php elseif (!$manquants): ?>
        <div style="font-size:.82rem;color:#16a34a;font-weight:600;"><i class="fa-solid fa-circle-check"></i> Tous les documents du fournisseur sont présents.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php
    include __DIR__ . '/../../views/layout/footer.php';
    return;
}

// ─────────── LISTE ───────────
$f = [
    'type'   => clean($_GET['type'] ?? ''),
    'q'      => clean($_GET['q']    ?? ''),
    'caisse' => (int)($_GET['caisse'] ?? 0),
];
$par_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = depense_lister($f, $par_page, $offset);
$depenses = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);
$stats    = depense_stats();
$brouillons = $peut_executer ? depense_brouillons_lister(user_id()) : [];
$docs_manquants = depense_documents_fournisseur_manquants();

include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:22px;}
.kpi{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;padding:16px 20px;}
.kpi-val{font-size:1.3rem;font-weight:800;line-height:1.1;margin-bottom:4px;}
.kpi-lbl{font-size:.74rem;color:var(--text-muted);}
.dep-tbl{width:100%;border-collapse:collapse;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.dep-tbl th,.dep-tbl td{padding:11px 14px;text-align:left;font-size:.82rem;border-bottom:1px solid var(--border);}
.dep-tbl th{background:var(--bg);font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);}
.dep-tbl tr:hover td{background:var(--bg);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.69rem;font-weight:700;}
.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:13px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:18px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
</style>

<div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);">Journal des dépenses exécutées et défalquées des caisses.</div>
  <a href="depenses_print.php?<?= e(http_build_query(array_filter($f))) ?>" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Imprimer / PDF</a>
  <?php if ($peut_executer): ?>
    <a href="executer.php" class="btn btn-primary"><i class="fa-solid fa-money-bill-transfer"></i> Exécuter une dépense</a>
  <?php endif; ?>
</div>

<?php if (!empty($brouillons)): ?>
<div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:12px;padding:14px 18px;margin-bottom:18px;">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <i class="fa-solid fa-pen-to-square" style="color:#f59e0b;font-size:1.1rem;"></i>
    <div style="flex:1;min-width:160px;font-size:.84rem;color:#92400e;font-weight:700;">
      <?= count($brouillons) ?> brouillon(s) en cours — reprenez une dépense commencée.
    </div>
    <a href="executer.php" class="btn btn-secondary btn-sm">Voir les brouillons</a>
  </div>
  <div style="display:flex;flex-direction:column;gap:6px;margin-top:10px;">
    <?php foreach ($brouillons as $b):
      $benef = $b['type_depense']==='personnel' ? ($b['employe_nom'] ?? '') : ($b['fournisseur_nom'] ?? '');
    ?>
    <div style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #fde68a;border-radius:9px;padding:9px 13px;flex-wrap:wrap;">
      <div style="flex:1;min-width:140px;font-size:.8rem;">
        <strong><?= e($b['libelle'] ?: 'Brouillon') ?></strong>
        <span style="color:var(--text-muted);">
          · <?= $b['type_depense']==='personnel' ? 'Personnel' : 'Biens & services' ?>
          <?= $benef ? ' · '.e($benef) : '' ?>
          <?= ($b['montant'] !== null) ? ' · '.sub_fmt((float)$b['montant']) : '' ?>
          · <?= format_datetime($b['date_creation']) ?>
        </span>
      </div>
      <a href="executer.php?id=<?= (int)$b['id'] ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen"></i> Reprendre</a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($docs_manquants)): ?>
<div style="background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;padding:14px 18px;margin-bottom:18px;">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;font-size:1.15rem;"></i>
    <div style="flex:1;min-width:180px;font-size:.86rem;color:#9a3412;font-weight:700;">
      <?= count($docs_manquants) ?> dépense(s) attendent leurs documents fournisseur — pensez à les compléter.
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:6px;margin-top:10px;">
    <?php foreach (array_slice($docs_manquants, 0, 6) as $dm): ?>
    <div style="display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #fed7aa;border-radius:9px;padding:9px 13px;flex-wrap:wrap;">
      <div style="flex:1;min-width:140px;font-size:.8rem;">
        <strong><?= e($dm['numero']) ?></strong>
        <span style="color:var(--text-muted);"> · <?= e($dm['fournisseur_nom'] ?: $dm['libelle']) ?> · <?= (int)$dm['nb_manquants'] ?> manquant(s)</span>
      </div>
      <a href="depenses.php?id=<?= (int)$dm['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-cloud-arrow-up"></i> Compléter</a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="kpi-row">
  <div class="kpi"><div class="kpi-val"><?= (int)$stats['nb'] ?></div><div class="kpi-lbl">Dépenses exécutées</div></div>
  <div class="kpi"><div class="kpi-val" style="color:#ef4444;"><?= sub_fmt((float)$stats['total']) ?></div><div class="kpi-lbl">Total défalqué</div></div>
  <div class="kpi"><div class="kpi-val" style="color:#1d4ed8;"><?= sub_fmt((float)$stats['total_biens']) ?></div><div class="kpi-lbl">Biens &amp; services</div></div>
  <div class="kpi"><div class="kpi-val" style="color:#166534;"><?= sub_fmt((float)$stats['total_personnel']) ?></div><div class="kpi-lbl">Personnel</div></div>
</div>

<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:160px;">
    <label>Recherche</label>
    <input type="text" id="fq" class="form-control" placeholder="N°, fournisseur, employé…" value="<?= e($f['q']) ?>">
  </div>
  <div class="fg">
    <label>Type</label>
    <select id="ftype" class="form-control">
      <option value="">Tous</option>
      <option value="biens_services" <?= $f['type']==='biens_services'?'selected':'' ?>>Biens &amp; services</option>
      <option value="personnel" <?= $f['type']==='personnel'?'selected':'' ?>>Personnel</option>
    </select>
  </div>
  <div class="fg">
    <label>&nbsp;</label>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-primary btn-sm" onclick="appF()"><i class="fa-solid fa-search"></i></button>
      <?php if (array_filter($f)): ?><a href="depenses.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
    </div>
  </div>
</div>

<?php if (empty($depenses)): ?>
<div style="text-align:center;padding:50px 24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
  <i class="fa-solid fa-receipt" style="font-size:1.8rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
  <div style="font-size:.88rem;color:var(--text-muted);">Aucune dépense exécutée pour le moment.</div>
</div>
<?php else: ?>
<div style="overflow-x:auto;">
<table class="dep-tbl">
  <thead><tr>
    <th>N°</th><th>Type</th><th>Bénéficiaire</th><th>Caisse</th><th>Montant</th><th>Date</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($depenses as $d): [$t_lbl,$t_bg,$t_co,$t_ic] = $type_cfg[$d['type_depense']] ?? ['—','#eee','#333','fa-file']; ?>
    <tr>
      <td style="font-weight:700;"><?= e($d['numero']) ?></td>
      <td><span class="badge" style="background:<?= $t_bg ?>;color:<?= $t_co ?>;"><i class="fa-solid <?= $t_ic ?>"></i> <?= $t_lbl ?></span></td>
      <td><?= e($d['type_depense']==='personnel' ? ($d['employe_nom'] ?? '—') : ($d['fournisseur_nom'] ?? '—')) ?></td>
      <td><?= e($d['code_caisse'] ?? '—') ?> · T<?= (int)$d['periode_trimestre'] ?>-<?= (int)$d['periode_annee'] ?></td>
      <td style="font-weight:700;color:#ef4444;"><?= sub_fmt((float)$d['montant']) ?></td>
      <td style="white-space:nowrap;"><?= format_datetime($d['date_execution']) ?></td>
      <td><a href="depenses.php?id=<?= (int)$d['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($pager['nb_pages']>1): $qb=array_filter(array_diff_key($_GET,['page'=>1])); ?>
<div class="pager">
  <?php for($pg=1;$pg<=$pager['nb_pages'];$pg++): ?>
    <?php if($pg===$page): ?><span class="cur"><?= $pg ?></span>
    <?php else: ?><a href="?<?= http_build_query(array_merge($qb,['page'=>$pg])) ?>"><?= $pg ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
$extra_scripts = '<script>
function appF(){
  const qs=new URLSearchParams({q:document.getElementById("fq").value,type:document.getElementById("ftype").value,page:1});
  for(const[k,v]of[...qs.entries()])if(!v)qs.delete(k);
  location.href="?"+qs;
}
document.getElementById("fq").addEventListener("keydown",e=>{if(e.key==="Enter")appF();});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
