-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2025 at 03:11 PM
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
-- Database: `cake_shop`
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
  `picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addons`
--

INSERT INTO `addons` (`id`, `name`, `is_free`, `price`, `picture`, `created_at`, `updated_at`) VALUES
(1, 'Free Candle', 1, 0.00, '55ce25fc3708f459-1763978462.jpg', '2025-11-24 10:01:02', NULL),
(2, 'Free Dedication Card', 1, 0.00, '03b015fd9c78c127-1763978494.jpg', '2025-11-24 10:01:34', NULL),
(3, 'Gold Happy Birthday Greeting Topper', 0, 28.00, '220ef604e81b11d6-1763978580.jpg', '2025-11-24 10:03:00', NULL);

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
(4, 'anna', '$2y$10$gZ/K57iYx6Bk2RCBh2f7a.zrpF9fKw9BKM5Iqc6WaiUuGpsmBNxeG', 'staff');

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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(9, 'CK00001', 'Yellow Yam', 1, 'img_6923e471d31df.jpg', 950.00, 1, 7, 1, 0, 0, '2025-11-24 04:52:01', 'Yellow Yam Lami ni Good for 15 servings'),
(10, 'CK00010', 'Purple Yam', 1, 'img_6923e7a44d465.jpg', 950.00, 1, 0, 1, 0, 0, '2025-11-24 05:05:40', 'Purple Yam sya lami ni sya'),
(11, 'CK00011', 'Chocolate Yam', 1, 'img_6924221cddded.jpg', 950.00, 1, 0, 1, 0, 0, '2025-11-24 09:15:08', 'Chocolate Good for 5-7 servings');

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
(3, 9, 1, '2025-11-24 10:19:53'),
(4, 9, 2, '2025-11-24 10:19:53'),
(5, 9, 3, '2025-11-24 10:19:53');

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
(1, 'Cakes', '1606c9cda61b648b-1763977731.jpg');

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
(5, 10, 1, '', '2025-11-25 05:34:37'),
(6, 9, 10, '', '2025-11-25 14:05:50');

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
  `otp_confirmed` tinyint(1) NOT NULL DEFAULT 0
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
(1, 'Aure Sanchez House of Cakes', 'P4, Molave City, Zamboang Del Sur', NULL, NULL, '1c91bb98c5534a52-1764030676.png', '08:00:00', '17:00:00', '2025-11-25 00:31:16', NULL);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ai_cake_messages`
--
ALTER TABLE `ai_cake_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `ai_cake_orders`
--
ALTER TABLE `ai_cake_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `cakes`
--
ALTER TABLE `cakes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `cakes_addons`
--
ALTER TABLE `cakes_addons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

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
