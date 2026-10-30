<?php
declare(strict_types=1);

$settings = [
    'business_name'        => setting('business_name', (string) config('name')),
    'business_phone'       => setting('business_phone'),
    'business_email'       => setting('business_email'),
    'business_address'     => setting('business_address'),
    'receipt_footer'       => setting('receipt_footer'),
    'default_delivery_fee' => setting('default_delivery_fee', '0'),
];

$dbDriver = db_is_mysql() ? 'MySQL' : 'SQLite';
$driverNote = db_is_mysql()
    ? 'Connected to MySQL using DB_HOST / DB_NAME / DB_USER / DB_PASS.'
    : 'No DB_HOST set, so the app is running on its bundled SQLite file at storage/laundry.sqlite.';

$activity = activity_feed(12);
$staffCount = count(employees_list());
$serviceCount = count(services_list());
$customerCount = (int) db()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$paymentCount = (int) db()->query('SELECT COUNT(*) FROM payments')->fetchColumn();
?>
<section class="section-title">
    <div>
        <p class="eyebrow">Admin only</p>
        <h2>Settings</h2>
    </div>
</section>

<div class="grid grid--main">
    <div class="stack">
        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Identity</p>
                    <h2>Business details</h2>
                </div>
            </div>
            <form method="post" action="<?= e(url('settings')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="settings.save">
                <div class="card__body">
                    <div class="stack--sm">
                        <div class="field">
                            <label for="business_name">Business name</label>
                            <input class="input" type="text" id="business_name" name="business_name" value="<?= e($settings['business_name']) ?>">
                        </div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="business_phone">Phone</label>
                                <input class="input" type="tel" id="business_phone" name="business_phone" value="<?= e($settings['business_phone']) ?>">
                            </div>
                            <div class="field">
                                <label for="business_email">Email</label>
                                <input class="input" type="email" id="business_email" name="business_email" value="<?= e($settings['business_email']) ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="business_address">Address</label>
                            <input class="input" type="text" id="business_address" name="business_address" value="<?= e($settings['business_address']) ?>">
                        </div>
                        <div class="field">
                            <label for="receipt_footer">Receipt footer</label>
                            <textarea class="textarea" id="receipt_footer" name="receipt_footer" rows="2"><?= e($settings['receipt_footer']) ?></textarea>
                            <span class="field__hint">Prints at the bottom of every receipt.</span>
                        </div>
                        <div class="field">
                            <label for="default_delivery_fee">Default delivery fee</label>
                            <input class="input input--num" type="number" step="0.01" min="0" id="default_delivery_fee" name="default_delivery_fee" value="<?= e($settings['default_delivery_fee']) ?>">
                            <span class="field__hint">Prefilled on new orders.</span>
                        </div>
                    </div>
                </div>
                <div class="card__foot">
                    <div class="btn-row">
                        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?><span>Save settings</span></button>
                        <a class="btn btn--ghost" href="<?= e(url('dashboard')) ?>">Back to dashboard</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Audit trail</p>
                    <h2>Recent activity</h2>
                </div>
            </div>
            <div class="card__body">
                <ul class="timeline">
                    <?php if ($activity === []): ?>
                        <li class="small">Nothing logged yet.</li>
                    <?php endif; ?>
                    <?php foreach ($activity as $row): ?>
                        <li>
                            <span class="timeline__dot" aria-hidden="true"></span>
                            <div>
                                <p class="strong"><?= e((string) $row['action']) ?><?= $row['detail'] ? ' · ' . e((string) $row['detail']) : '' ?></p>
                                <p class="small"><?= e(fmt_datetime($row['created_at'])) ?><?= $row['user_name'] ? ' · ' . e((string) $row['user_name']) : '' ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card card--ink">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Environment</p>
                    <h2><?= e($dbDriver) ?></h2>
                </div>
            </div>
            <div class="card__body">
                <p class="small"><?= e($driverNote) ?></p>
                <ul class="plain-list">
                    <li><span class="small">PHP</span><span class="mono small"><?= e(PHP_VERSION) ?></span></li>
                    <li><span class="small">Customers</span><span class="mono small"><?= $customerCount ?></span></li>
                    <li><span class="small">Orders</span><span class="mono small"><?= $orderCount ?></span></li>
                    <li><span class="small">Payments</span><span class="mono small"><?= $paymentCount ?></span></li>
                    <li><span class="small">Services</span><span class="mono small"><?= $serviceCount ?></span></li>
                    <li><span class="small">Staff</span><span class="mono small"><?= $staffCount ?></span></li>
                </ul>
            </div>
        </section>

        <section class="card card--yellow">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Demo accounts</p>
                    <h2>Sign-ins</h2>
                </div>
            </div>
            <div class="card__body">
                <ul class="plain-list">
                    <li><span class="small">Admin</span><span class="mono small">admin@laundry.test</span></li>
                    <li><span class="small">Password</span><span class="mono small">admin123</span></li>
                    <li><span class="small">Staff</span><span class="mono small">staff@laundry.test</span></li>
                    <li><span class="small">Password</span><span class="mono small">staff123</span></li>
                </ul>
                <p class="small">Staff accounts cannot delete records or open settings.</p>
            </div>
        </section>
    </div>
</div>
