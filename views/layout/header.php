<?php
declare(strict_types=1);

$layout = $layout ?? 'app';
$pageTitle = $pageTitle ?? 'Dashboard';
$route = $route ?? 'dashboard';
$user = auth_user();
$flashes = take_flashes();
$brand = setting('business_name', (string) config('name'));
$currency = (string) config('currency');
$activeOrders = 0;
try {
    $counts = status_counts();
    $activeOrders = $counts['received'] + $counts['processing'] + $counts['ready'];
} catch (Throwable) {
    $activeOrders = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e($brand) ?></title>
    <meta name="theme-color" content="#0b0b0f">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="layout-<?= e($layout) ?>">
<?php if ($layout === 'app'): ?>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar__brand" href="<?= e(url('dashboard')) ?>">
            <span class="brand-mark" aria-hidden="true"><?= icon('water', 26) ?></span>
            <span class="brand-text">
                <strong>Spin City</strong>
                <em>Laundry Control</em>
            </span>
            <span class="sticker sticker--rotate">EST. 2019</span>
        </a>

        <nav class="sidebar__nav" aria-label="Main">
            <?php foreach (nav_items() as [$key, $label, $icon]): ?>
                <?php if ($key === 'settings' && !is_admin()) {
                    continue;
                } ?>
                <a class="nav-link<?= route_is_active($route, $key) ? ' is-active' : '' ?>" href="<?= e(url($key)) ?>">
                    <span class="nav-link__icon"><?= icon($icon, 20) ?></span>
                    <span class="nav-link__label"><?= e($label) ?></span>
                    <?php if ($key === 'orders' && $activeOrders > 0): ?>
                        <span class="nav-link__badge"><?= (int) $activeOrders ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__foot">
            <div class="stamp">
                <span class="stamp__line"><?= e($currency) ?></span>
                <span class="stamp__line stamp__line--big">No.1</span>
                <span class="stamp__line">Clean crew</span>
            </div>
            <p class="sidebar__note">Wash. Press. Fold.<br>Repeat, loudly.</p>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="btn btn--icon btn--ghost menu-toggle" data-menu-toggle aria-label="Toggle menu">
                <?= icon('menu', 22) ?>
            </button>
            <div class="topbar__title">
                <p class="eyebrow"><?= e($brand) ?> <span aria-hidden="true">/</span> <?= e($pageTitle) ?></p>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="topbar__actions">
                <a class="btn btn--primary" href="<?= e(url('orders/new')) ?>">
                    <?= icon('plus', 18) ?><span>New order</span>
                </a>
                <div class="user-chip">
                    <span class="avatar" aria-hidden="true"><?= e(initials($user['name'] ?? 'U')) ?></span>
                    <span class="user-chip__meta">
                        <strong><?= e($user['name'] ?? 'Guest') ?></strong>
                        <em><?= e(ucfirst($user['role'] ?? 'staff')) ?></em>
                    </span>
                    <form method="post" action="<?= e(url('dashboard')) ?>" class="user-chip__form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="logout">
                        <button class="btn btn--icon btn--ghost" type="submit" title="Sign out" aria-label="Sign out">
                            <?= icon('logout', 18) ?>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="content">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash--<?= e($flash['type']) ?>" role="status" data-flash>
                    <span class="flash__icon"><?= icon($flash['type'] === 'error' ? 'x' : 'check', 18) ?></span>
                    <span class="flash__text"><?= e($flash['message']) ?></span>
                    <button type="button" class="flash__close" data-dismiss aria-label="Dismiss">×</button>
                </div>
            <?php endforeach; ?>
<?php elseif ($layout === 'auth'): ?>
    <main class="auth-shell">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash flash--<?= e($flash['type']) ?> flash--floating" role="status" data-flash>
                <span class="flash__icon"><?= icon($flash['type'] === 'error' ? 'x' : 'check', 18) ?></span>
                <span class="flash__text"><?= e($flash['message']) ?></span>
                <button type="button" class="flash__close" data-dismiss aria-label="Dismiss">×</button>
            </div>
        <?php endforeach; ?>
<?php else: ?>
    <main class="print-shell">
<?php endif; ?>
