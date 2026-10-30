<?php
declare(strict_types=1);

$editId = (int) query('edit', '0');
$isNew = query('new') === '1';
$errors = error_bag();

$employee = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM employees WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $employee = $stmt->fetch() ?: null;
    if ($employee === null) {
        flash('error', 'That staff member is not on file.');
        redirect('employees');
    }
}

$showForm = $isNew || $employee !== null;
$value = static function (string $key, string $default = '') use ($employee): string {
    return old($key, (string) ($employee[$key] ?? $default));
};

$employees = employees_list();
$monthFrom = date('Y-m-01 00:00:00');
$monthTo = date('Y-m-01 00:00:00', strtotime('first day of next month'));
$performance = [];
foreach (employee_performance($monthFrom, $monthTo) as $row) {
    $performance[(int) $row['id']] = $row;
}
$activeCount = 0;
foreach ($employees as $row) {
    if ((int) $row['active'] === 1) {
        $activeCount++;
    }
}
?>
<section class="section-title">
    <div>
        <p class="eyebrow"><?= count($employees) ?> people</p>
        <h2>Staff</h2>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= e(url('employees', ['new' => 1])) ?>"><?= icon('plus', 16) ?><span>Add staff</span></a>
    </div>
</section>

<div class="grid grid--3">
    <article class="stat stat--yellow">
        <p class="stat__label">Headcount</p>
        <p class="stat__value"><?= count($employees) ?></p>
        <p class="stat__foot">On the roster</p>
    </article>
    <article class="stat stat--mint">
        <p class="stat__label">Active</p>
        <p class="stat__value"><?= $activeCount ?></p>
        <p class="stat__foot">Currently working</p>
    </article>
    <article class="stat stat--blue">
        <p class="stat__label">This month</p>
        <p class="stat__value"><?= e(date('M')) ?></p>
        <p class="stat__foot"><?= e(date('Y')) ?> performance below</p>
    </article>
</div>

<div class="grid grid--main">
    <section class="card card--flat">
        <div class="card__head">
            <div>
                <p class="eyebrow">Roster</p>
                <h2>The team</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th class="num">Orders (mo)</th>
                        <th class="num">Revenue (mo)</th>
                        <th>State</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employees === []): ?>
                        <tr><td colspan="7">No staff on file yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($employees as $row): ?>
                        <?php $perf = $performance[(int) $row['id']] ?? null; ?>
                        <tr>
                            <td>
                                <span class="row">
                                    <span class="avatar avatar--sm"><?= e(initials((string) $row['name'])) ?></span>
                                    <span>
                                        <span class="table__title"><?= e($row['name']) ?></span>
                                        <span class="table__sub">Since <?= e(fmt_date($row['hired_on'])) ?></span>
                                    </span>
                                </span>
                            </td>
                            <td><span class="tag"><?= e((string) $row['role']) ?></span></td>
                            <td>
                                <?= e((string) ($row['phone'] ?? '')) ?: '—' ?>
                                <span class="table__sub"><?= e((string) ($row['email'] ?? '')) ?></span>
                            </td>
                            <td class="num mono"><?= $perf ? (int) $perf['orders'] : 0 ?></td>
                            <td class="num mono"><?= e(money($perf['revenue'] ?? 0)) ?></td>
                            <td>
                                <span class="<?= (int) $row['active'] === 1 ? 'pill pill--good' : 'pill' ?>">
                                    <?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a class="btn btn--ghost btn--sm" href="<?= e(url('employees', ['edit' => $row['id']])) ?>"><?= icon('edit', 14) ?></a>
                                <?php if (is_admin()): ?>
                                    <form method="post" action="<?= e(url('employees')) ?>" data-confirm="Remove <?= e((string) $row['name']) ?>?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="employee.delete">
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
                        <p class="eyebrow"><?= $employee !== null ? 'Editing #' . (int) $employee['id'] : 'New hire' ?></p>
                        <h2><?= $employee !== null ? 'Edit staff' : 'Add staff' ?></h2>
                    </div>
                </div>
                <form method="post" action="<?= e(url('employees')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee.save">
                    <input type="hidden" name="id" value="<?= $employee !== null ? (int) $employee['id'] : 0 ?>">
                    <div class="card__body">
                        <div class="stack--sm">
                            <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                                <label for="name">Full name</label>
                                <input class="input" type="text" id="name" name="name" value="<?= e($value('name')) ?>" placeholder="Mercy Njeri" required>
                                <?php if (isset($errors['name'])): ?>
                                    <span class="field__error"><?= e($errors['name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="field<?= isset($errors['role']) ? ' has-error' : '' ?>">
                                <label for="role">Role</label>
                                <input class="input" type="text" id="role" name="role" value="<?= e($value('role')) ?>" placeholder="Washer, presser, driver" required>
                                <?php if (isset($errors['role'])): ?>
                                    <span class="field__error"><?= e($errors['role']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="phone">Phone</label>
                                    <input class="input" type="tel" id="phone" name="phone" value="<?= e($value('phone')) ?>">
                                </div>
                                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                                    <label for="email">Email</label>
                                    <input class="input" type="email" id="email" name="email" value="<?= e($value('email')) ?>">
                                    <?php if (isset($errors['email'])): ?>
                                        <span class="field__error"><?= e($errors['email']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field">
                                    <label for="hired_on">Hired on</label>
                                    <input class="input" type="date" id="hired_on" name="hired_on" value="<?= e($value('hired_on', today())) ?>">
                                </div>
                                <div class="field">
                                    <label for="active">State</label>
                                    <div class="select-wrap">
                                        <select class="select" id="active" name="active">
                                            <option value="1"<?= selected($value('active', '1'), '1') ?>>Active</option>
                                            <option value="0"<?= selected($value('active', '1'), '0') ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card__foot">
                        <div class="btn-row">
                            <button class="btn btn--ink" type="submit"><?= icon('check', 16) ?><span><?= $employee !== null ? 'Save' : 'Add staff' ?></span></button>
                            <a class="btn btn--ghost" href="<?= e(url('employees')) ?>">Cancel</a>
                        </div>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <section class="card card--ink">
                <div class="card__head">
                    <div>
                        <p class="eyebrow">Month to date</p>
                        <h2>Who is crushing it</h2>
                    </div>
                </div>
                <div class="card__body">
                    <ol class="rank">
                        <?php $ranked = array_slice(array_values($performance), 0, 5); ?>
                        <?php if ($ranked === []): ?>
                            <li class="small">No assigned orders this month yet.</li>
                        <?php endif; ?>
                        <?php foreach ($ranked as $index => $row): ?>
                            <li>
                                <span class="rank__pos"><?= $index + 1 ?></span>
                                <span class="rank__name"><?= e((string) $row['name']) ?></span>
                                <span class="rank__value mono"><?= e(money($row['revenue'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <a class="btn btn--yellow btn--block" href="<?= e(url('employees', ['new' => 1])) ?>"><?= icon('plus', 16) ?><span>Add staff member</span></a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</div>
