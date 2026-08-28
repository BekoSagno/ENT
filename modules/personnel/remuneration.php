<?php
// modules/personnel/remuneration.php — Rémunération (confidentiel)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/personnel.php';
require_once __DIR__ . '/../../models/remuneration.php';

session_demarrer();
exiger_permission('PERSONNEL', 'GERER_REMUNERATION');

$id = (int)($_GET['id'] ?? 0);
$p  = $id ? personnel_par_id($id) : null;
if (!$p) { set_flash('error', 'Profil introuvable.'); redirect('/modules/personnel/index.php'); }

$mois_noms = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];

// ── POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { set_flash('error','Session expirée.'); redirect('/modules/personnel/remuneration.php?id='.$id); }
    $action = $_POST['action'] ?? '';

    if ($action === 'base_set') {
        $r = remuneration_definir_base($id, (float)str_replace([' ',','], ['','.'], $_POST['salaire_base'] ?? '0'),
                                       clean($_POST['date_effet'] ?? ''), clean($_POST['motif'] ?? ''));
        set_flash($r['succes']?'success':'error', $r['succes']?'Salaire de base enregistré.':$r['message']);
        redirect('/modules/personnel/remuneration.php?id='.$id);
    }
    if ($action === 'element_add') {
        $r = remuneration_element_ajouter($id, [
            'type'    => clean($_POST['type'] ?? 'PRIME'),
            'libelle' => clean($_POST['libelle'] ?? ''),
            'mode'    => clean($_POST['mode'] ?? 'FIXE'),
            'montant' => (float)str_replace([' ',','], ['','.'], $_POST['montant'] ?? '0'),
            'taux'    => (float)str_replace([','], ['.'], $_POST['taux'] ?? '0'),
        ]);
        set_flash($r['succes']?'success':'error', $r['succes']?'Élément ajouté.':$r['message']);
        redirect('/modules/personnel/remuneration.php?id='.$id);
    }
    if ($action === 'element_del') {
        remuneration_element_supprimer((int)($_POST['eid'] ?? 0), $id);
        set_flash('success','Élément retiré.');
        redirect('/modules/personnel/remuneration.php?id='.$id);
    }
    if ($action === 'bulletin_gen') {
        $r = bulletin_generer($id, (int)($_POST['annee'] ?? 0), (int)($_POST['mois'] ?? 0));
        set_flash($r['succes']?'success':'error', $r['succes']?'Bulletin généré.':$r['message']);
        redirect('/modules/personnel/remuneration.php?id='.$id);
    }
    if ($action === 'bulletin_statut') {
        bulletin_changer_statut((int)($_POST['bid'] ?? 0), clean($_POST['statut'] ?? ''));
        set_flash('success','Statut du bulletin mis à jour.');
        redirect('/modules/personnel/remuneration.php?id='.$id);
    }
    if ($action === 'bulletin_del') {
        $r = bulletin_supprimer((int)($_POST['bid'] ?? 0));
        set_flash($r['succes']?'success':'error', $r['succes']?'Bulletin supprimé.':$r['message']);
        redirect('/modules/personnel/remuneration.php?id='.$id);
    }
    redirect('/modules/personnel/remuneration.php?id='.$id);
}

// Toute consultation est tracée
remuneration_log_consultation($id);

