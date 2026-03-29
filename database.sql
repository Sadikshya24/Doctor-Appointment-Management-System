CREATE DATABASE IF NOT EXISTS `healthcare_portal`;
USE `healthcare_portal`;

-- Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `profile_photo` varchar(255) DEFAULT 'assets/img/default.png',
  `phone` varchar(15) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('patient','doctor','hospital','superadmin') NOT NULL DEFAULT 'patient',
  `google_id` varchar(100) DEFAULT NULL UNIQUE,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hospitals Table
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
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
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `reason` text,
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
  `details` text DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `report_details` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password Resets Table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patient Info Table
CREATE TABLE IF NOT EXISTS `patient_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `height` varchar(50) DEFAULT NULL,
  `weight` int(11) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patient Files Table
CREATE TABLE IF NOT EXISTS `patient_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- DUMMY DATA
-- ==========================================

-- Note: Because passwords for these users are not set correctly for login or google_id, 
-- they cannot exactly login unless via their correct credentials, but they can act as backend items.

-- Inserting dummy Hospitals into users
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `role`) VALUES 
(1001, 'City General Hospital', 'contact@citygeneral.com', 'hospital'),
(1002, 'Valley Health Care', 'info@valleyhealth.com', 'hospital'),
(1003, 'Sunrise Medical Center', 'admin@sunrisemedical.com', 'hospital');

-- Inserting actual hospital details
INSERT IGNORE INTO `hospitals` (`id`, `user_id`, `location`, `description`) VALUES 
(1, 1001, 'Kathmandu', 'A leading general hospital providing all round medical services.'),
(2, 1002, 'Lalitpur', 'Specialized care facility focusing on maternal and childcare.'),
(3, 1003, 'Bhaktapur', 'Premium tertiary care and advanced surgical center.');

-- Inserting dummy Doctors into users
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `role`) VALUES 
(2001, 'Aary Sharma', 'aary@gmail.com', 'doctor'),
(2002, 'Bimal Baskora', 'bimal@gmail.com', 'doctor'),
(2003, 'Rajan Joshi', 'rajan@gmail.com', 'doctor'),
(2004, 'Nima Sherpa', 'nima@gmail.com', 'doctor');

-- Inserting actual doctors
INSERT IGNORE INTO `doctors` (`id`, `user_id`, `hospital_id`, `speciality`, `description`) VALUES 
(1, 2001, 1, 'Cardiologist', '10+ years of experience in heart care.'),
(2, 2002, 1, 'Dermatologist', 'Expert in skincare and cosmetic procedures.'),
(3, 2003, 2, 'Pediatrician', 'Child healthcare specialist.'),
(4, 2004, 3, 'Neurologist', 'Specialized in brain and nervous system functioning.');
(4, 2004, 3, 'Neurologist', 'Specialized in brain and nervous system functioning.');
