<?php
declare(strict_types=1);

$status = query('status');
$term = trim(query('q'));
$page = max(1, (int) query('page', '1'));
$perPage = 12;

$where = [];
$params = [];
if ($status !== '' && in_array($status, ORDER_STATUSES, true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($term !== '') {
    $where[] = '(o.order_no LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $term . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

$countStmt = db()->prepare('SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pager = paginate($total, $perPage, $page);

$sql = 'SELECT o.*, c.name AS customer_name, c.phone AS customer_phone,
               (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.order_id = o.id) AS paid
        FROM orders o LEFT JOIN customers c ON c.id = o.customer_id'
    . $whereSql
    . ' ORDER BY o.created_at DESC, o.id DESC LIMIT ' . (int) $pager['per'] . ' OFFSET ' . (int) $pager['offset'];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = status_counts();
$activeTotal = $counts['received'] + $counts['processing'] + $counts['ready'];
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= (int) $total ?> tickets in this view</p>
        <h2>Order book</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url('orders/new')) ?>"><?= icon('plus', 16) ?><span>New order</span></a>
    </div>
</section>

<div class="grid grid--4">
    <article class="stat stat--blue">
        <p class="stat__label">In the pipeline</p>
        <p class="stat__value"><?= (int) $activeTotal ?></p>
        <p class="stat__foot">Not yet delivered</p>
    </article>
    <article class="stat stat--yellow">
        <p class="stat__label">Ready</p>
        <p class="stat__value"><?= (int) $counts['ready'] ?></p>
        <p class="stat__foot">Waiting on customers</p>
    </article>
    <article class="stat stat--mint">
        <p class="stat__label">Delivered</p>
        <p class="stat__value"><?= (int) $counts['delivered'] ?></p>
        <p class="stat__foot">All time</p>
    </article>
    <article class="stat stat--pink">
        <p class="stat__label">Cancelled</p>
        <p class="stat__value"><?= (int) $counts['cancelled'] ?></p>
        <p class="stat__foot">Records kept</p>
    </article>
</div>

<section class="card card--flat">
    <div class="card__head">
        <div>
            <p class="eyebrow">Filter the pile</p>
            <h2>Search</h2>
        </div>
        <div class="chart-legend">
            <?php foreach (ORDER_STATUSES as $key): ?>
                <span><?= e(status_meta($key)['label']) ?>: <?= (int) ($counts[$key] ?? 0) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card__body">
        <form class="toolbar" method="get" action="index.php">
            <input type="hidden" name="r" value="orders">
            <div class="field field--wide">
                <label for="q">Search</label>
                <input class="input" type="search" id="q" name="q" value="<?= e($term) ?>" placeholder="Order no, customer, phone">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select class="select" id="status" name="status" data-autosubmit>
                    <option value="">All statuses</option>
                    <?php foreach (ORDER_STATUSES as $key): ?>
                        <option value="<?= e($key) ?>"<?= selected($status, $key) ?>><?= e(status_meta($key)['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="toolbar__group">
                <button class="btn btn--blue" type="submit"><?= icon('search', 16) ?><span>Filter</span></button>
                <a class="btn btn--ghost" href="<?= e(url('orders')) ?>">Reset</a>
            </div>
        </form>
    </div>
</section>

<section class="card card--flat">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Placed</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th class="num">Total</th>
                    <th class="num">Paid</th>
                    <th class="num">Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($orders === []): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty">
                                <span class="empty__icon"><?= icon('basket', 30) ?></span>
                                <h3>Nothing here</h3>
                                <p>No orders match this filter. Loosen it or create a new ticket.</p>
                                <a class="btn btn--primary" href="<?= e(url('orders/new')) ?>">New order</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $meta = status_meta((string) $order['status']);
                    $balance = balance_meta((float) $order['total'], (float) $order['paid']);
                    ?>
                    <tr>
                        <td>
                            <a class="table__title mono" href="<?= e(url('orders/show', ['id' => $order['id']])) ?>"><?= e($order['order_no']) ?></a>
                            <span class="table__sub">#<?= (int) $order['id'] ?></span>
                        </td>
                        <td>
                            <?= e($order['customer_name'] ?? 'Walk-in') ?>
                            <span class="table__sub"><?= e((string) ($order['customer_phone'] ?? '')) ?></span>
                        </td>
                        <td class="nowrap"><?= e(fmt_date($order['created_at'])) ?></td>
                        <td class="nowrap"><?= e(fmt_date($order['delivery_date'])) ?></td>
                        <td><span class="<?= e(tone_class($meta['tone'])) ?>"><?= e($meta['label']) ?></span></td>
                        <td class="num mono"><?= e(money($order['total'])) ?></td>
                        <td class="num mono"><?= e(money($order['paid'])) ?></td>
                        <td class="num mono"><?= $balance['balance'] > 0 ? e(money($balance['balance'])) : '—' ?></td>
                        <td class="actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('orders/show', ['id' => $order['id']])) ?>">Open</a>
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
                    <?php if ($pager['pages'] > 7 && abs($i - $pager['page']) > 2 && $i !== 1 && $i !== $pager['pages']): ?>
                        <?php if ($i === 2 || $i === $pager['pages'] - 1): ?><span class="is-disabled">…</span><?php endif; ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="<?= $i === $pager['page'] ? 'is-current' : '' ?>" href="<?= e(current_query(['page' => $i])) ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a class="<?= $pager['page'] >= $pager['pages'] ? 'is-disabled' : '' ?>" href="<?= e(current_query(['page' => min($pager['pages'], $pager['page'] + 1)])) ?>">Next</a>
            </div>
            <span class="mono small">Showing <?= (int) $pager['from'] ?>–<?= (int) $pager['to'] ?> of <?= (int) $pager['total'] ?></span>
        </div>
    <?php endif; ?>
</section>
