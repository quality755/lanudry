<?php
declare(strict_types=1);

$term = trim(query('q'));
$page = max(1, (int) query('page', '1'));
$perPage = 12;

$where = '';
$params = [];
if ($term !== '') {
    $where = ' WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.code LIKE ?';
    $like = '%' . $term . '%';
    $params = [$like, $like, $like, $like];
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM customers c' . $where);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pager = paginate($total, $perPage, $page);

$stmt = db()->prepare(
    'SELECT c.*,
            (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) AS orders_count,
            (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.customer_id = c.id AND o.status <> \'cancelled\') AS spend,
            (SELECT COALESCE(SUM(GREATEST(o.total - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id), 0)), 0)
                FROM orders o WHERE o.customer_id = c.id AND o.status <> \'cancelled\') AS owed
     FROM customers c' . $where . '
     ORDER BY c.name LIMIT ' . (int) $pager['per'] . ' OFFSET ' . (int) $pager['offset']
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$totals = db()->query(
    'SELECT COUNT(*) AS total,
            (SELECT COUNT(*) FROM customers WHERE created_at >= \'' . date('Y-m-01 00:00:00') . '\') AS new_this_month,
            (SELECT COALESCE(SUM(GREATEST(o.total - (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id = o.id), 0)), 0)
                FROM orders o WHERE o.status <> \'cancelled\') AS owed
     FROM customers'
)->fetch() ?: ['total' => 0, 'new_this_month' => 0, 'owed' => 0];
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= (int) $total ?> on file</p>
        <h2>Customers</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url('customers/new')) ?>"><?= icon('plus', 16) ?><span>Add customer</span></a>
    </div>
</section>

<div class="grid grid--3">
    <article class="stat stat--yellow">
        <p class="stat__label">Total customers</p>
        <p class="stat__value"><?= (int) $totals['total'] ?></p>
        <p class="stat__foot">Lifetime records</p>
    </article>
    <article class="stat stat--mint">
        <p class="stat__label">New this month</p>
        <p class="stat__value"><?= (int) $totals['new_this_month'] ?></p>
        <p class="stat__foot"><?= e(date('F Y')) ?></p>
    </article>
    <article class="stat stat--pink">
        <p class="stat__label">Owed by all</p>
        <p class="stat__value"><?= e(money($totals['owed'], false)) ?></p>
        <p class="stat__foot">Across live orders</p>
    </article>
</div>

<section class="card card--flat">
    <div class="card__head">
        <div>
            <p class="eyebrow">Find anyone</p>
            <h2>Directory</h2>
        </div>
    </div>
    <div class="card__body">
        <form class="search-form" method="get" action="index.php">
            <input type="hidden" name="r" value="customers">
            <div class="field field--wide">
                <label for="q">Search</label>
                <input class="input" type="search" id="q" name="q" value="<?= e($term) ?>" placeholder="Name, phone, email or code">
            </div>
            <button class="btn btn--blue" type="submit"><?= icon('search', 16) ?><span>Search</span></button>
            <a class="btn btn--ghost" href="<?= e(url('customers')) ?>">Reset</a>
        </form>
    </div>
</section>

<section class="card card--flat">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Code</th>
                    <th>Contact</th>
                    <th class="num">Orders</th>
                    <th class="num">Spend</th>
                    <th class="num">Owed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customers === []): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty">
                                <span class="empty__icon"><?= icon('users', 30) ?></span>
                                <h3>No customers found</h3>
                                <p>Add the first one and the order book opens up.</p>
                                <a class="btn btn--primary" href="<?= e(url('customers/new')) ?>">Add customer</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <span class="row">
                                <span class="avatar avatar--sm"><?= e(initials((string) $customer['name'])) ?></span>
                                <span>
                                    <a class="table__title" href="<?= e(url('customers/show', ['id' => $customer['id']])) ?>"><?= e($customer['name']) ?></a>
                                    <span class="table__sub"><?= e((string) ($customer['address'] ?? '')) ?: 'No address on file' ?></span>
                                </span>
                            </span>
                        </td>
                        <td class="mono small"><?= e((string) $customer['code']) ?></td>
                        <td>
                            <?= e((string) ($customer['phone'] ?? '')) ?: '—' ?>
                            <span class="table__sub"><?= e((string) ($customer['email'] ?? '')) ?></span>
                        </td>
                        <td class="num mono"><?= (int) $customer['orders_count'] ?></td>
                        <td class="num mono"><?= e(money($customer['spend'])) ?></td>
                        <td class="num mono"><?= (float) $customer['owed'] > 0 ? e(money($customer['owed'])) : '—' ?></td>
                        <td class="actions">
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('customers/show', ['id' => $customer['id']])) ?>">Open</a>
                            <a class="btn btn--ghost btn--sm" href="<?= e(url('customers/edit', ['id' => $customer['id']])) ?>"><?= icon('edit', 14) ?></a>
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
            <span class="mono small">Showing <?= (int) $pager['from'] ?>–<?= (int) $pager['to'] ?> of <?= (int) $pager['total'] ?></span>
        </div>
    <?php endif; ?>
</section>
