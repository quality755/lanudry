<?php
declare(strict_types=1);

function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['r' => $route], $params));
}

function asset(string $path): string
{
    return 'assets/' . ltrim($path, '/') . '?v=1.0.4';
}

function redirect(string $route = 'dashboard', array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function redirect_back(string $fallback = 'dashboard'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && str_contains($ref, 'index.php')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function set_old(array $input): void
{
    $_SESSION['old'] = $input;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['old'][$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function old_array(string $key): array
{
    $value = $_SESSION['old'][$key] ?? [];
    return is_array($value) ? $value : [];
}

function error_bag(): array
{
    return $_SESSION['errors'] ?? [];
}

function set_errors(array $errors): void
{
    $_SESSION['errors'] = $errors;
}

function input(array $source, string $key, string $default = ''): string
{
    $value = $source[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function post(string $key, string $default = ''): string
{
    return input($_POST, $key, $default);
}

function query(string $key, string $default = ''): string
{
    return input($_GET, $key, $default);
}

function to_float(mixed $value): float
{
    if (is_string($value)) {
        $value = str_replace([',', ' '], '', $value);
    }
    return is_numeric($value) ? (float) $value : 0.0;
}

function money(mixed $amount, bool $withCurrency = true): string
{
    $formatted = number_format((float) $amount, 2, '.', ',');
    return $withCurrency ? config('currency') . ' ' . $formatted : $formatted;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

function fmt_date(?string $value, string $format = 'd M Y'): string
{
    if ($value === null || $value === '' || str_starts_with($value, '0000')) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '—';
}

function fmt_datetime(?string $value): string
{
    return fmt_date($value, 'd M Y · H:i');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts));
    if ($parts === []) {
        return '?';
    }
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function status_meta(string $status): array
{
    return match ($status) {
        'received'   => ['label' => 'Received', 'tone' => 'blue', 'step' => 1],
        'processing' => ['label' => 'Processing', 'tone' => 'orange', 'step' => 2],
        'ready'      => ['label' => 'Ready', 'tone' => 'yellow', 'step' => 3],
        'delivered'  => ['label' => 'Delivered', 'tone' => 'mint', 'step' => 4],
        'cancelled'  => ['label' => 'Cancelled', 'tone' => 'red', 'step' => 0],
        default      => ['label' => ucfirst($status), 'tone' => 'ink', 'step' => 0],
    };
}

function balance_meta(float $total, float $paid): array
{
    $balance = round($total - $paid, 2);
    if ($balance <= 0.009) {
        return ['label' => 'Paid', 'tone' => 'mint', 'balance' => max(0.0, $balance)];
    }
    if ($paid > 0.009) {
        return ['label' => 'Partial', 'tone' => 'orange', 'balance' => $balance];
    }
    return ['label' => 'Unpaid', 'tone' => 'red', 'balance' => $balance];
}

function tone_class(string $tone, string $prefix = 'pill'): string
{
    return $prefix . ' ' . $prefix . '--' . $tone;
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    return [
        'page'   => $page,
        'pages'  => $pages,
        'offset' => $offset,
        'total'  => $total,
        'from'   => $total === 0 ? 0 : $offset + 1,
        'to'     => min($total, $offset + $perPage),
        'per'    => $perPage,
    ];
}

function current_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);
    return 'index.php?' . http_build_query($params);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $value): string
{
    return $value ? ' checked' : '';
}

function array_get(array $arr, string $key, mixed $default = null): mixed
{
    return $arr[$key] ?? $default;
}

/* ----------------------------------------------------------------------
 | Inline icon set — chunky square stroke, deliberately unrefined
 | -------------------------------------------------------------------- */

function icon(string $name, int $size = 20, string $class = ''): string
{
    static $paths = [
        'grid'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20c0-3.2 2.6-5.2 6.2-5.2s6.2 2 6.2 5.2"/><path d="M16.2 5.4a3.2 3.2 0 010 5.6"/><path d="M18.4 20c0-2.7-1-4.4-2.6-5"/>',
        'tag'      => '<path d="M20.5 13.2l-7.3 7.3-9.4-9.4V3.5h7.6z"/><path d="M7.6 7.6h.02"/>',
        'basket'   => '<path d="M3.5 8h17l-1.6 12.5H5.1z"/><path d="M8 8V6.2a4 4 0 018 0V8"/>',
        'cash'     => '<rect x="2.5" y="6" width="19" height="12"/><circle cx="12" cy="12" r="3"/><path d="M6 10v4M18 10v4"/>',
        'badge'    => '<rect x="3" y="4" width="18" height="16"/><circle cx="9" cy="10" r="2.2"/><path d="M5.4 17c.6-2 2-3 3.6-3s3 1 3.6 3"/><path d="M15 9h4M15 14h4"/>',
        'chart'    => '<path d="M3 20.5h18"/><rect x="5" y="11" width="3.5" height="7"/><rect x="10.5" y="6.5" width="3.5" height="11.5"/><rect x="16" y="3.5" width="3.5" height="14.5"/>',
        'gear'     => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.5V6M12 18v3.5M2.5 12H6M18 12h3.5M5.2 5.2l2.4 2.4M16.4 16.4l2.4 2.4M18.8 5.2l-2.4 2.4M7.6 16.4l-2.4 2.4"/>',
        'plus'     => '<path d="M12 4v16M4 12h16"/>',
        'minus'    => '<path d="M4 12h16"/>',
        'search'   => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5l5 5"/>',
        'edit'     => '<path d="M4 20h4l11-11-4-4L4 16z"/><path d="M14.5 5.5l4 4"/>',
        'trash'    => '<path d="M4 7h16"/><path d="M9.5 7V4.5h5V7"/><path d="M6.5 7l1 13h9l1-13"/>',
        'logout'   => '<path d="M14 4H5v16h9"/><path d="M17.5 8l4 4-4 4"/><path d="M10 12h11"/>',
        'printer'  => '<path d="M7 9V3.5h10V9"/><rect x="3.5" y="9" width="17" height="8"/><path d="M7 14h10v6.5H7z"/>',
        'check'    => '<path d="M4 13l5 5L20 6"/>',
        'x'        => '<path d="M5 5l14 14M19 5L5 19"/>',
        'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.5l4 2.5"/>',
        'bolt'     => '<path d="M13.5 2.5L5 14h5l-1 8 9-12h-5.5z"/>',
        'arrow'    => '<path d="M4 12h15"/><path d="M13 6l6 6-6 6"/>',
        'phone'    => '<path d="M6 3.5h3l1.5 4-2 1.5a12 12 0 006 6l1.5-2 4 1.5v3c0 1-.8 2-2 2A17 17 0 013.5 5.5c0-1.2.9-2 2.5-2z"/>',
        'mail'     => '<rect x="2.5" y="5" width="19" height="14"/><path d="M3 6l9 7 9-7"/>',
        'pin'      => '<path d="M12 21s7-6.3 7-11.2A7 7 0 005 9.8C5 14.7 12 21 12 21z"/><circle cx="12" cy="9.8" r="2.6"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="16.5"/><path d="M3 10h18M8 2.5v4M16 2.5v4"/>',
        'receipt'  => '<path d="M5 2.5h14v19l-3.5-2.2L12 21.5l-3.5-2.2L5 21.5z"/><path d="M8.5 8h7M8.5 12h7M8.5 16h4"/>',
        'filter'   => '<path d="M3 5h18l-7 8v7l-4-2v-5z"/>',
        'download' => '<path d="M12 3.5v11"/><path d="M7.5 10l4.5 4.5 4.5-4.5"/><path d="M4 20.5h16"/>',
        'box'      => '<path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9"/>',
        'star'     => '<path d="M12 3l2.7 5.7 6.3.8-4.6 4.3 1.2 6.2-5.6-3.1-5.6 3.1 1.2-6.2L3 9.5l6.3-.8z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c0-4 3.4-6.2 7.5-6.2s7.5 2.2 7.5 6.2"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'chevron'  => '<path d="M9 5l7 7-7 7"/>',
        'water'    => '<path d="M12 2.5s6 7 6 11a6 6 0 01-12 0c0-4 6-11 6-11z"/><path d="M9.5 14.5c0 1.5 1 2.5 2.5 2.5"/>',
        'scissors' => '<circle cx="6.5" cy="17.5" r="3"/><circle cx="6.5" cy="6.5" r="3"/><path d="M9 8.5L20 19M9 15.5L20 5"/>',
    ];

    $body = $paths[$name] ?? $paths['box'];
    return sprintf(
        '<svg class="icon %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">%s</svg>',
        e($class),
        $size,
        $size,
        $body
    );
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        flash('error', 'Security token expired. Try that again.');
        redirect_back('dashboard');
    }
}

function log_activity(string $action, string $detail = ''): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([auth_user()['id'] ?? null, $action, mb_substr($detail, 0, 240), now()]);
    } catch (Throwable) {
        // activity logging must never break a request
    }
}

function next_code(string $table, string $column, string $prefix): string
{
    $stmt = db()->query("SELECT {$column} FROM {$table} ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $next = 1;
    if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
        $next = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function csv_download(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
