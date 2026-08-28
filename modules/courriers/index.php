<?php
// modules/courriers/index.php — Registre des courriers
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();
exiger_permission('COURRIERS', 'VOIR');

$peut_creer     = a_permission('COURRIERS', 'CREER');
$peut_modifier  = a_permission('COURRIERS', 'MODIFIER');
$peut_valider   = a_permission('COURRIERS', 'VALIDER');
$peut_supprimer = a_permission('COURRIERS', 'SUPPRIMER');

// Filtres
$f = [
    'q'               => clean($_GET['q']               ?? ''),
    'sens'            => clean($_GET['sens']            ?? ''),
    'statut'          => clean($_GET['statut']          ?? ''),
    'canal'           => clean($_GET['canal']           ?? ''),
    'priorite'        => clean($_GET['priorite']        ?? ''),
    'confidentialite' => clean($_GET['confidentialite'] ?? ''),
    'type'            => clean($_GET['type']            ?? ''),
    'service_id'      => clean($_GET['service_id']      ?? ''),
    'date_debut'      => clean($_GET['date_debut']      ?? ''),
    'date_fin'        => clean($_GET['date_fin']        ?? ''),
];

$par_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = courrier_lister($f, $par_page, $offset);
$courriers = $result['data'];
$total     = $result['total'];
$pager     = paginer($total, $par_page, $page);
$cpt       = courrier_compteurs();
$services  = courrier_services();

$STATUTS  = courrier_statuts();
$PRIOS    = courrier_priorites();
$CANAUX   = courrier_canaux();
$TYPES    = courrier_types();
$CONFS    = courrier_confidentialites();

$page_titre      = 'Courriers';
$page_breadcrumb = 'Gestion des Courriers — Registre';
$module_actif    = 'COURRIERS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:22px;}
.sc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;transition:box-shadow .15s;cursor:pointer;}
.sc:hover{box-shadow:var(--shadow);}
.sc-val{font-size:1.6rem;font-weight:800;line-height:1;margin-bottom:4px;}
.sc-lbl{font-size:.73rem;color:var(--text-muted);}

.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}

