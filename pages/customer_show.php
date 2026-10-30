<?php
declare(strict_types=1);

$id = (int) query('id', '0');
$stmt = db()->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$customer = $stmt->fetch() ?: null;

if ($customer === null) {
    flash('error', 'That customer is not on file.');
    redirect('customers');
}

$totals = customer_totals($id);
$orderStmt = db()->prepare(
    'SELECT o.*, (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id) AS paid
     FROM orders o WHERE o.customer_id = ? ORDER BY o.created_at DESC LIMIT 20'
);
$orderStmt->execute([$id]);
$orders = $orderStmt->fetchAll();

$owedStmt = db()->prepare(
    'SELECT COALESCE(SUM(MAX(o.total - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id), 0)), 0)
     FROM orders o WHERE o.customer_id = ? AND o.status <> \'cancelled\''
);
$owedStmt->execute([$id]);
$owed = (float) $owedStmt->fetchColumn();
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= e((string) $customer['code']) ?> · joined <?= e(fmt_date($customer['created_at'])) ?></p>
        <h2><?= e($customer['name']) ?></h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e(url('customers')) ?>"><?= icon('arrow', 16) ?><span>All customers</span></a>
        <a class="btn btn--primary" href="<?= e(url('customers/edit', ['id' => $id])) ?>"><?= icon('edit', 16) ?><span>Edit</span></a>
    </div>
</section>

<div class="grid grid--main">
    <div class="stack">
        <div class="grid grid--3">
            <article class="stat stat--yellow">
                <p class="stat__label">Orders</p>
                <p class="stat__value"><?= (int) $totals['orders'] ?></p>
                <p class="stat__foot"><?= (int) $totals['active'] ?> still active</p>
            </article>
            <article class="stat stat--mint">
                <p class="stat__label">Lifetime spend</p>
                <p class="stat__value"><?= e(money($totals['spend'], false)) ?></p>
                <p class="stat__foot">Excludes cancelled</p>
            </article>
            <article class="stat stat--pink">
                <p class="stat__label">Owed</p>
                <p class="stat__value"><?= e(money($owed, false)) ?></p>
                <p class="stat__foot"><?= $owed > 0 ? 'Chase it politely' : 'All settled' ?></p>
            </article>
        </div>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Newest first</p>
                    <h2>Order history</h2>
                </div>
                <a class="btn btn--mint btn--sm" href="<?= e(url('orders/new')) ?>"><?= icon('plus', 14) ?><span>New order</span></a>
            </div>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Placed</th>
                            <th>Status</th>
                            <th class="num">Total</th>
                            <th class="num">Balance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders === []): ?>
                            <tr><td colspan="6">No orders for this customer yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $meta = status_meta((string) $order['status']);
                            $balance = balance_meta((float) $order['total'], (float) $order['paid']);
                            ?>
                            <tr>
                                <td class="mono">
                                    <a class="table__title" href="<?= e(url('orders/show', ['id' => $order['id']])) ?>"><?= e($order['order_no']) ?></a>
                                </td>
                                <td class="nowrap"><?= e(fmt_date($order['created_at'])) ?></td>
                                <td><span class="<?= e(tone_class($meta['tone'])) ?>"><?= e($meta['label']) ?></span></td>
                                <td class="num mono"><?= e(money($order['total'])) ?></td>
                                <td class="num mono"><?= $balance['balance'] > 0 ? e(money($balance['balance'])) : '—' ?></td>
                                <td class="actions">
                                    <a class="btn btn--ghost btn--sm" href="<?= e(url('orders/show', ['id' => $order['id']])) ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card card--ink">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Reach them</p>
                    <h2>Contact</h2>
                </div>
            </div>
            <div class="card__body">
                <dl class="meta-list">
                    <div class="meta-item"><dt>Phone</dt><dd><?= e((string) ($customer['phone'] ?? '—')) ?></dd></div>
                    <div class="meta-item"><dt>Email</dt><dd><?= e((string) ($customer['email'] ?? '—')) ?></dd></div>
                    <div class="meta-item"><dt>Address</dt><dd><?= e((string) ($customer['address'] ?? '—')) ?></dd></div>
                </dl>
                <?php if (!empty($customer['phone'])): ?>
                    <a class="btn btn--mint btn--block" href="tel:<?= e((string) $customer['phone']) ?>"><?= icon('phone', 16) ?><span>Call</span></a>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($customer['notes'])): ?>
            <section class="card card--yellow">
                <div class="card__body">
                    <p class="eyebrow">Notes</p>
                    <p class="small"><?= nl2br(e((string) $customer['notes'])) ?></p>
                </div>
            </section>
        <?php endif; ?>

        <section class="card card--flat">
            <div class="card__body">
                <p class="eyebrow">Danger area</p>
                <form method="post" action="<?= e(url('customers/show', ['id' => $id])) ?>" data-confirm="Delete <?= e((string) $customer['name']) ?> and every order attached?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="customer.delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn--danger btn--block" type="submit"><?= icon('trash', 16) ?><span>Delete customer</span></button>
                </form>
            </div>
        </section>
    </div>
</div>
