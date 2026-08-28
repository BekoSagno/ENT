<?php
// modules/ged/depot.php — Détail d'un dépôt : documents, upload, partage
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/ged.php';

session_demarrer();
exiger_permission('GED', 'VOIR');

$uid   = (int)user_id();
$id    = (int)($_GET['id'] ?? 0);
$depot = ged_depot_charger($id);
if (!$depot) { set_flash('error', 'Dépôt introuvable.'); redirect('/modules/ged/index.php'); }

$droits = ged_depot_droits($depot, $uid);
if (!$droits['voir']) { set_flash('error', 'Vous n\'avez pas accès à ce dépôt.'); redirect('/modules/ged/index.php'); }

// Sous-dossier courant (0 = racine du dépôt)
$dossier_id      = (int)($_GET['dossier'] ?? 0);
$dossier_courant = $dossier_id ? ged_dossier_charger($dossier_id) : null;
if ($dossier_courant && (int)$dossier_courant['depot_id'] !== $id) { $dossier_courant = null; $dossier_id = 0; }
if ($dossier_id && !$dossier_courant) { $dossier_id = 0; } // dossier inexistant → racine

// Sous-dossiers du niveau courant (arborescence illimitée)
$dossiers  = ged_dossiers_lister($id, $dossier_id ?: null);
$fil_ariane = $dossier_id ? ged_dossier_fil_ariane($dossier_id) : [];
$documents = ged_documents_lister($id, $depot, $uid, $dossier_id);
$membres   = ged_depot_membres($id);

$users   = ged_liste_users(0);                // tous (on filtrera l'auteur côté affichage si besoin)
$groupes = ged_liste_groupes();

$vis_meta = [
    'PRIVE'   => ['Privé',   'fa-lock',       '#dc2626', '#fee2e2'],
    'PARTAGE' => ['Partagé', 'fa-user-group', '#7c3aed', '#ede9fe'],
    'PUBLIC'  => ['Public',  'fa-globe',      '#059669', '#d1fae5'],
];
[$vis_lbl,$vis_ico,$vis_col,$vis_bg] = $vis_meta[$depot['visibilite']];

$page_titre      = $depot['nom'];
$page_breadcrumb = 'GED · ' . e($depot['nom']);
$module_actif    = 'GED';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.depot-banner{display:flex;align-items:center;gap:16px;background:var(--surface);border:1.5px solid var(--border);
  border-left:6px solid <?= e($depot['couleur']) ?>;border-radius:14px;padding:18px 22px;margin-bottom:22px;}
.depot-banner .ico{width:54px;height:54px;border-radius:13px;display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;background:<?= e($depot['couleur']) ?>22;color:<?= e($depot['couleur']) ?>;flex-shrink:0;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:.69rem;font-weight:700;}

.doc-list{display:flex;flex-direction:column;gap:10px;}
.doc-item{display:flex;align-items:center;gap:14px;background:var(--surface);border:1.5px solid var(--border);
  border-radius:12px;padding:14px 18px;transition:box-shadow .15s;}
.doc-item:hover{box-shadow:0 4px 16px rgba(13,50,120,.08);}
.doc-ico{width:42px;height:42px;border-radius:10px;background:var(--blue-50);color:var(--blue-600);
  display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.doc-meta{flex:1;min-width:0;}
.doc-titre{font-size:.9rem;font-weight:700;}
.doc-sub{font-size:.74rem;color:var(--text-muted);margin-top:2px;}
.doc-actions{display:flex;gap:6px;align-items:center;flex-shrink:0;}

.empty-state{text-align:center;padding:50px 20px;color:var(--text-muted);}
.empty-state i{font-size:2.3rem;opacity:.4;margin-bottom:12px;}

.section-titre{font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:4px 0 10px;}
.fold-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:24px;}
.fold-item{display:flex;align-items:center;gap:12px;background:var(--surface);border:1.5px solid var(--border);
  border-radius:12px;padding:14px 16px;transition:box-shadow .15s,border-color .15s;}
.fold-item:hover{box-shadow:0 4px 16px rgba(13,50,120,.08);border-color:var(--blue-300);}
.fold-link{display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;}
.fold-ico{width:42px;height:42px;border-radius:10px;background:#fff7ed;color:#f59e0b;display:flex;align-items:center;
  justify-content:center;font-size:1.2rem;flex-shrink:0;}
