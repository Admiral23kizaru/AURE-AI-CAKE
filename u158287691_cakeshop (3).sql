-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 24, 2026 at 12:26 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u158287691_cakeshop`
--

-- --------------------------------------------------------

--
-- Table structure for table `addons`
--

CREATE TABLE `addons` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addons`
--

INSERT INTO `addons` (`id`, `name`, `is_free`, `price`, `quantity`, `picture`, `created_at`, `updated_at`) VALUES
(4, 'Dedication', 1, 0.00, 10, '3a69f19644875bb3-1765344383.jpg', '2025-12-10 05:26:23', '2026-01-13 00:17:53'),
(5, 'Free Candle', 1, 0.00, 10, '93710403788e83a0-1765344455.jpg', '2025-12-10 05:27:35', '2026-01-13 03:17:51'),
(6, 'Cake Topper', 1, 0.00, 10, '923b5f88096c4b8a-1765344514.jpg', '2025-12-10 05:28:34', '2026-01-13 00:17:44'),
(7, 'cover', 0, 5.00, 100, 'cc8f701eedd5e1121769149095.jpg', '2026-01-23 06:18:15', '2026-01-23 06:18:53');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$hkQ3NT4RnDw8Ik62Wl5/Qun66KBffyePmRSeIfI4y1Fjuu.rsn1JO', 'admin'),
(8, 'Staff2', '$2y$10$QtQB13Qlo4FMTSe6Cr6f7OBmhfX8UYc8LamyTxeOmjylS0oSdGTq6', 'staff'),
(9, 'stafftest', '$2y$10$BTebNwBVCameM2XaWNLaZ.RD1hjWLdp.hwY5mphHJTEeUatr39Qoe', 'staff'),
(10, 'Staff1', '$2y$10$pugSC5F1eHkdVOjS7BHfxu7xPuX7Hl3Ns5Vp9VuqVB6jy6yce4cMm', 'staff'),
(11, 'Staff3', '$2y$10$XH.PDceFWyw5i5NtDNR45eKDoAvIy5m2RhAKhb2.324Pv8ZqHqcI.', 'staff');

-- --------------------------------------------------------

--
-- Table structure for table `ai_cake_messages`
--

CREATE TABLE `ai_cake_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(64) DEFAULT NULL,
  `sender` enum('owner','customer') NOT NULL DEFAULT 'owner',
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ai_cake_orders`
--

