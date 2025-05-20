-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 09, 2025 at 04:51 PM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u659921429_dbnew`
--

-- --------------------------------------------------------

--
-- Table structure for table `appeals`
--

CREATE TABLE `appeals` (
  `appeal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `reason` text NOT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `instructor_id` int(11) NOT NULL,
  `instructor_read` tinyint(1) NOT NULL DEFAULT 0,
  `admin_read` tinyint(1) NOT NULL DEFAULT 0,
  `student_read` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appeals`
--

INSERT INTO `appeals` (`appeal_id`, `user_id`, `schedule_id`, `date`, `reason`, `document_path`, `status`, `instructor_id`, `instructor_read`, `admin_read`, `student_read`, `created_at`, `updated_at`) VALUES
(2, 10, 18, '2025-03-11', 'Sick leave', 'uploads/appeals/appeal_10_67dbb49b134e0.docx', 'Rejected', 4, 1, 1, 1, '2025-03-20 06:24:27', '2025-03-20 06:33:16');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('Present','Absent','Late','Cutting') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `user_id`, `schedule_id`, `section`, `course_id`, `date`, `check_in_time`, `check_out_time`, `status`) VALUES
(23, 10, 18, '', 0, '2025-03-14', '10:08:59', '10:19:52', 'Present'),
(26, 10, 28, '', 0, '2025-03-24', '16:47:47', '16:48:37', 'Present'),
(27, 8, 18, '', 0, '2025-05-09', '19:16:25', '19:18:00', 'Present');

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_name`, `start_date`, `end_date`) VALUES
(0, 'ITE Elective 4', '2025-03-07', '2025-05-07'),
(201, 'Anatomy', '2025-03-07', '2025-05-15'),
(302, 'Database Fundamentals', '2025-01-03', '2025-05-20'),
(401, 'Programming', '2025-01-03', '2025-05-20'),
(406, 'Data Modeling', '2025-03-07', '2025-03-14'),
(505, 'Astrology', '2025-01-03', '2025-05-20'),
(804, 'Figma', '2025-03-07', '2025-03-26'),
(90210, 'Travis Scott', '2025-03-07', '2025-03-31');

-- --------------------------------------------------------

--
-- Table structure for table `enrollment`
--

