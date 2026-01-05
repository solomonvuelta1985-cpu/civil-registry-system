-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 05, 2026 at 05:26 AM
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
-- Database: `iscan_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `created_at`) VALUES
(1, NULL, 'CREATE_CERTIFICATE', 'Created Certificate of Live Birth: Registry No. 589898', '2025-12-19 02:08:53'),
(2, NULL, 'CREATE_CERTIFICATE', 'Created Certificate of Live Birth: Registry No. 2025-655', '2025-12-19 03:10:35'),
(3, NULL, 'UPDATE_CERTIFICATE', 'Updated Certificate of Live Birth: Registry No. 2025-655 (ID: 2)', '2025-12-19 03:13:40'),
(4, NULL, 'CREATE_CERTIFICATE', 'Created Certificate of Live Birth: Registry No. 1970-148', '2025-12-19 03:38:45'),
(5, 0, '1', 'CREATE_EVENT', '2026-01-04 06:34:12'),
(6, 0, '1', 'DELETE_EVENT', '2026-01-04 06:49:38'),
(7, 0, '1', 'DELETE_EVENT', '2026-01-04 06:49:43');

-- --------------------------------------------------------

--
-- Table structure for table `application_for_marriage_license`
--

CREATE TABLE `application_for_marriage_license` (
  `id` int(11) NOT NULL,
  `registry_no` varchar(100) DEFAULT NULL,
  `date_of_application` date NOT NULL,
  `groom_first_name` varchar(100) NOT NULL,
  `groom_middle_name` varchar(100) DEFAULT NULL,
  `groom_last_name` varchar(100) NOT NULL,
  `groom_date_of_birth` date NOT NULL,
  `groom_place_of_birth` varchar(255) NOT NULL,
  `groom_citizenship` varchar(100) NOT NULL,
  `groom_residence` text NOT NULL,
  `groom_father_first_name` varchar(100) DEFAULT NULL,
  `groom_father_middle_name` varchar(100) DEFAULT NULL,
  `groom_father_last_name` varchar(100) DEFAULT NULL,
  `groom_father_citizenship` varchar(100) DEFAULT NULL,
  `groom_father_residence` text DEFAULT NULL,
  `groom_mother_first_name` varchar(100) DEFAULT NULL,
  `groom_mother_middle_name` varchar(100) DEFAULT NULL,
  `groom_mother_last_name` varchar(100) DEFAULT NULL,
  `groom_mother_citizenship` varchar(100) DEFAULT NULL,
  `groom_mother_residence` text DEFAULT NULL,
  `bride_first_name` varchar(100) NOT NULL,
  `bride_middle_name` varchar(100) DEFAULT NULL,
  `bride_last_name` varchar(100) NOT NULL,
  `bride_date_of_birth` date NOT NULL,
  `bride_place_of_birth` varchar(255) NOT NULL,
  `bride_citizenship` varchar(100) NOT NULL,
  `bride_residence` text NOT NULL,
  `bride_father_first_name` varchar(100) DEFAULT NULL,
  `bride_father_middle_name` varchar(100) DEFAULT NULL,
  `bride_father_last_name` varchar(100) DEFAULT NULL,
  `bride_father_citizenship` varchar(100) DEFAULT NULL,
  `bride_father_residence` text DEFAULT NULL,
  `bride_mother_first_name` varchar(100) DEFAULT NULL,
  `bride_mother_middle_name` varchar(100) DEFAULT NULL,
  `bride_mother_last_name` varchar(100) DEFAULT NULL,
  `bride_mother_citizenship` varchar(100) DEFAULT NULL,
  `bride_mother_residence` text DEFAULT NULL,
  `pdf_filename` varchar(255) DEFAULT NULL,
  `pdf_filepath` varchar(500) DEFAULT NULL,
  `status` enum('Active','Archived','Deleted') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batch_uploads`
--

CREATE TABLE `batch_uploads` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_name` varchar(200) NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `total_files` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `processed_files` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `successful_files` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `failed_files` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('uploading','queued','processing','completed','failed','cancelled') DEFAULT 'uploading',
  `progress_percentage` decimal(5,2) DEFAULT 0.00,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `estimated_completion` datetime DEFAULT NULL,
  `auto_ocr` tinyint(1) DEFAULT 1 COMMENT 'Automatically process OCR',
  `auto_validate` tinyint(1) DEFAULT 1 COMMENT 'Automatically validate data',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks batch upload operations';

-- --------------------------------------------------------

--
-- Table structure for table `batch_upload_items`
--

CREATE TABLE `batch_upload_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `certificate_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Set after successful processing',
  `pdf_attachment_id` int(10) UNSIGNED DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','processing','completed','failed','skipped') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `processing_order` smallint(5) UNSIGNED NOT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual items in a batch upload';

-- --------------------------------------------------------

--
-- Table structure for table `calendar_events`
--

CREATE TABLE `calendar_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` enum('registration','deadline','maintenance','digitization','meeting','other') NOT NULL,
  `certificate_type` enum('birth','marriage','death','license','all') DEFAULT 'all',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `end_date` date DEFAULT NULL COMMENT 'For multi-day events',
  `all_day` tinyint(1) DEFAULT 0,
  `barangay` varchar(100) DEFAULT NULL COMMENT 'Associated barangay if applicable',
  `registry_number` varchar(100) DEFAULT NULL COMMENT 'Link to specific record',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_days_before` tinyint(4) DEFAULT 1 COMMENT 'Days before to send reminder',
  `color_code` varchar(7) DEFAULT NULL COMMENT 'Hex color for calendar display',
  `icon` varchar(50) DEFAULT NULL COMMENT 'Icon name for display',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Calendar events for operational planning and tracking';

--
-- Dumping data for table `calendar_events`
--

