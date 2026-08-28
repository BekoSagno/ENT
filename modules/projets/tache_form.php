<?php
// modules/projets/tache_form.php — Créer / modifier une tâche
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/projet.php';

session_demarrer();
exiger_permission('PROJETS', 'VOIR');

$user      = user_courant();
$projet_id = (int)($_REQUEST['projet_id'] ?? 0);
$tache_id  = (int)($_REQUEST['id']        ?? 0);

if (!$projet_id) { set_flash('error','Projet non spécifié.'); redirect('/modules/projets/index.php'); }
$projet = projet_charger($projet_id);
if (!$projet) { set_flash('error','Projet introuvable.'); redirect('/modules/projets/index.php'); }

$tache     = $tache_id ? tache_charger($tache_id) : null;
$mode      = $tache ? 'modifier' : 'creer';
$est_chef  = _projet_est_chef($projet_id, (int)$user['id']);

// Contrôle d'accès fin-grain
if ($mode === 'creer' && !$est_chef) {
    set_flash('error','Seul le chef de projet peut créer des tâches.');
    redirect('/modules/projets/voir.php?id='.$projet_id);
}
if ($mode === 'modifier' && !$est_chef) {
    if ((int)($tache['assigne_a']??0) !== (int)$user['id']) {
        set_flash('error','Accès refusé.'); redirect('/modules/projets/voir.php?id='.$projet_id);
    }
}

// ── Traitement POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $h = (int)($_POST['duree_h'] ?? 0);
    $m = (int)($_POST['duree_m'] ?? 0);
    $duree_h_total = $h + ($m > 0 ? 0 : 0); // en heures (convertir minutes → fraction)
    // On stocke tout en heures arrondies ; on passe $h + arrondi des minutes
    $duree_val = ($h * 60 + min(59,$m)) > 0 ? (int)round(($h * 60 + min(59,$m)) / 60 * 10) / 10 : null;
    // Stocker en minutes pour rester compatible avec le suivi quotidien
    // La colonne `duree_estimee` est en HEURES dans projets_taches → on stocke h + frac
    $duree_stock = ($h > 0 || $m > 0) ? $h + round($m/60, 2) : null;

    $data = [
        'projet_id'       => $projet_id,
        'parent_id'       => (int)($_POST['parent_id']     ?? 0) ?: null,
        'titre'           => clean($_POST['titre']          ?? ''),
        'description'     => clean($_POST['description']    ?? ''),
        'assigne_a'       => (int)($_POST['assigne_a']      ?? 0) ?: null,
        'date_debut'      => clean($_POST['date_debut']     ?? '') ?: null,
        'date_fin_prevue' => clean($_POST['date_fin_prevue']?? '') ?: null,
        'duree_estimee'   => $duree_stock,
        'dependance_type' => clean($_POST['dependance_type']?? 'INDEPENDANTE'),
        'depend_de'       => (int)($_POST['depend_de']      ?? 0) ?: null,
        'statut'          => clean($_POST['statut']         ?? 'A_FAIRE'),
        'avancement'      => (int)($_POST['avancement']     ?? 0),
        'priorite'        => clean($_POST['priorite']       ?? 'NORMALE'),
        'outputs_attendus'=> clean($_POST['outputs_attendus']?? ''),
    ];

    $res = $mode === 'creer' ? tache_creer($data) : tache_modifier($tache_id, $data);

    if ($res['succes']) {
        $tid = $mode === 'creer' ? $res['tache_id'] : $tache_id;

        // Fichiers outputs (multiples)
        if (!empty($_FILES['out_fichier']['name'][0])) {
            foreach ($_FILES['out_fichier']['name'] as $k => $nom) {
                if ($_FILES['out_fichier']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $f = ['name'=>$nom,'tmp_name'=>$_FILES['out_fichier']['tmp_name'][$k],
                      'error'=>$_FILES['out_fichier']['error'][$k],'size'=>$_FILES['out_fichier']['size'][$k]];
                output_ajouter_fichier($tid, $projet_id, $projet['type_projet'], $f,
                                       clean($_POST['out_libelle'][$k] ?? ''));
            }
        }

        // Lien URL
        if (!empty(trim($_POST['out_url'] ?? ''))) {
            output_ajouter_lien($tid, $projet_id,
                clean($_POST['out_url']), clean($_POST['out_url_lib'] ?? ''));
        }

        set_flash('success', $mode==='creer' ? 'Tâche créée.' : 'Tâche modifiée.');
        header('Location: voir.php?id='.$projet_id.'#t'.$tid); exit;
    }
    set_flash('error', $res['message'] ?? 'Erreur.');
}