.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;}
.sens-pill{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:7px;}
.sens-in{background:#dcfce7;color:#166534;}
.sens-out{background:#dbeafe;color:#1d4ed8;}
.act-btns{display:flex;gap:5px;flex-wrap:nowrap;}
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:20px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all .12s;}
.pager a:hover{border-color:var(--blue-400);color:var(--blue-600);}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
.pager .dots{border:none;background:none;}
.retard{color:var(--danger);font-weight:700;}
</style>

<!-- En-tête -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);">
    Registre centralisé des courriers entrants et sortants — réception, classement, orientation et suivi du traitement.
  </div>
  <?php if ($peut_creer): ?>
    <a href="sync.php" class="btn btn-success" title="Relever les nouveaux emails reçus sur info@crict.edu.gn"><i class="fa-solid fa-rotate"></i> Relever info@</a>
    <a href="form.php?sens=ENTRANT" class="btn btn-secondary"><i class="fa-solid fa-inbox"></i> Courrier entrant</a>
    <a href="form.php?sens=SORTANT" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Courrier sortant</a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-row">
  <?php
  $sd = [
    ['val'=>$cpt['TOTAL'],         'lbl'=>'Total',         'color'=>'var(--blue-600)', 'k'=>'statut','v'=>''],
    ['val'=>$cpt['ENTRANT'],       'lbl'=>'Entrants',      'color'=>'#16a34a',         'k'=>'sens','v'=>'ENTRANT'],
    ['val'=>$cpt['SORTANT'],       'lbl'=>'Sortants',      'color'=>'#1d4ed8',         'k'=>'sens','v'=>'SORTANT'],
    ['val'=>$cpt['RECU']+$cpt['AFFECTE']+$cpt['EN_TRAITEMENT'], 'lbl'=>'En cours', 'color'=>'#d97706','k'=>'statut','v'=>'EN_TRAITEMENT'],
    ['val'=>$cpt['URGENTS'],       'lbl'=>'Urgents',       'color'=>'#dc2626',         'k'=>'priorite','v'=>'URGENTE'],
    ['val'=>$cpt['EN_RETARD'],     'lbl'=>'En retard',     'color'=>'#b91c1c',         'k'=>'','v'=>''],
  ];
  foreach ($sd as $s):
    $qs = http_build_query(array_merge($f, [$s['k']=>$s['v'],'page'=>1]));
  ?>
  <div class="sc" <?= $s['k'] ? 'onclick="location.href=\'?'.$qs.'\'"' : '' ?>>
    <div class="sc-val" style="color:<?= $s['color'] ?>;"><?= $s['val'] ?></div>
    <div class="sc-lbl"><?= $s['lbl'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:170px;">
    <label><i class="fa-solid fa-magnifying-glass"></i> Recherche</label>
    <input type="text" id="fq" class="form-control" placeholder="Référence, objet, expéditeur…" value="<?= e($f['q']) ?>">
  </div>
  <div class="fg">
    <label>Sens</label>
    <select id="fsens" class="form-control">
      <option value="">Tous</option>
      <option value="ENTRANT" <?= $f['sens']==='ENTRANT'?'selected':'' ?>>Entrant</option>
      <option value="SORTANT" <?= $f['sens']==='SORTANT'?'selected':'' ?>>Sortant</option>
    </select>
  </div>
  <div class="fg">
    <label>Statut</label>
    <select id="fstatut" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($STATUTS as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['statut']===$k?'selected':'' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Canal</label>
    <select id="fcanal" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($CANAUX as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['canal']===$k?'selected':'' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Priorité</label>
    <select id="fpriorite" class="form-control">
      <option value="">Toutes</option>
      <?php foreach ($PRIOS as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['priorite']===$k?'selected':'' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Service</label>
    <select id="fservice" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($services as $sv): ?>
        <option value="<?= $sv['id'] ?>" <?= (string)$f['service_id']===(string)$sv['id']?'selected':'' ?>><?= e($sv['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>&nbsp;</label>
    <div style="display:flex;gap:6px;">
      <button class="btn btn-primary btn-sm" onclick="appF()"><i class="fa-solid fa-search"></i></button>
      <?php if (array_filter($f)): ?>
        <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;">
  <strong style="color:var(--text);"><?= $total ?></strong> courrier<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?>
</div>

<?php if (empty($courriers)): ?>
<div style="text-align:center;padding:60px 24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
  <i class="fa-solid fa-envelopes-bulk" style="font-size:2rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
  <div style="font-size:.88rem;color:var(--text-muted);margin-bottom:14px;">Aucun courrier trouvé.</div>
  <?php if ($peut_creer): ?>
    <a href="form.php?sens=ENTRANT" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Enregistrer un courrier</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="table-wrap card">
<table class="crict-table">
  <thead>
    <tr>
      <th>Référence</th>
      <th>Sens / Canal</th>
      <th>Objet</th>
      <th>Correspondant</th>
      <th>Service / Affecté</th>
      <th>Priorité</th>
      <th>Statut</th>
      <th>Réception</th>
      <th>Docs</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($courriers as $c):
    $st = $STATUTS[$c['statut']] ?? $STATUTS['RECU'];
    $pr = $PRIOS[$c['priorite']] ?? $PRIOS['NORMALE'];
    $cn = $CANAUX[$c['canal']] ?? $CANAUX['AUTRE'];
    $en_retard = !empty($c['date_echeance']) && $c['date_echeance'] < date('Y-m-d')
                 && !in_array($c['statut'], ['TRAITE','CLOTURE','ARCHIVE']);
  ?>
    <tr>
      <td>
        <a href="voir.php?id=<?= $c['id'] ?>" style="font-family:var(--mono);font-size:.8rem;font-weight:700;color:var(--blue-600);text-decoration:none;">
          <?= e($c['reference']) ?>
        </a>
        <?php if ($c['confidentialite'] !== 'NORMAL'): ?>
          <div style="font-size:.66rem;color:#991b1b;font-weight:700;margin-top:2px;">
            <i class="fa-solid fa-lock"></i> <?= e($CONFS[$c['confidentialite']]['label']) ?>
          </div>
        <?php endif; ?>
      </td>
      <td>
        <span class="sens-pill <?= $c['sens']==='ENTRANT'?'sens-in':'sens-out' ?>">
          <i class="fa-solid <?= $c['sens']==='ENTRANT'?'fa-arrow-down-long':'fa-arrow-up-long' ?>"></i>
          <?= $c['sens']==='ENTRANT'?'Entrant':'Sortant' ?>
        </span>
        <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">
          <i class="fa-solid <?= $cn['icon'] ?>"></i> <?= e($cn['label']) ?>
        </div>
      </td>
      <td style="max-width:260px;">
        <div style="font-size:.84rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($c['objet']) ?>">
          <?= e($c['objet']) ?>
        </div>
        <div style="font-size:.7rem;color:var(--text-muted);"><?= e($TYPES[$c['type_courrier']] ?? $c['type_courrier']) ?></div>
      </td>
      <td>
        <div style="font-size:.82rem;"><?= e($c['correspondant_nom'] ?: '—') ?></div>
        <div style="font-size:.7rem;color:var(--text-muted);"><?= e($c['correspondant_organisation'] ?? '') ?></div>
      </td>
      <td>
        <div style="font-size:.8rem;"><?= e($c['service_nom'] ?: '—') ?></div>
        <?php if ($c['affecte_nom']): ?>
          <div style="font-size:.7rem;color:var(--blue-600);"><i class="fa-solid fa-user"></i> <?= e($c['affecte_nom']) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <span class="badge" style="background:<?= $pr['bg'] ?>;color:<?= $pr['color'] ?>;"><?= $pr['label'] ?></span>
      </td>
      <td>
        <span class="badge" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
          <i class="fa-solid <?= $st['icon'] ?>"></i> <?= $st['label'] ?>
        </span>
      </td>
      <td style="font-size:.76rem;color:var(--text-muted);white-space:nowrap;">
        <?= $c['date_reception'] ? format_date(substr($c['date_reception'],0,10)) : '—' ?>
        <?php if ($en_retard): ?>
          <div class="retard" style="font-size:.66rem;"><i class="fa-solid fa-clock"></i> Échéance dépassée</div>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <span style="font-size:.82rem;font-weight:700;color:var(--blue-600);"><?= (int)$c['nb_documents'] ?></span>
      </td>
      <td>
        <div class="act-btns">
          <a href="voir.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Voir"><i class="fa-solid fa-eye"></i></a>
          <?php if ($peut_modifier): ?>
            <a href="form.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier"><i class="fa-solid fa-pen"></i></a>
          <?php endif; ?>
          <?php if ($peut_supprimer): ?>
            <button type="button" title="Supprimer"
                    onclick="confirmerSuppr(<?= $c['id'] ?>, '<?= e(addslashes($c['reference'])) ?>')"
                    style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:7px;width:30px;height:30px;cursor:pointer;font-size:.78rem;">
              <i class="fa-solid fa-trash"></i>
            </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($pager['nb_pages'] > 1):
  $qb = array_filter(array_diff_key($_GET, ['page'=>1]));
?>
<div class="pager">
  <?php if ($pager['a_precedente']): ?>
    <a href="?<?= http_build_query(array_merge($qb,['page'=>$page-1])) ?>"><i class="fa-solid fa-chevron-left"></i></a>
  <?php endif; ?>
  <?php for ($pg=1;$pg<=$pager['nb_pages'];$pg++):
    if($pg===$page): ?><span class="cur"><?= $pg ?></span>
    <?php elseif($pg===1||$pg===$pager['nb_pages']||abs($pg-$page)<=1): ?>
      <a href="?<?= http_build_query(array_merge($qb,['page'=>$pg])) ?>"><?= $pg ?></a>
    <?php elseif(abs($pg-$page)===2): ?><span class="dots">…</span>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($pager['a_suivante']): ?>
    <a href="?<?= http_build_query(array_merge($qb,['page'=>$page+1])) ?>"><i class="fa-solid fa-chevron-right"></i></a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Form actions caché -->
<form method="POST" action="actions.php" id="formAction" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action"      id="faAction">
  <input type="hidden" name="courrier_id" id="faId">
</form>

<?php
$extra_scripts = '<script>
function appF() {
  const qs = new URLSearchParams({
    q:        document.getElementById("fq").value,
    sens:     document.getElementById("fsens").value,
    statut:   document.getElementById("fstatut").value,
    canal:    document.getElementById("fcanal").value,
    priorite: document.getElementById("fpriorite").value,
    service_id: document.getElementById("fservice").value,
    page: 1
  });
  for (const [k,v] of [...qs.entries()]) if(!v) qs.delete(k);
  location.href = "?" + qs;
}
document.getElementById("fq").addEventListener("keydown", e => { if(e.key==="Enter") appF(); });
function confirmerSuppr(id, ref) {
  if (!confirm("Supprimer définitivement le courrier \"" + ref + "\" et toutes ses pièces ?")) return;
  document.getElementById("faAction").value = "supprimer";
  document.getElementById("faId").value = id;
  document.getElementById("formAction").submit();
}
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
