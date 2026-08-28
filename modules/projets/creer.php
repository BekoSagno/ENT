<?php
// modules/projets/creer.php — Créer ou modifier un projet
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet.php';

session_demarrer();
exiger_permission('PROJETS', 'CREER');

$user      = user_courant();
$projet_id = (int)($_GET['id'] ?? 0);
$projet    = $projet_id ? projet_charger($projet_id) : null;
$mode      = $projet ? 'modifier' : 'creer';

if ($projet && !_projet_est_chef($projet_id, (int)$user['id'])) {
    set_flash('error', 'Accès refusé.'); redirect('/modules/projets/index.php');
}

// ── Traitement POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $data = [
        'titre'          => clean($_POST['titre']          ?? ''),
        'description'    => clean($_POST['description']    ?? ''),
        'tdr'            => $_POST['tdr']            ?? '',
        'cahier_charges' => $_POST['cahier_charges'] ?? '',
        'type_projet'    => clean($_POST['type_projet']    ?? 'AUTRE'),
        'budget'         => clean($_POST['budget']         ?? ''),
        'devise'         => clean($_POST['devise']         ?? 'GNF'),
        'date_debut'     => clean($_POST['date_debut']     ?? ''),
        'date_fin_prevue'=> clean($_POST['date_fin_prevue']?? ''),
        'statut'         => clean($_POST['statut']         ?? 'PLANIFIE'),
        'priorite'       => clean($_POST['priorite']       ?? 'NORMALE'),
        'responsable_id' => (int)($_POST['responsable_id'] ?? 0),
        'membres'        => array_map('intval', (array)($_POST['membres'] ?? [])),
    ];

    $res = $mode === 'creer' ? projet_creer($data) : projet_modifier($projet_id, $data);

    if ($res['succes']) {
        $pid = $mode === 'creer' ? $res['projet_id'] : $projet_id;
        // Upload documents
        $cat_map = ['doc_tdr'=>'TDR','doc_cdc'=>'CAHIER_CHARGES','doc_budget'=>'BUDGET','doc_autre'=>'AUTRE'];
        foreach ($cat_map as $champ => $cat) {
            if (!empty($_FILES[$champ]['name']) && $_FILES[$champ]['error']===UPLOAD_ERR_OK)
                projet_doc_ajouter($pid, $_FILES[$champ], $cat, clean($_POST['lib_'.$champ] ?? ''));
        }
        set_flash('success', $mode==='creer' ? 'Projet '.$res['code'].' créé.' : 'Projet modifié.');
        header('Location: voir.php?id='.$pid); exit;
    }
    set_flash('error', $res['message'] ?? 'Erreur.');
}

$all_users    = projet_liste_users();
$membres_ids  = $projet ? array_column($projet['membres']??[], 'utilisateur_id') : [];
$resp_id      = (int)($projet['responsable_id'] ?? $user['id']);

$page_titre      = $mode === 'creer' ? 'Nouveau projet' : 'Modifier le projet';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Projets</a> · '.($mode==='creer'?'Création':e($projet['titre']??''));
$module_actif    = 'PROJETS';
$projet_sous_actif = 'TACHES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.fsec{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;}
.fsec-hd{display:flex;align-items:center;gap:10px;padding:13px 20px;background:var(--bg);border-bottom:1px solid var(--border);}
.fsec-ico{width:32px;height:32px;border-radius:8px;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.fsec-hd h3{font-size:.88rem;font-weight:700;margin:0;}
.fsec-hd .sub{font-size:.71rem;color:var(--text-muted);}
.fsec-bd{padding:20px 22px;}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
@media(max-width:640px){.form-grid-2,.form-grid-3{grid-template-columns:1fr;}}

/* Membres picker */
.mbr-picker{display:flex;flex-direction:column;gap:8px;}
.mbr-search{position:relative;}
.mbr-search i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.78rem;}
.mbr-search input{padding-left:32px;}
.mbr-list{max-height:240px;overflow-y:auto;border:1.5px solid var(--border);border-radius:10px;background:var(--bg);}
.mbr-item{display:flex;align-items:center;gap:9px;padding:8px 12px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s;}
.mbr-item:last-child{border-bottom:none;}
.mbr-item:hover,.mbr-item.sel{background:var(--blue-50);}
.mbr-item input[type=checkbox]{accent-color:var(--blue-600);width:14px;height:14px;flex-shrink:0;}
.mbr-av{width:28px;height:28px;border-radius:50%;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-size:.63rem;font-weight:700;flex-shrink:0;}
.mbr-info{flex:1;min-width:0;}
.mbr-nom{font-size:.81rem;font-weight:600;}
.mbr-sub{font-size:.7rem;color:var(--text-muted);}