// ============================================================
//  VUE BULLETIN (imprimable)  ?id=..&bulletin=..
// ============================================================
$bid = (int)($_GET['bulletin'] ?? 0);
if ($bid) {
    $bul = bulletin_par_id($bid);
    if (!$bul || (int)$bul['personnel_id'] !== $id) { set_flash('error','Bulletin introuvable.'); redirect('/modules/personnel/remuneration.php?id='.$id); }
    $lignes = bulletin_lignes($bid);

    $page_titre      = 'Bulletin de paie';
    $page_breadcrumb = 'RH · Rémunération · Bulletin';
    $module_actif    = 'PERSONNEL';
    include __DIR__ . '/../../views/layout/header.php';
    ?>
    <style>
    .bp{max-width:720px;}
    .bp-paper{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:28px 32px;}
    .bp-h{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--blue-600);padding-bottom:14px;margin-bottom:18px;}
    .bp-table{width:100%;border-collapse:collapse;font-size:.88rem;}
    .bp-table td{padding:8px 4px;border-bottom:1px solid var(--border);}
    .bp-table td.m{text-align:right;font-family:var(--mono);white-space:nowrap;}
    .bp-net{display:flex;justify-content:space-between;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:10px;padding:14px 18px;margin-top:16px;font-weight:800;font-size:1.1rem;color:var(--blue-800);}
    @media print {.no-print{display:none !important;} .bp-paper{border:none;}}
    </style>
    <div class="bp">
      <div class="no-print" style="display:flex;gap:8px;margin-bottom:16px;">
        <a href="remuneration.php?id=<?= $id ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print"></i> Imprimer</button>
      </div>
      <div class="bp-paper">
        <div class="bp-h">
          <div>
            <div style="font-weight:800;font-size:1.1rem;">Bulletin de paie</div>
            <div style="font-size:.84rem;color:var(--text-muted);"><?= e($mois_noms[(int)$bul['mois']] ?? $bul['mois']) ?> <?= (int)$bul['annee'] ?></div>
          </div>
          <div style="text-align:right;font-size:.84rem;">
            <div style="font-weight:700;"><?= e($p['prenoms'].' '.$p['nom']) ?></div>
            <div style="color:var(--text-muted);"><?= e($p['matricule']) ?><?= !empty($p['poste_intitule'])?' · '.e($p['poste_intitule']):'' ?></div>
            <div style="color:var(--text-muted);">Statut : <?= e($bul['statut']) ?></div>
          </div>
        </div>
        <table class="bp-table">
          <?php foreach ($lignes as $l):
            $signe = $l['type']==='RETENUE' ? '−' : '';
          ?>
          <tr>
            <td><?= e($l['libelle']) ?><?= $l['type']!=='BASE' ? ' <span style="color:var(--text-muted);font-size:.76rem;">('.strtolower($l['type']).')</span>' : '' ?></td>
            <td class="m"><?= $signe . remu_format((float)$l['montant']) ?></td>
          </tr>
          <?php endforeach; ?>
          <tr><td style="font-weight:700;">Total brut</td><td class="m" style="font-weight:700;"><?= remu_format((float)$bul['total_brut']) ?></td></tr>
          <tr><td style="font-weight:700;">Total retenues</td><td class="m" style="font-weight:700;">− <?= remu_format((float)$bul['total_retenues']) ?></td></tr>
        </table>
        <div class="bp-net"><span>Net à payer</span><span><?= remu_format((float)$bul['net_a_payer']) ?></span></div>
      </div>
    </div>
    <?php
    include __DIR__ . '/../../views/layout/footer.php';
    exit;
}

// ============================================================
//  TABLEAU DE BORD RÉMUNÉRATION
// ============================================================
$sal        = remuneration_salaire_actuel($id);
$historique = remuneration_historique($id);
$elements   = remuneration_elements($id);
$apercu     = bulletin_calculer($id);
$bulletins  = bulletins_lister($id);

$type_lbl = ['PRIME'=>'Prime','INDEMNITE'=>'Indemnité','RETENUE'=>'Retenue'];
$st_bul   = ['BROUILLON'=>['Brouillon','#fef3c7','#92400e'],'VALIDE'=>['Validé','#dbeafe','#1e40af'],'PAYE'=>['Payé','#dcfce7','#166534']];

