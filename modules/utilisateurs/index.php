<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/utilisateur.php';

session_demarrer();
exiger_permission('UTILISATEURS', 'VOIR');

// Filtres
$filtres = [
    'recherche'  => clean($_GET['q']     ?? ''),
    'groupe_id'  => (int)($_GET['groupe'] ?? 0) ?: null,
    'statut'     => clean($_GET['statut'] ?? ''),
    'departement'=> clean($_GET['dept']   ?? ''),
];
$page     = max(1, (int)($_GET['page'] ?? 1));
$par_page = 20;
$result   = utilisateur_lister($filtres, $par_page, ($page - 1) * $par_page);
$pagination = paginer($result['total'], $par_page, $page);
$groupes  = groupes_lister();

$page_titre      = 'Gestion des Utilisateurs';
$page_breadcrumb = 'Administration · Utilisateurs';
$module_actif    = 'UTILISATEURS';

include __DIR__ . '/../../views/layout/header.php';
?>

<!-- Actions barre -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:22px;">
  <?php if (a_permission('UTILISATEURS','CREER')): ?>
  <button class="btn btn-primary" onclick="document.getElementById('modalCreer').classList.add('open')">
    <i class="fa-solid fa-user-plus"></i> Nouvel utilisateur
  </button>
  <?php endif; ?>
  <?php if (a_permission('UTILISATEURS','EXPORTER')): ?>
  <a href="?export=csv&<?= http_build_query($filtres) ?>" class="btn btn-secondary">
    <i class="fa-solid fa-file-export"></i> Exporter CSV
  </a>
  <?php endif; ?>
  <div style="flex:1;"></div>
  <div style="font-size:.82rem;color:var(--text-muted);">
    <strong><?= $result['total'] ?></strong> utilisateur<?= $result['total'] > 1 ? 's' : '' ?> trouvé<?= $result['total'] > 1 ? 's' : '' ?>
  </div>
</div>

