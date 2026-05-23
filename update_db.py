import re

with open('database.sql', 'r') as f:
    content = f.read()

# Find the start of the dummy data
start_marker = "------------------------------------\n-- Dummy data for testing all features"
idx = content.find(start_marker)

if idx != -1:
    new_dummy_data = """------------------------------------
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
(15, 'Crystal Lama', 'patientcrystal@gmail.com', '9888888888', '$2y$10$K1Vx6K9OaJX/9kE8aBZh4eYVYx7bVdK6hO9c/6Qj8sU5T6JvE1yWu', 'patient', 'active', 1);

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
(4, 12, 1, 'NMC-55555', 'approved', 'Dermatologist', 'Skin specialist.', 'MD, Dermatology', 5, 'Mon,Tue,Wed,Thu,Fri', '09:00:00', '15:00:00', 'dummyCV.pdf'),
(5, 13, 2, 'NMC-66666', 'pending', 'Pediatrician', 'Child health.', 'MD, Pediatrics', 3, 'Mon,Wed,Fri', '10:00:00', '16:00:00', 'dummyCV.pdf');

-- 4. Appointments
INSERT INTO `appointments` (`id`, `booking_id`, `patient_id`, `doctor_id`, `hospital_id`, `appointment_date`, `appointment_time`, `status`, `payment_status`, `reason`) VALUES
(1, 'BK-1001', 7, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30:00', 'scheduled', 'paid', 'Routine heart checkup.'),
(2, 'BK-1002', 8, 2, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', 'scheduled', 'paid', 'Chronic headaches.'),
(3, 'BK-1003', 9, 3, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:30:00', 'completed', 'paid', 'Follow-up for knee pain.'),
(4, 'BK-1004', 7, 2, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '14:00:00', 'scheduled', 'paid', 'Neurological assessment.'),
(5, 'BK-1005', 8, 3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00:00', 'scheduled', 'paid', 'Orthopedic consultation.'),
(6, 'BK-2001', 14, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:00:00', 'cancelled', 'cancelled', 'Routine checkup - cancelled.'),
(7, 'BK-2002', 14, 2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:00:00', 'missed', 'missed', 'Missed appointment.'),
(8, 'BK-2003', 14, 3, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:00:00', 'completed', 'paid', 'Completed treatment.'),
(9, 'BK-2004', 14, 4, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '12:00:00', 'scheduled', 'paid', 'Scheduled dermatology consult.'),
(10, 'BK-2005', 14, 4, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '13:00:00', 'scheduled', 'pending', 'Scheduled dermatology consult unpaid.'),
(11, 'BK-2006', 15, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:30:00', 'cancelled', 'cancelled', 'Routine checkup - cancelled.'),
(12, 'BK-2007', 15, 2, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:30:00', 'missed', 'missed', 'Missed appointment.'),
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
"""
    new_content = content[:idx] + new_dummy_data
    with open('database.sql', 'w') as f:
        f.write(new_content)
    print("Database SQL updated successfully.")
