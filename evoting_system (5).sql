-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2025 at 08:40 AM
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
-- Database: `evoting_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` text NOT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `timestamp`) VALUES
(1, 125, 'Deleted user: Elena Reyes (ID: 101)', '2025-09-23 17:44:43'),
(2, 125, 'Deleted user: Patricia David (ID: 111)', '2025-09-26 16:43:51'),
(3, 125, 'Deleted user: John Doe (ID: 181)', '2025-10-05 21:39:12'),
(4, 125, 'Deleted user: Jennifer Davis (ID: 185)', '2025-10-09 16:05:24'),
(5, 125, 'Deleted user: Jane Smith (ID: 186)', '2025-10-12 16:31:15');

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_tokens`
--

CREATE TABLE `admin_login_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login_tokens`
--

INSERT INTO `admin_login_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`) VALUES
(141, 67, 'a667c3e02dbc8a3c1a1681bd8d8f687f35e58ea9ec8130d54449e390254987e5', '2025-08-08 18:33:05', '2025-08-08 09:33:05'),
(146, 67, '73cf15f381805202852177fe537a630b0e432ade40747378a2c5d3493ea23db7', '2025-08-12 15:32:26', '2025-08-12 06:32:26'),
(147, 67, '6f59df130b5f0f16294e6d272af620d620e389633971c92c3d0a9a75624dda34', '2025-08-12 15:35:33', '2025-08-12 06:35:33'),
(148, 67, '223bb9bb6b13342f4ad4c3522528f11489fcafa9b8fbe12529cc7a0f8e4315da', '2025-08-12 15:36:18', '2025-08-12 06:36:18'),
(149, 67, '2fbddf03d984268defad8613be0a34fd878807ced7ffa22ee11d4a67e1b03a17', '2025-08-12 15:36:54', '2025-08-12 06:36:54'),
(150, 67, '6109d85d1d3f1ca7475689b173e988671b82e23bd8b155f313bc9a396b613f34', '2025-08-12 15:37:16', '2025-08-12 06:37:16'),
(151, 67, '04090c5d24658f0cdad71981285587cc25a4b32c462c13e2a9a80f1e0ebd3c2f', '2025-08-12 15:37:44', '2025-08-12 06:37:44'),
(152, 67, '953769a8bf511c74c9b0a3e18f6e1fc577d1623a05b34b5b31b601dbf251c354', '2025-08-12 15:38:21', '2025-08-12 06:38:21'),
(153, 67, '3f18aea6282599a874aea80c6fe75ab60490f1f02b874dd69d8987eac0e9dcb1', '2025-08-12 15:40:09', '2025-08-12 06:40:09'),
(154, 67, 'b58ce7a5bbb1759aaa3f0dd805e4b1bfe309708a69f651822ec25224ca0e9a64', '2025-08-12 15:46:23', '2025-08-12 06:46:23'),
(155, 67, '6fa256291660a779e5becdf77971cfbc3c1334a1c1668266ecd5579474c34a44', '2025-08-12 15:53:57', '2025-08-12 06:53:57'),
(156, 67, '8464c99aa99421aeece69d42ef8d688c3c6fe9ec18b53377af73410df5b1b531', '2025-08-12 16:08:27', '2025-08-12 07:08:27'),
(157, 67, '49aa7c92b486aad038a2ba369c9bf5606fa3b4974dd8b90d8f39244401d16c45', '2025-08-12 16:17:02', '2025-08-12 07:17:02'),
(160, 67, 'eddbea224a10ae8e71a9583934aa3d08fb5565db4ea51f27989d8a1c118f496b', '2025-08-14 17:43:40', '2025-08-14 08:43:40'),
(161, 67, '7db3cc341624688609e15e5bf308a3ede63745da3228d94748c2bfc8284e8929', '2025-08-14 19:20:29', '2025-08-14 10:20:29'),
(162, 67, 'ed25e4b3765987851e9c7571b4fe50c3806ba1c47e1b052da19d903ba0777e7f', '2025-08-14 19:36:20', '2025-08-14 10:36:20'),
(163, 67, '439ae283f646c17e40751301a42aa9823438114da118be5d06b0fbd654a2859c', '2025-08-14 19:45:15', '2025-08-14 10:45:15'),
(226, 67, '70a47a66669b7ef37fb0319dfe138cdc5c2b2d8e289434ea0f43c7a3378e7388', '2025-09-23 15:42:22', '2025-09-23 06:42:22'),
(227, 67, '0886874dedc1ea21453b342af94e1814715ddb2ad2a2d363adbd02f1a48cd103', '2025-09-23 15:42:44', '2025-09-23 06:42:44'),
(232, 125, '94310328c82d295a7734d185c13ed5ffa8b7bbbb82dee93f7ce9d6d40d787ffc', '2025-09-26 16:43:39', '2025-09-26 07:43:39'),
(233, 125, 'aa0725d02e48d4559e701ac4d142fe25ef634de6fec41409fa873f450ad0bfb8', '2025-09-26 17:40:33', '2025-09-26 08:40:33'),
(235, 67, '7617711d1dd672e3f40c7dbff608c9a8d4ddbe105c25e644ef4d604f227737e7', '2025-09-26 17:40:59', '2025-09-26 08:40:59'),
(240, 67, '9d9886bbd95e2f63351dee5d749c449ea2cf2ffec106476c6cf2fbbe4a0b176c', '2025-10-05 15:19:30', '2025-10-05 06:19:30'),
(253, 125, 'ba599f70609daa04af32d8434a40f7fa2ed036d7d1553ad575ee438921a6cc40', '2025-10-05 22:15:39', '2025-10-05 13:15:39'),
(254, 125, '5f38af34169b6ec0cab4392528f54e499b7dd24290963fd26e0feca2e71c88ad', '2025-10-05 22:15:47', '2025-10-05 13:15:47'),
(267, 125, 'cf4bfc45912be5feb651d81b239ee464dacccd60f1ebdfaefec99b03231e9f95', '2025-10-16 23:33:21', '2025-10-16 14:33:21'),
(268, 125, '053976e85c52a82533f1819bbfaafee70d2cd50c987b6f2695f03be99950b2d0', '2025-10-16 23:33:34', '2025-10-16 14:33:34'),
(269, 125, 'c7c2f0b91e4e80c81c07d19150b280cf7f90959ba054277aedcfc4fc01f7355b', '2025-10-16 23:33:54', '2025-10-16 14:33:54'),
(270, 125, '3a641039cac9f8cb48e327b30de4c528a2589022095a158ea514cc99515d8ab6', '2025-10-17 00:11:15', '2025-10-16 15:11:15'),
(271, 125, '519688f3b1b0b0d959db250583354c6f10e281519437b4939670a89199d0c3a4', '2025-10-17 00:22:17', '2025-10-16 15:22:17'),
(272, 125, '7ac8cc0d5616b4a987d4e1da77d272c41243f222219307668a5c8070f7c7c685', '2025-10-17 00:22:21', '2025-10-16 15:22:21');

-- --------------------------------------------------------

--
-- Table structure for table `admin_scopes`
--

CREATE TABLE `admin_scopes` (
  `scope_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'References users.user_id',
  `scope_type` enum('student','faculty','non-academic','coop') NOT NULL,
  `scope_value` varchar(100) DEFAULT NULL COMMENT 'College/department or NULL for all'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `identifier` varchar(50) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `credentials` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `party_list` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `user_id`, `identifier`, `first_name`, `last_name`, `middle_name`, `photo`, `credentials`, `created_by`, `created_at`, `updated_at`, `party_list`) VALUES
