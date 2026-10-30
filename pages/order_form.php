<?php
declare(strict_types=1);

$id = (int) query('id', '0');
$isEdit = $id > 0;
$order = $isEdit ? order_find($id) : null;

if ($isEdit && $order === null) {
    flash('error', 'That order is not in the book.');
    redirect('orders');
}

$services = services_list(true);
$customers = customers_list();
$employees = employees_list(true);
$errors = error_bag();

$defaults = [
    'customer_id'   => $order['customer_id'] ?? '',
    'employee_id'   => $order['employee_id'] ?? '',
    'status'        => $order['status'] ?? 'received',
    'pickup_date'   => $order['pickup_date'] ?? today(),
    'delivery_date' => $order['delivery_date'] ?? date('Y-m-d', strtotime('+2 days')),
    'notes'         => $order['notes'] ?? '',
    'discount'      => $order['discount'] ?? '0',
    'delivery_fee'  => $order['delivery_fee'] ?? (setting('default_delivery_fee', '0')),
];

$value = static function (string $key) use ($defaults): string {
    return old($key, (string) $defaults[$key]);
};

$lines = old_array('items');
if ($lines === [] && $isEdit) {
    $lines = array_map(
        static fn (array $item): array => [
            'service_id' => (int) $item['service_id'],
            'qty'        => (float) $item['qty'],
            'price'      => (float) $item['unit_price'],
        ],
        order_items($id)
    );
}
if ($lines === []) {
    $lines = [['service_id' => 0, 'qty' => 1, 'price' => 0]];
}
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= $isEdit ? e((string) $order['order_no']) : 'Fresh ticket' ?></p>
        <h2><?= $isEdit ? 'Edit order' : 'Create an order' ?></h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--ghost" href="<?= e($isEdit ? url('orders/show', ['id' => $id]) : url('orders')) ?>">Cancel</a>
        <?php if ($isEdit && is_admin()): ?>
            <form method="post" action="<?= e(url('orders/show', ['id' => $id])) ?>" data-confirm="Delete this order and its payments? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="order.delete">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn--danger" type="submit"><?= icon('trash', 16) ?><span>Delete</span></button>
            </form>
        <?php endif; ?>
    </div>
</section>

