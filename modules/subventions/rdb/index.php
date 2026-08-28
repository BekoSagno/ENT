<?php
// modules/subventions/rdb/index.php — Liste des RDB trimestriels
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../models/rdb.php';

session_demarrer();
exiger_permission('SUBVENTIONS', 'RAPPORT');

$f = [
    'annee'     => clean($_GET['annee'] ?? ''),
    'trimestre' => clean($_GET['trimestre'] ?? ''),
    'statut'    => clean($_GET['statut'] ?? ''),
];
$par_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$result   = rdb_lister($f, $par_page, ($page - 1) * $par_page);
$rapports = $result['data'];
$total    = $result['total'];
$pager    = paginer($total, $par_page, $page);

$annees = [];
for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 5; $y--) $annees[] = $y;

$statut_cfg = [
    'brouillon'  => ['Brouillon',  '#fef3c7', '#92400e'],
    'finalise'   => ['Finalisé',   '#dcfce7', '#166534'],
];

$page_titre      = 'RDB';
$page_breadcrumb = 'Subventions · Rapport d\'Exécution Budgétaire';
$module_actif    = 'SUBVENTIONS';
$subvention_sous_actif = 'RDB';
include __DIR__ . '/../../../views/layout/header.php';
?>
<style>
.rdb-hdr{display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;}
.rdb-hdr p{flex:1;margin:0;font-size:.84rem;color:var(--text-muted);}
.rdb-filtres{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:13px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.rdb-fg{display:flex;flex-direction:column;gap:4px;}
.rdb-fg label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;}
.rdb-table{width:100%;border-collapse:collapse;}
.rdb-table th,.rdb-table td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:.84rem;text-align:left;}
.rdb-table th{font-size:.72rem;text-transform:uppercase;color:var(--text-muted);background:var(--bg);}
.rdb-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;}
.rdb-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;align-items:center;justify-content:center;padding:20px;}
.rdb-modal-bg.on{display:flex;}
.rdb-modal{background:var(--surface);border-radius:16px;padding:24px;max-width:420px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,.2);}
.rdb-modal h3{margin:0 0 16px;font-size:1rem;}
</style>

<div class="rdb-hdr">
  <p>Rapports d'exécution budgétaire <strong>trimestriels</strong> — canevas administratif DG.</p>
  <button type="button" class="btn btn-primary" onclick="document.getElementById('modalCreer').classList.add('on')">
    <i class="fa-solid fa-plus"></i> Nouveau RDB
  </button>
</div>

<div class="rdb-filtres">
  <div class="rdb-fg">
    <label>Année</label>
    <select id="fAnnee" class="form-control">
      <option value="">Toutes</option>
      <?php foreach ($annees as $y): ?>
        <option value="<?= $y ?>" <?= $f['annee']===(string)$y?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="rdb-fg">
    <label>Trimestre</label>
    <select id="fTrim" class="form-control">
      <option value="">Tous</option>
      <?php for ($t=1;$t<=4;$t++): ?>
        <option value="<?= $t ?>" <?= $f['trimestre']===(string)$t?'selected':'' ?>>T<?= $t ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="rdb-fg">
    <label>Statut</label>
    <select id="fStatut" class="form-control">
      <option value="">Tous</option>
      <option value="brouillon" <?= $f['statut']==='brouillon'?'selected':'' ?>>Brouillon</option>
      <option value="finalise" <?= $f['statut']==='finalise'?'selected':'' ?>>Finalisé</option>
    </select>
  </div>
  <button class="btn btn-primary btn-sm" onclick="appFiltre()"><i class="fa-solid fa-search"></i></button>
  <?php if (array_filter($f)): ?><a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
</div>

<?php if (empty($rapports)): ?>
<div style="text-align:center;padding:50px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
  <i class="fa-solid fa-file-invoice" style="font-size:2rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
  <div style="color:var(--text-muted);margin-bottom:14px;">Aucun RDB pour ces critères.</div>
  <button type="button" class="btn btn-primary" onclick="document.getElementById('modalCreer').classList.add('on')">Créer le premier RDB</button>
