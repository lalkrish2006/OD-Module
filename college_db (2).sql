-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2025 at 06:09 AM
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
-- Database: `college_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cas`
--

CREATE TABLE `cas` (
  `id` int(11) NOT NULL,
  `register_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cas`
--

INSERT INTO `cas` (`id`, `register_no`, `name`, `password`, `department`, `year`, `section`) VALUES
(1, '5', 'Swarna Sudha S', '$2y$10$4A4qGPqj8Gdb7Z9akqlbUOeVacQqdmDHBr9rtWw6atyZBPo1e/g2a', 'CSE', 3, 'A');

-- --------------------------------------------------------

--
-- Table structure for table `hods`
--

CREATE TABLE `hods` (
  `id` int(11) NOT NULL,
  `register_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hods`
--

INSERT INTO `hods` (`id`, `register_no`, `name`, `password`, `department`) VALUES
(1, '1', 'Anish ', '$2y$10$tdLxIrLwwcLhfjtmILpJDemtz2Tx3atTG3JHtTa8j/PSVdIMwvkHe', 'CSE');

-- --------------------------------------------------------

--
-- Table structure for table `jas`
--

CREATE TABLE `jas` (
  `id` int(11) NOT NULL,
  `register_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jas`
--

INSERT INTO `jas` (`id`, `register_no`, `name`, `password`, `department`) VALUES
(2, '2', 'Rishi', '$2y$10$CUJVFvkwL8HYj2kJKGj.1eMBV3bAyVpFXYEj7aMKk06NaHaJYRAOW', 'CSE');

-- --------------------------------------------------------

--
-- Table structure for table `lab_form`
--

CREATE TABLE `lab_form` (
  `id` int(11) NOT NULL,
  `registerNumber` varchar(20) NOT NULL,
  `studentName` varchar(100) NOT NULL,
  `year` varchar(20) NOT NULL,
  `department` varchar(50) NOT NULL,
  `section` varchar(10) NOT NULL,
  `purpose` enum('internal','external') NOT NULL,
  `fullDayDate` date DEFAULT NULL,
  `fromTime` time DEFAULT NULL,
  `toTime` time DEFAULT NULL,
  `fromDate` date DEFAULT NULL,
  `toDate` date DEFAULT NULL,
  `collegeName` varchar(150) DEFAULT NULL,
  `eventName` varchar(150) DEFAULT NULL,
  `extFromDate` date DEFAULT NULL,
  `extToDate` date DEFAULT NULL,
  `mentor` varchar(100) NOT NULL,
  `systemRequired` enum('Yes','No') NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_technicians`
--

CREATE TABLE `lab_technicians` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `register_no` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_technicians`
--

INSERT INTO `lab_technicians` (`id`, `name`, `department`, `register_no`, `password`, `created_at`) VALUES
(1, 'Suresh ', 'CSE', '8', '$2y$10$0d2mhH3bpkGvOZ4xfeY50uRRIaR8RHQKUWRsOMEPQWHbWWjbMWOBy', '2025-10-11 05:35:46');

-- --------------------------------------------------------

--
-- Table structure for table `mentors`
--

CREATE TABLE `mentors` (
  `id` int(11) NOT NULL,
  `register_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `section` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mentors`
--

INSERT INTO `mentors` (`id`, `register_no`, `name`, `password`, `department`, `year`, `section`) VALUES
(10, '100', 'Vivek V', '$2y$10$C6zPl2hSB.2/j2P5TpdKaejg7VbbsNZUSnULf7pF301EcZH7u.ofm', 'CSE', 3, 'A'),
(15, '101', 'Swarna Sudha', '$2y$10$s2zqq9DCSYOM6Q1oMzX0o.sqFvBm.CeA9L61BtraZx3Qzy9eL4vg6', 'CSE', 3, 'A'),
(16, '102', 'Devi', '$2y$10$NSxIGOANIGHz//Xjz2GcEu.n8uk2PgRjWjXfj7rxGLWdVxmTwWcTq', 'CSE', 3, 'B');

-- --------------------------------------------------------

--
-- Table structure for table `od_applications`
--

CREATE TABLE `od_applications` (
  `id` int(11) NOT NULL,
  `register_no` varchar(20) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `year` varchar(20) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `mentor` varchar(100) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `od_type` varchar(20) DEFAULT NULL,
  `od_date` date DEFAULT NULL,
  `from_time` time DEFAULT NULL,
  `to_time` time DEFAULT NULL,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL,
  `college_name` varchar(150) DEFAULT NULL,
  `event_name` varchar(150) DEFAULT NULL,
  `lab_required` tinyint(1) DEFAULT 0,
  `lab_name` varchar(255) DEFAULT NULL,
  `system_required` tinyint(1) DEFAULT 0,
  `request_bonafide` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `od_applications`
--

INSERT INTO `od_applications` (`id`, `register_no`, `student_name`, `year`, `department`, `section`, `mentor`, `purpose`, `od_type`, `od_date`, `from_time`, `to_time`, `from_date`, `to_date`, `college_name`, `event_name`, `lab_required`, `lab_name`, `system_required`, `request_bonafide`, `created_at`, `status`) VALUES
(57, '953623104053', 'Lalith Krishna V M', '3rd Year', 'CSE', 'A', 'Vivek V', 'External Hackathon @ VIT', 'external', '0000-00-00', '00:00:00', '00:00:00', '2025-10-08', '2025-10-10', 'Vellore Institute Of Technology', 'Vellorathon', 0, NULL, 0, 1, '2025-10-08 03:54:05', 'Mentors Rejected'),
(58, '953623104053', 'Lalith Krishna V M', '3rd Year', 'CSE', 'A', 'Vivek V', 'Internal Hackathon @ RIT', 'internal', '2025-10-08', '10:27:00', '11:27:00', '2025-10-08', '2025-10-08', '', '', 0, NULL, 0, 0, '2025-10-08 04:57:56', 'HOD Accepted'),
(59, '953623104044', 'S Jeyaseelan', '3rd Year', 'CSE', 'A', 'Vivek V', 'KPR Institute Of Technology', 'internal', '2025-10-11', '00:00:00', '00:00:00', '2025-10-11', '2025-10-11', 'Kpr ', 'Hackaxerlate', 0, NULL, 0, 0, '2025-10-11 04:24:11', 'HOD Rejected'),
(70, '953623104053', 'Lalith Krishna V M', '3rd Year', 'CSE', 'A', 'Vivek V', '2K6 Hackathon', 'internal', '0000-00-00', '10:53:00', '12:53:00', '2025-10-11', '2025-10-11', '', '', 1, 'IoT Lab', 1, 0, '2025-10-11 05:21:46', 'HOD Accepted'),
(71, '953623104053', 'Lalith Krishna V M', '3rd Year', 'CSE', 'A', 'Vivek V', 'PEC Hacks!!', 'internal', '2005-11-20', '00:00:00', '00:00:00', '2005-11-20', '2005-11-20', '', '', 1, 'IoT Lab', 0, 0, '2025-10-13 03:52:56', 'HOD Accepted');

-- --------------------------------------------------------

--
-- Table structure for table `od_team_members`
--

CREATE TABLE `od_team_members` (
  `id` int(11) NOT NULL,
  `od_id` int(11) DEFAULT NULL,
  `member_name` varchar(100) DEFAULT NULL,
  `member_regno` varchar(20) DEFAULT NULL,
  `member_year` varchar(20) DEFAULT NULL,
  `member_department` varchar(50) DEFAULT NULL,
  `member_section` varchar(10) DEFAULT NULL,
  `mentor` varchar(255) DEFAULT NULL,
  `mentor_status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `od_team_members`
--

INSERT INTO `od_team_members` (`id`, `od_id`, `member_name`, `member_regno`, `member_year`, `member_department`, `member_section`, `mentor`, `mentor_status`) VALUES
(65, 57, 'Lalith Krishna V M', '953623104053', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(66, 57, 'Anish Kumar', '953623104007', '3rd Year', 'CSE', 'A', 'Swarna Sudha', 'Rejected'),
(67, 57, 'Diliban S M', '953623104013', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(68, 58, 'Lalith Krishna V M', '953623104053', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(69, 58, 'Anish Kumar', '953623104007', '3rd Year', 'CSE', 'A', 'Swarna Sudha', 'Rejected'),
(70, 58, 'Diliban S M', '953623104013', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(71, 59, 'S Jeyaseelan', '953623104044', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(90, 70, 'Lalith Krishna V M', '953623104053', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(91, 70, 'Jeyseelan S', '953623104044', '3rd Year', 'CSE', 'A', 'Swarna Sudha', 'Accepted'),
(92, 71, 'Lalith Krishna V M', '953623104053', '3rd Year', 'CSE', 'A', 'Vivek V', 'Accepted'),
(93, 71, 'Anish Kumar R', '953623104007', '3rd Year', 'CSE', 'A', 'Swarna Sudha', 'Accepted');

-- --------------------------------------------------------

--
-- Table structure for table `principals`
--

CREATE TABLE `principals` (
  `id` int(11) NOT NULL,
  `register_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `principals`
--

INSERT INTO `principals` (`id`, `register_no`, `name`, `password`) VALUES
(1, '10', 'Sarvesh', '$2y$10$YgdtPeubgG/zpYbeVgJ.mewoOVrIncEAH9mZqZn18ImBcHQqRaCJO');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `register_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(50) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `register_no`, `name`, `password`, `department`, `year`, `section`) VALUES
(4, '953623104053', 'Lalith Krishna V M', '$2y$10$fISjdXD/dKkypVse5EYNTueAnaadh7J8aju0LgUoipwjZC8vpqTQ6', 'CSE', 3, 'A'),
(6, '953623104007', 'Anish Kumar ', '$2y$10$RwNU3te2P0Agy8S69tlHU./6G974hN.QqiILQiKz7q9/56gWipwBa', 'CSE', 3, 'A'),
(8, '953623104013', 'Dilliban S M', '$2y$10$r/WFvQ/qMpGHOwHFrUUuR.M66DgBEzkEAqglisN62ykomR/ZwhtAe', 'CSE', 3, 'A'),
(9, '953623104044', 'Jeyaseelan ', '$2y$10$xGwL5TvIAeBCCgVCrj01yedRP7Mhf02QdkP6fiY5UMEgu3KeYHXdi', 'CSE', 3, 'A'),
(11, '953623105001', 'Abhishit', '$2y$10$Or9AT9z7D8NedV/5SexNHOxEmDDYHLLO0LrvORfPjTilk5a7H/ada', 'ECE', 2, 'B');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cas`
--
ALTER TABLE `cas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- Indexes for table `hods`
--
ALTER TABLE `hods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- Indexes for table `jas`
--
ALTER TABLE `jas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- Indexes for table `lab_form`
--
ALTER TABLE `lab_form`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_technicians`
--
ALTER TABLE `lab_technicians`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- Indexes for table `mentors`
--
ALTER TABLE `mentors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- Indexes for table `od_applications`
--
ALTER TABLE `od_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `od_team_members`
--
ALTER TABLE `od_team_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `od_id` (`od_id`);

--
-- Indexes for table `principals`
--
ALTER TABLE `principals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `register_no` (`register_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cas`
--
ALTER TABLE `cas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hods`
--
ALTER TABLE `hods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jas`
--
ALTER TABLE `jas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lab_form`
--
ALTER TABLE `lab_form`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lab_technicians`
--
ALTER TABLE `lab_technicians`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mentors`
--
ALTER TABLE `mentors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `od_applications`
--
ALTER TABLE `od_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `od_team_members`
--
ALTER TABLE `od_team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `principals`
--
ALTER TABLE `principals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `od_team_members`
--
ALTER TABLE `od_team_members`
  ADD CONSTRAINT `od_team_members_ibfk_1` FOREIGN KEY (`od_id`) REFERENCES `od_applications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
