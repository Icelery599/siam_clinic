-- ============================================
-- SIAM Clinic Management System - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS siam_clinic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siam_clinic;

-- ---------------------------------
-- users: two roles — admin and patient
-- ---------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','patient') NOT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------
-- patients: extended profile for role=patient users
-- ---------------------------------
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    dob DATE DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------
-- departments: includes a keyword list SIAM matches patient symptoms against
-- ---------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    keywords TEXT COMMENT 'Comma-separated symptom/keyword list used by SIAM matching',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------
-- doctors: managed by admin as records (not login accounts in this build)
-- ---------------------------------
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    department_id INT DEFAULT NULL,
    specialization VARCHAR(150) DEFAULT NULL,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    bio TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------
-- clinic_schedule: doctor's weekly working hours
-- ---------------------------------
CREATE TABLE clinic_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------
-- appointments
-- ---------------------------------
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('Pending','Approved','Rejected','Completed','Cancelled') DEFAULT 'Pending',
    reason TEXT,
    siam_symptoms TEXT COMMENT 'Raw symptom text the patient typed into SIAM, if used',
    siam_suggested TINYINT(1) DEFAULT 0 COMMENT '1 if this booking followed a SIAM suggestion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------
-- siam_assessments: log of every SIAM intake, for admin visibility/tuning
-- ---------------------------------
CREATE TABLE siam_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT DEFAULT NULL,
    input_text TEXT NOT NULL,
    matched_department_id INT DEFAULT NULL,
    matched_keywords TEXT,
    confidence VARCHAR(20) DEFAULT NULL COMMENT 'low / medium / high, based on keyword hit count',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
    FOREIGN KEY (matched_department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------
-- audit_logs
-- ---------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------
-- Seed data
-- ---------------------------------

INSERT INTO departments (name, description, keywords) VALUES
('General Medicine', 'General health checkups and common illness treatment',
 'fever,cough,cold,flu,headache,body ache,tired,fatigue,weakness,sore throat,nausea,vomiting,diarrhea,checkup,general'),
('Pediatrics', 'Child healthcare from infancy through adolescence',
 'child,baby,infant,kid,toddler,vaccination,immunization,growth,fever in child,newborn'),
('Dental', 'Dental care and treatment',
 'tooth,teeth,toothache,gum,cavity,dental,mouth pain,braces,jaw pain,bleeding gums'),
('Eye Clinic', 'Eye examination and treatment',
 'eye,vision,blurry,glasses,eyesight,red eye,eye pain,itchy eyes,eye infection,sight'),
('Cardiology', 'Heart and cardiovascular care',
 'chest pain,heart,palpitation,blood pressure,hypertension,shortness of breath,dizziness,heart racing'),
('Orthopedics', 'Bone, joint, and muscle care',
 'bone,joint,fracture,back pain,knee pain,muscle pain,sprain,arthritis,swelling,injury'),
('Laboratory', 'Diagnostic testing services',
 'blood test,lab test,x-ray,scan,diagnosis,test results'),
('Pharmacy', 'Medicine dispensing',
 'medicine,prescription refill,drugs,pharmacy');

-- Default admin account.
--   Email:    admin@siamclinic.com
--   Password: Admin@123
INSERT INTO users (name, email, password, role) VALUES
('System Administrator', 'admin@siamclinic.com', '$2b$12$2QlsmopPT.a2gSM9dGninuqtf3QVwL6d7bQobNuso1bH/w0lXSF6G', 'admin');

-- CHANGE THIS PASSWORD after your first login (Change Password page).
-- To generate a fresh hash for a different password, run:
--   php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"

-- Sample doctors so the booking flow has data to work with immediately
INSERT INTO doctors (name, department_id, specialization, consultation_fee, phone, email, status) VALUES
('Amara Chen', 1, 'General Practitioner', 5000.00, '0800-000-0001', 'amara.chen@siamclinic.com', 'active'),
('Bello Okafor', 2, 'Pediatrician', 6000.00, '0800-000-0002', 'bello.okafor@siamclinic.com', 'active'),
('Grace Adeyemi', 3, 'Dental Surgeon', 7000.00, '0800-000-0003', 'grace.adeyemi@siamclinic.com', 'active'),
('Wale Ibrahim', 5, 'Cardiologist', 9000.00, '0800-000-0004', 'wale.ibrahim@siamclinic.com', 'active');

INSERT INTO clinic_schedule (doctor_id, day, start_time, end_time) VALUES
(1,'Monday','09:00','17:00'),(1,'Wednesday','09:00','17:00'),(1,'Friday','09:00','13:00'),
(2,'Tuesday','09:00','16:00'),(2,'Thursday','09:00','16:00'),
(3,'Monday','10:00','15:00'),(3,'Thursday','10:00','15:00'),
(4,'Wednesday','09:00','12:00'),(4,'Friday','09:00','12:00');
