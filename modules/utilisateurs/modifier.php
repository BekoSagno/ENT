<?php
// modules/utilisateurs/modifier.php — Édition d'un utilisateur
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/utilisateur.php';

session_demarrer();
exiger_permission('UTILISATEURS', 'MODIFIER');

$id = (int)($_GET['id'] ?? 0);
$u  = utilisateur_par_id($id);
if (!$u) { set_flash('error', 'Utilisateur introuvable.'); redirect('/modules/utilisateurs/index.php'); }

$groupes = groupes_lister();

$page_titre      = 'Modifier — ' . $u['prenoms'] . ' ' . $u['nom'];
$page_breadcrumb = 'Administration · Utilisateurs · Modification';
$module_actif    = 'UTILISATEURS';
include __DIR__ . '/../../views/layout/header.php';
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
  <a href="voir.php?id=<?= $id ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour à la fiche</a>
  <div style="flex:1;"></div>
  <div style="font-size:.8rem;color:var(--text-muted);">
    Matricule <code style="background:var(--bg);padding:2px 8px;border-radius:5px;color:var(--blue-700);"><?= e($u['matricule']) ?></code>
  </div>
</div>

<div class="card" style="max-width:840px;margin:0 auto;">
  <div class="card-header">
    <i class="fa-solid fa-user-pen" style="color:var(--blue-500);"></i>
    <h2>Informations de l'utilisateur</h2>
  </div>
  <form method="POST" action="actions.php">
    <div class="card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="modifier">
      <input type="hidden" name="id" value="<?= $id ?>">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" required value="<?= e($u['nom']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Prénom(s) *</label>
          <input type="text" name="prenoms" class="form-control" required value="<?= e($u['prenoms']) ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required value="<?= e($u['email']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="tel" name="telephone" class="form-control" value="<?= e($u['telephone'] ?? '') ?>"
                 placeholder="+224 6XX XXX XXX">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Groupe / Rôle *</label>
          <select name="groupe_id" class="form-control" required>
            <option value="">— Sélectionner —</option>
            <?php foreach ($groupes as $g): ?>
              <option value="<?= $g['id'] ?>" <?= (int)$u['groupe_id'] === (int)$g['id'] ? 'selected' : '' ?>>
                <?= e($g['libelle']) ?> (<?= e($g['code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Genre</label>
          <select name="genre" class="form-control">
            <option value="">— Sélectionner —</option>
            <option value="M" <?= $u['genre'] === 'M' ? 'selected' : '' ?>>Masculin</option>
            <option value="F" <?= $u['genre'] === 'F' ? 'selected' : '' ?>>Féminin</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Grade / Diplôme</label>
          <input type="text" name="grade" class="form-control" value="<?= e($u['grade'] ?? '') ?>"
                 placeholder="Ex: Doctorat, Master, Licence">
        </div>
        <div class="form-group">
          <label class="form-label">Spécialité</label>
          <input type="text" name="specialite" class="form-control" value="<?= e($u['specialite'] ?? '') ?>"
                 placeholder="Ex: Cybersécurité, IA…">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Date de naissance</label>
          <input type="date" name="date_naissance" class="form-control"
                 value="<?= e($u['date_naissance'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Date prise de poste</label>
          <input type="date" name="date_prise_poste" class="form-control"
                 value="<?= e($u['date_prise_poste'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Statut *</label>
          <select name="statut" class="form-control" required <?= $id === user_id() ? 'disabled' : '' ?>>
            <option value="ACTIF"    <?= $u['statut'] === 'ACTIF'    ? 'selected' : '' ?>>Actif</option>
            <option value="INACTIF"  <?= $u['statut'] === 'INACTIF'  ? 'selected' : '' ?>>Inactif</option>
            <option value="SUSPENDU" <?= $u['statut'] === 'SUSPENDU' ? 'selected' : '' ?>>Suspendu</option>
            <option value="ARCHIVE"  <?= $u['statut'] === 'ARCHIVE'  ? 'selected' : '' ?>>Archivé</option>
          </select>
          <?php if ($id === user_id()): ?>
            <input type="hidden" name="statut" value="<?= e($u['statut']) ?>">
            <div style="font-size:.74rem;color:var(--text-muted);margin-top:4px;">
              <i class="fa-solid fa-info-circle"></i> Vous ne pouvez pas modifier votre propre statut.
            </div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">&nbsp;</label>
          <div style="font-size:.78rem;color:var(--text-muted);padding-top:9px;">
            <i class="fa-solid fa-key"></i> Le mot de passe se réinitialise depuis la fiche utilisateur.
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Adresse</label>
        <textarea name="adresse" class="form-control" rows="2" placeholder="Adresse complète…"><?= e($u['adresse'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="modal-footer" style="border-top:1px solid var(--border);">
      <a href="voir.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
      </button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>
