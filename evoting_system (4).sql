-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 14, 2025 at 12:49 PM
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
(159, 89, '488a2f0a5a35902706cdb5d05e056a24a0f4d49e18f4834c1fc32715c57fa44b', '2025-08-14 17:43:22', '2025-08-14 08:43:22'),
(160, 67, 'eddbea224a10ae8e71a9583934aa3d08fb5565db4ea51f27989d8a1c118f496b', '2025-08-14 17:43:40', '2025-08-14 08:43:40'),
(161, 67, '7db3cc341624688609e15e5bf308a3ede63745da3228d94748c2bfc8284e8929', '2025-08-14 19:20:29', '2025-08-14 10:20:29'),
(162, 67, 'ed25e4b3765987851e9c7571b4fe50c3806ba1c47e1b052da19d903ba0777e7f', '2025-08-14 19:36:20', '2025-08-14 10:36:20'),
(163, 67, '439ae283f646c17e40751301a42aa9823438114da118be5d06b0fbd654a2859c', '2025-08-14 19:45:15', '2025-08-14 10:45:15');

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
  `election_id` int(11) NOT NULL,
  `added_by` int(11) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `party_list` varchar(255) DEFAULT NULL,
  `credentials` text DEFAULT NULL,
  `manifesto` text DEFAULT NULL,
  `platform` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `election_id`, `added_by`, `full_name`, `position`, `party_list`, `credentials`, `manifesto`, `platform`, `created_at`) VALUES
