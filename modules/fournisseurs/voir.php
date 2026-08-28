<?php
// modules/fournisseurs/voir.php — Fiche détaillée d'un fournisseur
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/fournisseur.php';

session_demarrer();
exiger_permission('FOURNISSEURS', 'VOIR');

$fid  = (int)($_GET['id'] ?? 0);
$four = $fid ? fournisseur_charger($fid) : null;
if (!$four) { set_flash('error','Fournisseur introuvable.'); redirect('/modules/fournisseurs/index.php'); }

$peut_modifier  = a_permission('FOURNISSEURS', 'MODIFIER');
$peut_valider   = a_permission('FOURNISSEURS', 'VALIDER');
$peut_supprimer = a_permission('FOURNISSEURS', 'SUPPRIMER');

$statut_cfg = [
    'en_attente' => ['label'=>'En attente', 'bg'=>'#fef3c7','color'=>'#92400e','bord'=>'#f59e0b','icon'=>'fa-hourglass-half'],
    'valide'     => ['label'=>'Validé',     'bg'=>'#dcfce7','color'=>'#166534','bord'=>'#22c55e','icon'=>'fa-circle-check'],
    'rejete'     => ['label'=>'Rejeté',     'bg'=>'#fee2e2','color'=>'#991b1b','bord'=>'#ef4444','icon'=>'fa-circle-xmark'],
    'suspendu'   => ['label'=>'Suspendu',   'bg'=>'#f3f4f6','color'=>'#6b7280','bord'=>'#9ca3af','icon'=>'fa-ban'],
];
$sc = $statut_cfg[$four['statut']] ?? $statut_cfg['en_attente'];

// Grouper les documents par type
$types_labels = [
    'piece_identite' => ['Pièce d\'identité','fa-id-card','#dbeafe','#1d4ed8'],
    'rccm'           => ['RCCM',              'fa-building','#fef3c7','#92400e'],
    'nif'            => ['NIF',               'fa-file-invoice','#dcfce7','#166534'],
    'quittance'      => ['Quittance',         'fa-receipt','#fce7f3','#9d174d'],
    'autre'          => ['Autre',             'fa-paperclip','#f3f4f6','#6b7280'],
];
$docs_par_type = [];
foreach ($four['documents'] as $d) { $docs_par_type[$d['type_document']][] = $d; }

$page_titre      = e($four['nom_entreprise']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Fournisseurs</a> · '.e($four['nom_entreprise']);
$module_actif    = 'FOURNISSEURS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
/* Header fournisseur */
.fh{background:var(--surface);border:1.5px solid var(--border);border-left:5px solid <?= $sc['bord'] ?>;border-radius:14px;padding:20px 24px;margin-bottom:20px;}
.fh-top{display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;}
.fh-av{width:56px;height:56px;border-radius:12px;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:800;flex-shrink:0;}
.fh-titre{font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:5px;}
.fh-sub{font-size:.78rem;color:var(--text-muted);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}

/* Info grid */
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:16px;}
.info-block{background:var(--bg);border-radius:9px;padding:11px 13px;}
.ib-lbl{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.ib-val{font-size:.85rem;font-weight:600;color:var(--text);}
.ib-val.mono{font-family:var(--mono);}

/* Documents */
.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:9px;}
.doc-card{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:11px 13px;display:flex;gap:9px;align-items:center;transition:box-shadow .14s;}
.doc-card:hover{box-shadow:0 3px 12px rgba(13,50,120,.08);}
.doc-ico{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}

/* Panel validation */
.val-panel{background:var(--surface);border:2px solid var(--blue-300);border-radius:13px;padding:20px 22px;margin-bottom:20px;}
.val-panel h3{font-size:.9rem;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:7px;}
.val-btns{display:flex;gap:9px;flex-wrap:wrap;}

/* Tabs */
.tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:18px;border-bottom:2px solid var(--border);}
.tab{padding:9px 17px;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;cursor:pointer;background:none;border:1.5px solid transparent;border-bottom:none;color:var(--text-muted);transition:all .14s;}
.tab:hover{color:var(--blue-600);background:var(--blue-50);}
.tab.on{color:var(--blue-600);background:var(--surface);border-color:var(--border);border-bottom:2px solid var(--surface);margin-bottom:-2px;}
.tp{display:none;}.tp.on{display:block;}

