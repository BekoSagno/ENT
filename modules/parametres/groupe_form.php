<?php
// modules/parametres/groupe_form.php — Créer / modifier un groupe
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/privilege.php';

session_demarrer();
exiger_permission('PARAMETRES','CREER');

$gid    = (int)($_GET['id'] ?? 0);
$groupe = $gid ? priv_groupe_charger($gid) : null;
$mode   = $groupe ? 'modifier' : 'creer';

if ($groupe && $groupe['code'] === 'SUPER_ADMIN') {
    set_flash('error','Le Super Administrateur ne peut pas être modifié.');
    redirect('/modules/parametres/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $data = [
        'code'        => clean($_POST['code']        ?? ''),
        'libelle'     => clean($_POST['libelle']      ?? ''),
        'departement' => clean($_POST['departement']  ?? ''),
        'description' => clean($_POST['description']  ?? ''),
        'niveau'      => (int)($_POST['niveau']       ?? 5),
        'actif'       => (int)($_POST['actif']        ?? 1),
    ];

    $erreurs = [];
    if ($mode === 'creer' && empty($data['code']))    $erreurs[] = 'Le code est obligatoire.';
    if (empty($data['libelle']))                      $erreurs[] = 'Le libellé est obligatoire.';
    if (empty($data['departement']))                  $erreurs[] = 'Le département est obligatoire.';
    if (!preg_match('/^[A-Z0-9_]{2,20}$/', strtoupper($data['code'] ?? '')))
        $erreurs[] = 'Code invalide (lettres majuscules, chiffres, underscore, 2-20 car.).';

    if (empty($erreurs)) {
        $res = $mode === 'creer' ? priv_groupe_creer($data) : priv_groupe_modifier($gid, $data);
        if ($res['succes']) {
            set_flash('success', $mode === 'creer'
                ? 'Groupe créé. Configurez maintenant ses permissions.'
                : 'Groupe modifié.');
            $redirect_id = $mode === 'creer' ? $res['id'] : $gid;
            header('Location: matrice.php?groupe_id='.$redirect_id); exit;
        }
        set_flash('error', $res['message'] ?? 'Erreur.');
    } else {
        foreach ($erreurs as $e_) set_flash('error', $e_);
    }
}

$depts = [
    'DIRECTION'             => 'Direction',
    'ADMIN_GENERALE'        => 'Administration Générale',
    'ADMIN_FINANCIERE'      => 'Administration Financière',
    'RECHERCHE_SCIENTIFIQUE'=> 'Recherche Scientifique',
    'SYSTEME'               => 'Système',
];