</div>
<?php else: ?>
<div class="card"><div class="card-body" style="padding:0;overflow-x:auto;">
<table class="rdb-table">
  <thead><tr>
    <th>Période</th><th>Statut</th><th>Lignes</th><th>Auteur</th><th>Dernière MAJ</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rapports as $r):
    [$sl,$sb,$sc] = $statut_cfg[$r['statut']] ?? ['—','#eee','#333'];
  ?>
    <tr>
      <td><strong>T<?= (int)$r['trimestre'] ?> — <?= (int)$r['annee'] ?></strong></td>
      <td><span class="rdb-badge" style="background:<?= $sb ?>;color:<?= $sc ?>;"><?= $sl ?></span></td>
      <td><?= (int)$r['nb_lignes'] ?></td>
      <td><?= e($r['auteur_nom'] ?? '—') ?></td>
      <td><?= $r['date_maj'] ? e(format_datetime($r['date_maj'])) : '—' ?></td>
      <td style="white-space:nowrap;">
        <a href="form.php?id=<?= (int)$r['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i></a>
        <a href="print.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="PDF"><i class="fa-solid fa-print"></i></a>
        <?php if ($r['statut']==='brouillon'): ?>
        <button type="button" class="btn btn-secondary btn-sm" title="Dupliquer vers…"
                onclick="openDup(<?= (int)$r['id'] ?>)"><i class="fa-solid fa-copy"></i></button>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php endif; ?>

<!-- Modal création -->
<div class="rdb-modal-bg" id="modalCreer" onclick="if(event.target===this)this.classList.remove('on')">
  <div class="rdb-modal">
    <h3><i class="fa-solid fa-file-circle-plus"></i> Nouveau RDB</h3>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="creer">
      <div class="form-group" style="margin-bottom:12px;">
        <label class="rf-label">Année</label>
        <select name="annee" class="form-control" required>
          <?php foreach ($annees as $y): ?>
            <option value="<?= $y ?>" <?= $y===(int)date('Y')?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:16px;">
        <label class="rf-label">Trimestre</label>
        <select name="trimestre" class="form-control" required>
          <?php for ($t=1;$t<=4;$t++): ?><option value="<?= $t ?>">Trimestre <?= $t ?></option><?php endfor; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCreer').classList.remove('on')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal duplication -->
<div class="rdb-modal-bg" id="modalDup" onclick="if(event.target===this)this.classList.remove('on')">
  <div class="rdb-modal">
    <h3><i class="fa-solid fa-copy"></i> Dupliquer vers</h3>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="dupliquer">
      <input type="hidden" name="source_id" id="dupSource" value="">
      <div class="form-group" style="margin-bottom:12px;">
        <label>Année cible</label>
        <select name="annee" class="form-control" required>
          <?php foreach ($annees as $y): ?><option value="<?= $y ?>"><?= $y ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:16px;">
        <label>Trimestre cible</label>
        <select name="trimestre" class="form-control" required>
          <?php for ($t=1;$t<=4;$t++): ?><option value="<?= $t ?>">T<?= $t ?></option><?php endfor; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalDup').classList.remove('on')">Annuler</button>
        <button type="submit" class="btn btn-primary">Dupliquer</button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_scripts = <<<'JS'
<script>
function appFiltre(){
  const qs=new URLSearchParams({annee:document.getElementById('fAnnee').value,trimestre:document.getElementById('fTrim').value,statut:document.getElementById('fStatut').value});
  for(const[k,v]of[...qs.entries()])if(!v)qs.delete(k);
  location.href='?'+qs;
}
function openDup(id){ document.getElementById('dupSource').value=id; document.getElementById('modalDup').classList.add('on'); }
</script>
JS;
include __DIR__ . '/../../../views/layout/footer.php';