/* Note étoiles */
.stars{color:#f59e0b;}
</style>

<!-- Bouton retour -->
<div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <?php if ($peut_modifier): ?>
    <a href="form.php?id=<?= $fid ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
  <?php endif; ?>
  <?php if ($peut_supprimer): ?>
    <button type="button" class="btn btn-sm"
            style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;"
            onclick="confirmerSuppr(<?= $fid ?>, '<?= e(addslashes($four['nom_entreprise'])) ?>')">
      <i class="fa-solid fa-trash"></i> Supprimer
    </button>
  <?php endif; ?>
</div>

<!-- Header -->
<div class="fh">
  <div class="fh-top">
    <div class="fh-av"><?= strtoupper(mb_substr($four['nom_entreprise'],0,2)) ?></div>
    <div style="flex:1;min-width:0;">
      <div class="fh-titre"><?= e($four['nom_entreprise']) ?></div>
      <div class="fh-sub">
        <?= ucfirst($four['type_fournisseur']) ?> · <?= e($four['categorie']) ?>
      </div>
      <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
        <span class="badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
          <i class="fa-solid <?= $sc['icon'] ?>"></i> <?= $sc['label'] ?>
        </span>
        <?php if ((float)$four['note_moyenne'] > 0): ?>
          <span class="badge" style="background:#fef3c7;color:#92400e;">
            <i class="fa-solid fa-star" style="color:#f59e0b;"></i>
            <?= number_format((float)$four['note_moyenne'],1) ?>/5
            (<?= (int)$four['nombre_evaluations'] ?> avis)
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <div style="font-size:.68rem;color:var(--text-muted);margin-bottom:3px;">Inscrit le</div>
      <div style="font-size:.85rem;font-weight:700;color:var(--blue-700);">
        <?= format_datetime($four['date_inscription']) ?>
      </div>
      <?php if ($four['statut'] === 'valide' && $four['date_validation']): ?>
        <div style="font-size:.7rem;color:var(--success);margin-top:3px;">
          <i class="fa-solid fa-check-circle"></i>
          Validé le <?= format_datetime($four['date_validation']) ?>
          <?= $four['validateur_nom'] ? ' par '.$four['validateur_nom'] : '' ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Info grid -->
  <div class="info-grid">
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-phone"></i> Téléphone</div>
      <div class="ib-val"><?= e($four['telephone']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-regular fa-envelope"></i> Email</div>
      <div class="ib-val"><a href="mailto:<?= e($four['email']) ?>" style="color:var(--blue-600);text-decoration:none;"><?= e($four['email']) ?></a></div>
    </div>
    <?php if ($four['site_web']): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-globe"></i> Site web</div>
      <div class="ib-val"><a href="<?= e($four['site_web']) ?>" target="_blank" rel="noopener" style="color:var(--blue-600);text-decoration:none;"><?= e($four['site_web']) ?></a></div>
    </div>
    <?php endif; ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-user-tie"></i> Gérant</div>
      <div class="ib-val"><?= e($four['nom_gerant']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-phone"></i> Tél. gérant</div>
      <div class="ib-val"><?= e($four['telephone_gerant']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-building"></i> RCCM</div>
      <div class="ib-val mono"><?= e($four['numero_rccm']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-file-invoice"></i> NIF</div>
      <div class="ib-val mono"><?= e($four['nif']) ?></div>
    </div>
    <?php if ($four['quittance']): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-receipt"></i> Quittance</div>
      <div class="ib-val mono"><?= e($four['quittance']) ?></div>
    </div>
    <?php endif; ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-user"></i> Identifiant</div>
      <div class="ib-val mono"><?= e($four['nom_utilisateur']) ?></div>
    </div>
  </div>
</div>

<!-- Motif rejet si applicable -->
<?php if ($four['statut'] === 'rejete' && $four['motif_rejet']): ?>
<div class="alert alert-error" style="margin-bottom:16px;">
  <i class="fa-solid fa-circle-xmark" style="flex-shrink:0;margin-top:2px;"></i>
  <div><strong>Motif du rejet :</strong> <?= nl2br(e($four['motif_rejet'])) ?></div>
</div>
<?php endif; ?>

<!-- Panneau validation -->
<?php if ($peut_valider && $four['statut'] === 'en_attente'): ?>
<div class="val-panel">
  <h3><i class="fa-solid fa-gavel" style="color:var(--blue-500);"></i> Décision de validation</h3>
  <div class="val-btns">
    <button type="button" class="btn btn-success"
            onclick="changerStatut(<?= $fid ?>, 'valider', '<?= e(addslashes($four['nom_entreprise'])) ?>')">
      <i class="fa-solid fa-circle-check"></i> Valider ce fournisseur
    </button>
    <button type="button" class="btn btn-danger"
            onclick="ouvrirRejet(<?= $fid ?>, '<?= e(addslashes($four['nom_entreprise'])) ?>')">
      <i class="fa-solid fa-circle-xmark"></i> Rejeter
    </button>
    <button type="button" class="btn btn-secondary"
            onclick="changerStatut(<?= $fid ?>, 'suspendu', '<?= e(addslashes($four['nom_entreprise'])) ?>')">
      <i class="fa-solid fa-ban"></i> Suspendre
    </button>
  </div>
</div>
<?php elseif ($peut_valider && in_array($four['statut'], ['valide','suspendu'])): ?>
<div style="background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <span style="font-size:.84rem;font-weight:600;"><i class="fa-solid fa-exchange-alt" style="color:var(--blue-500);margin-right:6px;"></i> Changer le statut</span>
  <div style="display:flex;gap:7px;flex-wrap:wrap;margin-left:auto;">
    <?php if ($four['statut'] === 'valide'): ?>
      <button type="button" class="btn btn-secondary btn-sm"
              onclick="changerStatut(<?= $fid ?>, 'suspendu', '<?= e(addslashes($four['nom_entreprise'])) ?>')">
        <i class="fa-solid fa-ban"></i> Suspendre
      </button>
    <?php elseif ($four['statut'] === 'suspendu'): ?>
      <button type="button" class="btn btn-success btn-sm"
              onclick="changerStatut(<?= $fid ?>, 'valider', '<?= e(addslashes($four['nom_entreprise'])) ?>')">
        <i class="fa-solid fa-circle-check"></i> Réactiver
      </button>
    <?php endif; ?>
    <button type="button" class="btn btn-sm"
            style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;"
            onclick="ouvrirRejet(<?= $fid ?>, '<?= e(addslashes($four['nom_entreprise'])) ?>')">
      <i class="fa-solid fa-circle-xmark"></i> Rejeter
    </button>
  </div>
</div>
<?php endif; ?>

<!-- Onglets -->
<div class="tabs">
  <button class="tab on" onclick="sw('docs',this)">
    <i class="fa-solid fa-paperclip"></i> Documents (<?= count($four['documents']) ?>)
  </button>
  <button class="tab" onclick="sw('infos',this)">
    <i class="fa-solid fa-circle-info"></i> Informations complémentaires
  </button>
</div>

<!-- TAB DOCUMENTS -->
<div class="tp on" id="tp-docs">
  <?php if (empty($four['documents'])): ?>
    <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-muted);">
      <i class="fa-solid fa-folder-open" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
      Aucun document joint.
      <?php if ($peut_modifier): ?>
        <div style="margin-top:10px;"><a href="form.php?id=<?= $fid ?>">Ajouter des documents</a></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($types_labels as $type => [$lbl, $ico, $bg, $co]):
      if (empty($docs_par_type[$type])) continue;
    ?>
    <div style="margin-bottom:18px;">
      <div style="font-size:.73rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">
        <i class="fa-solid <?= $ico ?>" style="color:<?= $co ?>;"></i> <?= $lbl ?>
      </div>
      <div class="doc-grid">
        <?php foreach ($docs_par_type[$type] as $doc):
          $ext = strtolower($doc['extension'] ?? '');
          $d_ico = in_array($ext,['doc','docx']) ? 'fa-file-word'
                 : (in_array($ext,['jpg','jpeg','png','webp']) ? 'fa-image' : 'fa-file-pdf');
        ?>
        <div class="doc-card">
          <div class="doc-ico" style="background:<?= $bg ?>;color:<?= $co ?>;"><i class="fa-solid <?= $d_ico ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.81rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                 title="<?= e($doc['nom_original']) ?>"><?= e($doc['nom_original']) ?></div>
            <div style="font-size:.68rem;color:var(--text-muted);">
              <?= e($doc['deposant'] ?? '') ?>
              <?= $doc['taille_fichier'] ? ' · '.round($doc['taille_fichier']/1024,1).' Ko' : '' ?>
              · <?= format_datetime($doc['date_upload']) ?>
            </div>
          </div>
          <a href="download.php?id=<?= $doc['id'] ?>" target="_blank"
             class="btn btn-secondary btn-sm btn-icon" title="Télécharger">
            <i class="fa-solid fa-download"></i>
          </a>
          <?php if ($peut_modifier): ?>
          <form method="POST" action="actions.php" style="display:inline;"
                onsubmit="return confirm('Supprimer ce document ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action"    value="supprimer_doc">
            <input type="hidden" name="doc_id"    value="<?= $doc['id'] ?>">
            <input type="hidden" name="retour_id" value="<?= $fid ?>">
            <button type="submit"
                    style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:6px;width:26px;height:26px;cursor:pointer;font-size:.72rem;padding:0;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ($peut_modifier): ?>
    <div style="margin-top:10px;">
      <a href="form.php?id=<?= $fid ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-plus"></i> Ajouter des documents
      </a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- TAB INFOS COMPLÉMENTAIRES -->
<div class="tp" id="tp-infos">
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-key" style="color:var(--blue-500);"></i><h2>Compte d'accès</h2></div>
      <div class="card-body" style="padding:16px 20px;">
        <div style="margin-bottom:10px;">
          <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Nom d'utilisateur</div>
          <code style="font-size:.88rem;background:var(--bg);padding:4px 8px;border-radius:6px;"><?= e($four['nom_utilisateur']) ?></code>
        </div>
        <?php if ($peut_modifier): ?>
        <a href="form.php?id=<?= $fid ?>" class="btn btn-secondary btn-sm" style="margin-top:4px;">
          <i class="fa-solid fa-key"></i> Changer le mot de passe
        </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><i class="fa-solid fa-star" style="color:#f59e0b;"></i><h2>Évaluation</h2></div>
      <div class="card-body" style="padding:16px 20px;text-align:center;">
        <?php if ((float)$four['note_moyenne'] > 0): ?>
          <div style="font-size:2rem;font-weight:800;color:var(--text);"><?= number_format((float)$four['note_moyenne'],1) ?><span style="font-size:1rem;color:var(--text-muted);">/5</span></div>
          <div class="stars" style="font-size:1.2rem;margin:6px 0;">
            <?php for ($i=1;$i<=5;$i++): ?>
              <i class="fa-<?= $i<=round($four['note_moyenne'])?'solid':'regular' ?> fa-star"></i>
            <?php endfor; ?>
          </div>
          <div style="font-size:.78rem;color:var(--text-muted);"><?= (int)$four['nombre_evaluations'] ?> évaluation<?= $four['nombre_evaluations']>1?'s':'' ?></div>
        <?php else: ?>
          <div style="color:var(--text-muted);font-size:.85rem;padding:10px 0;">Aucune évaluation pour l'instant.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal rejet -->
<div class="modal-overlay" id="modalRejet">
  <div class="modal" style="max-width:440px;">
    <div class="modal-header">
      <i class="fa-solid fa-circle-xmark" style="color:var(--danger);"></i>
      <h3 id="rejetTitre">Rejeter le fournisseur</h3>
      <button class="modal-close" onclick="document.getElementById('modalRejet').classList.remove('open')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action"         value="rejeter">
      <input type="hidden" name="fournisseur_id" id="rejetId" value="<?= $fid ?>">
      <input type="hidden" name="retour_id"      value="<?= $fid ?>">
      <div class="modal-body">
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

<!-- Form actions caché -->
<form method="POST" action="actions.php" id="formAct" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action"         id="faAct">
  <input type="hidden" name="fournisseur_id" value="<?= $fid ?>">
  <input type="hidden" name="retour_id"      value="<?= $fid ?>">
</form>

<?php
$extra_scripts = '<script>
function sw(id,btn){
  document.querySelectorAll(".tp").forEach(p=>p.classList.remove("on"));
  document.querySelectorAll(".tab").forEach(b=>b.classList.remove("on"));
  document.getElementById("tp-"+id).classList.add("on");
  btn.classList.add("on");
}
function changerStatut(id, action, nom) {
  const msgs = {valider:"Valider",suspendu:"Suspendre",rejeter:"Rejeter"};
  if(!confirm((msgs[action]||action)+" le fournisseur \""+nom+"\" ?")) return;
  document.getElementById("faAct").value = action;
  document.getElementById("formAct").submit();
}
function ouvrirRejet(id, nom) {
  document.getElementById("rejetId").value = id;
  document.getElementById("rejetTitre").textContent = "Rejeter : " + nom;
  document.getElementById("modalRejet").classList.add("open");
  setTimeout(()=>document.querySelector("#modalRejet textarea").focus(),150);
}
function confirmerSuppr(id, nom) {
  if(!confirm("Supprimer définitivement \""+nom+"\" et tous ses documents ?")) return;
  document.getElementById("faAct").value = "supprimer";
  document.getElementById("formAct").submit();
}
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
