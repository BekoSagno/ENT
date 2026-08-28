<?php
// modules/fournisseurs/index.php — Liste des fournisseurs
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/fournisseur.php';

session_demarrer();
exiger_permission('FOURNISSEURS', 'VOIR');

$peut_creer    = a_permission('FOURNISSEURS', 'CREER');
$peut_valider  = a_permission('FOURNISSEURS', 'VALIDER');
$peut_supprimer= a_permission('FOURNISSEURS', 'SUPPRIMER');

// Filtres
$f = [
    'statut'    => clean($_GET['statut']    ?? ''),
    'type'      => clean($_GET['type']      ?? ''),
    'categorie' => clean($_GET['categorie'] ?? ''),
    'q'         => clean($_GET['q']         ?? ''),
];

$par_page = 15;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $par_page;
$result   = fournisseur_lister($f, $par_page, $offset);
$fournisseurs = $result['data'];
$total        = $result['total'];
$pager        = paginer($total, $par_page, $page);
$cpt          = fournisseur_compteurs();
$categories   = fournisseur_categories();

$statut_cfg = [
    'en_attente' => ['label'=>'En attente', 'bg'=>'#fef3c7','color'=>'#92400e','icon'=>'fa-hourglass-half'],
    'valide'     => ['label'=>'Validé',     'bg'=>'#dcfce7','color'=>'#166534','icon'=>'fa-circle-check'],
    'rejete'     => ['label'=>'Rejeté',     'bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'fa-circle-xmark'],
    'suspendu'   => ['label'=>'Suspendu',   'bg'=>'#f3f4f6','color'=>'#6b7280','icon'=>'fa-ban'],
];

$page_titre      = 'Fournisseurs';
$page_breadcrumb = 'Gestion des Fournisseurs';
$module_actif    = 'FOURNISSEURS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:22px;}
.sc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;transition:box-shadow .15s;cursor:pointer;}
.sc:hover{box-shadow:var(--shadow);}
.sc-val{font-size:1.6rem;font-weight:800;line-height:1;margin-bottom:4px;}
.sc-lbl{font-size:.73rem;color:var(--text-muted);}

/* Filtres */
.filtres-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
.fg{display:flex;flex-direction:column;gap:4px;}
.fg label{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;}

