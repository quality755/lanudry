<?php
declare(strict_types=1);

$orderId = (int) query('id', '0');
$order = $orderId > 0 ? order_find($orderId) : null;
if ($order === null) {
    flash('error', 'Pick an order to print a receipt.');
    redirect('orders');
}

$items = order_items($orderId);
$payments = order_payments($orderId);
$money = outstanding_for_order($order);
$status = status_meta((string) $order['status']);
?>
<div class="receipt-shell">
    <div class="receipt-actions no-print">
        <a class="btn btn--ghost" href="<?= e(url('orders/show', ['id' => $orderId])) ?>"><?= icon('arrow', 16) ?><span>Back to order</span></a>
        <button class="btn btn--primary" type="button" data-print><?= icon('printer', 16) ?><span>Print receipt</span></button>
    </div>

    <article class="receipt">
        <header class="receipt__head">
            <div>
                <p class="receipt__brand"><?= e(setting('business_name', (string) config('name'))) ?></p>
                <p class="small"><?= e(setting('business_address')) ?></p>
                <p class="small">
                    <?= e(setting('business_phone')) ?> · <?= e(setting('business_email')) ?>
                </p>
            </div>
            <div class="receipt__stamp">
                <span class="sticker">RECEIPT</span>
                <p class="mono"><?= e((string) $order['order_no']) ?></p>
                <p class="small"><?= e(fmt_datetime($order['created_at'])) ?></p>
            </div>
        </header>

        <div class="receipt__meta">
            <div>
                <p class="eyebrow">Customer</p>
                <p class="strong"><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></p>
                <p class="small"><?= e((string) ($order['customer_phone'] ?? '')) ?></p>
                <p class="small"><?= e((string) ($order['customer_address'] ?? '')) ?></p>
            </div>
            <div>
                <p class="eyebrow">Service details</p>
                <p class="small">Status: <strong><?= e($status['label']) ?></strong></p>
                <p class="small">Picked up: <?= e(fmt_date($order['pickup_date'])) ?></p>
                <p class="small">Delivery: <?= e(fmt_date($order['delivery_date'])) ?></p>
                <p class="small">Handled by: <?= e((string) ($order['employee_name'] ?? 'Unassigned')) ?></p>
            </div>
        </div>

        <table class="table table--receipt">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Unit price</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <span class="table__title"><?= e((string) $item['label']) ?></span>
                            <span class="table__sub"><?= e((string) $item['unit']) ?></span>
                        </td>
                        <td class="num mono"><?= e(number_format((float) $item['qty'], 0)) ?></td>
                        <td class="num mono"><?= e(money($item['unit_price'])) ?></td>
                        <td class="num mono"><?= e(money($item['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($items === []): ?>
                    <tr><td colspan="4">No line items recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="receipt__totals">
            <div class="receipt__lines">
                <p><span>Subtotal</span><span class="mono"><?= e(money($order['subtotal'])) ?></span></p>
                <?php if ((float) $order['discount'] > 0): ?>
                    <p><span>Discount</span><span class="mono">−<?= e(money($order['discount'])) ?></span></p>
                <?php endif; ?>
                <?php if ((float) $order['delivery_fee'] > 0): ?>
                    <p><span>Delivery</span><span class="mono"><?= e(money($order['delivery_fee'])) ?></span></p>
                <?php endif; ?>
                <p class="receipt__total"><span>Total</span><span class="mono"><?= e(money($order['total'])) ?></span></p>
                <p><span>Paid</span><span class="mono"><?= e(money($money['paid'])) ?></span></p>
            </div>
            <div class="receipt__balance receipt__balance--<?= e($money['tone']) ?>">
                <p class="eyebrow">Balance due</p>
                <p class="receipt__balance-value"><?= e(money($money['balance'])) ?></p>
                <span class="pill <?= e(tone_class($money['tone'])) ?>"><?= e($money['label']) ?></span>
            </div>
        </div>

        <?php if ($payments !== []): ?>
            <div class="receipt__payments">
                <p class="eyebrow">Payments received</p>
                <table class="table table--receipt">
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="mono small"><?= e(fmt_datetime($payment['paid_at'])) ?></td>
                                <td class="small"><?= e(strtoupper((string) $payment['method'])) ?><?= $payment['reference'] ? ' · ' . e((string) $payment['reference']) : '' ?></td>
                                <td class="num mono"><?= e(money($payment['amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($order['notes'])): ?>
            <p class="receipt__note small"><strong>Notes:</strong> <?= e((string) $order['notes']) ?></p>
        <?php endif; ?>

        <footer class="receipt__foot">
            <p><?= e(setting('receipt_footer')) ?></p>
            <p class="small mono">Served by <?= e(auth_user()['name'] ?? 'counter staff') ?> · <?= e(date('d M Y H:i')) ?></p>
        </footer>
    </article>
</div>
