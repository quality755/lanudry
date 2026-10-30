<?php
declare(strict_types=1);

$from = query('from', date('Y-m-01'));
$to = query('to', today());
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = today();
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$fromStamp = $from . ' 00:00:00';
$toStamp = date('Y-m-d 23:59:59', strtotime($to));

$series = revenue_by_day($from . ' 00:00:00', date('Y-m-d 00:00:00', strtotime($to . ' +1 day')));
$topServices = top_services($fromStamp, date('Y-m-d 00:00:00', strtotime($to . ' +1 day')), 6);
$methods = payments_by_method($fromStamp, date('Y-m-d 00:00:00', strtotime($to . ' +1 day')));
$staff = employee_performance($fromStamp, date('Y-m-d 00:00:00', strtotime($to . ' +1 day')));
$counts = status_counts();

$rangeRevenue = 0.0;
$rangeOrders = 0;
$peak = 0.0;
foreach ($series as $day) {
    $rangeRevenue += $day['revenue'];
    $rangeOrders += $day['orders'];
    $peak = max($peak, $day['revenue']);
}

$collectedStmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_at >= ? AND paid_at <= ?');
$collectedStmt->execute([$fromStamp, $toStamp]);
$rangeCollected = (float) $collectedStmt->fetchColumn();

$avgTicket = $rangeOrders > 0 ? $rangeRevenue / $rangeOrders : 0.0;
$collectionRate = $rangeRevenue > 0 ? ($rangeCollected / $rangeRevenue) * 100 : 0.0;

$exportUrl = url('reports', ['from' => $from, 'to' => $to, 'export' => 'csv']);
$methodMax = 0.0;
foreach ($methods as $row) {
    $methodMax = max($methodMax, (float) $row['total']);
}
$serviceMax = 0.0;
foreach ($topServices as $row) {
    $serviceMax = max($serviceMax, (float) $row['revenue']);
}
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= e(fmt_date($from)) ?> → <?= e(fmt_date($to)) ?></p>
        <h2>Reports</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e($exportUrl) ?>"><?= icon('download', 16) ?><span>Export CSV</span></a>
        <button class="btn btn--ghost" type="button" data-print><?= icon('printer', 16) ?><span>Print</span></button>
    </div>
</section>

<section class="card card--flat filter-bar">
    <form class="filter-form" method="get" action="index.php">
        <input type="hidden" name="r" value="reports">
        <div class="field">
            <label for="from">From</label>
            <input class="input" type="date" id="from" name="from" value="<?= e($from) ?>">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input class="input" type="date" id="to" name="to" value="<?= e($to) ?>">
        </div>
        <button class="btn btn--ink" type="submit"><?= icon('filter', 16) ?><span>Run</span></button>
        <div class="quick-ranges">
            <a class="chip" href="<?= e(url('reports', ['from' => today(), 'to' => today()])) ?>">Today</a>
            <a class="chip" href="<?= e(url('reports', ['from' => date('Y-m-d', strtotime('-6 days')), 'to' => today()])) ?>">7 days</a>
            <a class="chip" href="<?= e(url('reports', ['from' => date('Y-m-01'), 'to' => today()])) ?>">This month</a>
            <a class="chip" href="<?= e(url('reports', ['from' => date('Y-01-01'), 'to' => today()])) ?>">YTD</a>
        </div>
    </form>
</section>

<div class="grid grid--4">
    <article class="stat stat--yellow">
        <p class="stat__label">Order value</p>
        <p class="stat__value"><?= e(money($rangeRevenue, false)) ?></p>
        <p class="stat__foot"><?= (int) $rangeOrders ?> orders in range</p>
    </article>
    <article class="stat stat--mint">
        <p class="stat__label">Collected</p>
        <p class="stat__value"><?= e(money($rangeCollected, false)) ?></p>
        <p class="stat__foot"><?= number_format($collectionRate, 0) ?>% of billed</p>
    </article>
    <article class="stat stat--blue">
        <p class="stat__label">Avg ticket</p>
        <p class="stat__value"><?= e(money($avgTicket, false)) ?></p>
        <p class="stat__foot">Per order</p>
    </article>
    <article class="stat stat--pink">
        <p class="stat__label">Peak day</p>
        <p class="stat__value"><?= e(money($peak, false)) ?></p>
        <p class="stat__foot">Best single day</p>
    </article>
