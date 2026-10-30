<?php
declare(strict_types=1);

$editId = (int) query('edit', '0');
$isNew = query('new') === '1';
$errors = error_bag();

$service = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $service = $stmt->fetch() ?: null;
    if ($service === null) {
        flash('error', 'That service no longer exists.');
        redirect('services');
    }
}

$showForm = $isNew || $service !== null;
$value = static function (string $key, string $default = '') use ($service): string {
    return old($key, (string) ($service[$key] ?? $default));
};

$services = services_list();
$usage = [];
foreach (db()->query('SELECT service_id, COUNT(*) AS lines, COALESCE(SUM(qty),0) AS qty FROM order_items GROUP BY service_id') as $row) {
    $usage[(int) $row['service_id']] = $row;
}
$activeCount = 0;
foreach ($services as $row) {
    if ((int) $row['active'] === 1) {
        $activeCount++;
    }
}
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= count($services) ?> on the price list</p>
        <h2>Services</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url('services', ['new' => 1])) ?>"><?= icon('plus', 16) ?><span>New service</span></a>
    </div>
</section>

<div class="grid grid--3">
    <article class="stat stat--yellow">
        <p class="stat__label">Services</p>
        <p class="stat__value"><?= count($services) ?></p>
        <p class="stat__foot">All entries</p>
    </article>
    <article class="stat stat--mint">
        <p class="stat__label">Sellable</p>
        <p class="stat__value"><?= $activeCount ?></p>
        <p class="stat__foot">Active on the counter</p>
    </article>
    <article class="stat stat--blue">
        <p class="stat__label">Archived</p>
        <p class="stat__value"><?= count($services) - $activeCount ?></p>
        <p class="stat__foot">Hidden but kept for history</p>
    </article>
</div>

<div class="grid grid--main">
    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Price list</p>
                <h2>Everything you offer</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Unit</th>
                        <th class="num">Price</th>
                        <th class="num">Turnaround</th>
                        <th class="num">Sold</th>
                        <th>State</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($services === []): ?>
                        <tr><td colspan="7">No services yet. Add your first one.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($services as $row): ?>
                        <?php $rowUsage = $usage[(int) $row['id']] ?? null; ?>
                        <tr>
                            <td>
                                <span class="table__title"><?= e($row['name']) ?></span>
                                <span class="table__sub"><?= e((string) ($row['description'] ?? '')) ?></span>
                            </td>
                            <td class="mono small"><?= e((string) $row['unit']) ?></td>
                            <td class="num mono"><?= e(money($row['price'])) ?></td>
                            <td class="num mono"><?= (int) $row['turnaround_hours'] ?>h</td>
                            <td class="num mono"><?= $rowUsage ? e(number_format((float) $rowUsage['qty'], 0)) : '—' ?></td>
                            <td>
                                <span class="<?= (int) $row['active'] === 1 ? 'pill pill--good' : 'pill' ?>">
                                    <?= (int) $row['active'] === 1 ? 'Active' : 'Archived' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('services', ['edit' => $row['id']])) ?>"><?= icon('edit', 14) ?></a>
                                <?php if (is_admin()): ?>
                                    <form method="post" action="<?= e(url('services')) ?>" data-confirm="Delete <?= e((string) $row['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="service.delete">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
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

    <div class="stack">
        <?php if ($showForm): ?>
            <section class="card card--mint">
                <div class="card__head">
                    <div>
                        <p class="eyebrow"><?= $service !== null ? 'Editing #' . (int) $service['id'] : 'Brand new' ?></p>
                        <h2><?= $service !== null ? 'Edit service' : 'Add service' ?></h2>
                    </div>
                </div>
                <form method="post" action="<?= e(url('services')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="service.save">
                    <input type="hidden" name="id" value="<?= $service !== null ? (int) $service['id'] : 0 ?>">
                    <div class="card__body">
                        <div class="stack--sm">
                            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                                <label for="name">Name</label>
                                <input class="input" type="text" id="name" name="name" value="<?= e($value('name')) ?>" placeholder="Wash &amp; Fold" required>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="field__error"><?= e($errors['name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="unit">Unit</label>
                                    <div class="select-wrap">
                                        <select class="select" id="unit" name="unit">
                                            <?php foreach (SERVICE_UNITS as $unit): ?>
                                                <option value="<?= e($unit) ?>"<?= selected($value('unit', 'piece'), $unit) ?>><?= e($unit) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="field<?= isset($errors['price']) ? ' has-error' : '' ?>">
                                    <label for="price">Price</label>
                                    <input class="input input--num" type="number" id="price" name="price" step="0.01" min="0" value="<?= e($value('price', '0.00')) ?>">
                                    <?php if (isset($errors['price'])): ?>
                                        <span class="field__error"><?= e($errors['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="field">
                                <label for="turnaround_hours">Turnaround hours</label>
                                <input class="input input--num" type="number" id="turnaround_hours" name="turnaround_hours" min="1" max="336" value="<?= e($value('turnaround_hours', '24')) ?>">
                            </div>
                            <div class="field">
                                <label for="description">Description</label>
                                <textarea class="textarea" id="description" name="description" rows="3" placeholder="What is included?"><?= e($value('description')) ?></textarea>
                            </div>
                            <div class="field">
                                <label for="active">State</label>
                                <div class="select-wrap">
                                    <select class="select" id="active" name="active">
                                        <option value="1"<?= selected($value('active', '1'), '1') ?>>Active</option>
                                        <option value="0"<?= selected($value('active', '1'), '0') ?>>Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card__foot">
                        <div class="btn-row">
                            <button class="btn btn--ink" type="submit"><?= icon('check', 16) ?><span><?= $service !== null ? 'Save' : 'Add service' ?></span></button>
                            <a class="btn btn--ghost" href="<?= e(url('services')) ?>">Cancel</a>
                        </div>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section class="card card--ink">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">How pricing works</p>
                        <h2>Line items</h2>
                    </div>
                </div>
                <div class="card__body">
                    <p class="small">
                        Orders copy the service name, unit and price at the moment they are created, so
                        changing a price here never rewrites old tickets. Pick a row to edit it.
                    </p>
                    <a class="btn btn--yellow btn--block" href="<?= e(url('services', ['new' => 1])) ?>"><?= icon('plus', 16) ?><span>Add another service</span></a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
