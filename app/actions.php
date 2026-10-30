<?php
declare(strict_types=1);

const ORDER_STATUSES = ['received', 'processing', 'ready', 'delivered', 'cancelled'];
const PAYMENT_METHODS = ['cash', 'mpesa', 'card', 'bank', 'other'];
const SERVICE_UNITS = ['kg', 'piece', 'set', 'pair'];

function handle_action(): void
{
    $action = post('action');
    verify_csrf();

    switch ($action) {
        case 'login':
            action_login();
            break;
        case 'logout':
            logout();
            redirect('login');
        case 'customer.save':
            action_customer_save();
            break;
        case 'customer.delete':
            action_customer_delete();
            break;
        case 'service.save':
            action_service_save();
            break;
        case 'service.delete':
            action_service_delete();
            break;
        case 'employee.save':
            action_employee_save();
            break;
        case 'employee.delete':
            action_employee_delete();
            break;
        case 'order.save':
            action_order_save();
            break;
        case 'order.status':
            action_order_status();
            break;
        case 'order.delete':
            action_order_delete();
            break;
        case 'payment.save':
            action_payment_save();
            break;
        case 'payment.delete':
            action_payment_delete();
            break;
        case 'settings.save':
            action_settings_save();
            break;
        default:
            flash('error', 'Unknown action received.');
            redirect('dashboard');
    }
}

function action_login(): void
{
    $email = post('email');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('error', 'Put in your email and password first.');
        set_old(['email' => $email]);
        redirect('login');
    }

    if (!attempt_login($email, $password)) {
        flash('error', 'Nope. That email and password do not match.');
        set_old(['email' => $email]);
        redirect('login');
    }

    $intended = $_SESSION['intended'] ?? 'dashboard';
    unset($_SESSION['intended']);
    clear_old();
    flash('success', 'Welcome back. Let us get these clothes clean.');
    redirect(in_array($intended, known_routes(), true) ? $intended : 'dashboard');
}

function action_customer_save(): void
{
    require_auth();
    $id = (int) post('id');
    $name = post('name');
    $phone = post('phone');
    $email = post('email');
    $address = post('address');
    $notes = post('notes');

    $errors = [];
    if ($name === '') {
        $errors['name'] = 'Customer name is required.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address is malformed.';
    }
    if ($phone !== '' && strlen(preg_replace('/\D+/', '', $phone) ?? '') < 7) {
        $errors['phone'] = 'Phone number needs at least 7 digits.';
    }

    if ($errors !== []) {
        set_errors($errors);
        set_old($_POST);
        flash('error', 'Some fields need attention.');
        redirect($id > 0 ? 'customers/edit' : 'customers/new', $id > 0 ? ['id' => $id] : []);
    }

    $pdo = db();
    if ($id > 0) {
        $pdo->prepare('UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, notes = ? WHERE id = ?')
            ->execute([$name, $phone, $email, $address, $notes, $id]);
        log_activity('Updated customer', $name);
        flash('success', 'Customer updated.');
    } else {
        $pdo->prepare('INSERT INTO customers (code, name, phone, email, address, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([next_customer_code(), $name, $phone, $email, $address, $notes, now()]);
        log_activity('Created customer', $name);
        flash('success', 'Customer added to the books.');
    }

    clear_old();
    redirect('customers');
}

function action_customer_delete(): void
{
    require_admin();
    $id = (int) post('id');
    $stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'That customer has orders. Archive is not available, so deletion is blocked.');
        redirect('customers');
    }
    db()->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    log_activity('Deleted customer', '#' . $id);
    flash('success', 'Customer removed.');
    redirect('customers');
}