/* Tableau */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;}
.note-stars{color:#f59e0b;font-size:.75rem;}

/* Actions inline */
.act-btns{display:flex;gap:5px;flex-wrap:nowrap;}

/* Colonne Actions toujours visible (même si le tableau déborde) */
.crict-table th:last-child,
.crict-table td:last-child{
  position:sticky;right:0;background:var(--surface);
  box-shadow:-6px 0 8px -6px rgba(0,0,0,.12);z-index:2;
}
.crict-table thead th:last-child{background:var(--bg);}
.lien-entreprise{color:inherit;text-decoration:none;}
.lien-entreprise:hover{color:var(--blue-600);}

/* Pagination */
.pager{display:flex;gap:5px;justify-content:center;flex-wrap:wrap;margin-top:20px;}
.pager a,.pager span{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 8px;border-radius:8px;border:1px solid var(--border);background:var(--surface);font-size:.82rem;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all .12s;}
.pager a:hover{border-color:var(--blue-400);color:var(--blue-600);}
.pager span.cur{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}
.pager .dots{border:none;background:none;}

/* Modal */
.modal-sm{max-width:440px;}

/* Sous-infos compactes dans la colonne Entreprise */
.frn-meta{font-size:.7rem;color:var(--text-muted);margin-top:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.frn-meta .stars{color:#f59e0b;}

/* ── Vue en CARTES sur tablette / mobile (≤ 860px) ── */
@media (max-width: 860px){
  .frn-table thead{display:none;}
  .frn-table, .frn-table tbody{display:block;width:100%;}
  .frn-table tr{
    display:block;background:var(--surface);border:1.5px solid var(--border);
    border-radius:12px;margin-bottom:12px;padding:4px 0;
  }
  .frn-table td{
    display:flex;justify-content:space-between;align-items:flex-start;gap:14px;
    padding:9px 16px;border:none;text-align:right;
  }
  .frn-table td::before{
    content:attr(data-label);font-weight:700;color:var(--text-muted);
    font-size:.66rem;text-transform:uppercase;letter-spacing:.05em;
    text-align:left;flex-shrink:0;padding-top:2px;
  }
  /* Désactiver la colonne Actions "collée" en mode cartes */
  .frn-table td:last-child,
  .frn-table th:last-child{position:static;box-shadow:none;background:transparent;}
  .frn-table td:last-child{justify-content:flex-end;}
  .frn-table .act-btns{flex-wrap:wrap;justify-content:flex-end;}
  .frn-meta{justify-content:flex-end;}
}
</style>

<!-- En-tête -->
<div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);">
    Répertoire des entreprises fournissant des biens et services au CRICT.
  </div>
  <?php if ($peut_creer): ?>
    <button type="button" class="btn btn-secondary" id="btnLienInscr"
            data-lien="<?= e(APP_URL) ?>/modules/fournisseurs/inscription.php">
      <i class="fa-solid fa-link"></i> Copier le lien d'inscription
    </button>
    <a href="form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau fournisseur</a>
  <?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-row">
  <?php
  $sd = [
    ['val'=>$cpt['TOTAL'],       'lbl'=>'Total',       'color'=>'var(--blue-600)', 'statut'=>''],
    ['val'=>$cpt['en_attente'],  'lbl'=>'En attente',  'color'=>'#f59e0b',         'statut'=>'en_attente'],
    ['val'=>$cpt['valide'],      'lbl'=>'Validés',     'color'=>'#22c55e',         'statut'=>'valide'],
    ['val'=>$cpt['rejete'],      'lbl'=>'Rejetés',     'color'=>'#ef4444',         'statut'=>'rejete'],
    ['val'=>$cpt['suspendu'],    'lbl'=>'Suspendus',   'color'=>'#9ca3af',         'statut'=>'suspendu'],
  ];
  foreach ($sd as $s):
    $qs = http_build_query(array_merge($f, ['statut'=>$s['statut'],'page'=>1]));
  ?>
  <div class="sc" onclick="location.href='?<?= $qs ?>'">
    <div class="sc-val" style="color:<?= $s['color'] ?>;"><?= $s['val'] ?></div>
    <div class="sc-lbl"><?= $s['lbl'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtres -->
<div class="filtres-bar">
  <div class="fg" style="flex:2;min-width:160px;">
    <label><i class="fa-solid fa-magnifying-glass"></i> Recherche</label>
    <input type="text" id="fq" class="form-control" placeholder="Nom, email, RCCM, gérant…"
           value="<?= e($f['q']) ?>">
  </div>
  <div class="fg">
    <label>Statut</label>
    <select id="fstatut" class="form-control">
      <option value="">Tous</option>
      <?php foreach ($statut_cfg as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $f['statut']===$k?'selected':'' ?>><?= $v['label'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="fg">
    <label>Type</label>
    <select id="ftype" class="form-control">
      <option value="">Tous</option>
      <option value="biens"    <?= $f['type']==='biens'?'selected':'' ?>>Biens</option>
      <option value="services" <?= $f['type']==='services'?'selected':'' ?>>Services</option>
    </select>
  </div>
  <div class="fg">
    <label>Catégorie</label>
    <select id="fcategorie" class="form-control">
      <option value="">Toutes</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $f['categorie']===$cat?'selected':'' ?>><?= e($cat) ?></option>
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

<!-- Résumé -->
<div style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px;">
  <strong style="color:var(--text);"><?= $total ?></strong> fournisseur<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?>
</div>

<!-- Tableau -->
<?php if (empty($fournisseurs)): ?>
<div style="text-align:center;padding:60px 24px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);">
  <i class="fa-solid fa-truck" style="font-size:2rem;color:var(--blue-300);display:block;margin-bottom:10px;"></i>
  <div style="font-size:.88rem;color:var(--text-muted);margin-bottom:14px;">Aucun fournisseur trouvé.</div>
  <?php if ($peut_creer): ?>
    <a href="form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Ajouter un fournisseur</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="table-wrap">
<table class="crict-table frn-table">
  <thead>
    <tr>
      <th>Entreprise</th>
      <th>Type / Catégorie</th>
      <th>Gérant &amp; contact</th>
      <th>RCCM / NIF</th>
      <th>Statut</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($fournisseurs as $four): $sc = $statut_cfg[$four['statut']] ?? $statut_cfg['en_attente']; ?>
    <tr>
      <td data-label="Entreprise">
        <a href="voir.php?id=<?= $four['id'] ?>" class="lien-entreprise">
          <div style="font-weight:700;font-size:.88rem;"><?= e($four['nom_entreprise']) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted);"><?= e($four['email']) ?></div>
        </a>
        <div class="frn-meta">
          <span><i class="fa-regular fa-calendar"></i> <?= format_date(substr($four['date_inscription'],0,10)) ?></span>
          <span><i class="fa-solid fa-paperclip"></i> <?= (int)$four['nb_documents'] ?> doc<?= (int)$four['nb_documents']>1?'s':'' ?></span>
          <?php if ((float)$four['note_moyenne'] > 0): ?>
            <span class="stars"><i class="fa-solid fa-star"></i> <?= number_format((float)$four['note_moyenne'],1) ?>/5</span>
          <?php endif; ?>
        </div>
      </td>
      <td data-label="Type / Catégorie">
        <span style="font-size:.8rem;font-weight:600;text-transform:capitalize;"><?= e($four['type_fournisseur']) ?></span><br>
        <span style="font-size:.72rem;color:var(--text-muted);"><?= e($four['categorie']) ?></span>
      </td>
      <td data-label="Gérant &amp; contact">
        <div style="font-size:.84rem;"><?= e($four['nom_gerant']) ?></div>
        <?php if ($four['telephone_gerant']): ?><div style="font-size:.72rem;color:var(--text-muted);"><i class="fa-solid fa-user"></i> <?= e($four['telephone_gerant']) ?></div><?php endif; ?>
        <?php if ($four['telephone']): ?><div style="font-size:.72rem;color:var(--text-muted);"><i class="fa-solid fa-phone"></i> <?= e($four['telephone']) ?></div><?php endif; ?>
        <?php if ($four['site_web']): ?>
          <a href="<?= e($four['site_web']) ?>" target="_blank" rel="noopener"
             style="font-size:.7rem;color:var(--blue-500);text-decoration:none;">
            <i class="fa-solid fa-globe"></i> Site
          </a>
        <?php endif; ?>
      </td>
      <td data-label="RCCM / NIF">
        <div style="font-size:.78rem;font-family:var(--mono);"><?= $four['numero_rccm'] ? e($four['numero_rccm']) : '—' ?></div>
        <div style="font-size:.72rem;color:var(--text-muted);font-family:var(--mono);"><?= $four['nif'] ? e($four['nif']) : '' ?></div>
      </td>
      <td data-label="Statut">
        <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
          <i class="fa-solid <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
        </span>
      </td>
      <td data-label="Actions">
        <div class="act-btns">
          <a href="voir.php?id=<?= $four['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Voir">
            <i class="fa-solid fa-eye"></i>
          </a>
          <?php if ($peut_creer): ?>
            <a href="form.php?id=<?= $four['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier">
              <i class="fa-solid fa-pen"></i>
            </a>
          <?php endif; ?>
          <?php if ($peut_valider && $four['statut'] === 'en_attente'): ?>
            <button type="button" title="Valider"
                    onclick="changerStatut(<?= $four['id'] ?>, 'valide', '<?= e(addslashes($four['nom_entreprise'])) ?>')"
                    style="background:#dcfce7;border:1px solid #bbf7d0;color:#166534;border-radius:7px;width:30px;height:30px;cursor:pointer;font-size:.78rem;">
              <i class="fa-solid fa-check"></i>
            </button>
            <button type="button" title="Rejeter"
                    onclick="ouvrirRejet(<?= $four['id'] ?>, '<?= e(addslashes($four['nom_entreprise'])) ?>')"
                    style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:7px;width:30px;height:30px;cursor:pointer;font-size:.78rem;">
              <i class="fa-solid fa-xmark"></i>
            </button>
          <?php endif; ?>
          <?php if ($peut_supprimer): ?>
            <button type="button" title="Supprimer"
                    onclick="confirmerSuppr(<?= $four['id'] ?>, '<?= e(addslashes($four['nom_entreprise'])) ?>')"
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

<!-- Modal rejet -->
<div class="modal-overlay" id="modalRejet">
  <div class="modal modal-sm">
    <div class="modal-header">
      <i class="fa-solid fa-circle-xmark" style="color:var(--danger);"></i>
      <h3>Rejeter le fournisseur</h3>
      <button class="modal-close" onclick="document.getElementById('modalRejet').classList.remove('open')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action"        value="rejeter">
      <input type="hidden" name="fournisseur_id" id="rejetId">
      <div class="modal-body">
        <div id="rejetNom" style="font-size:.85rem;font-weight:600;margin-bottom:12px;"></div>
        <div class="form-group">
          <label class="form-label">Motif du rejet <span style="color:var(--danger);">*</span></label>
          <textarea name="motif" class="form-control" rows="4"
                    placeholder="Expliquez la raison du rejet…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                onclick="document.getElementById('modalRejet').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-danger">
          <i class="fa-solid fa-circle-xmark"></i> Confirmer le rejet
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Form actions cachées -->
<form method="POST" action="actions.php" id="formAction" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action"         id="faAction">
  <input type="hidden" name="fournisseur_id" id="faId">
</form>

<?php
$extra_scripts = '<script>
function appF() {
  const qs = new URLSearchParams({
    q:         document.getElementById("fq").value,
    statut:    document.getElementById("fstatut").value,
    type:      document.getElementById("ftype").value,
    categorie: document.getElementById("fcategorie").value,
    page: 1
  });
  for (const [k,v] of [...qs.entries()]) if(!v) qs.delete(k);
  location.href = "?" + qs;
}
document.getElementById("fq").addEventListener("keydown", e => { if(e.key==="Enter") appF(); });

function changerStatut(id, statut, nom) {
  if (!confirm("Valider le fournisseur \"" + nom + "\" ?")) return;
  document.getElementById("faAction").value = statut === "valide" ? "valider" : statut;
  document.getElementById("faId").value = id;
  document.getElementById("formAction").submit();
}

function ouvrirRejet(id, nom) {
  document.getElementById("rejetId").value = id;
  document.getElementById("rejetNom").textContent = nom;
  document.getElementById("modalRejet").classList.add("open");
  setTimeout(() => document.querySelector("#modalRejet textarea").focus(), 150);
}

function confirmerSuppr(id, nom) {
  if (!confirm("Supprimer définitivement \"" + nom + "\" et tous ses documents ?")) return;
  document.getElementById("faAction").value = "supprimer";
  document.getElementById("faId").value = id;
  document.getElementById("formAction").submit();
}

(function(){
  const b = document.getElementById("btnLienInscr");
  if (!b) return;
  b.addEventListener("click", async function(){
    const lien = b.dataset.lien;
    const ok = () => { const t = b.innerHTML; b.innerHTML = "<i class=\"fa-solid fa-check\"></i> Lien copié !"; setTimeout(()=>{b.innerHTML=t;}, 1800); };
    try {
      await navigator.clipboard.writeText(lien); ok();
    } catch(e) {
      const ta = document.createElement("textarea"); ta.value = lien; document.body.appendChild(ta);
      ta.select(); try { document.execCommand("copy"); ok(); } catch(_){ prompt("Copiez le lien :", lien); }
      document.body.removeChild(ta);
    }
  });
})();
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
