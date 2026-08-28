<?php
// modules/utilisateurs/voir.php — Fiche détaillée d'un utilisateur
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/utilisateur.php';

session_demarrer();
exiger_permission('UTILISATEURS', 'VOIR');

$id = (int)($_GET['id'] ?? 0);
$u  = utilisateur_par_id($id);
if (!$u) { set_flash('error', 'Utilisateur introuvable.'); redirect('/modules/utilisateurs/index.php'); }

$peut_modifier = a_permission('UTILISATEURS', 'MODIFIER');
$est_moi       = $id === user_id();

// Matrice des permissions (overrides spécifiques + droits hérités du groupe)
$modules_perms = modules_avec_permissions();
$specifiques   = permissions_utilisateur_specifiques($id);   // overrides existants
$herites       = permissions_groupe_utilisateur((int)$u['groupe_id']);

// Indexer les overrides par permission_id => accorde (bool)
$override_map = [];
foreach ($specifiques as $sp) {
    $override_map[(int)$sp['permission_id']] = (bool)$sp['accorde'];
}

$initiales = mb_strtoupper(mb_substr($u['prenoms'], 0, 1) . mb_substr($u['nom'], 0, 1));

$page_titre      = $u['prenoms'] . ' ' . $u['nom'];
$page_breadcrumb = 'Administration · Utilisateurs · Fiche';
$module_actif    = 'UTILISATEURS';
include __DIR__ . '/../../views/layout/header.php';
?>
<style>
.profil-grid{display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;}
@media (max-width:900px){.profil-grid{grid-template-columns:1fr;}}

.profil-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.profil-head{background:linear-gradient(160deg,var(--blue-800),var(--blue-600));padding:26px 22px 22px;text-align:center;color:#fff;}
.profil-avatar{width:84px;height:84px;border-radius:50%;background:rgba(255,255,255,.15);border:3px solid rgba(255,255,255,.4);
  display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;margin:0 auto 12px;overflow:hidden;}
.profil-avatar img{width:100%;height:100%;object-fit:cover;}
.profil-nom{font-size:1.1rem;font-weight:800;}
.profil-mat{font-size:.78rem;color:var(--blue-100);font-family:var(--mono);margin-top:3px;}
.profil-body{padding:18px 22px;}
.info-line{display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);font-size:.84rem;}
.info-line:last-child{border-bottom:none;}
.info-line .ic{width:30px;color:var(--blue-500);flex-shrink:0;text-align:center;padding-top:1px;}
.info-line .lbl{color:var(--text-muted);width:120px;flex-shrink:0;}
.info-line .val{color:var(--text);font-weight:500;flex:1;word-break:break-word;}

.dept-tag{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:600;
  background:var(--blue-50);color:var(--blue-700);}

