<?php
// modules/ged/index.php — Gestion Électronique de Documents : liste des dépôts
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/ged.php';

session_demarrer();
exiger_permission('GED', 'VOIR');

$uid = (int)user_id();

$filtres = [
    'visibilite' => clean($_GET['visibilite'] ?? ''),
    'q'          => clean($_GET['q'] ?? ''),
];
$depots = ged_depots_visibles($uid, $filtres);
$stats  = ged_compteurs($uid);

$users   = ged_liste_users($uid);
$groupes = ged_liste_groupes();

$vis_meta = [
    'PRIVE'   => ['Privé',   'fa-lock',         '#dc2626', '#fee2e2'],
    'PARTAGE' => ['Partagé', 'fa-user-group',   '#7c3aed', '#ede9fe'],
    'PUBLIC'  => ['Public',  'fa-globe',        '#059669', '#d1fae5'],
];

$page_titre      = 'Gestion Électronique de Documents';
$page_breadcrumb = 'GED · Mes dépôts';
$module_actif    = 'GED';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.kpi-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px;}
.kcard{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;}
.kcard .v{font-size:1.5rem;font-weight:800;line-height:1;margin-bottom:3px;}
.kcard .l{font-size:.72rem;color:var(--text-muted);}

.depots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;}
.dc{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .18s,transform .15s;display:flex;flex-direction:column;}
.dc:hover{box-shadow:0 6px 22px rgba(13,50,120,.1);transform:translateY(-2px);}
.dc-top{height:5px;}
.dc-body{padding:16px 18px;flex:1;}
.dc-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px;}
.dc-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.dc-nom{font-size:.95rem;font-weight:800;line-height:1.2;margin-bottom:3px;}
.dc-desc{font-size:.78rem;color:var(--text-muted);line-height:1.45;min-height:34px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;}
.dc-stats{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:12px;}
.dc-stat{background:var(--bg);border-radius:8px;padding:8px 10px;text-align:center;}
.dc-stat .sv{font-size:1.1rem;font-weight:800;}
.dc-stat .sl{font-size:.66rem;color:var(--text-muted);}
.dc-footer{padding:10px 16px;border-top:1px solid var(--border);background:var(--bg);display:flex;gap:6px;align-items:center;}

.empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
.empty-state i{font-size:2.5rem;opacity:.4;margin-bottom:14px;}