$page_titre      = 'Rémunération — ' . $p['prenoms'] . ' ' . $p['nom'];
$page_breadcrumb = 'RH · Rémunération';
$module_actif    = 'PERSONNEL';
include __DIR__ . '/../../views/layout/header.php';
?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
  <a href="voir.php?id=<?= $id ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Fiche</a>
  <div style="font-size:.8rem;color:#92400e;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:6px 12px;">
    <i class="fa-solid fa-lock"></i> Données confidentielles — chaque consultation est journalisée.
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;" class="grid2">

  <!-- Salaire de base -->
  <div class="card">
    <div class="card-header"><h2><i class="fa-solid fa-money-bill-wave" style="color:var(--blue-500);"></i>&nbsp; Salaire de base</h2></div>
    <div class="card-body">
      <div style="font-size:1.6rem;font-weight:800;color:var(--blue-700);">
        <?= $sal ? remu_format((float)$sal['salaire_base']) : '— non défini —' ?>
      </div>
      <?php if ($sal): ?><div style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px;">depuis le <?= e(format_date($sal['date_effet'])) ?></div><?php endif; ?>

      <form method="post" style="border-top:1px solid var(--border);padding-top:14px;margin-top:8px;">
        <?= csrf_field() ?><input type="hidden" name="action" value="base_set">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Nouveau salaire (GNF) *</label><input type="text" name="salaire_base" class="form-control" inputmode="numeric" required placeholder="ex. 4 500 000"></div>
          <div class="form-group"><label class="form-label">Date d'effet *</label><input type="date" name="date_effet" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="form-group"><label class="form-label">Motif</label><input type="text" name="motif" class="form-control" placeholder="Embauche, augmentation…"></div>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
      </form>

      <?php if (count($historique) > 1): ?>
      <div style="margin-top:16px;">
        <div style="font-size:.74rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;">Historique</div>
        <?php foreach ($historique as $h): ?>
          <div style="font-size:.8rem;display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border);">
            <span><?= e(format_date($h['date_effet'])) ?><?= !empty($h['motif'])?' · '.e($h['motif']):'' ?></span>
            <span style="font-family:var(--mono);"><?= remu_format((float)$h['salaire_base']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Éléments récurrents -->
  <div class="card">
    <div class="card-header"><h2><i class="fa-solid fa-list" style="color:var(--blue-500);"></i>&nbsp; Primes, indemnités & retenues</h2></div>
    <div class="card-body">
      <?php if (empty($elements)): ?><p style="font-size:.84rem;color:var(--text-muted);">Aucun élément.</p><?php endif; ?>
      <?php foreach ($elements as $el):
        $val = $el['mode']==='POURCENTAGE' ? rtrim(rtrim(number_format((float)$el['taux'],2,'.',''),'0'),'.').' % du base' : remu_format((float)$el['montant']);
      ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border);font-size:.84rem;">
        <span><?= e($el['libelle']) ?> <span style="color:var(--text-muted);font-size:.74rem;">(<?= e($type_lbl[$el['type']] ?? $el['type']) ?>)</span></span>
        <span style="display:flex;gap:8px;align-items:center;">
          <span style="font-family:var(--mono);color:<?= $el['type']==='RETENUE'?'#991b1b':'var(--text)' ?>;"><?= $el['type']==='RETENUE'?'− ':'' ?><?= $val ?></span>
          <form method="post" onsubmit="return confirm('Retirer ?');" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="element_del"><input type="hidden" name="eid" value="<?= (int)$el['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-xmark"></i></button></form>
        </span>
      </div>
      <?php endforeach; ?>

      <form method="post" style="border-top:1px solid var(--border);padding-top:14px;margin-top:12px;">
        <?= csrf_field() ?><input type="hidden" name="action" value="element_add">
        <div class="form-row-3">
          <div class="form-group"><label class="form-label">Type</label>
            <select name="type" class="form-control"><option value="PRIME">Prime</option><option value="INDEMNITE">Indemnité</option><option value="RETENUE">Retenue</option></select></div>
          <div class="form-group"><label class="form-label">Libellé *</label><input type="text" name="libelle" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Mode</label>
            <select name="mode" id="modeSel" class="form-control" onchange="majMode()"><option value="FIXE">Montant fixe</option><option value="POURCENTAGE">% du base</option></select></div>
        </div>
        <div class="form-row">
          <div class="form-group" id="fMontant"><label class="form-label">Montant (GNF)</label><input type="text" name="montant" class="form-control" inputmode="numeric"></div>
          <div class="form-group" id="fTaux" style="display:none;"><label class="form-label">Taux (%)</label><input type="text" name="taux" class="form-control" inputmode="decimal"></div>
        </div>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Ajouter l'élément</button>
      </form>
    </div>
  </div>