// Données formulaire
$membres_projet = $projet['membres'] ?? [];
$taches_dep     = taches_pour_dependance($projet_id, $tache_id);
$taches_parent  = array_filter($taches_dep, fn($t)=>$t['id']!==$tache_id);

// Durée existante en heures → décomposer en h + min
$duree_h_val = ''; $duree_m_val = '';
if (!empty($tache['duree_estimee'])) {
    $dh_float = (float)$tache['duree_estimee'];
    $duree_h_val = (int)floor($dh_float);
    $duree_m_val = (int)round(($dh_float - $duree_h_val) * 60);
}

$statut_labels = [
    'A_FAIRE'=>'À faire','EN_COURS'=>'En cours','EN_REVISION'=>'En révision',
    'TERMINEE'=>'Terminée','BLOQUEE'=>'Bloquée','ANNULEE'=>'Annulée',
];
$type_projet = $projet['type_projet'];
// Accept par type de projet
$accept_files = match($type_projet) {
    'INFORMATIQUE' => '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.zip',
    'TERRAIN'      => '.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp',
    'RECHERCHE'    => '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png',
    default        => '.pdf,.doc,.docx,.xls,.xlsx,.odt,.ods',
};
$hint_outputs = match($type_projet) {
    'INFORMATIQUE' => 'Dépôts Git, URL de démo, captures d\'écran, archives ZIP…',
    'TERRAIN'      => 'Photos terrain, rapports PDF, comptes rendus, justificatifs…',
    'RECHERCHE'    => 'Articles, jeux de données, rapports, images…',
    default        => 'Documents PDF, Word, Excel, tableaux de bord…',
};

$page_titre      = $mode==='creer' ? 'Nouvelle tâche' : 'Modifier la tâche';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Projets</a>'
                 . ' · <a href="voir.php?id='.$projet_id.'" style="color:var(--blue-500);text-decoration:none;">'.e($projet['titre']).'</a>'
                 . ' · '.($mode==='creer'?'Nouvelle tâche':e($tache['titre']??''));
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

/* Durée */
.duree-picker{display:flex;gap:8px;align-items:center;}
.duree-part{display:flex;align-items:center;gap:4px;}
.duree-part .form-control{width:62px;text-align:center;}
.duree-unit{font-size:.77rem;color:var(--text-muted);font-weight:600;}

/* Avancement */
.avan-wrap{display:flex;align-items:center;gap:12px;}
.avan-wrap input[type=range]{flex:1;accent-color:var(--blue-600);}
.avan-val{font-size:.9rem;font-weight:800;color:var(--blue-700);min-width:42px;text-align:right;}