.fold-nom{font-size:.9rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fold-sub{font-size:.72rem;color:var(--text-muted);margin-top:2px;}
.fold-actions{display:flex;gap:4px;flex-shrink:0;}

.user-pick-list{max-height:220px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px;padding:6px;}
.user-pick{display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:8px;}
.user-pick:hover{background:var(--blue-50);}
.user-pick .meta{flex:1;min-width:0;}
.user-pick .meta .n{font-size:.83rem;font-weight:600;}
.user-pick .meta .g{font-size:.7rem;color:var(--text-muted);}
.chk-grp{display:flex;flex-wrap:wrap;gap:8px;}
.chk-grp label{display:flex;align-items:center;gap:6px;padding:7px 12px;border:1.5px solid var(--border);
  border-radius:8px;font-size:.8rem;cursor:pointer;}
.portee-row{display:none;}
</style>

<?php if ($dossier_courant):
  // Lien de retour = niveau parent immédiat
  $parent_url = !empty($dossier_courant['parent_id'])
    ? 'depot.php?id=' . $id . '&dossier=' . (int)$dossier_courant['parent_id']
    : 'depot.php?id=' . $id;
?>
<a href="<?= $parent_url ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:var(--blue-600);text-decoration:none;margin-bottom:14px;">
  <i class="fa-solid fa-arrow-left"></i> Remonter d'un niveau
</a>
<div style="font-size:.78rem;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
  <i class="fa-solid fa-folder-open"></i>
  <a href="depot.php?id=<?= $id ?>" style="color:var(--blue-600);text-decoration:none;"><?= e($depot['nom']) ?></a>
  <?php foreach ($fil_ariane as $k => $anc): $dernier = ($k === count($fil_ariane)-1); ?>
    <i class="fa-solid fa-angle-right" style="font-size:.7rem;opacity:.6;"></i>
    <?php if ($dernier): ?>
      <strong><?= e($anc['nom']) ?></strong>
    <?php else: ?>
      <a href="depot.php?id=<?= $id ?>&dossier=<?= (int)$anc['id'] ?>" style="color:var(--blue-600);text-decoration:none;"><?= e($anc['nom']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php else: ?>
<a href="index.php" style="display:inline-flex;align-items:center;gap:6px;font-size:.8rem;color:var(--blue-600);text-decoration:none;margin-bottom:14px;">
  <i class="fa-solid fa-arrow-left"></i> Retour aux dépôts
</a>
<?php endif; ?>

<!-- Bannière du dépôt -->
<div class="depot-banner">
  <div class="ico"><i class="fa-solid <?= e($depot['icone']) ?>"></i></div>
  <div style="flex:1;min-width:0;">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
      <h2 style="font-size:1.1rem;font-weight:800;"><?= e($depot['nom']) ?></h2>
      <span class="badge" style="background:<?= $vis_bg ?>;color:<?= $vis_col ?>;">
        <i class="fa-solid <?= $vis_ico ?>"></i> <?= $vis_lbl ?>
      </span>
    </div>
    <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px;">
      <?= $depot['description'] ? e($depot['description']) : 'Aucune description' ?>
    </div>
    <div style="font-size:.72rem;color:var(--text-muted);margin-top:6px;">
      <i class="fa-solid fa-user"></i> Propriétaire : <?= e($depot['proprietaire_nom']) ?>
      · <i class="fa-solid fa-file"></i> <?= count($documents) ?> document<?= count($documents)>1?'s':'' ?>
      <?php if ($depot['visibilite']==='PARTAGE'): ?>
        · <i class="fa-solid fa-user-group"></i> <?= count($membres) ?> membre<?= count($membres)>1?'s':'' ?>
      <?php endif; ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-shrink:0;">
    <?php if ($droits['deposer']): ?>
      <button type="button" class="btn btn-secondary" onclick="ouvrir('modalDossier')">
        <i class="fa-solid fa-folder-plus"></i> Nouveau dossier
      </button>
    <?php endif; ?>
    <?php if ($droits['deposer']): ?>
      <button type="button" class="btn btn-primary" onclick="ouvrir('modalUpload')">
        <i class="fa-solid fa-upload"></i> Déposer
      </button>
    <?php endif; ?>
    <?php if ($droits['gerer']): ?>
      <button type="button" class="btn btn-secondary" onclick="ouvrir('modalEditDepot')" title="Paramètres du dépôt">
        <i class="fa-solid fa-gear"></i>
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Sous-dossiers du niveau courant -->
<?php if (!empty($dossiers)): ?>
  <div class="section-titre"><i class="fa-solid fa-folder"></i> Dossiers</div>
  <div class="fold-grid">
  <?php foreach ($dossiers as $fo): ?>
    <div class="fold-item">
      <a class="fold-link" href="depot.php?id=<?= $id ?>&dossier=<?= (int)$fo['id'] ?>">
        <div class="fold-ico"><i class="fa-solid fa-folder"></i></div>
        <div style="min-width:0;">
          <div class="fold-nom"><?= e($fo['nom']) ?></div>
          <div class="fold-sub">
            <?= (int)$fo['nb_documents'] ?> document<?= (int)$fo['nb_documents']>1?'s':'' ?>
            <?php if ((int)($fo['nb_sous_dossiers'] ?? 0) > 0): ?>
              · <?= (int)$fo['nb_sous_dossiers'] ?> dossier<?= (int)$fo['nb_sous_dossiers']>1?'s':'' ?>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php if ($droits['gerer']): ?>
      <div class="fold-actions">
        <button type="button" class="btn btn-secondary btn-sm btn-icon" title="Renommer"
                onclick="ouvrirRenom(<?= (int)$fo['id'] ?>, <?= htmlspecialchars(json_encode($fo['nom']), ENT_QUOTES) ?>)">
          <i class="fa-solid fa-pen"></i>
        </button>
        <form method="POST" action="actions.php" style="display:inline;"
              onsubmit="return confirm('Supprimer le dossier « <?= e($fo['nom']) ?> » et ses sous-dossiers ? Les documents reviendront à la racine.')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="supprimer_dossier">
          <input type="hidden" name="depot_id" value="<?= $id ?>">
          <input type="hidden" name="dossier_id" value="<?= (int)$fo['id'] ?>">
          <button type="submit" class="btn btn-sm btn-icon" style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;" title="Supprimer">
            <i class="fa-solid fa-trash"></i>
          </button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Liste des documents -->
<?php if (!empty($documents) && !empty($dossiers)): ?>
  <div class="section-titre"><i class="fa-solid fa-file"></i> Documents</div>
<?php endif; ?>
<?php if (empty($documents) && empty($dossiers)): ?>
  <div class="card"><div class="empty-state">
    <i class="fa-solid fa-file-circle-plus"></i>
    <?php if ($dossier_courant): ?>
      <p style="font-weight:600;color:var(--text);">Ce dossier est vide</p>
      <?php if ($droits['deposer']): ?>
        <p style="font-size:.82rem;">Déposez un document ou créez un sous-dossier.</p>
      <?php endif; ?>
    <?php else: ?>
      <p style="font-weight:600;color:var(--text);">Ce dépôt est vide</p>
      <?php if ($droits['deposer']): ?>
        <p style="font-size:.82rem;">Déposez un premier document ou créez un dossier.</p>
      <?php else: ?>
        <p style="font-size:.82rem;">Aucun document accessible pour le moment.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div></div>
<?php elseif (!empty($documents)): ?>
  <div class="doc-list">
  <?php foreach ($documents as $doc):
      $restreint = $doc['portee'] === 'RESTREINT';
      $peut_gerer_doc = $droits['gerer'] || (int)($doc['depose_par'] ?? 0) === $uid;
  ?>
    <div class="doc-item">
      <div class="doc-ico"><i class="fa-solid <?= ged_icone_fichier($doc['extension']) ?>"></i></div>
      <div class="doc-meta">
        <div class="doc-titre"><?= e($doc['titre']) ?></div>
        <div class="doc-sub">
          <?= strtoupper(e($doc['extension'])) ?> · <?= _ged_taille_lisible((int)$doc['taille']) ?>
          · déposé par <?= e($doc['deposant'] ?? '—') ?> le <?= format_datetime($doc['cree_le']) ?>
          <?php if ($restreint): ?>
            · <span class="badge" style="background:#fef3c7;color:#92400e;">
                <i class="fa-solid fa-user-lock"></i> Restreint
                (<?= (int)$doc['nb_users_autorises'] ?> utilisateur<?= $doc['nb_users_autorises']>1?'s':'' ?>,
                 <?= (int)$doc['nb_groupes_autorises'] ?> groupe<?= $doc['nb_groupes_autorises']>1?'s':'' ?>)
              </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="doc-actions">
        <a href="download.php?id=<?= (int)$doc['id'] ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon" title="Aperçu">
          <i class="fa-solid fa-eye"></i>
        </a>
        <a href="download.php?id=<?= (int)$doc['id'] ?>&dl=1" class="btn btn-secondary btn-sm btn-icon" title="Télécharger">
          <i class="fa-solid fa-download"></i>
        </a>
        <?php if ($peut_gerer_doc): ?>
          <button type="button" class="btn btn-secondary btn-sm btn-icon" title="Partage / accès"
                  onclick='ouvrirPartage(<?= json_encode([
                      "id"=>(int)$doc["id"], "titre"=>$doc["titre"], "portee"=>$doc["portee"],
                      "users"=>ged_document_users_autorises((int)$doc["id"]),
                      "groupes"=>ged_document_groupes_autorises((int)$doc["id"]),
                  ], JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
            <i class="fa-solid fa-user-lock"></i>
          </button>
          <form method="POST" action="actions.php" style="display:inline;"
                onsubmit="return confirm('Supprimer ce document ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="supprimer_document">
            <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
            <input type="hidden" name="depot_id" value="<?= $id ?>">
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

<!-- ============ MODAL : UPLOAD ============ -->
<?php if ($droits['deposer']): ?>
<div class="modal-overlay" id="modalUpload">
  <div class="modal">
    <form method="POST" action="actions.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="deposer_document">
      <input type="hidden" name="depot_id" value="<?= $id ?>">
      <input type="hidden" name="dossier_id" value="<?= $dossier_id ?>">
      <div class="modal-header">
        <h3><i class="fa-solid fa-upload"></i> Déposer un document<?= $dossier_courant ? ' dans « ' . e($dossier_courant['nom']) . ' »' : '' ?></h3>
        <button type="button" class="modal-close" onclick="fermer('modalUpload')"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Fichier *</label>
          <input type="file" name="fichier" class="form-control" required>
          <small style="color:var(--text-muted);font-size:.72rem;">Max 20 Mo · PDF, Office, images, archives, texte.</small>
        </div>
        <div class="form-group">
          <label class="form-label">Titre (optionnel)</label>
          <input type="text" name="titre" class="form-control" maxlength="255"
                 placeholder="Par défaut : nom du fichier">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Accès au document</label>
          <select name="portee" class="form-control" onchange="togglePortee(this.value,'up')">
            <option value="HERITE">Suivre la visibilité du dépôt (<?= $vis_lbl ?>)</option>
            <option value="RESTREINT">Restreindre à certains utilisateurs / groupes</option>
          </select>
        </div>
        <div class="portee-row" id="porteeUp">
          <div class="form-group">
            <label class="form-label">Utilisateurs autorisés</label>
            <div class="user-pick-list">
              <?php foreach ($users as $u): ?>
                <label class="user-pick">
                  <input type="checkbox" name="users[]" value="<?= (int)$u['id'] ?>">
                  <div class="meta">
                    <div class="n"><?= e($u['nom_complet']) ?></div>
                    <div class="g"><?= e($u['groupe']) ?> · <?= e($u['matricule']) ?></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Groupes autorisés</label>
            <div class="chk-grp">
              <?php foreach ($groupes as $g): ?>
                <label><input type="checkbox" name="groupes[]" value="<?= (int)$g['id'] ?>"> <?= e($g['libelle']) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermer('modalUpload')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Déposer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ============ MODAL : NOUVEAU DOSSIER ============ -->
<?php if ($droits['deposer']): ?>
<div class="modal-overlay" id="modalDossier">
  <div class="modal">
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="creer_dossier">
      <input type="hidden" name="depot_id" value="<?= $id ?>">
      <input type="hidden" name="parent_id" value="<?= $dossier_id ?>">
      <div class="modal-header">
        <h3><i class="fa-solid fa-folder-plus"></i> Nouveau dossier</h3>
        <button type="button" class="modal-close" onclick="fermer('modalDossier')"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom du dossier *</label>
          <input type="text" name="nom" class="form-control" required maxlength="150" placeholder="Ex : 2025, Trimestre 1, Archives…" autofocus>
          <small style="color:var(--text-muted);font-size:.72rem;">
            Il sera créé dans « <?= e($dossier_courant ? $dossier_courant['nom'] : $depot['nom']) ?> ».
          </small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermer('modalDossier')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Créer le dossier</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ============ MODAL : RENOMMER UN DOSSIER ============ -->
<?php if ($droits['gerer']): ?>
<div class="modal-overlay" id="modalRenom">
  <div class="modal">
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="renommer_dossier">
      <input type="hidden" name="depot_id" value="<?= $id ?>">
      <input type="hidden" name="dossier_id" id="rn_dossier_id">
      <div class="modal-header">
        <h3><i class="fa-solid fa-pen"></i> Renommer le dossier</h3>
        <button type="button" class="modal-close" onclick="fermer('modalRenom')"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nouveau nom *</label>
          <input type="text" name="nom" id="rn_nom" class="form-control" required maxlength="150">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermer('modalRenom')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ============ MODAL : PARTAGE D'UN DOCUMENT ============ -->
<div class="modal-overlay" id="modalPartage">
  <div class="modal">
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="modifier_acces_document">
      <input type="hidden" name="document_id" id="pg_doc_id">
      <input type="hidden" name="depot_id" value="<?= $id ?>">
      <div class="modal-header">
        <h3><i class="fa-solid fa-user-lock"></i> Partage : <span id="pg_titre"></span></h3>
        <button type="button" class="modal-close" onclick="fermer('modalPartage')"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Mode d'accès</label>
          <select name="portee" id="pg_portee" class="form-control" onchange="togglePortee(this.value,'pg')">
            <option value="HERITE">Suivre la visibilité du dépôt (<?= $vis_lbl ?>)</option>
            <option value="RESTREINT">Restreindre à certains utilisateurs / groupes</option>
          </select>
        </div>
        <div class="portee-row" id="porteePg">
          <div class="form-group">
            <label class="form-label">Utilisateurs autorisés</label>
            <div class="user-pick-list" id="pg_users">
              <?php foreach ($users as $u): ?>
                <label class="user-pick">
                  <input type="checkbox" name="users[]" value="<?= (int)$u['id'] ?>" data-uid="<?= (int)$u['id'] ?>">
                  <div class="meta">
                    <div class="n"><?= e($u['nom_complet']) ?></div>
                    <div class="g"><?= e($u['groupe']) ?> · <?= e($u['matricule']) ?></div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Groupes autorisés</label>
            <div class="chk-grp" id="pg_groupes">
              <?php foreach ($groupes as $g): ?>
                <label><input type="checkbox" name="groupes[]" value="<?= (int)$g['id'] ?>" data-gid="<?= (int)$g['id'] ?>"> <?= e($g['libelle']) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermer('modalPartage')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL : PARAMÈTRES DU DÉPÔT ============ -->
<?php if ($droits['gerer']):
  $membres_ids = array_column($membres, 'utilisateur_id');
  $membre_roles = [];
  foreach ($membres as $m) { $membre_roles[(int)$m['utilisateur_id']] = $m['role']; }
?>
<div class="modal-overlay" id="modalEditDepot">
  <div class="modal">
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="modifier_depot">
      <input type="hidden" name="depot_id" value="<?= $id ?>">
      <div class="modal-header">
        <h3><i class="fa-solid fa-gear"></i> Paramètres du dépôt</h3>
        <button type="button" class="modal-close" onclick="fermer('modalEditDepot')"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" required maxlength="150" value="<?= e($depot['nom']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"><?= e($depot['description'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Couleur</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php foreach (GED_COULEURS as $col): $sel = $col===$depot['couleur']; ?>
                <div class="swatch <?= $sel?'sel':'' ?>" style="background:<?= $col ?>;border-radius:8px;width:30px;height:30px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:#fff;border:3px solid <?= $sel?'var(--text)':'transparent' ?>;"
                     data-col="<?= $col ?>" onclick="choisirCouleurEd(this)">
                  <i class="fa-solid fa-check" style="font-size:.7rem;<?= $sel?'':'opacity:0;' ?>"></i>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="couleur" id="couleurInputEd" value="<?= e($depot['couleur']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Icône</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php foreach (GED_ICONES as $ic): $sel=$ic===$depot['icone']; ?>
                <div class="icon-pick <?= $sel?'sel':'' ?>" style="width:38px;height:38px;border-radius:9px;border:1.5px solid <?= $sel?'var(--blue-500)':'var(--border)' ?>;background:<?= $sel?'var(--blue-50)':'var(--bg)' ?>;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;color:<?= $sel?'var(--blue-600)':'var(--text-muted)' ?>;"
                     data-ic="<?= $ic ?>" onclick="choisirIconeEd(this)">
                  <i class="fa-solid <?= $ic ?>"></i>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="icone" id="iconeInputEd" value="<?= e($depot['icone']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Visibilité *</label>
          <select name="visibilite" class="form-control" id="visEd" onchange="toggleMembresEd()">
            <option value="PRIVE"   <?= $depot['visibilite']==='PRIVE'?'selected':'' ?>>🔒 Privé</option>
            <option value="PARTAGE" <?= $depot['visibilite']==='PARTAGE'?'selected':'' ?>>👥 Partagé</option>
            <option value="PUBLIC"  <?= $depot['visibilite']==='PUBLIC'?'selected':'' ?>>🌍 Public</option>
          </select>
        </div>
        <div class="form-group" id="blocMembresEd" style="display:<?= $depot['visibilite']==='PARTAGE'?'block':'none' ?>;">
          <label class="form-label">Utilisateurs autorisés</label>
          <div class="user-pick-list">
            <?php foreach ($users as $u):
              $checked = in_array((int)$u['id'], array_map('intval',$membres_ids), true);
              $role = $membre_roles[(int)$u['id']] ?? 'LECTEUR';
            ?>
              <label class="user-pick">
                <input type="checkbox" name="membres[]" value="<?= (int)$u['id'] ?>" <?= $checked?'checked':'' ?>
                       onchange="toggleRoleEd(this,<?= (int)$u['id'] ?>)">
                <div class="meta">
                  <div class="n"><?= e($u['nom_complet']) ?></div>
                  <div class="g"><?= e($u['groupe']) ?></div>
                </div>
                <select name="roles[<?= (int)$u['id'] ?>]" class="form-control" style="width:auto;padding:4px 8px;font-size:.76rem;" <?= $checked?'':'disabled' ?>>
                  <option value="LECTEUR"      <?= $role==='LECTEUR'?'selected':'' ?>>Lecture</option>
                  <option value="CONTRIBUTEUR" <?= $role==='CONTRIBUTEUR'?'selected':'' ?>>Contribution</option>
                </select>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermer('modalEditDepot')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function ouvrir(id){ document.getElementById(id).classList.add('open'); }
function fermer(id){ document.getElementById(id).classList.remove('open'); }

function ouvrirRenom(id, nom){
  document.getElementById('rn_dossier_id').value = id;
  document.getElementById('rn_nom').value = nom;
  ouvrir('modalRenom');
}

function togglePortee(val, suffix){
  document.getElementById('portee'+(suffix==='up'?'Up':'Pg')).style.display = (val==='RESTREINT')?'block':'none';
}

// Partage d'un document : pré-remplir
function ouvrirPartage(data){
  document.getElementById('pg_doc_id').value = data.id;
  document.getElementById('pg_titre').textContent = data.titre;
  document.getElementById('pg_portee').value = data.portee;
  togglePortee(data.portee, 'pg');

  document.querySelectorAll('#pg_users input[type=checkbox]').forEach(cb=>{
    cb.checked = data.users.includes(parseInt(cb.dataset.uid));
  });
  document.querySelectorAll('#pg_groupes input[type=checkbox]').forEach(cb=>{
    cb.checked = data.groupes.includes(parseInt(cb.dataset.gid));
  });
  ouvrir('modalPartage');
}

<?php if ($droits['gerer']): ?>
function choisirCouleurEd(el){
  document.querySelectorAll('#modalEditDepot .swatch').forEach(s=>{s.style.borderColor='transparent';s.querySelector('i').style.opacity='0';});
  el.style.borderColor='var(--text)'; el.querySelector('i').style.opacity='1';
  document.getElementById('couleurInputEd').value = el.dataset.col;
}
function choisirIconeEd(el){
  document.querySelectorAll('#modalEditDepot .icon-pick').forEach(s=>{s.style.borderColor='var(--border)';s.style.background='var(--bg)';s.style.color='var(--text-muted)';});
  el.style.borderColor='var(--blue-500)'; el.style.background='var(--blue-50)'; el.style.color='var(--blue-600)';
  document.getElementById('iconeInputEd').value = el.dataset.ic;
}
function toggleMembresEd(){
  document.getElementById('blocMembresEd').style.display = (document.getElementById('visEd').value==='PARTAGE')?'block':'none';
}
function toggleRoleEd(cb,id){
  const sel=document.querySelector('#modalEditDepot select[name="roles['+id+']"]');
  if(sel) sel.disabled=!cb.checked;
}
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