/* Upload docs */
.doc-row{display:flex;align-items:center;gap:9px;margin-bottom:9px;flex-wrap:wrap;}
.doc-row .doc-lbl{font-size:.77rem;font-weight:600;min-width:140px;}

/* Textarea TDR */
.tdr-ta{font-family:var(--mono);font-size:.83rem;min-height:180px;resize:vertical;}

/* Sticky actions */
.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
</style>

<form method="POST" enctype="multipart/form-data" id="projetForm">
<?= csrf_field() ?>

<!-- ① Informations générales -->
<div class="fsec">
  <div class="fsec-hd">
    <div class="fsec-ico"><i class="fa-solid fa-info-circle"></i></div>
    <div><h3>Informations générales</h3><div class="sub">Titre, type, statut, priorité, responsable</div></div>
  </div>
  <div class="fsec-bd">
    <div class="form-group">
      <label class="form-label">Titre du projet <span style="color:var(--danger);">*</span></label>
      <input type="text" name="titre" class="form-control" required
             value="<?= e($projet['titre'] ?? '') ?>"
             placeholder="Ex : Déploiement infrastructure réseau CRICT 2026…">
    </div>
    <div class="form-grid-2" style="margin-top:14px;">
      <div class="form-group">
        <label class="form-label">Type de projet <span style="color:var(--danger);">*</span></label>
        <select name="type_projet" class="form-control">
          <?php foreach (['ADMINISTRATIF'=>'Administratif','INFORMATIQUE'=>'Informatique','TERRAIN'=>'Terrain','RECHERCHE'=>'Recherche','AUTRE'=>'Autre'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($projet['type_projet']??'AUTRE')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Responsable (Chef de projet) <span style="color:var(--danger);">*</span></label>
        <select name="responsable_id" class="form-control" required>
          <option value="">— Sélectionner —</option>
          <?php foreach ($all_users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $resp_id===$u['id']?'selected':'' ?>>
              <?= e($u['nom_complet']) ?> · <?= e($u['matricule']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Statut</label>
        <select name="statut" class="form-control">
          <?php foreach (['PLANIFIE'=>'Planifié','EN_COURS'=>'En cours','SUSPENDU'=>'Suspendu','TERMINE'=>'Terminé','ANNULE'=>'Annulé'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($projet['statut']??'PLANIFIE')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Priorité</label>
        <select name="priorite" class="form-control">
          <?php foreach (['FAIBLE'=>'Faible','NORMALE'=>'Normale','HAUTE'=>'Haute','CRITIQUE'=>'Critique'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($projet['priorite']??'NORMALE')===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group" style="margin-top:14px;">
      <label class="form-label">Description courte</label>
      <textarea name="description" class="form-control" rows="3"
                placeholder="Résumé du projet en 2–3 phrases…"><?= e($projet['description'] ?? '') ?></textarea>
    </div>
  </div>
</div>

<!-- ② Planification & Budget -->
<div class="fsec">
  <div class="fsec-hd">
    <div class="fsec-ico"><i class="fa-solid fa-calendar-days"></i></div>
    <div><h3>Planification & Budget</h3><div class="sub">Dates et enveloppe budgétaire</div></div>
  </div>
  <div class="fsec-bd">
    <div class="form-grid-3">
      <div class="form-group">
        <label class="form-label">Date de début <span style="color:var(--danger);">*</span></label>
        <input type="date" name="date_debut" class="form-control" required
               value="<?= e($projet['date_debut'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Date de fin prévue <span style="color:var(--danger);">*</span></label>
        <input type="date" name="date_fin_prevue" class="form-control" required
               value="<?= e($projet['date_fin_prevue'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Budget</label>
        <div style="display:flex;gap:6px;">
          <input type="number" name="budget" class="form-control"
                 value="<?= e($projet['budget'] ?? '') ?>" placeholder="0" min="0" step="0.01">
          <select name="devise" class="form-control" style="max-width:75px;">
            <option value="GNF" <?= ($projet['devise']??'GNF')==='GNF'?'selected':'' ?>>GNF</option>
            <option value="USD" <?= ($projet['devise']??'')==='USD'?'selected':'' ?>>USD</option>
            <option value="EUR" <?= ($projet['devise']??'')==='EUR'?'selected':'' ?>>EUR</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ③ TDR & Cahier des charges -->
<div class="fsec">
  <div class="fsec-hd">
    <div class="fsec-ico"><i class="fa-solid fa-file-lines"></i></div>
    <div><h3>TDR & Cahier des charges</h3><div class="sub">Texte libre — Markdown accepté</div></div>
  </div>
  <div class="fsec-bd">
    <div class="form-group">
      <label class="form-label"><i class="fa-solid fa-file-alt" style="color:var(--blue-500);"></i> Termes De Référence (TDR)</label>
      <textarea name="tdr" class="form-control tdr-ta"
                placeholder="# Contexte&#10;&#10;## Objectifs&#10;&#10;## Résultats attendus…"><?= htmlspecialchars($projet['tdr']??'',ENT_QUOTES) ?></textarea>
    </div>
    <div class="form-group" style="margin-top:16px;">
      <label class="form-label"><i class="fa-solid fa-book" style="color:var(--blue-500);"></i> Cahier des charges</label>
      <textarea name="cahier_charges" class="form-control tdr-ta" rows="10"
                placeholder="## Spécifications techniques&#10;&#10;## Contraintes…"><?= htmlspecialchars($projet['cahier_charges']??'',ENT_QUOTES) ?></textarea>
    </div>
  </div>
</div>

<!-- ④ Membres -->
<div class="fsec">
  <div class="fsec-hd">
    <div class="fsec-ico"><i class="fa-solid fa-users"></i></div>
    <div><h3>Membres de l'équipe</h3><div class="sub">Sélectionnez les collaborateurs affectés</div></div>
  </div>
  <div class="fsec-bd">
    <div class="mbr-picker">
      <div class="mbr-search">
        <i class="fa-solid fa-search"></i>
        <input type="text" class="form-control" id="mbrSearch"
               placeholder="Filtrer par nom, matricule ou département…"
               oninput="filtrerMbr(this.value)">
      </div>
      <div class="mbr-list" id="mbrList">
        <?php foreach ($all_users as $u):
          $init    = strtoupper(mb_substr($u['nom_complet'],0,1));
          $checked = in_array($u['id'], $membres_ids) ? 'checked' : '';
        ?>
        <label class="mbr-item <?= $checked?'sel':'' ?>"
               data-s="<?= e(strtolower($u['nom_complet'].' '.$u['matricule'].' '.($u['departement']??''))) ?>">
          <input type="checkbox" name="membres[]" value="<?= $u['id'] ?>" <?= $checked ?>>
          <div class="mbr-av"><?= $init ?></div>
          <div class="mbr-info">
            <div class="mbr-nom"><?= e($u['nom_complet']) ?></div>
            <div class="mbr-sub"><?= e($u['matricule']) ?> · <?= e($u['groupe']) ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="font-size:.71rem;color:var(--text-muted);">
        <span id="mbrCpt"><?= count($membres_ids) ?></span> membre<?= count($membres_ids)>1?'s':'' ?> sélectionné<?= count($membres_ids)>1?'s':'' ?> — le responsable est automatiquement ajouté comme chef.
      </div>
    </div>
  </div>
</div>

<!-- ⑤ Documents du projet -->
<div class="fsec">
  <div class="fsec-hd">
    <div class="fsec-ico"><i class="fa-solid fa-paperclip"></i></div>
    <div><h3>Documents du projet</h3><div class="sub">TDR, cahier des charges, budget… (PDF, Word, Excel — max 20 Mo)</div></div>
  </div>
  <div class="fsec-bd">

    <?php if (!empty($projet['documents'])): ?>
    <div style="margin-bottom:16px;">
      <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;">Documents existants</div>
      <?php
      $cats_lbl = ['TDR'=>'TDR','CAHIER_CHARGES'=>'Cahier des charges','BUDGET'=>'Budget','RAPPORT'=>'Rapport','AUTRE'=>'Autre'];
      $docs_by  = [];
      foreach ($projet['documents'] as $d) { $docs_by[$d['categorie']][] = $d; }
      foreach ($docs_by as $cat => $docs): ?>
        <div style="margin-bottom:8px;">
          <div style="font-size:.71rem;color:var(--blue-600);font-weight:700;margin-bottom:3px;"><?= $cats_lbl[$cat]??$cat ?></div>
          <?php foreach ($docs as $d): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--bg);border-radius:7px;margin-bottom:3px;">
            <i class="fa-solid fa-file-pdf" style="color:#dc2626;"></i>
            <span style="flex:1;font-size:.81rem;"><?= e($d['libelle']?:$d['nom_original']) ?></span>
            <span style="font-size:.68rem;color:var(--text-muted);"><?= round($d['taille']/1024,1) ?> Ko</span>
            <a href="../projets/upload.php?id=<?= $d['id'] ?>&type=projet" target="_blank" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-download"></i></a>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;">Ajouter des documents</div>
    <?php foreach (['doc_tdr'=>'TDR','doc_cdc'=>'Cahier des charges','doc_budget'=>'Budget','doc_autre'=>'Autre document'] as $champ=>$lbl): ?>
    <div class="doc-row">
      <span class="doc-lbl"><i class="fa-solid fa-paperclip" style="color:var(--blue-400);"></i> <?= $lbl ?></span>
      <input type="text"  name="lib_<?= $champ ?>" class="form-control" placeholder="Libellé…" style="flex:1;min-width:120px;max-width:240px;">
      <input type="file"  name="<?= $champ ?>" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods" style="flex:1;min-width:140px;">
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Actions sticky -->
<div class="form-actions">
  <a href="<?= $projet ? 'voir.php?id='.$projet_id : 'index.php' ?>" class="btn btn-secondary">
    <i class="fa-solid fa-xmark"></i> Annuler
  </a>
  <button type="submit" class="btn btn-primary">
    <i class="fa-solid <?= $mode==='creer'?'fa-plus':'fa-floppy-disk' ?>"></i>
    <?= $mode==='creer' ? 'Créer le projet' : 'Enregistrer les modifications' ?>
  </button>
</div>
</form>

<?php
$extra_scripts = '<script>
function filtrerMbr(q){
  q=q.toLowerCase();
  document.querySelectorAll("#mbrList .mbr-item").forEach(el=>{
    el.style.display=(!q||el.dataset.s.includes(q))?"":"none";
  });
}
document.getElementById("mbrList")?.addEventListener("change",()=>{
  const n=document.querySelectorAll("#mbrList input:checked").length;
  document.getElementById("mbrCpt").textContent=n;
  document.querySelectorAll("#mbrList .mbr-item").forEach(el=>{
    el.classList.toggle("sel",el.querySelector("input").checked);
  });
});
document.getElementById("projetForm")?.addEventListener("submit",e=>{
  const dd=document.querySelector("[name=date_debut]").value;
  const df=document.querySelector("[name=date_fin_prevue]").value;
  if(dd&&df&&df<=dd){e.preventDefault();alert("La date de fin doit être après la date de début.");}
});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