/* Dépendance pills */
.dep-pills{display:flex;gap:7px;flex-wrap:wrap;}
.dep-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;border:1.5px solid var(--border);font-size:.79rem;font-weight:600;color:var(--text-muted);cursor:pointer;transition:all .14s;user-select:none;}
.dep-pill:hover{border-color:var(--blue-400);color:var(--blue-600);}
.dep-pill.on{background:var(--blue-600);color:#fff;border-color:var(--blue-600);}

/* Outputs */
.out-exist{display:flex;align-items:center;gap:8px;padding:8px 10px;background:var(--bg);border-radius:8px;margin-bottom:6px;}
.out-ico{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.out-ico.f{background:#fee2e2;color:#dc2626;}
.out-ico.i{background:#fce7f3;color:#9d174d;}
.out-ico.l{background:#dbeafe;color:#1d4ed8;}
.out-files{display:flex;flex-direction:column;gap:8px;}
.out-frow{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.btn-add-f{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:8px;border:2px dashed var(--blue-300);background:var(--blue-50);color:var(--blue-600);font-size:.81rem;font-weight:600;cursor:pointer;transition:all .14s;}
.btn-add-f:hover{background:var(--blue-100);border-color:var(--blue-500);}

/* Sticky actions */
.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;}
</style>

<div style="margin-bottom:14px;">
  <a href="voir.php?id=<?= $projet_id ?>" class="btn btn-secondary btn-sm">
    <i class="fa-solid fa-arrow-left"></i> Retour au projet
  </a>
</div>

<form method="POST" enctype="multipart/form-data" id="tacheForm">
  <?= csrf_field() ?>
  <input type="hidden" name="projet_id" value="<?= $projet_id ?>">
  <?php if ($tache_id): ?><input type="hidden" name="tache_id" value="<?= $tache_id ?>"><?php endif; ?>

  <!-- ① Informations -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-list-check"></i></div>
      <div>
        <h3>Informations de la tâche</h3>
        <div class="sub">Projet : <strong><?= e($projet['titre']) ?></strong> — <?= e($projet['code']) ?></div>
      </div>
    </div>
    <div class="fsec-bd">
      <div class="form-group">
        <label class="form-label">Titre <span style="color:var(--danger);">*</span></label>
        <input type="text" name="titre" class="form-control" required
               value="<?= e($tache['titre'] ?? '') ?>"
               placeholder="Ex : Rédaction du rapport technique…">
      </div>
      <div class="form-grid-2" style="margin-top:14px;">
        <div class="form-group">
          <label class="form-label">Statut</label>
          <select name="statut" class="form-control" id="statSel">
            <?php foreach ($statut_labels as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($tache['statut']??'A_FAIRE')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priorité</label>
          <select name="priorite" class="form-control">
            <?php foreach (['FAIBLE'=>'Faible','NORMALE'=>'Normale','HAUTE'=>'Haute','CRITIQUE'=>'Critique'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($tache['priorite']??'NORMALE')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if (!empty($taches_parent)): ?>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Sous-tâche de (optionnel)</label>
        <select name="parent_id" class="form-control">
          <option value="">— Tâche principale —</option>
          <?php foreach ($taches_parent as $tp): ?>
            <option value="<?= $tp['id'] ?>" <?= ($tache['parent_id']??0)===$tp['id']?'selected':'' ?>>
              <?= e($tp['code'].' – '.$tp['titre']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4"
                  placeholder="Détails, étapes, contraintes…"><?= e($tache['description'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- ② Assignation & Planification -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-user-clock"></i></div>
      <div><h3>Assignation & Planification</h3><div class="sub">Responsable, dates, durée et avancement</div></div>
    </div>
    <div class="fsec-bd">
      <div class="form-grid-2">
        <div class="form-group">
          <label class="form-label">Assigné à</label>
          <select name="assigne_a" class="form-control">
            <option value="">— Non assigné —</option>
            <?php foreach ($membres_projet as $m): ?>
              <option value="<?= $m['utilisateur_id'] ?>"
                      <?= ($tache['assigne_a']??0)===$m['utilisateur_id']?'selected':'' ?>>
                <?= e($m['nom_complet']) ?> · <?= e($m['matricule']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Durée estimée</label>
          <div class="duree-picker">
            <div class="duree-part">
              <input type="number" id="dH" class="form-control" min="0" max="9999"
                     placeholder="0" value="<?= $duree_h_val ?>">
              <span class="duree-unit">h</span>
            </div>
            <div class="duree-part">
              <input type="number" id="dM" name="duree_m" class="form-control" min="0" max="59"
                     placeholder="00" value="<?= $duree_m_val ?>">
              <span class="duree-unit">min</span>
            </div>
          </div>
          <input type="hidden" name="duree_h" id="dHHid" value="<?= $duree_h_val ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Date de début</label>
          <input type="date" name="date_debut" class="form-control"
                 value="<?= e($tache['date_debut'] ?? $projet['date_debut'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" style="color:var(--danger);">Deadline <span style="font-weight:400;color:var(--text-muted);">(date fin prévue)</span></label>
          <input type="date" name="date_fin_prevue" class="form-control"
                 value="<?= e($tache['date_fin_prevue'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Avancement</label>
        <div class="avan-wrap">
          <input type="range" name="avancement" id="aRange" min="0" max="100" step="5"
                 value="<?= (int)($tache['avancement']??0) ?>"
                 oninput="document.getElementById('aVal').textContent=this.value+'%';syncStatut(this.value);">
          <span class="avan-val" id="aVal"><?= (int)($tache['avancement']??0) ?>%</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ③ Dépendance -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-link"></i></div>
      <div><h3>Dépendance</h3><div class="sub">Indépendante ou dépend d'une autre tâche ?</div></div>
    </div>
    <div class="fsec-bd">
      <?php $dep = $tache['dependance_type'] ?? 'INDEPENDANTE'; ?>
      <div class="dep-pills" id="depPills">
        <?php foreach (['INDEPENDANTE'=>['Indépendante','fa-circle'],'APRES_FIN'=>['Commence après la FIN de','fa-arrow-right'],'APRES_DEBUT'=>['Commence après le DÉBUT de','fa-play']] as $k=>[$lbl,$ico]): ?>
          <div class="dep-pill <?= $dep===$k?'on':'' ?>" onclick="setDep('<?= $k ?>')" data-v="<?= $k ?>">
            <i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?>
          </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="dependance_type" id="depType" value="<?= e($dep) ?>">

      <div id="depBlock" style="margin-top:14px;<?= $dep==='INDEPENDANTE'?'display:none;':'' ?>">
        <label class="form-label">Tâche dont elle dépend</label>
        <select name="depend_de" class="form-control">
          <option value="">— Choisir —</option>
          <?php foreach ($taches_dep as $td): ?>
            <option value="<?= $td['id'] ?>" <?= ($tache['depend_de']??0)===$td['id']?'selected':'' ?>>
              <?= e($td['code'].' – '.$td['titre']) ?> (<?= $td['statut'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- ④ Livrables & Outputs -->
  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-box-open"></i></div>
      <div>
        <h3>Livrables & Outputs</h3>
        <div class="sub"><?= e($hint_outputs) ?></div>
      </div>
    </div>
    <div class="fsec-bd">

      <div class="form-group">
        <label class="form-label">Description des livrables attendus</label>
        <textarea name="outputs_attendus" class="form-control" rows="3"
                  placeholder="Ex : rapport PDF, 3 photos terrain, tableau Excel, URL de démo…"><?= e($tache['outputs_attendus']??'') ?></textarea>
      </div>

      <!-- Outputs existants -->
      <?php if (!empty($tache['outputs'])): ?>
      <div style="margin-top:14px;">
        <div class="form-label" style="margin-bottom:8px;">Livrables déjà déposés</div>
        <?php foreach ($tache['outputs'] as $o):
          $oc = $o['type']==='IMAGE'?'i':($o['type']==='LIEN'?'l':'f');
          $oi = $o['type']==='IMAGE'?'fa-image':($o['type']==='LIEN'?'fa-link':'fa-file');
        ?>
        <div class="out-exist">
          <div class="out-ico <?= $oc ?>"><i class="fa-solid <?= $oi ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($o['libelle']?:$o['nom_original']?:$o['url']?:'') ?></div>
            <div style="font-size:.69rem;color:var(--text-muted);"><?= e($o['deposant_nom']??'') ?><?= $o['taille']?' · '.round($o['taille']/1024,1).' Ko':'' ?></div>
          </div>
          <a href="upload.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon"><i class="fa-solid fa-download"></i></a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Upload nouveaux fichiers -->
      <div style="margin-top:16px;">
        <div class="form-label" style="margin-bottom:8px;">Ajouter des fichiers</div>
        <div class="out-files" id="outFiles">
          <div class="out-frow">
            <input type="text"  name="out_libelle[]" class="form-control" placeholder="Description…" style="max-width:240px;">
            <input type="file"  name="out_fichier[]" class="form-control" accept="<?= $accept_files ?>">
          </div>
        </div>
        <button type="button" class="btn-add-f" style="margin-top:8px;" onclick="ajouterFichier()">
          <i class="fa-solid fa-plus"></i> Ajouter un fichier
        </button>
      </div>

      <!-- Lien URL -->
      <div style="margin-top:16px;">
        <div class="form-label" style="margin-bottom:8px;">
          <i class="fa-solid fa-link" style="color:var(--blue-500);"></i>
          Ajouter un lien<?= $type_projet==='INFORMATIQUE'?' (dépôt Git, URL démo…)':'' ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <input type="url" name="out_url" class="form-control" placeholder="https://…" style="flex:2;min-width:200px;">
          <input type="text" name="out_url_lib" class="form-control" placeholder="Libellé du lien…" style="flex:1;min-width:150px;">
        </div>
      </div>
    </div>
  </div>

  <!-- Actions sticky -->
  <div class="form-actions">
    <a href="voir.php?id=<?= $projet_id ?>" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Annuler</a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid <?= $mode==='creer'?'fa-plus':'fa-floppy-disk' ?>"></i>
      <?= $mode==='creer'?'Créer la tâche':'Enregistrer' ?>
    </button>
  </div>
</form>

<?php
$extra_scripts = '<script>
// ── Durée hh + mm → duree_h hidden ──
const dH=document.getElementById("dH"), dM=document.getElementById("dM"), dHH=document.getElementById("dHHid");
function syncDuree(){
  const h=Math.max(0,parseInt(dH.value)||0);
  const m=Math.min(59,Math.max(0,parseInt(dM.value)||0));
  if(parseInt(dM.value)>59) dM.value=59;
  dHH.value=h;
}
dH?.addEventListener("input",syncDuree);
dM?.addEventListener("input",()=>{if((parseInt(dM.value)||0)>59)dM.value=59;syncDuree();});
dM?.addEventListener("blur",()=>{const v=parseInt(dM.value)||0;if(v>0&&v<10)dM.value="0"+v;});

// ── Avancement ↔ Statut ──
function syncStatut(val){
  const s=document.getElementById("statSel"); if(!s) return;
  if(parseInt(val)===100&&s.value!=="TERMINEE") s.value="TERMINEE";
  else if(parseInt(val)===0&&s.value==="EN_COURS") s.value="A_FAIRE";
  else if(parseInt(val)>0&&parseInt(val)<100&&s.value==="A_FAIRE") s.value="EN_COURS";
}
document.getElementById("statSel")?.addEventListener("change",function(){
  const r=document.getElementById("aRange");
  if(this.value==="TERMINEE"){r.value=100;document.getElementById("aVal").textContent="100%";}
  if(this.value==="A_FAIRE"){r.value=0;document.getElementById("aVal").textContent="0%";}
});

// ── Dépendance ──
function setDep(val){
  document.getElementById("depType").value=val;
  document.querySelectorAll(".dep-pill").forEach(p=>p.classList.toggle("on",p.dataset.v===val));
  document.getElementById("depBlock").style.display=val==="INDEPENDANTE"?"none":"";
}

// ── Multi-fichier outputs ──
let outCnt=1;
function ajouterFichier(){
  if(outCnt>=5){alert("Max 5 fichiers par envoi.");return;}
  const row=document.createElement("div"); row.className="out-frow";
  row.innerHTML=`
    <input type="text"  name="out_libelle[]" class="form-control" placeholder="Description…" style="max-width:240px;">
    <input type="file"  name="out_fichier[]" class="form-control" accept="<?= addslashes($accept_files) ?>">
    <button type="button" onclick="this.parentElement.remove();outCnt--;"
            style="background:#fee2e2;border:1px solid #fecaca;color:#dc2626;border-radius:7px;padding:6px 9px;cursor:pointer;font-size:.78rem;flex-shrink:0;">
      <i class="fa-solid fa-trash"></i>
    </button>`;
  document.getElementById("outFiles").appendChild(row);
  outCnt++;
}

// ── Validation dates ──
document.getElementById("tacheForm")?.addEventListener("submit",e=>{
  const dd=document.querySelector("[name=date_debut]")?.value;
  const df=document.querySelector("[name=date_fin_prevue]")?.value;
  if(dd&&df&&df<dd){e.preventDefault();alert("La deadline doit être après la date de début.");}
});
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
