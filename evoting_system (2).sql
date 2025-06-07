-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2025 at 04:12 PM
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
(5, 20, '55fe192dbfb261d888180fbac78855aa8a03bf3403ded9eed18214fd30ab5da8', '2025-05-28 17:26:49', '2025-05-28 08:26:49'),
(20, 20, '298a5194bd921c82d99c5ddd78a6037aae16808a24dfe3d2efc4b659b6e007e9', '2025-05-28 23:40:41', '2025-05-28 14:40:41'),
(38, 20, 'b4f98f45f902c2e1765fdb0ddaabf48bf77845c5fa9bc840ea7919e1c63cfc06', '2025-06-03 04:05:56', '2025-06-02 19:05:56'),
(39, 20, 'e319548e9783253cef3c9ca3c330ba83943ce4fd594ce4cff29d3bdab3dfb293', '2025-06-03 04:11:01', '2025-06-02 19:11:01'),
(44, 20, 'ce4d849fd8d49dc0acd5169082fb56488d1f88a2b77f1940a7ab8d7ca005f252', '2025-06-03 12:49:39', '2025-06-03 03:49:39'),
(59, 20, '8098be14c19307201d0fb4c96623fb7f17f57b454c83e082827b80ba63144819', '2025-06-04 19:15:55', '2025-06-04 10:15:55'),
(60, 20, 'f5ee8b332edc329dac358d0d90e27d4e6e0168c77577e271b278d93a9fff65f5', '2025-06-04 19:16:49', '2025-06-04 10:16:49'),
(64, 20, '886b2cdf3ac6813c92118d1959336a13a4aa023735475e1d6ccf4ac6da6de2fc', '2025-06-06 18:04:54', '2025-06-06 09:04:54'),
(77, 20, 'ad16e8b9e96a6e716ccfe9139fc51a686dbf5606e2a5b7cf10ad3a2f866e96da', '2025-06-07 15:16:22', '2025-06-07 06:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `candidate_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `manifesto` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_department` varchar(100) NOT NULL DEFAULT 'All',
  `target_position` enum('student','faculty','non-academic','coop','All') NOT NULL DEFAULT 'All',
  `realtime_results` tinyint(1) NOT NULL DEFAULT 0,
  `allowed_colleges` varchar(255) NOT NULL DEFAULT 'All',
  `allowed_courses` text DEFAULT '',
  `allowed_status` text DEFAULT NULL,
  `allowed_departments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`election_id`, `title`, `description`, `start_datetime`, `end_datetime`, `status`, `created_at`, `target_department`, `target_position`, `realtime_results`, `allowed_colleges`, `allowed_courses`, `allowed_status`, `allowed_departments`) VALUES
(121, 'coop', 'edi moew', '2025-06-07 00:00:00', '2025-06-07 23:59:59', 'ongoing', '2025-06-07 02:42:29', 'All', 'coop', 0, 'All', 'All', 'MIGS', NULL),
(125, 'non-academic', 'okay', '2025-06-07 00:00:00', '2025-06-07 23:59:59', 'ongoing', '2025-06-07 02:55:18', 'All', 'non-academic', 0, 'NAEA', 'All', 'Part-time', NULL),
(127, 'FA', 'SASAS', '2025-06-07 00:00:00', '2029-07-07 23:59:59', 'ongoing', '2025-06-07 02:57:01', 'All', 'faculty', 0, 'CSPEAR', 'BPE', 'Regular', NULL),
(128, 'EDI MEOW', 'HAAAAAAAAAAAAAA', '2025-06-07 00:00:00', '2025-06-07 23:59:59', 'ongoing', '2025-06-07 02:58:07', 'All', 'student', 0, 'CEIT', 'BSIT,BSCS,BSCpE,BSEE', 'All', NULL),
(131, 'try ngani', 'non-acad', '2025-06-07 00:00:00', '2025-06-07 23:59:59', 'ongoing', '2025-06-07 06:10:39', 'All', 'non-academic', 0, 'IT', 'All', 'Regular,Part-time,Contractual', NULL),
(132, 'HAHAHHAAHAH', 'YHAHAYAHAHAH', '2025-06-07 00:00:00', '2025-06-07 23:59:59', 'ongoing', '2025-06-07 06:15:44', 'All', 'faculty', 0, 'CAS', 'BSBio,BSChem', 'Contractual', NULL),
(138, 'NAGANA BAT O', 'SAS', '2025-06-07 00:00:00', '2025-06-07 23:59:59', 'ongoing', '2025-06-07 12:33:24', 'All', 'faculty', 0, 'COM', 'BLIS', 'Regular,Part-time,Contractual', NULL);

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

--
-- Dumping data for table `email_verification_tokens`
--

INSERT INTO `email_verification_tokens` (`token_id`, `user_id`, `token`, `expires_at`, `is_used`) VALUES
(5, 20, '89d828edb9762c9aa0a9414dad0638097affc70f953983b9176c4045ccea1f1e', '2025-05-27 19:38:25', 0),
(6, 20, '240a4139fe22b74da2a2a7eb485a28e84ea36f0502d4046c9b84355c6a20d448', '2025-05-27 19:42:18', 0),
(7, 20, '7b48bcc95c4ded928c1bb7935d39c002d4669c6b9f3760650834726e14744f88', '2025-05-27 20:15:40', 0),
(8, 20, '5cf93bffee3afa5b62bf02eb98064159ec03cb5b7b5adb03bed6ba3743de1831', '2025-05-27 20:23:00', 0),
(9, 20, '691d3713596b3c164fee74fd50b74e97cf9b03d9e09bf95690c14e0ec7c810fd', '2025-05-27 20:23:04', 0),
(10, 20, '692f8bab67d6f09cec46d25fdeae4e966b15def79e8581b48adb97e2f2bc42c3', '2025-05-27 20:27:10', 0),
(11, 20, '8cd822ddec673fd777d2d6e9d9f974564f19b4e9d071354cfebee40f44e50f6b', '2025-05-27 20:27:27', 0),
(12, 20, 'bef1a35e8b84559f93876ebd851c6f076ba3aa52613c6077716ba41ad7165c23', '2025-05-27 20:28:20', 0),
(13, 20, 'bbe06c5236eb905c5972ee6cc9d3877eb79c9875e933a3ed803e4761a15e8c70', '2025-05-27 20:29:51', 0),
(14, 20, 'ef191d2067b0b98f3d4c652fcb6b61794ce07d03cf8043c84dbbf93f90961fa5', '2025-05-27 20:31:12', 0),
(15, 20, '388c191173b3c9bc2c68b6974a02462ea93a11c24ec7c87591f6d94f177bbeeb', '2025-05-27 20:36:50', 0),
(16, 20, '02e14e6a2c2dcae5af8fab5eff8947d4d7f175a6ccf60945280e2298c3630eda', '2025-05-27 20:38:01', 0),
(17, 20, 'fd2b104dae83d9dc44e18c52c60fa2c137a8df92fff4c2a196791e231b222c4a', '2025-05-28 20:23:44', 0),
(18, 20, 'ac8b535ae4eabe54b2038b6974af764fdc3d723212c0fb6d0614725ccab524e9', '2025-05-28 20:24:33', 0),
(19, 20, '8cccbe4d91a8dc7d30379bdb9068be615f0b189d267103c535a08f88e1493e66', '2025-05-28 20:25:44', 0),
(20, 20, '8819ad32994fbf7c090c5ad3289917de0495069e0ace5d4a8234cb38555ae5b0', '2025-05-28 20:28:48', 0),
(21, 20, 'ba8e4019a2f777086b845c5d91fc042861ab377152886867f2368126b0778371', '2025-05-28 20:33:33', 0),
(22, 20, '55c404fda44f279fa3b11002d84b35f33a9e927ed2344a9b3e53b859d9eddfd7', '2025-05-28 20:38:31', 0),
(23, 20, '222a5c42dff76d80c97052505c880866e892cd8d192b3c8e72a2eabecd4c44b5', '2025-05-28 20:39:14', 0),
(24, 20, 'c88f46878078d6e01c2262d4c0ba5922f0da6b11a79cbb8c87a0ffdc0343aa9f', '2025-05-28 20:43:35', 0),
(25, 20, '6df9522fe5b2c3fc5615306c323e8df4a1ef07303a678627688fb4def4e41e02', '2025-05-28 20:47:21', 0),
(26, 20, '446213c52557c77bea688cd394760efe73ce8850c38352abcd8aaebecf3f7a7d', '2025-05-28 20:53:03', 0),
(27, 20, '8f70e7166316512eafbdb781434fa186a1ac6733c7ec6e9d85fbd465b86f8004', '2025-05-28 21:13:42', 0),
(28, 20, '511e2b8ea2d0838a2e04047a33ab3f9784d1a9781be6a8feeb470b4efa1d0483', '2025-05-28 21:23:00', 0),
(29, 20, 'c2ae5e7f41f840c0014850280a6edc3b5ffec63575eb55db5963c36db6da760a', '2025-05-28 21:26:48', 0),
(30, 20, 'f94f0be017ceef31ea07f52076297176ebfd7273941957eab161e1dadf9420ce', '2025-05-28 21:32:32', 0),
(31, 20, '1452649cb01316ffcc8b0ad61a6bf5539af36850e3523fb1393d06f1a9d43e0e', '2025-05-28 21:35:03', 0),
(32, 20, '93404be5bf58459096ecebaf41ce81801c6a6bfebc16a4184908634573059c3e', '2025-05-28 21:38:35', 0),
(33, 20, '7fdf124b86b3a337b475a191b197ab8aa3199ed7e1f7512740d349dbf9491986', '2025-05-29 03:40:43', 0),
(34, 20, '8d86463045bf66d3c4bf93d0e3a7b4a00953d02f8dfbf839c5f24fc70a8e84fd', '2025-05-29 03:43:18', 0),
(35, 20, '96dcb32bb2cf17189d21e33784764096e601ccde2ab5c1233c6c44957d5ef42d', '2025-05-29 03:46:46', 0),
(36, 20, '27ec768df14daa6eaa0f9ea5c8e9be7bc1a34f7320f1a8535f89bf318e3a62ba', '2025-05-29 03:52:58', 0),
(37, 20, 'cdeb74c2e98313ba446707552f91625e64459c2585e6f6b55e81f09d7285bc86', '2025-05-29 03:57:11', 0),
(38, 20, '1c8588b8f04a1da02bb428c91b0a03689bbd85e2e5275b0e4a7cdf4458bd6e9d', '2025-05-29 14:11:36', 0),
(39, 20, '01c64e0a3f17c54d518a0373a660f4b1ed2810e453bb36881ab619dae3bb1fdd', '2025-05-29 15:35:55', 0),
(40, 20, 'd0764355b5411d87fb9266cf628b1e184546626c5977bb8a2b787923496f321f', '2025-05-29 15:39:31', 0),
(41, 20, '6af677cf9bdd9c65f91dfaf4b694f93d3f806965dd98c790560c023379e5f1cd', '2025-05-29 15:41:16', 0),
(42, 20, '3a2c6356c3516e25266301d6e9ba9bd05b2a16b3e2d611220986f59ac8c0bc09', '2025-05-29 15:55:37', 0),
(43, 20, '808b3fe53a721935e960c2d0412f0133d60058382d77537dc5a81a49a3066ccf', '2025-05-29 15:57:30', 0),
(44, 20, 'f0d802ce7bafc85e939842303fe39ca02207e8acfc4a9220cd62c32fc2c1ebfc', '2025-05-29 16:10:03', 0);

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
  `source` enum('normal','csv') NOT NULL DEFAULT 'normal'
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
  `position` enum('student','academic','non-academic') NOT NULL,
  `is_coop_member` tinyint(1) NOT NULL DEFAULT 0,
  `department` varchar(100) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `migs_status` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `position`, `is_coop_member`, `department`, `course`, `status`, `password`, `is_verified`, `is_admin`, `created_at`, `remember_token`, `is_active`, `migs_status`) VALUES
(20, 'Admin', 'Account', 'main.markanthony.dano@cvsu.edu.ph', '', 0, NULL, NULL, 'regular', '$2y$10$ketvjRXDJwwJdX4WT4gDoelyWJPKrx794nHf.GmaTwP.j8Gt1V.xG', 1, 1, '2025-05-27 11:30:36', NULL, 1, 0),
(40, 'mark', 'dano', 'mark.anthony.mark233@gmail.com', 'student', 0, 'CEIT', 'BS Information Technology', '', '$2y$10$FD/fR4az/mW26C9YLRBeWunevAAjdj9rIxgs74ixoY7mshJ8iwPr.', 1, 0, '2025-06-07 03:24:09', NULL, 1, 0),
(43, 'zam', 'ali', 'main.alejandro.zamora@cvsu.edu.ph', 'academic', 1, 'CSPEAR', 'Bachelor of Physical Education', 'regular', '$2y$10$NbPMcysLWoI1xmjKX82vGOcZ53oEoONpn4dvl01ubGx2s/FexTPiC', 1, 0, '2025-06-07 06:25:58', NULL, 1, 0),
(53, 'jhenidell', 'benito', 'va.markanthony.dano@gmail.com', 'non-academic', 0, 'NAEA', '', 'part-time', '$2y$10$djfcU82.5p3CGkX4JChH0uTL7V2jXZsot.Pkd4glud3V7cLNWKF3m', 1, 0, '2025-06-07 08:17:24', NULL, 1, 0),
(54, 'kyle', 'mabingnay', 'danomarkanthony30@gmail.com', 'non-academic', 1, 'IT', '', 'regular', '$2y$10$mfVmuDuZ2TKMzpCIQ/Xd7Oxa9BEdY94UQl4sdGrilSCKP1A8M9ZgC', 1, 0, '2025-06-07 08:23:34', NULL, 1, 1);

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
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`candidate_id`),
  ADD KEY `election_id` (`election_id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD PRIMARY KEY (`election_id`);

--
-- Indexes for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `candidate_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `election_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `pending_users`
--
ALTER TABLE `pending_users`
  MODIFY `pending_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_login_tokens`
--
ALTER TABLE `admin_login_tokens`
  ADD CONSTRAINT `admin_login_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`election_id`),
  ADD CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`);

--
-- Constraints for table `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD CONSTRAINT `email_verification_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

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
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`candidate_id`),
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`voter_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
