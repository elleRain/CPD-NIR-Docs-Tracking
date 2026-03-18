-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 17, 2026 at 06:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `docs_tracking`
--

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `attachment_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`attachment_id`, `document_id`, `file_name`, `file_path`, `uploaded_at`) VALUES
(1, 1, '1771911293_0_guzon week6.pdf', '../uploads/1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-02 08:36:21'),
(2, 2, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772499818_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-02 08:36:42'),
(3, 3, '1771911293_0_guzon week6.pdf', '../uploads/1772441074_0_1771911293_0_guzon week6.pdf', '2026-03-02 08:44:34'),
(4, 4, '1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772441090_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-02 08:44:50'),
(5, 5, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772502402_0_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-03 01:46:42'),
(6, 6, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772502443_0_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-03 01:47:23'),
(7, 7, '1772440581_0_1771911293_0_guzon week6 (1).pdf', '../uploads/1772505948_0_1772440581_0_1771911293_0_guzon week6 (1).pdf', '2026-03-03 02:45:48'),
(8, 8, 'd3c87869-f8d6-4c46-b78b-ce028877948a.jpg', '../uploads/1772506213_0_d3c87869-f8d6-4c46-b78b-ce028877948a.jpg', '2026-03-03 02:50:13'),
(9, 9, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772510127_0_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-03 03:55:27'),
(10, 10, '1772423218_1772420792_0_couple_details_form (1).php', '../uploads/1772510429_0_1772423218_1772420792_0_couple_details_form (1).php', '2026-03-03 04:00:29'),
(11, 11, '1772440581_0_1771911293_0_guzon week6.pdf', '../uploads/1772515545_1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-03 05:03:17'),
(12, 12, '1772440581_0_1771911293_0_guzon week6.pdf', '../uploads/1772516708_1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-03 05:43:58'),
(13, 13, 'guzon week 6.pdf', '../uploads/1772583960_0_guzon week 6.pdf', '2026-03-04 00:26:00'),
(14, 20, 'guzon week4 (1).pdf', '../uploads/1772585400_0_guzon week4 (1).pdf', '2026-03-04 00:50:00'),
(15, 26, 'guzon week 6.pdf', '../uploads/1772586176_0_guzon week 6.pdf', '2026-03-04 01:02:56'),
(16, 27, 'guzon week 6.pdf', '../uploads/1772586959_0_guzon week 6.pdf', '2026-03-04 01:15:59'),
(17, 28, 'guzon week4 (1).pdf', '../uploads/1772587097_0_guzon week4 (1).pdf', '2026-03-04 01:18:17'),
(18, 29, 'guzon week4 (1).pdf', '../uploads/1772587194_0_guzon week4 (1).pdf', '2026-03-04 01:19:54'),
(19, 30, '1772440581_0_1771911293_0_guzon week6 (1).pdf', '../uploads/1772587228_0_1772440581_0_1771911293_0_guzon week6 (1).pdf', '2026-03-04 01:20:28'),
(20, 31, 'guzon week 6.pdf', '../uploads/1772589143_guzon week 6.pdf', '2026-03-04 01:24:26'),
(21, 32, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772589396_0_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 01:56:36'),
(22, 33, 'RPCPPE_Report_2026-02-16.xls', '../uploads/1772591973_0_RPCPPE_Report_2026-02-16.xls', '2026-03-04 02:39:33'),
(23, 34, 'test file.pdf', '../uploads/1772604550_test file.pdf', '2026-03-04 06:08:41'),
(24, 35, 'test file.pdf', '../uploads/1772604818_0_test file.pdf', '2026-03-04 06:13:38'),
(25, 36, 'guzon week 6 (1).pdf', '../uploads/1772605151_0_guzon week 6 (1).pdf', '2026-03-04 06:19:11'),
(26, 37, 'test file.pdf', '../uploads/1772605748_test file.pdf', '2026-03-04 06:27:57'),
(27, 38, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772605810_0_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 06:30:10'),
(28, 39, 'test file.pdf', '../uploads/1772606920_0_test file.pdf', '2026-03-04 06:48:40'),
(29, 40, 'test file.pdf', '../uploads/1772608134_0_test file.pdf', '2026-03-04 07:08:54'),
(30, 41, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '../uploads/1772612564_0_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 08:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `document_id`, `action_time`) VALUES
(1, 1, 'Uploaded updated file', 2, '2026-03-03 01:03:38'),
(2, 1, 'Uploaded updated file', 11, '2026-03-03 05:18:24'),
(3, 1, 'Uploaded updated file', 11, '2026-03-03 05:25:45'),
(4, 1, 'Uploaded updated file', 12, '2026-03-03 05:44:25'),
(5, 1, 'Uploaded updated file', 12, '2026-03-03 05:45:08'),
(6, 2, 'Requested revision for designated document: guzon week 4 (DTS-20260303-652933)', 12, '2026-03-03 06:47:50'),
(7, 2, 'Approved designated document: guzon week4 (DTS-20260303-280443)', 11, '2026-03-03 06:50:01'),
(8, 2, 'Approved designated document: guzon week4 (DTS-20260303-280443)', 11, '2026-03-03 06:50:05'),
(9, 2, 'Requested revision for designated document: guzon week4 (DTS-20260303-280443)', 11, '2026-03-03 06:50:08'),
(10, 2, 'Approved designated document: 1772423218_1772420792_0_couple_details_form (1) (DTS-20260303-262464)', 10, '2026-03-03 07:06:35'),
(11, 1, 'Approved designated document: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1) (DTS-20260303-739848)', 6, '2026-03-03 07:20:18'),
(12, 2, 'Approved designated document: 1772423218_1772420792_0_couple_details_form (1) (DTS-20260303-262464)', 10, '2026-03-03 07:44:40'),
(13, 2, 'Requested revision for designated document: 1772423218_1772420792_0_couple_details_form (1) (DTS-20260303-262464)', 10, '2026-03-03 07:44:43'),
(14, 2, 'Approved designated document: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1) (DTS-20260303-174891)', 9, '2026-03-03 07:51:48'),
(15, 2, 'Approved designated document: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1) (DTS-20260303-174891)', 9, '2026-03-03 07:51:49'),
(16, 1, 'Uploaded updated file', 31, '2026-03-04 01:29:59'),
(17, 1, 'Uploaded updated file', 31, '2026-03-04 01:40:22'),
(18, 1, 'Uploaded updated file', 31, '2026-03-04 01:52:23'),
(19, 2, 'Approved designated document: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1) (DTS-20260304-357134)', 32, '2026-03-04 03:20:42'),
(20, 2, 'Approved designated document: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1) (DTS-20260304-357134)', 32, '2026-03-04 03:21:03'),
(21, 2, 'Approved designated document: RPCPPE_Report_2026-02-16 (DTS-20260304-249374)', 33, '2026-03-04 03:43:24'),
(22, 1, 'Uploaded updated file', 34, '2026-03-04 06:09:10'),
(23, 2, 'Approved designated document: test file (DTS-20260304-417348)', 34, '2026-03-04 06:10:02'),
(24, 2, 'Approved designated document: guzon week 6 (DTS-20260304-356983)', 31, '2026-03-04 06:23:59'),
(25, 1, 'Uploaded updated file', 37, '2026-03-04 06:29:08'),
(26, 2, 'Requested revision for designated document: test file (DTS-20260304-519493)', 37, '2026-03-04 06:31:05'),
(27, 2, 'Approved designated document: test file (DTS-20260304-848452)', 40, '2026-03-04 08:32:34');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `status_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`document_id`, `tracking_number`, `title`, `description`, `type_id`, `status_id`, `created_by`, `created_at`) VALUES
(37, 'DTS-20260304-519493', 'test file', 'test', 2, 4, 2, '2026-03-04 06:27:57'),
(38, 'DTS-20260304-438412', '1772440602_0_1772377146_1772376242_0_guzon week 1 (1)', 'ttest', 2, 1, 2, '2026-03-04 06:30:10'),
(39, 'DTS-20260304-758287', 'test file', 'test', 1, 5, 2, '2026-03-04 06:48:40'),
(40, 'DTS-20260304-848452', 'test file', 'wqweqweqwewqewqqweqwe', 2, 5, 2, '2026-03-04 07:08:54'),
(41, 'DTS-20260304-765691', '1772440602_0_1772377146_1772376242_0_guzon week 1 (1)', 'test', 1, 1, 2, '2026-03-04 08:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `document_activity_log`
--

CREATE TABLE `document_activity_log` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `activity_type` enum('download','upload','view','share','print','sent','finished') NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `event_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_activity_log`
--

INSERT INTO `document_activity_log` (`id`, `document_id`, `user_id`, `recipient_id`, `activity_type`, `details`, `created_at`, `ip_address`, `user_agent`, `metadata`, `event_hash`) VALUES
(1, 2, 1, NULL, 'view', 'Previewed document', '2026-03-03 08:50:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '{\"screen_width\":1440,\"screen_height\":960,\"referrer\":\"http://localhost/docs//admin/document.php\"}', '6274c6245e43d929d3e1e413e4bc55cc28fb1469875d10623d7b511dd248b8e0'),
(2, 1, 1, NULL, 'view', 'Previewed document', '2026-03-03 08:54:25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '{\"screen_width\":1440,\"screen_height\":960,\"referrer\":\"http://localhost/docs//admin/document.php\"}', '7e0de8bf0e40abc22050922042beab9d3fc9d1f4be7dda7ca254270b1c5ad256'),
(3, 2, 1, NULL, 'download', 'Downloaded via direct link', '2026-03-03 09:02:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, 'fc30f2a2b8dbcc684b0aaa457bccc8453f89424e19afcc97d89aa9962f16f864'),
(4, 2, 1, NULL, 'share', 'Shared document with 1 user', '2026-03-03 09:03:07', NULL, NULL, NULL, NULL),
(5, 2, 1, NULL, 'upload', 'Uploaded updated file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-03 09:03:38', NULL, NULL, NULL, NULL),
(6, 5, 1, NULL, 'upload', 'Uploaded original file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-03 09:46:42', NULL, NULL, NULL, NULL),
(7, 5, 1, NULL, 'share', 'Shared document with 1 user', '2026-03-03 09:46:42', NULL, NULL, NULL, NULL),
(8, 6, 1, NULL, 'upload', 'Uploaded original file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-03 09:47:23', NULL, NULL, NULL, NULL),
(9, 6, 1, NULL, 'share', 'Shared document with 1 user', '2026-03-03 09:47:23', NULL, NULL, NULL, NULL),
(10, 6, 2, NULL, 'view', 'Previewed document', '2026-03-03 09:47:30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '{\"screen_width\":1440,\"screen_height\":960,\"referrer\":\"http://localhost/docs/superadmin/document.php?view=designated\"}', 'e42ccc4754e2cadb056facee1ee0cd7070e12c3407c95d4f6c6f819f96015642'),
(11, 1, 2, NULL, 'view', '', '2026-03-03 10:31:13', NULL, NULL, NULL, NULL),
(12, 7, 2, NULL, 'upload', 'Uploaded original file: 1772440581_0_1771911293_0_guzon week6 (1).pdf', '2026-03-03 10:45:48', NULL, NULL, NULL, NULL),
(13, 7, 2, NULL, 'share', 'Shared document with 1 user', '2026-03-03 10:45:48', NULL, NULL, NULL, NULL),
(14, 8, 2, NULL, 'upload', 'Uploaded original file: d3c87869-f8d6-4c46-b78b-ce028877948a.jpg', '2026-03-03 10:50:13', NULL, NULL, NULL, NULL),
(15, 8, 2, NULL, 'share', 'Shared document with 1 user', '2026-03-03 10:50:13', NULL, NULL, NULL, NULL),
(16, 8, 1, NULL, 'view', 'Previewed document', '2026-03-03 11:32:33', NULL, NULL, NULL, NULL),
(17, 6, 2, NULL, 'view', 'Previewed document', '2026-03-03 11:32:40', NULL, NULL, NULL, NULL),
(18, 8, 2, NULL, 'view', '', '2026-03-03 11:50:16', NULL, NULL, NULL, NULL),
(19, 9, 2, NULL, 'share', 'Successfully sent the 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf to Admin User', '2026-03-03 11:55:27', NULL, NULL, NULL, NULL),
(20, 10, 2, NULL, '', 'Successfully sent the 1772423218_1772420792_0_couple_details_form (1).php to Admin User', '2026-03-03 12:00:29', NULL, NULL, NULL, NULL),
(21, 10, 1, NULL, 'view', 'Previewed document', '2026-03-03 12:01:50', NULL, NULL, NULL, NULL),
(22, 10, 1, NULL, 'view', 'Previewed document', '2026-03-03 12:01:59', NULL, NULL, NULL, NULL),
(23, 10, 1, NULL, '', 'Successfully sent file to Ranier Guzon', '2026-03-03 12:56:39', NULL, NULL, NULL, NULL),
(24, 9, 1, NULL, 'view', 'Admin User viewed the file at March 3, 2026 1:02 PM', '2026-03-03 13:02:20', NULL, NULL, NULL, NULL),
(25, 11, 2, NULL, '', 'Successfully sent the guzon week4.pdf to Admin User', '2026-03-03 13:03:17', NULL, NULL, NULL, NULL),
(26, 11, 1, NULL, 'view', 'Admin User viewed the file at March 3, 2026 1:03 PM', '2026-03-03 13:03:27', NULL, NULL, NULL, NULL),
(27, 11, 1, NULL, 'download', 'Downloaded file: guzon week4.pdf', '2026-03-03 13:17:58', NULL, NULL, NULL, NULL),
(28, 11, 1, NULL, 'upload', 'Uploaded updated file: 1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-03 13:18:24', NULL, NULL, NULL, NULL),
(29, 11, 1, NULL, 'upload', 'Uploaded updated file: 1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-03 13:25:45', NULL, NULL, NULL, NULL),
(30, 11, 2, NULL, 'view', 'Super Admin viewed the file at March 3, 2026 1:26 PM', '2026-03-03 13:26:11', NULL, NULL, NULL, NULL),
(31, 10, 2, NULL, 'view', 'Super Admin viewed the file at March 3, 2026 1:31 PM', '2026-03-03 13:31:34', NULL, NULL, NULL, NULL),
(32, 4, 1, NULL, 'view', 'Admin User viewed the file at March 3, 2026 1:40 PM', '2026-03-03 13:40:26', NULL, NULL, NULL, NULL),
(33, 4, 2, NULL, 'view', 'Super Admin viewed the file at March 3, 2026 1:40 PM', '2026-03-03 13:40:32', NULL, NULL, NULL, NULL),
(34, 12, 2, NULL, '', 'Successfully sent the guzon week 4.pdf to Admin User', '2026-03-03 13:43:58', NULL, NULL, NULL, NULL),
(35, 12, 1, NULL, 'view', 'Admin User viewed the file at March 3, 2026 1:44 PM', '2026-03-03 13:44:16', NULL, NULL, NULL, NULL),
(36, 12, 1, NULL, 'upload', 'Uploaded updated file: 1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-03 13:44:25', NULL, NULL, NULL, NULL),
(37, 12, 1, NULL, 'upload', 'Uploaded updated file: 1772440581_0_1771911293_0_guzon week6.pdf', '2026-03-03 13:45:08', NULL, NULL, NULL, NULL),
(38, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 13:45:11', NULL, NULL, NULL, NULL),
(39, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 13:45:14', NULL, NULL, NULL, NULL),
(40, 6, 2, NULL, '', 'Finished working on document', '2026-03-03 13:49:10', NULL, NULL, NULL, NULL),
(41, 12, 2, NULL, 'view', 'Super Admin viewed the file at March 3, 2026 1:52 PM', '2026-03-03 13:52:38', NULL, NULL, NULL, NULL),
(42, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 14:04:43', NULL, NULL, NULL, NULL),
(43, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 14:07:35', NULL, NULL, NULL, NULL),
(44, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 14:10:36', NULL, NULL, NULL, NULL),
(45, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 14:10:40', NULL, NULL, NULL, NULL),
(46, 6, 2, NULL, '', 'Finished working on document', '2026-03-03 14:11:30', NULL, NULL, NULL, NULL),
(47, 6, 2, NULL, '', 'Finished working on document', '2026-03-03 14:15:29', NULL, NULL, NULL, NULL),
(48, 6, 2, NULL, '', 'Finished working on document', '2026-03-03 14:15:32', NULL, NULL, NULL, NULL),
(49, 12, 1, NULL, '', 'Finished working on document', '2026-03-03 14:15:57', NULL, NULL, NULL, NULL),
(50, 11, 1, NULL, '', 'Finished working on document', '2026-03-03 14:16:00', NULL, NULL, NULL, NULL),
(51, 6, 2, NULL, 'finished', 'Finished working on document', '2026-03-03 14:26:24', NULL, NULL, NULL, NULL),
(52, 12, 1, NULL, 'finished', 'Finished working on document', '2026-03-03 14:26:44', NULL, NULL, NULL, NULL),
(53, 11, 1, NULL, 'finished', 'Finished working on document', '2026-03-03 14:49:44', NULL, NULL, NULL, NULL),
(54, 10, 1, NULL, 'finished', 'Finished working on document', '2026-03-03 15:06:23', NULL, NULL, NULL, NULL),
(55, 9, 2, NULL, 'view', 'Super Admin viewed the file at March 3, 2026 3:33 PM', '2026-03-03 15:33:34', NULL, NULL, NULL, NULL),
(56, 7, 2, NULL, 'view', 'Super Admin viewed the file at March 3, 2026 3:36 PM', '2026-03-03 15:36:18', NULL, NULL, NULL, NULL),
(57, 9, 1, NULL, 'finished', 'Finished working on document', '2026-03-03 15:45:22', NULL, NULL, NULL, NULL),
(58, 8, 1, NULL, 'finished', 'Finished working on document', '2026-03-04 08:09:15', NULL, NULL, NULL, NULL),
(59, 7, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 8:09 AM', '2026-03-04 08:09:45', NULL, NULL, NULL, NULL),
(60, 7, 1, NULL, 'finished', 'Finished working on document', '2026-03-04 08:09:47', NULL, NULL, NULL, NULL),
(61, 2, 1, NULL, 'finished', 'Submitted document', '2026-03-04 08:25:05', NULL, NULL, NULL, NULL),
(62, 13, 2, NULL, 'sent', 'Successfully sent the guzon week 6.pdf to Admin User', '2026-03-04 08:26:00', NULL, NULL, NULL, NULL),
(63, 13, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 8:26 AM', '2026-03-04 08:26:23', NULL, NULL, NULL, NULL),
(64, 20, 2, NULL, 'upload', 'Uploaded original file: guzon week4 (1).pdf', '2026-03-04 08:50:00', NULL, NULL, NULL, NULL),
(65, 26, 2, NULL, 'sent', 'Successfully sent the guzon week 6.pdf to Admin User', '2026-03-04 09:02:56', NULL, NULL, NULL, NULL),
(66, 26, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 9:03 AM', '2026-03-04 09:03:03', NULL, NULL, NULL, NULL),
(67, 26, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 9:03 AM', '2026-03-04 09:03:09', NULL, NULL, NULL, NULL),
(68, 27, 2, NULL, 'sent', 'Successfully sent the guzon week 6.pdf to Admin User', '2026-03-04 09:15:59', NULL, NULL, NULL, NULL),
(69, 28, 2, NULL, 'sent', 'Successfully sent the guzon week4 (1).pdf to Admin User', '2026-03-04 09:18:17', NULL, NULL, NULL, NULL),
(70, 29, 2, NULL, 'sent', 'Successfully sent the guzon week4 (1).pdf to Admin User', '2026-03-04 09:19:54', NULL, NULL, NULL, NULL),
(71, 30, 2, NULL, 'sent', 'Successfully sent the 1772440581_0_1771911293_0_guzon week6 (1).pdf to Admin User', '2026-03-04 09:20:28', NULL, NULL, NULL, NULL),
(72, 31, 2, NULL, 'sent', 'Successfully sent the guzon week 6.pdf to Admin User', '2026-03-04 09:24:26', NULL, NULL, NULL, NULL),
(73, 31, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 9:26 AM', '2026-03-04 09:26:58', NULL, NULL, NULL, NULL),
(74, 31, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 9:28 AM', '2026-03-04 09:28:00', NULL, NULL, NULL, NULL),
(75, 31, 1, NULL, 'sent', 'Successfully sent file to Ranier Guzon', '2026-03-04 09:29:44', NULL, NULL, NULL, NULL),
(76, 31, 1, NULL, 'upload', 'Uploaded updated file: guzon week 6.pdf', '2026-03-04 09:29:59', NULL, NULL, NULL, NULL),
(77, 31, 1, NULL, 'download', 'Downloaded file: guzon week 6.pdf', '2026-03-04 09:30:25', NULL, NULL, NULL, NULL),
(78, 31, 1, NULL, 'upload', 'Uploaded updated file: guzon week 6.pdf', '2026-03-04 09:40:22', NULL, NULL, NULL, NULL),
(79, 31, 1, NULL, 'upload', 'Uploaded updated file: guzon week 6.pdf', '2026-03-04 09:52:23', NULL, NULL, NULL, NULL),
(80, 32, 2, NULL, 'sent', 'Successfully sent the 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf to Admin User', '2026-03-04 09:56:36', NULL, NULL, NULL, NULL),
(81, 32, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 9:56 AM', '2026-03-04 09:56:41', NULL, NULL, NULL, NULL),
(82, 33, 2, NULL, 'sent', 'Successfully sent the RPCPPE_Report_2026-02-16.xls to Admin User', '2026-03-04 10:39:33', NULL, NULL, NULL, NULL),
(83, 33, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 10:39 AM', '2026-03-04 10:39:44', NULL, NULL, NULL, NULL),
(84, 33, 1, NULL, 'sent', 'Successfully sent file to Ranier Guzon', '2026-03-04 10:41:13', NULL, NULL, NULL, NULL),
(85, 33, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 10:41 AM', '2026-03-04 10:41:21', NULL, NULL, NULL, NULL),
(86, 32, 1, NULL, 'finished', 'already good', '2026-03-04 10:54:29', NULL, NULL, NULL, NULL),
(87, 32, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 10:54 AM', '2026-03-04 10:54:37', NULL, NULL, NULL, NULL),
(88, 32, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 11:35:29', NULL, NULL, NULL, NULL),
(89, 33, 1, NULL, 'finished', 'testestetsatdasyiudljwpqegqwhhhhhhhhhhjum,', '2026-03-04 11:43:16', NULL, NULL, NULL, NULL),
(90, 31, 1, NULL, 'finished', 'asdasdasd', '2026-03-04 11:44:09', NULL, NULL, NULL, NULL),
(91, 33, 1, NULL, 'sent', 'Successfully sent file to Ranier Guzon', '2026-03-04 14:06:58', NULL, NULL, NULL, NULL),
(92, 34, 2, 1, 'sent', 'Successfully sent the test file.pdf to Admin User', '2026-03-04 14:08:41', NULL, NULL, NULL, NULL),
(93, 34, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 2:08 PM', '2026-03-04 14:08:53', NULL, NULL, NULL, NULL),
(94, 34, 1, NULL, 'upload', 'Uploaded updated file: test file.pdf', '2026-03-04 14:09:10', NULL, NULL, NULL, NULL),
(95, 34, 1, NULL, 'finished', 'i already update the file', '2026-03-04 14:09:42', NULL, NULL, NULL, NULL),
(96, 34, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 2:09 PM', '2026-03-04 14:09:52', NULL, NULL, NULL, NULL),
(97, 35, 2, 1, 'sent', 'Successfully sent the test file.pdf to Admin User', '2026-03-04 14:13:38', NULL, NULL, NULL, NULL),
(98, 36, 2, 1, 'sent', 'Successfully sent the guzon week 6 (1).pdf to Admin User', '2026-03-04 14:19:11', NULL, NULL, NULL, NULL),
(99, 37, 2, 1, 'sent', 'Successfully sent the test file.pdf to Admin User', '2026-03-04 14:27:57', NULL, NULL, NULL, NULL),
(100, 37, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 2:28 PM', '2026-03-04 14:28:21', NULL, NULL, NULL, NULL),
(101, 37, 1, NULL, 'upload', 'Uploaded updated file: test file.pdf', '2026-03-04 14:29:08', NULL, NULL, NULL, NULL),
(102, 37, 1, NULL, 'finished', 'done sir', '2026-03-04 14:29:32', NULL, NULL, NULL, NULL),
(103, 38, 2, 1, 'sent', 'Successfully sent the 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf to Admin User', '2026-03-04 14:30:10', NULL, NULL, NULL, NULL),
(104, 37, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 2:30 PM', '2026-03-04 14:30:23', NULL, NULL, NULL, NULL),
(105, 39, 2, NULL, 'upload', 'Uploaded original file: test file.pdf', '2026-03-04 14:48:40', NULL, NULL, NULL, NULL),
(106, 39, 2, NULL, '', 'Shared document with all users', '2026-03-04 14:48:40', NULL, NULL, NULL, NULL),
(107, 39, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 2:50 PM', '2026-03-04 14:50:18', NULL, NULL, NULL, NULL),
(108, 40, 2, 1, 'sent', 'Successfully sent the test file.pdf to Admin User', '2026-03-04 15:08:54', NULL, NULL, NULL, NULL),
(109, 41, 2, 1, 'sent', 'Successfully sent the 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf to Admin User', '2026-03-04 16:22:44', NULL, NULL, NULL, NULL),
(110, 41, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 4:23 PM', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(111, 41, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(112, 41, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(113, 41, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(114, 41, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(115, 41, 1, NULL, 'finished', '8kiki', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(116, 41, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(117, 41, 1, NULL, 'download', 'Downloaded file: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', '2026-03-04 16:23:46', NULL, NULL, NULL, NULL),
(118, 40, 1, NULL, 'view', 'Admin User viewed the file at March 4, 2026 4:24 PM', '2026-03-04 16:24:11', NULL, NULL, NULL, NULL),
(119, 40, 1, NULL, 'finished', 'ujmiuji7juimimu,uiko', '2026-03-04 16:24:11', NULL, NULL, NULL, NULL),
(120, 40, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 4:32 PM', '2026-03-04 16:32:34', NULL, NULL, NULL, NULL),
(121, 41, 2, NULL, 'view', 'Super Admin viewed the file at March 4, 2026 4:32 PM', '2026-03-04 16:32:50', NULL, NULL, NULL, NULL),
(122, 39, 1, NULL, 'view', 'Admin User viewed the file at March 16, 2026 3:04 PM', '2026-03-16 15:04:59', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_downloads`
--

CREATE TABLE `document_downloads` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_history_id` int(11) DEFAULT NULL,
  `downloaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_file_history`
--

CREATE TABLE `document_file_history` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `status` enum('Current','Old') DEFAULT 'Current',
  `file_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_file_history`
--

INSERT INTO `document_file_history` (`id`, `document_id`, `file_name`, `updated_by`, `updated_at`, `remarks`, `status`, `file_path`) VALUES
(1, 2, '1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf', 1, '2026-03-03 09:03:38', 'assd', 'Current', '../uploads/1772499818_1772440602_0_1772377146_1772376242_0_guzon week 1 (1).pdf'),
(2, 11, '1772440581_0_1771911293_0_guzon week6.pdf', 1, '2026-03-03 13:18:24', 'asdas', 'Old', '../uploads/1772515104_1772440581_0_1771911293_0_guzon week6.pdf'),
(3, 11, '1772440581_0_1771911293_0_guzon week6.pdf', 1, '2026-03-03 13:25:45', 'asdas', 'Current', '../uploads/1772515545_1772440581_0_1771911293_0_guzon week6.pdf'),
(4, 12, '1772440581_0_1771911293_0_guzon week6.pdf', 1, '2026-03-03 13:44:25', 'asdsa', 'Old', '../uploads/1772516665_1772440581_0_1771911293_0_guzon week6.pdf'),
(5, 12, '1772440581_0_1771911293_0_guzon week6.pdf', 1, '2026-03-03 13:45:08', 'asdsa', 'Current', '../uploads/1772516708_1772440581_0_1771911293_0_guzon week6.pdf'),
(6, 31, 'guzon week 6.pdf', 1, '2026-03-04 09:29:59', 'asdasd', 'Old', '../uploads/1772587799_guzon week 6.pdf'),
(7, 31, 'guzon week 6.pdf', 1, '2026-03-04 09:40:22', 'asdasd', 'Old', '../uploads/1772588422_guzon week 6.pdf'),
(8, 31, 'guzon week 6.pdf', 1, '2026-03-04 09:52:23', 'asdasd', 'Current', '../uploads/1772589143_guzon week 6.pdf'),
(9, 34, 'test file.pdf', 1, '2026-03-04 14:09:10', 'i updated the file', 'Current', '../uploads/1772604550_test file.pdf'),
(10, 37, 'test file.pdf', 1, '2026-03-04 14:29:08', 'updated file', 'Current', '../uploads/1772605748_test file.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `document_remarks`
--

CREATE TABLE `document_remarks` (
  `remark_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `remark` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_remarks`
--

INSERT INTO `document_remarks` (`remark_id`, `document_id`, `user_id`, `remark`, `created_at`) VALUES
(1, 5, 1, 'Required Action: To-Do', '2026-03-03 01:46:42'),
(2, 6, 1, 'Required Action: To-Do', '2026-03-03 01:47:23'),
(3, 7, 2, 'Required Action: To-Do', '2026-03-03 02:45:48'),
(4, 8, 2, 'Required Action: Review', '2026-03-03 02:50:13'),
(5, 9, 2, 'Required Action: Approval', '2026-03-03 03:55:27'),
(6, 10, 2, 'Required Action: To-Do', '2026-03-03 04:00:29'),
(7, 11, 2, 'Required Action: Review', '2026-03-03 05:03:17'),
(8, 12, 2, 'Required Action: Approval', '2026-03-03 05:43:58'),
(9, 13, 2, 'Required Action: Approval', '2026-03-04 00:26:00'),
(10, 26, 2, 'Required Action: Review', '2026-03-04 01:02:56'),
(11, 27, 2, 'Required Action: Review', '2026-03-04 01:15:59'),
(12, 28, 2, 'Required Action: To-Do', '2026-03-04 01:18:17'),
(13, 29, 2, 'Required Action: To-Do', '2026-03-04 01:19:54'),
(14, 30, 2, 'Required Action: To-Do', '2026-03-04 01:20:28'),
(15, 31, 2, 'Required Action: Approval', '2026-03-04 01:24:26'),
(16, 32, 2, 'Required Action: Review', '2026-03-04 01:56:36'),
(17, 33, 2, 'Required Action: Approval', '2026-03-04 02:39:33'),
(18, 34, 2, 'Required Action: To-Do', '2026-03-04 06:08:41'),
(19, 35, 2, 'Required Action: To-Do', '2026-03-04 06:13:38'),
(20, 36, 2, 'Required Action: To-Do', '2026-03-04 06:19:11'),
(21, 37, 2, 'Required Action: To-Do', '2026-03-04 06:27:57'),
(22, 38, 2, 'Required Action: To-Do', '2026-03-04 06:30:10'),
(23, 40, 2, 'Required Action: Review', '2026-03-04 07:08:54'),
(24, 41, 2, 'Required Action: Review', '2026-03-04 08:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `document_shares`
--

CREATE TABLE `document_shares` (
  `id` int(10) UNSIGNED NOT NULL,
  `document_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `shared_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_shares`
--

INSERT INTO `document_shares` (`id`, `document_id`, `sender_id`, `recipient_id`, `shared_by`, `created_at`) VALUES
(1, 1, NULL, 1, 2, '2026-03-02 16:36:21'),
(2, 2, NULL, 1, 2, '2026-03-02 16:36:42'),
(3, 3, NULL, 4, 1, '2026-03-02 16:44:34'),
(4, 1, NULL, 4, 1, '2026-03-03 08:54:31'),
(5, 1, NULL, 4, 1, '2026-03-03 08:55:18'),
(6, 2, NULL, 4, 1, '2026-03-03 09:03:07'),
(7, 5, NULL, 4, 1, '2026-03-03 09:46:42'),
(8, 6, NULL, 2, 1, '2026-03-03 09:47:23'),
(9, 7, NULL, 1, 2, '2026-03-03 10:45:48'),
(10, 8, NULL, 1, 2, '2026-03-03 10:50:13'),
(11, 9, NULL, 1, 2, '2026-03-03 11:55:27'),
(12, 10, NULL, 1, 2, '2026-03-03 12:00:29'),
(13, 10, NULL, 4, 1, '2026-03-03 12:56:39'),
(14, 11, NULL, 1, 2, '2026-03-03 13:03:17'),
(15, 12, NULL, 1, 2, '2026-03-03 13:43:58'),
(16, 13, NULL, 1, 2, '2026-03-04 08:26:00'),
(17, 26, NULL, 1, 2, '2026-03-04 09:02:56'),
(18, 27, NULL, 1, 2, '2026-03-04 09:15:59'),
(19, 28, NULL, 1, 2, '2026-03-04 09:18:17'),
(20, 29, NULL, 1, 2, '2026-03-04 09:19:54'),
(21, 30, NULL, 1, 2, '2026-03-04 09:20:28'),
(22, 31, NULL, 1, 2, '2026-03-04 09:24:26'),
(23, 31, NULL, 4, 1, '2026-03-04 09:29:44'),
(24, 32, NULL, 1, 2, '2026-03-04 09:56:36'),
(25, 33, NULL, 1, 2, '2026-03-04 10:39:33'),
(26, 33, NULL, 4, 1, '2026-03-04 10:41:13'),
(27, 33, NULL, 4, 1, '2026-03-04 14:06:58'),
(28, 34, NULL, 1, 2, '2026-03-04 14:08:41'),
(29, 35, NULL, 1, 2, '2026-03-04 14:13:38'),
(30, 36, NULL, 1, 2, '2026-03-04 14:19:11'),
(31, 37, NULL, 1, 2, '2026-03-04 14:27:57'),
(32, 38, NULL, 1, 2, '2026-03-04 14:30:10'),
(33, 40, NULL, 1, 2, '2026-03-04 15:08:54'),
(34, 41, NULL, 1, 2, '2026-03-04 16:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `document_status`
--

CREATE TABLE `document_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_status`
--

INSERT INTO `document_status` (`status_id`, `status_name`) VALUES
(1, 'Draft'),
(3, 'In Review'),
(4, 'For Revision'),
(5, 'Approved'),
(6, 'Rejected'),
(7, 'Archived');

-- --------------------------------------------------------

--
-- Table structure for table `document_tracking`
--

CREATE TABLE `document_tracking` (
  `tracking_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `from_user` int(11) DEFAULT NULL,
  `to_user` int(11) DEFAULT NULL,
  `action_taken` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `action_by` int(11) DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_tracking`
--

INSERT INTO `document_tracking` (`tracking_id`, `document_id`, `from_user`, `to_user`, `action_taken`, `remarks`, `action_by`, `action_date`) VALUES
(1, 1, 2, 3, 'Forwarded for Review', 'Please review this document', 2, '2026-01-26 00:46:55');

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`type_id`, `type_name`) VALUES
(1, 'Letter'),
(2, 'Memorandum'),
(3, 'Proposal'),
(4, 'Report'),
(5, 'haha'),
(6, 'test');

-- --------------------------------------------------------

--
-- Table structure for table `document_view_log`
--

CREATE TABLE `document_view_log` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `viewer_id` int(11) NOT NULL,
  `first_view_at` datetime DEFAULT NULL,
  `last_view_at` datetime DEFAULT NULL,
  `view_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_view_log`
--

INSERT INTO `document_view_log` (`id`, `document_id`, `viewer_id`, `first_view_at`, `last_view_at`, `view_count`) VALUES
(1, 1, 1, '2026-03-03 08:54:34', '2026-03-03 08:54:58', 1),
(2, 2, 1, '2026-03-03 08:57:47', '2026-03-03 09:03:28', 1),
(3, 6, 2, '2026-03-03 09:47:30', '2026-03-03 09:47:30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `invitations`
--

CREATE TABLE `invitations` (
  `id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `full_name` varchar(191) DEFAULT NULL,
  `role` enum('superadmin','admin','staff','user') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `used_by_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invitations`
--

INSERT INTO `invitations` (`id`, `token`, `email`, `full_name`, `role`, `created_at`, `expires_at`, `used_at`, `used_by_user_id`) VALUES
(4, '845ae7e5aa38a8e89467e845c991ea5b4b77a3aeccb4159cb1cc31ccf61e3225', '', '', 'admin', '2026-02-24 10:42:08', '2026-02-24 07:42:08', NULL, NULL),
(5, '73781d02765f536eac7571b35cd3fd5ddaf5b6bf159e9a73849224741455a57a', '', '', 'admin', '2026-02-24 10:48:57', '2026-02-24 07:48:57', NULL, NULL),
(6, '2b9cc4355a808fbc5194022fef8066b4cc19a773da4703960dbff6649e5091a5', '', '', 'admin', '2026-02-24 10:50:17', '2026-02-24 07:50:17', NULL, NULL),
(7, '453f212d78de75d9cbfa643ca9c3f73bf9e70bcc01c644b2b185a0a9e44595ac', '', '', 'admin', '2026-02-24 10:56:41', '2026-02-24 07:56:41', NULL, NULL),
(8, 'a21ff9abf03d44766756181203277efc9b948570c133184926de3e752ebce8f7', '', '', 'admin', '2026-02-24 10:56:55', '2026-02-24 07:56:55', '2026-02-24 11:00:40', 4),
(9, '2a083c8853ab7379754c04e420b15b8adbdbd94ba475e3fdbf0328fba7bf7f0a', '', '', 'admin', '2026-02-24 11:01:35', '2026-02-24 08:01:35', NULL, NULL),
(10, 'ac9c0d6b42f9defe4de909ce4f245601a703321a50eb5f91dd05165df05d16b8', '', '', 'admin', '2026-02-24 11:02:41', '2026-02-24 08:02:41', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `type` enum('success','warning','error','info') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  `link_url` varchar(500) DEFAULT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `type`, `title`, `message`, `data_json`, `link_url`, `actor_user_id`, `document_id`, `created_at`) VALUES
(1, 'info', 'Document Sent', 'superadmin sent you a document: 1772440602_0_1772377146_1772376242_0_guzon week 1 (1) (DTS-20260304-765691)', '{\"route\":\"document_search\",\"tracking_number\":\"DTS-20260304-765691\"}', NULL, 2, 41, '2026-03-04 08:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `notification_preferences`
--

CREATE TABLE `notification_preferences` (
  `user_id` int(11) NOT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings_json`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_preferences`
--

INSERT INTO `notification_preferences` (`user_id`, `settings_json`, `updated_at`) VALUES
(2, '{\"enabled\":true,\"realtime\":true,\"poll_interval_ms\":15000,\"categories\":{\"share\":true,\"update\":true,\"delete\":true,\"status\":true,\"download\":true,\"view\":true}}', '2026-03-04 08:44:33');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_targets`
--

CREATE TABLE `notification_targets` (
  `target_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_targets`
--

INSERT INTO `notification_targets` (`target_id`, `notification_id`, `user_id`, `is_read`, `read_at`, `is_deleted`, `deleted_at`, `created_at`) VALUES
(1, 1, 1, 0, NULL, 0, NULL, '2026-03-04 08:22:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` int(255) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `middle_name`, `last_name`, `email`, `username`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 0, 'User', 'admin@test.com', 'admin', '$2y$10$QxDOrEgODZbqXsGXhgDQV.5R5evaVeilx6ElQKsvm8AMPlrggt..S', 'admin', '2026-01-26 01:04:25', '2026-01-26 01:07:58'),
(2, 'Super', 0, 'Admin', 'superadmin@test.com', 'superadmin', '$2y$10$ETO7ZOzpC4mlIDWac1OvVu9SJml7NHctnPVFM3a57/e19iiOW/u0O', 'superadmin', '2026-01-26 01:07:58', '2026-01-26 01:07:58'),
(3, 'Staff', 0, 'User', 'staff@test.com', 'staff', '$2y$10$KUxVlQljbpyJ1MwozRZgV.A9zye.SZmtDkxdK4gKjDa.Z0fk7.mnW', 'staff', '2026-01-26 01:07:58', '2026-01-26 01:07:58'),
(4, 'Ranier', 0, 'Guzon', 'rjguzon247@gmail.com', 'ranier', '$2y$10$8lvMC5YyJD7OQbHbvMqXo.XECFBA2EEUHNSKzifg.b316EMFtMj/W', 'admin', '2026-02-24 03:00:40', '2026-02-24 03:00:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `document_activity_log`
--
ALTER TABLE `document_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_event_hash` (`event_hash`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_recipient_id` (`recipient_id`);

--
-- Indexes for table `document_downloads`
--
ALTER TABLE `document_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `file_history_id` (`file_history_id`);

--
-- Indexes for table `document_file_history`
--
ALTER TABLE `document_file_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `document_remarks`
--
ALTER TABLE `document_remarks`
  ADD PRIMARY KEY (`remark_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_id`),
  ADD KEY `idx_recipient` (`recipient_id`),
  ADD KEY `idx_shared_by` (`shared_by`),
  ADD KEY `idx_sender_id` (`sender_id`);

--
-- Indexes for table `document_status`
--
ALTER TABLE `document_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `document_tracking`
--
ALTER TABLE `document_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `from_user` (`from_user`),
  ADD KEY `to_user` (`to_user`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`type_id`);

--
-- Indexes for table `document_view_log`
--
ALTER TABLE `document_view_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_view` (`document_id`,`viewer_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `viewer_id` (`viewer_id`);

--
-- Indexes for table `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `token_2` (`token`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_document_id` (`document_id`),
  ADD KEY `idx_actor_user_id` (`actor_user_id`);

--
-- Indexes for table `notification_preferences`
--
ALTER TABLE `notification_preferences`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`user_id`,`document_id`);

--
-- Indexes for table `notification_targets`
--
ALTER TABLE `notification_targets`
  ADD PRIMARY KEY (`target_id`),
  ADD UNIQUE KEY `uniq_notification_user` (`notification_id`,`user_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`,`is_deleted`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `document_activity_log`
--
ALTER TABLE `document_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `document_downloads`
--
ALTER TABLE `document_downloads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_file_history`
--
ALTER TABLE `document_file_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `document_remarks`
--
ALTER TABLE `document_remarks`
  MODIFY `remark_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `document_shares`
--
ALTER TABLE `document_shares`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `document_status`
--
ALTER TABLE `document_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `document_tracking`
--
ALTER TABLE `document_tracking`
  MODIFY `tracking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `document_view_log`
--
ALTER TABLE `document_view_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `invitations`
--
ALTER TABLE `invitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_targets`
--
ALTER TABLE `notification_targets`
  MODIFY `target_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`type_id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `document_status` (`status_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