.perm-mod{border:1px solid var(--border);border-radius:10px;margin-bottom:12px;overflow:hidden;}
.perm-mod-head{display:flex;align-items:center;gap:10px;padding:11px 16px;background:var(--bg);font-weight:700;font-size:.85rem;}
.perm-mod-head .ic{width:26px;height:26px;border-radius:7px;background:var(--blue-100);color:var(--blue-600);display:flex;align-items:center;justify-content:center;font-size:.8rem;}
.perm-row{display:flex;align-items:center;gap:12px;padding:10px 16px;border-top:1px solid var(--border);font-size:.83rem;}
.perm-row .pname{flex:1;}
.perm-row .pbase{font-size:.72rem;color:var(--text-muted);}
.seg{display:inline-flex;border:1.5px solid var(--border);border-radius:8px;overflow:hidden;}
.seg label{padding:5px 11px;font-size:.74rem;font-weight:600;cursor:pointer;color:var(--text-muted);background:var(--surface);}
.seg input{display:none;}
.seg label:not(:last-child){border-right:1.5px solid var(--border);}
.seg input:checked + label.h{background:var(--blue-50);color:var(--blue-700);}
.seg input:checked + label.y{background:#dcfce7;color:#166534;}
.seg input:checked + label.n{background:#fee2e2;color:#991b1b;}
</style>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
  <a href="index.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <div style="flex:1;"></div>
  <?php if ($peut_modifier): ?>
    <a href="modifier.php?id=<?= $id ?>" class="btn btn-primary">
      <i class="fa-solid fa-pen"></i> Modifier
    </a>
    <button class="btn btn-secondary" onclick="document.getElementById('modalMdp').classList.add('open')">
      <i class="fa-solid fa-key"></i> Réinitialiser le mot de passe
    </button>
  <?php endif; ?>
</div>

<div class="profil-grid">
  <!-- Colonne gauche : carte profil -->
  <div>
    <div class="profil-card">
      <div class="profil-head">
        <div class="profil-avatar">
          <?php if (!empty($u['photo'])): ?>
            <img src="<?= e($u['photo']) ?>" alt="">
          <?php else: ?>
            <?= e($initiales) ?>
          <?php endif; ?>
        </div>
        <div class="profil-nom"><?= e($u['prenoms'] . ' ' . $u['nom']) ?></div>
        <div class="profil-mat"><?= e($u['matricule']) ?></div>
        <div style="margin-top:10px;"><?= badge_statut($u['statut']) ?></div>
      </div>
      <div class="profil-body">
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-user-tag"></i></span>
          <span class="lbl">Groupe</span>
          <span class="val"><?= e($u['groupe_libelle']) ?> <span style="color:var(--text-muted);">(<?= e($u['groupe_code']) ?>)</span></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-building"></i></span>
          <span class="lbl">Département</span>
          <span class="val"><span class="dept-tag"><?= e(str_replace('_', ' ', $u['departement'])) ?></span></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-envelope"></i></span>
          <span class="lbl">Email</span>
          <span class="val"><?= e($u['email']) ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-phone"></i></span>
          <span class="lbl">Téléphone</span>
          <span class="val"><?= e($u['telephone'] ?? '—') ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-venus-mars"></i></span>
          <span class="lbl">Genre</span>
          <span class="val"><?= $u['genre'] === 'M' ? 'Masculin' : ($u['genre'] === 'F' ? 'Féminin' : '—') ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-cake-candles"></i></span>
          <span class="lbl">Naissance</span>
          <span class="val"><?= !empty($u['date_naissance']) ? format_date($u['date_naissance']) : '—' ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-graduation-cap"></i></span>
          <span class="lbl">Grade</span>
          <span class="val"><?= e($u['grade'] ?? '—') ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-flask"></i></span>
          <span class="lbl">Spécialité</span>
          <span class="val"><?= e($u['specialite'] ?? '—') ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-location-dot"></i></span>
          <span class="lbl">Adresse</span>
          <span class="val"><?= e($u['adresse'] ?? '—') ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-briefcase"></i></span>
          <span class="lbl">Prise de poste</span>
          <span class="val"><?= !empty($u['date_prise_poste']) ? format_date($u['date_prise_poste']) : '—' ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-right-to-bracket"></i></span>
          <span class="lbl">Dernière connexion</span>
          <span class="val"><?= !empty($u['derniere_connexion']) ? format_datetime($u['derniere_connexion']) : 'Jamais' ?></span>
        </div>
        <div class="info-line">
          <span class="ic"><i class="fa-solid fa-calendar-plus"></i></span>
          <span class="lbl">Créé le</span>
          <span class="val"><?= format_datetime($u['cree_le']) ?></span>
        </div>
      </div>
    </div>

    <?php if ($peut_modifier && !$est_moi): ?>
    <!-- Carte statut -->
    <div class="card" style="margin-top:18px;">
      <div class="card-header"><h2><i class="fa-solid fa-toggle-on" style="color:var(--blue-500);"></i> Statut du compte</h2></div>
      <div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
          $statuts = [
            'ACTIF'    => ['Réactiver', 'fa-user-check', '#16a34a', '#dcfce7', '#bbf7d0'],
            'SUSPENDU' => ['Suspendre', 'fa-user-slash', '#d97706', '#fef3c7', '#fde68a'],
            'INACTIF'  => ['Désactiver','fa-user-xmark', '#64748b', '#f1f5f9', '#e2e8f0'],
            'ARCHIVE'  => ['Archiver',  'fa-box-archive','#78716c', '#f5f5f4', '#e7e5e4'],
          ];
          foreach ($statuts as $st => [$lbl,$ico,$col,$bg,$bd]):
            if ($u['statut'] === $st) continue;
        ?>
          <form method="POST" action="actions.php" onsubmit="return confirm('<?= $lbl ?> cet utilisateur ?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="changer_statut">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="statut" value="<?= $st ?>">
            <button type="submit" class="btn btn-sm" style="background:<?= $bg ?>;color:<?= $col ?>;border:1px solid <?= $bd ?>;">
              <i class="fa-solid <?= $ico ?>"></i> <?= $lbl ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Colonne droite : permissions spécifiques -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fa-solid fa-shield-halved" style="color:var(--blue-500);"></i> Permissions spécifiques</h2>
    </div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:18px;">
        <i class="fa-solid fa-circle-info"></i>
        <div>
          Par défaut, l'utilisateur hérite des droits de son groupe <strong><?= e($u['groupe_libelle']) ?></strong>.
          Vous pouvez ici <strong>forcer</strong> (accorder/refuser) un droit individuellement — ces réglages priment sur le groupe.
        </div>
      </div>

      <?php if (!$peut_modifier): ?>
        <div style="font-size:.82rem;color:var(--text-muted);margin-bottom:14px;">
          <i class="fa-solid fa-lock"></i> Vous n'avez pas le droit de modifier les permissions.
        </div>
      <?php endif; ?>

      <form method="POST" action="actions.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sauvegarder_permissions">
        <input type="hidden" name="utilisateur_id" value="<?= $id ?>">

        <?php foreach ($modules_perms as $mod): ?>
          <div class="perm-mod">
            <div class="perm-mod-head">
              <span class="ic"><i class="fa-solid <?= e($mod['icone']) ?>"></i></span>
              <?= e($mod['libelle']) ?>
            </div>
            <?php foreach ($mod['permissions'] as $p):
              $pid       = $p['id'];
              $base      = $herites[$pid] ?? false;             // droit hérité du groupe
              $has_over  = array_key_exists($pid, $override_map);
              $etat      = $has_over ? ($override_map[$pid] ? 'y' : 'n') : 'h';   // h=hérité, y=accordé, n=refusé
            ?>
              <div class="perm-row">
                <div class="pname">
                  <?= e($p['libelle']) ?>
                  <div class="pbase">
                    Hérité du groupe :
                    <?= $base
                        ? '<span style="color:#16a34a;font-weight:600;">Accordé</span>'
                        : '<span style="color:#991b1b;font-weight:600;">Refusé</span>' ?>
                  </div>
                </div>
                <div class="seg">
                  <input type="radio" id="p<?= $pid ?>_h" name="permissions[<?= $pid ?>]" value=""
                         <?= $etat==='h'?'checked':'' ?> <?= $peut_modifier?'':'disabled' ?>>
                  <label for="p<?= $pid ?>_h" class="h">Hérité</label>

                  <input type="radio" id="p<?= $pid ?>_y" name="permissions[<?= $pid ?>]" value="1"
                         <?= $etat==='y'?'checked':'' ?> <?= $peut_modifier?'':'disabled' ?>>
                  <label for="p<?= $pid ?>_y" class="y">Accordé</label>

                  <input type="radio" id="p<?= $pid ?>_n" name="permissions[<?= $pid ?>]" value="0"
                         <?= $etat==='n'?'checked':'' ?> <?= $peut_modifier?'':'disabled' ?>>
                  <label for="p<?= $pid ?>_n" class="n">Refusé</label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($peut_modifier): ?>
          <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk"></i> Enregistrer les permissions
            </button>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<!-- ===== MODAL RÉINITIALISER MOT DE PASSE ===== -->
<?php if ($peut_modifier): ?>
<div class="modal-overlay" id="modalMdp">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <i class="fa-solid fa-key" style="color:var(--warn);"></i>
      <h3>Réinitialiser le mot de passe</h3>
      <button class="modal-close" onclick="document.getElementById('modalMdp').classList.remove('open')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_mdp">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="modal-body">
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px;">
          Un nouveau mot de passe temporaire sera défini pour
          <strong><?= e($u['prenoms'] . ' ' . $u['nom']) ?></strong>.
          Il devra le changer à sa prochaine connexion, et le recevra par email.
        </p>
        <div class="form-group">
          <label class="form-label">Nouveau mot de passe *</label>
          <input type="text" name="nouveau_mdp" class="form-control" required minlength="6"
                 value="CRICT@<?= date('Y') ?>" placeholder="6 caractères minimum">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                onclick="document.getElementById('modalMdp').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-warn"><i class="fa-solid fa-key"></i> Réinitialiser</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