</div>

<!-- Bulletins -->
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h2><i class="fa-solid fa-receipt" style="color:var(--blue-500);"></i>&nbsp; Bulletins de paie</h2></div>
  <div class="card-body">
    <div style="background:var(--blue-50);border:1px solid var(--blue-200);border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span style="font-size:.84rem;color:var(--text-muted);">Net mensuel estimé (paramètres actuels)</span>
      <span style="font-weight:800;color:var(--blue-700);"><?= remu_format($apercu['net']) ?></span>
    </div>

    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px;">
      <?= csrf_field() ?><input type="hidden" name="action" value="bulletin_gen">
      <div class="form-group" style="margin:0;"><label class="form-label">Mois</label>
        <select name="mois" class="form-control"><?php foreach ($mois_noms as $k=>$v): ?><option value="<?= $k ?>" <?= $k===(int)date('n')?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="form-group" style="margin:0;"><label class="form-label">Année</label><input type="number" name="annee" class="form-control" value="<?= date('Y') ?>" style="width:110px;"></div>
      <button class="btn btn-primary"><i class="fa-solid fa-gears"></i> Générer le bulletin</button>
    </form>

    <?php if (empty($bulletins)): ?><p style="font-size:.84rem;color:var(--text-muted);">Aucun bulletin généré.</p><?php endif; ?>
    <?php if ($bulletins): ?>
    <div class="table-wrap"><table class="crict-table">
      <thead><tr><th>Période</th><th>Net à payer</th><th>Statut</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($bulletins as $b): $sb = $st_bul[$b['statut']] ?? $st_bul['BROUILLON']; ?>
        <tr>
          <td style="font-weight:600;"><?= e($mois_noms[(int)$b['mois']] ?? $b['mois']) ?> <?= (int)$b['annee'] ?></td>
          <td style="font-family:var(--mono);"><?= remu_format((float)$b['net_a_payer']) ?></td>
          <td><span class="badge" style="background:<?= $sb[1] ?>;color:<?= $sb[2] ?>;padding:2px 10px;border-radius:20px;font-size:.7rem;font-weight:700;"><?= $sb[0] ?></span></td>
          <td style="text-align:right;white-space:nowrap;">
            <a href="remuneration.php?id=<?= $id ?>&bulletin=<?= (int)$b['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eye"></i></a>
            <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="bulletin_statut"><input type="hidden" name="bid" value="<?= (int)$b['id'] ?>">
              <select name="statut" class="form-control" style="display:inline-block;width:auto;padding:4px 8px;font-size:.78rem;" onchange="this.form.submit()">
                <?php foreach ($st_bul as $k=>$v): ?><option value="<?= $k ?>" <?= $b['statut']===$k?'selected':'' ?>><?= $v[0] ?></option><?php endforeach; ?>
              </select>
            </form>
            <?php if ($b['statut']==='BROUILLON'): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce brouillon ?');"><?= csrf_field() ?><input type="hidden" name="action" value="bulletin_del"><input type="hidden" name="bid" value="<?= (int)$b['id'] ?>"><button class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
</div>

<?php
$extra_scripts = '<script>
function majMode(){
  var m=document.getElementById("modeSel").value;
  document.getElementById("fMontant").style.display = m==="FIXE" ? "" : "none";
  document.getElementById("fTaux").style.display    = m==="POURCENTAGE" ? "" : "none";
}
</script>
<style>@media(max-width:880px){.grid2{grid-template-columns:1fr !important;}}</style>';
include __DIR__ . '/../../views/layout/footer.php';
?>
