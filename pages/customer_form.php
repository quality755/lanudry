<?php
declare(strict_types=1);

$id = (int) query('id', '0');
$isEdit = $id > 0;
$customer = null;

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $customer = $stmt->fetch() ?: null;
    if ($customer === null) {
        flash('error', 'That customer is not on file.');
        redirect('customers');
    }
}

$errors = error_bag();
$value = static function (string $key, string $default = '') use ($customer): string {
    return old($key, (string) ($customer[$key] ?? $default));
};
$totals = $isEdit ? customer_totals($id) : ['orders' => 0, 'spend' => 0, 'active' => 0];
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= $isEdit ? e((string) $customer['code']) : 'New record' ?></p>
        <h2><?= $isEdit ? 'Edit customer' : 'Add customer' ?></h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e($isEdit ? url('customers/show', ['id' => $id]) : url('customers')) ?>">Cancel</a>
    </div>
</section>

<div class="grid grid--main">
    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Contact record</p>
                <h2>Details</h2>
            </div>
        </div>
        <form method="post" action="<?= e($isEdit ? url('customers/edit', ['id' => $id]) : url('customers/new')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="customer.save">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field field--full<?= isset($errors['name']) ? ' has-error' : '' ?>">
                        <label for="name">Full name</label>
                        <input class="input" type="text" id="name" name="name" value="<?= e($value('name')) ?>" placeholder="Amina Yusuf" required>
                        <?php if (isset($errors['name'])): ?>
                            <span class="field__error"><?= e($errors['name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                        <label for="phone">Phone</label>
                        <input class="input" type="tel" id="phone" name="phone" value="<?= e($value('phone')) ?>" placeholder="+254 700 123 456">
                        <?php if (isset($errors['phone'])): ?>
                            <span class="field__error"><?= e($errors['phone']) ?></span>
                        <?php endif; ?>
                        <span class="field__hint">Used to call when the load is ready.</span>
                    </div>
                    <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                        <label for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" value="<?= e($value('email')) ?>" placeholder="amina@example.com">
                        <?php if (isset($errors['email'])): ?>
                            <span class="field__error"><?= e($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="field field--full">
                        <label for="address">Address / pick-up point</label>
                        <input class="input" type="text" id="address" name="address" value="<?= e($value('address')) ?>" placeholder="Block C, Riverside Apartments">
                    </div>
                    <div class="field field--full">
                        <label for="notes">Internal notes</label>
                        <textarea class="textarea" id="notes" name="notes" rows="3" placeholder="Allergic to bleach, prefers unscented detergent..."><?= e($value('notes')) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card__foot">
                <div class="btn-row">
                    <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?><span><?= $isEdit ? 'Save changes' : 'Create customer' ?></span></button>
                    <a class="btn btn--ghost" href="<?= e($isEdit ? url('customers/show', ['id' => $id]) : url('customers')) ?>">Discard</a>
                </div>
            </div>
        </form>
    </section>

    <div class="stack">
        <?php if ($isEdit): ?>
            <section class="card card--yellow">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">History</p>
                        <h2>Value</h2>
                    </div>
                </div>
                <div class="card__body stack--sm">
                    <div class="row row--between"><span>Orders</span><span class="mono"><?= (int) $totals['orders'] ?></span></div>
                    <div class="row row--between"><span>Lifetime spend</span><span class="mono"><?= e(money($totals['spend'])) ?></span></div>
                    <div class="row row--between"><span>Active tickets</span><span class="mono"><?= (int) $totals['active'] ?></span></div>
                </div>
                <div class="card__foot">
                    <a class="btn btn--ink btn--block" href="<?= e(url('orders/new')) ?>"><?= icon('plus', 16) ?><span>Start an order</span></a>
                </div>
            </section>

            <section class="card card--flat">
                <div class="card__body">
                    <form method="post" action="<?= e(url('customers/show', ['id' => $id])) ?>" data-confirm="Delete <?= e((string) $customer['name']) ?> and every order attached?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="customer.delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn--danger btn--block" type="submit"><?= icon('trash', 16) ?><span>Delete customer</span></button>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <section class="card card--flat">
                <div class="card__body">
                    <p class="eyebrow">Good to know</p>
                    <p class="small">
                        A customer code is generated automatically. Phone numbers are the fastest way to
                        find someone at the counter, so fill it in if you can.
                    </p>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
