<?php
declare(strict_types=1);

$term = trim(query('q'));
$method = query('method');
$page = max(1, (int) query('page', '1'));
$perPage = 15;
$errors = error_bag();

$where = ['1 = 1'];
$params = [];
if ($term !== '') {
    $where[] = '(o.order_no LIKE ? OR c.name LIKE ? OR p.reference LIKE ?)';
    $like = '%' . $term . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (in_array($method, PAYMENT_METHODS, true)) {
    $where[] = 'p.method = ?';
    $params[] = $method;
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare(
    'SELECT COUNT(*) FROM payments p
     JOIN orders o ON o.id = p.order_id
     LEFT JOIN customers c ON c.id = o.customer_id' . $whereSql
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pager = paginate($total, $perPage, $page);

/* Ledger uses the same WHERE clause as the count, so rebuild the parameters for the page query. */
$pageParams = $params;
$stmt = db()->prepare(
    'SELECT p.*, o.order_no, o.total AS order_total, c.name AS customer_name, u.name AS cashier
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     LEFT JOIN customers c ON c.id = o.customer_id
     LEFT JOIN users u ON u.id = p.created_by' . $whereSql . '
     ORDER BY p.paid_at DESC, p.id DESC LIMIT ' . (int) $pager['per'] . ' OFFSET ' . (int) $pager['offset']
);
$stmt->execute($pageParams);
$payments = $stmt->fetchAll();

$sumStmt = db()->prepare(
    'SELECT COALESCE(SUM(p.amount),0) AS total, COUNT(*) AS n FROM payments p
     JOIN orders o ON o.id = p.order_id
     LEFT JOIN customers c ON c.id = o.customer_id' . $whereSql
);
$sumStmt->execute($params);
$filtered = $sumStmt->fetch() ?: ['total' => 0, 'n' => 0];

$todayTotal = (float) db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = '" . today() . "'")->fetchColumn();
$monthTotal = (float) db()->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= '" . date('Y-m-01 00:00:00') . "'"
)->fetchColumn();
$outstanding = (float) db()->query(
    "SELECT COALESCE(SUM(GREATEST(o.total - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id), 0)),0)
     FROM orders o WHERE o.status <> 'cancelled'"
)->fetchColumn();

$openOrders = db()->query(
    "SELECT o.id, o.order_no, o.total, c.name AS customer_name,
            (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id) AS paid
     FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
     WHERE o.status <> 'cancelled'
     HAVING (o.total - paid) > 0.009
     ORDER BY o.created_at ASC LIMIT 100"
)->fetchAll();

$mix = [];
foreach (db()->query(
    "SELECT method, COUNT(*) AS n, COALESCE(SUM(amount),0) AS total FROM payments WHERE paid_at >= '" . date('Y-m-01 00:00:00') . "' GROUP BY method ORDER BY total DESC"
) as $row) {
    $mix[] = $row;
}
$mixMax = 0.0;
foreach ($mix as $row) {
    $mixMax = max($mixMax, (float) $row['total']);
}
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= (int) $total ?> receipt<?= $total === 1 ? '' : 's' ?></p>
        <h2>Payments</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e(url('reports')) ?>"><?= icon('chart', 16) ?><span>Reports</span></a>
    </div>
</section>

<div class="grid grid--3">
    <article class="stat stat--mint">
        <p class="stat__label">Collected today</p>
        <p class="stat__value"><?= e(money($todayTotal, false)) ?></p>
        <p class="stat__foot"><?= e(fmt_date(today())) ?></p>
    </article>
    <article class="stat stat--yellow">
        <p class="stat__label">This month</p>
        <p class="stat__value"><?= e(money($monthTotal, false)) ?></p>
        <p class="stat__foot"><?= e(date('F Y')) ?></p>
    </article>
    <article class="stat stat--pink">
        <p class="stat__label">Still owed</p>
        <p class="stat__value"><?= e(money($outstanding, false)) ?></p>
        <p class="stat__foot">Across all open tickets</p>
    </article>
</div>

<div class="grid grid--main">
    <div class="stack">
        <section class="card card--flat">
            <div class="card__body">
                <form class="search-form" method="get" action="index.php">
                    <input type="hidden" name="r" value="payments">
                    <div class="field field--wide">
                        <label for="q">Search</label>
                        <input class="input" type="search" id="q" name="q" value="<?= e($term) ?>" placeholder="Order no, customer, reference">
                    </div>
                    <div class="field">
                        <label for="method">Method</label>
                        <div class="select-wrap">
                            <select class="select" id="method" name="method">
                                <option value="">All</option>
                                <?php foreach (PAYMENT_METHODS as $option): ?>
                                    <option value="<?= e($option) ?>"<?= selected($method, $option) ?>><?= e(strtoupper($option)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn--blue" type="submit"><?= icon('filter', 16) ?><span>Apply</span></button>
                    <a class="btn btn--ghost" href="<?= e(url('payments')) ?>">Reset</a>
                </form>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Ledger</p>
                    <h2>Money in</h2>
                </div>
                <span class="mono small"><?= e(money($filtered['total'])) ?> · <?= (int) $filtered['n'] ?> entries</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="num">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments === []): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty">
                                        <span class="empty__icon"><?= icon('cash', 30) ?></span>
                                        <h3>No payments match</h3>
                                        <p>Record a payment on the right, or clear the filters.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $row): ?>
                            <tr>
                                <td class="nowrap mono small"><?= e(fmt_datetime($row['paid_at'])) ?></td>
                                <td class="mono">
                                    <a class="table__title" href="<?= e(url('orders/show', ['id' => $row['order_id']])) ?>"><?= e($row['order_no']) ?></a>
                                    <span class="table__sub">Total <?= e(money($row['order_total'])) ?></span>
                                </td>
                                <td class="small"><?= e((string) ($row['customer_name'] ?? '—')) ?></td>
                                <td><span class="tag"><?= e(strtoupper((string) $row['method'])) ?></span></td>
                                <td class="small"><?= e((string) ($row['reference'] ?? '')) ?: '—' ?></td>
                                <td class="num mono"><?= e(money($row['amount'])) ?></td>
                                <td class="actions">
                                    <?php if (is_admin()): ?>
                                        <form method="post" action="<?= e(url('payments')) ?>" data-confirm="Delete this payment of <?= e(money($row['amount'])) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="payment.delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="order_id" value="<?= (int) $row['order_id'] ?>">
                                            <button class="btn btn--ghost btn--sm" type="submit"><?= icon('trash', 14) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pager['pages'] > 1): ?>
                <div class="card__foot">
                    <div class="pagination">
                        <a class="<?= $pager['page'] <= 1 ? 'is-disabled' : '' ?>" href="<?= e(current_query(['page' => max(1, $pager['page'] - 1)])) ?>">Prev</a>
                        <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                            <a class="<?= $i === $pager['page'] ? 'is-current' : '' ?>" href="<?= e(current_query(['page' => $i])) ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <a class="<?= $pager['page'] >= $pager['pages'] ? 'is-disabled' : '' ?>" href="<?= e(current_query(['page' => min($pager['pages'], $pager['page'] + 1)])) ?>">Next</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow"><?= e(date('F')) ?></p>
                    <h2>Collection mix</h2>
                </div>
            </div>
            <div class="card__body">
                <div class="data-list">
                    <?php if ($mix === []): ?>
                        <p class="small">No payments this month yet.</p>
                    <?php endif; ?>
                    <?php foreach ($mix as $row): ?>
                        <div class="data-list__row">
                            <span class="mono small"><?= e(strtoupper((string) $row['method'])) ?></span>
                            <div class="data-list__bar">
                                <span style="width: <?= $mixMax > 0 ? max(3, (int) round(((float) $row['total'] / $mixMax) * 100)) : 3 ?>%"></span>
                            </div>
                            <span class="mono small"><?= e(money($row['total'])) ?></span>
                            <span class="mono small"><?= (int) $row['n'] ?>x</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card card--mint">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Counter</p>
                    <h2>Record payment</h2>
                </div>
            </div>
            <div class="card__body">
                <?php if ($openOrders === []): ?>
                    <p class="small">Every order is settled. Nothing to collect.</p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('payments')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="payment.save">
                        <div class="stack--sm">
                            <div class="field<?= isset($errors['order_id']) ? ' has-error' : '' ?>">
                                <label for="order_id">Order</label>
                                <div class="select-wrap">
                                    <select class="select" id="order_id" name="order_id" data-balance-select required>
                                        <option value="">Pick an order</option>
                                        <?php foreach ($openOrders as $open): ?>
                                            <?php $due = (float) $open['total'] - (float) $open['paid']; ?>
                                            <option value="<?= (int) $open['id'] ?>"
                                                    data-balance="<?= e(number_format($due, 2, '.', '')) ?>"
                                                    <?= selected(old('order_id'), (string) $open['id']) ?>>
                                                <?= e($open['order_no']) ?> · <?= e((string) ($open['customer_name'] ?? 'Walk-in')) ?> · <?= e(money($due)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="field<?= isset($errors['amount']) ? ' has-error' : '' ?>">
                                <label for="amount">Amount</label>
                                <input class="input input--num" type="number" id="amount" name="amount" step="0.01" min="0" value="<?= e(old('amount')) ?>" data-pay-amount>
                                <?php if (isset($errors['amount'])): ?>
                                    <span class="field__error"><?= e($errors['amount']) ?></span>
                                <?php endif; ?>
                                <span class="field__hint">Balance <span data-pay-balance>—</span></span>
                            </div>
                            <div class="field">
                                <label for="method">Method</label>
                                <div class="select-wrap">
                                    <select class="select" id="method" name="method">
                                        <?php foreach (PAYMENT_METHODS as $option): ?>
                                            <option value="<?= e($option) ?>"<?= selected(old('method', 'cash'), $option) ?>><?= e(strtoupper($option)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label for="reference">Reference</label>
                                <input class="input" type="text" id="reference" name="reference" value="<?= e(old('reference')) ?>" placeholder="M-Pesa code or slip">
                            </div>
                            <button class="btn btn--ink btn--block" type="submit"><?= icon('cash', 16) ?><span>Bank it</span></button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="card card--ink">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Chase list</p>
                    <h2>Unpaid orders</h2>
                </div>
            </div>
            <div class="card__body">
                <ul class="plain-list">
                    <?php if ($openOrders === []): ?>
                        <li class="small">Nothing outstanding. Enjoy the quiet.</li>
                    <?php endif; ?>
                    <?php foreach (array_slice($openOrders, 0, 6) as $open): ?>
                        <?php $due = (float) $open['total'] - (float) $open['paid']; ?>
                        <li>
                            <a href="<?= e(url('orders/show', ['id' => $open['id']])) ?>">
                                <span class="mono small"><?= e($open['order_no']) ?></span>
                                <span class="small"><?= e((string) ($open['customer_name'] ?? 'Walk-in')) ?></span>
                                <span class="mono small"><?= e(money($due)) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
</div>