CREATE TABLE `enrollment` (
  `enrollment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollment`
--

INSERT INTO `enrollment` (`enrollment_id`, `user_id`, `schedule_id`) VALUES
(35, 8, 18),
(36, 10, 18),
(50, 10, 28);

-- --------------------------------------------------------

--
-- Table structure for table `parent_student`
--

CREATE TABLE `parent_student` (
  `id` int(11) NOT NULL,
  `parent_id` varchar(20) NOT NULL,
  `student_id` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parent_student`
--

INSERT INTO `parent_student` (`id`, `parent_id`, `student_id`) VALUES
(3, 'P10-534931', '10-534931'),
(5, 'P10-534931', '12-5345712'),
(4, 'P15-300108', '15-300108');

-- --------------------------------------------------------

--
-- Table structure for table `pending_tags`
--

CREATE TABLE `pending_tags` (
  `id` int(11) NOT NULL,
  `rfid_tag` varchar(50) NOT NULL,
  `registration_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_tags`
--

INSERT INTO `pending_tags` (`id`, `rfid_tag`, `registration_time`) VALUES
(1, '1249181', '2025-03-26 14:20:02'),
(2, '122161', '2025-05-09 18:54:12'),
(3, '924581', '2025-05-09 18:54:41'),
(4, '1387671', '2025-03-28 15:02:46'),
(5, '115235849', '2025-03-28 16:26:27');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `section` varchar(50) NOT NULL,
  `course_id` int(11) NOT NULL,
  `instructor_id` int(11) NOT NULL,
  `room` varchar(50) NOT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `checkin_grace_period` int(11) NOT NULL DEFAULT 10,
  `checkout_grace_period` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `section`, `course_id`, `instructor_id`, `room`, `day`, `start_time`, `end_time`, `checkin_grace_period`, `checkout_grace_period`) VALUES
(3, '401I', 401, 4, 'H-311', 'Friday', '14:10:00', '17:00:00', 10, 10),
(4, '402I', 0, 4, 'H-311', 'Friday', '10:14:00', '11:00:00', 10, 10),
(18, '401I', 401, 4, 'C26', 'Friday', '18:14:00', '19:14:00', 10, 10),
(19, '403I', 201, 18, 'H401', 'Friday', '07:00:00', '09:00:00', 10, 10),
(20, 'TEST', 90210, 18, 'H401', 'Wednesday', '09:00:00', '10:30:00', 10, 10),
(22, '402I', 90210, 4, 'H501', 'Wednesday', '13:00:00', '15:00:00', 10, 10),
(28, '401I', 302, 4, 'H311', 'Monday', '15:40:00', '17:40:00', 10, 10);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `section` varchar(20) DEFAULT NULL,
  `rfid_tag` varchar(255) DEFAULT NULL,
  `role_id` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `id_number`, `email`, `password`, `name`, `section`, `rfid_tag`, `role_id`) VALUES
(1, 'ADMIN2024', 'admin@gmail.com', '$123', 'System Administrator', '', 'RFID12344', 1),
(4, '12-645081', 'aoba.suzuki@my.jru.edu', 'AOBA', 'Aoba Suzuki', NULL, '', 2),
(8, '16-300381', 'marcusdaunte.rodriguez@my.jru.edu', '$2y$10$C/d/j/ET8Gs47wA05kraaeRKWhlrGZ6mD8LEOMwIukGMoVGWuKR/G', 'Marcus Daunte Rodriguez', '401I', '15611881', 3),
(10, '10-534931', 'johnnethjay.maure@my.jru.edu', 'MOMMSNOK', 'Johnneth Jay Maure', '401I', '185481', 3),
(11, '12-5345712', 'eujanefaith.maure@my.jru.edu', 'FAITH', 'Eujanefaith Maure', NULL, NULL, 3),
(12, '22-259714', 'kyanozidrendimpas@my.jru.edu', 'KIANO', 'Kiano Zidren Dimpas', '401I', '11011371', 3),
(15, 'P10-534931', 'janeth@gmail.com', 'janeth', 'Janeth Maure', NULL, NULL, 4),
(16, '15-300108', 'macristina.ragot@my.jru.edu', 'EDCJZJMI', 'Ma. Cristina Ragot', NULL, '10310581', 3),
(17, 'P15-300108', 'mercedes@gmail.com', 'mercedes', 'Mercedes Ragot', NULL, NULL, 4),
(18, '13-173869', 'francis.serafico@my.jru.edu', 'FRANCIS', 'Francis Serafico', NULL, NULL, 2),
(22, '14-546368', 'eufegenio.maure@my.jru.edu', 'john', 'Eufegenio Maure', NULL, NULL, 3),
(27, '17-300381', 'Leane@gmail.com', '$2y$10$xBfcYrEaZZvXZ9NDLjkWAOE0nNRRuVB.oJN8RJQPWzZqaGpRo56kC', 'Leane', '401I', '123456788', 3),
(28, '17-305132', 'John@gmail.com', '$2y$10$DA5e61mJqq5F.5AVl4P6J.WAsPI2YeqMS3Pu8fS9Ul5F7/qaTZRva', 'John', '401I', '123456788', 3),
(33, '13-42425', 'kyle@gmail.com', '$2y$10$5ZjXoYB8JG7.w.DHXUvmX.LY8gatK4MlXVVqEmgUGjcWVUhWl5FYm', 'Kyle', '401I', '1490823', 3),
(34, '16-34563', 'richard@gmail.com', '$2y$10$FNQwWxESw27N.sf4q/M79OV60x/g9KuUlnKiGCiGu2OVF7uqjc3pW', 'Richard', '401I', '189032', 3),
(38, '19-789087', 'royalejandro@gmail.com', '$2y$10$5l5lVVC5araz5gm6axq9MOTsGj4hTxYle5T9ioj0qGfSEGE9DP1wW', 'Roy Alejandro', '401I', NULL, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appeals`
--
ALTER TABLE `appeals`
  ADD PRIMARY KEY (`appeal_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parent_id` (`parent_id`,`student_id`),
  ADD KEY `parent_student_ibfk_2` (`student_id`);

--
-- Indexes for table `pending_tags`
--
ALTER TABLE `pending_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_tag` (`rfid_tag`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_section` (`section`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appeals`
--
ALTER TABLE `appeals`
  MODIFY `appeal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollment`
--
ALTER TABLE `enrollment`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `parent_student`
--
ALTER TABLE `parent_student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `pending_tags`
--
ALTER TABLE `pending_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appeals`
--
ALTER TABLE `appeals`
  ADD CONSTRAINT `appeals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appeals_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appeals_ibfk_3` FOREIGN KEY (`instructor_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`),
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`);

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `enrollment`
--
ALTER TABLE `enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `enrollment_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`);

--
-- Constraints for table `parent_student`
--
ALTER TABLE `parent_student`
  ADD CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `user` (`id_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `user` (`id_number`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`),
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`instructor_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
