-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2025 at 04:19 AM
-- Server version: 10.1.38-MariaDB
-- PHP Version: 7.3.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ar`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(1, 'admin', 'admin123');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `package_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `equipment_id`, `package_id`, `quantity`, `price`) VALUES
(1, 1, 34, NULL, 1, '12.00'),
(2, 2, 33, NULL, 1, '21.00'),
(3, 3, 30, NULL, 1, '20.00'),
(4, 4, 34, NULL, 1, '12.00'),
(5, 5, 34, NULL, 1, '12.00'),
(6, 6, 34, NULL, 1, '12.00');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `customer_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `borrow_date` datetime NOT NULL,
  `return_date` datetime NOT NULL,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('Borrowed','Returned','Overdue','Cancelled') DEFAULT 'Borrowed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actual_return_date` datetime DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT '0.00',
  `damage_fee` decimal(10,2) DEFAULT '0.00',
  `damage_notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `customer_booking`
--

INSERT INTO `customer_booking` (`id`, `customer_name`, `email`, `phone`, `address`, `borrow_date`, `return_date`, `total_amount`, `status`, `created_at`, `actual_return_date`, `fine_amount`, `damage_fee`, `damage_notes`) VALUES
(1, 'Michael Jude', 'garde@gmail.com', '09069191944', 'Banga South Cotabato', '2025-10-26 00:00:00', '2025-10-26 00:00:00', '12.00', 'Overdue', '2025-10-26 10:56:33', '2025-10-30 04:07:03', '10100.00', '0.00', NULL),
(2, 'Jude', 'a@gdasda.c', '09070101011', 'Banga', '2025-10-26 00:00:00', '2025-10-27 00:00:00', '21.00', 'Borrowed', '2025-10-26 10:57:33', NULL, '8300.00', '0.00', NULL),
(3, 'Mike', 'mike@g.c', '09090909011', 'Banga', '2026-10-26 00:00:00', '2025-10-26 00:00:00', '20.00', 'Borrowed', '2025-10-26 11:25:21', NULL, '10700.00', '0.00', NULL),
(4, 'dd', 'd@f.n', '09069191921', 'Banga', '2025-10-30 00:00:00', '2025-11-01 00:00:00', '12.00', 'Borrowed', '2025-10-30 01:46:07', NULL, '0.00', '0.00', NULL),
(5, 'dsd', 'd@f.n', '09069191921', 'Banga', '2025-10-30 03:58:15', '2026-02-22 14:22:00', '12.00', 'Borrowed', '2025-10-30 02:58:15', NULL, '0.00', '0.00', NULL),
(6, 's', 'mike@g.c', '09069191921', 'dada', '2025-10-30 04:11:24', '2026-11-11 11:11:00', '12.00', 'Returned', '2025-10-30 03:11:24', '2025-10-30 04:11:39', '0.00', '0.00', NULL);

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
  `quantity` int(11) NOT NULL DEFAULT '0',
  `stock` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `name`, `photo`, `category_id`, `price`, `quantity`, `stock`, `created_at`, `updated_at`) VALUES
(29, 'Tables', '1761357204_Tate60inRndDiningTbl3QSSF23_3D_512x512.webp', 64, '50.00', 20, 20, '2025-10-25 01:53:24', '2025-10-25 01:53:24'),
(30, 'Chairs', '1761357361_RUBY1-APPLE-GREEN-FRONT-with-sticker-min-600x696.webp', 64, '20.00', 260, 259, '2025-10-25 01:56:01', '2025-10-26 11:25:21'),
(31, 'Plates', '1761357436_1758710394_plates.jpg', 62, '10.00', 200, 200, '2025-10-25 01:57:16', '2025-10-25 01:57:16'),
(32, 'Sofas', '1761357491_1758712985_Sofas and Couches.png', 64, '40.00', 26, 26, '2025-10-25 01:58:11', '2025-10-25 01:58:11'),
(33, 'Wine Glass', '1761357556_1758712636_wine_glass.png', 62, '21.00', 34, 33, '2025-10-25 01:59:16', '2025-10-26 10:57:33'),
(34, 'Water Glass', '1761357577_1759635915_WATER GLASS.jpg', 62, '12.00', 64, 62, '2025-10-25 01:59:37', '2025-10-30 03:11:39');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `package_name`, `price`, `created_at`) VALUES
(1, 'Weeding Package', '500.00', '2025-10-30 03:04:16');

-- --------------------------------------------------------

--
-- Table structure for table `package_items`
--

CREATE TABLE `package_items` (
  `id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `package_items`
--

INSERT INTO `package_items` (`id`, `package_id`, `equipment_id`, `quantity`) VALUES
(1, 1, 30, 1),
(2, 1, 31, 1),
(3, 1, 32, 1),
(4, 1, 29, 1),
(5, 1, 34, 1),
(6, 1, 33, 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff_info`
--

CREATE TABLE `staff_info` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `firstname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `age` smallint(5) UNSIGNED DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `contact_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_info`
--

INSERT INTO `staff_info` (`id`, `firstname`, `lastname`, `age`, `address`, `contact_number`, `username`, `password_hash`, `created_at`) VALUES
(1, 'Michael Jude', 'Garde', 20, 'Prk Mabuhay Rizal 3 Banga South Cotabato', '09069191920', 'garde', '$2y$10$mQlWpqHbbTDjSEGWmGYcYeR9TvsHA3VuQ6gwVdUSRleOcd0Jz06X2', '2025-10-25 09:43:15');

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
-- Indexes for table `staff_info`
--
ALTER TABLE `staff_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `customer_booking`
--
ALTER TABLE `customer_booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `package_items`
--
ALTER TABLE `package_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff_info`
--
ALTER TABLE `staff_info`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
