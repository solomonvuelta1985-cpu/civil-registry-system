-- Create certificate_of_marriage table
CREATE TABLE IF NOT EXISTS `certificate_of_marriage` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Registry Information
    `registry_no` VARCHAR(100) DEFAULT NULL,
    `date_of_registration` DATE NOT NULL,

    -- Husband's Information
    `husband_first_name` VARCHAR(100) NOT NULL,
    `husband_middle_name` VARCHAR(100) DEFAULT NULL,
    `husband_last_name` VARCHAR(100) NOT NULL,
    `husband_date_of_birth` DATE NOT NULL,
    `husband_place_of_birth` VARCHAR(255) NOT NULL,
    `husband_residence` TEXT NOT NULL,
    `husband_father_name` VARCHAR(255) DEFAULT NULL,
    `husband_father_residence` TEXT DEFAULT NULL,
    `husband_mother_name` VARCHAR(255) DEFAULT NULL,
    `husband_mother_residence` TEXT DEFAULT NULL,

    -- Wife's Information
    `wife_first_name` VARCHAR(100) NOT NULL,
    `wife_middle_name` VARCHAR(100) DEFAULT NULL,
    `wife_last_name` VARCHAR(100) NOT NULL,
    `wife_date_of_birth` DATE NOT NULL,
    `wife_place_of_birth` VARCHAR(255) NOT NULL,
    `wife_residence` TEXT NOT NULL,
    `wife_father_name` VARCHAR(255) DEFAULT NULL,
    `wife_father_residence` TEXT DEFAULT NULL,
    `wife_mother_name` VARCHAR(255) DEFAULT NULL,
    `wife_mother_residence` TEXT DEFAULT NULL,

    -- Marriage Information
    `date_of_marriage` DATE NOT NULL,
    `place_of_marriage` VARCHAR(255) NOT NULL,

    -- PDF File
    `pdf_filename` VARCHAR(255) DEFAULT NULL,
    `pdf_filepath` VARCHAR(500) DEFAULT NULL,

    -- Metadata
    `status` ENUM('Active', 'Archived', 'Deleted') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL,
    `updated_by` INT(11) DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_registry_no` (`registry_no`),
    INDEX `idx_husband_name` (`husband_last_name`, `husband_first_name`),
    INDEX `idx_wife_name` (`wife_last_name`, `wife_first_name`),
    INDEX `idx_date_of_marriage` (`date_of_marriage`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
























-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 26, 2025 at 09:20 AM
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
(4, NULL, 'CREATE_CERTIFICATE', 'Created Certificate of Live Birth: Registry No. 1970-148', '2025-12-19 03:38:45');

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

INSERT INTO `certificate_of_live_birth` (`id`, `registry_no`, `date_of_registration`, `type_of_birth`, `type_of_birth_other`, `birth_order`, `birth_order_other`, `mother_first_name`, `mother_middle_name`, `mother_last_name`, `father_first_name`, `father_middle_name`, `father_last_name`, `date_of_marriage`, `place_of_marriage`, `pdf_filename`, `pdf_filepath`, `created_at`, `updated_at`, `created_by`, `updated_by`, `status`) VALUES
(1, '589898', '1999-11-17', 'Single', '', '1st', '', 'richmond', '', 'rosete', 'richmond', '', 'rosete', NULL, '', 'cert_6944b3b549d089.46614342_1766110133.pdf', 'C:\\xampp\\htdocs\\iscan\\includes/../uploads/cert_6944b3b549d089.46614342_1766110133.pdf', '2025-12-19 02:08:53', '2025-12-19 02:08:53', NULL, NULL, 'Active'),
(2, '2025-655', '2025-08-29', 'Single', '', '2nd', '', 'Winie', 'Javier', 'De Leon', 'Reymark', 'Pante', 'Abalos', '2005-04-26', 'Baggao, Cagayan', 'cert_6944c22b05f2f3.76038801_1766113835.pdf', 'C:\\xampp\\htdocs\\iscan\\includes/../uploads/cert_6944c22b05f2f3.76038801_1766113835.pdf', '2025-12-19 03:10:35', '2025-12-19 03:13:40', NULL, NULL, 'Active'),
(3, '1970-148', '1970-03-14', 'Single', '', '7th', '', 'Brigida', '', 'Galla', 'Crispulo', '', 'Tungpalan', NULL, '', 'cert_6944c8c550b138.15084646_1766115525.pdf', 'C:\\xampp\\htdocs\\iscan\\includes/../uploads/cert_6944c8c550b138.15084646_1766115525.pdf', '2025-12-19 03:38:45', '2025-12-19 03:38:45', NULL, NULL, 'Active');

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
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@iscan.local', 'Admin', 'Active', '2025-12-19 01:40:11', '2025-12-19 01:40:11', NULL);

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
  ADD KEY `idx_created_at` (`created_at`);

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `certificate_of_live_birth`
--
ALTER TABLE `certificate_of_live_birth`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `certificate_of_marriage`
--
ALTER TABLE `certificate_of_marriage`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