(25, NULL, '202200145', 'Jhenidell', 'Mary', 'M.', 'uploads/profile_pictures/candidate_1758984933.jpg', 'uploads/credentials/credentials_1758984933.pdf', 125, '2025-09-27 14:55:33', '2025-09-27 14:55:33', ''),
(26, NULL, '', 'Mark', 'Anthony', 'D.', 'uploads/profile_pictures/candidate_1758984996.jpg', 'uploads/credentials/credentials_1758984996.pdf', 125, '2025-09-27 14:56:36', '2025-09-27 14:56:36', ''),
(27, NULL, '', 'Mark Anthony', 'Dano', '', 'uploads/profile_pictures/candidate_1759767924.jpg', 'uploads/credentials/credentials_1758985072.pdf', 125, '2025-09-27 14:57:52', '2025-10-06 16:25:24', ''),
(28, NULL, '202200145', 'Jhenidell', 'Mary', 'M.', 'uploads/profile_pictures/candidate_1758986171.jpg', 'uploads/credentials/credentials_1758986171.pdf', 125, '2025-09-27 15:16:11', '2025-09-27 15:16:11', 'CVSU'),
(29, NULL, '202200145', 'Jhenidell', 'Mary', 'M.', 'uploads/profile_pictures/candidate_1758986278.jpeg', 'uploads/credentials/credentials_1758986889.pdf', 125, '2025-09-27 15:17:58', '2025-09-27 15:28:09', '');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `dept_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `dept_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `disabled_default_positions`
--