(1, 0, NULL, 'Mark Anthony Dano', 'President', 'PARKAM', 'SALSJKLAJKSKAJDKLAJDKLAJDKL', 'DLA;LLSDL;AKL;DKAL;KD', 'DKAMKDJAJDKLAJDK', '2025-06-08 05:58:46'),
(2, 0, NULL, 'ALEJANDRO ZAMORA', 'President', 'CVSU', 'DKAJDKJKJSDJKAJSK', 'DAMKOWIODJWLFNM,SNDMSN', 'DANIWUDIMD,SM,CMSNMNFJ', '2025-06-08 05:59:37'),
(3, 0, NULL, 'Jhenidell Anne Mary Benito', 'Secretary', 'PARKAM', 'DADASDADADAD', 'DADADADAD', 'DADADADAD', '2025-06-08 06:48:04'),
(6, 145, NULL, 'kyle', 'Secretary', 'jsajskasjk', 'akl;djjlakjd', 'dadad', 'sasas', '2025-06-09 00:15:51'),
(8, 148, NULL, 'Mark Anthony Dano', 'President', 'CVSU', 'SASASAS', 'SDSKLDK', 'D;AKJSDKAJSK', '2025-06-10 14:44:48'),
(9, 148, NULL, 'Mark Anthony Dano', 'President', '', 'sjakjskajskajsas', 'skalsklajdlkajdk', 'dlakslakslkdlam', '2025-06-10 15:02:18'),
(10, 148, NULL, 'Jhenidell Anne Mary Benito', 'Senator', 'CSVU', 'to help students', ',ammsjkajdkajd', 'skkjakdja', '2025-06-10 15:04:58'),
(11, 150, NULL, 'Kyle Raven Mabingnay', 'Vice President', '', 'try', 'help', 'students', '2025-06-11 00:51:50');

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
(159, 'meow', 'sasas', '2025-08-11 00:00:00', '2025-08-11 12:59:00', 'completed', 'pending_admin', NULL, NULL, '2025-08-11 06:27:54', 'All', 'non-academic', 0, 'All', 'All', 'Part-time', 'MAINTENANCE', 'uploads/logos/1754993189_pingwinnn.jpg'),
(182, 'last na', 'sasasas', '2025-08-12 11:11:00', '2025-08-12 21:11:00', 'completed', 'pending_admin', NULL, NULL, '2025-08-11 08:17:25', 'Faculty', 'faculty', 0, 'CVMBS', NULL, 'Part-time', NULL, 'uploads/logos/1754915127_NESCAFE Banner.jpg'),
(187, 'okkkk', 'sasas', '2025-08-14 02:05:00', '2025-08-14 23:11:00', 'ongoing', 'pending_admin', NULL, NULL, '2025-08-12 09:31:49', 'Faculty', 'faculty', 0, 'all', NULL, 'Contractual', NULL, 'uploads/logos/1755158000_prof.jpg'),
(188, 'makiiii', 'kjkj', '2025-08-13 11:11:00', '2025-08-13 23:11:00', 'completed', 'pending_admin', NULL, NULL, '2025-08-12 10:40:02', 'Students', 'student', 0, 'CEIT', 'BSIT', NULL, NULL, 'uploads/logos/1755158671_NESCAFE Banner.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `election_candidates`
--

CREATE TABLE `election_candidates` (
  `id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(20, 67, '457dedc9f752f7d4d2fc48983d42904868c664708f60de2f4a5ae0b4ea078df3', '2025-08-08 15:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `pending_users`
--

CREATE TABLE `pending_users` (
  `pending_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `is_coop_member` tinyint(1) NOT NULL DEFAULT 0,
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT NULL,
  `source` enum('normal','csv') NOT NULL DEFAULT 'normal',
  `department1` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `assigned_scope` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `role`, `position`, `is_coop_member`, `department`, `course`, `status`, `password`, `force_password_change`, `is_verified`, `is_admin`, `created_at`, `remember_token`, `is_active`, `migs_status`, `assigned_scope`) VALUES
(43, 'zam', 'ali', 'main.alejandro.zamora@cvsu.edu.ph', 'voter', 'academic', 1, 'CSPEAR', 'Bachelor of Physical Education', 'regular', '$2y$10$NbPMcysLWoI1xmjKX82vGOcZ53oEoONpn4dvl01ubGx2s/FexTPiC', 1, 1, 0, '2025-06-07 06:25:58', NULL, 1, 1, NULL),
(61, 'mark', 'dano', 'mark.anthony.mark233@gmail.com', 'voter', 'academic', 0, 'CEIT', 'BS Information Technology', 'regular', '$2y$10$qnUyw9cpLdl/6gBpOkzO1e4fwfZEe83kdt02jFwYrJf8AXfaYwGIi', 1, 1, 0, '2025-06-10 13:22:15', NULL, 1, 0, NULL),
(66, 'jhenidell', 'benito', 'main.jhenidellannemary.benito@cvsu.edu.ph', 'voter', 'student', 0, 'CEIT', 'BS Information Technology', '', '$2y$10$BtZW40qUPWfJ.k2jafEyXulbhaoT0lF7eXjTrpVfISCfbnTT4vID2', 1, 1, 0, '2025-06-10 17:48:24', NULL, 1, 0, NULL),
(67, 'Super', 'Admin', 'danomarkanthony30@gmail.com', 'super_admin', 'non-academic', 0, NULL, NULL, 'regular', '$2y$10$noaNXFF6a0t/o15BTw84GupBndZo6y.Nema67tEO6FdzaK5pLpvRi', 0, 1, 1, '2025-07-31 12:33:14', NULL, 1, 0, 'system-wide'),
(89, 'mark', 'dano', 'va.markanthony.dano@gmail.com', 'admin', 'student', 0, NULL, NULL, NULL, '$2y$10$NQkrVqhfqVp.Wog/mnCpWuvwf3tHWD6tNly7BvrbzRR4TnSceYNWO', 1, 1, 1, '2025-08-07 15:53:22', NULL, 1, 0, 'COOP');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `vote_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `voter_id` int(11) NOT NULL,
  `vote_datetime` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

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
  ADD KEY `fk_added_by` (`added_by`);

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
  ADD KEY `fk_candidate` (`candidate_id`);

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
  ADD UNIQUE KEY `unique_vote` (`election_id`,`voter_id`),
  ADD KEY `candidate_id` (`candidate_id`),
  ADD KEY `voter_id` (`voter_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_login_tokens`
--
ALTER TABLE `admin_login_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `admin_scopes`
--
ALTER TABLE `admin_scopes`
  MODIFY `scope_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `election_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `election_candidates`
--
ALTER TABLE `election_candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `pending_users`
--
ALTER TABLE `pending_users`
  MODIFY `pending_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `fk_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `fk_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
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
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`voter_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
