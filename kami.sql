-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2026 at 01:57 PM
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
-- Database: `kami`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `category`, `price`, `stock`, `created_at`) VALUES
(1, 'COG-001', 'Hennessy VS', 'Cognac', 45.00, 24, '2026-05-29 08:35:41'),
(2, 'WHI-001', 'Jack Daniels', 'Whiskey', 35.00, 14, '2026-05-29 08:35:41'),
(3, 'VOD-001', 'Grey Goose', 'Vodka', 40.00, 8, '2026-05-29 08:35:41'),
(4, 'WIN-001', 'Cabernet Sauvignon', 'Wine', 25.00, 29, '2026-05-29 08:35:41'),
(5, 'BEE-001', 'Heineken 6-Pack', 'Beer', 12.00, 49, '2026-05-29 08:35:41');

-- --------------------------------------------------------

--
-- Table structure for table `qr_orders`
--

CREATE TABLE `qr_orders` (
  `id` int(11) NOT NULL,
  `table_number` varchar(10) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','momo') NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_order_items`
--

CREATE TABLE `qr_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `preference` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_order_items`
--

INSERT INTO `qr_order_items` (`id`, `order_id`, `product_name`, `quantity`, `price`, `preference`) VALUES
(1, 1, 'Highland Reserve 18', 1, 320.00, NULL),
(2, 1, 'Ozone Cask Strength', 1, 185.00, NULL),
(3, 2, 'The Macallan 25', 1, 1250.00, NULL),
(4, 3, 'The Macallan 25', 1, 1250.00, NULL),
(5, 4, 'The Macallan 25', 1, 1250.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `cashier_id`, `subtotal`, `tax`, `total`, `created_at`) VALUES
(1, 3, 60.00, 10.80, 70.80, '2026-05-30 12:58:55'),
(2, 3, 80.00, 14.40, 94.40, '2026-05-31 19:08:36'),
(3, 3, 12.00, 2.16, 14.16, '2026-05-31 19:09:27');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `qty`, `price`) VALUES
(1, 1, 2, 1, 35.00),
(2, 1, 4, 1, 25.00),
(3, 2, 3, 2, 40.00),
(4, 3, 5, 1, 12.00);

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `starting_cash` decimal(10,2) NOT NULL,
  `ending_cash` decimal(10,2) DEFAULT NULL,
  `clock_in` datetime NOT NULL DEFAULT current_timestamp(),
  `clock_out` datetime DEFAULT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `cashier_id`, `starting_cash`, `ending_cash`, `clock_in`, `clock_out`, `status`, `submitted_at`) VALUES
(1, 2, 10000.00, 20000.00, '2026-05-30 15:29:11', '2026-05-30 15:29:29', 'closed', '2026-05-30 15:32:02'),
(2, 3, 1000.00, 12000.00, '2026-05-31 21:08:26', '2026-05-31 21:08:49', 'closed', '2026-05-31 21:08:58'),
(3, 4, 40000.00, 80000.00, '2026-06-03 05:50:50', '2026-06-03 05:51:03', 'closed', '2026-06-03 05:51:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','cashier') NOT NULL DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Master Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-05-30 13:02:42'),
(4, 'test', 'test', '$2y$10$HINZcrNb3P8X9X5re/Ae0OgfJxfYp.UbiMzieoLnb.wLcaPMDQJ3y', 'cashier', '2026-06-01 03:33:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `qr_orders`
--
ALTER TABLE `qr_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `qr_order_items`
--
ALTER TABLE `qr_order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `qr_orders`
--
ALTER TABLE `qr_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `qr_order_items`
--
ALTER TABLE `qr_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
