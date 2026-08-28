<?php
// ============================================================
// includes/helpers.php  — Fonctions utilitaires
// ============================================================

// --- Sécurité HTML ---
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// --- Redirection sécurisée ---
function redirect(string $url): void {
    header('Location: ' . APP_URL . $url);
    exit;
}

// --- CSRF Token ---
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// --- Hash mot de passe ---
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// --- Messages flash ---
function set_flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// --- Nettoyage des entrées ---
function clean(mixed $val): string {
    if (is_array($val)) return '';
    return trim(strip_tags((string)$val));
}

// --- Pagination ---
function paginer(int $total, int $par_page, int $page_actuelle): array {
    $nb_pages = max(1, (int)ceil($total / $par_page));
    $page_actuelle = max(1, min($page_actuelle, $nb_pages));
    $offset = ($page_actuelle - 1) * $par_page;
    return [
        'total'        => $total,
        'par_page'     => $par_page,
        'page'         => $page_actuelle,
        'nb_pages'     => $nb_pages,
        'offset'       => $offset,
        'a_precedente' => $page_actuelle > 1,
        'a_suivante'   => $page_actuelle < $nb_pages,
    ];
}

// --- Log activité ---
function log_activite(
    ?int $user_id,
    string $module,
    string $action,
    string $description = '',
    string $statut = 'SUCCES',
    ?array $avant = null,
    ?array $apres = null
): void {
    try {
        $db = get_db();
        $sql = "INSERT INTO logs_activites
                (utilisateur_id, module, action, description, ip, user_agent, donnees_avant, donnees_apres, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([
            $user_id,
            $module,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $avant ? json_encode($avant, JSON_UNESCAPED_UNICODE) : null,
            $apres  ? json_encode($apres,  JSON_UNESCAPED_UNICODE) : null,
            $statut,
        ]);
    } catch (Throwable $e) {
        error_log('[CRICT LOG] ' . $e->getMessage());
    }
}

// --- Formatage date ---
function format_date(string $date, string $format = 'd/m/Y'): string {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format($format) : $date;
}

function format_datetime(string $dt): string {
    $d = new DateTime($dt);
    return $d->format('d/m/Y H:i');
}

// --- Durée en texte ---
function minutes_en_texte(int $minutes): string {
    if ($minutes < 60) return "{$minutes} min";
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m > 0 ? "{$h}h{$m}min" : "{$h}h";
}

// --- Badge HTML statut ---
function badge_statut(string $statut): string {
    $map = [
        'ACTIF'       => ['bg' => '#22c55e', 'label' => 'Actif'],
        'INACTIF'     => ['bg' => '#94a3b8', 'label' => 'Inactif'],
        'SUSPENDU'    => ['bg' => '#ef4444', 'label' => 'Suspendu'],
        'ARCHIVE'     => ['bg' => '#78716c', 'label' => 'Archivé'],
        'BROUILLON'   => ['bg' => '#f59e0b', 'label' => 'Brouillon'],
        'SOUMISE'     => ['bg' => '#3b82f6', 'label' => 'Soumise'],
        'VALIDEE'     => ['bg' => '#22c55e', 'label' => 'Validée'],
        'REJETEE'     => ['bg' => '#ef4444', 'label' => 'Rejetée'],
    ];
    $info = $map[$statut] ?? ['bg' => '#64748b', 'label' => $statut];
    return '<span style="background:' . $info['bg'] . ';color:#fff;padding:2px 10px;border-radius:20px;font-size:.78rem;font-weight:600;">'
         . e($info['label']) . '</span>';
}

// --- Formatage date en français long ---
function strftime_fr(string $date): string {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août',
               'Septembre','Octobre','Novembre','Décembre'];
    $d = new DateTime($date);
    return $jours[(int)$d->format('w')] . ' '
         . $d->format('d') . ' '
         . $mois[(int)$d->format('n')] . ' '
         . $d->format('Y');
}
