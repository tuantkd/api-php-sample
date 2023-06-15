-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 15, 2023 at 06:18 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tasks`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sessions`
--

CREATE TABLE `tbl_sessions` (
  `id` bigint(20) NOT NULL COMMENT 'Session ID',
  `user_id` bigint(20) NOT NULL COMMENT 'User ID',
  `access_token` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Access Token',
  `access_token_expiry` datetime NOT NULL COMMENT 'Access Token Expiry Date time',
  `refresh_token` varchar(100) NOT NULL COMMENT 'Refresh Token',
  `refresh_token_expiry` datetime NOT NULL COMMENT 'Refresh Token Expiry Date time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_sessions`
--

INSERT INTO `tbl_sessions` (`id`, `user_id`, `access_token`, `access_token_expiry`, `refresh_token`, `refresh_token_expiry`) VALUES
(7, 3, 'OTRjOTRjMTY0ZDA2YmMzMWM0ZDI2OWNjOWQ0YmJmOTg2YzcwZjM4NGFjZGQ2MWYxMTY4Njg0NTcxOQ==', '2023-06-15 23:35:19', 'NDlmYzZlMGExNTRhMTA1M2NiMzQ4MDIxN2UzNGI0NzI5NjhhOTYwOGI0ZmJkMjAxMTY4Njg0NTcxOQ==', '2023-06-29 23:15:19');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_task`
--

CREATE TABLE `tbl_task` (
  `id` bigint(20) NOT NULL COMMENT 'Task ID - Rrimary Key',
  `title` varchar(255) NOT NULL COMMENT 'Task Title',
  `description` mediumtext DEFAULT NULL COMMENT 'Task Description',
  `deadline` datetime DEFAULT NULL COMMENT 'Task Deadline Date',
  `completed` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Task Completed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_task`
--

INSERT INTO `tbl_task` (`id`, `title`, `description`, `deadline`, `completed`) VALUES
(2, 'Test', 'Test', '2023-06-11 18:53:03', 'N'),
(3, 'Test 2', 'Test 2', '2023-06-11 18:53:45', 'Y'),
(4, 'Test update data', 'Test update description', '2023-08-23 12:40:00', 'N'),
(5, 'Test 4', 'Test 4', '2023-08-12 12:40:00', 'N'),
(6, 'Test 5', 'Test 5', '2023-08-12 12:40:00', 'N'),
(7, 'Test 8', 'Test 8', '2023-08-12 12:40:00', 'N');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `id` bigint(20) NOT NULL COMMENT 'User ID',
  `fullname` varchar(255) NOT NULL COMMENT 'User Fullname',
  `username` varchar(255) NOT NULL COMMENT 'User Name',
  `password` varchar(255) NOT NULL COMMENT 'Password',
  `user_active` enum('N','Y') NOT NULL COMMENT 'Is User Active',
  `user_attempts` int(1) NOT NULL DEFAULT 0 COMMENT 'User Attempts'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`id`, `fullname`, `username`, `password`, `user_active`, `user_attempts`) VALUES
(3, 'Nguyen Van B', 'tuan', '$2y$10$U.nQQw266xQSfL5cxFVZhuI.ecTvkYmes6iMhcnFBMp9mYmkQ/5lW', 'Y', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_sessions`
--
ALTER TABLE `tbl_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_token` (`access_token`),
  ADD UNIQUE KEY `refresh_token` (`refresh_token`),
  ADD KEY `session_user_fk` (`user_id`);

--
-- Indexes for table `tbl_task`
--
ALTER TABLE `tbl_task`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_sessions`
--
ALTER TABLE `tbl_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Session ID', AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_task`
--
ALTER TABLE `tbl_task`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Task ID - Rrimary Key', AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'User ID', AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_sessions`
--
ALTER TABLE `tbl_sessions`
  ADD CONSTRAINT `session_user_fk` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
