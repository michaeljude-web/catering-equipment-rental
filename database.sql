-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2025 at 01:16 AM
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
(1, 'admin1', 'admin123');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `equipment_id`, `quantity`, `unit_price`, `total_price`) VALUES
(4, 5, 23, 1, 10.00, 10.00),
(5, 6, 1, 1, 10.00, 10.00),
(6, 7, 24, 1, 20.00, 20.00),
(7, 7, 22, 1, 40.00, 40.00),
(9, 9, 28, 6, 12.00, 72.00);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `customer_id`, `equipment_id`, `quantity`, `price`, `total`, `created_at`) VALUES
(10, 4, 23, 1, 10.00, 10.00, '2025-09-30 04:51:58'),
(17, 5, 1, 2, 10.00, 20.00, '2025-10-02 05:53:02'),
(20, 6, 23, 1, 10.00, 10.00, '2025-10-05 02:55:53'),
(21, 6, 1, 1, 10.00, 10.00, '2025-10-05 03:44:17');

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
(55, 'Storage & Transport'),
(56, 'Serving & Display'),
(57, 'Amenities'),
(58, 'tableware & utensils'),
(59, 'Glasses and Cups'),
(60, 'Serving Platters and Trays');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `email`, `password`, `created_at`) VALUES
(4, 'Mike', 'mike@gmail.com', '$2y$10$EtjdzUAE8KvZvduQhIqRJ.X5oSorEoRkQ3jCj/PptZGeD4UgfDo7C', '2025-09-30 04:50:56'),
(5, 'Mike', 'mike@g.c', '$2y$10$Dy8AxJdCsQJUDMABHOyf/OmsB2zG2p5NHC0KHlTY9Pep9NdtxaMr6', '2025-10-02 03:03:43'),
(6, 'Jude', 'garde@gmail.com', '$2y$10$5eaR5r5AUJSoZ1bztFEiW.Dbu0QFOPX4m5VSFeVbtUJGtb31Tdnne', '2025-10-05 02:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `customer_booking`
--

CREATE TABLE `customer_booking` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_ref` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(20) NOT NULL,
  `full_address` text NOT NULL,
  `borrow_date` date DEFAULT NULL,
  `return_date` date NOT NULL,
  `total_payment` decimal(10,2) NOT NULL,
  `valid_id_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Confirm','Canceled','Approved') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_booking`
--

INSERT INTO `customer_booking` (`id`, `user_id`, `booking_ref`, `name`, `contact`, `full_address`, `borrow_date`, `return_date`, `total_payment`, `valid_id_path`, `status`) VALUES
(5, 5, 'ECC-20251002-8EB1E3', 'Mike', '09069191922', 'dd', '2027-02-15', '2029-02-15', 10.00, 'valid_ids/ECC-20251002-8EB1E3_valid_id.jpg', 'Approved'),
(6, 5, 'ECC-20251002-D47544', 'Mike', '09050505044', 'banga', '2026-02-15', '2028-02-15', 10.00, 'valid_ids/ECC-20251002-D47544_valid_id.png', 'Canceled'),
(7, 5, 'ECC-20251002-6541A6', 'Mike', '09069898988', 'd', '2088-11-12', '2099-02-15', 60.00, 'uploads/valid_ids/ECC-20251002-6541A6_valid_id.png', 'Canceled'),
(8, 5, 'ECC-20251005-90DA2F', 'Mike', '09090909099', 'banga', '2026-02-15', '2026-02-16', 1.00, 'uploads/valid_ids/ECC-20251005-90DA2F_valid_id.jpg', 'Approved'),
(9, 6, 'ECC-20251005-5C5E53', 'Jude ', '09080909011', 'Prk. Mabuhay Rizal 3 Banga', '2029-02-14', '2030-02-15', 72.00, 'uploads/valid_ids/ECC-20251005-5C5E53_valid_id.jpg', 'Approved');

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
(1, 'Dinner Plates', '1758710394_plates.jpg', 58, 10.00, 240, 238, '2025-09-24 10:39:54', '2025-10-04 00:15:53'),
(20, 'Chaffing Dish', '1758712247_chaffing_dish.jpeg', 56, 30.00, 295, 295, '2025-09-24 11:10:47', '2025-09-24 11:10:47'),
(21, 'Wine Glass', '1758712636_wine_glass.png', 59, 10.00, 370, 370, '2025-09-24 11:17:16', '2025-09-24 11:17:16'),
(22, 'Sofas and Couches', '1758712985_Sofas and Couches.png', 57, 40.00, 35, 35, '2025-09-24 11:23:05', '2025-09-24 11:23:05'),
(23, 'Cocktail Trays', '1758713594_Cocktail Trays.png', 60, 10.00, 37, 37, '2025-09-24 11:33:14', '2025-09-24 11:33:14'),
(24, 'Chafing Dish Carriers', '1758713795_Chafing Dish Carriers.png', 55, 20.00, 24, 24, '2025-09-24 11:36:35', '2025-09-24 11:36:35'),
(26, 'Large stainless', '1759603903_stainless-steel-bhagona.png', 60, 20.00, 13, 13, '2025-10-04 18:51:43', '2025-10-04 18:51:43'),
(28, 'Water Glass', '1759635915_WATER GLASS.jpg', 59, 12.00, 6, 6, '2025-10-05 03:45:15', '2025-10-05 03:49:05');

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
  ADD KEY `fk_booking_items_booking` (`booking_id`),
  ADD KEY `fk_booking_items_equipment` (`equipment_id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cart_customer` (`customer_id`),
  ADD KEY `fk_cart_equipment` (`equipment_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_booking`
--
ALTER TABLE `customer_booking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_user` (`user_id`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_category` (`category_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_booking`
--
ALTER TABLE `customer_booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `fk_booking_items_booking` FOREIGN KEY (`booking_id`) REFERENCES `customer_booking` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_items_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_booking`
--
ALTER TABLE `customer_booking`
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipments`
--
ALTER TABLE `equipments`
  ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
