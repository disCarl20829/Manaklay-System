<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'db.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // --- CUSTOMER LOG ACTIONS ---
    if ($action === 'add' || $action === 'edit') {
        $name = $_POST['customer_name'];
        $type = $_POST['customer_type'];
        $overnight = $_POST['overnight'];
        $accommodation = $_POST['accommodation'];
        $contact = $_POST['contact_number'];
        $payment = $_POST['payment_status'] ?? 'Unpaid';
        $check_in = $_POST['check_in_time'] ?? date('Y-m-d H:i:s');
        
        // Financials
        $entrance_fee = floatval($_POST['entrance_fee'] ?? 0);
        $acc_fee = floatval($_POST['accommodation_fee'] ?? 0);
        // Removed PHP $total_amount calculation since MySQL handles it

        // Pax Data
        $adults = intval($_POST['adults'] ?? 0);
        $seniors = intval($_POST['seniors'] ?? 0);
        $children = intval($_POST['children'] ?? 0);
        $pax = $adults + $seniors + $children;

        if ($action === 'add') {
            // Removed total_amount from INSERT statement
            $stmt = $pdo->prepare("INSERT INTO customer_logs (customer_name, pax, adults, seniors, children, customer_type, overnight, accommodation, contact_number, entrance_fee, accommodation_fee, payment_status, check_in_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $pax, $adults, $seniors, $children, $type, $overnight, $accommodation, $contact, $entrance_fee, $acc_fee, $payment, $check_in]);

            if (!empty($accommodation)) {
                $pdo->prepare("UPDATE accommodations SET status = 'Active' WHERE CONCAT(type, ' ', number) = ?")->execute([$accommodation]);
            }
        } else {
            $id = $_POST['customer_id'];
            // Removed total_amount from UPDATE statement
            $stmt = $pdo->prepare("UPDATE customer_logs SET customer_name=?, pax=?, adults=?, seniors=?, children=?, customer_type=?, overnight=?, accommodation=?, contact_number=?, entrance_fee=?, accommodation_fee=?, payment_status=?, check_in_time=? WHERE id=?");
            $stmt->execute([$name, $pax, $adults, $seniors, $children, $type, $overnight, $accommodation, $contact, $entrance_fee, $acc_fee, $payment, $check_in, $id]);
        }
        header("Location: logbook.php");
        exit;
    }
    
    // --- LOGBOOK CHECKOUT ---
    elseif ($action === 'checkout') {
        $customer_id = $_POST['customer_id'];

        // 1. Find which room they were in to free it up
        $stmt = $pdo->prepare("SELECT accommodation FROM customer_logs WHERE id = ?");
        $stmt->execute([$customer_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($log && !empty($log['accommodation'])) {
            // Set the room status back to Open
            $updateAcc = $pdo->prepare("UPDATE accommodations SET status = 'Open' WHERE CONCAT(type, ' ', number) = ?");
            $updateAcc->execute([$log['accommodation']]);
        }

        // 2. Stamp the checkout time
        $stmt = $pdo->prepare("UPDATE customer_logs SET check_out_time = NOW() WHERE id = ?");
        $stmt->execute([$customer_id]);

        header("Location: logbook.php");
        exit;
    } 
    
    // --- LOGBOOK DELETE ---
    elseif ($action === 'delete') {
        $customer_id = $_POST['customer_id'];

        // Check if the log being deleted is still "Active" to free up the room
        $stmt = $pdo->prepare("SELECT accommodation, check_out_time FROM customer_logs WHERE id = ?");
        $stmt->execute([$customer_id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($log && is_null($log['check_out_time']) && !empty($log['accommodation'])) {
            $pdo->prepare("UPDATE accommodations SET status = 'Open' WHERE CONCAT(type, ' ', number) = ?")->execute([$log['accommodation']]);
        }

        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM customer_logs WHERE id = ?");
        $stmt->execute([$customer_id]);

        header("Location: logbook.php");
        exit;
    }
    
    // --- ACCOMMODATION ACTIONS ---
    elseif ($action === 'add_acc' || $action === 'edit_acc') {
        $type = $_POST['type'];
        $number = $_POST['number'];
        $price = $_POST['price_per_day'];
        $status = $_POST['status'];
        $notes = $_POST['notes'];

        if ($action === 'add_acc') {
            $stmt = $pdo->prepare("INSERT INTO accommodations (type, number, price_per_day, status, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$type, $number, $price, $status, $notes]);
        } else {
            $stmt = $pdo->prepare("UPDATE accommodations SET type=?, number=?, price_per_day=?, status=?, notes=? WHERE id=?");
            $stmt->execute([$type, $number, $price, $status, $notes, $_POST['acc_id']]);
        }
        header("Location: accommodations.php");
        exit;
    } elseif ($action === 'delete_acc') {
        $stmt = $pdo->prepare("DELETE FROM accommodations WHERE id = ?");
        $stmt->execute([$_POST['acc_id']]);
        header("Location: accommodations.php");
        exit;
    } elseif ($action === 'reserve_acc' || $action === 'edit_reserve') {
        $name = $_POST['reserve_name'];
        $acc_name = $_POST['acc_name'];
        $check_in = $_POST['reserve_date'];
        $check_out = $_POST['reserve_end_date'];

        if ($action === 'reserve_acc') {
            $stmt = $pdo->prepare("INSERT INTO customer_logs (customer_name, pax, customer_type, overnight, accommodation, contact_number, check_in_time, check_out_time) VALUES (?, 0, 'Reservation', 'Yes', ?, 'Pending', ?, ?)");
            $stmt->execute([$name, $acc_name, $check_in, $check_out]);

            $pdo->prepare("UPDATE accommodations SET status = 'Reserved' WHERE id = ?")->execute([$_POST['acc_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE customer_logs SET customer_name = ?, check_in_time = ?, check_out_time = ? WHERE accommodation = ? AND customer_type = 'Reservation' AND check_in_time > NOW()");
            $stmt->execute([$name, $check_in, $check_out, $acc_name]);
        }
        header("Location: accommodations.php");
        exit;
    } elseif ($action === 'remove_reserve') {
        $acc_name = $_POST['acc_name'];
        $pdo->prepare("DELETE FROM customer_logs WHERE accommodation = ? AND customer_type = 'Reservation' AND check_in_time > NOW()")->execute([$acc_name]);
        $pdo->prepare("UPDATE accommodations SET status = 'Open' WHERE id = ?")->execute([$_POST['acc_id']]);
        header("Location: accommodations.php");
        exit;
    }

    // --- PAYMENT ACTIONS ---
    elseif ($action === 'update_payment') {
        $customer_id = $_POST['customer_id'];

        $entrance_fee = floatval($_POST['entrance_fee'] ?? 0);
        $accommodation_fee = floatval($_POST['accommodation_fee'] ?? 0);
        $payment_status = $_POST['payment_status'];

        // Removed total_amount from UPDATE statement
        $stmt = $pdo->prepare("UPDATE customer_logs SET entrance_fee = ?, accommodation_fee = ?, payment_status = ? WHERE id = ?");
        $stmt->execute([$entrance_fee, $accommodation_fee, $payment_status, $customer_id]);

        header("Location: payments.php");
        exit;
    }

    // --- SETTINGS ACTIONS ---
    elseif ($action === 'update_settings') {
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'fee_adult'")->execute([$_POST['fee_adult']]);
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'fee_senior'")->execute([$_POST['fee_senior']]);
        $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'fee_child'")->execute([$_POST['fee_child']]);
        header("Location: settings.php?success=1");
        exit;
    }
}