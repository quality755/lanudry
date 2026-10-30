<?php
declare(strict_types=1);

/**
 * Portable schema installer + demo seeder.
 * Uses MySQL when configured, SQLite otherwise.
 */
final class Schema
{
    public static function ensure(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $pdo = db();
        try {
            $pdo->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable) {
            self::install($pdo);
            self::seed($pdo);
        }
        $done = true;
    }

    public static function install(PDO $pdo): void
    {
        $mysql = db_is_mysql();
        $pk = $mysql
            ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        $statements = [
            "CREATE TABLE IF NOT EXISTS users (
                id {$pk},
                name VARCHAR(120) NOT NULL,
                email VARCHAR(160) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'staff',
                active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NULL,
                UNIQUE (email)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS customers (
                id {$pk},
                code VARCHAR(24) NOT NULL,
                name VARCHAR(160) NOT NULL,
                phone VARCHAR(40) NULL,
                email VARCHAR(160) NULL,
                address VARCHAR(255) NULL,
                notes VARCHAR(500) NULL,
                created_at DATETIME NULL,
                UNIQUE (code)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS services (
                id {$pk},
                name VARCHAR(140) NOT NULL,
                unit VARCHAR(16) NOT NULL DEFAULT 'piece',
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                turnaround_hours INTEGER NOT NULL DEFAULT 24,
                description VARCHAR(400) NULL,
                active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS employees (
                id {$pk},
                name VARCHAR(160) NOT NULL,
                role VARCHAR(100) NOT NULL,
                phone VARCHAR(40) NULL,
                email VARCHAR(160) NULL,
                hired_on DATE NULL,
                active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS orders (
                id {$pk},
                order_no VARCHAR(24) NOT NULL,
                customer_id INTEGER NOT NULL,
                employee_id INTEGER NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'received',
                pickup_date DATE NULL,
                delivery_date DATE NULL,
                notes VARCHAR(500) NULL,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
                discount DECIMAL(10,2) NOT NULL DEFAULT 0,
                delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL DEFAULT 0,
                created_by INTEGER NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE (order_no),
                FOREIGN KEY (customer_id) REFERENCES customers(id)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS order_items (
                id {$pk},
                order_id INTEGER NOT NULL,
                service_id INTEGER NULL,
                label VARCHAR(160) NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1,
                unit VARCHAR(16) NOT NULL DEFAULT 'piece',
                unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS payments (
                id {$pk},
                order_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                method VARCHAR(24) NOT NULL DEFAULT 'cash',
                reference VARCHAR(80) NULL,
                note VARCHAR(255) NULL,
                paid_at DATETIME NULL,
                created_by INTEGER NULL,
                created_at DATETIME NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS settings (
                skey VARCHAR(80) NOT NULL PRIMARY KEY,
                svalue TEXT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS activity (
                id {$pk},
                user_id INTEGER NULL,
                action VARCHAR(80) NOT NULL,
                detail VARCHAR(255) NULL,
                created_at DATETIME NULL
            ){$suffix}",
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function seed(PDO $pdo): void
    {
        $existing = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($existing > 0) {
            return;
        }

        $now = now();
        $insertUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, active, created_at) VALUES (?, ?, ?, ?, 1, ?)');
        $insertUser->execute(['Dennis Kariuki', 'admin@laundry.test', password_hash('admin123', PASSWORD_DEFAULT), 'admin', $now]);
        $adminId = (int) $pdo->lastInsertId();
        $insertUser->execute(['Mercy Njeri', 'staff@laundry.test', password_hash('staff123', PASSWORD_DEFAULT), 'staff', $now]);
        $staffId = (int) $pdo->lastInsertId();

        $services = [
            ['Wash & Fold', 'kg', 250.00, 24, 'Everyday laundry washed, dried and folded.'],
            ['Dry Cleaning', 'piece', 450.00, 48, 'Solvent clean for suits, dresses and delicate fabric.'],
            ['Ironing Only', 'piece', 120.00, 24, 'Crisp press and hang. No wash.'],
            ['Duvet & Bedding', 'set', 850.00, 48, 'Duvets, comforters, mattress protectors and sheets.'],
            ['Sneaker Revival', 'pair', 550.00, 48, 'Deep clean, deodorise and lace refresh.'],
            ['Curtain Care', 'kg', 400.00, 48, 'Curtains washed and pressed ready to hang.'],
            ['Express Wash', 'kg', 380.00, 6, 'Six hour turnaround for the last minute crowd.'],
            ['Stain Rescue', 'piece', 300.00, 24, 'Targeted treatment for oil, ink and grass stains.'],
        ];
        $insertService = $pdo->prepare('INSERT INTO services (name, unit, price, turnaround_hours, description, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
        $serviceIds = [];
        foreach ($services as $service) {
            $insertService->execute([$service[0], $service[1], $service[2], $service[3], $service[4], $now]);
            $serviceIds[$service[0]] = (int) $pdo->lastInsertId();
        }

        $customers = [
            ['Amani Wanjiku', '+254 712 345 678', 'amani.w@example.com', 'Kilimani, Nairobi', 'Prefers unscented detergent.'],
            ['Brian Otieno', '+254 733 901 224', 'brian.otieno@example.com', 'South B, Nairobi', ''],
            ['Faith Chepkoech', '+254 701 556 332', 'faith.c@example.com', 'Lavington, Nairobi', 'Deliver after 6pm only.'],
            ['Dennis Mwangi', '+254 720 884 115', 'dmwangi@example.com', 'Kileleshwa, Nairobi', ''],
            ['Halima Yusuf', '+254 745 220 906', 'halima.y@example.com', 'Eastleigh, Nairobi', 'Handle abayas with care.'],
            ['Kevin Kariuki', '+254 715 678 001', 'kev.kariuki@example.com', 'Westlands, Nairobi', ''],
            ['Naomi Achieng', '+254 799 112 334', 'naomi.a@example.com', 'Langata, Nairobi', 'Weekly pickups on Monday.'],
            ['Peter Njoroge', '+254 722 445 889', 'p.njoroge@example.com', 'Kasarani, Nairobi', ''],
        ];
        $insertCustomer = $pdo->prepare('INSERT INTO customers (code, name, phone, email, address, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $customerIds = [];
        foreach ($customers as $i => $customer) {
            $insertCustomer->execute([
                'CUST-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                $customer[0], $customer[1], $customer[2], $customer[3], $customer[4],
                date('Y-m-d H:i:s', strtotime('-' . (60 - $i * 5) . ' days')),
            ]);
            $customerIds[] = (int) $pdo->lastInsertId();
        }

        $employees = [
            ['Grace Muthoni', 'Lead Presser', '+254 711 222 333', 'grace@spincity.test', '2021-03-15'],
            ['Samuel Barasa', 'Wash Operator', '+254 722 333 444', 'samuel@spincity.test', '2022-07-01'],
            ['Joyce Wairimu', 'Front Desk', '+254 733 444 555', 'joyce@spincity.test', '2023-01-09'],
            ['Ali Hassan', 'Delivery Rider', '+254 744 555 666', 'ali@spincity.test', '2023-09-20'],
        ];
        $insertEmployee = $pdo->prepare('INSERT INTO employees (name, role, phone, email, hired_on, active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
        $employeeIds = [];
        foreach ($employees as $employee) {
            $insertEmployee->execute([$employee[0], $employee[1], $employee[2], $employee[3], $employee[4], $now]);
            $employeeIds[] = (int) $pdo->lastInsertId();
        }

        $orderSeeds = [
            [0, 0, 'received',   ['Wash & Fold' => 4.5, 'Ironing Only' => 3], 0, 0, 'Pickup from reception, gate code 2244.'],
            [0, 1, 'processing', ['Express Wash' => 3, 'Stain Rescue' => 2], 0, 0, 'Ink stain on the white shirt.'],
            [0, 2, 'ready',      ['Duvet & Bedding' => 2, 'Wash & Fold' => 6], 850, 150, 'Deliver after 6pm.'],
            [1, 3, 'ready',      ['Dry Cleaning' => 3], 1350, 0, 'Three suits, urgent for a wedding.'],
            [1, 0, 'delivered',  ['Wash & Fold' => 8], 2000, 0, ''],
            [2, 4, 'ready',      ['Sneaker Revival' => 2], 1100, 0, 'White Air Force 1s, yellowed soles.'],
            [3, 1, 'delivered',  ['Curtain Care' => 5.5, 'Ironing Only' => 2], 2440, 150, ''],
            [4, 2, 'delivered',  ['Wash & Fold' => 12], 3000, 0, 'Weekly office laundry.'],
            [5, 3, 'delivered',  ['Dry Cleaning' => 2, 'Stain Rescue' => 1], 1200, 0, ''],
            [7, 0, 'delivered',  ['Duvet & Bedding' => 1, 'Wash & Fold' => 3], 1600, 150, ''],
            [9, 1, 'cancelled',  ['Ironing Only' => 5], 0, 0, 'Customer travelled, cancelled before washing.'],
            [11, 2, 'delivered', ['Express Wash' => 7], 2660, 0, ''],
            [14, 3, 'delivered', ['Sneaker Revival' => 1, 'Stain Rescue' => 2], 1150, 150, ''],
            [17, 0, 'delivered', ['Curtain Care' => 4, 'Ironing Only' => 6], 2320, 0, ''],
            [21, 1, 'delivered', ['Wash & Fold' => 15], 3750, 0, ''],
            [25, 2, 'delivered', ['Dry Cleaning' => 4, 'Duvet & Bedding' => 1], 2650, 150, ''],
            [30, 3, 'delivered', ['Wash & Fold' => 9, 'Ironing Only' => 4], 2730, 0, ''],
            [37, 0, 'delivered', ['Express Wash' => 5], 1900, 0, ''],
            [44, 2, 'delivered', ['Duvet & Bedding' => 3], 2550, 150, 'Hotel linen restock.'],
        ];

        $prices = [];
        foreach ($services as $service) {
            $prices[$service[0]] = [$service[2], $service[1], $service[3]];
        }

        $orderStmt = $pdo->prepare('INSERT INTO orders (order_no, customer_id, employee_id, status, pickup_date, delivery_date, notes, subtotal, discount, delivery_fee, total, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, service_id, label, qty, unit, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $payStmt = $pdo->prepare('INSERT INTO payments (order_id, amount, method, reference, note, paid_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $methods = ['cash', 'mpesa', 'card', 'bank'];

        $seq = 1;
        foreach ($orderSeeds as $seed) {
            [$daysAgo, $employeeIndex, $status, $items, $paid, $deliveryFee, $notes] = $seed;
            $created = date('Y-m-d H:i:s', strtotime('-' . $daysAgo . ' days 09:15'));
            $pickup = date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
            $turnaround = 24;
            $subtotal = 0.0;
            $rows = [];
            foreach ($items as $label => $qty) {
                [$price, $unit, $hours] = $prices[$label];
                $line = round($price * $qty, 2);
                $subtotal += $line;
                $turnaround = max($turnaround, (int) $hours);
                $rows[] = [$serviceIds[$label], $label, $qty, $unit, $price, $line];
            }
            $total = round($subtotal + $deliveryFee, 2);
            $delivery = date('Y-m-d', strtotime('-' . $daysAgo . ' days +' . (int) ceil($turnaround / 24) . ' days'));

            $orderStmt->execute([
                'ORD-' . date('Y', strtotime($created)) . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
                $customerIds[$seed[0] === 0 && $status === 'received' ? 0 : ($seq % count($customerIds))],
                $employeeIds[$employeeIndex],
                $status,
                $pickup,
                $delivery,
                $notes,
                $subtotal,
                $status === 'cancelled' ? $subtotal : 0,
                $deliveryFee,
                $status === 'cancelled' ? 0 : $total,
                $adminId,
                $created,
                $created,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            foreach ($rows as $row) {
                $itemStmt->execute(array_merge([$orderId], $row));
            }

            if ($paid > 0) {
                $payStmt->execute([
                    $orderId, min((float) $paid, $total), $methods[$seq % count($methods)],
                    strtoupper($methods[$seq % count($methods)]) . '-' . str_pad((string) (1000 + $seq), 5, '0'),
                    '', date('Y-m-d H:i:s', strtotime($created . ' +2 hours')), $adminId,
                    date('Y-m-d H:i:s', strtotime($created . ' +2 hours')),
                ]);
            }
            $seq++;
        }

        $settings = [
            'business_name'    => 'Spin City Laundry',
            'business_phone'   => '+254 700 123 456',
            'business_email'   => 'hello@spincitylaundry.test',
            'business_address' => 'Mpaka Road, Industrial Area, Nairobi',
            'receipt_footer'   => 'Clothes clean. Standards cleaner. Thanks for riding with us.',
            'default_delivery_fee' => '150',
        ];
        $settingStmt = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)');
        foreach ($settings as $key => $value) {
            $settingStmt->execute([$key, $value]);
        }

        $activityStmt = $pdo->prepare('INSERT INTO activity (user_id, action, detail, created_at) VALUES (?, ?, ?, ?)');
        $activityStmt->execute([$adminId, 'Opened store', 'Demo workspace seeded', $now]);
        $activityStmt->execute([$staffId, 'Created order', 'ORD-' . date('Y') . '-001 for Amani Wanjiku', date('Y-m-d H:i:s', strtotime('-2 hours'))]);
    }
}