$page_titre      = $mode === 'creer' ? 'Nouveau groupe' : 'Modifier le groupe';
$page_breadcrumb = '<a href="index.php" style="color:var(--blue-500);text-decoration:none;">Privilèges</a> · '.($mode==='creer'?'Nouveau groupe':e($groupe['libelle']??''));
$module_actif    = 'PARAMETRES';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.fsec{background:var(--surface);border:1.5px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:20px;}
.fsec-hd{display:flex;align-items:center;gap:10px;padding:13px 20px;background:var(--bg);border-bottom:1px solid var(--border);}
.fsec-ico{width:30px;height:30px;border-radius:7px;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.82rem;}
.fsec-hd h3{font-size:.88rem;font-weight:700;margin:0;}
.fsec-bd{padding:20px 22px;}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:640px){.fg2{grid-template-columns:1fr;}}
.form-actions{position:sticky;bottom:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid var(--border);padding:13px 0;display:flex;gap:10px;justify-content:flex-end;}
.code-hint{font-size:.7rem;color:var(--text-muted);margin-top:4px;}
.niveau-btns{display:flex;gap:7px;margin-top:6px;flex-wrap:wrap;}
.niv-btn{padding:7px 14px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);cursor:pointer;font-size:.82rem;font-weight:700;color:var(--text-muted);transition:all .14s;}
.niv-btn.on{background:var(--blue-600);border-color:var(--blue-600);color:#fff;}
</style>

<div style="margin-bottom:14px;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</div>

<form method="POST" id="groupeForm">
  <?= csrf_field() ?>
  <input type="hidden" name="niveau" id="niveauHidden" value="<?= (int)($groupe['niveau']??5) ?>">

  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-users-gear"></i></div>
      <h3>Identité du groupe</h3>
    </div>
    <div class="fsec-bd">
      <div class="fg2">
        <div class="form-group">
          <label class="form-label">Code unique <span style="color:var(--danger);">*</span></label>
          <?php if ($mode === 'modifier'): ?>
            <div style="background:var(--bg);padding:9px 14px;border-radius:8px;font-weight:700;font-family:var(--mono);font-size:.9rem;border:1.5px solid var(--border);">
              <?= e($groupe['code']) ?>
            </div>
            <div class="code-hint">Le code ne peut pas être modifié après création.</div>
          <?php else: ?>
            <input type="text" name="code" class="form-control" maxlength="20"
                   value="<?= e($_POST['code'] ?? '') ?>"
                   placeholder="Ex: SUPERVISEUR"
                   oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9_]/g,'')">
            <div class="code-hint">Lettres majuscules, chiffres et underscore uniquement (ex: CHEF_PROJET)</div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé complet <span style="color:var(--danger);">*</span></label>
          <input type="text" name="libelle" class="form-control" maxlength="100"
                 value="<?= e($groupe['libelle'] ?? $_POST['libelle'] ?? '') ?>"
                 placeholder="Ex: Chef de Projet">
        </div>
      </div>

      <div class="fg2" style="margin-top:14px;">
        <div class="form-group">
          <label class="form-label">Département <span style="color:var(--danger);">*</span></label>
          <select name="departement" class="form-control" required>
            <option value="">— Choisir —</option>
            <?php foreach ($depts as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($groupe['departement']??$_POST['departement']??'')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Statut</label>
          <select name="actif" class="form-control">
            <option value="1" <?= ($groupe['actif']??1)==1?'selected':'' ?>>Actif</option>
            <option value="0" <?= ($groupe['actif']??1)==0?'selected':'' ?>>Inactif</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-top:14px;">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"
                  placeholder="Décrivez le rôle de ce groupe…"><?= e($groupe['description'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <div class="fsec">
    <div class="fsec-hd">
      <div class="fsec-ico"><i class="fa-solid fa-layer-group"></i></div>
      <h3>Niveau hiérarchique</h3>
    </div>
    <div class="fsec-bd">
      <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:8px;">
        Détermine la position dans l'organigramme. <strong>1 = Direction</strong>, niveaux plus élevés = opérationnel.
      </div>
      <div class="niveau-btns" id="niveauBtns">
        <?php for ($n=1;$n<=6;$n++):
          $labels = [1=>'Direction',2=>'Cadre sup.',3=>'Cadre',4=>'Agent',5=>'Technicien',6=>'Stagiaire'];
        ?>
        <button type="button" class="niv-btn <?= ($groupe['niveau']??5)===$n?'on':'' ?>"
                onclick="setNiveau(<?= $n ?>)">
          <span style="font-size:.65rem;display:block;text-align:center;">N<?= $n ?></span>
          <?= $labels[$n] ?>
        </button>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-xmark"></i> Annuler</a>
    <button type="submit" class="btn btn-primary">
      <i class="fa-solid <?= $mode==='creer'?'fa-plus':'fa-floppy-disk' ?>"></i>
      <?= $mode==='creer' ? 'Créer et configurer les permissions' : 'Enregistrer' ?>
    </button>
  </div>
</form>

<?php
$extra_scripts = '<script>
function setNiveau(n){
  document.getElementById("niveauHidden").value = n;
  document.querySelectorAll(".niv-btn").forEach((b,i) => {
    b.classList.toggle("on", i+1 === n);
  });
}
</script>';
include __DIR__ . '/../../views/layout/footer.php';
?>