INSERT INTO `calendar_events` (`id`, `title`, `description`, `event_type`, `certificate_type`, `event_date`, `event_time`, `end_date`, `all_day`, `barangay`, `registry_number`, `priority`, `status`, `reminder_sent`, `reminder_days_before`, `color_code`, `icon`, `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_at`) VALUES
(1, 'PSA Monthly Report Deadline', 'Submit monthly statistics to PSA', 'deadline', 'all', '2026-02-05', NULL, NULL, 0, NULL, NULL, 'high', 'pending', 0, 1, '#ef4444', NULL, 1, '2026-01-04 14:17:05', NULL, NULL, NULL),
(2, 'Barangay San Isidro Bulk Submission', 'Expected delayed birth certificates from flood-affected area', 'registration', 'birth', '2026-01-15', NULL, NULL, 0, NULL, NULL, 'medium', 'cancelled', 0, 1, '#3b82f6', NULL, 1, '2026-01-04 14:17:05', NULL, '2026-01-04 14:49:38', '2026-01-04 14:49:38'),
(3, 'System Maintenance Window', 'Database optimization and backup', 'maintenance', 'all', '2026-01-20', NULL, NULL, 0, NULL, NULL, 'low', 'cancelled', 0, 1, '#64748b', NULL, 1, '2026-01-04 14:17:05', NULL, '2026-01-04 14:49:43', '2026-01-04 14:49:43'),
(4, 'TESTING', NULL, 'deadline', 'all', '2026-01-13', '08:00:00', NULL, 0, NULL, NULL, 'high', 'pending', 0, 1, NULL, NULL, 1, '2026-01-04 14:34:12', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `certificate_of_death`
--

CREATE TABLE `certificate_of_death` (
  `id` int(11) UNSIGNED NOT NULL,
  `registry_no` varchar(100) DEFAULT NULL,
  `date_of_registration` date NOT NULL,
  `deceased_first_name` varchar(100) NOT NULL,
  `deceased_middle_name` varchar(100) DEFAULT NULL,
  `deceased_last_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `date_of_death` date NOT NULL,
  `age` int(11) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `place_of_death` varchar(255) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `father_first_name` varchar(100) DEFAULT NULL,
  `father_middle_name` varchar(100) DEFAULT NULL,
  `father_last_name` varchar(100) DEFAULT NULL,
  `mother_first_name` varchar(100) DEFAULT NULL,
  `mother_middle_name` varchar(100) DEFAULT NULL,
  `mother_last_name` varchar(100) DEFAULT NULL,
  `pdf_filename` varchar(255) DEFAULT NULL,
  `pdf_filepath` varchar(500) DEFAULT NULL,
  `status` enum('Active','Archived','Deleted') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificate_of_live_birth`
--

CREATE TABLE `certificate_of_live_birth` (
  `id` int(11) UNSIGNED NOT NULL,
  `registry_no` varchar(100) NOT NULL,
  `date_of_registration` date NOT NULL,
  `type_of_birth` enum('Single','Twin','Triplets','Quadruplets','Other') NOT NULL DEFAULT 'Single',
  `type_of_birth_other` varchar(100) DEFAULT NULL,
  `birth_order` enum('1st','2nd','3rd','4th','5th','6th','7th','Other') DEFAULT NULL,
  `birth_order_other` varchar(50) DEFAULT NULL,
  `child_first_name` varchar(100) DEFAULT NULL,
  `child_middle_name` varchar(100) DEFAULT NULL,
  `child_last_name` varchar(100) DEFAULT NULL,
  `child_date_of_birth` date DEFAULT NULL,
  `child_place_of_birth` varchar(255) DEFAULT NULL,
  `child_sex` enum('Male','Female') DEFAULT NULL,
  `mother_first_name` varchar(100) NOT NULL,
  `mother_middle_name` varchar(100) DEFAULT NULL,
  `mother_last_name` varchar(100) NOT NULL,
  `father_first_name` varchar(100) DEFAULT NULL,
  `father_middle_name` varchar(100) DEFAULT NULL,
  `father_last_name` varchar(100) DEFAULT NULL,
  `date_of_marriage` date DEFAULT NULL,
  `place_of_marriage` varchar(255) DEFAULT NULL,
  `pdf_filename` varchar(255) DEFAULT NULL,
  `pdf_filepath` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `updated_by` int(11) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Archived','Deleted') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificate_of_live_birth`
--

INSERT INTO `certificate_of_live_birth` (`id`, `registry_no`, `date_of_registration`, `type_of_birth`, `type_of_birth_other`, `birth_order`, `birth_order_other`, `child_first_name`, `child_middle_name`, `child_last_name`, `child_date_of_birth`, `child_place_of_birth`, `child_sex`, `mother_first_name`, `mother_middle_name`, `mother_last_name`, `father_first_name`, `father_middle_name`, `father_last_name`, `date_of_marriage`, `place_of_marriage`, `pdf_filename`, `pdf_filepath`, `created_at`, `updated_at`, `created_by`, `updated_by`, `status`) VALUES
(1, '589898', '1999-11-17', 'Single', '', '1st', '', NULL, NULL, NULL, NULL, NULL, NULL, 'richmond', '', 'rosete', 'richmond', '', 'rosete', NULL, '', 'cert_6944b3b549d089.46614342_1766110133.pdf', 'C:\\xampp\\htdocs\\iscan\\includes/../uploads/cert_6944b3b549d089.46614342_1766110133.pdf', '2025-12-19 02:08:53', '2025-12-19 02:08:53', NULL, NULL, 'Active'),
(2, '2025-655', '2025-08-29', 'Single', '', '2nd', '', NULL, NULL, NULL, NULL, NULL, NULL, 'Winie', 'Javier', 'De Leon', 'Reymark', 'Pante', 'Abalos', '2005-04-26', 'Baggao, Cagayan', 'cert_6944c22b05f2f3.76038801_1766113835.pdf', 'C:\\xampp\\htdocs\\iscan\\includes/../uploads/cert_6944c22b05f2f3.76038801_1766113835.pdf', '2025-12-19 03:10:35', '2025-12-19 03:13:40', NULL, NULL, 'Active'),
(3, '1970-148', '1970-03-14', 'Single', '', '7th', '', NULL, NULL, NULL, NULL, NULL, NULL, 'Brigida', '', 'Galla', 'Crispulo', '', 'Tungpalan', NULL, '', 'cert_6944c8c550b138.15084646_1766115525.pdf', 'C:\\xampp\\htdocs\\iscan\\includes/../uploads/cert_6944c8c550b138.15084646_1766115525.pdf', '2025-12-19 03:38:45', '2025-12-19 03:38:45', NULL, NULL, 'Active'),
(4, 'REG-2025-00001', '2025-12-23', 'Single', NULL, '1st', NULL, 'Juan', 'Cruz', 'Dela Cruz', '2025-12-02', 'Barangay Centro', NULL, 'Liza', 'San', 'Dela Cruz', 'Roberto', 'De', 'Dela Cruz', '2023-10-02', 'Baggao, Cagayan', 'birth_cert_1.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(5, 'REG-2025-00002', '2025-12-03', 'Single', NULL, '2nd', NULL, 'Maria', 'Santos', 'Santos', '2025-12-14', 'Barangay San Jose', NULL, 'Anna', 'San', 'Santos', 'Carlos', 'De', 'Santos', '2020-07-28', 'Baggao, Cagayan', 'birth_cert_2.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(6, 'REG-2025-00003', '2025-07-25', 'Twin', NULL, '3rd', NULL, 'Jose', 'Reyes', 'Reyes', '2025-11-28', 'Barangay Poblacion', NULL, 'Marie', 'San', 'Reyes', 'Fernando', 'De', 'Reyes', '2019-09-02', 'Baggao, Cagayan', 'birth_cert_3.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(7, 'REG-2025-00004', '2024-12-28', 'Single', NULL, '1st', NULL, 'Ana', 'Garcia', 'Garcia', '2025-12-19', 'Barangay Santa Cruz', NULL, 'Diana', 'San', 'Garcia', 'Ricardo', 'De', 'Garcia', '2020-11-16', 'Baggao, Cagayan', 'birth_cert_4.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(8, 'REG-2025-00005', '2025-02-07', 'Single', NULL, '2nd', NULL, 'Pedro', 'Flores', 'Flores', '2025-12-25', 'Barangay San Miguel', NULL, 'Rita', 'San', 'Flores', 'Eduardo', 'De', 'Flores', '2019-01-23', 'Baggao, Cagayan', 'birth_cert_5.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(9, 'REG-2025-00006', '2025-12-19', 'Twin', NULL, '1st', NULL, 'Rosa', 'Torres', 'Torres', '2025-11-29', 'Barangay San Juan', NULL, 'Luz', 'San', 'Torres', 'Alberto', 'De', 'Torres', '2017-11-15', 'Baggao, Cagayan', 'birth_cert_6.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(10, 'REG-2025-00007', '2025-10-01', 'Single', NULL, '4th', NULL, 'Luis', 'Ramos', 'Ramos', '2025-12-13', 'Barangay San Pedro', NULL, 'Grace', 'San', 'Ramos', 'Rafael', 'De', 'Ramos', '2016-04-14', 'Baggao, Cagayan', 'birth_cert_7.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(11, 'REG-2025-00008', '2025-02-20', 'Single', NULL, '1st', NULL, 'Carmen', 'Mendoza', 'Mendoza', '2025-12-14', 'Barangay Santa Maria', NULL, 'Olivia', 'San', 'Mendoza', 'Gabriel', 'De', 'Mendoza', '2021-12-05', 'Baggao, Cagayan', 'birth_cert_8.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(12, 'REG-2025-00009', '2025-04-25', 'Twin', NULL, '3rd', NULL, 'Miguel', 'Rivera', 'Rivera', '2025-11-27', 'Barangay San Antonio', NULL, 'Sofia', 'San', 'Rivera', 'Daniel', 'De', 'Rivera', '2020-09-21', 'Baggao, Cagayan', 'birth_cert_9.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active'),
(13, 'REG-2025-00010', '2025-09-27', 'Single', NULL, '2nd', NULL, 'Elena', 'Gomez', 'Gomez', '2025-11-29', 'Barangay San Vicente', NULL, 'Emma', 'San', 'Gomez', 'Manuel', 'De', 'Gomez', '2017-09-22', 'Baggao, Cagayan', 'birth_cert_10.pdf', NULL, '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `certificate_of_marriage`
--

CREATE TABLE `certificate_of_marriage` (
  `id` int(11) UNSIGNED NOT NULL,
  `registry_no` varchar(100) DEFAULT NULL,
  `date_of_registration` date NOT NULL,
  `husband_first_name` varchar(100) NOT NULL,
  `husband_middle_name` varchar(100) DEFAULT NULL,
  `husband_last_name` varchar(100) NOT NULL,
  `husband_date_of_birth` date NOT NULL,
  `husband_place_of_birth` varchar(255) NOT NULL,
  `husband_residence` text NOT NULL,
  `husband_father_name` varchar(255) DEFAULT NULL,
  `husband_father_residence` text DEFAULT NULL,
  `husband_mother_name` varchar(255) DEFAULT NULL,
  `husband_mother_residence` text DEFAULT NULL,
  `wife_first_name` varchar(100) NOT NULL,
  `wife_middle_name` varchar(100) DEFAULT NULL,
  `wife_last_name` varchar(100) NOT NULL,
  `wife_date_of_birth` date NOT NULL,
  `wife_place_of_birth` varchar(255) NOT NULL,
  `wife_residence` text NOT NULL,
  `wife_father_name` varchar(255) DEFAULT NULL,
  `wife_father_residence` text DEFAULT NULL,
  `wife_mother_name` varchar(255) DEFAULT NULL,
  `wife_mother_residence` text DEFAULT NULL,
  `date_of_marriage` date NOT NULL,
  `place_of_marriage` varchar(255) NOT NULL,
  `pdf_filename` varchar(255) DEFAULT NULL,
  `pdf_filepath` varchar(500) DEFAULT NULL,
  `status` enum('Active','Archived','Deleted') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `certificate_of_marriage`
--

INSERT INTO `certificate_of_marriage` (`id`, `registry_no`, `date_of_registration`, `husband_first_name`, `husband_middle_name`, `husband_last_name`, `husband_date_of_birth`, `husband_place_of_birth`, `husband_residence`, `husband_father_name`, `husband_father_residence`, `husband_mother_name`, `husband_mother_residence`, `wife_first_name`, `wife_middle_name`, `wife_last_name`, `wife_date_of_birth`, `wife_place_of_birth`, `wife_residence`, `wife_father_name`, `wife_father_residence`, `wife_mother_name`, `wife_mother_residence`, `date_of_marriage`, `place_of_marriage`, `pdf_filename`, `pdf_filepath`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'MAR-2025-00001', '2025-08-28', 'Roberto', 'San', 'Dela Cruz', '1994-07-08', 'Baggao, Cagayan', '123 Main St, Baggao, Cagayan', 'Juan Dela Cruz', '123 Main St, Baggao, Cagayan', 'Rosa Santos', '123 Main St, Baggao, Cagayan', 'Liza', 'De', 'Flores', '2004-03-25', 'Baggao, Cagayan', '123 Main St, Baggao, Cagayan', 'Pedro Flores', '123 Main St, Baggao, Cagayan', 'Maria Garcia', '123 Main St, Baggao, Cagayan', '2025-03-19', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_1.pdf', NULL, 'Deleted', '2025-12-27 05:46:04', '2026-01-03 11:49:56', NULL, 1),
(2, 'MAR-2025-00002', '2025-08-20', 'Carlos', 'San', 'Santos', '1994-02-11', 'Baggao, Cagayan', '456 Rizal Ave, Baggao, Cagayan', 'Juan Santos', '456 Rizal Ave, Baggao, Cagayan', 'Rosa Santos', '456 Rizal Ave, Baggao, Cagayan', 'Anna', 'De', 'Torres', '1989-11-15', 'Baggao, Cagayan', '456 Rizal Ave, Baggao, Cagayan', 'Pedro Torres', '456 Rizal Ave, Baggao, Cagayan', 'Maria Garcia', '456 Rizal Ave, Baggao, Cagayan', '2025-12-20', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_2.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(3, 'MAR-2025-00003', '2025-07-03', 'Fernando', 'San', 'Reyes', '1992-02-06', 'Baggao, Cagayan', '789 Luna St, Baggao, Cagayan', 'Juan Reyes', '789 Luna St, Baggao, Cagayan', 'Rosa Santos', '789 Luna St, Baggao, Cagayan', 'Marie', 'De', 'Ramos', '1995-12-31', 'Baggao, Cagayan', '789 Luna St, Baggao, Cagayan', 'Pedro Ramos', '789 Luna St, Baggao, Cagayan', 'Maria Garcia', '789 Luna St, Baggao, Cagayan', '2025-08-28', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_3.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(4, 'MAR-2025-00004', '2025-06-30', 'Ricardo', 'San', 'Garcia', '1991-01-24', 'Baggao, Cagayan', '321 Mabini St, Baggao, Cagayan', 'Juan Garcia', '321 Mabini St, Baggao, Cagayan', 'Rosa Santos', '321 Mabini St, Baggao, Cagayan', 'Diana', 'De', 'Mendoza', '1989-09-09', 'Baggao, Cagayan', '321 Mabini St, Baggao, Cagayan', 'Pedro Mendoza', '321 Mabini St, Baggao, Cagayan', 'Maria Garcia', '321 Mabini St, Baggao, Cagayan', '2025-07-15', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_4.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(5, 'MAR-2025-00005', '2025-11-03', 'Eduardo', 'San', 'Flores', '1998-08-06', 'Baggao, Cagayan', '654 Bonifacio Ave, Baggao, Cagayan', 'Juan Flores', '654 Bonifacio Ave, Baggao, Cagayan', 'Rosa Santos', '654 Bonifacio Ave, Baggao, Cagayan', 'Rita', 'De', 'Rivera', '1996-06-19', 'Baggao, Cagayan', '654 Bonifacio Ave, Baggao, Cagayan', 'Pedro Rivera', '654 Bonifacio Ave, Baggao, Cagayan', 'Maria Garcia', '654 Bonifacio Ave, Baggao, Cagayan', '2025-11-24', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_5.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(6, 'MAR-2025-00006', '2025-07-15', 'Alberto', 'San', 'Torres', '1999-01-10', 'Baggao, Cagayan', '987 Aguinaldo St, Baggao, Cagayan', 'Juan Torres', '987 Aguinaldo St, Baggao, Cagayan', 'Rosa Santos', '987 Aguinaldo St, Baggao, Cagayan', 'Luz', 'De', 'Gomez', '1993-09-22', 'Baggao, Cagayan', '987 Aguinaldo St, Baggao, Cagayan', 'Pedro Gomez', '987 Aguinaldo St, Baggao, Cagayan', 'Maria Garcia', '987 Aguinaldo St, Baggao, Cagayan', '2025-09-17', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_6.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(7, 'MAR-2025-00007', '2025-11-23', 'Rafael', 'San', 'Ramos', '2001-09-04', 'Baggao, Cagayan', '147 Del Pilar St, Baggao, Cagayan', 'Juan Ramos', '147 Del Pilar St, Baggao, Cagayan', 'Rosa Santos', '147 Del Pilar St, Baggao, Cagayan', 'Grace', 'De', 'Dela Cruz', '1997-02-25', 'Baggao, Cagayan', '147 Del Pilar St, Baggao, Cagayan', 'Pedro Dela Cruz', '147 Del Pilar St, Baggao, Cagayan', 'Maria Garcia', '147 Del Pilar St, Baggao, Cagayan', '2025-08-05', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_7.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(8, 'MAR-2025-00008', '2025-07-01', 'Gabriel', 'San', 'Mendoza', '1993-12-09', 'Baggao, Cagayan', '258 Gomez St, Baggao, Cagayan', 'Juan Mendoza', '258 Gomez St, Baggao, Cagayan', 'Rosa Santos', '258 Gomez St, Baggao, Cagayan', 'Olivia', 'De', 'Santos', '1991-02-17', 'Baggao, Cagayan', '258 Gomez St, Baggao, Cagayan', 'Pedro Santos', '258 Gomez St, Baggao, Cagayan', 'Maria Garcia', '258 Gomez St, Baggao, Cagayan', '2025-01-10', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_8.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(9, 'MAR-2025-00009', '2025-10-04', 'Daniel', 'San', 'Rivera', '1998-08-05', 'Baggao, Cagayan', '369 Quezon Ave, Baggao, Cagayan', 'Juan Rivera', '369 Quezon Ave, Baggao, Cagayan', 'Rosa Santos', '369 Quezon Ave, Baggao, Cagayan', 'Sofia', 'De', 'Reyes', '1986-09-17', 'Baggao, Cagayan', '369 Quezon Ave, Baggao, Cagayan', 'Pedro Reyes', '369 Quezon Ave, Baggao, Cagayan', 'Maria Garcia', '369 Quezon Ave, Baggao, Cagayan', '2025-06-13', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_9.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL),
(10, 'MAR-2025-00010', '2025-12-26', 'Manuel', 'San', 'Gomez', '1995-12-10', 'Baggao, Cagayan', '741 Roxas Blvd, Baggao, Cagayan', 'Juan Gomez', '741 Roxas Blvd, Baggao, Cagayan', 'Rosa Santos', '741 Roxas Blvd, Baggao, Cagayan', 'Emma', 'De', 'Garcia', '2001-03-16', 'Baggao, Cagayan', '741 Roxas Blvd, Baggao, Cagayan', 'Pedro Garcia', '741 Roxas Blvd, Baggao, Cagayan', 'Maria Garcia', '741 Roxas Blvd, Baggao, Cagayan', '2025-06-28', 'Baggao Church, Baggao, Cagayan', 'marriage_cert_10.pdf', NULL, 'Active', '2025-12-27 05:46:04', '2025-12-27 05:46:04', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `certificate_versions`
--

CREATE TABLE `certificate_versions` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `certificate_id` int(10) UNSIGNED NOT NULL,
  `version_number` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `is_current` tinyint(1) DEFAULT 1,
  `data_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Complete certificate data at this version' CHECK (json_valid(`data_snapshot`)),
  `change_type` enum('created','updated','corrected','annotated','amended') NOT NULL,
  `change_summary` text DEFAULT NULL COMMENT 'Human-readable summary of changes',
  `fields_changed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of field names that changed' CHECK (json_valid(`fields_changed`)),
  `amendment_type` enum('clerical_error','legal_correction','court_order','legitimation','adoption','other') DEFAULT NULL,
  `supporting_document_path` varchar(500) DEFAULT NULL COMMENT 'Path to court order, affidavit, etc.',
  `amendment_notes` text DEFAULT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks all versions and amendments of certificates';

-- --------------------------------------------------------

--
-- Table structure for table `event_reminders`
--

CREATE TABLE `event_reminders` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `reminder_date` date NOT NULL,
  `reminder_time` time NOT NULL,
  `sent` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reminder notifications for calendar events';

-- --------------------------------------------------------

--
-- Table structure for table `note_tags`
--

CREATE TABLE `note_tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `tag_name` varchar(50) NOT NULL,
  `tag_color` varchar(7) DEFAULT NULL COMMENT 'Hex color code',
  `usage_count` int(10) UNSIGNED DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tags for organizing notes';

--
-- Dumping data for table `note_tags`
--

INSERT INTO `note_tags` (`id`, `tag_name`, `tag_color`, `usage_count`, `created_at`) VALUES
(1, 'flood-impact', '#fbbf24', 1, '2026-01-04 14:17:05'),
(2, 'psa-guidelines', '#3b82f6', 1, '2026-01-04 14:17:05'),
(3, 'milestone', '#22c55e', 1, '2026-01-04 14:17:05'),
(4, 'training', '#8b5cf6', 1, '2026-01-04 14:17:05'),
(5, 'emergency', '#ef4444', 0, '2026-01-04 14:17:05');

-- --------------------------------------------------------

--
-- Table structure for table `note_tag_relations`
--

CREATE TABLE `note_tag_relations` (
  `note_id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-many relationship between notes and tags';

-- --------------------------------------------------------

--
-- Table structure for table `ocr_cache`
--

CREATE TABLE `ocr_cache` (
  `id` int(11) UNSIGNED NOT NULL,
  `file_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of PDF file',
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `ocr_text` longtext NOT NULL COMMENT 'Raw OCR extracted text',
  `structured_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Parsed field data' CHECK (json_valid(`structured_data`)),
  `processing_time` decimal(6,2) DEFAULT NULL COMMENT 'Processing time in seconds',
  `tesseract_version` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_accessed` timestamp NULL DEFAULT NULL,
  `access_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Cache for OCR processing results';

-- --------------------------------------------------------

--
-- Table structure for table `ocr_processing_queue`
--

CREATE TABLE `ocr_processing_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `pdf_attachment_id` int(10) UNSIGNED NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('queued','processing','completed','failed','cancelled') DEFAULT 'queued',
  `attempts` tinyint(3) UNSIGNED DEFAULT 0,
  `max_attempts` tinyint(3) UNSIGNED DEFAULT 3,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `processing_time_seconds` int(10) UNSIGNED DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `ocr_engine` varchar(50) DEFAULT 'tesseract' COMMENT 'Engine to use',
  `language` varchar(10) DEFAULT 'eng',
  `dpi` smallint(5) UNSIGNED DEFAULT 300,
  `queued_at` datetime DEFAULT current_timestamp(),
  `queued_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue for processing PDFs through OCR engines';

-- --------------------------------------------------------

--
-- Table structure for table `pdf_attachments`
--

CREATE TABLE `pdf_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL COMMENT 'Type of certificate',
  `certificate_id` int(10) UNSIGNED NOT NULL COMMENT 'ID in respective certificate table',
  `file_name` varchar(255) NOT NULL COMMENT 'Original filename',
  `file_path` varchar(500) NOT NULL COMMENT 'Storage path',
  `file_size` int(10) UNSIGNED NOT NULL COMMENT 'File size in bytes',
  `file_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash for integrity',
  `mime_type` varchar(100) DEFAULT 'application/pdf',
  `version` tinyint(3) UNSIGNED DEFAULT 1 COMMENT 'Version number for amendments',
  `is_current_version` tinyint(1) DEFAULT 1 COMMENT 'Is this the active version?',
  `replaced_by_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID of newer version if replaced',
  `version_notes` text DEFAULT NULL COMMENT 'Reason for new version',
  `ocr_text` longtext DEFAULT NULL COMMENT 'Extracted text from PDF',
  `ocr_confidence_score` decimal(5,2) DEFAULT NULL COMMENT 'OCR confidence 0-100',
  `ocr_processed_at` datetime DEFAULT NULL,
  `ocr_engine` varchar(50) DEFAULT NULL COMMENT 'tesseract, google-vision, etc.',
  `ocr_language` varchar(10) DEFAULT 'eng' COMMENT 'Language code',
  `ocr_data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Structured OCR data with field mappings' CHECK (json_valid(`ocr_data_json`)),
  `page_count` tinyint(3) UNSIGNED DEFAULT 1,
  `is_multipage` tinyint(1) DEFAULT 0,
  `processing_status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `processing_error` text DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks PDF versions and OCR data separately from main tables';

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `module`, `created_at`) VALUES
(1, 'users_view', 'View users list', 'users', '2026-01-01 02:58:31'),
(2, 'users_create', 'Create new users', 'users', '2026-01-01 02:58:31'),
(3, 'users_edit', 'Edit existing users', 'users', '2026-01-01 02:58:31'),
(4, 'users_delete', 'Delete users', 'users', '2026-01-01 02:58:31'),
(5, 'birth_view', 'View birth records', 'birth', '2026-01-01 02:58:31'),
(6, 'birth_create', 'Create birth records', 'birth', '2026-01-01 02:58:31'),
(7, 'birth_edit', 'Edit birth records', 'birth', '2026-01-01 02:58:31'),
(8, 'birth_delete', 'Delete birth records', 'birth', '2026-01-01 02:58:31'),
(9, 'marriage_view', 'View marriage records', 'marriage', '2026-01-01 02:58:31'),
(10, 'marriage_create', 'Create marriage records', 'marriage', '2026-01-01 02:58:31'),
(11, 'marriage_edit', 'Edit marriage records', 'marriage', '2026-01-01 02:58:31'),
(12, 'marriage_delete', 'Delete marriage records', 'marriage', '2026-01-01 02:58:31'),
(13, 'death_view', 'View death records', 'death', '2026-01-01 02:58:31'),
(14, 'death_create', 'Create death records', 'death', '2026-01-01 02:58:31'),
(15, 'death_edit', 'Edit death records', 'death', '2026-01-01 02:58:31'),
(16, 'death_delete', 'Delete death records', 'death', '2026-01-01 02:58:31'),
(17, 'reports_view', 'View reports', 'reports', '2026-01-01 02:58:31'),
(18, 'reports_export', 'Export reports', 'reports', '2026-01-01 02:58:31'),
(19, 'settings_view', 'View settings', 'settings', '2026-01-01 02:58:31'),
(20, 'settings_edit', 'Edit settings', 'settings', '2026-01-01 02:58:31');

-- --------------------------------------------------------

--
-- Table structure for table `qa_samples`
--

CREATE TABLE `qa_samples` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `certificate_id` int(10) UNSIGNED NOT NULL,
  `sample_date` date NOT NULL,
  `sample_batch` varchar(100) DEFAULT NULL COMMENT 'Batch identifier',
  `sampling_method` enum('random','targeted','problematic','high_risk') DEFAULT 'random',
  `reviewer_id` int(10) UNSIGNED DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `review_status` enum('pending','in_progress','passed','failed') DEFAULT 'pending',
  `errors_found` tinyint(3) UNSIGNED DEFAULT 0,
  `error_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of errors found' CHECK (json_valid(`error_details`)),
  `overall_rating` enum('excellent','good','fair','poor') DEFAULT NULL,
  `reviewer_notes` text DEFAULT NULL,
  `original_encoder_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quality assurance sampling and review tracking';

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) UNSIGNED NOT NULL,
  `role` enum('Admin','Encoder','Viewer') NOT NULL,
  `permission_id` int(11) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `permission_id`, `created_at`) VALUES
(1, 'Admin', 6, '2026-01-01 02:58:31'),
(2, 'Admin', 8, '2026-01-01 02:58:31'),
(3, 'Admin', 7, '2026-01-01 02:58:31'),
(4, 'Admin', 5, '2026-01-01 02:58:31'),
(5, 'Admin', 14, '2026-01-01 02:58:31'),
(6, 'Admin', 16, '2026-01-01 02:58:31'),
(7, 'Admin', 15, '2026-01-01 02:58:31'),
(8, 'Admin', 13, '2026-01-01 02:58:31'),
(9, 'Admin', 10, '2026-01-01 02:58:31'),
(10, 'Admin', 12, '2026-01-01 02:58:31'),
(11, 'Admin', 11, '2026-01-01 02:58:31'),
(12, 'Admin', 9, '2026-01-01 02:58:31'),
(13, 'Admin', 18, '2026-01-01 02:58:31'),
(14, 'Admin', 17, '2026-01-01 02:58:31'),
(15, 'Admin', 20, '2026-01-01 02:58:31'),
(16, 'Admin', 19, '2026-01-01 02:58:31'),
(17, 'Admin', 2, '2026-01-01 02:58:31'),
(18, 'Admin', 4, '2026-01-01 02:58:31'),
(19, 'Admin', 3, '2026-01-01 02:58:31'),
(20, 'Admin', 1, '2026-01-01 02:58:31'),
(32, 'Encoder', 6, '2026-01-01 02:58:31'),
(33, 'Encoder', 7, '2026-01-01 02:58:31'),
(34, 'Encoder', 5, '2026-01-01 02:58:31'),
(35, 'Encoder', 14, '2026-01-01 02:58:31'),
(36, 'Encoder', 15, '2026-01-01 02:58:31'),
(37, 'Encoder', 13, '2026-01-01 02:58:31'),
(38, 'Encoder', 10, '2026-01-01 02:58:31'),
(39, 'Encoder', 11, '2026-01-01 02:58:31'),
(40, 'Encoder', 9, '2026-01-01 02:58:31'),
(41, 'Encoder', 18, '2026-01-01 02:58:31'),
(42, 'Encoder', 17, '2026-01-01 02:58:31'),
(43, 'Encoder', 19, '2026-01-01 02:58:31'),
(44, 'Encoder', 3, '2026-01-01 02:58:31'),
(45, 'Encoder', 1, '2026-01-01 02:58:31'),
(47, 'Viewer', 5, '2026-01-01 02:58:31'),
(48, 'Viewer', 13, '2026-01-01 02:58:31'),
(49, 'Viewer', 9, '2026-01-01 02:58:31'),
(50, 'Viewer', 17, '2026-01-01 02:58:31'),
(51, 'Viewer', 19, '2026-01-01 02:58:31'),
(52, 'Viewer', 1, '2026-01-01 02:58:31');

-- --------------------------------------------------------

--
-- Table structure for table `system_notes`
--

CREATE TABLE `system_notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `note_type` enum('operational','administrative','technical','audit','compliance','other') NOT NULL,
  `certificate_type` enum('birth','marriage','death','license','all','none') DEFAULT 'none',
  `registry_number` varchar(100) DEFAULT NULL COMMENT 'Link to specific record',
  `barangay` varchar(100) DEFAULT NULL,
  `event_date` date DEFAULT NULL COMMENT 'Date this note refers to',
  `linked_certificate_id` int(10) UNSIGNED DEFAULT NULL,
  `linked_event_id` int(10) UNSIGNED DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `is_pinned` tinyint(1) DEFAULT 0 COMMENT 'Pin to top of notes list',
  `visibility` enum('private','team','public') DEFAULT 'team' COMMENT 'Who can see this note',
  `status` enum('draft','active','archived') DEFAULT 'active',
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'Prevent editing (audit protection)',
  `locked_by` int(10) UNSIGNED DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `has_attachment` tinyint(1) DEFAULT 0,
  `attachment_path` varchar(500) DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contextual notes for institutional memory and operational tracking';

--
-- Dumping data for table `system_notes`
--

INSERT INTO `system_notes` (`id`, `title`, `content`, `note_type`, `certificate_type`, `registry_number`, `barangay`, `event_date`, `linked_certificate_id`, `linked_event_id`, `priority`, `is_pinned`, `visibility`, `status`, `is_locked`, `locked_by`, `locked_at`, `has_attachment`, `attachment_path`, `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_at`) VALUES
(1, 'Delayed Registrations Explanation', 'Barangay San Isidro submitted delayed birth records (Dec 12-18) due to severe flooding. Registry operations were suspended during this period. All documents verified and compliant.', 'operational', 'birth', NULL, 'San Isidro', '2025-12-18', NULL, NULL, 'high', 1, 'team', 'active', 0, NULL, NULL, 0, NULL, 1, '2026-01-04 14:17:05', NULL, NULL, NULL),
(2, 'New PSA Guidelines', 'PSA issued new guidelines for marriage certificate annotation procedures. All staff trained on Jan 10, 2026.', 'compliance', 'marriage', NULL, NULL, '2026-01-10', NULL, NULL, 'medium', 1, 'team', 'active', 0, NULL, NULL, 0, NULL, 1, '2026-01-04 14:17:05', NULL, NULL, NULL),
(3, 'Digitization Milestone', 'Completed digitization of all 2020 marriage certificates (total: 543 records). Scans uploaded and verified.', 'administrative', 'marriage', NULL, NULL, '2026-01-04', NULL, NULL, 'medium', 0, 'team', 'active', 0, NULL, NULL, 0, NULL, 1, '2026-01-04 14:17:05', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT NULL COMMENT 'Group related settings',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0 COMMENT 'Can non-admins see this?',
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide configuration settings';

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`, `updated_by`, `updated_at`) VALUES
(1, 'ocr_enabled', 'true', 'boolean', 'OCR', 'Enable OCR processing for uploaded PDFs', 0, NULL, '2025-12-27 14:36:54'),
(2, 'ocr_default_engine', 'tesseract', 'string', 'OCR', 'Default OCR engine (tesseract, google-vision, aws-textract)', 0, NULL, '2025-12-27 14:36:54'),
(3, 'ocr_auto_process', 'true', 'boolean', 'OCR', 'Automatically process OCR on PDF upload', 0, NULL, '2025-12-27 14:36:54'),
(4, 'ocr_confidence_threshold', '75.00', 'number', 'OCR', 'Minimum confidence score to auto-fill fields (0-100)', 0, NULL, '2025-12-27 14:36:54'),
(5, 'workflow_require_verification', 'true', 'boolean', 'Workflow', 'Require verification before approval', 0, NULL, '2025-12-27 14:36:54'),
(6, 'workflow_auto_approve_high_quality', 'false', 'boolean', 'Workflow', 'Auto-approve records with quality score > 95', 0, NULL, '2025-12-27 14:36:54'),
(7, 'qa_sample_percentage', '10.00', 'number', 'QA', 'Percentage of records to sample for QA (0-100)', 0, NULL, '2025-12-27 14:36:54'),
(8, 'qa_enabled', 'true', 'boolean', 'QA', 'Enable quality assurance sampling', 0, NULL, '2025-12-27 14:36:54'),
(9, 'batch_upload_enabled', 'true', 'boolean', 'Batch', 'Enable batch upload feature', 0, NULL, '2025-12-27 14:36:54'),
(10, 'batch_max_files', '100', 'number', 'Batch', 'Maximum files per batch upload', 0, NULL, '2025-12-27 14:36:54'),
(11, 'versioning_enabled', 'true', 'boolean', 'Versioning', 'Track all versions of certificates', 0, NULL, '2025-12-27 14:36:54'),
(12, 'max_file_size_mb', '10', 'number', 'Upload', 'Maximum PDF file size in MB', 1, NULL, '2025-12-27 14:36:54'),
(13, 'allowed_file_types', 'pdf', 'string', 'Upload', 'Allowed file extensions (comma-separated)', 1, NULL, '2025-12-27 14:36:54');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('Admin','Encoder','Viewer') DEFAULT 'Encoder',
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$k6LNFsmcdozqcHcBIkaPheXdS9B4C1UDSiKuWpZuAgL7zQJkL5XDG', 'System Administrator', 'admin@iscan.local', 'Admin', 'Active', '2025-12-19 01:40:11', '2026-01-03 08:18:18', '2026-01-03 08:18:18');

-- --------------------------------------------------------

--
-- Table structure for table `user_performance_metrics`
--

CREATE TABLE `user_performance_metrics` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `metric_date` date NOT NULL,
  `records_created` smallint(5) UNSIGNED DEFAULT 0,
  `records_updated` smallint(5) UNSIGNED DEFAULT 0,
  `records_verified` smallint(5) UNSIGNED DEFAULT 0,
  `records_approved` smallint(5) UNSIGNED DEFAULT 0,
  `qa_samples_reviewed` smallint(5) UNSIGNED DEFAULT 0,
  `qa_samples_passed` smallint(5) UNSIGNED DEFAULT 0,
  `qa_samples_failed` smallint(5) UNSIGNED DEFAULT 0,
  `error_rate_percentage` decimal(5,2) DEFAULT 0.00,
  `average_quality_score` decimal(5,2) DEFAULT NULL,
  `total_time_minutes` int(10) UNSIGNED DEFAULT 0,
  `average_time_per_record` decimal(8,2) DEFAULT NULL COMMENT 'Minutes per record'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily performance metrics per user';

-- --------------------------------------------------------

--
-- Table structure for table `validation_discrepancies`
--

CREATE TABLE `validation_discrepancies` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `certificate_id` int(10) UNSIGNED NOT NULL,
  `pdf_attachment_id` int(10) UNSIGNED DEFAULT NULL,
  `field_name` varchar(100) NOT NULL COMMENT 'Field with discrepancy',
  `form_value` text DEFAULT NULL COMMENT 'Value from manual entry',
  `pdf_value` text DEFAULT NULL COMMENT 'Value extracted from PDF',
  `discrepancy_type` enum('missing','mismatch','format_error','unclear','confidence_low') NOT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL COMMENT 'OCR confidence for this field',
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('open','resolved','ignored','escalated') DEFAULT 'open',
  `resolution_value` text DEFAULT NULL COMMENT 'Final corrected value',
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `detected_at` datetime DEFAULT current_timestamp(),
  `detected_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User or system (NULL = automated)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks discrepancies between manual entry and PDF/OCR data';

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_active_certificates`
-- (See below for the actual view)
--
CREATE TABLE `vw_active_certificates` (
`id` int(11) unsigned
,`registry_no` varchar(100)
,`date_of_registration` date
,`type_of_birth` enum('Single','Twin','Triplets','Quadruplets','Other')
,`birth_order` enum('1st','2nd','3rd','4th','5th','6th','7th','Other')
,`mother_full_name` varchar(303)
,`father_full_name` varchar(303)
,`date_of_marriage` date
,`place_of_marriage` varchar(255)
,`pdf_filename` varchar(255)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_certificate_statistics`
-- (See below for the actual view)
--
CREATE TABLE `vw_certificate_statistics` (
`total_records` bigint(21)
,`active_records` decimal(22,0)
,`archived_records` decimal(22,0)
,`single_births` decimal(22,0)
,`twin_births` decimal(22,0)
,`triplet_births` decimal(22,0)
,`today_registrations` decimal(22,0)
,`this_month_registrations` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pinned_notes`
-- (See below for the actual view)
--
CREATE TABLE `vw_pinned_notes` (
`id` int(10) unsigned
,`title` varchar(200)
,`content` text
,`note_type` enum('operational','administrative','technical','audit','compliance','other')
,`certificate_type` enum('birth','marriage','death','license','all','none')
,`registry_number` varchar(100)
,`barangay` varchar(100)
,`event_date` date
,`linked_certificate_id` int(10) unsigned
,`linked_event_id` int(10) unsigned
,`priority` enum('low','medium','high')
,`is_pinned` tinyint(1)
,`visibility` enum('private','team','public')
,`status` enum('draft','active','archived')
,`is_locked` tinyint(1)
,`locked_by` int(10) unsigned
,`locked_at` datetime
,`has_attachment` tinyint(1)
,`attachment_path` varchar(500)
,`created_by` int(10) unsigned
,`created_at` datetime
,`updated_by` int(10) unsigned
,`updated_at` datetime
,`deleted_at` datetime
,`created_by_name` varchar(150)
,`created_by_role` enum('Admin','Encoder','Viewer')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_today_events`
-- (See below for the actual view)
--
CREATE TABLE `vw_today_events` (
`id` int(10) unsigned
,`title` varchar(200)
,`description` text
,`event_type` enum('registration','deadline','maintenance','digitization','meeting','other')
,`certificate_type` enum('birth','marriage','death','license','all')
,`event_date` date
,`event_time` time
,`end_date` date
,`all_day` tinyint(1)
,`barangay` varchar(100)
,`registry_number` varchar(100)
,`priority` enum('low','medium','high','urgent')
,`status` enum('pending','in_progress','completed','cancelled')
,`reminder_sent` tinyint(1)
,`reminder_days_before` tinyint(4)
,`color_code` varchar(7)
,`icon` varchar(50)
,`created_by` int(10) unsigned
,`created_at` datetime
,`updated_by` int(10) unsigned
,`updated_at` datetime
,`deleted_at` datetime
,`created_by_name` varchar(150)
,`created_by_role` enum('Admin','Encoder','Viewer')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_upcoming_events`
-- (See below for the actual view)
--
CREATE TABLE `vw_upcoming_events` (
`id` int(10) unsigned
,`title` varchar(200)
,`description` text
,`event_type` enum('registration','deadline','maintenance','digitization','meeting','other')
,`certificate_type` enum('birth','marriage','death','license','all')
,`event_date` date
,`event_time` time
,`end_date` date
,`all_day` tinyint(1)
,`barangay` varchar(100)
,`registry_number` varchar(100)
,`priority` enum('low','medium','high','urgent')
,`status` enum('pending','in_progress','completed','cancelled')
,`reminder_sent` tinyint(1)
,`reminder_days_before` tinyint(4)
,`color_code` varchar(7)
,`icon` varchar(50)
,`created_by` int(10) unsigned
,`created_at` datetime
,`updated_by` int(10) unsigned
,`updated_at` datetime
,`deleted_at` datetime
,`created_by_name` varchar(150)
,`created_by_role` enum('Admin','Encoder','Viewer')
,`days_until_event` int(7)
);

-- --------------------------------------------------------

--
-- Table structure for table `workflow_states`
--

CREATE TABLE `workflow_states` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `certificate_id` int(10) UNSIGNED NOT NULL,
  `current_state` enum('draft','pending_review','verified','approved','rejected','archived') DEFAULT 'draft',
  `data_quality_score` decimal(5,2) DEFAULT NULL COMMENT 'Overall confidence score 0-100',
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `rejected_by` int(10) UNSIGNED DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manages workflow states separately from certificate tables';

-- --------------------------------------------------------

--
-- Table structure for table `workflow_transitions`
--

CREATE TABLE `workflow_transitions` (
  `id` int(10) UNSIGNED NOT NULL,
  `certificate_type` enum('birth','marriage','death') NOT NULL,
  `certificate_id` int(10) UNSIGNED NOT NULL,
  `from_state` enum('draft','pending_review','verified','approved','rejected','archived') DEFAULT NULL,
  `to_state` enum('draft','pending_review','verified','approved','rejected','archived') NOT NULL,
  `transition_type` enum('submit','verify','approve','reject','archive','reopen') NOT NULL,
  `notes` text DEFAULT NULL COMMENT 'Reason for transition',
  `automated` tinyint(1) DEFAULT 0 COMMENT 'Was this an automated transition?',
  `performed_by` int(10) UNSIGNED NOT NULL,
  `performed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail of all workflow state changes';

-- --------------------------------------------------------

--
-- Structure for view `vw_active_certificates`
--
DROP TABLE IF EXISTS `vw_active_certificates`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_active_certificates`  AS SELECT `certificate_of_live_birth`.`id` AS `id`, `certificate_of_live_birth`.`registry_no` AS `registry_no`, `certificate_of_live_birth`.`date_of_registration` AS `date_of_registration`, `certificate_of_live_birth`.`type_of_birth` AS `type_of_birth`, `certificate_of_live_birth`.`birth_order` AS `birth_order`, concat(`certificate_of_live_birth`.`mother_last_name`,', ',`certificate_of_live_birth`.`mother_first_name`,' ',ifnull(`certificate_of_live_birth`.`mother_middle_name`,'')) AS `mother_full_name`, concat(`certificate_of_live_birth`.`father_last_name`,', ',`certificate_of_live_birth`.`father_first_name`,' ',ifnull(`certificate_of_live_birth`.`father_middle_name`,'')) AS `father_full_name`, `certificate_of_live_birth`.`date_of_marriage` AS `date_of_marriage`, `certificate_of_live_birth`.`place_of_marriage` AS `place_of_marriage`, `certificate_of_live_birth`.`pdf_filename` AS `pdf_filename`, `certificate_of_live_birth`.`created_at` AS `created_at`, `certificate_of_live_birth`.`updated_at` AS `updated_at` FROM `certificate_of_live_birth` WHERE `certificate_of_live_birth`.`status` = 'Active' ORDER BY `certificate_of_live_birth`.`date_of_registration` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_certificate_statistics`
--
DROP TABLE IF EXISTS `vw_certificate_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_certificate_statistics`  AS SELECT count(0) AS `total_records`, sum(case when `certificate_of_live_birth`.`status` = 'Active' then 1 else 0 end) AS `active_records`, sum(case when `certificate_of_live_birth`.`status` = 'Archived' then 1 else 0 end) AS `archived_records`, sum(case when `certificate_of_live_birth`.`type_of_birth` = 'Single' then 1 else 0 end) AS `single_births`, sum(case when `certificate_of_live_birth`.`type_of_birth` = 'Twin' then 1 else 0 end) AS `twin_births`, sum(case when `certificate_of_live_birth`.`type_of_birth` = 'Triplets' then 1 else 0 end) AS `triplet_births`, sum(case when cast(`certificate_of_live_birth`.`date_of_registration` as date) = curdate() then 1 else 0 end) AS `today_registrations`, sum(case when month(`certificate_of_live_birth`.`date_of_registration`) = month(curdate()) and year(`certificate_of_live_birth`.`date_of_registration`) = year(curdate()) then 1 else 0 end) AS `this_month_registrations` FROM `certificate_of_live_birth` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_pinned_notes`
--
DROP TABLE IF EXISTS `vw_pinned_notes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_pinned_notes`  AS SELECT `n`.`id` AS `id`, `n`.`title` AS `title`, `n`.`content` AS `content`, `n`.`note_type` AS `note_type`, `n`.`certificate_type` AS `certificate_type`, `n`.`registry_number` AS `registry_number`, `n`.`barangay` AS `barangay`, `n`.`event_date` AS `event_date`, `n`.`linked_certificate_id` AS `linked_certificate_id`, `n`.`linked_event_id` AS `linked_event_id`, `n`.`priority` AS `priority`, `n`.`is_pinned` AS `is_pinned`, `n`.`visibility` AS `visibility`, `n`.`status` AS `status`, `n`.`is_locked` AS `is_locked`, `n`.`locked_by` AS `locked_by`, `n`.`locked_at` AS `locked_at`, `n`.`has_attachment` AS `has_attachment`, `n`.`attachment_path` AS `attachment_path`, `n`.`created_by` AS `created_by`, `n`.`created_at` AS `created_at`, `n`.`updated_by` AS `updated_by`, `n`.`updated_at` AS `updated_at`, `n`.`deleted_at` AS `deleted_at`, `u`.`full_name` AS `created_by_name`, `u`.`role` AS `created_by_role` FROM (`system_notes` `n` left join `users` `u` on(`n`.`created_by` = `u`.`id`)) WHERE `n`.`is_pinned` = 1 AND `n`.`deleted_at` is null AND `n`.`status` = 'active' ORDER BY `n`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_today_events`
--
DROP TABLE IF EXISTS `vw_today_events`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_today_events`  AS SELECT `e`.`id` AS `id`, `e`.`title` AS `title`, `e`.`description` AS `description`, `e`.`event_type` AS `event_type`, `e`.`certificate_type` AS `certificate_type`, `e`.`event_date` AS `event_date`, `e`.`event_time` AS `event_time`, `e`.`end_date` AS `end_date`, `e`.`all_day` AS `all_day`, `e`.`barangay` AS `barangay`, `e`.`registry_number` AS `registry_number`, `e`.`priority` AS `priority`, `e`.`status` AS `status`, `e`.`reminder_sent` AS `reminder_sent`, `e`.`reminder_days_before` AS `reminder_days_before`, `e`.`color_code` AS `color_code`, `e`.`icon` AS `icon`, `e`.`created_by` AS `created_by`, `e`.`created_at` AS `created_at`, `e`.`updated_by` AS `updated_by`, `e`.`updated_at` AS `updated_at`, `e`.`deleted_at` AS `deleted_at`, `u`.`full_name` AS `created_by_name`, `u`.`role` AS `created_by_role` FROM (`calendar_events` `e` left join `users` `u` on(`e`.`created_by` = `u`.`id`)) WHERE cast(`e`.`event_date` as date) = curdate() AND `e`.`deleted_at` is null AND `e`.`status` <> 'cancelled' ORDER BY `e`.`event_time` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_upcoming_events`
--
DROP TABLE IF EXISTS `vw_upcoming_events`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_upcoming_events`  AS SELECT `e`.`id` AS `id`, `e`.`title` AS `title`, `e`.`description` AS `description`, `e`.`event_type` AS `event_type`, `e`.`certificate_type` AS `certificate_type`, `e`.`event_date` AS `event_date`, `e`.`event_time` AS `event_time`, `e`.`end_date` AS `end_date`, `e`.`all_day` AS `all_day`, `e`.`barangay` AS `barangay`, `e`.`registry_number` AS `registry_number`, `e`.`priority` AS `priority`, `e`.`status` AS `status`, `e`.`reminder_sent` AS `reminder_sent`, `e`.`reminder_days_before` AS `reminder_days_before`, `e`.`color_code` AS `color_code`, `e`.`icon` AS `icon`, `e`.`created_by` AS `created_by`, `e`.`created_at` AS `created_at`, `e`.`updated_by` AS `updated_by`, `e`.`updated_at` AS `updated_at`, `e`.`deleted_at` AS `deleted_at`, `u`.`full_name` AS `created_by_name`, `u`.`role` AS `created_by_role`, to_days(`e`.`event_date`) - to_days(curdate()) AS `days_until_event` FROM (`calendar_events` `e` left join `users` `u` on(`e`.`created_by` = `u`.`id`)) WHERE `e`.`event_date` >= curdate() AND `e`.`event_date` <= curdate() + interval 30 day AND `e`.`deleted_at` is null AND `e`.`status` <> 'cancelled' ORDER BY `e`.`event_date` ASC, `e`.`event_time` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `application_for_marriage_license`
--
ALTER TABLE `application_for_marriage_license`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registry_no` (`registry_no`),
  ADD KEY `idx_date_of_application` (`date_of_application`),
  ADD KEY `idx_groom_name` (`groom_first_name`,`groom_last_name`),
  ADD KEY `idx_bride_name` (`bride_first_name`,`bride_last_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `batch_uploads`
--
ALTER TABLE `batch_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `batch_upload_items`
--
ALTER TABLE `batch_upload_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_processing_order` (`processing_order`),
  ADD KEY `pdf_attachment_id` (`pdf_attachment_id`);

--
-- Indexes for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_certificate_type` (`certificate_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_barangay` (`barangay`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `certificate_of_death`
--
ALTER TABLE `certificate_of_death`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registry_no` (`registry_no`),
  ADD KEY `idx_deceased_name` (`deceased_last_name`,`deceased_first_name`),
  ADD KEY `idx_father_name` (`father_last_name`,`father_first_name`),
  ADD KEY `idx_mother_name` (`mother_last_name`,`mother_first_name`),
  ADD KEY `idx_date_of_death` (`date_of_death`),
  ADD KEY `idx_date_of_birth` (`date_of_birth`),
  ADD KEY `idx_date_of_registration` (`date_of_registration`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `certificate_of_live_birth`
--
ALTER TABLE `certificate_of_live_birth`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registry_no` (`registry_no`),
  ADD KEY `idx_registry_no` (`registry_no`),
  ADD KEY `idx_mother_name` (`mother_last_name`,`mother_first_name`),
  ADD KEY `idx_father_name` (`father_last_name`,`father_first_name`),
  ADD KEY `idx_date_registration` (`date_of_registration`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_child_name` (`child_last_name`,`child_first_name`),
  ADD KEY `idx_child_date_of_birth` (`child_date_of_birth`);

--
-- Indexes for table `certificate_of_marriage`
--
ALTER TABLE `certificate_of_marriage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registry_no` (`registry_no`),
  ADD KEY `idx_husband_name` (`husband_last_name`,`husband_first_name`),
  ADD KEY `idx_wife_name` (`wife_last_name`,`wife_first_name`),
  ADD KEY `idx_date_of_marriage` (`date_of_marriage`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `certificate_versions`
--
ALTER TABLE `certificate_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_version` (`certificate_type`,`certificate_id`,`version_number`),
  ADD KEY `idx_certificate_lookup` (`certificate_type`,`certificate_id`),
  ADD KEY `idx_version_number` (`version_number`),
  ADD KEY `idx_is_current` (`is_current`),
  ADD KEY `idx_change_type` (`change_type`),
  ADD KEY `idx_changed_by` (`changed_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `event_reminders`
--
ALTER TABLE `event_reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_reminder_date` (`reminder_date`),
  ADD KEY `idx_sent` (`sent`);

--
-- Indexes for table `note_tags`
--
ALTER TABLE `note_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`),
  ADD KEY `idx_tag_name` (`tag_name`);

--
-- Indexes for table `note_tag_relations`
--
ALTER TABLE `note_tag_relations`
  ADD PRIMARY KEY (`note_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `ocr_cache`
--
ALTER TABLE `ocr_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_hash` (`file_hash`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `ocr_processing_queue`
--
ALTER TABLE `ocr_processing_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_pdf_attachment` (`pdf_attachment_id`),
  ADD KEY `idx_queued_at` (`queued_at`),
  ADD KEY `queued_by` (`queued_by`);

--
-- Indexes for table `pdf_attachments`
--
ALTER TABLE `pdf_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_certificate_lookup` (`certificate_type`,`certificate_id`),
  ADD KEY `idx_current_version` (`is_current_version`),
  ADD KEY `idx_file_hash` (`file_hash`),
  ADD KEY `idx_processing_status` (`processing_status`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);
ALTER TABLE `pdf_attachments` ADD FULLTEXT KEY `idx_ocr_text` (`ocr_text`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `qa_samples`
--
ALTER TABLE `qa_samples`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_certificate_lookup` (`certificate_type`,`certificate_id`),
  ADD KEY `idx_review_status` (`review_status`),
  ADD KEY `idx_reviewer` (`reviewer_id`),
  ADD KEY `idx_sample_date` (`sample_date`),
  ADD KEY `idx_original_encoder` (`original_encoder_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `system_notes`
--
ALTER TABLE `system_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_note_type` (`note_type`),
  ADD KEY `idx_certificate_type` (`certificate_type`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_pinned` (`is_pinned`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_registry_number` (`registry_number`),
  ADD KEY `idx_barangay` (`barangay`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `locked_by` (`locked_by`),
  ADD KEY `linked_event_id` (`linked_event_id`);
ALTER TABLE `system_notes` ADD FULLTEXT KEY `idx_content_search` (`title`,`content`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_is_public` (`is_public`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_performance_metrics`
--
ALTER TABLE `user_performance_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`metric_date`),
  ADD KEY `idx_metric_date` (`metric_date`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `validation_discrepancies`
--
ALTER TABLE `validation_discrepancies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_certificate_lookup` (`certificate_type`,`certificate_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_discrepancy_type` (`discrepancy_type`),
  ADD KEY `idx_detected_at` (`detected_at`),
  ADD KEY `pdf_attachment_id` (`pdf_attachment_id`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `workflow_states`
--
ALTER TABLE `workflow_states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_certificate` (`certificate_type`,`certificate_id`),
  ADD KEY `idx_current_state` (`current_state`),
  ADD KEY `idx_verified_by` (`verified_by`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `rejected_by` (`rejected_by`);

--
-- Indexes for table `workflow_transitions`
--
ALTER TABLE `workflow_transitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_certificate_lookup` (`certificate_type`,`certificate_id`),
  ADD KEY `idx_transition_type` (`transition_type`),
  ADD KEY `idx_performed_by` (`performed_by`),
  ADD KEY `idx_performed_at` (`performed_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `application_for_marriage_license`
--
ALTER TABLE `application_for_marriage_license`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_uploads`
--
ALTER TABLE `batch_uploads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_upload_items`
--
ALTER TABLE `batch_upload_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendar_events`
--
ALTER TABLE `calendar_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificate_of_death`
--
ALTER TABLE `certificate_of_death`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificate_of_live_birth`
--
ALTER TABLE `certificate_of_live_birth`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `certificate_of_marriage`
--
ALTER TABLE `certificate_of_marriage`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `certificate_versions`
--
ALTER TABLE `certificate_versions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_reminders`
--
ALTER TABLE `event_reminders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `note_tags`
--
ALTER TABLE `note_tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ocr_cache`
--
ALTER TABLE `ocr_cache`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ocr_processing_queue`
--
ALTER TABLE `ocr_processing_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pdf_attachments`
--
ALTER TABLE `pdf_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `qa_samples`
--
ALTER TABLE `qa_samples`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `system_notes`
--
ALTER TABLE `system_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_performance_metrics`
--
ALTER TABLE `user_performance_metrics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `validation_discrepancies`
--
ALTER TABLE `validation_discrepancies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_states`
--
ALTER TABLE `workflow_states`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workflow_transitions`
--
ALTER TABLE `workflow_transitions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batch_uploads`
--
ALTER TABLE `batch_uploads`
  ADD CONSTRAINT `batch_uploads_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `batch_upload_items`
--
ALTER TABLE `batch_upload_items`
  ADD CONSTRAINT `batch_upload_items_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batch_uploads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_upload_items_ibfk_2` FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `calendar_events`
--
ALTER TABLE `calendar_events`
  ADD CONSTRAINT `calendar_events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `calendar_events_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificate_versions`
--
ALTER TABLE `certificate_versions`
  ADD CONSTRAINT `certificate_versions_ibfk_1` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `certificate_versions_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_reminders`
--
ALTER TABLE `event_reminders`
  ADD CONSTRAINT `event_reminders_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `calendar_events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_reminders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `note_tag_relations`
--
ALTER TABLE `note_tag_relations`
  ADD CONSTRAINT `note_tag_relations_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `system_notes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_tag_relations_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `note_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ocr_processing_queue`
--
ALTER TABLE `ocr_processing_queue`
  ADD CONSTRAINT `ocr_processing_queue_ibfk_1` FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ocr_processing_queue_ibfk_2` FOREIGN KEY (`queued_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `pdf_attachments`
--
ALTER TABLE `pdf_attachments`
  ADD CONSTRAINT `pdf_attachments_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `qa_samples`
--
ALTER TABLE `qa_samples`
  ADD CONSTRAINT `qa_samples_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `qa_samples_ibfk_2` FOREIGN KEY (`original_encoder_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_notes`
--
ALTER TABLE `system_notes`
  ADD CONSTRAINT `system_notes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `system_notes_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `system_notes_ibfk_3` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `system_notes_ibfk_4` FOREIGN KEY (`linked_event_id`) REFERENCES `calendar_events` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_performance_metrics`
--
ALTER TABLE `user_performance_metrics`
  ADD CONSTRAINT `user_performance_metrics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `validation_discrepancies`
--
ALTER TABLE `validation_discrepancies`
  ADD CONSTRAINT `validation_discrepancies_ibfk_1` FOREIGN KEY (`pdf_attachment_id`) REFERENCES `pdf_attachments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `validation_discrepancies_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `workflow_states`
--
ALTER TABLE `workflow_states`
  ADD CONSTRAINT `workflow_states_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_states_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_states_ibfk_3` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `workflow_transitions`
--
ALTER TABLE `workflow_transitions`
  ADD CONSTRAINT `workflow_transitions_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