CREATE TABLE `ai_cake_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(64) NOT NULL,
  `customer_name` varchar(200) NOT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `customer_number` varchar(50) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment` varchar(80) DEFAULT NULL,
  `personalize` text DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` varchar(20) DEFAULT NULL,
  `store_name` varchar(200) DEFAULT NULL,
  `status` enum('pending','confirmed','ready','processing','completed','cancelled') DEFAULT 'pending',
  `is_ai` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `refund_required` tinyint(1) DEFAULT 0,
  `payment_invoice_id` varchar(100) DEFAULT NULL,
  `paid_sms_sent` tinyint(1) NOT NULL DEFAULT 0,
  `paid_sms_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_cake_orders`
--

INSERT INTO `ai_cake_orders` (`id`, `order_number`, `customer_name`, `picture`, `customer_email`, `customer_number`, `total`, `payment`, `personalize`, `pickup_date`, `pickup_time`, `store_name`, `status`, `is_ai`, `created_at`, `updated_at`, `payment_status`, `payment_reference`, `refund_required`, `payment_invoice_id`, `paid_sms_sent`, `paid_sms_sent_at`) VALUES
(30, '683510', 'Pearly', 'ai_1769253862_683510.png', '', '09484080782', 1.00, 'cash', 'Yellow cake', '2026-01-25', '12:00', 'Aure Sanchez House of Cakes', 'confirmed', 1, '2026-01-24 11:24:22', '2026-01-24 11:32:04', 'paid', NULL, 0, NULL, 0, NULL),
(31, '661424', 'Pearly', 'ai_1769254499_661424.png', '', '09484080782', 1.00, 'gcash', 'Pearly Shell cake', '2026-01-25', '14:45', 'Aure Sanchez House of Cakes', 'confirmed', 1, '2026-01-24 11:34:59', '2026-01-24 11:38:16', 'paid', 'https://checkout-staging.xendit.co/web/6974af0928da9a8b49069986', 0, '6974af0928da9a8b49069986', 0, NULL),
(32, '247665', 'Shaina', 'ai_1769255189_247665.png', '', '09484080782', 1.00, 'gcash', 'Art Cake', '2026-01-25', '11:15', 'Aure Sanchez House of Cakes', 'confirmed', 1, '2026-01-24 11:46:29', '2026-01-24 11:47:51', 'paid', 'https://checkout-staging.xendit.co/web/6974b15676d9d406b4e35c01', 0, '6974b15676d9d406b4e35c01', 0, NULL),
(33, '685481', 'Shaina', 'ai_1769256670_685481.png', '', '09484080782', 1.00, 'gcash', 'Example Cake', '2026-01-25', '11:15', 'Aure Sanchez House of Cakes', 'confirmed', 1, '2026-01-24 12:11:10', '2026-01-24 12:13:25', 'paid', 'https://checkout-staging.xendit.co/web/6974b71176d9d406b4e3611f', 0, '6974b71176d9d406b4e3611f', 1, '2026-01-24 12:13:25');

-- --------------------------------------------------------

--
-- Table structure for table `cakes`
--

CREATE TABLE `cakes` (
  `id` int(11) NOT NULL,
  `cake_id` varchar(100) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `available` tinyint(1) DEFAULT 1,
  `quantity` int(11) DEFAULT 0,
  `is_new` tinyint(1) DEFAULT 0,
  `is_recommended` tinyint(1) DEFAULT 0,
  `best_sellers` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `cakes`
--

INSERT INTO `cakes` (`id`, `cake_id`, `name`, `category_id`, `picture`, `price`, `available`, `quantity`, `is_new`, `is_recommended`, `best_sellers`, `created_at`, `description`) VALUES
(9, 'CK00001', 'Yellow Cake', 1, 'img_692892b704060.jpg', 10.00, 1, 10, 1, 0, 0, '2025-11-24 04:52:01', 'Good for 6-7 servings'),
(11, 'CK00011', 'Chocolate Cake', 1, 'img_6928927834198.jpg', 10.00, 1, 10, 1, 0, 0, '2025-11-24 09:15:08', 'Chocolate Good for 5-7 servings'),
(13, 'CK00013', 'Chocolate Cake Circle', 1, 'img_6932d350d06e9.jpg', 10.00, 1, 10, 1, 1, 1, '2025-12-05 12:42:56', ''),
(14, 'CK00014', 'Ube Cake', 1, 'img_6934bc731cb1f.jpg', 10.00, 1, 10, 1, 0, 1, '2025-12-06 23:29:55', ''),
(17, 'CK00015', 'Trio', 9, 'img_6938322c7019f.jpg', 10.00, 1, 10, 0, 1, 0, '2025-12-09 14:29:00', ''),
(18, 'CK00018', 'Ube Cake Cup', 9, 'img_693832db85703.jpg', 100.00, 1, 5, 1, 0, 0, '2025-12-09 14:31:55', 'Deserve mo to!'),
(19, 'CK00019', 'Choco Cake Cup', 9, 'img_693833a0cdac4.png', 1.00, 1, 24, 1, 0, 0, '2025-12-09 14:35:12', 'Palit n\'!'),
(23, 'CK00020', 'yema', NULL, 'img_6973125f8049e.jpg', 1.00, 1, 5, 0, 0, 0, '2026-01-23 06:17:03', '');

-- --------------------------------------------------------

--
-- Table structure for table `cakes_addons`
--

CREATE TABLE `cakes_addons` (
  `id` int(10) UNSIGNED NOT NULL,
  `cake_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cakes_addons`
--

INSERT INTO `cakes_addons` (`id`, `cake_id`, `addon_id`, `created_at`) VALUES
(58, 10, 4, '2025-12-10 05:42:04'),
(59, 10, 5, '2025-12-10 05:42:04'),
(78, 9, 4, '2026-01-15 03:50:44'),
(79, 9, 5, '2026-01-15 03:50:44'),
(80, 11, 4, '2026-01-15 03:50:54'),
(81, 11, 5, '2026-01-15 03:50:54'),
(82, 13, 5, '2026-01-15 03:51:06'),
(83, 14, 4, '2026-01-15 03:51:24'),
(84, 14, 5, '2026-01-15 03:51:24'),
(87, 23, 6, '2026-01-23 15:07:11'),
(88, 23, 5, '2026-01-23 15:07:11');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `picture`) VALUES
(1, 'Cakes', '1606c9cda61b648b-1763977731.jpg'),
(9, 'top', 'e4fc04818268c575-1767857739.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_addons`
--

