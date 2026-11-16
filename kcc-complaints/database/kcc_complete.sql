-- ============================================================
-- KCC Complaint Management System — COMPLETE DATABASE SETUP
-- Esther Maina, Zetech University
-- 
-- HOW TO USE:
-- 1. Open phpMyAdmin: http://localhost/phpmyadmin
-- 2. Click New → type kcc_complaints → click Create
-- 3. Click kcc_complaints → Import tab → Choose this file → Go
-- DONE. All tables + data + accounts are created automatically.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── DROP EXISTING TABLES (safe re-run) ───────────────────────
DROP TABLE IF EXISTS escalations;
DROP TABLE IF EXISTS complaint_updates;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;

SET FOREIGN_KEY_CHECKS = 1;

-- ── 1. DEPARTMENTS ────────────────────────────────────────────
CREATE TABLE departments (
    dept_id     INT           PRIMARY KEY AUTO_INCREMENT,
    dept_name   VARCHAR(100)  NOT NULL UNIQUE,
    description TEXT,
    is_active   BOOLEAN       DEFAULT TRUE,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 2. CATEGORIES ─────────────────────────────────────────────
CREATE TABLE categories (
    category_id     INT          PRIMARY KEY AUTO_INCREMENT,
    category_name   VARCHAR(80)  NOT NULL UNIQUE,
    dept_id         INT          NOT NULL,
    resolution_days INT          DEFAULT 14,
    description     TEXT,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 3. USERS ──────────────────────────────────────────────────
CREATE TABLE users (
    user_id       INT           PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    phone         VARCHAR(20),
    password_hash TEXT          NOT NULL,
    role          ENUM('citizen','officer','supervisor','admin') NOT NULL DEFAULT 'citizen',
    dept_id       INT,
    is_active     BOOLEAN       DEFAULT TRUE,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. COMPLAINTS ─────────────────────────────────────────────
CREATE TABLE complaints (
    complaint_id      INT           PRIMARY KEY AUTO_INCREMENT,
    reference_no      VARCHAR(20)   NOT NULL UNIQUE,
    citizen_id        INT           NULL,
    citizen_name      VARCHAR(100)  NOT NULL,
    citizen_contact   VARCHAR(150)  NOT NULL,
    category_id       INT           NOT NULL,
    dept_id           INT           NOT NULL,
    location          VARCHAR(200)  NOT NULL,
    description       TEXT          NOT NULL,
    status            ENUM('submitted','under_review','in_progress','resolved','closed')
                                    NOT NULL DEFAULT 'submitted',
    is_duplicate_flag BOOLEAN       DEFAULT FALSE,
    submitted_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    last_updated_at   TIMESTAMP     NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (dept_id)     REFERENCES departments(dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. COMPLAINT UPDATES (audit trail) ────────────────────────
CREATE TABLE complaint_updates (
    update_id    INT       PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT       NOT NULL,
    officer_id   INT       NOT NULL,
    new_status   ENUM('submitted','under_review','in_progress','resolved','closed') NOT NULL,
    notes        TEXT      NOT NULL,
    escalated    BOOLEAN   DEFAULT FALSE,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id),
    FOREIGN KEY (officer_id)   REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. ESCALATIONS ────────────────────────────────────────────
CREATE TABLE escalations (
    escalation_id       INT       PRIMARY KEY AUTO_INCREMENT,
    complaint_id        INT       NOT NULL,
    notified_supervisor INT       NOT NULL,
    days_overdue        INT       NOT NULL,
    resolved            BOOLEAN   DEFAULT FALSE,
    escalated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id)        REFERENCES complaints(complaint_id),
    FOREIGN KEY (notified_supervisor) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ════════════════════════════════════════════════════════════
-- SEED DATA
-- ════════════════════════════════════════════════════════════

-- Departments
INSERT INTO departments (dept_name, description) VALUES
('Roads & Infrastructure', 'Road repairs, potholes, street lighting, and infrastructure maintenance'),
('Water & Sanitation',     'Water supply, shortages, sewage, drainage, and waste collection'),
('Land & Environment',     'Land disputes, boundary conflicts, environmental degradation, and planning'),
('Health Services',        'County health facility concerns and public health issues');

-- Categories with SLA resolution days
INSERT INTO categories (category_name, dept_id, resolution_days, description) VALUES
('Road Damage',       1, 14, 'Potholes, broken tarmac, road flooding, road collapse'),
('Street Lighting',   1, 10, 'Broken or missing street lights on public roads'),
('Water Shortage',    2,  7, 'No water supply to residential or commercial areas'),
('Sewage / Drainage', 2, 10, 'Blocked drains, sewage overflow, flooding from poor drainage'),
('Waste Collection',  2, 10, 'Uncollected garbage, illegal dumping sites'),
('Land Dispute',      3, 21, 'Boundary conflicts, encroachment, illegal land grabbing'),
('Tree / Environment',3, 14, 'Fallen trees, illegal logging, air or water pollution'),
('Health Facility',   4, 14, 'Concerns about county clinics, dispensaries, or health officers');

-- ── STAFF ACCOUNTS ────────────────────────────────────────────
-- All accounts use password: Admin@1234
-- Hash generated with: password_hash('Admin@1234', PASSWORD_BCRYPT)
SET @hash = '$2y$10$glwPfws/kDvpnhNbkxDtUugzKh5ckdduKmnpU..9uSFv7lO9H7Dd.';

INSERT INTO users (name, email, phone, password_hash, role, dept_id) VALUES
('System Admin',        'admin@kiambu.go.ke',    '0700000001', @hash, 'admin',      NULL),
('Roads Supervisor',    'roads@kiambu.go.ke',    '0700000002', @hash, 'supervisor', 1),
('Water Supervisor',    'water@kiambu.go.ke',    '0700000003', @hash, 'supervisor', 2),
('Land Supervisor',     'land@kiambu.go.ke',     '0700000004', @hash, 'supervisor', 3),
('Roads Officer A',     'officer1@kiambu.go.ke', '0700000005', @hash, 'officer',    1),
('Roads Officer B',     'officer2@kiambu.go.ke', '0700000006', @hash, 'officer',    1),
('Water Officer A',     'officer3@kiambu.go.ke', '0700000007', @hash, 'officer',    2),
('Land Officer A',      'officer4@kiambu.go.ke', '0700000008', @hash, 'officer',    3),
('Health Officer A',    'officer5@kiambu.go.ke', '0700000009', @hash, 'officer',    4);

-- ── SAMPLE COMPLAINTS (for demonstration) ─────────────────────
INSERT INTO complaints
(reference_no, citizen_name, citizen_contact, category_id, dept_id,
 location, description, status, is_duplicate_flag, submitted_at) VALUES
('KCC-2026-00001','Jane Wanjiru Kamau','0712345678',1,1,
 'Githunguri Junction, Kiambu County',
 'Large pothole at Githunguri junction has been there for 6 weeks. Two boda boda riders have been injured. The hole is approximately 1.5 metres wide and needs urgent repair.',
 'in_progress', 0, '2026-09-12 09:14:00'),

('KCC-2026-00002','Ali Hassan Ouma','ali@gmail.com',2,1,
 'Kenyatta Avenue, Thika Town',
 'Street lights along Kenyatta Avenue have been off for two weeks. The area is very dark at night and residents feel unsafe walking home.',
 'under_review', 0, '2026-09-13 14:20:00'),

('KCC-2026-00003','Mary Njoki Mwangi','0722987654',3,2,
 'Ruiru West Estate',
 'No water supply to Ruiru West Estate for 5 days. Residents are buying water from vendors at very high prices. This is affecting elderly people and families with young children.',
 'resolved', 0, '2026-09-10 07:30:00'),

('KCC-2026-00004','Peter Mwangi Njoroge','0733111222',1,1,
 'Limuru Road near ACK School',
 'Potholes on Limuru Road near ACK School are getting worse after the rains. Several vehicles have been damaged. Road needs urgent patching.',
 'submitted', 1, '2026-09-18 11:45:00'),

('KCC-2026-00005','Sarah Akinyi Odhiambo','sarah@yahoo.com',2,1,
 'Kikuyu-Thogoto Road',
 'No street lighting on the entire Kikuyu-Thogoto Road stretch of about 3km. Very dangerous especially for school children walking in the evening.',
 'in_progress', 0, '2026-09-05 16:00:00'),

('KCC-2026-00006','David Kiprotich Rono','0700456789',5,2,
 'Kiambaa Market Area',
 'Garbage has not been collected from Kiambaa market for 3 weeks. The waste is piling up and there is a terrible smell. Health risk to traders and customers.',
 'submitted', 0, '2026-09-09 08:15:00'),

('KCC-2026-00007','Grace Muthoni Wambua','0711223344',6,3,
 'Lari Sub-County, Githunguri',
 'Neighbour has encroached on my land by building a fence 2 metres into my property. I have a title deed and the boundary was officially surveyed. Need urgent intervention.',
 'under_review', 0, '2026-09-15 10:00:00'),

('KCC-2026-00008','James Otieno Owino','0722334455',4,2,
 'Ruaka Town Centre',
 'Sewage has been overflowing from the main drain near Ruaka town centre for over a week. The whole area smells and businesses are losing customers.',
 'in_progress', 0, '2026-09-14 13:30:00'),

('KCC-2026-00009','Fatuma Abdi Hassan','0733445566',8,4,
 'Gatundu South Health Centre',
 'Gatundu South Health Centre has been without clean water for 2 weeks. Patients are being turned away and nurses cannot wash hands properly. Urgent attention needed.',
 'submitted', 0, '2026-09-20 09:00:00'),

('KCC-2026-00010','Joseph Kamau Mwangi','0744556677',3,2,
 'Thika West, Pipeline Estate',
 'Water shortage in Pipeline Estate Thika West. No water since last Monday. Same issue reported last month but was not fully fixed.',
 'resolved', 0, '2026-09-02 07:44:00');

-- Sample complaint updates (audit trail for demo complaints)
INSERT INTO complaint_updates (complaint_id, officer_id, new_status, notes, updated_at) VALUES
(1, 5, 'under_review', 'Complaint reviewed. Site inspection scheduled for tomorrow.', '2026-09-14 10:30:00'),
(1, 5, 'in_progress',  'Road repair crew dispatched to Githunguri junction. Pothole repair scheduled for 22 Sep 2026.', '2026-09-19 14:05:00'),
(2, 5, 'under_review', 'Fault reported to Kenya Power. Awaiting their maintenance team schedule.', '2026-09-15 09:00:00'),
(3, 7, 'under_review', 'Confirmed water shortage affecting Ruiru West Estate — main valve issue identified.', '2026-09-11 08:20:00'),
(3, 7, 'in_progress',  'Water board engineer on site. Main valve replaced.', '2026-09-12 11:00:00'),
(3, 7, 'resolved',     'Water supply fully restored to Ruiru West Estate. Resident confirmed receipt. Follow-up call completed.', '2026-09-13 16:30:00'),
(5, 5, 'under_review', 'Fault traced to substation failure. Kenya Power notified with priority ticket.', '2026-09-07 10:00:00'),
(5, 5, 'in_progress',  'Kenya Power confirmed repair scheduled for 25 Sep 2026.', '2026-09-10 14:00:00'),
(8, 7, 'under_review', 'Site inspection done. Main sewer blocked by construction debris.', '2026-09-15 10:30:00'),
(8, 7, 'in_progress',  'Excavation team deployed. Blockage clearance in progress.', '2026-09-17 09:00:00'),
(10, 7, 'in_progress', 'Same main valve issue. Engineer on site.', '2026-09-03 10:00:00'),
(10, 7, 'resolved',    'Valve fully replaced with new unit. Water restored.', '2026-09-05 15:00:00');

-- Sample escalation (complaint 4 is overdue)
INSERT INTO escalations (complaint_id, notified_supervisor, days_overdue, resolved) VALUES
(4, 2, 11, 0),
(6, 3, 8,  0);

-- ── DONE ─────────────────────────────────────────────────────
SELECT 'Database setup complete!' as Status,
       (SELECT COUNT(*) FROM departments)      as Departments,
       (SELECT COUNT(*) FROM categories)       as Categories,
       (SELECT COUNT(*) FROM users)            as Users,
       (SELECT COUNT(*) FROM complaints)       as Complaints,
       (SELECT COUNT(*) FROM complaint_updates)as Updates;
