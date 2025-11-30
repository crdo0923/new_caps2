-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 14, 2025 at 05:24 PM
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
-- Database: `smart_study`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics`
--

CREATE TABLE `analytics` (
  `id` int(11) UNSIGNED NOT NULL,
  `success_rate` decimal(5,2) NOT NULL,
  `total_users` int(11) UNSIGNED NOT NULL,
  `total_sessions_recorded` int(11) UNSIGNED NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics`
--

INSERT INTO `analytics` (`id`, `success_rate`, `total_users`, `total_sessions_recorded`, `recorded_at`) VALUES
(1, 90.50, 200, 5000, '2025-10-29 16:00:00'),
(2, 94.80, 450, 9500, '2025-11-04 16:00:00'),
(3, 95.25, 504, 10200, '2025-11-07 14:45:19');

-- --------------------------------------------------------

--
-- Table structure for table `study_sessions`
--

CREATE TABLE `study_sessions` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `task_description` varchar(255) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `program` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `student_id`, `program`, `password`) VALUES
(7, 'mat', 'rap', 'mat@gmail.com', '22145687', 'BSIT', '$2y$10$X4dcTMuVQo14ycQqj0X/R.jhv2nQhPdWD2nnbUHSCtGRArIigoKCu'),
(8, 'rap', 'fael', 'rap@gmail.com', '22145676', 'BSIS', '$2y$10$9a6GA7tUhfxjSY.ZmeQ7mOx72x/VMubEJ.bPHU7pttObiWlUw..W2'),
(9, 'jan', 'keth', 'jan@gmail.com', '2219650', 'BSCS', '$2y$10$ue3I10lYJ06iIkXNj0Bs4.ZdqZR9BhEx1VEUaBhn8tPL/r4HaqFyy'),
(11, 'ge', 'lo', 'gelo@gmail.com', '22415868', 'BSIT', '$2y$10$/pM/7wjKevBqLNM8lClp8ueZBloEcJTMFqAlrjnIREhTWo/Zk88fG'),
(12, 'gee', 'geeee', 'gee@gmai.com', '22145686', 'BSIT', '$2y$10$uc862XAAN3OSbVAJgrkrt.DYds9eR4kdQckYcvw7XTmBE6RU0AXSi'),
(13, 'RENEN', 'rey', '1234@gmail.com', '22145111', 'BSIT', '$2y$10$thH2TVl3oaFdh5dUgvevquDbH2Jr4scM94gxRdJQHi/wfXdtEsYMi'),
(14, 'REAWEAUBED', 'ADASUDH', '11111@gmail.com', '22141234', 'BSIS', '$2y$10$10BWNOX47EvM1NAyiIbUBOfSh8jgKU9y0Aq8wSaodaypFovJq0reK');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics`
--
ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `study_sessions`
--
ALTER TABLE `study_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics`
--
ALTER TABLE `analytics`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `study_sessions`
--
ALTER TABLE `study_sessions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `study_sessions`
--
ALTER TABLE `study_sessions`
  ADD CONSTRAINT `study_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
