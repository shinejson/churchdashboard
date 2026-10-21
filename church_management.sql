-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 21, 2026 at 08:37 PM
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
-- Database: `church_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `service_date` date NOT NULL,
  `service_id` varchar(50) DEFAULT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `status` enum('present','absent','excused') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_timers_count` int(11) DEFAULT 0 COMMENT 'Number of first-time visitors not in members list'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `member_id`, `service_date`, `service_id`, `fellowship_id`, `status`, `notes`, `is_deleted`, `created_by`, `created_at`, `updated_at`, `first_timers_count`) VALUES
(12, 7, '2025-11-27', 'global_communion_service', 3, 'present', '', 0, 7, '2025-11-27 12:22:27', '2025-11-28 11:57:25', 0),
(13, 1, '2025-11-28', 'sunday_service', 3, 'present', '', 0, 7, '2025-11-28 10:45:31', '2025-11-28 10:45:31', 0),
(14, 7, '2025-11-28', 'sunday_service', 3, 'present', '', 0, 7, '2025-11-28 10:45:31', '2025-11-28 10:45:31', 0),
(15, 13, '2025-11-28', 'wednesday_service', 4, 'absent', '', 0, 7, '2025-11-28 12:00:02', '2025-11-28 12:00:02', 0),
(16, 14, '2025-11-28', 'wednesday_service', 4, 'absent', '', 0, 7, '2025-11-28 12:00:02', '2025-11-28 12:00:02', 0),
(17, 9, '2025-11-28', 'wednesday_service', 4, 'present', '', 0, 7, '2025-11-28 12:00:02', '2025-11-28 12:00:02', 0),
(18, 8, '2025-11-28', 'wednesday_service', 4, 'absent', '', 0, 7, '2025-11-28 12:00:02', '2025-11-28 12:00:02', 0),
(19, 1, '2025-11-27', 'friday_service', 3, 'present', '', 0, 7, '2025-11-28 12:42:16', '2025-11-28 12:42:16', 0),
(20, 7, '2025-11-27', 'friday_service', 3, 'present', '', 0, 7, '2025-11-28 12:42:16', '2025-11-28 12:42:16', 0),
(21, 1, '2025-11-27', 'sunday_service', 3, 'present', '', 0, 7, '2025-11-28 12:42:33', '2025-11-28 12:42:33', 0),
(22, 7, '2025-11-27', 'sunday_service', 3, 'present', '', 0, 7, '2025-11-28 12:42:33', '2025-11-28 12:42:33', 0),
(23, 13, '2025-11-23', 'global_communion_service', 4, 'present', '', 0, 7, '2025-11-28 12:42:53', '2025-11-28 12:42:53', 0),
(24, 14, '2025-11-23', 'global_communion_service', 4, 'present', '', 0, 7, '2025-11-28 12:42:53', '2025-11-28 12:42:53', 0),
(25, 9, '2025-11-23', 'global_communion_service', 4, 'present', '', 0, 7, '2025-11-28 12:42:53', '2025-11-28 12:42:53', 0),
(26, 8, '2025-11-23', 'global_communion_service', 4, 'present', '', 0, 7, '2025-11-28 12:42:53', '2025-11-28 12:42:53', 0),
(27, 13, '2025-11-23', 'wednesday_service', 4, 'present', '', 0, 7, '2025-11-28 12:43:04', '2025-11-28 12:43:04', 0),
(28, 14, '2025-11-23', 'wednesday_service', 4, 'present', '', 0, 7, '2025-11-28 12:43:04', '2025-11-28 12:43:04', 0),
(29, 9, '2025-11-23', 'wednesday_service', 4, 'present', '', 0, 7, '2025-11-28 12:43:04', '2025-11-28 12:43:04', 0),
(30, 8, '2025-11-23', 'wednesday_service', 4, 'present', '', 0, 7, '2025-11-28 12:43:04', '2025-11-28 12:43:04', 0),
(33, 1, '2025-11-23', 'global_communion_service', 3, 'present', '', 0, 7, '2025-11-28 12:44:13', '2025-11-28 12:44:13', 0),
(34, 7, '2025-11-23', 'global_communion_service', 3, 'present', '', 0, 7, '2025-11-28 12:44:13', '2025-11-28 12:44:13', 0),
(35, 1, '2025-11-26', 'global_communion_service', 3, 'present', '', 1, 7, '2025-11-28 12:44:25', '2025-11-28 23:31:59', 0),
(36, 7, '2025-11-26', 'global_communion_service', 3, 'present', '', 1, 7, '2025-11-28 12:44:25', '2025-11-28 23:31:59', 0),
(37, 1, '2025-11-26', 'sunday_service', 3, 'present', '', 1, 7, '2025-11-28 12:44:58', '2025-11-28 23:31:14', 0),
(38, 7, '2025-11-26', 'sunday_service', 3, 'present', '', 1, 7, '2025-11-28 12:44:59', '2025-11-28 23:31:14', 0),
(39, 1, '2025-11-23', 'wednesday_service', 3, 'present', '', 0, 7, '2025-11-28 12:45:09', '2025-11-28 12:45:09', 0),
(40, 7, '2025-11-23', 'wednesday_service', 3, 'present', '', 0, 7, '2025-11-28 12:45:09', '2025-11-28 12:45:09', 0),
(41, 1, '2025-11-23', 'friday_service', 3, 'present', '', 0, 7, '2025-11-28 12:45:20', '2025-11-28 12:45:20', 0),
(42, 7, '2025-11-23', 'friday_service', 3, 'present', '', 0, 7, '2025-11-28 12:45:20', '2025-11-28 12:45:20', 0),
(44, 1, '2025-11-27', 'wednesday_service', 3, 'present', '', 0, 7, '2025-11-28 12:45:50', '2025-11-28 12:45:50', 0),
(45, 7, '2025-11-27', 'wednesday_service', 3, 'present', '', 0, 7, '2025-11-28 12:45:50', '2025-11-28 12:45:50', 0),
(47, 1, '2025-12-01', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-01 09:14:02', '2025-12-01 09:14:02', 0),
(48, 7, '2025-12-01', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-01 09:14:02', '2025-12-01 09:14:02', 0),
(49, 1, '2025-12-04', 'wednesday_service', 3, 'present', '', 0, 7, '2025-12-04 13:38:57', '2025-12-04 13:38:57', 0),
(50, 7, '2025-12-04', 'wednesday_service', 3, 'present', '', 0, 7, '2025-12-04 13:38:57', '2025-12-04 13:38:57', 0),
(53, 13, '2025-12-08', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-08 17:18:54', '2025-12-08 17:18:54', 0),
(54, 14, '2025-12-08', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-08 17:18:54', '2025-12-08 17:18:54', 0),
(55, 9, '2025-12-08', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-08 17:18:54', '2025-12-08 17:18:54', 0),
(56, 8, '2025-12-08', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-08 17:18:54', '2025-12-08 17:18:54', 0),
(57, 1, '2025-12-08', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-08 20:22:51', '2025-12-08 20:22:51', 0),
(58, 7, '2025-12-08', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-08 20:22:51', '2025-12-08 20:22:51', 0),
(59, 1, '2025-12-09', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-09 17:25:28', '2025-12-09 17:25:28', 0),
(60, 7, '2025-12-09', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-09 17:25:28', '2025-12-09 17:25:28', 0),
(61, 1, '2025-12-12', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-12 23:32:12', '2025-12-12 23:32:12', 0),
(62, 7, '2025-12-12', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-12 23:32:12', '2025-12-12 23:32:12', 0),
(63, 13, '2025-12-12', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-12 23:44:05', '2025-12-12 23:44:05', 0),
(64, 14, '2025-12-12', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-12 23:44:05', '2025-12-12 23:44:05', 0),
(65, 9, '2025-12-12', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-12 23:44:05', '2025-12-12 23:44:05', 0),
(66, 8, '2025-12-12', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-12 23:44:05', '2025-12-12 23:44:05', 0),
(67, 1, '2025-12-13', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-13 01:03:52', '2025-12-13 01:03:52', 0),
(68, 7, '2025-12-13', 'global_communion_service', 3, 'present', '', 0, 7, '2025-12-13 01:03:52', '2025-12-13 01:03:52', 0),
(69, 13, '2025-12-13', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-13 01:04:54', '2025-12-13 01:04:54', 0),
(70, 14, '2025-12-13', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-13 01:04:54', '2025-12-13 01:04:54', 0),
(71, 9, '2025-12-13', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-13 01:04:54', '2025-12-13 01:04:54', 0),
(72, 8, '2025-12-13', 'global_communion_service', 4, 'present', '', 0, 7, '2025-12-13 01:04:54', '2025-12-13 01:04:54', 0),
(73, 1, '2025-12-20', 'friday_service', 3, 'present', '', 0, 7, '2025-12-20 18:31:04', '2025-12-20 18:31:04', 0),
(74, 7, '2025-12-20', 'friday_service', 3, 'present', '', 0, 7, '2025-12-20 18:31:04', '2025-12-20 18:31:04', 0),
(75, 1, '2025-12-21', 'sunday_service', 3, 'present', '', 0, NULL, '2025-12-21 15:11:53', '2025-12-21 15:11:53', 0),
(76, 7, '2025-12-21', 'sunday_service', 3, 'present', '', 0, NULL, '2025-12-21 15:11:53', '2025-12-21 15:11:53', 0),
(77, 1, '2025-12-22', 'global_communion_service', 3, 'present', '', 0, NULL, '2025-12-22 11:23:09', '2025-12-22 11:23:09', 0),
(78, 7, '2025-12-22', 'global_communion_service', 3, 'present', '', 0, NULL, '2025-12-22 11:23:09', '2025-12-22 11:23:09', 0),
(79, 1, '2025-12-23', 'global_communion_service', 3, 'present', '', 0, NULL, '2025-12-23 10:32:29', '2025-12-23 10:32:29', 0),
(80, 7, '2025-12-23', 'global_communion_service', 3, 'present', '', 0, NULL, '2025-12-23 10:32:29', '2025-12-23 10:32:29', 0),
(81, 1, '2026-01-17', 'sunday_service', 3, 'present', '', 0, NULL, '2026-01-17 14:34:02', '2026-01-17 14:34:02', 0),
(82, 7, '2026-01-17', 'sunday_service', 3, 'present', '', 0, NULL, '2026-01-17 14:34:02', '2026-01-17 14:34:02', 0),
(83, 15, '2026-01-19', 'sunday_service', 3, 'present', '', 0, NULL, '2026-01-19 17:31:34', '2026-01-19 17:31:34', 0),
(84, 1, '2026-01-19', 'sunday_service', 3, 'present', '', 0, NULL, '2026-01-19 17:31:35', '2026-01-19 17:31:35', 0),
(85, 7, '2026-01-19', 'sunday_service', 3, 'present', '', 0, NULL, '2026-01-19 17:31:35', '2026-01-19 17:31:35', 0),
(86, 13, '2026-01-19', 'sunday_service', 4, 'present', '', 0, NULL, '2026-01-19 17:32:12', '2026-01-19 17:32:12', 0),
(87, 14, '2026-01-19', 'sunday_service', 4, 'present', '', 0, NULL, '2026-01-19 17:32:12', '2026-01-19 17:32:12', 0),
(88, 9, '2026-01-19', 'sunday_service', 4, 'present', '', 0, NULL, '2026-01-19 17:32:12', '2026-01-19 17:32:12', 0),
(89, 8, '2026-01-19', 'sunday_service', 4, 'present', '', 0, NULL, '2026-01-19 17:32:12', '2026-01-19 17:32:12', 0),
(90, 13, '2026-02-13', 'friday_service', 4, 'present', '', 0, NULL, '2026-02-13 11:22:06', '2026-02-13 11:22:06', 0),
(91, 14, '2026-02-13', 'friday_service', 4, 'present', '', 0, NULL, '2026-02-13 11:22:06', '2026-02-13 11:22:06', 0),
(92, 9, '2026-02-13', 'friday_service', 4, 'present', '', 0, NULL, '2026-02-13 11:22:06', '2026-02-13 11:22:06', 0),
(93, 8, '2026-02-13', 'friday_service', 4, 'present', '', 0, NULL, '2026-02-13 11:22:06', '2026-02-13 11:22:06', 0),
(109, 15, '2026-03-07', 'wednesday_service', 3, 'present', '', 0, 7, '2026-03-07 02:40:32', '2026-03-07 02:40:32', 3),
(110, 1, '2026-03-07', 'wednesday_service', 3, 'present', '', 0, 7, '2026-03-07 02:40:32', '2026-03-07 02:40:32', 0),
(111, 7, '2026-03-07', 'wednesday_service', 3, 'absent', '', 0, 7, '2026-03-07 02:40:32', '2026-03-07 02:40:32', 0),
(112, 15, '2026-03-07', 'friday_service', 3, 'present', '', 0, 7, '2026-03-07 02:57:03', '2026-03-07 02:57:03', 4),
(113, 1, '2026-03-07', 'friday_service', 3, 'present', '', 0, 7, '2026-03-07 02:57:03', '2026-03-07 02:57:03', 0),
(114, 7, '2026-03-07', 'friday_service', 3, 'present', '', 0, 7, '2026-03-07 02:57:03', '2026-03-07 02:57:03', 0),
(118, 15, '2026-04-12', 'sunday_service', 3, 'absent', '', 0, 7, '2026-04-12 12:56:11', '2026-04-12 12:56:11', 2),
(119, 1, '2026-04-12', 'sunday_service', 3, 'present', '', 0, 7, '2026-04-12 12:56:11', '2026-04-12 12:56:11', 0),
(120, 7, '2026-04-12', 'sunday_service', 3, 'present', '', 0, 7, '2026-04-12 12:56:11', '2026-04-12 12:56:11', 0);

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','excused') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cells`
--

CREATE TABLE `cells` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `leader_id` int(11) DEFAULT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `location` text DEFAULT NULL,
  `meeting_day` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cells`
--

INSERT INTO `cells` (`id`, `name`, `leader_id`, `fellowship_id`, `location`, `meeting_day`, `created_at`, `updated_at`) VALUES
(1, 'Triumphant cell', 14, 3, 'Bankuman', 'Saturday', '2026-04-15 13:10:11', '2026-04-15 13:10:11');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Choir', 'This controls the ministration of the church', '2026-04-15 12:31:06', '2026-04-15 12:31:06');

-- --------------------------------------------------------

--
-- Table structure for table `fellowships`
--

CREATE TABLE `fellowships` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `leader_id` int(11) DEFAULT NULL,
  `assistant_leader_id` int(11) DEFAULT NULL,
  `meeting_day` enum('sunday','monday','tuesday','wednesday','thursday','friday','saturday') NOT NULL DEFAULT 'sunday',
  `meeting_time` time NOT NULL DEFAULT '10:00:00',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'active',
  `description` text DEFAULT NULL,
  `members_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fellowships`
--

INSERT INTO `fellowships` (`id`, `name`, `location`, `leader_id`, `assistant_leader_id`, `meeting_day`, `meeting_time`, `status`, `description`, `members_count`, `created_at`, `updated_at`) VALUES
(3, 'bbbbbbbbf', 'asfsgdbd', 2, NULL, 'sunday', '17:48:00', 'active', NULL, 0, '2025-11-16 15:47:38', '2026-02-13 14:47:59'),
(4, 'gegggbb thrh', 'jyytjtytj', 8, NULL, 'sunday', '19:11:00', 'active', NULL, 0, '2025-11-16 16:08:39', '2026-02-13 14:47:59');

-- --------------------------------------------------------

--
-- Table structure for table `fellowship_schedules`
--

CREATE TABLE `fellowship_schedules` (
  `id` int(11) NOT NULL,
  `fellowship_id` int(11) NOT NULL,
  `day` enum('sunday','monday','tuesday','wednesday','thursday','friday','saturday') NOT NULL,
  `time` time NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fellowship_schedules`
--

INSERT INTO `fellowship_schedules` (`id`, `fellowship_id`, `day`, `time`, `is_primary`, `created_at`) VALUES
(1, 4, 'sunday', '19:11:00', 1, '2025-11-16 16:08:39'),
(2, 4, 'monday', '18:10:00', 0, '2025-11-16 16:08:39');

-- --------------------------------------------------------

--
-- Table structure for table `financial_transactions`
--

CREATE TABLE `financial_transactions` (
  `id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `service_id` varchar(50) DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','check','card') DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `financial_transactions`
--

INSERT INTO `financial_transactions` (`id`, `transaction_date`, `fellowship_id`, `member_id`, `service_id`, `type`, `category`, `amount`, `description`, `payment_method`, `reference`, `notes`, `recorded_by`, `recorded_at`) VALUES
(1, '2025-12-09', 3, NULL, 'global_communion_service', 'income', 'tithe_offering', 57.00, 'Offering: GHS 23.00, Tithe: GHS 34.00', 'cash', NULL, NULL, 7, '2025-12-09 17:27:52'),
(11, '2026-01-17', 3, NULL, 'sunday_service', 'income', 'offering', 34.00, 'Offering: GHS 34.00', 'cash', NULL, NULL, 7, '2026-01-17 18:03:15'),
(12, '2026-04-12', 3, NULL, 'sunday_service', 'income', 'tithe_offering', 144.00, 'Offering: GHS 54.00, Tithe: GHS 90.00', 'cash', NULL, NULL, 7, '2026-04-12 13:09:33');

-- --------------------------------------------------------

--
-- Table structure for table `import_log`
--

CREATE TABLE `import_log` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `imported_by` int(11) NOT NULL,
  `imported_at` datetime DEFAULT current_timestamp(),
  `total_rows` int(11) NOT NULL,
  `success_count` int(11) NOT NULL,
  `failed_count` int(11) NOT NULL,
  `batch_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role_in_church` varchar(100) DEFAULT NULL,
  `cell_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','visitor') NOT NULL DEFAULT 'active',
  `category` enum('adult','children','teen') NOT NULL DEFAULT 'adult',
  `join_date` date DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `age_category` enum('adult','children') DEFAULT 'adult'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `photo`, `fellowship_id`, `department_id`, `role_in_church`, `cell_id`, `status`, `category`, `join_date`, `birth_date`, `notes`, `created_at`, `updated_at`, `age_category`) VALUES
(1, NULL, 'Richard', 'Nane', 'shineakakpe3@gmail.com', '+233245834338', '', 'uploads/members/member_691dfe224d518.jpg', 3, 1, '', 1, 'active', 'adult', '2025-11-19', '2003-04-16', '', '2025-11-19 17:28:02', '2026-04-15 13:12:50', 'children'),
(2, 2, 'Richard', 'Nane', 'agbeyalberta@gmail.com', '+233245834338', '', NULL, 4, NULL, NULL, NULL, 'inactive', 'adult', '2025-11-19', '0000-00-00', '', '2025-11-19 17:33:18', '2026-04-15 15:15:40', 'children'),
(7, 7, 'Viru', 'Fiagbedzi', 'shineakakp035@gmail.com', '+233245834339', '', NULL, 3, NULL, NULL, NULL, 'visitor', 'children', '2025-11-20', '0000-00-00', '', '2025-11-20 10:13:24', '2026-04-15 15:15:40', 'children'),
(8, NULL, 'vosfsjofsf', 'weoifyiwyie', 'shinamaze@outlook.com', '+233245843338', '', NULL, 4, NULL, NULL, NULL, 'visitor', 'adult', '2025-11-20', '0000-00-00', '', '2025-11-20 13:12:41', '2025-11-22 20:37:06', 'adult'),
(9, NULL, 'Theophilusss', 'weoifyivwyies', 'shinadmaze@outlook.com', '+233245843333', '', NULL, 4, NULL, NULL, NULL, 'visitor', 'children', '2025-11-20', '0000-00-00', '', '2025-11-20 17:18:22', '2025-11-22 20:38:10', 'adult'),
(13, 12, 'Amanyo', 'Theopg', 'sdhineakakpe3@gmail.com', '+233245834336', '', NULL, 4, NULL, NULL, NULL, 'visitor', 'children', '2025-11-21', '0000-00-00', '', '2025-11-21 18:08:18', '2026-04-15 15:57:20', 'adult'),
(14, NULL, 'Lebene', 'Akakpo', 'shinarmazee@outlook.com', '+233245843331', '', NULL, 4, NULL, NULL, NULL, 'active', 'children', '2025-11-22', '0000-00-00', '', '2025-11-22 11:31:51', NULL, 'adult'),
(15, 17, 'David', 'Sorkpor', 'sorkpordavid@gmail.com', '0554552456', 'Lapaz', NULL, 3, NULL, NULL, NULL, 'active', 'adult', '2026-01-17', '1993-02-09', '', '2026-01-17 16:40:52', '2026-04-15 16:23:09', 'adult');

-- --------------------------------------------------------

--
-- Table structure for table `partnership_arms`
--

CREATE TABLE `partnership_arms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `target_amount` decimal(10,2) DEFAULT NULL,
  `current_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partnership_arms`
--

INSERT INTO `partnership_arms` (`id`, `name`, `description`, `target_amount`, `current_amount`, `start_date`, `end_date`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'Rhapsody of Realities', '', NULL, 0.00, NULL, NULL, 1, '2025-11-27 10:13:26', '2026-04-16 15:20:47'),
(3, 'Healing Stream Healing Service', 'fdchgfvjhvhk', NULL, 0.00, NULL, NULL, 1, '2025-11-27 10:23:01', '2026-04-16 15:20:47'),
(4, 'Inner city', '', NULL, 0.00, NULL, NULL, 1, '2025-12-12 23:30:01', '2026-04-16 15:20:47');

-- --------------------------------------------------------

--
-- Table structure for table `partnership_contributions`
--

CREATE TABLE `partnership_contributions` (
  `id` int(11) NOT NULL,
  `financial_transaction_id` int(11) DEFAULT NULL,
  `partnership_arm_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `contribution_date` date NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_receipts`
--

CREATE TABLE `payment_receipts` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `receipt_number` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_via_email` tinyint(1) NOT NULL DEFAULT 0,
  `email_sent_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `payment_type` enum('tithe','partnership','offering') NOT NULL,
  `partnership_arm_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `reference` varchar(100) NOT NULL,
  `espees_reference` varchar(100) DEFAULT NULL,
  `payment_url` text DEFAULT NULL,
  `status` enum('pending','verified','failed','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `espees_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `role` varchar(20) NOT NULL,
  `module` varchar(50) NOT NULL,
  `allowed` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `role`, `module`, `allowed`) VALUES
(1, 'admin', 'dashboard', 1),
(2, 'admin', 'users', 1),
(3, 'admin', 'fellowships', 1),
(4, 'admin', 'attendance', 1),
(5, 'admin', 'financial', 1),
(6, 'admin', 'reports', 1),
(7, 'admin', 'settings', 1),
(8, 'pastor', 'dashboard', 1),
(9, 'pastor', 'users', 0),
(10, 'pastor', 'fellowships', 1),
(11, 'pastor', 'attendance', 1),
(12, 'pastor', 'financial', 1),
(13, 'pastor', 'reports', 1),
(14, 'pastor', 'settings', 0),
(15, 'pcf', 'dashboard', 1),
(16, 'pcf', 'users', 0),
(17, 'pcf', 'fellowships', 1),
(18, 'pcf', 'attendance', 1),
(19, 'pcf', 'financial', 0),
(20, 'pcf', 'reports', 0),
(21, 'pcf', 'settings', 0),
(22, 'leader', 'dashboard', 1),
(23, 'leader', 'users', 0),
(24, 'leader', 'fellowships', 0),
(25, 'leader', 'attendance', 1),
(26, 'leader', 'financial', 0),
(27, 'leader', 'reports', 0),
(28, 'leader', 'settings', 0),
(29, 'admin', 'profile', 1),
(30, 'admin', 'members', 1),
(31, 'admin', 'cells', 1),
(32, 'admin', 'departments', 1),
(40, 'pastor', 'profile', 1),
(41, 'pastor', 'members', 1),
(42, 'pastor', 'cells', 1),
(43, 'pastor', 'departments', 1),
(51, 'pcf', 'profile', 1),
(52, 'pcf', 'members', 1),
(53, 'pcf', 'cells', 1),
(54, 'pcf', 'departments', 0),
(62, 'leader', 'profile', 1),
(63, 'leader', 'members', 0),
(64, 'leader', 'cells', 0),
(65, 'leader', 'departments', 0),
(73, 'member', 'dashboard', 0),
(74, 'member', 'users', 0),
(75, 'member', 'fellowships', 0),
(76, 'member', 'attendance', 0),
(77, 'member', 'financial', 0),
(78, 'member', 'reports', 0),
(79, 'member', 'settings', 0),
(80, 'member', 'profile', 1),
(81, 'member', 'members', 0),
(82, 'member', 'cells', 0),
(83, 'member', 'departments', 0);

-- --------------------------------------------------------

--
-- Table structure for table `recurring_payments`
--

CREATE TABLE `recurring_payments` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `fellowship_id` int(11) DEFAULT NULL,
  `payment_type` enum('tithe','partnership','offering') NOT NULL,
  `partnership_arm_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `frequency` enum('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_payment_date` date NOT NULL,
  `last_payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'online',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `total_payments` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recurring_payment_history`
--

CREATE TABLE `recurring_payment_history` (
  `id` int(11) NOT NULL,
  `recurring_payment_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `status` enum('success','failed','skipped') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_logs`
--

CREATE TABLE `report_logs` (
  `id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `send_method` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `recipients_count` int(11) DEFAULT 0,
  `results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`results`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report_logs`
--

INSERT INTO `report_logs` (`id`, `report_type`, `send_method`, `start_date`, `end_date`, `sent_by`, `recipients_count`, `results`, `created_at`, `updated_at`) VALUES
(1, 'overview', 'email', '2025-12-01', '2025-12-21', 7, 1, '{\"email\":[{\"recipient\":\"shinamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-21 14:54:44', '2025-12-21 14:54:44'),
(2, 'overview', 'email', '2025-12-01', '2025-12-21', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-21 15:00:14', '2025-12-21 15:00:14'),
(3, 'overview', 'email', '2025-12-01', '2025-12-21', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-21 15:13:28', '2025-12-21 15:13:28'),
(4, 'overview', 'email', '2025-12-01', '2025-12-22', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-22 10:58:15', '2025-12-22 10:58:15'),
(5, 'overview', 'email', '2025-12-01', '2025-12-22', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[],\"first_timers\":[{\"name\":\"Vinog Vondolia\",\"count\":1}]}', '2025-12-22 11:14:35', '2025-12-22 11:14:35'),
(6, 'overview', 'email', '2025-12-01', '2025-12-22', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-22 13:37:57', '2025-12-22 13:37:57'),
(7, 'overview', 'email', '2025-12-01', '2025-12-22', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-22 13:52:04', '2025-12-22 13:52:04'),
(8, 'overview', 'email', '2025-12-01', '2025-12-22', 7, 1, '{\"email\":[{\"recipient\":\"shineamaze@outlook.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-22 14:00:20', '2025-12-22 14:00:20'),
(9, 'overview', 'email', '2025-12-01', '2025-12-22', 7, 1, '{\"email\":[{\"recipient\":\"vinolia@gmail.com\",\"success\":true,\"message\":\"Email sent successfully\"}],\"sms\":[]}', '2025-12-22 14:51:00', '2025-12-22 14:51:00'),
(10, 'financial_overview', 'email', '2026-01-01', '2026-01-17', 7, 2, '{\"email\":[{\"recipient\":\"shineakakpe3@gmail.com\",\"success\":true},{\"recipient\":\"shineamaze@outlook.com\",\"success\":true}],\"sms\":[]}', '2026-01-17 22:30:16', '2026-01-17 22:30:16'),
(11, 'financial_detailed', 'email', '2026-01-01', '2026-01-17', 7, 1, '{\"email\":[{\"recipient\":\"shineakakpe3@gmail.com\",\"success\":true}],\"sms\":[]}', '2026-01-17 22:31:55', '2026-01-17 22:31:55'),
(12, 'overview', 'email', '2026-03-01', '2026-03-07', 7, 1, '{\"email\":[{\"recipient\":\"shineakakpe3@gmail.com\",\"success\":false,\"message\":\"Failed to send email\"}],\"sms\":[]}', '2026-03-07 02:22:11', '2026-03-07 02:22:11');

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_imports`
--

CREATE TABLE `scheduled_imports` (
  `id` int(11) NOT NULL,
  `gsheet_url` text NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL,
  `last_run` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `created_at`, `created_by`, `is_active`) VALUES
('friday_service', 'Friday Service', '2025-11-23 16:28:40', NULL, 1),
('global_communion_service', 'Global Communion Service', '2025-11-23 14:11:24', NULL, 1),
('sunday_service', 'Sunday service', '2025-11-23 14:30:01', NULL, 1),
('wednesday_service', 'Wednesday Service', '2025-11-23 16:09:44', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `category`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'general', 'church_name', 'Christ Embassy Tema Newtown', '2026-04-12 13:14:00', '2026-04-12 13:15:05'),
(2, 'general', 'church_address', '', '2026-04-12 13:14:00', '2026-04-12 13:15:05'),
(3, 'general', 'church_phone', '', '2026-04-12 13:14:00', '2026-04-12 13:15:05'),
(4, 'general', 'church_email', '', '2026-04-12 13:14:00', '2026-04-12 13:15:05'),
(5, 'general', 'currency', 'GHS', '2026-04-12 13:14:00', '2026-04-12 13:15:05');

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

CREATE TABLE `sms_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_templates`
--

INSERT INTO `sms_templates` (`id`, `name`, `message`, `is_default`) VALUES
(1, 'Welcome Message', 'Welcome to Grace Community Church, {{name}}! We are blessed to have you. Your fellowship: {{fellowship}}. Stay connected! - Pastor Team', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tithe_periods`
--

CREATE TABLE `tithe_periods` (
  `id` int(11) NOT NULL,
  `period_code` varchar(20) NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tithe_transactions`
--

CREATE TABLE `tithe_transactions` (
  `id` int(11) NOT NULL,
  `financial_transaction_id` int(11) DEFAULT NULL,
  `member_id` int(11) NOT NULL,
  `fellowship_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `period` varchar(20) DEFAULT NULL COMMENT 'e.g., "2024-12", "Weekly", "Monthly", "Quarterly"',
  `period_id` int(11) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','check','card') DEFAULT 'cash',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_first_tithe` tinyint(1) DEFAULT 1,
  `is_second_tithe` tinyint(1) DEFAULT 0,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tithe_transactions`
--

INSERT INTO `tithe_transactions` (`id`, `financial_transaction_id`, `member_id`, `fellowship_id`, `amount`, `period`, `period_id`, `transaction_date`, `payment_method`, `reference`, `notes`, `is_first_tithe`, `is_second_tithe`, `recorded_by`, `recorded_at`, `updated_at`) VALUES
(1, 1, 1, 3, 34.00, '2025-12', NULL, '2025-12-09', 'cash', NULL, 'Tithe payment', 1, 0, 7, '2025-12-09 17:27:52', '2025-12-09 17:27:52'),
(2, 12, 15, 3, 45.00, '2026-04', NULL, '2026-04-12', 'cash', NULL, 'Tithe payment', 1, 0, 7, '2026-04-12 13:09:33', '2026-04-12 13:09:33'),
(3, 12, 1, 3, 45.00, '2026-04', NULL, '2026-04-12', 'cash', NULL, 'Tithe payment', 1, 0, 7, '2026-04-12 13:09:33', '2026-04-12 13:09:33'),
(4, NULL, 15, 3, 23.00, NULL, NULL, '2026-04-21', 'mobile_money', 'errt', NULL, 1, 0, 17, '2026-04-21 17:07:54', '2026-04-21 17:07:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `kingschat_id` varchar(100) DEFAULT NULL,
  `kingschat_token` text DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','pastor','pcf','leader','member') NOT NULL DEFAULT 'member',
  `fellowship_id` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `avatar` varchar(255) DEFAULT 'assets/avatar-placeholder.png',
  `last_seen` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `kingschat_id`, `kingschat_token`, `email`, `first_name`, `last_name`, `role`, `fellowship_id`, `member_id`, `status`, `notes`, `last_login`, `created_at`, `updated_at`, `avatar`, `last_seen`, `phone`) VALUES
(2, 'Theo', '$2y$10$N.VCClQwFeR1hV65BBVBiO.ohKfwIDr4qF7sQZ1ZNGfuIhYIUF9UK', NULL, NULL, 'elisabeth@outlook.com', 'Elizabeth', 'Theo', 'pastor', 3, 2, 'active', '', NULL, '2025-11-15 18:29:00', '2026-04-15 15:15:40', 'assets/avatar-placeholder.png', '2026-04-15 15:15:40', NULL),
(3, 'John', '$2y$10$drutxMyHpMKBqgl/N0KTXeIE7vFgss1Y0MZWzssJIlhregQidL.X2', NULL, NULL, 'shineakakpe3@gmail.com', 'Richard', 'Ivy', 'pcf', NULL, NULL, 'active', '', NULL, '2025-11-15 18:47:13', NULL, 'assets/avatar-placeholder.png', '2025-11-16 01:26:02', NULL),
(7, 'Peter', '$2y$10$eNvL83keCvFDmMi6EwZvD.wM2N0MriRV3l39Hfefnf/j74V1zHSJq', NULL, NULL, 'shineamaze@outlook.com', 'Mr Abel', 'Elizabeth', 'admin', 3, 7, 'active', '', NULL, '2025-11-15 19:10:21', '2026-04-19 13:30:04', 'assets/avatar-placeholder.png', '2026-04-19 13:30:04', NULL),
(11, 'Vinolia', '$2y$10$sFVB9uk4WUHTygm26jOYp.7CK/nlKmIGSAg1t7xjMZsiiuSfXzOSO', NULL, NULL, 'vinolia@gmail.com', 'Vinolia', 'Doom', 'pcf', 4, NULL, 'active', NULL, NULL, '2025-11-19 07:51:40', NULL, 'assets/avatar-placeholder.png', '2025-11-19 07:51:40', NULL),
(12, 'amanyo.theopg', '$2y$10$pJpU8gpjTMbR7sdHk3ogPuB4Oi9OgFocv7YML/fill6bgAR8LOwge', NULL, NULL, NULL, '', '', '', 4, 13, 'active', NULL, NULL, '2026-04-15 15:57:20', '2026-04-15 16:21:53', 'assets/avatar-placeholder.png', '2026-04-15 16:21:53', NULL),
(17, 'david', '$2y$10$ujLObi9fqYEMrIT9nYkzZ.NBOZ295PKtvkZT5WlcIMBQODaK7vZ7O', NULL, NULL, 'sorkpordavid@gmail.com', '', '', 'member', 3, 15, 'active', NULL, NULL, '2026-04-15 16:23:09', '2026-04-21 17:07:53', 'assets/avatar-placeholder.png', '2026-04-21 17:07:53', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_attendance` (`service_date`,`fellowship_id`,`service_id`,`member_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_service_date` (`service_date`),
  ADD KEY `idx_service_id` (`service_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`member_id`,`service_id`,`date`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_service_id` (`service_id`),
  ADD KEY `idx_fellowship_id` (`fellowship_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `cells`
--
ALTER TABLE `cells`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fellowships`
--
ALTER TABLE `fellowships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leader_id` (`leader_id`),
  ADD KEY `assistant_leader_id` (`assistant_leader_id`);

--
-- Indexes for table `fellowship_schedules`
--
ALTER TABLE `fellowship_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fellowship` (`fellowship_id`),
  ADD KEY `idx_primary` (`fellowship_id`,`is_primary`);

--
-- Indexes for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fellowship_id` (`fellowship_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `import_log`
--
ALTER TABLE `import_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_id` (`batch_id`),
  ADD KEY `imported_by` (`imported_by`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fellowship_id` (`fellowship_id`),
  ADD KEY `status` (`status`),
  ADD KEY `join_date` (`join_date`),
  ADD KEY `idx_members_name` (`first_name`,`last_name`),
  ADD KEY `idx_members_fellowship` (`fellowship_id`,`status`),
  ADD KEY `idx_members_created` (`created_at`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `partnership_arms`
--
ALTER TABLE `partnership_arms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partnership_contributions`
--
ALTER TABLE `partnership_contributions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `financial_transaction_id` (`financial_transaction_id`),
  ADD KEY `partnership_arm_id` (`partnership_arm_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_receipt_number` (`receipt_number`),
  ADD KEY `idx_transaction_id` (`transaction_id`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reference` (`reference`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_fellowship_id` (`fellowship_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_payment_type` (`payment_type`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_module` (`role`,`module`);

--
-- Indexes for table `recurring_payments`
--
ALTER TABLE `recurring_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_fellowship_id` (`fellowship_id`),
  ADD KEY `idx_next_payment_date` (`next_payment_date`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `recurring_payment_history`
--
ALTER TABLE `recurring_payment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_recurring_payment_id` (`recurring_payment_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `report_logs`
--
ALTER TABLE `report_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sent_by` (`sent_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `scheduled_imports`
--
ALTER TABLE `scheduled_imports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`category`,`key`);

--
-- Indexes for table `sms_templates`
--
ALTER TABLE `sms_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tithe_periods`
--
ALTER TABLE `tithe_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_period_code` (`period_code`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `tithe_transactions`
--
ALTER TABLE `tithe_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `financial_transaction_id` (`financial_transaction_id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_member` (`member_id`),
  ADD KEY `idx_fellowship` (`fellowship_id`),
  ADD KEY `idx_date_period` (`transaction_date`,`period`),
  ADD KEY `idx_member_period` (`member_id`,`period`),
  ADD KEY `idx_fellowship_period` (`fellowship_id`,`period`),
  ADD KEY `period_id` (`period_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_kingschat_id` (`kingschat_id`),
  ADD KEY `idx_member_id` (`member_id`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cells`
--
ALTER TABLE `cells`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fellowships`
--
ALTER TABLE `fellowships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `fellowship_schedules`
--
ALTER TABLE `fellowship_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `import_log`
--
ALTER TABLE `import_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `partnership_arms`
--
ALTER TABLE `partnership_arms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `partnership_contributions`
--
ALTER TABLE `partnership_contributions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `recurring_payments`
--
ALTER TABLE `recurring_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recurring_payment_history`
--
ALTER TABLE `recurring_payment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_logs`
--
ALTER TABLE `report_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `scheduled_imports`
--
ALTER TABLE `scheduled_imports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `sms_templates`
--
ALTER TABLE `sms_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tithe_periods`
--
ALTER TABLE `tithe_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tithe_transactions`
--
ALTER TABLE `tithe_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `fellowships`
--
ALTER TABLE `fellowships`
  ADD CONSTRAINT `fellowships_ibfk_1` FOREIGN KEY (`leader_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fellowships_ibfk_2` FOREIGN KEY (`assistant_leader_id`) REFERENCES `members` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `fellowship_schedules`
--
ALTER TABLE `fellowship_schedules`
  ADD CONSTRAINT `fellowship_schedules_ibfk_1` FOREIGN KEY (`fellowship_id`) REFERENCES `fellowships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`fellowship_id`) REFERENCES `fellowships` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `financial_transactions_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `import_log`
--
ALTER TABLE `import_log`
  ADD CONSTRAINT `import_log_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_fellowship_fk` FOREIGN KEY (`fellowship_id`) REFERENCES `fellowships` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `partnership_contributions`
--
ALTER TABLE `partnership_contributions`
  ADD CONSTRAINT `partnership_contributions_ibfk_1` FOREIGN KEY (`financial_transaction_id`) REFERENCES `financial_transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `partnership_contributions_ibfk_2` FOREIGN KEY (`partnership_arm_id`) REFERENCES `partnership_arms` (`id`),
  ADD CONSTRAINT `partnership_contributions_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
  ADD CONSTRAINT `partnership_contributions_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `report_logs`
--
ALTER TABLE `report_logs`
  ADD CONSTRAINT `report_logs_ibfk_1` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `tithe_transactions`
--
ALTER TABLE `tithe_transactions`
  ADD CONSTRAINT `tithe_transactions_ibfk_1` FOREIGN KEY (`financial_transaction_id`) REFERENCES `financial_transactions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tithe_transactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tithe_transactions_ibfk_3` FOREIGN KEY (`fellowship_id`) REFERENCES `fellowships` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tithe_transactions_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tithe_transactions_ibfk_5` FOREIGN KEY (`period_id`) REFERENCES `tithe_periods` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