CREATE TABLE `inventory_addons` (
  `id` int(10) UNSIGNED NOT NULL,
  `addon_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_addons`
--

INSERT INTO `inventory_addons` (`id`, `addon_id`, `quantity`, `created_at`, `updated_at`) VALUES
(5, 6, 10, '2026-01-13 00:17:44', '2026-01-13 00:17:44'),
(6, 4, 10, '2026-01-13 00:17:53', '2026-01-13 00:17:53'),
(7, 5, 10, '2026-01-13 00:18:00', '2026-01-13 03:17:58'),
(8, 7, 100, '2026-01-23 06:18:53', '2026-01-23 06:18:53');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustments`
--

CREATE TABLE `inventory_adjustments` (
  `id` int(11) NOT NULL,
  `cake_id` int(11) NOT NULL,
  `qty_change` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `inventory_adjustments`
--

INSERT INTO `inventory_adjustments` (`id`, `cake_id`, `qty_change`, `note`, `created_at`) VALUES
(8, 19, 10, '', '2026-01-12 05:22:30'),
(9, 11, 10, '', '2026-01-12 05:23:06'),
(10, 13, 10, '', '2026-01-12 05:23:39'),
(11, 19, 10, '', '2026-01-12 10:31:56'),
(12, 14, 10, '', '2026-01-13 00:11:56'),
(13, 19, 10, 'SOLD IN 5', '2026-01-15 03:56:13');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(100) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(32) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT 0.00,
  `payment` varchar(60) DEFAULT NULL,
  `addons` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`addons`)),
  `personalization` text DEFAULT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `store_name` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `is_ai` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `guest_otp` varchar(16) DEFAULT NULL,
  `otp_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_reference` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_invoice_id` varchar(100) DEFAULT NULL,
  `sms_paid_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `cake_id` int(11) DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_ai` tinyint(1) NOT NULL DEFAULT 0,
  `ai_ref` varchar(60) DEFAULT NULL,
  `ai_prompt` text DEFAULT NULL,
  `ai_message` text DEFAULT NULL,
  `ai_flavor` varchar(80) DEFAULT NULL,
  `ai_size` varchar(80) DEFAULT NULL,
  `ai_img` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `address` text NOT NULL,
  `description` text DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `picture` varchar(255) DEFAULT NULL,
  `open_time` time NOT NULL DEFAULT '08:00:00',
  `close_time` time NOT NULL DEFAULT '22:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `name`, `address`, `description`, `phone`, `picture`, `open_time`, `close_time`, `created_at`, `updated_at`) VALUES
(1, 'Aure Sanchez House of Cakes', 'P4, Molave City, Zamboang Del Sur', NULL, '09812547481', '1c91bb98c5534a52-1764030676.png', '08:00:00', '17:00:00', '2025-11-25 00:31:16', '2026-01-24 08:06:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addons`
--
ALTER TABLE `addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `ai_cake_messages`
--
ALTER TABLE `ai_cake_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `ai_cake_orders`
--
ALTER TABLE `ai_cake_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_ai` (`is_ai`),
  ADD KEY `idx_pickup_date` (`pickup_date`);

--
-- Indexes for table `cakes`
--
ALTER TABLE `cakes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cake_id` (`cake_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `cakes_addons`
--
ALTER TABLE `cakes_addons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cake_addon` (`cake_id`,`addon_id`),
  ADD KEY `idx_cake` (`cake_id`),
  ADD KEY `idx_addon` (`addon_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `inventory_addons`
--
ALTER TABLE `inventory_addons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_addon_inventory` (`addon_id`);

--
-- Indexes for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cake_id` (`cake_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `cake_id` (`cake_id`),
  ADD KEY `idx_order_items_ai_ref` (`ai_ref`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addons`
--
ALTER TABLE `addons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ai_cake_messages`
--
ALTER TABLE `ai_cake_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `ai_cake_orders`
--
ALTER TABLE `ai_cake_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `cakes`
--
ALTER TABLE `cakes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `cakes_addons`
--
ALTER TABLE `cakes_addons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventory_addons`
--
ALTER TABLE `inventory_addons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_cake_messages`
--
ALTER TABLE `ai_cake_messages`
  ADD CONSTRAINT `fk_ai_cake_messages_order` FOREIGN KEY (`order_id`) REFERENCES `ai_cake_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cakes`
--
ALTER TABLE `cakes`
  ADD CONSTRAINT `cakes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_addons`
--
ALTER TABLE `inventory_addons`
  ADD CONSTRAINT `fk_inventory_addons` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_addons_addon` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD CONSTRAINT `inventory_adjustments_ibfk_1` FOREIGN KEY (`cake_id`) REFERENCES `cakes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`cake_id`) REFERENCES `cakes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
