<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require 'db.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ── ADD / EDIT CUSTOMER LOG ───────────────────────────────────────────
    if ($action === 'add' || $action === 'edit') {
        $name = $_POST['customer_name'];
        $type = $_POST['customer_type'];
        $overnight = $_POST['overnight'];
        $accommodation = $_POST['accommodation'];
        $contact = $_POST['contact_number'];
        $payment = $_POST['payment_status'] ?? 'Unpaid';
        $payment_method = $_POST['payment_method'] ?? 'Cash';  // used in payment_transactions only
        $check_in = $_POST['check_in_time'] ?? date('Y-m-d H:i:s');
        $notes = $_POST['notes'] ?? '';

        // Financials
        $entrance_fee = floatval($_POST['entrance_fee'] ?? 0);
        $acc_fee = floatval($_POST['accommodation_fee'] ?? 0);

        // Pax breakdown
        $adults = intval($_POST['adults'] ?? 0);
        $seniors = intval($_POST['seniors'] ?? 0);
        $children = intval($_POST['children'] ?? 0);
        $pax = $adults + $seniors + $children;

        // Amount paid this transaction (optional on edit, encouraged on add)
        $amount_paid = (float) ($_POST['amount_paid'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');

        if ($action === 'add') {
            $stmt = $pdo->prepare(
                "INSERT INTO customer_logs
                    (customer_name, pax, adults, seniors, children, customer_type,
                    overnight, accommodation, contact_number,
                    entrance_fee, accommodation_fee,
                    payment_status, check_in_time, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $name,
                $pax,
                $adults,
                $seniors,
                $children,
                $type,
                $overnight,
                $accommodation,
                $contact,
                $entrance_fee,
                $acc_fee,
                $payment,
                $check_in,
                $notes
            ]);
            $new_log_id = (int) $pdo->lastInsertId();

            // Record initial payment transaction if amount was provided
            if ($amount_paid > 0) {
                $pdo->prepare(
                    "INSERT INTO payment_transactions
                         (customer_log_id, amount_paid, payment_method, remarks)
                     VALUES (?, ?, ?, ?)"
                )->execute([
                            $new_log_id,
                            $amount_paid,
                            $payment_method,
                            $remarks !== '' ? $remarks : null
                        ]);
            }

            // Mark accommodations as Active
            if (!empty($accommodation)) {
                foreach (array_map('trim', explode(',', $accommodation)) as $accName) {
                    if ($accName !== '') {
                        $pdo->prepare("UPDATE accommodations SET status = 'Active' WHERE CONCAT(type, ' ', number) = ?")
                            ->execute([$accName]);
                    }
                }
            }

        } else {
            // EDIT
            $id = $_POST['customer_id'];
            $stmt = $pdo->prepare(
                "UPDATE customer_logs
                 SET customer_name=?, pax=?, adults=?, seniors=?, children=?,
                     customer_type=?, overnight=?, accommodation=?, contact_number=?,
                     entrance_fee=?, accommodation_fee=?,
                     payment_status=?, check_in_time=?, notes=?
                 WHERE id=?"
            );
            $stmt->execute([
                $name,
                $pax,
                $adults,
                $seniors,
                $children,
                $type,
                $overnight,
                $accommodation,
                $contact,
                $entrance_fee,
                $acc_fee,
                $payment,
                $check_in,
                $notes,
                $id
            ]);

            // Record payment transaction if an amount was entered
            if ($amount_paid > 0) {
                $pdo->prepare(
                    "INSERT INTO payment_transactions
                         (customer_log_id, amount_paid, payment_method, remarks)
                     VALUES (?, ?, ?, ?)"
                )->execute([
                            $id,
                            $amount_paid,
                            $payment_method,
                            $remarks !== '' ? $remarks : null
                        ]);
            }
        }

        header("Location: logbook.php");
        exit;
    }

    // ── CHECKOUT ─────────────────────────────────────────────────────────
    elseif ($action === 'checkout') {
        $customer_id = $_POST['customer_id'];

        // Free up rooms
        $stmt = $pdo->prepare("SELECT accommodation FROM customer_logs WHERE id = ?");
        $stmt->execute([$customer_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($log && !empty($log['accommodation'])) {
            foreach (array_map('trim', explode(',', $log['accommodation'])) as $accName) {
                if ($accName !== '') {
                    $pdo->prepare("UPDATE accommodations SET status = 'Open' WHERE CONCAT(type, ' ', number) = ?")
                        ->execute([$accName]);
                }
            }
        }

        $pdo->prepare("UPDATE customer_logs SET check_out_time = NOW() WHERE id = ?")
            ->execute([$customer_id]);

        header("Location: logbook.php");
        exit;
    }

    // ── DELETE CUSTOMER LOG ───────────────────────────────────────────────
    elseif ($action === 'delete') {
        $customer_id = $_POST['customer_id'];

        // Free up rooms if still active
        $stmt = $pdo->prepare("SELECT accommodation, check_out_time FROM customer_logs WHERE id = ?");
        $stmt->execute([$customer_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($log && is_null($log['check_out_time']) && !empty($log['accommodation'])) {
            foreach (array_map('trim', explode(',', $log['accommodation'])) as $accName) {
                if ($accName !== '') {
                    $pdo->prepare("UPDATE accommodations SET status = 'Open' WHERE CONCAT(type, ' ', number) = ?")
                        ->execute([$accName]);
                }
            }
        }

        $pdo->prepare("DELETE FROM customer_logs WHERE id = ?")->execute([$customer_id]);

        header("Location: logbook.php");
        exit;
    }

    // ── ACCOMMODATION ACTIONS ─────────────────────────────────────────────
    elseif ($action === 'add_acc' || $action === 'edit_acc') {
        $type = $_POST['type'];
        $number = $_POST['number'];
        $price = $_POST['price_per_day'];
        $status = $_POST['status'];
        $notes = $_POST['notes'];

        if ($action === 'add_acc') {
            $pdo->prepare("INSERT INTO accommodations (type, number, price_per_day, status, notes) VALUES (?, ?, ?, ?, ?)")
                ->execute([$type, $number, $price, $status, $notes]);
        } else {
            $pdo->prepare("UPDATE accommodations SET type=?, number=?, price_per_day=?, status=?, notes=? WHERE id=?")
                ->execute([$type, $number, $price, $status, $notes, $_POST['acc_id']]);
        }
        header("Location: accommodations.php");
        exit;

    } elseif ($action === 'delete_acc') {
        $pdo->prepare("DELETE FROM accommodations WHERE id = ?")->execute([$_POST['acc_id']]);
        header("Location: accommodations.php");
        exit;

    } elseif ($action === 'reserve_acc' || $action === 'edit_reserve') {
        $name = $_POST['reserve_name'];
        $acc_name = $_POST['acc_name'];
        $check_in = $_POST['reserve_date'];
        $check_out = $_POST['reserve_end_date'];

        if ($action === 'reserve_acc') {
            $pdo->prepare(
                "INSERT INTO customer_logs
                    (customer_name, pax, customer_type, overnight, accommodation,
                     contact_number, check_in_time, check_out_time)
                 VALUES (?, 0, 'Reservation', 'Yes', ?, 'Pending', ?, ?)"
            )->execute([$name, $acc_name, $check_in, $check_out]);

            $pdo->prepare("UPDATE accommodations SET status = 'Reserved' WHERE id = ?")
                ->execute([$_POST['acc_id']]);
        } else {
            $pdo->prepare(
                "UPDATE customer_logs
                 SET customer_name = ?, check_in_time = ?, check_out_time = ?
                 WHERE accommodation = ? AND customer_type = 'Reservation' AND check_in_time > NOW()"
            )->execute([$name, $check_in, $check_out, $acc_name]);
        }
        header("Location: accommodations.php");
        exit;

    } elseif ($action === 'remove_reserve') {
        $acc_name = $_POST['acc_name'];
        $pdo->prepare(
            "DELETE FROM customer_logs
             WHERE accommodation = ? AND customer_type = 'Reservation' AND check_in_time > NOW()"
        )->execute([$acc_name]);
        $pdo->prepare("UPDATE accommodations SET status = 'Open' WHERE id = ?")
            ->execute([$_POST['acc_id']]);
        header("Location: accommodations.php");
        exit;
    }

    // ── UPDATE PAYMENT (payments.php) ─────────────────────────────────────
    // Single authoritative handler — records fees + logs the transaction.
    elseif ($action === 'update_payment') {
        $customerId = (int) $_POST['customer_id'];
        $entranceFee = (float) ($_POST['entrance_fee'] ?? 0);
        $accommodationFee = (float) ($_POST['accommodation_fee'] ?? 0);
        $totalAmount = $entranceFee + $accommodationFee;
        $paymentStatus = $_POST['payment_status'];
        $amountPaid = (float) ($_POST['amount_paid'] ?? 0);
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $remarks = trim($_POST['remarks'] ?? '');

        // 1. Update the customer_logs row
        $pdo->prepare(
            "UPDATE customer_logs
             SET entrance_fee      = ?,
                 accommodation_fee = ?,
                 payment_status    = ?
             WHERE id = ?"
        )->execute([
                    $entranceFee,
                    $accommodationFee,
                    $paymentStatus,
                    $customerId
                ]);

        // 2. Insert a transaction row if money was paid
        if ($amountPaid > 0) {
            $pdo->prepare(
                "INSERT INTO payment_transactions
                     (customer_log_id, amount_paid, payment_method, remarks)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                        $customerId,
                        $amountPaid,
                        $paymentMethod,
                        $remarks !== '' ? $remarks : null
                    ]);
        }

        header("Location: payments.php");
        exit;
    }

    // ── SETTINGS ──────────────────────────────────────────────────────────
    elseif ($action === 'update_settings') {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'fee_senior'")
            ->execute([$_POST['fee_senior']]);

        foreach ([
            'fee_adult_day',
            'fee_child_day',
            'fee_senior_day',
            'fee_adult_overnight',
            'fee_child_overnight',
            'fee_senior_overnight'
        ] as $key) {
            $val = $_POST[$key] ?? 0;
            $pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?"
            )->execute([$key, $val, $val]);
        }

        header("Location: settings.php?success=1");
        exit;
    }
}