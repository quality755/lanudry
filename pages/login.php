<?php
declare(strict_types=1);

$email = old('email');
$customerCount = 0;
$orderCount = 0;
try {
    $customerCount = (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
} catch (Throwable) {
    $customerCount = 0;
    $orderCount = 0;
}
?>
<div class="auth-card">
    <section class="auth-brand">
        <span class="auth-logo"><?= icon('water', 34) ?></span>
        <h1>Clean clothes.<br><span>Zero chaos.</span></h1>
        <p>
            Orders in. Stains out. This is the control room for
            <?= e(setting('business_name', (string) config('name'))) ?> —
            customers, tickets, cash and the crew, all on one loud screen.
        </p>
        <div class="auth-facts">
            <div class="auth-fact"><b><?= (int) $orderCount ?></b><span>Orders on file</span></div>
            <div class="auth-fact"><b><?= (int) $customerCount ?></b><span>Customers</span></div>
            <div class="auth-fact"><b>4</b><span>Status stages</span></div>
        </div>
        <p class="mono small">WASH · PRESS · FOLD · DELIVER</p>
    </section>

    <form class="auth-form" method="post" action="<?= e(url('login')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">

        <p class="eyebrow">Staff access</p>
        <h2>Sign in</h2>

        <div class="field<?= error_bag()['email'] ?? false ? ' has-error' : '' ?>">
            <label for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
                   placeholder="you@laundry.test" autocomplete="username" required>
            <?php if (!empty(error_bag()['email'])): ?>
                <span class="field__error"><?= e(error_bag()['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password"
                   placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <button class="btn btn--primary btn--block" type="submit">
            <?= icon('check', 18) ?><span>Enter the laundry</span>
        </button>

        <div class="demo-creds">
            <p class="eyebrow">Demo logins</p>
            <p><strong>admin@laundry.test</strong> / admin123 <span class="tag">Admin</span></p>
            <p><strong>staff@laundry.test</strong> / staff123 <span class="tag tag--plain">Staff</span></p>
        </div>

        <p class="auth-foot">
            Problems signing in? Call the shop on <?= e(setting('business_phone', '+254 700 000 000')) ?>.
        </p>
    </form>
</div>