<form method="post" action="<?= e($isEdit ? url('orders/edit', ['id' => $id]) : url('orders/new')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="order.save">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="grid grid--main">
        <div class="stack">
            <section class="card card--flat">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">Who and when</p>
                        <h2>Order details</h2>
                    </div>
                    <?php if ($isEdit): ?>
                        <span class="tag mono"><?= e((string) $order['order_no']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card__body">
                    <div class="form-grid">
                        <div class="field field--full<?= isset($errors['customer_id']) ? ' has-error' : '' ?>">
                            <label for="customer_id">Customer</label>
                            <div class="select-wrap">
                                <select class="select" id="customer_id" name="customer_id" required>
                                    <option value="">— pick a customer —</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= (int) $customer['id'] ?>"<?= selected($value('customer_id'), $customer['id']) ?>>
                                            <?= e($customer['name']) ?><?= $customer['phone'] ? ' · ' . e((string) $customer['phone']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (isset($errors['customer_id'])): ?>
                                <span class="field__error"><?= e($errors['customer_id']) ?></span>
                            <?php endif; ?>
                            <span class="field__hint">
                                New face? <a href="<?= e(url('customers/new')) ?>">Add a customer first</a>.
                            </span>
                        </div>

                        <div class="field">
                            <label for="employee_id">Assigned to</label>
                            <div class="select-wrap">
                                <select class="select" id="employee_id" name="employee_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= (int) $employee['id'] ?>"<?= selected($value('employee_id'), $employee['id']) ?>>
                                            <?= e($employee['name']) ?> · <?= e((string) $employee['role']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="status">Status</label>
                            <div class="select-wrap">
                                <select class="select" id="status" name="status">
                                    <?php foreach (ORDER_STATUSES as $status): ?>
                                        <option value="<?= e($status) ?>"<?= selected($value('status'), $status) ?>>
                                            <?= e(status_meta($status)['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="pickup_date">Picked up</label>
                            <input class="input" type="date" id="pickup_date" name="pickup_date" value="<?= e($value('pickup_date')) ?>">
                        </div>

                        <div class="field">
                            <label for="delivery_date">Promised</label>
                            <input class="input" type="date" id="delivery_date" name="delivery_date" value="<?= e($value('delivery_date')) ?>">
                        </div>

                        <div class="field field--full">
                            <label for="notes">Notes for the crew</label>
                            <textarea class="textarea" id="notes" name="notes" rows="2" placeholder="Gate code, stains, do-not-use fabric softener..."><?= e($value('notes')) ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card card--flat">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">Line items</p>
                        <h2>What are we cleaning?</h2>
                    </div>
                    <button class="btn btn--mint btn--sm" type="button" id="add-line">
                        <?= icon('plus', 16) ?><span>Add line</span>
                    </button>
                </div>
                <div class="card__body">
                    <?php if (isset($errors['items'])): ?>
                        <p class="field__error"><?= e($errors['items']) ?></p>
                    <?php endif; ?>

                    <div class="order-lines-editor">
                        <div class="lines-head">
                            <span>Service</span>
                            <span>Qty</span>
                            <span>Unit price</span>
                            <span>Line total</span>
                            <span></span>
                        </div>
                        <div id="line-rows">
                            <?php foreach (array_values($lines) as $index => $line): ?>
                                <div class="line-row">
                                    <div class="field">
                                        <label class="label">Service <span class="muted mono" data-line-index><?= $index + 1 ?></span></label>
                                        <div class="select-wrap">
                                            <select class="select" name="item_service[]">
                                                <option value="" data-price="0" data-unit="">— none —</option>
                                                <?php foreach ($services as $service): ?>
                                                    <option value="<?= (int) $service['id'] ?>"
                                                            data-price="<?= e(number_format((float) $service['price'], 2, '.', '')) ?>"
                                                            data-unit="<?= e((string) $service['unit']) ?>"
                                                        <?= selected($line['service_id'], $service['id']) ?>>
                                                        <?= e($service['name']) ?> (<?= e(money($service['price'])) ?>/<?= e((string) $service['unit']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="label">Qty</label>
                                        <input class="input input--num" type="number" name="item_qty[]" min="0" step="0.5"
                                               value="<?= e((string) $line['qty']) ?>">
                                    </div>
                                    <div class="field">
                                        <label class="label">Price <span class="muted mono" data-unit></span></label>
                                        <input class="input input--num" type="number" name="item_price[]" min="0" step="1"
                                               value="<?= e((string) $line['price']) ?>">
                                    </div>
                                    <p class="line-row__total" data-line-total>0.00</p>
                                    <button class="btn btn--danger btn--icon btn--sm line-row__remove" type="button"
                                            data-remove-line aria-label="Remove line"><?= icon('x', 16) ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="stack">
            <section class="card card--yellow">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">Money</p>
                        <h2>Ticket total</h2>
                    </div>
                </div>
                <div class="card__body">
                    <div class="totals">
                        <div class="totals__row">
                            <span>Subtotal</span>
                            <span class="mono"><?= e(config('currency')) ?> <b data-subtotal>0.00</b></span>
                        </div>
                        <div class="field">
                            <label for="discount">Discount</label>
                            <input class="input input--num" type="number" id="discount" name="discount" min="0" step="1" value="<?= e($value('discount')) ?>">
                        </div>
                        <div class="field">
                            <label for="delivery_fee">Delivery fee</label>
                            <input class="input input--num" type="number" id="delivery_fee" name="delivery_fee" min="0" step="1" value="<?= e($value('delivery_fee')) ?>">
                        </div>
                        <div class="totals__row totals__row--grand">
                            <span>Total</span>
                            <span class="mono"><?= e(config('currency')) ?> <b data-total>0.00</b></span>
                        </div>
                    </div>
                </div>
                <div class="card__foot">
                    <button class="btn btn--ink btn--block" type="submit">
                        <?= icon('check', 18) ?><span><?= $isEdit ? 'Save changes' : 'Save order' ?></span>
                    </button>
                </div>
            </section>

            <section class="card card--flat">
                <div class="card__body stack--sm">
                    <p class="eyebrow">Price list</p>
                    <?php foreach ($services as $service): ?>
                        <div class="row row--between">
                            <span class="small"><?= e($service['name']) ?></span>
                            <span class="mono small"><?= e(money($service['price'])) ?>/<?= e((string) $service['unit']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <a class="btn btn--ghost btn--sm btn--block" href="<?= e(url('services')) ?>">Manage services</a>
                </div>
            </section>
        </div>
    </div>
</form>

<template id="line-template">
    <div class="line-row">
        <div class="field">
            <label class="label">Service <span class="muted mono" data-line-index></span></label>
            <div class="select-wrap">
                <select class="select" name="item_service[]">
                    <option value="" data-price="0" data-unit="">— none —</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['id'] ?>"
                                data-price="<?= e(number_format((float) $service['price'], 2, '.', '')) ?>"
                                data-unit="<?= e((string) $service['unit']) ?>">
                            <?= e($service['name']) ?> (<?= e(money($service['price'])) ?>/<?= e((string) $service['unit']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label class="label">Qty</label>
            <input class="input input--num" type="number" name="item_qty[]" min="0" step="0.5" value="1">
        </div>
        <div class="field">
            <label class="label">Price <span class="muted mono" data-unit></span></label>
            <input class="input input--num" type="number" name="item_price[]" min="0" step="1" value="0">
        </div>
        <p class="line-row__total" data-line-total>0.00</p>
        <button class="btn btn--danger btn--icon btn--sm line-row__remove" type="button" data-remove-line aria-label="Remove line"><?= icon('x', 16) ?></button>
    </div>
</template>