CREATE TABLE `disabled_default_positions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disabled_default_positions`
--

INSERT INTO `disabled_default_positions` (`id`, `admin_id`, `position_name`, `created_at`) VALUES
(1, 92, 'Senator', '2025-09-03 15:52:47'),
(2, 92, 'Representative', '2025-09-03 15:52:59'),
(3, 92, 'Auditor', '2025-09-04 07:20:07'),
(4, 92, 'President', '2025-09-04 07:20:14'),
(5, 92, 'Secretary', '2025-09-04 07:56:50'),
(6, 123, 'Representative', '2025-09-10 13:22:51'),
(7, 125, 'President', '2025-09-11 06:55:57'),
(8, 125, 'Public Relations Officer', '2025-09-27 15:30:12');

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `election_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `status` enum('upcoming','ongoing','completed') DEFAULT 'upcoming',
  `creation_stage` enum('draft','published','pending_admin','ready_for_voters') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `assigned_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_department` varchar(100) NOT NULL DEFAULT 'All',
  `target_position` enum('student','faculty','non-academic','coop','All') NOT NULL DEFAULT 'All',
  `realtime_results` tinyint(1) NOT NULL DEFAULT 0,
  `allowed_colleges` varchar(255) NOT NULL DEFAULT 'All',
  `allowed_courses` text DEFAULT '',
  `allowed_status` text DEFAULT NULL,
  `allowed_departments` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`election_id`, `title`, `description`, `start_datetime`, `end_datetime`, `status`, `creation_stage`, `created_by`, `assigned_admin_id`, `created_at`, `target_department`, `target_position`, `realtime_results`, `allowed_colleges`, `allowed_courses`, `allowed_status`, `allowed_departments`, `logo_path`) VALUES
(159, 'meow', 'sasas', '2025-08-28 00:00:00', '2025-08-29 12:59:00', 'completed', 'pending_admin', NULL, NULL, '2025-08-11 06:27:54', 'All', 'non-academic', 0, 'All', 'All', 'Part-time', 'MAINTENANCE', 'uploads/logos/1754993189_pingwinnn.jpg'),
(182, 'last na', 'sasasas', '2025-09-07 11:11:00', '2025-09-07 21:11:00', 'completed', 'pending_admin', NULL, NULL, '2025-08-11 08:17:25', 'All', 'coop', 0, 'All', 'All', 'MIGS', NULL, 'uploads/logos/1754915127_NESCAFE Banner.jpg'),
(187, 'okkkk', 'sasas', '2025-08-28 08:05:00', '2025-08-29 23:11:00', 'completed', 'ready_for_voters', NULL, NULL, '2025-08-12 09:31:49', 'All', 'coop', 0, 'All', 'All', 'MIGS', NULL, 'uploads/logos/1755158000_prof.jpg'),
(189, 'coop', 'ty', '2025-09-08 14:48:00', '2025-09-09 22:00:00', 'completed', 'ready_for_voters', NULL, NULL, '2025-08-17 06:19:45', 'Faculty', 'faculty', 0, 'all', NULL, 'Regular,Part-time,Contractual', NULL, 'uploads/logos/1755411585_tortang talong.jpg'),
(191, 'aaaaaaaaaaaaaaaa', 'sasasasassa', '2025-09-07 15:00:00', '2025-09-07 22:11:00', 'completed', 'ready_for_voters', NULL, NULL, '2025-08-22 04:35:08', 'Faculty', 'faculty', 0, 'all', NULL, 'Regular,Part-time,Contractual', NULL, 'uploads/logos/1755837308_s-l1600.jpg'),
(195, 'COOP Testing', 'try COOP', '2025-10-19 03:10:00', '2025-10-21 11:11:00', 'ongoing', 'ready_for_voters', NULL, 125, '2025-09-10 13:28:42', 'All', 'coop', 0, 'All', 'All', 'MIGS', NULL, 'uploads/logos/1757510922_pingwinnn.jpg'),
(196, 'FA Election', 'tryyy', '2025-09-28 21:19:00', '2025-09-30 11:11:00', 'completed', 'ready_for_voters', NULL, 125, '2025-09-10 13:32:43', 'Faculty', 'faculty', 0, 'all', NULL, 'Regular,Part-time,Contractual', NULL, 'uploads/logos/1757511163_images.jpeg'),
(197, 'try v1', 'try v1', '2025-09-11 21:34:00', '2025-09-12 11:11:00', 'completed', 'ready_for_voters', NULL, NULL, '2025-09-10 13:34:15', 'All', 'coop', 0, 'All', 'All', 'MIGS', NULL, 'uploads/logos/1757511255_prof.jpg'),
(198, 'CEIT', 'TRY', '2025-09-11 14:49:25', '2025-09-13 11:11:25', 'completed', 'pending_admin', NULL, 125, '2025-09-11 06:49:40', 'All', 'student', 0, 'CEIT', 'BSCS,BSIT,BSCpE,BSECE,BSCE,BSME,BSEE,BSIE,BSArch', 'All', NULL, 'uploads/logos/1757573380_pingwinnn.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `election_candidates`
--

CREATE TABLE `election_candidates` (
  `id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `position_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `election_candidates`
--

INSERT INTO `election_candidates` (`id`, `election_id`, `candidate_id`, `position`, `position_id`) VALUES
(3, 189, 3, 'Secretary', NULL),
(9, 189, 9, 'Vice President', NULL),
(10, 189, 10, 'Treasurer', NULL),
(11, 189, 11, 'Treasurer', NULL),
(12, 191, 12, 'Vice President', NULL),
(13, 182, 13, 'Chairpeson', 15),
(14, 191, 14, 'Treasurer', NULL),
(15, 191, 15, 'Vice President', NULL),
(17, 197, 17, 'President', NULL),
(18, 197, 18, 'Treasurer', NULL),
(19, 197, 19, 'Secretary', NULL),
(20, 197, 20, 'President', NULL),
(21, 197, 21, 'Public Relations Officer', NULL),
(25, 195, 25, 'Vice President', NULL),
(26, 195, 26, 'Chairpeson', 19),
(27, 195, 27, 'Senator', NULL),
(28, 195, 28, 'Senator', NULL),
(29, 195, 29, 'Vice President', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_verification_tokens`
--

