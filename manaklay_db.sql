-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 03, 2026 at 08:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `manaklay_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accommodations`
--

CREATE TABLE `accommodations` (
  `id` int(11) NOT NULL,
  `type` enum('Room','Cottage','Function Hall','Dormitory','Conference Hall','Conference Room','Others') NOT NULL,
  `number` varchar(50) NOT NULL,
  `price_per_day` decimal(10,2) DEFAULT 0.00,
  `status` enum('Open','Reserved','Active','Out of Service') DEFAULT 'Open',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accommodations`
--

INSERT INTO `accommodations` (`id`, `type`, `number`, `price_per_day`, `status`, `notes`) VALUES
(1, 'Cottage', 'A1', 500.00, 'Open', ''),
(2, 'Room', '1', 3999.00, 'Open', ''),
(3, 'Room', '2', 3999.00, 'Open', ''),
(4, 'Room', '3', 3999.00, 'Open', ''),
(5, 'Room', '4', 3999.00, 'Open', ''),
(6, 'Room', '5', 3999.00, 'Open', ''),
(7, 'Room', '6', 3999.00, 'Open', ''),
(8, 'Room', '7', 3999.00, 'Open', ''),
(9, 'Cottage', 'A2', 500.00, 'Open', ''),
(10, 'Cottage', 'A3', 500.00, 'Open', ''),
(11, 'Cottage', 'A4', 500.00, 'Open', ''),
(12, 'Cottage', 'A5', 500.00, 'Open', ''),
(13, 'Cottage', 'A6', 500.00, 'Active', ''),
(14, 'Cottage', 'A7', 500.00, 'Open', ''),
(15, 'Room', 'A8', 500.00, 'Open', ''),
(16, 'Cottage', 'B1', 600.00, 'Open', ''),
(17, 'Cottage', 'B2', 600.00, 'Open', ''),
(18, 'Cottage', 'B3', 600.00, 'Open', ''),
(19, 'Cottage', 'B4', 600.00, 'Open', ''),
(20, 'Cottage', 'B5', 600.00, 'Open', ''),
(21, 'Cottage', 'B6', 600.00, 'Open', ''),
(22, 'Cottage', 'B7', 600.00, 'Open', ''),
(23, 'Cottage', 'B8', 600.00, 'Open', ''),
(24, 'Cottage', 'B9', 600.00, 'Open', ''),
(25, 'Cottage', 'B10', 600.00, 'Open', ''),
(26, 'Cottage', 'B11', 600.00, 'Active', NULL),
(27, 'Cottage', 'B12', 600.00, 'Active', NULL),
(29, 'Dormitory', '1', 12500.00, 'Open', '28pax'),
(30, 'Dormitory', '2', 10000.00, 'Open', 'non-aircon, 20pax'),
(31, 'Dormitory', '3', 10000.00, 'Open', 'non-aircon, 20pax'),
(32, 'Dormitory', '4', 10000.00, 'Open', 'non-aircon, 20pax'),
(34, 'Function Hall', '1', 6000.00, 'Open', '50pax, 6hours'),
(35, 'Conference Room', '1', 6000.00, 'Open', '50pax, 6hours'),
(36, 'Others', 'Tables&Chairs', 400.00, 'Open', '6seaters'),
(37, 'Others', 'Tables&Chairs', 350.00, 'Open', '4seaters');

-- --------------------------------------------------------

--
-- Table structure for table `customer_logs`
--

CREATE TABLE `customer_logs` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `pax` int(11) NOT NULL,
  `adults` int(11) DEFAULT 0,
  `seniors` int(11) DEFAULT 0,
  `children` int(11) DEFAULT 0,
  `customer_type` enum('Walk-in','Reservation') NOT NULL,
  `overnight` enum('Yes','No') NOT NULL,
  `accommodation` varchar(100) NOT NULL,
  `check_in_time` datetime DEFAULT current_timestamp(),
  `check_out_time` datetime DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `entrance_fee` decimal(10,2) DEFAULT 0.00,
  `accommodation_fee` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) GENERATED ALWAYS AS (`entrance_fee` + `accommodation_fee`) STORED,
  `payment_status` enum('Partial','Full') DEFAULT 'Partial',
  `payment_method` varchar(50) DEFAULT 'Cash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_logs`
--

INSERT INTO `customer_logs` (`id`, `customer_name`, `pax`, `adults`, `seniors`, `children`, `customer_type`, `overnight`, `accommodation`, `check_in_time`, `check_out_time`, `contact_number`, `entrance_fee`, `accommodation_fee`, `payment_status`, `payment_method`) VALUES
(3, 'Deo', 6, 2, 2, 2, 'Walk-in', 'No', 'Room 2, Cottage A5', '2026-06-03 07:35:00', '2026-06-03 14:07:49', '0912312312', 240.00, 4499.00, 'Partial', 'Cash'),
(7, 'John', 25, 1, 12, 12, 'Walk-in', 'No', 'Cottage B11', '2026-06-20 08:13:00', NULL, '0912312312', 890.00, 600.00, 'Partial', 'GCash'),
(8, 'Deo', 12, 4, 4, 4, 'Reservation', 'Yes', 'Cottage A6, Cottage B12', '2026-06-04 08:23:00', NULL, '0912312312', 800.00, 1100.00, 'Partial', 'Cash');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `category` varchar(100) DEFAULT 'Others',
  `payment_method` varchar(50) DEFAULT 'cash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `category_id`, `expense_date`, `description`, `amount`, `reference`, `user_id`, `created_at`, `notes`, `category`, `payment_method`) VALUES
(1, NULL, '2026-06-03', 'abc', 1000.00, '', 5, '2026-06-03 04:07:19', '', 'Utilities', 'cash');

-- --------------------------------------------------------

--
-- Table structure for table `expenses_categories`
--

CREATE TABLE `expenses_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses_categories`
--

INSERT INTO `expenses_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Electricity', NULL, '2026-04-27 14:51:19'),
(2, 'Water', NULL, '2026-04-27 14:51:19'),
(3, 'Internet', NULL, '2026-04-27 14:51:19'),
(4, 'Maintenance', NULL, '2026-04-27 14:51:19'),
(5, 'Salary', NULL, '2026-04-27 14:51:19'),
(6, 'Supplies', NULL, '2026-04-27 14:51:19'),
(7, 'Rent', NULL, '2026-04-27 14:51:19');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `category_id`, `product_name`, `description`, `unit_price`, `cost_price`, `stock_quantity`, `reorder_level`, `created_at`, `image`) VALUES
(1, 3, 'Piattos', '', 30.00, 20.00, 6, 3, '2026-06-03 03:52:29', 'default.png'),
(2, 3, 'Criss Cross', '', 25.00, 20.00, 4, 3, '2026-06-03 03:53:21', 'default.png'),
(3, 3, 'Pic-a', 'junkfood', 50.00, 30.00, 9, 3, '2026-06-03 04:04:46', 'default.png');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Candy', NULL, '2026-04-27 14:38:57'),
(2, 'Biscuit', NULL, '2026-04-27 14:38:57'),
(3, 'Junk Food', NULL, '2026-04-27 14:38:57'),
(4, 'Meal', NULL, '2026-04-27 14:38:57'),
(5, 'Beverage', NULL, '2026-04-27 14:38:57'),
(6, 'Alcohol', NULL, '2026-04-27 14:38:57'),
(7, 'Hygiene', NULL, '2026-04-27 14:38:57'),
(8, 'Utensils', NULL, '2026-04-27 14:38:57'),
(9, 'Clothing', NULL, '2026-04-27 14:38:57'),
(10, 'Unspecified', NULL, '2026-04-27 14:38:57');

-- --------------------------------------------------------

--
-- Table structure for table `product_transactions`
--

CREATE TABLE `product_transactions` (
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_transactions`
--

INSERT INTO `product_transactions` (`transaction_id`, `product_id`, `quantity`, `unit_price`, `notes`, `transaction_date`, `user_id`) VALUES
(1, 1, 5, 30.00, '', '2026-06-03 03:54:01', 5),
(2, 1, -3, 30.00, '', '2026-06-03 03:54:42', 5),
(3, 3, 3, 50.00, '', '2026-06-03 04:05:18', 5),
(4, 1, 4, 30.00, '', '2026-06-03 05:33:25', 5),
(5, 2, 4, 25.00, '', '2026-06-03 05:33:29', 5);

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `report_name`, `file_path`, `start_date`, `end_date`, `created_at`) VALUES
(1, 'Sales & Expenses Report (1 month)', 'reports/report_2026-06-03_14-00-06.csv', '2026-05-03', '2026-06-03', '2026-06-03 06:00:06');