</div>

<section class="card card--flat">
    <div class="card__head">
        <div>
            <p class="eyebrow">Trend</p>
            <h2>Revenue by day</h2>
        </div>
        <span class="mono small"><?= count($series) ?> days</span>
    </div>
    <div class="card__body">
        <?php if ($series === []): ?>
            <p class="small">No orders in this range.</p>
        <?php else: ?>
            <div class="chart">
                <div class="chart__bars">
                    <?php foreach ($series as $day => $data): ?>
                        <?php $height = $peak > 0 ? max(2, (int) round(((float) $data['revenue'] / $peak) * 100)) : 2; ?>
                        <div class="chart__bar" title="<?= e(fmt_date($day)) ?>: <?= e(money($data['revenue'])) ?> (<?= (int) $data['orders'] ?> orders)">
                            <span class="chart__fill" style="height: <?= $height ?>%"></span>
                            <span class="chart__label"><?= e(date('d', strtotime($day))) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="grid grid--2">
    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Best sellers</p>
                <h2>Services ranked</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th class="num">Orders</th>
                        <th class="num">Qty</th>
                        <th class="num">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($topServices === []): ?>
                        <tr><td colspan="4">Nothing sold in this range.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($topServices as $row): ?>
                        <tr>
                            <td>
                                <span class="table__title"><?= e((string) $row['label']) ?></span>
                                <div class="bar bar--thin">
                                    <span style="width: <?= $serviceMax > 0 ? max(3, (int) round(((float) $row['revenue'] / $serviceMax) * 100)) : 3 ?>%"></span>
                                </div>
                            </td>
                            <td class="num mono"><?= (int) $row['orders'] ?></td>
                            <td class="num mono"><?= e(number_format((float) $row['qty'], 0)) ?></td>
                            <td class="num mono"><?= e(money($row['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Cashflow</p>
                <h2>Payment methods</h2>
            </div>
        </div>
        <div class="card__body">
            <div class="data-list">
                <?php if ($methods === []): ?>
                    <p class="small">No payments collected in this range.</p>
                <?php endif; ?>
                <?php foreach ($methods as $row): ?>
                    <div class="data-list__row">
                        <span class="mono small"><?= e(strtoupper((string) $row['method'])) ?></span>
                        <div class="data-list__bar">
                            <span style="width: <?= $methodMax > 0 ? max(3, (int) round(((float) $row['total'] / $methodMax) * 100)) : 3 ?>%"></span>
                        </div>
                        <span class="mono small"><?= e(money($row['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<div class="grid grid--2">
    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Team</p>
                <h2>Staff output</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th class="num">Orders</th>
                        <th class="num">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $row): ?>
                        <tr>
                            <td>
                                <span class="row">
                                    <span class="avatar avatar--sm"><?= e(initials((string) $row['name'])) ?></span>
                                    <span class="table__title"><?= e((string) $row['name']) ?></span>
                                </span>
                            </td>
                            <td class="small"><?= e((string) $row['role']) ?></td>
                            <td class="num mono"><?= (int) $row['orders'] ?></td>
                            <td class="num mono"><?= e(money($row['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Pipeline</p>
                <h2>Order status snapshot</h2>
            </div>
        </div>
        <div class="card__body">
            <div class="data-list">
                <?php
                $grand = array_sum($counts);
                foreach (ORDER_STATUSES as $status):
                    $n = (int) ($counts[$status] ?? 0);
                    $meta = status_meta($status);
                    ?>
                    <div class="data-list__row">
                        <span class="pill <?= e(tone_class($meta['tone'])) ?>"><?= e($meta['label']) ?></span>
                        <div class="data-list__bar">
                            <span style="width: <?= $grand > 0 ? max(3, (int) round(($n / $grand) * 100)) : 3 ?>%"></span>
                        </div>
                        <span class="mono small"><?= $n ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>