CREATE TABLE `email_verification_tokens` (
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_logs`
--

CREATE TABLE `import_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `imported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_rows` int(11) DEFAULT 0,
  `successful_rows` int(11) DEFAULT 0,
  `failed_rows` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token`, `expires_at`) VALUES
(20, 67, '4658db1e6ab7212d2a2e1ba2426453d22c83081c38eb699cf8f92f2e8f643946', '2025-08-24 13:31:06');

-- --------------------------------------------------------

--
-- Table structure for table `pending_users`
--

CREATE TABLE `pending_users` (
  `pending_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `position` enum('student','academic','non-academic') NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `is_coop_member` tinyint(1) NOT NULL DEFAULT 0,
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT NULL,
  `source` enum('normal','csv') NOT NULL DEFAULT 'normal',
  `department1` varchar(255) DEFAULT NULL,
  `is_restricted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_users`
--

INSERT INTO `pending_users` (`pending_id`, `first_name`, `last_name`, `email`, `position`, `student_number`, `employee_number`, `is_coop_member`, `department`, `course`, `password`, `token`, `expires_at`, `created_at`, `status`, `source`, `department1`, `is_restricted`) VALUES
(196, 'John', 'Doe', 'john.doe@cvsu.edu.ph', 'academic', NULL, '1001', 1, 'CEIT', NULL, '$2y$10$CPoMCsKyy/Hni/k/./pI/.7ukkvN4TAtrpjsEB95Eg40x66U.Awzy', '1cb749d66408ef9ea5fd214e99837a0b9c0c3b00c355a603e624a264881f4c9c', '2025-10-13 16:36:00', '2025-10-12 08:36:00', 'regular', 'csv', 'Department of Computer and Electronics Engineering', 0),
(198, 'Robert', 'Johnson', 'robert.kyleraven.mabingnay@cvsu.edu.ph', 'non-academic', NULL, '1003', 1, 'Library', NULL, '$2y$10$6LBrehEjxjK0LNeGU5ulTeN8zxUZn0Ye9yGR96bLBPRRjrSvOTTFu', '433f5adbda733212e04e1776c321978b5c7fddae5e4b7b334d9d63544f8c466c', '2025-10-13 16:36:09', '2025-10-12 08:36:09', 'regular', 'csv', NULL, 0),
(201, 'Mark', 'Anthony', 'main.alejandro.zamora@cvsu.edu.ph', 'student', '202200155', '', 0, 'CCJ', 'BS Criminology', '$2y$10$pgvgNntY2OrXkJZjQaMOQ.Gp1/hlOOUs6VP2S7pHyGoXoJZ1ruEdq', 'f48b3a15ef4e54e5d3af250e8c34a787d23386b75bdd21d7c8d99ba59be6799f', '2025-10-17 16:34:50', '2025-10-16 14:34:50', '', 'normal', 'Department of Criminal Justice', 0);

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `created_by`, `created_at`) VALUES
(14, 'Chairpeson', 67, '2025-09-03 15:56:51'),
(15, 'Chairpeson', 67, '2025-09-04 06:09:42'),
(18, 'secret', 67, '2025-09-04 07:41:44'),
(19, 'Chairpeson', 125, '2025-09-11 06:56:27');

