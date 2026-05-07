-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 07, 2026 at 02:21 PM
-- Server version: 8.0.31
-- PHP Version: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `umu_voting`
CREATE DATABASE IF NOT EXISTS umu_voting
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE umu_voting;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
CREATE TABLE IF NOT EXISTS `candidates` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `position_id` int UNSIGNED NOT NULL,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manifesto` text COLLATE utf8mb4_unicode_ci,
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vote_count` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `position_id` (`position_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `position_id`, `full_name`, `manifesto`, `photo_path`, `vote_count`, `created_at`) VALUES
(1, 1, 'Hellen Namulema', 'I pledge to champion student welfare and academic excellence across all faculties.', 'uploads/candidate-photos/AliceNamutebi_1778162143.png', 1, '2026-05-07 12:41:54'),
(2, 1, 'Muwulya Derrick', 'My vision: a united, empowered student body with transparent leadership.', NULL, 0, '2026-05-07 12:41:54'),
(3, 2, 'Ssemugenyi Lawrence', 'I will bridge the gap between administration and students effectively.', NULL, 0, '2026-05-07 12:41:54'),
(4, 2, 'Sserwanga Adrian', 'Together we build a university community that respects every voice.', NULL, 1, '2026-05-07 12:41:54'),
(5, 3, 'Kaweesi Christopher', 'Efficient communication and organised records are my top priorities.', NULL, 1, '2026-05-07 12:41:54'),
(6, 3, 'Nsamba Johnmary', 'Every student informed, every decision documented and transparent.', NULL, 0, '2026-05-07 12:41:54'),
(7, 4, 'Kabazi ROnald', 'Responsible budgeting and transparent financial management for all.', NULL, 0, '2026-05-07 12:41:54'),
(8, 4, 'Namayengo Milly', 'Fair allocation of guild funds to benefit every single student.', NULL, 1, '2026-05-07 12:41:54'),
(9, 5, 'Ninsiima Promise', 'Student health and mental well-being will be at the heart of my tenure.', NULL, 0, '2026-05-07 12:41:54'),
(10, 5, 'Baguma Gerald', 'A supportive environment where every student can thrive and succeed.', NULL, 1, '2026-05-07 12:41:54');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
CREATE TABLE IF NOT EXISTS `positions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` tinyint UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `title`, `display_order`) VALUES
(1, 'Guild President', 1),
(2, 'Guild Vice President', 2),
(3, 'Secretary General', 3),
(4, 'Finance Minister', 4),
(5, 'Minister of Welfare', 5);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`) VALUES
(1, 'voting_open', '1'),
(2, 'election_title', 'UMU Student Guild Elections 2025/2026');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `reg_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `second_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('student','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `has_voted` tinyint(1) NOT NULL DEFAULT '0',
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reg_number` (`reg_number`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `reg_number`, `first_name`, `second_name`, `full_name`, `email`, `password`, `role`, `is_approved`, `has_voted`, `photo_path`, `created_at`) VALUES
(1, 'ADMIN-A000-00001', 'System', 'Administrator', 'System Administrator', 'admin@umu.ac.ug', '$2y$12$Hg0YBL7jG1qu/AfLjr5.J.arroRMPaSt0okTPIsGJ5E.X7fQ5X25i', 'admin', 1, 0, NULL, '2026-05-07 12:41:54'),
(2, '2023-B072-31711', '', '', 'Baguma Gerald', 'baguma.gerald@stud.umu.ac.ug', '$2y$12$LBR3x.L3SSPI.4uygWiBXe7stR7oeli76iSsKDokv4yFaQjdUXNlW', 'student', 1, 0, NULL, '2026-05-07 12:51:46'),
(3, '2023-B072-31712', 'Muwulya', 'Derrick', 'Muwulya Derrick', 'muwulya.derrick@stud.umu.ac.ug', '$2y$12$GvAH49xCKv.9TVH4X3iM/.iTkaU/KPGHjxllKZzQXIHdrWV7zYghG', 'student', 1, 1, 'uploads/user-photos/2023-B072-31712_1778160195.jpg', '2026-05-07 13:23:16');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
CREATE TABLE IF NOT EXISTS `votes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `candidate_id` int UNSIGNED NOT NULL,
  `position_id` int UNSIGNED NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_pos` (`user_id`,`position_id`),
  KEY `candidate_id` (`candidate_id`),
  KEY `position_id` (`position_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `user_id`, `candidate_id`, `position_id`, `voted_at`) VALUES
(1, 3, 1, 1, '2026-05-07 13:56:22'),
(2, 3, 4, 2, '2026-05-07 13:56:22'),
(3, 3, 5, 3, '2026-05-07 13:56:22'),
(4, 3, 8, 4, '2026-05-07 13:56:22'),
(5, 3, 10, 5, '2026-05-07 13:56:22');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
