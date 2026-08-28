<?php
// modules/personnel/creer.php — Nouvel employé
//   Deux modes :
//   (A) Lier un utilisateur EXISTANT  -> on complète seulement ses données RH
//       (l'identité provient de la table `utilisateurs` et fait foi)
//   (B) Créer un compte depuis zéro   -> ancien comportement (compte + profil)
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../models/utilisateur.php';
require_once __DIR__ . '/../../models/personnel.php';

session_demarrer();
exiger_permission('PERSONNEL', 'CREER');

$peut_sensible = a_permission('PERSONNEL', 'MODIFIER_SENSIBLE');
$groupes       = groupes_lister();

$contrats   = ['CDD' => 'CDD', 'CDI' => 'CDI', 'STAGIAIRE' => 'Stagiaire', 'CONSULTANT' => 'Consultant', 'VACATAIRE' => 'Vacataire'];
$situations = ['CELIBATAIRE' => 'Célibataire', 'MARIE' => 'Marié(e)', 'DIVORCE' => 'Divorcé(e)', 'VEUF' => 'Veuf(ve)'];

// ── Liste des utilisateurs sélectionnables (pour le mode A) ──
//    On charge les champs nécessaires à l'auto-remplissage de la section identité,
//    et on repère si le profil RH est déjà renseigné (à compléter vs déjà rempli).
$utilisateurs = get_db()->query("
    SELECT u.id, u.groupe_id, u.matricule, u.nom, u.prenoms, u.email, u.telephone,
           u.genre, u.date_naissance, u.adresse, u.grade, u.specialite, u.date_prise_poste,
           g.libelle AS groupe_libelle, g.code AS groupe_code,
           p.fonction, p.matricule_rh, p.date_embauche
    FROM utilisateurs u
    JOIN groupes_utilisateurs g ON g.id = u.groupe_id
    LEFT JOIN personnel p       ON p.utilisateur_id = u.id
    WHERE u.statut <> 'ARCHIVE'
    ORDER BY u.nom, u.prenoms
")->fetchAll();

// Map JSON id -> données identité (consommé par le JS pour l'auto-remplissage)
$users_json = [];
foreach ($utilisateurs as $u) {
    $users_json[(int)$u['id']] = [
        'groupe_id'        => (int)$u['groupe_id'],
        'groupe_libelle'   => $u['groupe_libelle'],
        'nom'              => $u['nom'],
        'prenoms'          => $u['prenoms'],
        'email'            => $u['email'],
        'telephone'        => $u['telephone'] ?? '',
        'genre'            => $u['genre'] ?? '',
        'date_naissance'   => $u['date_naissance'] ?? '',
        'adresse'          => $u['adresse'] ?? '',
        'grade'            => $u['grade'] ?? '',
        'specialite'       => $u['specialite'] ?? '',
        'date_prise_poste' => $u['date_prise_poste'] ?? '',
    ];
}

// ── Traitement ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        set_flash('error', 'Session expirée. Réessayez.');
        redirect('/modules/personnel/creer.php');
    }

    $utilisateur_id = (int)($_POST['utilisateur_id'] ?? 0);

    // Champs RH communs aux deux modes
    $data_rh = [
        'matricule_rh'           => clean($_POST['matricule_rh'] ?? ''),
        'lieu_naissance'         => clean($_POST['lieu_naissance'] ?? ''),
        'nationalite'            => clean($_POST['nationalite'] ?? ''),
        'situation_matrimoniale' => clean($_POST['situation_matrimoniale'] ?? ''),
        'nombre_enfants'         => (int)($_POST['nombre_enfants'] ?? 0),
        'fonction'               => clean($_POST['fonction'] ?? ''),
        'date_embauche'          => clean($_POST['date_embauche'] ?? ''),
        'type_contrat'           => clean($_POST['type_contrat'] ?? ''),
    ];
    $statut_rh = clean($_POST['statut_rh'] ?? 'ACTIF');

    // ════════════════════════════════════════════════════════════
    //  MODE A — Lier un utilisateur existant
    // ════════════════════════════════════════════════════════════
    if ($utilisateur_id > 0) {
        $u = utilisateur_par_id($utilisateur_id);
        if (!$u) {
            set_flash('error', "Utilisateur introuvable.");
        } else {
            // Profil RH (créé automatiquement avec le compte : on s'en assure)
            $pid = personnel_assurer_profil($utilisateur_id);

            $res = personnel_modifier($pid, $data_rh);
            if (!$res['succes']) {
                set_flash('error', $res['message']);
            } else {
                // Statut RH initial (si autorisé et différent d'ACTIF)
                if ($peut_sensible && $statut_rh !== 'ACTIF') {
                    personnel_changer_statut($pid, $statut_rh);
                }
                set_flash('success', "Profil employé complété pour {$u['prenoms']} {$u['nom']} ({$u['matricule']}).");
                redirect('/modules/personnel/voir.php?id=' . $pid);
            }
        }
    }
    // ════════════════════════════════════════════════════════════
    //  MODE B — Création d'un compte depuis zéro (comportement initial)
    // ════════════════════════════════════════════════════════════
    else {
        // Retrouver le code du groupe (pour le matricule)
        $groupe_id   = (int)($_POST['groupe_id'] ?? 0);
        $groupe_code = 'USR';
        foreach ($groupes as $g) {
            if ((int)$g['id'] === $groupe_id) { $groupe_code = $g['code']; break; }
        }

        $data_user = [
            'groupe_id'        => $groupe_id,
            'groupe_code'      => $groupe_code,
            'nom'              => clean($_POST['nom'] ?? ''),
            'prenoms'          => clean($_POST['prenoms'] ?? ''),
            'email'            => clean($_POST['email'] ?? ''),
            'telephone'        => clean($_POST['telephone'] ?? ''),
            'mot_de_passe'     => $_POST['mot_de_passe'] ?? '',
            'genre'            => clean($_POST['genre'] ?? ''),
            'date_naissance'   => clean($_POST['date_naissance'] ?? ''),
            'adresse'          => clean($_POST['adresse'] ?? ''),
            'grade'            => clean($_POST['grade'] ?? ''),
            'specialite'       => clean($_POST['specialite'] ?? ''),
            'date_prise_poste' => clean($_POST['date_prise_poste'] ?? ''),
            'statut'           => 'ACTIF',
        ];

        $erreurs = [];
        if (!$groupe_id)                                              $erreurs[] = 'Le groupe est obligatoire.';
        if ($data_user['nom'] === '' || $data_user['prenoms'] === '') $erreurs[] = 'Nom et prénoms obligatoires.';
        if (!filter_var($data_user['email'], FILTER_VALIDATE_EMAIL))  $erreurs[] = 'E-mail invalide.';
        if (strlen($data_user['mot_de_passe']) < 8)                   $erreurs[] = 'Mot de passe : 8 caractères minimum.';

        if ($erreurs) {
            set_flash('error', implode(' ', $erreurs));
        } else {
            // 1) Création compte + profil (transaction dans utilisateur_creer)
            $res = utilisateur_creer($data_user);
            if (!$res['succes']) {
                set_flash('error', $res['message']);
            } else {
                $perso = personnel_par_utilisateur((int)$res['id']);
                $pid   = (int)$perso['id'];

                // 2) Champs RH
                personnel_modifier($pid, $data_rh);

                // 3) Statut RH initial (si autorisé et différent d'ACTIF)
                if ($peut_sensible && $statut_rh !== 'ACTIF') {
                    personnel_changer_statut($pid, $statut_rh);
                }

                set_flash('success', "Employé créé (matricule {$res['matricule']}). Mot de passe à changer à la 1ʳᵉ connexion.");
                redirect('/modules/personnel/voir.php?id=' . $pid);
            }
        }
    }
}

