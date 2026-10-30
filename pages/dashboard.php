<?php
declare(strict_types=1);

$stats = dashboard_stats();
$counts = status_counts();
$from = date('Y-m-d 00:00:00', strtotime('-13 days'));
$to = date('Y-m-d 23:59:59');
$series = revenue_by_day($from, $to);
$peak = 0.0;
foreach ($series as $point) {
    $peak = max($peak, (float) $point['revenue']);
}
$orders = recent_orders(7);
$topServices = top_services($from, $to, 5);
$methods = payments_by_method($from, $to);
$feed = activity_feed(6);
$collectionRate = $stats['open_orders'] > 0
    ? (int) round(($stats['paid_orders'] / max(1, $stats['open_orders'])) * 100)
    : 0;
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= e(date('l d M Y')) ?></p>
        <h2>Today on the floor</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--ink btn--sm" href="<?= e(url('reports')) ?>"><?= icon('chart', 16) ?><span>Reports</span></a>
        <a class="btn btn--mint btn--sm" href="<?= e(url('payments')) ?>"><?= icon('cash', 16) ?><span>Payments</span></a>
    </div>
</section>

<div class="grid grid--4">
    <article class="stat stat--yellow">
        <p class="stat__label">Orders today</p>
        <p class="stat__value"><?= (int) $stats['orders_today'] ?></p>
        <p class="stat__foot"><?= (int) $stats['active_orders'] ?> still in the pipeline</p>
    </article>
    <article class="stat stat--pink">
        <p class="stat__label">Ready for pickup</p>
        <p class="stat__value"><?= (int) $stats['ready'] ?></p>
        <p class="stat__foot">Call them before they forget</p>
    </article>
    <article class="stat stat--blue">
        <p class="stat__label">Billed this month</p>
        <p class="stat__value"><?= e(money($stats['month_revenue'], false)) ?></p>
        <p class="stat__foot"><?= e(money($stats['month_collected'])) ?> collected</p>
    </article>
    <article class="stat stat--ink">
        <p class="stat__label">Money owed</p>
        <p class="stat__value"><?= e(money($stats['outstanding'], false)) ?></p>
        <p class="stat__foot"><?= $collectionRate ?>% of orders settled</p>
    </article>
</div>

<div class="grid grid--main">
    <div class="stack">
        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Last 14 days</p>
                    <h2>Revenue rhythm</h2>
                </div>
                <span class="tag"><?= e(money($stats['week_revenue'])) ?> this week</span>
            </div>
            <div class="card__body">
                <div class="chart-wrap">
                    <div class="chart">
                        <?php foreach ($series as $day => $point): ?>
                            <?php $height = $peak > 0 ? max(2, (int) round(($point['revenue'] / $peak) * 100)) : 2; ?>
                            <div class="chart__col<?= $point['revenue'] <= 0 ? ' chart__col--empty' : '' ?>">
                                <span class="chart__value"><?= e(money($point['revenue'], false)) ?></span>
                                <div class="chart__bar" style="height: <?= $height ?>%"></div>
                                <span class="chart__label"><?= e(date('d/m', strtotime($day))) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="chart-legend">
                    <span>Daily order value</span>
                    <span>Peak <?= e(money($peak)) ?></span>
                    <span>Avg order <?= e(money($stats['avg_order'])) ?></span>
                </div>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Newest first</p>
                    <h2>Recent tickets</h2>
                </div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('orders')) ?>">All orders <?= icon('arrow', 14) ?></a>
            </div>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="num">Total</th>
                            <th class="num">Balance</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders === []): ?>
                            <tr><td colspan="6">No orders yet. Create the first ticket.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $meta = status_meta((string) $order['status']);
                            $balance = balance_meta((float) $order['total'], (float) $order['paid']);
                            ?>
                            <tr>
                                <td>
                                    <a class="table__title mono" href="<?= e(url('orders/show', ['id' => $order['id']])) ?>">
                                        <?= e($order['order_no']) ?>
                                    </a>
                                    <span class="table__sub"><?= e(fmt_date($order['created_at'])) ?></span>
                                </td>
                                <td><?= e($order['customer_name'] ?? 'Walk-in') ?></td>
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

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Now</p>
                    <h2>Pipeline</h2>
                </div>
                <span class="tag"><?= array_sum($counts) ?> total tickets</span>
            </div>
            <div class="card__body">
                <div class="steps">
                    <?php foreach (ORDER_STATUSES as $index => $status): ?>
                        <?php $meta = status_meta($status); ?>
                        <div class="step<?= $index === 4 ? '' : ' is-done' ?>">
                            <span class="step__dot"><?= (int) $index + 1 ?></span>
                            <div>
                                <strong><?= e($meta['label']) ?></strong>
                                <span class="mono small"><?= (int) ($counts[$status] ?? 0) ?> orders</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <div class="stack">
        <section class="card card--yellow">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Cash watch</p>
                    <h2>Collection</h2>
                </div>
            </div>
            <div class="card__body stack--sm">
                <div class="progress"><span style="width: <?= max(2, $collectionRate) ?>%"></span></div>
                <p class="mono small"><?= (int) $stats['paid_orders'] ?> of <?= (int) $stats['open_orders'] ?> orders fully paid</p>
                <div class="balance-box balance-box--due">
                    <strong><?= e(money($stats['outstanding'])) ?></strong>
                    <span>Outstanding across all live orders</span>
                </div>
                <a class="btn btn--ink btn--block" href="<?= e(url('payments')) ?>"><?= icon('cash', 16) ?><span>Record a payment</span></a>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Top earners</p>
                    <h2>Services</h2>
                </div>
            </div>
            <div class="card__body card__body--tight">
                <?php if ($topServices === []): ?>
                    <div class="empty"><p>No service revenue in this window.</p></div>
                <?php else: ?>
                    <?php $top = (float) $topServices[0]['revenue']; ?>
                    <div class="rank-list">
                        <?php foreach ($topServices as $service): ?>
                            <div class="rank-row">
                                <div class="rank-row__top">
                                    <strong><?= e($service['label']) ?></strong>
                                    <em><?= e(money($service['revenue'])) ?></em>
                                </div>
                                <div class="rank-bar">
                                    <span style="width: <?= $top > 0 ? max(4, (int) round(((float) $service['revenue'] / $top) * 100)) : 4 ?>%"></span>
                                </div>
                                <p class="mono small"><?= (float) $service['qty'] ?> units · <?= (int) $service['orders'] ?> orders</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Last 14 days</p>
                    <h2>Payment mix</h2>
                </div>
            </div>
            <div class="card__body card__body--tight">
                <?php if ($methods === []): ?>
                    <div class="empty"><p>Nothing collected yet.</p></div>
                <?php else: ?>
                    <dl class="data-list">
                        <?php foreach ($methods as $method): ?>
                            <dt><?= e(ucfirst($method['method'])) ?></dt>
                            <dd class="mono"><?= e(money($method['total'])) ?> <span class="muted">· <?= (int) $method['payments'] ?>x</span></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Audit</p>
                    <h2>Activity</h2>
                </div>
            </div>
            <div class="card__body card__body--tight">
                <ul class="feed">
                    <?php if ($feed === []): ?>
                        <li><div class="feed__body"><strong>Nothing logged yet</strong></div></li>
                    <?php endif; ?>
                    <?php foreach ($feed as $item): ?>
                        <li>
                            <span class="feed__dot" aria-hidden="true"></span>
                            <div class="feed__body">
                                <strong><?= e($item['action']) ?></strong>
                                <span><?= e((string) ($item['detail'] ?? '')) ?> · <?= e(fmt_datetime($item['created_at'])) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
</div>
