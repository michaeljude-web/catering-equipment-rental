-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 20, 2026 at 03:38 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `catering`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(2, 'admin', '$2y$10$os3Ya/c5GNSqcbPG.5Q6JOWpWFy0lUP1ENo3EQebdoqNUfxbi8n9.');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `equipment_id`, `package_id`, `quantity`, `price`) VALUES
(12, 12, 30, NULL, 1, 20.00),
(13, 13, 30, NULL, 1, 20.00),
(14, 13, 34, NULL, 10, 120.00),
(15, 13, 31, NULL, 1, 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`) VALUES
(62, 'Utensils and Dining Ware'),
(63, 'Food Service Equipment'),
(64, 'Furniture and Setup'),
(65, 'Cooking Equipment'),
(66, 'Event and DÃ©cor'),
(67, 'Rental Accessories');

-- --------------------------------------------------------

--
-- Table structure for table `customer_booking`
--

CREATE TABLE `customer_booking` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(500) NOT NULL,
  `email` varchar(500) DEFAULT NULL,
  `phone` varchar(500) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `borrow_date` datetime NOT NULL,
  `return_date` datetime NOT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('Borrowed','Returned','Overdue','Cancelled') DEFAULT 'Borrowed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actual_return_date` datetime DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `damage_fee` decimal(10,2) DEFAULT 0.00,
  `damage_notes` text DEFAULT NULL,
  `damaged_items` text DEFAULT NULL,
  `sms_reminder_sent` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_booking`
--

INSERT INTO `customer_booking` (`id`, `customer_name`, `email`, `phone`, `address`, `borrow_date`, `return_date`, `total_amount`, `status`, `created_at`, `actual_return_date`, `fine_amount`, `damage_fee`, `damage_notes`, `damaged_items`, `sms_reminder_sent`) VALUES
(12, 'XJ/G/1k5F+MljzZcd4Ax6DUyOVJ5YS8wWDljWThaVkV6OEF6Y2c9PQ==', '0rw4Wj+8Hh7GDwt/eXQIUVhvRGZIRURCMXFLd3ZZbU5oU1F2c1E9PQ==', 'eBTvVTsBEopyAb7Epied129wb3VRb1I1U2t5QzhQVGpUbUNjVVE9PQ==', 'uhBugJIp+LHhdrRt6bDHCDdpVGhuOXFURlg5Mkt5ZUN2NTR3Tys0Tm0xZEs1Z2pxUjdtYWE1Vys0TVNJK0cvV3NuVEl2YVZIK1BZejUwa1g=', '2026-03-16 05:21:31', '2026-03-16 23:01:00', 20.00, 'Borrowed', '2026-03-16 04:21:31', NULL, 6800.00, 0.00, NULL, NULL, 0),
(13, 'pQ/SHl6FJcdV/AG2MwzIqy9seWhFdm81S0dOK1MrU0FqOUlib0E9PQ==', 'Yb/KH8WfVdt2mbUfCRRPLVp0NVlScDJlWjdyRlhNVVVSS25Qdmc9PQ==', '31vrMLyLGvHDQAB1Gyb9n2VlTk1QVVFienQ4ajBBR3duOVZEdGc9PQ==', '1yoFCQ0VaboUrfLBvk1eVUhlSDZXb1czSVlIbXRyZzI0eHZweG1uK1RNVUhGT3k5Vi9WcThReHBiK2FtUUJ5SzFtYlA1aWd4aFE5OE4vM20=', '2026-03-16 05:39:24', '2026-03-16 23:11:00', 150.00, 'Returned', '2026-03-16 04:39:24', '2026-03-16 05:39:32', 0.00, 0.00, '', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `name`, `photo`, `category_id`, `price`, `quantity`, `stock`, `created_at`, `updated_at`) VALUES
(29, 'Tables', '1761357204_Tate60inRndDiningTbl3QSSF23_3D_512x512.webp', 64, 50.00, 19, 20, '2025-10-25 01:53:24', '2025-11-01 01:20:23'),
(30, 'Chairs', '1761357361_RUBY1-APPLE-GREEN-FRONT-with-sticker-min-600x696.webp', 64, 20.00, 260, 257, '2025-10-25 01:56:01', '2026-03-16 04:39:32'),
(31, 'Plates', '1761357436_1758710394_plates.jpg', 62, 10.00, 200, 179, '2025-10-25 01:57:16', '2026-03-16 04:39:32'),
(32, 'Sofas', '1761357491_1758712985_Sofas and Couches.png', 64, 40.00, 24, 5, '2025-10-25 01:58:11', '2026-03-16 04:01:03'),
(33, 'Wine Glass', '1761357556_1758712636_wine_glass.png', 62, 21.00, 34, 32, '2025-10-25 01:59:16', '2026-03-16 04:01:03'),
(34, 'Water Glass', '1761357577_1759635915_WATER GLASS.jpg', 62, 12.00, 63, 63, '2025-10-25 01:59:37', '2026-03-16 04:39:32');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `device_hash` varchar(64) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `ban_until` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `package_name`, `price`, `created_at`) VALUES
(2, 'Weeding Package', 3000.00, '2026-03-16 02:46:19');

-- --------------------------------------------------------

--
-- Table structure for table `package_items`
--

CREATE TABLE `package_items` (
  `id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `package_items`
--

INSERT INTO `package_items` (`id`, `package_id`, `equipment_id`, `quantity`) VALUES
(7, 2, 30, 1),
(8, 2, 33, 1),
(9, 2, 32, 10),
(10, 2, 31, 11);

-- --------------------------------------------------------

--
-- Table structure for table `security_questions`
--

CREATE TABLE `security_questions` (
  `id` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_questions`
--

INSERT INTO `security_questions` (`id`, `question`, `is_active`, `created_at`) VALUES
(1, 'What is the name of your first pet?', 1, '2026-03-20 01:25:55'),
(2, 'What is your mother\'s maiden name?', 1, '2026-03-20 01:25:55'),
(3, 'What city were you born in?', 1, '2026-03-20 01:25:55'),
(4, 'What is your birthday?', 1, '2026-03-20 01:25:55'),
(5, 'What is your oldest sibling\'s middle name?', 1, '2026-03-20 01:25:55'),
(6, 'What was the make of your first car?', 1, '2026-03-20 01:25:55'),
(7, 'What is your favorite childhood food?', 1, '2026-03-20 01:25:55'),
(8, 'What street did you grow up on?', 1, '2026-03-20 01:25:55');

-- --------------------------------------------------------

--
-- Table structure for table `staff_info`
--

CREATE TABLE `staff_info` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `firstname` varchar(500) NOT NULL,
  `lastname` varchar(500) NOT NULL,
  `age` smallint(5) UNSIGNED DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(500) DEFAULT NULL,
  `username` varchar(500) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_info`
--

INSERT INTO `staff_info` (`id`, `firstname`, `lastname`, `age`, `address`, `contact_number`, `username`, `password_hash`, `created_at`) VALUES
(4, 'GZUtvtkpmiu+fxCk5vKG62RKVlFpSkhaeGNhQ0MzT0N5ZGo4RlE9PQ==', 'SOwMpEXuyZnOMbbH5RzGK0ZZd0JRaWk4V1FVenFTVUQrMXpVcGc9PQ==', 15, 'dESkFQZNmrGrS4MKtmECWVhuMzdNM0VHUkl6Zm9BR1ZxV2dKYzA0V2JKVi83NTY1akhaTFI3KzhRSDkvZTRveFk1eTcycTUrUWhtTGN2bHE=', 'WKeq0aVgwot4oj4g5+gokkloU21tZ2doajNmQXFpWXl4Y0lMS3c9PQ==', 'juan', '$2y$10$Ek.wRKThW3EG65U7pRQwjeQW3Fc10jNJyihcRuZqovd73Dy99A3ha', '2026-03-16 11:41:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_security_answers`
--

CREATE TABLE `user_security_answers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('admin','staff') NOT NULL,
  `question_id` int(11) NOT NULL,
  `answer_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_security_answers`
--

INSERT INTO `user_security_answers` (`id`, `user_id`, `user_type`, `question_id`, `answer_hash`, `created_at`, `updated_at`) VALUES
(5, 2, 'admin', 1, '$2y$10$5RE6F5Fq43X2fjf/1EHX3OBPh.j58cP7bJWwPB4tu2192H2jLTCHW', '2026-03-20 02:10:15', '2026-03-20 02:10:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking` (`booking_id`),
  ADD KEY `fk_equipment_booking` (`equipment_id`),
  ADD KEY `fk_package_booking` (`package_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_booking`
--
ALTER TABLE `customer_booking`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_category` (`category_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device` (`ip_address`,`device_hash`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `package_items`
--
ALTER TABLE `package_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_package` (`package_id`),
  ADD KEY `fk_equipment` (`equipment_id`);

--
-- Indexes for table `security_questions`
--
ALTER TABLE `security_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_info`
--
ALTER TABLE `staff_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_security_answers`
--
ALTER TABLE `user_security_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_question` (`user_id`,`user_type`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `customer_booking`
--
ALTER TABLE `customer_booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `package_items`
--
ALTER TABLE `package_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `security_questions`
--
ALTER TABLE `security_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `staff_info`
--
ALTER TABLE `staff_info`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_security_answers`
--
ALTER TABLE `user_security_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `fk_booking` FOREIGN KEY (`booking_id`) REFERENCES `customer_booking` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_equipment_booking` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_package_booking` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `equipments`
--
ALTER TABLE `equipments`
  ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `package_items`
--
ALTER TABLE `package_items`
  ADD CONSTRAINT `fk_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_security_answers`
--
ALTER TABLE `user_security_answers`
  ADD CONSTRAINT `user_security_answers_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `security_questions` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;