$page_titre      = 'Nouvel employé';
$page_breadcrumb = 'RH · Personnel · Création';
$module_actif    = 'PERSONNEL';
include __DIR__ . '/../../views/layout/header.php';
?>
<div style="max-width:900px;">
  <a href="index.php" class="btn btn-secondary btn-sm" style="margin-bottom:16px;"><i class="fa-solid fa-arrow-left"></i> Retour à la liste</a>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="utilisateur_id" id="utilisateur_id" value="">

    <!-- Source : utilisateur existant -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2><i class="fa-solid fa-link" style="color:var(--blue-500);"></i>&nbsp; Lier un utilisateur</h2></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Compte utilisateur existant</label>
          <select id="select_utilisateur" class="form-control">
            <option value="">— Créer un nouveau compte depuis zéro —</option>
            <?php foreach ($utilisateurs as $u):
                $complet = !empty($u['fonction']) || !empty($u['matricule_rh']) || !empty($u['date_embauche']);
                $tag = $complet ? '✓ déjà renseigné' : 'à compléter';
            ?>
              <option value="<?= (int)$u['id'] ?>">
                <?= e($u['prenoms'] . ' ' . $u['nom']) ?> — <?= e($u['matricule']) ?> (<?= $tag ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <small class="form-help" style="display:block;margin-top:6px;color:var(--gray-500);">
            Sélectionnez un utilisateur : sa section <strong>Compte &amp; identité</strong> sera reprise automatiquement
            et vous n'aurez qu'à renseigner ses <strong>données RH</strong>.
            Laissez vide pour créer un tout nouveau compte.
          </small>
        </div>
      </div>
    </div>

    <!-- Compte -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2><i class="fa-solid fa-user" style="color:var(--blue-500);"></i>&nbsp; Compte &amp; identité</h2></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input type="text" name="nom" id="f_nom" class="form-control js-identite" required value="<?= e($_POST['nom'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Prénoms *</label>
            <input type="text" name="prenoms" id="f_prenoms" class="form-control js-identite" required value="<?= e($_POST['prenoms'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">E-mail *</label>
            <input type="email" name="email" id="f_email" class="form-control js-identite" required value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone" id="f_telephone" class="form-control js-identite" placeholder="+224…" value="<?= e($_POST['telephone'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Groupe (rôle / accès) *</label>
            <select name="groupe_id" id="f_groupe_id" class="form-control js-identite" required>
              <option value="">— Sélectionner —</option>
              <?php foreach ($groupes as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= (int)($_POST['groupe_id'] ?? 0)===(int)$g['id']?'selected':'' ?>>
                  <?= e($g['libelle']) ?> (<?= e($g['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="grp_mot_de_passe">
            <label class="form-label">Mot de passe provisoire *</label>
            <input type="text" name="mot_de_passe" id="f_mot_de_passe" class="form-control" required minlength="8" placeholder="8 caractères min.">
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Genre</label>
            <select name="genre" id="f_genre" class="form-control js-identite">
              <option value="">—</option>
              <option value="M">Homme</option>
              <option value="F">Femme</option>
              <option value="AUTRE">Autre</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date de naissance</label>
            <input type="date" name="date_naissance" id="f_date_naissance" class="form-control js-identite" value="<?= e($_POST['date_naissance'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Date de prise de poste</label>
            <input type="date" name="date_prise_poste" id="f_date_prise_poste" class="form-control js-identite" value="<?= e($_POST['date_prise_poste'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Grade</label>
            <input type="text" name="grade" id="f_grade" class="form-control js-identite" placeholder="Doctorat, Master…" value="<?= e($_POST['grade'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Spécialité</label>
            <input type="text" name="specialite" id="f_specialite" class="form-control js-identite" value="<?= e($_POST['specialite'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Adresse</label>
            <input type="text" name="adresse" id="f_adresse" class="form-control js-identite" value="<?= e($_POST['adresse'] ?? '') ?>">
          </div>
        </div>
        <p id="identite_lock_note" style="display:none;margin:8px 0 0;color:var(--blue-600);font-size:13px;">
          <i class="fa-solid fa-lock"></i> Identité reprise du compte sélectionné. Pour la modifier, passez par la fiche utilisateur.
        </p>
      </div>
    </div>

    <!-- RH -->
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header"><h2><i class="fa-solid fa-id-card-clip" style="color:var(--blue-500);"></i>&nbsp; Données RH</h2></div>
      <div class="card-body">
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Fonction</label>
            <input type="text" name="fonction" class="form-control" placeholder="Ingénieur logiciel…" value="<?= e($_POST['fonction'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Matricule RH</label>
            <input type="text" name="matricule_rh" class="form-control" value="<?= e($_POST['matricule_rh'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Type de contrat</label>
            <select name="type_contrat" class="form-control">
              <option value="">—</option>
              <?php foreach ($contrats as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Date d'embauche</label>
            <input type="date" name="date_embauche" class="form-control" value="<?= e($_POST['date_embauche'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Nationalité</label>
            <input type="text" name="nationalite" class="form-control" value="<?= e($_POST['nationalite'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Lieu de naissance</label>
            <input type="text" name="lieu_naissance" class="form-control" value="<?= e($_POST['lieu_naissance'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Situation matrimoniale</label>
            <select name="situation_matrimoniale" class="form-control">
              <option value="">—</option>
              <?php foreach ($situations as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nombre d'enfants</label>
            <input type="number" name="nombre_enfants" class="form-control" min="0" value="<?= e($_POST['nombre_enfants'] ?? '0') ?>">
          </div>
          <?php if ($peut_sensible): ?>
          <div class="form-group">
            <label class="form-label">Statut RH</label>
            <select name="statut_rh" class="form-control">
              <option value="ACTIF">Actif</option>
              <option value="CONGE">En congé</option>
              <option value="MISSION">En mission</option>
              <option value="SUSPENDU">Suspendu</option>
            </select>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <span id="btn_submit_label">Créer l'employé</span></button>
      <a href="index.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>

<script>
(function () {
  // Données identité des utilisateurs, indexées par id
  const USERS = <?= json_encode($users_json, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

  const sel        = document.getElementById('select_utilisateur');
  const hiddenId   = document.getElementById('utilisateur_id');
  const pwdGroup   = document.getElementById('grp_mot_de_passe');
  const pwdInput   = document.getElementById('f_mot_de_passe');
  const lockNote   = document.getElementById('identite_lock_note');
  const btnLabel   = document.getElementById('btn_submit_label');

  // Champs identité (id -> clé dans USERS)
  const MAP = {
    f_nom:'nom', f_prenoms:'prenoms', f_email:'email', f_telephone:'telephone',
    f_groupe_id:'groupe_id', f_genre:'genre', f_date_naissance:'date_naissance',
    f_date_prise_poste:'date_prise_poste', f_grade:'grade', f_specialite:'specialite',
    f_adresse:'adresse'
  };

  function setLocked(locked) {
    document.querySelectorAll('.js-identite').forEach(el => {
      // disabled => non soumis : en mode A l'identité provient du serveur, donc OK
      el.disabled = locked;
      el.style.background = locked ? 'var(--gray-100)' : '';
    });
    lockNote.style.display = locked ? 'block' : 'none';
    // Mot de passe : inutile pour un compte existant
    pwdGroup.style.display = locked ? 'none' : '';
    pwdInput.required      = !locked;
    if (locked) pwdInput.value = '';
    btnLabel.textContent = locked ? "Enregistrer le profil RH" : "Créer l'employé";
  }

  function fill(u) {
    for (const [id, key] of Object.entries(MAP)) {
      const el = document.getElementById(id);
      if (el) el.value = (u[key] ?? '');
    }
  }
  function clearFields() {
    for (const id of Object.keys(MAP)) {
      const el = document.getElementById(id);
      if (el) el.value = '';
    }
  }

  sel.addEventListener('change', function () {
    const id = this.value;
    if (id && USERS[id]) {
      hiddenId.value = id;
      fill(USERS[id]);
      setLocked(true);
    } else {
      hiddenId.value = '';
      clearFields();
      setLocked(false);
    }
  });
})();
</script>

<?php include __DIR__ . '/../../views/layout/footer.php'; ?>