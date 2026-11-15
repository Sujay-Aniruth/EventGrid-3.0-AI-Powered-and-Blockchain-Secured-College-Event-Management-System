CREATE DATABASE IF NOT EXISTS eventgrid_3_0 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventgrid_3_0;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  reg_no VARCHAR(40) NULL UNIQUE,
  dob DATE NULL,
  department VARCHAR(100) NULL,
  phone CHAR(10) NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','student') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE scheduler_state (
  id TINYINT UNSIGNED PRIMARY KEY,
  reminders_checked_at DATETIME NULL
) ENGINE=InnoDB;
INSERT INTO scheduler_state (id,reminders_checked_at) VALUES (1,NULL);

CREATE TABLE events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(80) NOT NULL,
  venue VARCHAR(180) NOT NULL,
  event_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  reg_deadline DATETIME NOT NULL,
  max_participants INT UNSIGNED NOT NULL,
  poster VARCHAR(255) NULL,
  status ENUM('Open','Closed','Cancelled') NOT NULL DEFAULT 'Open',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_events_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_events_date_status (event_date, status),
  INDEX idx_events_category (category)
) ENGINE=InnoDB;

CREATE TABLE registrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  reg_no VARCHAR(40) NOT NULL,
  dob DATE NOT NULL,
  department VARCHAR(100) NOT NULL,
  phone CHAR(10) NOT NULL,
  email VARCHAR(190) NOT NULL,
  study_year TINYINT UNSIGNED NULL,
  section VARCHAR(30) NULL,
  team_name VARCHAR(120) NULL,
  tshirt_size VARCHAR(10) NULL,
  diet VARCHAR(100) NULL,
  emergency_contact VARCHAR(30) NULL,
  special_requirements TEXT NULL,
  consent TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('Pending','Verified','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_registration_student_event (user_id, event_id),
  INDEX idx_registrations_event_status (event_id, status),
  CONSTRAINT fk_registrations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_registrations_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NULL,
  type ENUM('reminder','update','cancel','status','system') NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user_read (user_id, is_read, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reminder_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reminder_event_user (event_id, user_id),
  CONSTRAINT fk_reminders_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_reminders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE blockchain_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id BIGINT UNSIGNED NULL,
  data_hash CHAR(64) NOT NULL,
  previous_hash CHAR(64) NOT NULL,
  block_hash CHAR(64) NOT NULL,
  nonce BIGINT UNSIGNED NOT NULL DEFAULT 0,
  data_snapshot JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ledger_registration (registration_id, id),
  UNIQUE KEY uq_ledger_block_hash (block_hash),
  CONSTRAINT fk_ledger_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE integrity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id BIGINT UNSIGNED NULL,
  block_id BIGINT UNSIGNED NULL,
  expected_hash CHAR(64) NULL,
  actual_hash CHAR(64) NULL,
  result ENUM('Secure','Tampered') NOT NULL,
  status ENUM('Open','Investigating','Resolved','False Alarm') NOT NULL DEFAULT 'Open',
  admin_notes TEXT NULL,
  checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_integrity_status (result, status, checked_at),
  CONSTRAINT fk_integrity_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL,
  CONSTRAINT fk_integrity_block FOREIGN KEY (block_id) REFERENCES blockchain_ledger(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE chat_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  state VARCHAR(40) NOT NULL DEFAULT 'idle',
  context_json JSON NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (name,reg_no,dob,department,phone,email,password_hash,role) VALUES
('EventGrid Administrator',NULL,NULL,NULL,NULL,'Admin@eventgrid.in','$2y$10$bxGTbe760PPVHxA.gLhnfeGyyxBY2D4uMknEpXG7iwCKh7C.o.O7K','admin'),
('Aarav Mehta','CS-2026-001','2005-01-10','Computer Science','9876543210','aarav@example.edu','$2y$10$bxGTbe760PPVHxA.gLhnfeGyyxBY2D4uMknEpXG7iwCKh7C.o.O7K','student'),
('Diya Sharma','IT-2026-014','2004-08-22','Information Technology','9876543211','diya@example.edu','$2y$10$bxGTbe760PPVHxA.gLhnfeGyyxBY2D4uMknEpXG7iwCKh7C.o.O7K','student'),
('Kabir Rao','EC-2026-033','2005-03-15','Electronics','9876543212','kabir@example.edu','$2y$10$bxGTbe760PPVHxA.gLhnfeGyyxBY2D4uMknEpXG7iwCKh7C.o.O7K','student');

INSERT INTO events (title,description,category,venue,event_date,start_time,end_time,reg_deadline,max_participants,status,created_by) VALUES
('Campus Innovation Summit','A day of ideas, prototypes, and student-led technology talks.','Technology','Main Auditorium',DATE_ADD(CURDATE(), INTERVAL 12 DAY),'09:00','16:00',DATE_ADD(NOW(), INTERVAL 10 DAY),180,'Open',1),
('Spring Music Night','Live performances from campus artists and bands.','Culture','Open Air Theatre',DATE_ADD(CURDATE(), INTERVAL 18 DAY),'18:00','21:30',DATE_ADD(NOW(), INTERVAL 16 DAY),300,'Open',1),
('Inter-College Hackathon','Build useful software with a diverse team in 24 hours.','Technology','Innovation Lab',DATE_ADD(CURDATE(), INTERVAL 25 DAY),'10:00','18:00',DATE_ADD(NOW(), INTERVAL 20 DAY),120,'Open',1),
('Wellness and Mindfulness','Guided mindfulness sessions and practical wellbeing workshops.','Wellness','Seminar Hall B',DATE_ADD(CURDATE(), INTERVAL 7 DAY),'11:00','14:00',DATE_ADD(NOW(), INTERVAL 5 DAY),90,'Open',1),
('Entrepreneurship Forum','Meet founders and learn how early-stage ventures grow.','Business','Lecture Theatre 2',DATE_ADD(CURDATE(), INTERVAL 32 DAY),'13:00','17:00',DATE_ADD(NOW(), INTERVAL 28 DAY),150,'Open',1);

INSERT INTO blockchain_ledger (registration_id,data_hash,previous_hash,block_hash,nonce,data_snapshot,created_at)
VALUES (NULL, SHA2('eventgrid-genesis-v1',256), REPEAT('0',64), SHA2(CONCAT('1',SHA2('eventgrid-genesis-v1',256),REPEAT('0',64),'2025-01-01 00:00:00',0),256), 0, JSON_OBJECT('type','genesis'), '2025-01-01 00:00:00');