.swatch{width:30px;height:30px;border-radius:8px;cursor:pointer;border:3px solid transparent;display:inline-flex;align-items:center;justify-content:center;color:#fff;}
.swatch.sel{border-color:var(--text);}
.icon-pick{width:38px;height:38px;border-radius:9px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:.95rem;}
.icon-pick.sel{border-color:var(--blue-500);background:var(--blue-50);color:var(--blue-600);}

.membre-row{display:none;}
.user-pick-list{max-height:230px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px;padding:6px;}
.user-pick{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;}
.user-pick:hover{background:var(--blue-50);}
.user-pick .meta{flex:1;min-width:0;}
.user-pick .meta .n{font-size:.83rem;font-weight:600;}
.user-pick .meta .g{font-size:.7rem;color:var(--text-muted);}
.user-pick select{width:auto;padding:4px 8px;font-size:.76rem;}
</style>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;">
  <div style="flex:1;font-size:.8rem;color:var(--text-muted);min-width:200px;">
    Créez des dépôts pour organiser et partager vos documents : gardez-les privés, partagez-les avec
    des utilisateurs ou des groupes choisis, ou rendez-les publics.
  </div>
  <?php if (a_permission('GED','CREER')): ?>
    <button type="button" class="btn btn-primary" onclick="ouvrirDepot()">
      <i class="fa-solid fa-folder-plus"></i> Nouveau dépôt
    </button>
  <?php endif; ?>
</div>

<!-- KPI -->
<div class="kpi-row">
  <div class="kcard"><div class="v" style="color:var(--blue-600);"><?= $stats['TOTAL'] ?></div><div class="l">Dépôts accessibles</div></div>
  <div class="kcard"><div class="v" style="color:#dc2626;"><?= $stats['PRIVE'] ?></div><div class="l">Privés</div></div>
  <div class="kcard"><div class="v" style="color:#7c3aed;"><?= $stats['PARTAGE'] ?></div><div class="l">Partagés</div></div>
  <div class="kcard"><div class="v" style="color:#059669;"><?= $stats['PUBLIC'] ?></div><div class="l">Publics</div></div>
  <div class="kcard"><div class="v" style="color:var(--accent-dk);"><?= $stats['DOCS'] ?></div><div class="l">Documents</div></div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom:18px;">
  <form method="GET" class="search-bar" style="border-bottom:none;">
    <div class="search-input-wrap">
      <i class="fa-solid fa-search"></i>
      <input type="text" name="q" class="form-control" placeholder="Rechercher un dépôt…"
             value="<?= e($filtres['q']) ?>">
    </div>
    <select name="visibilite" class="form-control" style="max-width:170px;" onchange="this.form.submit()">
      <option value="">Toutes visibilités</option>
      <option value="PRIVE"   <?= $filtres['visibilite']==='PRIVE'?'selected':'' ?>>Privés</option>
      <option value="PARTAGE" <?= $filtres['visibilite']==='PARTAGE'?'selected':'' ?>>Partagés</option>
      <option value="PUBLIC"  <?= $filtres['visibilite']==='PUBLIC'?'selected':'' ?>>Publics</option>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filtrer</button>
    <?php if ($filtres['q'] || $filtres['visibilite']): ?>
      <a href="index.php" class="btn btn-secondary">Réinitialiser</a>
    <?php endif; ?>
  </form>
</div>

<?php if (empty($depots)): ?>
  <div class="card"><div class="empty-state">
    <i class="fa-solid fa-folder-open"></i>
    <p style="font-weight:600;color:var(--text);">Aucun dépôt pour l'instant</p>
    <p style="font-size:.82rem;">Créez votre premier dépôt pour commencer à classer vos documents.</p>
  </div></div>
<?php else: ?>
  <div class="depots-grid">
  <?php foreach ($depots as $d):
      [$vis_lbl,$vis_ico,$vis_col,$vis_bg] = $vis_meta[$d['visibilite']];
      $proprio = (int)$d['est_proprietaire'] === 1;
  ?>
    <div class="dc">
      <div class="dc-top" style="background:<?= e($d['couleur']) ?>;"></div>
      <div class="dc-body">
        <div class="dc-head">
          <div class="dc-icon" style="background:<?= e($d['couleur']) ?>22;color:<?= e($d['couleur']) ?>;">
            <i class="fa-solid <?= e($d['icone']) ?>"></i>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="dc-nom"><?= e($d['nom']) ?></div>
            <span class="badge" style="background:<?= $vis_bg ?>;color:<?= $vis_col ?>;">
              <i class="fa-solid <?= $vis_ico ?>"></i> <?= $vis_lbl ?>
            </span>
            <?php if (!$proprio): ?>
              <span class="badge" style="background:var(--blue-100);color:var(--blue-700);margin-left:4px;">
                <i class="fa-solid fa-user"></i> <?= e($d['proprietaire_nom']) ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
        <div class="dc-desc">
          <?= $d['description'] ? e(mb_strimwidth($d['description'],0,90,'…')) : '<em>Aucune description</em>' ?>
        </div>
        <div class="dc-stats">
          <div class="dc-stat">
            <div class="sv" style="color:var(--blue-600);"><?= (int)$d['nb_documents'] ?></div>
            <div class="sl">Document<?= $d['nb_documents']>1?'s':'' ?></div>
          </div>
          <div class="dc-stat">
            <div class="sv" style="color:#7c3aed;"><?= (int)$d['nb_membres'] ?></div>
            <div class="sl">Membre<?= $d['nb_membres']>1?'s':'' ?></div>
          </div>
        </div>
      </div>
      <div class="dc-footer">
        <a href="depot.php?id=<?= (int)$d['id'] ?>" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-folder-open"></i> Ouvrir
        </a>
        <?php if ($proprio || _ged_est_admin()): ?>
          <form method="POST" action="actions.php" style="display:inline;margin-left:auto;"
                onsubmit="return confirm('Supprimer définitivement ce dépôt et tous ses documents ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="supprimer_depot">
            <input type="hidden" name="depot_id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="btn btn-sm btn-icon"
                    style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;" title="Supprimer">
              <i class="fa-solid fa-trash"></i>
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ============ MODAL : NOUVEAU DÉPÔT ============ -->
<div class="modal-overlay" id="modalDepot">
  <div class="modal">
    <form method="POST" action="actions.php" id="formDepot">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="creer_depot">
      <div class="modal-header">
        <h3><i class="fa-solid fa-folder-plus"></i> Nouveau dépôt</h3>
        <button type="button" class="modal-close" onclick="fermerDepot()"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom du dépôt *</label>
          <input type="text" name="nom" class="form-control" required maxlength="150"
                 placeholder="Ex : Rapports de recherche">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"
                    placeholder="À quoi sert ce dépôt ?"></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Couleur</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php foreach (GED_COULEURS as $i=>$col): ?>
                <div class="swatch <?= $i===0?'sel':'' ?>" style="background:<?= $col ?>;"
                     data-col="<?= $col ?>" onclick="choisirCouleur(this)">
                  <i class="fa-solid fa-check" style="font-size:.7rem;<?= $i===0?'':'opacity:0;' ?>"></i>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="couleur" id="couleurInput" value="<?= GED_COULEURS[0] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Icône</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php foreach (GED_ICONES as $i=>$ic): ?>
                <div class="icon-pick <?= $i===0?'sel':'' ?>" data-ic="<?= $ic ?>" onclick="choisirIcone(this)">
                  <i class="fa-solid <?= $ic ?>"></i>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="icone" id="iconeInput" value="<?= GED_ICONES[0] ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Visibilité *</label>
          <select name="visibilite" class="form-control" id="visibiliteSelect" onchange="toggleMembres()">
            <option value="PRIVE">🔒 Privé — accessible à moi seul</option>
            <option value="PARTAGE">👥 Partagé — utilisateurs sélectionnés</option>
            <option value="PUBLIC">🌍 Public — tous les utilisateurs</option>
          </select>
        </div>

        <div class="form-group membre-row" id="blocMembres">
          <label class="form-label">Utilisateurs autorisés</label>
          <div class="user-pick-list">
            <?php foreach ($users as $u): ?>
              <label class="user-pick">
                <input type="checkbox" name="membres[]" value="<?= (int)$u['id'] ?>"
                       onchange="toggleRole(this, <?= (int)$u['id'] ?>)">
                <div class="meta">
                  <div class="n"><?= e($u['nom_complet']) ?></div>
                  <div class="g"><?= e($u['groupe']) ?> · <?= e($u['matricule']) ?></div>
                </div>
                <select name="roles[<?= (int)$u['id'] ?>]" class="form-control" disabled>
                  <option value="LECTEUR">Lecture</option>
                  <option value="CONTRIBUTEUR">Contribution</option>
                </select>
              </label>
            <?php endforeach; ?>
          </div>
          <small style="color:var(--text-muted);font-size:.72rem;">
            « Lecture » : consulter et télécharger. « Contribution » : peut aussi déposer des documents.
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerDepot()">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer le dépôt</button>
      </div>
    </form>
  </div>
</div>

<script>
function ouvrirDepot(){ document.getElementById('modalDepot').classList.add('open'); }
function fermerDepot(){ document.getElementById('modalDepot').classList.remove('open'); }

function choisirCouleur(el){
  document.querySelectorAll('#modalDepot .swatch').forEach(s=>{s.classList.remove('sel');s.querySelector('i').style.opacity='0';});
  el.classList.add('sel'); el.querySelector('i').style.opacity='1';
  document.getElementById('couleurInput').value = el.dataset.col;
}
function choisirIcone(el){
  document.querySelectorAll('#modalDepot .icon-pick').forEach(s=>s.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('iconeInput').value = el.dataset.ic;
}
function toggleMembres(){
  const v = document.getElementById('visibiliteSelect').value;
  document.getElementById('blocMembres').style.display = (v === 'PARTAGE') ? 'block' : 'none';
}
function toggleRole(cb, id){
  const sel = document.querySelector('select[name="roles['+id+']"]');
  if (sel) sel.disabled = !cb.checked;
}
</script>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
