<?php
// modules/courriers/voir.php — Fiche détaillée d'un courrier
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/courrier.php';

session_demarrer();
exiger_permission('COURRIERS', 'VOIR');

$cid  = (int)($_GET['id'] ?? 0);
$cour = $cid ? courrier_charger($cid) : null;
if (!$cour) { set_flash('error', 'Courrier introuvable.'); redirect('/modules/courriers/index.php'); }

$peut_modifier  = a_permission('COURRIERS', 'MODIFIER');
$peut_valider   = a_permission('COURRIERS', 'VALIDER');
$peut_supprimer = a_permission('COURRIERS', 'SUPPRIMER');

$STATUTS = courrier_statuts();
$PRIOS   = courrier_priorites();
$CANAUX  = courrier_canaux();
$TYPES   = courrier_types();
$CONFS   = courrier_confidentialites();

$st = $STATUTS[$cour['statut']]          ?? $STATUTS['RECU'];
$pr = $PRIOS[$cour['priorite']]          ?? $PRIOS['NORMALE'];
$cn = $CANAUX[$cour['canal']]            ?? $CANAUX['AUTRE'];
$cf = $CONFS[$cour['confidentialite']]   ?? $CONFS['NORMAL'];
$entrant   = $cour['sens'] === 'ENTRANT';
$en_retard = !empty($cour['date_echeance']) && $cour['date_echeance'] < date('Y-m-d')
             && !in_array($cour['statut'], ['TRAITE','CLOTURE','ARCHIVE'], true);

$services     = $peut_modifier ? courrier_services()     : [];
$utilisateurs = $peut_modifier ? courrier_utilisateurs() : [];

$doc_types = [
    'SCAN'         => ['Scan du courrier', 'fa-file-image',  '#dbeafe', '#1d4ed8'],
    'PIECE_JOINTE' => ['Pièce jointe',     'fa-paperclip',   '#fef3c7', '#92400e'],
    'REPONSE'      => ['Réponse',          'fa-reply',       '#dcfce7', '#166534'],
    'AUTRE'        => ['Autre',            'fa-file',        '#f3f4f6', '#6b7280'],
];
$docs_par_type = [];
foreach ($cour['documents'] as $d) { $docs_par_type[$d['type_document']][] = $d; }

$page_titre      = 'Courrier ' . e($cour['reference']);
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Courriers</a> · ' . e($cour['reference']);
$module_actif    = 'COURRIERS';
include __DIR__ . '/../../views/layout/header.php';

$val = fn($v) => ($v !== null && $v !== '') ? e((string)$v) : '<span style="color:var(--text-muted);font-weight:400;">—</span>';
?>
<style>
.ch{background:var(--surface);border:1.5px solid var(--border);border-left:5px solid <?= $st['bord'] ?>;border-radius:14px;padding:20px 24px;margin-bottom:20px;}
.ch-top{display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;}
.ch-ico{width:56px;height:56px;border-radius:12px;background:<?= $entrant ? '#dcfce7' : '#dbeafe' ?>;color:<?= $entrant ? '#166534' : '#1d4ed8' ?>;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.ch-ref{font-family:var(--mono);font-size:.82rem;font-weight:700;color:var(--blue-600);}
.ch-titre{font-size:1.1rem;font-weight:800;color:var(--text);margin:3px 0 5px;}
.ch-sub{font-size:.78rem;color:var(--text-muted);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;}

.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-top:16px;}
.info-block{background:var(--bg);border-radius:9px;padding:11px 13px;}
.ib-lbl{font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;}
.ib-val{font-size:.85rem;font-weight:600;color:var(--text);word-break:break-word;}

.txt-bloc{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:16px;}
.txt-bloc h4{font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin:0 0 8px;}
.txt-bloc div{font-size:.86rem;line-height:1.6;color:var(--text);}
.instr-bloc{background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 18px;margin-bottom:16px;}
.instr-bloc h4{font-size:.75rem;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px;}
.instr-bloc div{font-size:.86rem;line-height:1.6;color:#7c5e10;}

.act-panel{background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.act-panel .lbl{font-size:.84rem;font-weight:600;}

.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:9px;}
.doc-card{background:var(--surface);border:1.5px solid var(--border);border-radius:10px;padding:11px 13px;display:flex;gap:9px;align-items:center;transition:box-shadow .14s;}
.doc-card:hover{box-shadow:0 3px 12px rgba(13,50,120,.08);}
.doc-ico{width:34px;height:34px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}

.tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:18px;border-bottom:2px solid var(--border);}
.tab{padding:9px 17px;border-radius:8px 8px 0 0;font-size:.82rem;font-weight:600;cursor:pointer;background:none;border:1.5px solid transparent;border-bottom:none;color:var(--text-muted);transition:all .14s;}
.tab:hover{color:var(--blue-600);background:var(--blue-50);}
.tab.on{color:var(--blue-600);background:var(--surface);border-color:var(--border);border-bottom:2px solid var(--surface);margin-bottom:-2px;}
.tp{display:none;}.tp.on{display:block;}