function action_service_save(): void
{
    require_admin();
    $id = (int) post('id');
    $name = post('name');
    $unit = post('unit', 'piece');
    $price = to_float(post('price'));
    $turnaround = max(1, (int) to_float(post('turnaround_hours')));
    $description = post('description');
    $active = post('active') === '1' ? 1 : 0;

    $errors = [];
    if ($name === '') {
        $errors['name'] = 'Service name is required.';
    }
    if (!in_array($unit, SERVICE_UNITS, true)) {
        $errors['unit'] = 'Pick a valid unit.';
    }
    if ($price < 0) {
        $errors['price'] = 'Price cannot be negative.';
    }

    if ($errors !== []) {
        set_errors($errors);
        set_old($_POST);
        flash('error', 'Service could not be saved.');
        redirect('services', $id > 0 ? ['edit' => $id] : ['new' => 1]);
    }

    $pdo = db();
    if ($id > 0) {
        $pdo->prepare('UPDATE services SET name = ?, unit = ?, price = ?, turnaround_hours = ?, description = ?, active = ? WHERE id = ?')
            ->execute([$name, $unit, $price, $turnaround, $description, $active, $id]);
        log_activity('Updated service', $name);
        flash('success', 'Service updated.');
    } else {
        $pdo->prepare('INSERT INTO services (name, unit, price, turnaround_hours, description, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $unit, $price, $turnaround, $description, $active, now()]);
        log_activity('Created service', $name);
        flash('success', 'Service added to the price list.');
    }

    clear_old();
    redirect('services');
}

function action_service_delete(): void
{
    require_admin();
    $id = (int) post('id');
    $stmt = db()->prepare('SELECT COUNT(*) FROM order_items WHERE service_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        db()->prepare('UPDATE services SET active = 0 WHERE id = ?')->execute([$id]);
        flash('error', 'This service is used by orders, so it was archived instead of deleted.');
        redirect('services');
    }
    db()->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
    log_activity('Deleted service', '#' . $id);
    flash('success', 'Service deleted.');
    redirect('services');
}

function action_employee_save(): void
{
    require_admin();
    $id = (int) post('id');
    $name = post('name');
    $role = post('role');
    $phone = post('phone');
    $email = post('email');
    $hiredOn = post('hired_on') ?: null;
    $active = post('active') === '1' ? 1 : 0;

    $errors = [];
    if ($name === '') {
        $errors['name'] = 'Staff name is required.';
    }
    if ($role === '') {
        $errors['role'] = 'Give this person a role.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address is malformed.';
    }

    if ($errors !== []) {
        set_errors($errors);
        set_old($_POST);
        flash('error', 'Staff record could not be saved.');
        redirect('employees', $id > 0 ? ['edit' => $id] : ['new' => 1]);
    }

    $pdo = db();
    if ($id > 0) {
        $pdo->prepare('UPDATE employees SET name = ?, role = ?, phone = ?, email = ?, hired_on = ?, active = ? WHERE id = ?')
            ->execute([$name, $role, $phone, $email, $hiredOn, $active, $id]);
        log_activity('Updated staff', $name);
        flash('success', 'Staff record updated.');
    } else {
        $pdo->prepare('INSERT INTO employees (name, role, phone, email, hired_on, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$name, $role, $phone, $email, $hiredOn, $active, now()]);
        log_activity('Added staff', $name);
        flash('success', 'Staff member added.');
    }

    clear_old();
    redirect('employees');
}

function action_employee_delete(): void
{
    require_admin();
    $id = (int) post('id');
    $stmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE employee_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        db()->prepare('UPDATE employees SET active = 0 WHERE id = ?')->execute([$id]);
        flash('error', 'This staff member has orders attached, so they were deactivated instead.');
        redirect('employees');
    }
    db()->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
    log_activity('Deleted staff', '#' . $id);
    flash('success', 'Staff member removed.');
    redirect('employees');
}

function action_order_save(): void
{
    require_auth();
    $id = (int) post('id');
    $customerId = (int) post('customer_id');
    $employeeRaw = post('employee_id');
    $employeeId = $employeeRaw === '' ? null : (int) $employeeRaw;
    $status = post('status', 'received');
    $pickup = post('pickup_date') ?: today();
    $delivery = post('delivery_date') ?: null;
    $notes = post('notes');
    $discount = max(0.0, to_float(post('discount')));
    $deliveryFee = max(0.0, to_float(post('delivery_fee')));

    $errors = [];
    $customerStmt = db()->prepare('SELECT id, name FROM customers WHERE id = ?');
    $customerStmt->execute([$customerId]);
    if (!$customerStmt->fetch()) {
        $errors['customer_id'] = 'Pick a customer for this order.';
    }
    if (!in_array($status, ORDER_STATUSES, true)) {
        $status = 'received';
    }

    $serviceRows = [];
    foreach (services_list() as $service) {
        $serviceRows[(int) $service['id']] = $service;
    }

    $items = [];
    $rawItems = [];
    $postedServices = $_POST['item_service'] ?? [];
    $postedQty = $_POST['item_qty'] ?? [];
    $postedPrice = $_POST['item_price'] ?? [];
    if (is_array($postedServices)) {
        foreach ($postedServices as $index => $serviceId) {
            $sid = (int) $serviceId;
            $qty = to_float($postedQty[$index] ?? 0);
            $price = to_float($postedPrice[$index] ?? 0);
            if ($sid <= 0 && $qty <= 0) {
                continue;
            }
            $rawItems[] = ['service_id' => $sid, 'qty' => $qty, 'price' => $price];
            if (!isset($serviceRows[$sid])) {
                $errors['items'] = 'One of the selected services no longer exists.';
                continue;
            }
            if ($qty <= 0) {
                $errors['items'] = 'Every service line needs a quantity above zero.';
                continue;
            }
            $items[] = [
                'service_id' => $sid,
                'label'      => $serviceRows[$sid]['name'],
                'unit'       => $serviceRows[$sid]['unit'],
                'qty'        => $qty,
                'unit_price' => $price,
                'line_total' => round($qty * $price, 2),
            ];
        }
    }
    if ($items === [] && !isset($errors['items'])) {
        $errors['items'] = 'Add at least one service line.';
    }

    if ($errors !== []) {
        set_errors($errors);
        $old = $_POST;
        $old['items'] = $rawItems;
        set_old($old);
        flash('error', 'Order could not be saved. Check the highlighted bits.');
        redirect($id > 0 ? 'orders/edit' : 'orders/new', $id > 0 ? ['id' => $id] : []);
    }

    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += $item['line_total'];
    }
    $subtotal = round($subtotal, 2);
    $total = max(0.0, round($subtotal - $discount + $deliveryFee, 2));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $pdo->prepare(
                'UPDATE orders SET customer_id = ?, employee_id = ?, status = ?, pickup_date = ?, delivery_date = ?, notes = ?,
                 subtotal = ?, discount = ?, delivery_fee = ?, total = ?, updated_at = ? WHERE id = ?'
            )->execute([$customerId, $employeeId, $status, $pickup, $delivery, $notes, $subtotal, $discount, $deliveryFee, $total, now(), $id]);
            $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
            log_activity('Updated order', '#' . $id);
        } else {
            $pdo->prepare(
                'INSERT INTO orders (order_no, customer_id, employee_id, status, pickup_date, delivery_date, notes, subtotal, discount, delivery_fee, total, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                'TMP-' . bin2hex(random_bytes(4)), $customerId, $employeeId, $status, $pickup, $delivery, $notes,
                $subtotal, $discount, $deliveryFee, $total, auth_user()['id'] ?? null, now(), now(),
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE orders SET order_no = ? WHERE id = ?')
                ->execute(['ORD-' . date('Y') . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT), $id]);
            log_activity('Created order', 'ORD-' . date('Y') . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT));
        }

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, service_id, label, qty, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($items as $item) {
            $itemStmt->execute([$id, $item['service_id'], $item['label'], $item['qty'], $item['unit'], $item['unit_price'], $item['line_total']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Database rejected the order: ' . $e->getMessage());
        redirect($id > 0 ? 'orders/edit' : 'orders/new', $id > 0 && $id > 0 ? ['id' => $id] : []);
    }

    clear_old();
    flash('success', 'Order saved. Time to make it shine.');
    redirect('orders/show', ['id' => $id]);
}

function action_order_status(): void
{
    require_auth();
    $id = (int) post('id');
    $status = post('status');
    if (!in_array($status, ORDER_STATUSES, true)) {
        flash('error', 'That is not a valid order status.');
        redirect('orders');
    }
    db()->prepare('UPDATE orders SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, now(), $id]);
    $order = order_find($id);
    log_activity('Moved order to ' . status_meta($status)['label'], $order['order_no'] ?? ('#' . $id));
    flash('success', 'Order marked ' . status_meta($status)['label'] . '.');
    redirect_back('orders');
}

function action_order_delete(): void
{
    require_admin();
    $id = (int) post('id');
    $order = order_find($id);
    db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    log_activity('Deleted order', $order['order_no'] ?? ('#' . $id));
    flash('success', 'Order deleted along with its items and payments.');
    redirect('orders');
}

function action_payment_save(): void
{
    require_auth();
    $orderId = (int) post('order_id');
    $order = order_find($orderId);
    if (!$order) {
        flash('error', 'That order does not exist.');
        redirect('payments');
    }

    $total = (float) $order['total'];
    $paid = (float) $order['paid'];
    $balance = round($total - $paid, 2);
    $amount = to_float(post('amount'));
    $method = post('method', 'cash');
    $reference = post('reference');
    $note = post('note');
    $paidAt = post('paid_at');

    $errors = [];
    if ($amount <= 0) {
        $errors['amount'] = 'Payment amount must be greater than zero.';
    }
    if ($amount > $balance + 0.009) {
        $errors['amount'] = 'That is more than the outstanding balance of ' . money($balance) . '.';
    }
    if (!in_array($method, PAYMENT_METHODS, true)) {
        $errors['method'] = 'Choose a valid payment method.';
    }

    if ($errors !== []) {
        set_errors($errors);
        set_old($_POST);
        flash('error', 'Payment was not recorded.');
        redirect('orders/show', ['id' => $orderId]);
    }

    if ($paidAt !== '') {
        $ts = strtotime(str_replace('T', ' ', $paidAt));
        $paidAt = $ts ? date('Y-m-d H:i:s', $ts) : now();
    } else {
        $paidAt = now();
    }

    db()->prepare('INSERT INTO payments (order_id, amount, method, reference, note, paid_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$orderId, $amount, $method, $reference, $note, $paidAt, auth_user()['id'] ?? null, now()]);

    log_activity('Recorded payment of ' . money($amount), $order['order_no']);
    clear_old();
    flash('success', money($amount) . ' received on ' . $order['order_no'] . '.');
    redirect('orders/show', ['id' => $orderId]);
}

function action_payment_delete(): void
{
    require_admin();
    $paymentId = (int) post('id');
    $stmt = db()->prepare('SELECT * FROM payments WHERE id = ?');
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) {
        flash('error', 'Payment not found.');
        redirect('payments');
    }
    db()->prepare('DELETE FROM payments WHERE id = ?')->execute([$paymentId]);
    log_activity('Reversed payment of ' . money($payment['amount']), 'order #' . $payment['order_id']);
    flash('success', 'Payment reversed.');
    redirect('orders/show', ['id' => (int) $payment['order_id']]);
}

function action_settings_save(): void
{
    require_admin();
    $allowed = ['business_name', 'business_phone', 'business_email', 'business_address', 'receipt_footer', 'default_delivery_fee'];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $_POST)) {
            save_setting($key, post($key));
        }
    }
    log_activity('Updated settings');
    flash('success', 'Business settings saved.');
    redirect('settings');
}
