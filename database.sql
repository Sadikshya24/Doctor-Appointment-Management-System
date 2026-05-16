CREATE DATABASE IF NOT EXISTS `doc_appoint_portal`;
USE `doc_appoint_portal`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `profile_photo` varchar(255) DEFAULT 'assets/img/default.jpeg',
  `phone` varchar(15) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('patient','doctor','hospital','superadmin') NOT NULL DEFAULT 'patient',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `google_id` varchar(100) DEFAULT NULL UNIQUE,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_token_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hospitals Table
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctors Table
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `nmc_number` varchar(50) DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `speciality` varchar(100) NOT NULL,
  `description` text,
  `qualification` varchar(255) DEFAULT 'MBBS',
  `experience_years` int(11) DEFAULT 0,
  `available_days` varchar(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  `start_time` time DEFAULT '09:00:00',
  `end_time` time DEFAULT '17:00:00',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointments Table
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` varchar(50) UNIQUE DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled','reschedule_requested','missed','pending_payment','refunded') DEFAULT 'scheduled',
  `requested_date` date DEFAULT NULL,
  `requested_time` time DEFAULT NULL,
  `reason` text,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `payment_intent_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','paid','refunded','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reports Table
CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `details` text NOT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `report_details` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Report History Table for Versioning
CREATE TABLE IF NOT EXISTS `report_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `diagnosis` varchar(255) NOT NULL,
  `report_details` text NOT NULL,
  `prescription` text NOT NULL,
  `version_number` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password Resets Table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- DUMMY DATA FOR TESTING
-- --------------------------------------------------------

-- Insert Superadmin (Password: @Password123)
INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `status`, `is_verified`) VALUES 
('Super Admin', 'medscape444@gmail.com', '9800000000', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'superadmin', 'active', 1);

-- Insert Hospital Users
INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES 
('City General Hospital', 'hospitalcity@gmail.com', '9811111111', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'hospital', 'active'),
('Lumbini Medical Center', 'hospitallumbini@gmail.com', '9822222222', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'hospital', 'active');

-- Insert Hospital Details
INSERT INTO `hospitals` (`user_id`, `province`, `city`, `location`, `description`) VALUES 
(2, 'Bagmati', 'Kathmandu', 'New Baneshwor, Kathmandu', 'Leading healthcare provider in the capital.'),
(3, 'Lumbini', 'Butwal', 'Butwal-1, Rupandehi', 'Specialized medical center for all your health needs.');

-- Insert Doctor Users
INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES 
('Ramesh Thapa', 'doctorramesh@gmail.com', '9833333333', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'doctor', 'active'),
('Sita Kumari', 'doctorsita@gmail.com', '9844444444', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'doctor', 'active'),
('Hari Prasad', 'doctorhari@gmail.com', '9855555555', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'doctor', 'active');

-- Insert Doctor Details
INSERT INTO `doctors` (`user_id`, `hospital_id`, `nmc_number`, `status`, `speciality`, `description`, `qualification`, `experience_years`, `available_days`, `start_time`, `end_time`) VALUES 
(4, 1, 'NMC-12345', 'approved', 'Cardiologist', 'Experienced heart specialist.', 'MD, Cardiology', 10, 'Sun,Mon,Tue,Wed,Thu,Fri', '10:00:00', '16:00:00'),
(5, 1, 'NMC-67890', 'approved', 'Neurologist', 'Specialized in brain and nerve disorders.', 'MD, Neurology', 8, 'Mon,Tue,Wed,Thu', '11:00:00', '17:00:00'),
(6, 2, 'NMC-11223', 'approved', 'Orthopedist', 'Bone and joint specialist.', 'MS, Orthopedics', 15, 'Mon,Wed,Fri', '09:00:00', '13:00:00');

-- Insert Patient Users
INSERT INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `status`) VALUES 
('Ram Shrestha', 'patientram@gmail.com', '9866666666', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'patient', 'active'),
('Geeta Rai', 'patientgeeta@gmail.com', '9877777777', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'patient', 'active'),
('Hari Shrestha', 'patienthari@gmail.com', '9888888888', '$2y$10$Pe5ICHxa9MtFP8/krNaRtu7jAulOQLByKtnmAMNth6Vt4Zg.VJUs6', 'patient', 'active');

-- Insert Sample Appointments
INSERT INTO `appointments` (`booking_id`, `patient_id`, `doctor_id`, `hospital_id`, `appointment_date`, `appointment_time`, `status`, `reason`) VALUES 
('BK-1001', 7, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00', 'scheduled', 'Routine heart checkup.'),
('BK-1002', 8, 2, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', 'scheduled', 'Chronic headaches.'),
('BK-1003', 9, 3, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'completed', 'Follow-up for knee pain.'),
('BK-1004', 7, 2, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', 'scheduled', 'General neurological assessment.'),
('BK-1005', 8, 3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00:00', 'scheduled', 'Orthopedic consultation.');

-- Insert Sample Reports
INSERT INTO `reports` (`appointment_id`, `patient_id`, `doctor_id`, `details`, `diagnosis`, `report_details`, `prescription`) VALUES 
(3, 9, 3, 'Patient reports improvement in knee movement.', 'Recovering Knee Sprain', 'Joint stability is good. Inflammation has reduced.', 'Physiotherapy sessions: twice a week, Naproxen 250mg once daily.');

-- --------------------------------------------------------
-- PATIENT HEALTH INFO & FILES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `patient_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `height` varchar(50) DEFAULT NULL,
  `weight` int(11) DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patient_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Logs Table
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `user` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;