.suivi-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
.suivi-item:last-child{border-bottom:none;}
.suivi-dot{width:30px;height:30px;border-radius:50%;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.75rem;flex-shrink:0;}
</style>

<!-- Barre d'actions -->
<div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour au registre</a>
  <?php if ($peut_modifier): ?>
    <a href="form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Modifier</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="ouvrirModal('modalAffecter')">
      <i class="fa-solid fa-share-from-square"></i> Affecter
    </button>
  <?php endif; ?>
  <?php if ($peut_supprimer): ?>
    <button type="button" class="btn btn-sm"
            style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;"
            onclick="confirmerSuppr('<?= e(addslashes($cour['reference'])) ?>')">
      <i class="fa-solid fa-trash"></i> Supprimer
    </button>
  <?php endif; ?>
</div>

<!-- En-tête du courrier -->
<div class="ch">
  <div class="ch-top">
    <div class="ch-ico"><i class="fa-solid <?= $entrant ? 'fa-arrow-down-long' : 'fa-arrow-up-long' ?>"></i></div>
    <div style="flex:1;min-width:0;">
      <div class="ch-ref"><?= e($cour['reference']) ?></div>
      <div class="ch-titre"><?= e($cour['objet']) ?></div>
      <div class="ch-sub">
        Courrier <?= $entrant ? 'entrant' : 'sortant' ?>
        · <?= e($TYPES[$cour['type_courrier']] ?? $cour['type_courrier']) ?>
        · <i class="fa-solid <?= $cn['icon'] ?>"></i> <?= e($cn['label']) ?>
      </div>
      <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
        <span class="badge" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;">
          <i class="fa-solid <?= $st['icon'] ?>"></i> <?= $st['label'] ?>
        </span>
        <span class="badge" style="background:<?= $pr['bg'] ?>;color:<?= $pr['color'] ?>;">Priorité : <?= $pr['label'] ?></span>
        <?php if ($cour['confidentialite'] !== 'NORMAL'): ?>
          <span class="badge" style="background:<?= $cf['bg'] ?>;color:<?= $cf['color'] ?>;">
            <i class="fa-solid <?= $cf['icon'] ?>"></i> <?= $cf['label'] ?>
          </span>
        <?php endif; ?>
        <?php if ($en_retard): ?>
          <span class="badge" style="background:#fee2e2;color:#991b1b;"><i class="fa-solid fa-clock"></i> Échéance dépassée</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <div style="font-size:.68rem;color:var(--text-muted);margin-bottom:3px;">Enregistré le</div>
      <div style="font-size:.85rem;font-weight:700;color:var(--blue-700);"><?= format_datetime($cour['cree_le']) ?></div>
      <?php if (!empty($cour['enregistreur_nom'])): ?>
        <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">par <?= e($cour['enregistreur_nom']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-user-tie"></i> <?= $entrant ? 'Expéditeur' : 'Destinataire' ?></div>
      <div class="ib-val"><?= $val($cour['correspondant_nom']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-building"></i> Organisation</div>
      <div class="ib-val"><?= $val($cour['correspondant_organisation']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-regular fa-envelope"></i> Email</div>
      <div class="ib-val">
        <?php if (!empty($cour['correspondant_email'])): ?>
          <a href="mailto:<?= e($cour['correspondant_email']) ?>" style="color:var(--blue-600);text-decoration:none;"><?= e($cour['correspondant_email']) ?></a>
        <?php else: ?><?= $val(null) ?><?php endif; ?>
      </div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-phone"></i> Téléphone</div>
      <div class="ib-val"><?= $val($cour['correspondant_telephone']) ?></div>
    </div>
    <?php if (!empty($cour['correspondant_adresse'])): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-location-dot"></i> Adresse</div>
      <div class="ib-val"><?= e($cour['correspondant_adresse']) ?></div>
    </div>
    <?php endif; ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-regular fa-calendar"></i> Date du courrier</div>
      <div class="ib-val"><?= !empty($cour['date_courrier']) ? format_date($cour['date_courrier']) : $val(null) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-inbox"></i> Date de réception</div>
      <div class="ib-val"><?= !empty($cour['date_reception']) ? format_datetime($cour['date_reception']) : $val(null) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-hourglass-half"></i> Échéance</div>
      <div class="ib-val" <?= $en_retard ? 'style="color:var(--danger);"' : '' ?>>
        <?= !empty($cour['date_echeance']) ? format_date($cour['date_echeance']) : $val(null) ?>
      </div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-sitemap"></i> Service concerné</div>
      <div class="ib-val"><?= $val($cour['service_nom']) ?></div>
    </div>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-user"></i> Affecté à</div>
      <div class="ib-val"><?= $val($cour['affecte_nom']) ?></div>
    </div>
    <?php if (!empty($cour['traiteur_nom'])): ?>
    <div class="info-block">
      <div class="ib-lbl"><i class="fa-solid fa-circle-check"></i> Traité par</div>
      <div class="ib-val">
        <?= e($cour['traiteur_nom']) ?>
        <?php if (!empty($cour['date_traitement'])): ?>
          <div style="font-size:.7rem;color:var(--text-muted);font-weight:400;">le <?= format_datetime($cour['date_traitement']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (trim((string)$cour['contenu']) !== ''): ?>
<div class="txt-bloc">
  <h4><i class="fa-solid fa-file-lines"></i> Contenu / résumé</h4>
  <div><?= nl2br(e($cour['contenu'])) ?></div>
</div>
<?php endif; ?>

<?php if (trim((string)$cour['instruction']) !== ''): ?>
<div class="instr-bloc">
  <h4><i class="fa-solid fa-pen-nib"></i> Instruction / annotation</h4>
  <div><?= nl2br(e($cour['instruction'])) ?></div>
</div>
<?php endif; ?>

<!-- Changement de statut -->
<?php if ($peut_valider): ?>
<form method="POST" action="actions.php" class="act-panel">
  <?= csrf_field() ?>
  <input type="hidden" name="action"      value="statut">
  <input type="hidden" name="courrier_id" value="<?= $cid ?>">
  <span class="lbl"><i class="fa-solid fa-arrows-rotate" style="color:var(--blue-500);margin-right:6px;"></i> Changer le statut</span>
  <select name="statut" class="form-control" style="width:auto;min-width:170px;" required>
    <?php foreach ($STATUTS as $k => $s): if ($k === $cour['statut']) continue; ?>
      <option value="<?= $k ?>"><?= $s['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="commentaire" class="form-control" style="flex:1;min-width:200px;" maxlength="500"
         placeholder="Commentaire (facultatif)">
  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Appliquer</button>
</form>
<?php endif; ?>

<!-- Onglets -->
<div class="tabs">
  <button class="tab on" onclick="sw('docs',this)">
    <i class="fa-solid fa-paperclip"></i> Documents (<?= count($cour['documents']) ?>)
  </button>
  <button class="tab" onclick="sw('suivi',this)">
    <i class="fa-solid fa-clock-rotate-left"></i> Suivi (<?= count($cour['suivi']) ?>)
  </button>
</div>

<!-- Documents -->
<div class="tp on" id="tp-docs">
  <?php if (empty($cour['documents'])): ?>
    <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);color:var(--text-muted);">
      <i class="fa-solid fa-folder-open" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
      Aucun document joint.
      <?php if ($peut_modifier): ?>
        <div style="margin-top:10px;"><a href="form.php?id=<?= $cid ?>">Ajouter des documents</a></div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php foreach ($doc_types as $type => [$lbl, $ico, $bg, $co]):
      if (empty($docs_par_type[$type])) continue;
    ?>
    <div style="margin-bottom:18px;">
      <div style="font-size:.73rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">
        <i class="fa-solid <?= $ico ?>" style="color:<?= $co ?>;"></i> <?= $lbl ?>
      </div>
      <div class="doc-grid">
        <?php foreach ($docs_par_type[$type] as $doc):
          $ext = strtolower($doc['extension'] ?? '');
          $d_ico = in_array($ext, ['doc','docx'], true)                         ? 'fa-file-word'
                 : (in_array($ext, ['xls','xlsx'], true)                        ? 'fa-file-excel'
                 : (in_array($ext, ['jpg','jpeg','png','webp','tif','tiff'], true) ? 'fa-image' : 'fa-file-pdf'));
          $apercu = in_array($ext, ['pdf','jpg','jpeg','png','webp'], true);
        ?>
        <div class="doc-card">
          <div class="doc-ico" style="background:<?= $bg ?>;color:<?= $co ?>;"><i class="fa-solid <?= $d_ico ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.81rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                 title="<?= e($doc['nom_original']) ?>"><?= e($doc['nom_original']) ?></div>
            <div style="font-size:.68rem;color:var(--text-muted);">
              <?= e($doc['deposant'] ?? '') ?>
              <?= $doc['taille_fichier'] ? ' · ' . round($doc['taille_fichier'] / 1024, 1) . ' Ko' : '' ?>
              · <?= format_datetime($doc['date_upload']) ?>
            </div>
          </div>
          <?php if ($apercu): ?>
            <a href="download.php?id=<?= $doc['id'] ?>" target="_blank" rel="noopener"
               class="btn btn-secondary btn-sm btn-icon" title="Voir">
              <i class="fa-solid fa-eye"></i>
            </a>
          <?php endif; ?>
          <a href="download.php?id=<?= $doc['id'] ?>&amp;dl=1"
             class="btn btn-secondary btn-sm btn-icon" title="Télécharger">
            <i class="fa-solid fa-download"></i>
          </a>
          <?php if ($peut_modifier): ?>
          <form method="POST" action="actions.php" style="display:inline;"
                onsubmit="return confirm('Supprimer ce document ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action"      value="supprimer_doc">
            <input type="hidden" name="doc_id"      value="<?= $doc['id'] ?>">
            <input type="hidden" name="courrier_id" value="<?= $cid ?>">
            <button type="submit" title="Supprimer"
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
      <a href="form.php?id=<?= $cid ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-plus"></i> Ajouter des documents
      </a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Suivi -->
<div class="tp" id="tp-suivi">
  <div class="card">
    <div class="card-body" style="padding:6px 20px;">
      <?php if (empty($cour['suivi'])): ?>
        <div style="padding:24px 0;text-align:center;color:var(--text-muted);font-size:.85rem;">Aucun événement enregistré.</div>
      <?php else: foreach ($cour['suivi'] as $s): ?>
        <div class="suivi-item">
          <div class="suivi-dot"><i class="fa-solid fa-circle-dot"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.82rem;font-weight:700;color:var(--text);">
              <?= e(ucfirst(strtolower(str_replace('_', ' ', $s['action'])))) ?>
              <?php if (!empty($s['nouveau_statut']) && $s['nouveau_statut'] !== $s['ancien_statut']): ?>
                <span style="font-weight:500;color:var(--text-muted);">
                  → <?= e($STATUTS[$s['nouveau_statut']]['label'] ?? $s['nouveau_statut']) ?>
                </span>
              <?php endif; ?>
            </div>
            <?php if (!empty($s['commentaire'])): ?>
              <div style="font-size:.8rem;color:var(--text);margin-top:2px;"><?= nl2br(e($s['commentaire'])) ?></div>
            <?php endif; ?>
            <div style="font-size:.7rem;color:var(--text-muted);margin-top:3px;">
              <?= format_datetime($s['cree_le']) ?><?= !empty($s['auteur']) ? ' · ' . e($s['auteur']) : '' ?>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<?php if ($peut_modifier): ?>
<!-- Modal affectation -->
<div class="modal-overlay" id="modalAffecter">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <i class="fa-solid fa-share-from-square" style="color:var(--blue-500);"></i>
      <h3>Affecter le courrier</h3>
      <button class="modal-close" type="button" onclick="fermerModal('modalAffecter')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action"      value="affecter">
      <input type="hidden" name="courrier_id" value="<?= $cid ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Service concerné</label>
          <select name="service_id" class="form-control">
            <option value="">— Aucun —</option>
            <?php foreach ($services as $sv): ?>
              <option value="<?= $sv['id'] ?>" <?= (string)($cour['service_id'] ?? '') === (string)$sv['id'] ? 'selected' : '' ?>><?= e($sv['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Affecter à (agent)</label>
          <select name="affecte_a" class="form-control">
            <option value="">— Non affecté —</option>
            <?php foreach ($utilisateurs as $u): ?>
              <option value="<?= $u['id'] ?>" <?= (string)($cour['affecte_a'] ?? '') === (string)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['nom_complet']) ?> — <?= e($u['groupe']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Instruction / annotation</label>
          <textarea name="instruction" class="form-control" rows="3"
                    placeholder="Consigne de traitement…"><?= e($cour['instruction'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="fermerModal('modalAffecter')">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Enregistrer l'affectation</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Form actions caché -->
<form method="POST" action="actions.php" id="formAct" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action"      id="faAct">
  <input type="hidden" name="courrier_id" value="<?= $cid ?>">
</form>

<?php
$extra_scripts = '<script>
function sw(id,btn){
  document.querySelectorAll(".tp").forEach(p=>p.classList.remove("on"));
  document.querySelectorAll(".tab").forEach(b=>b.classList.remove("on"));
  document.getElementById("tp-"+id).classList.add("on");
  btn.classList.add("on");
}
function ouvrirModal(id){ document.getElementById(id).classList.add("open"); }
function fermerModal(id){ document.getElementById(id).classList.remove("open"); }
function confirmerSuppr(ref){
  if(!confirm("Supprimer définitivement le courrier \""+ref+"\" et toutes ses pièces ?")) return;
  document.getElementById("faAct").value = "supprimer";
  document.getElementById("formAct").submit();
}
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
