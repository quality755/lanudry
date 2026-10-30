<?php
declare(strict_types=1);

$id = (int) query('id', '0');
$order = order_find($id);
if ($order === null) {
    flash('error', 'That order is not in the book.');
    redirect('orders');
}

$items = order_items($id);
$payments = order_payments($id);
$meta = status_meta((string) $order['status']);
$balance = balance_meta((float) $order['total'], (float) $order['paid']);
$errors = error_bag();
$stepIndex = (int) $meta['step'];
$flow = ['received', 'processing', 'ready', 'delivered'];
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= e((string) $order['order_no']) ?> · <?= e(fmt_date($order['created_at'])) ?></p>
        <h2><?= e($order['customer_name'] ?? 'Walk-in') ?></h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e(url('orders')) ?>"><?= icon('arrow', 16) ?><span>Back</span></a>
        <a class="btn btn--blue" href="<?= e(url('invoice', ['id' => $id])) ?>"><?= icon('receipt', 16) ?><span>Receipt</span></a>
        <a class="btn btn--primary" href="<?= e(url('orders/edit', ['id' => $id])) ?>"><?= icon('edit', 16) ?><span>Edit</span></a>
    </div>
</section>

<div class="grid grid--main">
    <div class="stack">
        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Stage</p>
                    <h2>Where is it?</h2>
                </div>
                <span class="<?= e(tone_class($meta['tone'])) ?>"><?= e($meta['label']) ?></span>
            </div>
            <div class="card__body">
                <div class="steps">
                    <?php foreach ($flow as $index => $stage): ?>
                        <?php
                        $stageMeta = status_meta($stage);
                        $class = '';
                        if ((string) $order['status'] === 'cancelled') {
                            $class = $index === 0 ? ' is-cancelled' : '';
                        } elseif ($stepIndex > $index + 1) {
                            $class = ' is-done';
                        } elseif ($stepIndex === $index + 1) {
                            $class = ' is-current';
                        }
                        ?>
                        <div class="step<?= $class ?>">
                            <span class="step__dot"><?= $index + 1 ?></span>
                            <div>
                                <strong><?= e($stageMeta['label']) ?></strong>
                                <span class="mono small">
                                    <?= $stage === 'received' ? e(fmt_date($order['pickup_date'])) : '' ?>
                                    <?= $stage === 'delivered' ? e(fmt_date($order['delivery_date'])) : '' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="divider"></div>

                <div class="btn-row">
                    <?php foreach (ORDER_STATUSES as $status): ?>
                        <?php if ($status === (string) $order['status']) {
                            continue;
                        } ?>
                        <form method="post" action="<?= e(url('orders/show', ['id' => $id])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="order.status">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="status" value="<?= e($status) ?>">
                            <button class="btn btn--sm <?= $status === 'cancelled' ? 'btn--danger' : 'btn--ghost' ?>" type="submit">
                                <?= icon($status === 'cancelled' ? 'x' : 'check', 14) ?>
                                <span><?= e(status_meta($status)['label']) ?></span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow"><?= count($items) ?> line<?= count($items) === 1 ? '' : 's' ?></p>
                    <h2>Items</h2>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Unit</th>
                            <th class="num">Qty</th>
                            <th class="num">Price</th>
                            <th class="num">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['label']) ?></td>
                                <td class="mono small"><?= e((string) $item['unit']) ?></td>
                                <td class="num mono"><?= e(number_format((float) $item['qty'], (floor((float) $item['qty']) == (float) $item['qty'] ? 0 : 1))) ?></td>
                                <td class="num mono"><?= e(money($item['unit_price'])) ?></td>
                                <td class="num mono"><?= e(money($item['line_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card__body">
                <div class="totals">
                    <div class="totals__row">
                        <span>Subtotal</span>
                        <span class="mono"><?= e(money($order['subtotal'])) ?></span>
                    </div>
                    <div class="totals__row">
                        <span>Discount</span>
                        <span class="mono">− <?= e(money($order['discount'])) ?></span>
                    </div>
                    <div class="totals__row">
                        <span>Delivery</span>
                        <span class="mono"><?= e(money($order['delivery_fee'])) ?></span>
                    </div>
                    <div class="totals__row totals__row--grand">
                        <span>Total</span>
                        <span class="mono"><?= e(money($order['total'])) ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow"><?= count($payments) ?> receipt<?= count($payments) === 1 ? '' : 's' ?></p>
                    <h2>Payments</h2>
                </div>
                <span class="mono small">Paid <?= e(money($order['paid'])) ?></span>
            </div>
            <div class="table-wrap">
                <table class="table table--compact">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th class="num">Amount</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments === []): ?>
                            <tr><td colspan="5">No payments recorded yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="nowrap mono small"><?= e(fmt_datetime($payment['paid_at'])) ?></td>
                                <td><span class="tag"><?= e(strtoupper((string) $payment['method'])) ?></span></td>
                                <td class="small"><?= e((string) ($payment['reference'] ?? '')) ?: '—' ?></td>
                                <td class="num mono"><?= e(money($payment['amount'])) ?></td>
                                <td class="actions">
                                    <?php if (is_admin()): ?>
                                        <form method="post" action="<?= e(url('orders/show', ['id' => $id])) ?>" data-confirm="Delete this payment?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="payment.delete">
                                            <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
                                            <input type="hidden" name="order_id" value="<?= $id ?>">
                                            <button class="btn btn--ghost btn--sm" type="submit"><?= icon('trash', 14) ?></button>
                                        </form>
                                    <?php endif; ?>
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
                    <p class="eyebrow">Balance</p>
                    <h2><?= e($balance['label']) ?></h2>
                </div>
            </div>
            <div class="card__body stack--sm">
                <div class="balance-box balance-box--<?= $balance['balance'] > 0 ? 'due' : 'paid' ?>">
                    <strong><?= e(money($balance['balance'])) ?></strong>
                    <span>Outstanding on this order</span>
                </div>
                <div class="progress"><span style="width: <?= (float) $order['total'] > 0 ? max(2, min(100, (int) round(((float) $order['paid'] / (float) $order['total']) * 100))) : 100 ?>%"></span></div>
                <p class="mono small"><?= e(money($order['paid'])) ?> of <?= e(money($order['total'])) ?> settled</p>
            </div>
        </section>

        <?php if ($balance['balance'] > 0): ?>
            <section class="card card--mint">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">Take money</p>
                        <h2>Record payment</h2>
                    </div>
                </div>
                <div class="card__body">
                    <form method="post" action="<?= e(url('payments')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="payment.save">
                        <input type="hidden" name="order_id" value="<?= $id ?>">
                        <div class="stack--sm">
                            <div class="field<?= isset($errors['amount']) ? ' has-error' : '' ?>">
                                <label for="amount">Amount</label>
                                <input class="input input--num" type="number" id="amount" name="amount" step="0.01" min="0"
                                       max="<?= e(number_format($balance['balance'], 2, '.', '')) ?>"
                                       value="<?= e(old('amount', number_format($balance['balance'], 2, '.', ''))) ?>">
                                <span class="field__hint">
                                    Full balance <button class="btn btn--ghost btn--sm" type="button" data-pay-full data-target="#amount" data-amount="<?= e(number_format($balance['balance'], 2, '.', '')) ?>">Fill</button>
                                </span>
                                <?php if (isset($errors['amount'])): ?>
                                    <span class="field__error"><?= e($errors['amount']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field">
                                <label for="method">Method</label>
                                <div class="select-wrap">
                                    <select class="select" id="method" name="method">
                                        <?php foreach (PAYMENT_METHODS as $method): ?>
                                            <option value="<?= e($method) ?>"<?= selected(old('method', 'cash'), $method) ?>><?= e(strtoupper($method)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label for="reference">Reference</label>
                                <input class="input" type="text" id="reference" name="reference" value="<?= e(old('reference')) ?>" placeholder="M-Pesa code, slip no">
                            </div>
                            <button class="btn btn--ink btn--block" type="submit"><?= icon('cash', 16) ?><span>Record payment</span></button>
                        </div>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <section class="card card--mint">
                <div class="card__body center stack--sm">
                    <span class="empty__icon"><?= icon('check', 28) ?></span>
                    <h3>Fully paid</h3>
                    <p class="small">Nothing left to collect on this ticket.</p>
                    <a class="btn btn--ink btn--block" href="<?= e(url('invoice', ['id' => $id])) ?>">Print receipt</a>
                </div>
            </section>
        <?php endif; ?>

        <section class="card card--flat">
            <div class="card__head">
                <div>
                    <p class="eyebrow">Who</p>
                    <h2>Customer</h2>
                </div>
            </div>
            <div class="card__body stack--sm">
                <p><strong><?= e($order['customer_name'] ?? 'Walk-in') ?></strong></p>
                <dl class="meta-list">
                    <div class="meta-item"><dt>Phone</dt><dd><?= e((string) ($order['customer_phone'] ?? '—')) ?></dd></div>
                    <div class="meta-item"><dt>Email</dt><dd><?= e((string) ($order['customer_email'] ?? '—')) ?></dd></div>
                    <div class="meta-item"><dt>Address</dt><dd><?= e((string) ($order['customer_address'] ?? '—')) ?></dd></div>
                    <div class="meta-item"><dt>Promised</dt><dd><?= e(fmt_date($order['delivery_date'])) ?></dd></div>
                    <div class="meta-item"><dt>Staff</dt><dd><?= e((string) ($order['employee_name'] ?? 'Unassigned')) ?></dd></div>
                </dl>
                <?php if (!empty($order['customer_id'])): ?>
                    <a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('customers/show', ['id' => $order['customer_id']])) ?>">Customer file</a>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($order['notes'])): ?>
            <section class="card card--yellow">
                <div class="card__body">
                    <p class="eyebrow">Notes</p>
                    <p class="small"><?= nl2br(e((string) $order['notes'])) ?></p>
                </div>
            </section>
        <?php endif; ?>

        <?php if (is_admin()): ?>
            <section class="card card--flat">
                <div class="card__body">
                    <form method="post" action="<?= e(url('orders/show', ['id' => $id])) ?>" data-confirm="Delete order <?= e((string) $order['order_no']) ?> and all its payments?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="order.delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="btn btn--danger btn--block" type="submit"><?= icon('trash', 16) ?><span>Delete order</span></button>
                    </form>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