-- --------------------------------------------------------

--
-- Table structure for table `report_history`
--

CREATE TABLE `report_history` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `generated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report_history`
--

INSERT INTO `report_history` (`id`, `filename`, `report_type`, `start_date`, `end_date`, `generated_at`) VALUES
(1, 'Manaklay_Day_Report_20260603_135856.xlsx', 'day', '2026-06-03', '2026-06-03', '2026-06-03 13:58:58'),
(2, 'Manaklay_Day_Report_20260603_135916.xlsx', 'day', '2026-06-03', '2026-06-03', '2026-06-03 13:59:17'),
(3, 'Manaklay_Day_Report_20260603_140610.xlsx', 'day', '2026-06-03', '2026-06-03', '2026-06-03 14:06:12'),
(4, 'Manaklay_Day_Report_20260603_142409.xlsx', 'day', '2026-06-03', '2026-06-03', '2026-06-03 14:24:11'),
(5, 'Manaklay_Day_Report_20260603_142443.xlsx', 'day', '2026-06-03', '2026-06-03', '2026-06-03 14:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('fee_adult_day', 50.00),
('fee_adult_overnight', 80.00),
('fee_child_day', 30.00),
('fee_child_overnight', 50.00),
('fee_senior_day', 40.00),
('fee_senior_overnight', 70.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `role`, `created_at`) VALUES
(1, 'test', 'test', 'test t. test', NULL, 'staff', '2026-04-29 17:37:04'),
(5, 'admin', 'admin123', 'Administrator', NULL, 'admin', '2026-05-23 13:27:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accommodations`
--
ALTER TABLE `accommodations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_logs`
--
ALTER TABLE `customer_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses_categories`
--
ALTER TABLE `expenses_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `product_transactions`
--
ALTER TABLE `product_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`);

--
-- Indexes for table `report_history`
--
ALTER TABLE `report_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accommodations`
--
ALTER TABLE `accommodations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `customer_logs`
--
ALTER TABLE `customer_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenses_categories`
--
ALTER TABLE `expenses_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product_transactions`
--
ALTER TABLE `product_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report_history`
--
ALTER TABLE `report_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expenses_categories` (`category_id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`);

--
-- Constraints for table `product_transactions`
--
ALTER TABLE `product_transactions`
  ADD CONSTRAINT `product_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