<div class="card">
  <!-- Filtres -->
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa-solid fa-search"></i>
      <input type="text" class="form-control" id="searchInput"
             placeholder="Rechercher par nom, prénom, email, matricule…"
             value="<?= e($filtres['recherche']) ?>"
             onkeyup="filtrerUtilisateurs()">
    </div>

    <select class="form-control" style="width:auto;min-width:160px;" id="filtreGroupe" onchange="filtrerUtilisateurs()">
      <option value="">Tous les groupes</option>
      <?php foreach ($groupes as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $filtres['groupe_id'] == $g['id'] ? 'selected' : '' ?>>
          <?= e($g['libelle']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select class="form-control" style="width:auto;min-width:130px;" id="filtreStatut" onchange="filtrerUtilisateurs()">
      <option value="">Tous statuts</option>
      <option value="ACTIF"    <?= $filtres['statut'] === 'ACTIF'    ? 'selected' : '' ?>>Actif</option>
      <option value="INACTIF"  <?= $filtres['statut'] === 'INACTIF'  ? 'selected' : '' ?>>Inactif</option>
      <option value="SUSPENDU" <?= $filtres['statut'] === 'SUSPENDU' ? 'selected' : '' ?>>Suspendu</option>
      <option value="ARCHIVE"  <?= $filtres['statut'] === 'ARCHIVE'  ? 'selected' : '' ?>>Archivé</option>
    </select>

    <select class="form-control" style="width:auto;min-width:170px;" id="filtreDept" onchange="filtrerUtilisateurs()">
      <option value="">Tous départements</option>
      <option value="DIRECTION"           <?= $filtres['departement'] === 'DIRECTION'           ? 'selected' : '' ?>>Direction</option>
      <option value="ADMIN_GENERALE"      <?= $filtres['departement'] === 'ADMIN_GENERALE'      ? 'selected' : '' ?>>Admin. Générale</option>
      <option value="ADMIN_FINANCIERE"    <?= $filtres['departement'] === 'ADMIN_FINANCIERE'    ? 'selected' : '' ?>>Admin. Financière</option>
      <option value="RECHERCHE_SCIENTIFIQUE" <?= $filtres['departement'] === 'RECHERCHE_SCIENTIFIQUE' ? 'selected' : '' ?>>Recherche Scientifique</option>
    </select>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table class="crict-table">
      <thead>
        <tr>
          <th>Utilisateur</th>
          <th>Matricule</th>
          <th>Groupe / Département</th>
          <th>Grade</th>
          <th>Statut</th>
          <th>Dernière connexion</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($result['data'])): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:40px;">
            <i class="fa-solid fa-users-slash" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i>
            Aucun utilisateur trouvé
          </td></tr>
        <?php else: ?>
          <?php foreach ($result['data'] as $u): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar-initials">
                  <?= e(mb_strtoupper(mb_substr($u['prenoms'],0,1) . mb_substr($u['nom'],0,1))) ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:.85rem;"><?= e($u['prenoms'] . ' ' . $u['nom']) ?></div>
                  <div style="font-size:.75rem;color:var(--text-muted);"><?= e($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <code style="font-size:.78rem;background:var(--bg);padding:3px 8px;border-radius:5px;color:var(--blue-700);">
                <?= e($u['matricule']) ?>
              </code>
            </td>
            <td>
              <div style="font-size:.83rem;font-weight:600;"><?= e($u['groupe_libelle']) ?></div>
              <div style="font-size:.73rem;color:var(--text-muted);"><?= e(str_replace('_', ' ', $u['departement'])) ?></div>
            </td>
            <td style="font-size:.82rem;color:var(--text-muted);"><?= e($u['grade'] ?? '—') ?></td>
            <td><?= badge_statut($u['statut']) ?></td>
            <td style="font-size:.78rem;color:var(--text-muted);">
              <?= $u['derniere_connexion'] ? format_datetime($u['derniere_connexion']) : 'Jamais' ?>
            </td>
            <td style="text-align:center;">
              <div style="display:flex;gap:5px;justify-content:center;">
                <a href="voir.php?id=<?= $u['id'] ?>" class="btn btn-secondary btn-icon btn-sm" title="Voir">
                  <i class="fa-solid fa-eye"></i>
                </a>
                <?php if (a_permission('UTILISATEURS','MODIFIER')): ?>
                <button class="btn btn-secondary btn-icon btn-sm" title="Modifier"
                        onclick="ouvrirEdition(<?= $u['id'] ?>)">
                  <i class="fa-solid fa-pen"></i>
                </button>
                <?php endif; ?>
                <?php if (a_permission('UTILISATEURS','MODIFIER') && $u['statut'] === 'ACTIF' && $u['id'] !== user_id()): ?>
                <button class="btn btn-icon btn-sm" title="Suspendre"
                        style="background:#fef3c7;color:#d97706;border:1px solid #fde68a;"
                        onclick="changerStatut(<?= $u['id'] ?>, 'SUSPENDU', '<?= e($u['prenoms'].' '.$u['nom']) ?>')">
                  <i class="fa-solid fa-user-slash"></i>
                </button>
                <?php elseif (a_permission('UTILISATEURS','MODIFIER') && $u['statut'] === 'SUSPENDU'): ?>
                <button class="btn btn-icon btn-sm" title="Réactiver"
                        style="background:#dcfce7;color:#16a34a;border:1px solid #bbf7d0;"
                        onclick="changerStatut(<?= $u['id'] ?>, 'ACTIF', '<?= e($u['prenoms'].' '.$u['nom']) ?>')">
                  <i class="fa-solid fa-user-check"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pagination['nb_pages'] > 1): ?>
  <div class="pagination">
    <?php if ($pagination['a_precedente']): ?>
      <a href="?page=<?= $pagination['page']-1 ?>&<?= http_build_query($filtres) ?>" class="page-btn">
        <i class="fa-solid fa-chevron-left"></i>
      </a>
    <?php endif; ?>
    <?php for ($p = max(1, $pagination['page']-2); $p <= min($pagination['nb_pages'], $pagination['page']+2); $p++): ?>
      <a href="?page=<?= $p ?>&<?= http_build_query($filtres) ?>" class="page-btn <?= $p === $pagination['page'] ? 'active' : '' ?>">
        <?= $p ?>
      </a>
    <?php endfor; ?>
    <?php if ($pagination['a_suivante']): ?>
      <a href="?page=<?= $pagination['page']+1 ?>&<?= http_build_query($filtres) ?>" class="page-btn">
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    <?php endif; ?>
    <div class="pagination-info">
      Page <?= $pagination['page'] ?> / <?= $pagination['nb_pages'] ?> ·
      <?= $result['total'] ?> résultat<?= $result['total'] > 1 ? 's' : '' ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== MODAL CRÉER UTILISATEUR ===== -->
<?php if (a_permission('UTILISATEURS','CREER')): ?>
<div class="modal-overlay" id="modalCreer">
  <div class="modal modal-lg">
    <div class="modal-header">
      <i class="fa-solid fa-user-plus" style="color:var(--blue-500);"></i>
      <h3>Nouvel utilisateur</h3>
      <button class="modal-close" onclick="document.getElementById('modalCreer').classList.remove('open')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions.php">
      <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="creer">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input type="text" name="nom" class="form-control" placeholder="Nom de famille" required>
          </div>
          <div class="form-group">
            <label class="form-label">Prénom(s) *</label>
            <input type="text" name="prenoms" class="form-control" placeholder="Prénom(s)" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" placeholder="email@crict.edu.gn" required>
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="telephone" class="form-control" placeholder="+224 6XX XXX XXX">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Groupe / Rôle *</label>
            <select name="groupe_id" class="form-control" required>
              <option value="">— Sélectionner —</option>
              <?php foreach ($groupes as $g): ?>
                <option value="<?= $g['id'] ?>"><?= e($g['libelle']) ?> (<?= e($g['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="genre" class="form-control">
              <option value="">— Sélectionner —</option>
              <option value="M">Masculin</option>
              <option value="F">Féminin</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Grade / Diplôme</label>
            <input type="text" name="grade" class="form-control" placeholder="Ex: Doctorat, Master, Licence">
          </div>
          <div class="form-group">
            <label class="form-label">Spécialité</label>
            <input type="text" name="specialite" class="form-control" placeholder="Ex: Cybersécurité, IA…">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date de naissance</label>
            <input type="date" name="date_naissance" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Date prise de poste</label>
            <input type="date" name="date_prise_poste" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mot de passe initial *</label>
          <input type="text" name="mot_de_passe" class="form-control"
                 placeholder="Mot de passe temporaire (l'utilisateur devra le changer)"
                 value="CRICT@<?= date('Y') ?>" required>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px;">
            <i class="fa-solid fa-info-circle"></i>
            L'utilisateur sera obligé de changer son mot de passe à la première connexion.
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Adresse</label>
          <textarea name="adresse" class="form-control" rows="2" placeholder="Adresse complète…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                onclick="document.getElementById('modalCreer').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-user-plus"></i> Créer l'utilisateur
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal Confirmation statut -->
<div class="modal-overlay" id="modalStatut">
  <div class="modal" style="max-width:420px;">
    <div class="modal-header">
      <i class="fa-solid fa-triangle-exclamation" style="color:var(--warn);"></i>
      <h3 id="modalStatutTitre">Confirmer l'action</h3>
      <button class="modal-close" onclick="document.getElementById('modalStatut').classList.remove('open')">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <form method="POST" action="actions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="changer_statut">
      <input type="hidden" name="id" id="statutUserId">
      <input type="hidden" name="statut" id="statutValeur">
      <div class="modal-body">
        <p id="modalStatutMsg" style="color:var(--text);"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary"
                onclick="document.getElementById('modalStatut').classList.remove('open')">Annuler</button>
        <button type="submit" class="btn btn-warn">Confirmer</button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_scripts = <<<JS
<script>
function filtrerUtilisateurs() {
  clearTimeout(window._filterTimer);
  window._filterTimer = setTimeout(() => {
    const q      = document.getElementById('searchInput').value;
    const groupe = document.getElementById('filtreGroupe').value;
    const statut = document.getElementById('filtreStatut').value;
    const dept   = document.getElementById('filtreDept').value;
    window.location.href = '?' + new URLSearchParams({q, groupe, statut, dept, page:1}).toString();
  }, 400);
}

function changerStatut(id, statut, nom) {
  document.getElementById('statutUserId').value = id;
  document.getElementById('statutValeur').value  = statut;
  const labels = {SUSPENDU:'Suspendre', ACTIF:'Réactiver', ARCHIVE:'Archiver'};
  document.getElementById('modalStatutTitre').textContent = labels[statut] + ' l\'utilisateur';
  document.getElementById('modalStatutMsg').innerHTML =
    'Êtes-vous sûr de vouloir <strong>' + labels[statut].toLowerCase() + '</strong> l\'utilisateur <strong>' + nom + '</strong> ?';
  document.getElementById('modalStatut').classList.add('open');
}

function ouvrirEdition(id) {
  window.location.href = 'modifier.php?id=' + id;
}
</script>
JS;
include __DIR__ . '/../../views/layout/footer.php';
?>