-- --------------------------------------------------------

--
-- Table structure for table `position_types`
--

CREATE TABLE `position_types` (
  `position_id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `allow_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `max_votes` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `position_types`
--

INSERT INTO `position_types` (`position_id`, `position_name`, `allow_multiple`, `max_votes`) VALUES
(0, 'Auditor', 0, 1),
(0, 'President', 0, 1),
(0, 'Public Relations Officer', 0, 1),
(0, 'Representative', 1, 1),
(0, 'Secretary', 0, 1),
(0, 'Senator', 1, 12),
(0, 'Treasurer', 0, 1),
(0, 'Vice President', 0, 1),
(14, '', 0, 1),
(15, '', 0, 1),
(18, '', 0, 1),
(19, '', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','voter') NOT NULL DEFAULT 'voter',
  `position` enum('student','academic','non-academic') NOT NULL,
  `student_number` varchar(50) DEFAULT NULL,
  `employee_number` varchar(50) DEFAULT NULL,
  `is_coop_member` tinyint(1) NOT NULL DEFAULT 0,
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `migs_status` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_scope` varchar(100) DEFAULT NULL,
  `department1` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `role`, `position`, `student_number`, `employee_number`, `is_coop_member`, `department`, `course`, `status`, `password`, `force_password_change`, `is_verified`, `is_admin`, `created_at`, `remember_token`, `is_active`, `migs_status`, `assigned_scope`, `department1`) VALUES
(67, 'Super', 'Admin', 'danomarkanthony30@gmail.com', 'super_admin', 'non-academic', NULL, NULL, 0, NULL, NULL, 'regular', '$2y$10$noaNXFF6a0t/o15BTw84GupBndZo6y.Nema67tEO6FdzaK5pLpvRi', 0, 1, 1, '2025-07-31 12:33:14', NULL, 1, 0, 'system-wide', NULL),
(125, 'mark', 'anthony', 'va.markanthony.dano@gmail.com', 'admin', 'student', NULL, NULL, 0, NULL, NULL, NULL, '$2y$10$nGECJLTzdTucOD0tI0KIV.LNw5KmGhYUB2v4mZujk4vE56QK.wHR2', 0, 1, 1, '2025-09-11 06:46:47', NULL, 1, 0, 'COOP', NULL),
(187, 'Robert', 'Johnson', 'main.kyleraven.mabingnay@cvsu.edu.ph', 'voter', 'non-academic', NULL, '1003', 1, 'Library', NULL, 'regular', '$2y$10$PeGIAmWdHBFwe7lLIYifbOxPekw4A7HPLWK73NiWCkYY.0CY6KtOa', 0, 1, 0, '2025-10-12 08:39:12', NULL, 1, 1, NULL, NULL),
(189, 'Jane', 'Smith', 'main.jhenidellannemary.benito@cvsu.edu.ph', 'voter', 'non-academic', NULL, '1002', 1, 'Administration', NULL, 'part-time', '$2y$10$9DubGmjXWusSdPHjeQnqfugd4gio5oynV5y2K8b57W6l6J/leihvy', 0, 1, 0, '2025-10-15 17:24:49', NULL, 1, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `vote_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `voter_id` int(11) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `position_name` varchar(100) DEFAULT NULL,
  `vote_datetime` timestamp NOT NULL DEFAULT current_timestamp(),
  `vote_type` enum('single','multi') NOT NULL DEFAULT 'single'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_login_tokens`
--
ALTER TABLE `admin_login_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `admin_scopes`
--
ALTER TABLE `admin_scopes`
  ADD PRIMARY KEY (`scope_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `scope_lookup` (`scope_type`,`scope_value`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_candidates_created_by` (`created_by`),
  ADD KEY `fk_candidate_user` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `dept_id` (`dept_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `disabled_default_positions`
--
ALTER TABLE `disabled_default_positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_position` (`admin_id`,`position_name`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`election_id`),
  ADD KEY `fk_created_by` (`created_by`),
  ADD KEY `fk_assigned_admin` (`assigned_admin_id`);

--
-- Indexes for table `election_candidates`
--
ALTER TABLE `election_candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_election` (`election_id`),
  ADD KEY `fk_candidate` (`candidate_id`),
  ADD KEY `position_id` (`position_id`);

--
-- Indexes for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD KEY `email_verification_tokens_ibfk_1` (`user_id`);

--
-- Indexes for table `import_logs`
--
ALTER TABLE `import_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `pending_users`
--
ALTER TABLE `pending_users`
  ADD PRIMARY KEY (`pending_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `position_types`
--
ALTER TABLE `position_types`
  ADD PRIMARY KEY (`position_id`,`position_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`vote_id`),
  ADD UNIQUE KEY `unique_candidate_vote` (`election_id`,`voter_id`,`candidate_id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `voter_id` (`voter_id`),
  ADD KEY `position_id` (`position_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admin_login_tokens`
--
ALTER TABLE `admin_login_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- AUTO_INCREMENT for table `admin_scopes`
--
ALTER TABLE `admin_scopes`
  MODIFY `scope_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `disabled_default_positions`
--
ALTER TABLE `disabled_default_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `election_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=199;

--
-- AUTO_INCREMENT for table `election_candidates`
--
ALTER TABLE `election_candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `import_logs`
--
ALTER TABLE `import_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `pending_users`
--
ALTER TABLE `pending_users`
  MODIFY `pending_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `admin_login_tokens`
--
ALTER TABLE `admin_login_tokens`
  ADD CONSTRAINT `admin_login_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_scopes`
--
ALTER TABLE `admin_scopes`
  ADD CONSTRAINT `admin_scopes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `fk_candidate_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_candidates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);

--
-- Constraints for table `elections`
--
ALTER TABLE `elections`
  ADD CONSTRAINT `fk_assigned_admin` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `election_candidates`
--
ALTER TABLE `election_candidates`
  ADD CONSTRAINT `election_candidates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`),
  ADD CONSTRAINT `fk_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `import_logs`
--
ALTER TABLE `import_logs`
  ADD CONSTRAINT `import_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`),
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`voter_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `votes_ibfk_4` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
