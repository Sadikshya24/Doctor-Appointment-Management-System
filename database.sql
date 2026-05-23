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
  `status` enum('scheduled','completed','cancelled','reschedule_requested','missed','pending_payment') DEFAULT 'scheduled',
  `requested_date` date DEFAULT NULL,
  `requested_time` time DEFAULT NULL,
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `payment_intent_id` varchar(255) DEFAULT NULL,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `reason` text,
  `amount_paid` varchar(50) DEFAULT NULL,
  `stripe_payment_id` varchar(255) DEFAULT NULL,
  `amount_usd_charged` decimal(10,2) DEFAULT NULL,
  `exchange_rate_used` decimal(10,2) DEFAULT NULL,
  `final_amount_npr` decimal(10,2) DEFAULT NULL,
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

-- Patient health info and file Table 

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

-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info', 'success', 'warning', 'error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
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

-- AI Recommendations Table
CREATE TABLE IF NOT EXISTS `ai_recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `status` enum('pending','accepted','ignored','viewed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

------------------------------------
-- Dummy data for testing all features

-- 1. All Users (Superadmin, Hospitals, Doctors, Patients)
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `status`, `is_verified`) VALUES
(1, 'Super Admin', 'medscape444@gmail.com', '9800000000', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'superadmin', 'active', 1),
(2, 'General Hospital', 'hospitalgeneral@gmail.com', '9811111111', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'hospital', 'active', 1),
(3, 'Lumbini Medical Center', 'hospitallumbini@gmail.com', '9822222222', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'hospital', 'active', 1),
(4, 'Trisha Singh', 'doctortrisha@gmail.com', '9833333333', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'doctor', 'active', 1),
(5, 'Sirisha Shrestha', 'doctorsirisha@gmail.com', '9844444444', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'doctor', 'active', 1),
(6, 'Ayush Karki', 'doctorayush@gmail.com', '9855555555', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'doctor', 'active', 1),
(7, 'Sanjay Shrestha', 'patientsanjay@gmail.com', '9866666666', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'patient', 'active', 1),
(8, 'Priyani Rai', 'patientpriyani@gmail.com', '9877777777', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'patient', 'active', 1),
(9, 'Ayusha Giri', 'patientayusha@gmail.com', '9888888888', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'patient', 'active', 1),
(10, 'Pokhara Health Center', 'hospitalpokhara@gmail.com', '9833333333', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'hospital', 'active', 1),
(11, 'Lalitpur General', 'hospitallalitpur@gmail.com', '9844444444', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'hospital', 'active', 1),
(12, 'Pranati Thapa', 'budhacmallika@gmail.com', '9855555555', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'doctor', 'active', 1),
(13, 'Pending Doctor', 'pendingdoc@example.com', '9866666666', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'doctor', 'active', 1),
(14, 'Kshitiz Shah', 'patientkshitiz@gmail.com', '9877777777', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'patient', 'active', 1),
(15, 'Crystal Lama', 'jesanglimbu479@gmail.com', '9888888888', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'patient', 'active', 1);

-- 2. Hospitals (linking to user IDs)
INSERT INTO `hospitals` (`id`, `user_id`, `province`, `city`, `location`, `description`) VALUES
(1, 2, 'Bagmati', 'Kathmandu', 'New Baneshwor, Kathmandu', 'Leading healthcare provider in the capital.'),
(2, 3, 'Lumbini', 'Butwal', 'Butwal-1, Rupandehi', 'Specialized medical center for all health needs.'),
(3, 10, 'Gandaki', 'Pokhara', 'Lakeside, Pokhara', 'Modern hospital in Pokhara.'),
(4, 11, 'Bagmati', 'Lalitpur', 'Patana, Lalitpur', 'Well‑established clinic in Patan.');

-- 3. Doctors (linking to users and hospitals)
INSERT INTO `doctors` (`id`, `user_id`, `hospital_id`, `nmc_number`, `status`, `speciality`, `description`, `qualification`, `experience_years`, `available_days`, `start_time`, `end_time`, `cv_path`) VALUES
(1, 4, 1, '12345', 'approved', 'Cardiologist', 'Experienced heart specialist.', 'MD, Cardiology', 10, 'Sun,Mon,Tue,Wed,Thu,Fri', '10:00:00', '16:00:00', NULL),
(2, 5, 1, '67890', 'approved', 'Neurologist', 'Specialized in brain disorders.', 'MD, Neurology', 8, 'Mon,Tue,Wed,Thu', '11:00:00', '17:00:00', NULL),
(3, 6, 2, '11223', 'approved', 'Orthopedist', 'Bone and joint specialist.', 'MS, Orthopedics', 15, 'Mon,Wed,Fri', '09:00:00', '13:00:00', NULL),
(4, 12, 1, '55555', 'approved', 'Dermatologist', 'Skin specialist.', 'MD, Dermatology', 5, 'Mon,Tue,Wed,Thu,Fri', '09:00:00', '15:00:00', 'dummyCV.pdf'),
(5, 13, 2, '66666', 'pending', 'Pediatrician', 'Child health.', 'MD, Pediatrics', 3, 'Mon,Wed,Fri', '10:00:00', '16:00:00', 'dummyCV.pdf');

-- 4. Appointments
INSERT INTO `appointments` (`id`, `booking_id`, `patient_id`, `doctor_id`, `hospital_id`, `appointment_date`, `appointment_time`, `status`, `payment_status`, `reason`) VALUES
(1, 'BK-1001', 7, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00', 'scheduled', 'paid', 'Routine heart checkup.'),
(2, 'BK-1002', 8, 2, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', 'scheduled', 'paid', 'Chronic headaches.'),
(3, 'BK-1003', 9, 3, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'completed', 'paid', 'Follow-up for knee pain.'),
(4, 'BK-1004', 7, 2, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', 'scheduled', 'paid', 'Neurological assessment.'),
(5, 'BK-1005', 8, 3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00:00', 'scheduled', 'paid', 'Orthopedic consultation.'),
(6, 'BK-2001', 14, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:00:00', 'cancelled', 'refunded', 'Routine checkup - cancelled.'),
(7, 'BK-2002', 14, 2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:00:00', 'missed', 'pending', 'Missed appointment.'),
(8, 'BK-2003', 14, 3, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:00:00', 'completed', 'paid', 'Completed treatment.'),
(9, 'BK-2004', 14, 4, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '12:00:00', 'scheduled', 'paid', 'Scheduled dermatology consult.'),
(10, 'BK-2005', 14, 4, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '13:00:00', 'scheduled', 'pending', 'Scheduled dermatology consult unpaid.'),
(11, 'BK-2006', 15, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:30:00', 'cancelled', 'refunded', 'Routine checkup - cancelled.'),
(12, 'BK-2007', 15, 2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:30:00', 'missed', 'pending', 'Missed appointment.'),
(13, 'BK-2008', 15, 3, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:30:00', 'completed', 'paid', 'Completed orthopedics.'),
(14, 'BK-2009', 15, 4, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '12:30:00', 'scheduled', 'paid', 'Scheduled dermatology consult.'),
(15, 'BK-2010', 15, 4, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '13:30:00', 'scheduled', 'pending', 'Scheduled dermatology consult unpaid.');

-- 5. Reports
INSERT INTO `reports` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `details`, `diagnosis`, `report_details`, `prescription`) VALUES
(1, 3, 9, 3, 'Patient reports improvement in knee movement.', 'Recovering Knee Sprain', 'Joint stability is good. Inflammation reduced.', 'Physiotherapy twice a week, Naproxen 250mg daily.'),
(2, 8, 14, 3, 'Patient recovered well.', 'Recovered Condition', 'All metrics normal.', 'Continue medication and follow-up after 2 weeks.'),
(3, 13, 15, 3, 'Orthopedic follow-up.', 'Improved Mobility', 'Knee stability improved.', 'Physiotherapy recommended and follow-up after 3 weeks.');

-- 6. Logs
INSERT INTO `logs` (`action`, `user`) VALUES
('System initialized with dummy data', 'system');
