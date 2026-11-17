-- Resort360 / MariaDB 10.4+ / MySQL 8.0+. Import into an empty database.
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS resort360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE resort360;
CREATE TABLE roles (id INT PRIMARY KEY, name VARCHAR(30) NOT NULL UNIQUE) ENGINE=InnoDB;
INSERT INTO roles VALUES (1,'admin'),(2,'receptionist'),(3,'staff'),(4,'guest');
CREATE TABLE permissions (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(60) NOT NULL UNIQUE) ENGINE=InnoDB;
INSERT INTO permissions(name) VALUES ('reservations.manage'),('guests.manage'),('facilities.read'),('facilities.manage'),('payments.manage'),('payments.void'),('maintenance.manage'),('monitoring.read'),('devices.manage'),('reports.read'),('users.manage'),('audit.read');
CREATE TABLE role_permissions (role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY(role_id,permission_id), FOREIGN KEY(role_id) REFERENCES roles(id), FOREIGN KEY(permission_id) REFERENCES permissions(id)) ENGINE=InnoDB;
INSERT INTO role_permissions SELECT 1,id FROM permissions;
INSERT INTO role_permissions SELECT 2,id FROM permissions WHERE name IN ('reservations.manage','guests.manage','facilities.read','payments.manage');
INSERT INTO role_permissions SELECT 3,id FROM permissions WHERE name IN ('facilities.read','maintenance.manage','monitoring.read');
CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, active TINYINT NOT NULL DEFAULT 1, session_version INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(role_id) REFERENCES roles(id), CHECK(active IN (0,1))) ENGINE=InnoDB;
CREATE TABLE guests (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL UNIQUE, name VARCHAR(100) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(40) NOT NULL, address VARCHAR(255) NOT NULL DEFAULT '', archived_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id), INDEX(name), INDEX(email)) ENGINE=InnoDB;
CREATE TABLE facilities (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, type ENUM('room','cottage','facility') NOT NULL, capacity INT NOT NULL, nightly_rate DECIMAL(12,2) NOT NULL, status ENUM('available','maintenance') NOT NULL DEFAULT 'available', description TEXT NOT NULL, image_path VARCHAR(255) NOT NULL DEFAULT '', archived_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CHECK(capacity>0), CHECK(nightly_rate>=0)) ENGINE=InnoDB;
INSERT INTO facilities(name,type,capacity,nightly_rate,description,image_path) VALUES ('Ocean Room 2','room',2,2500,CONCAT_WS(CHAR(10),'Room Inclusions:','Airconditioned Rooms','Bed + Extra Mattress','Own/ Common CR','Own Kitchen','Free use of Kitchen Utensils','Free WiFi','Free open cottage / Tambayan Area','Free use of Videoke until 10 PM','Details:','Beach Front + Swimming Pool Access','No Entrance Fee','No Corkage Fee','Highlight:','PERFECT FOR FAMILY, BARKADA & TEAM OUTING!'),'assets/img/ocean-room-2-clean.png'),('Family Room 1','room',6,4500,CONCAT_WS(CHAR(10),'Room Inclusions:','Airconditioned Rooms','Bed + Extra Mattress','Own/ Common CR','Own Kitchen','Free use of Kitchen Utensils','Free WiFi','Free open cottage / Tambayan Area','Free use of Videoke until 10 PM','Details:','Beach Front + Swimming Pool Access','No Entrance Fee','No Corkage Fee','Highlight:','PERFECT FOR FAMILY, BARKADA & TEAM OUTING!'),'assets/img/family-room-1-clean.png'),('Demo · Garden Cottage 01','cottage',8,1800,'Sample cottage using the documented nightly booking assumption.','assets/img/garden-cottage-01.jpeg'),('Demo · Pool Area','facility',20,0,'Non-bookable facility for maintenance and monitoring.',''),('Ocean Room 1','room',10,2500,CONCAT_WS(CHAR(10),'Room Inclusions:','Airconditioned Rooms','Bed + Extra Mattress','Own/ Common CR','Own Kitchen','Free use of Kitchen Utensils','Free WiFi','Free open cottage / Tambayan Area','Free use of Videoke until 10 PM','Details:','Beach Front + Swimming Pool Access','No Entrance Fee','No Corkage Fee','Highlight:','PERFECT FOR FAMILY, BARKADA & TEAM OUTING!'),'assets/img/ocean-room-1-clean.png'),('Family Room 2','room',10,4500,CONCAT_WS(CHAR(10),'Room Inclusions:','Airconditioned Rooms','Bed + Extra Mattress','Own/ Common CR','Own Kitchen','Free use of Kitchen Utensils','Free WiFi','Free open cottage / Tambayan Area','Free use of Videoke until 10 PM','Details:','Beach Front + Swimming Pool Access','No Entrance Fee','No Corkage Fee','Highlight:','PERFECT FOR FAMILY, BARKADA & TEAM OUTING!'),'assets/img/family-room-2-clean.png');
UPDATE facilities
SET description=CONCAT_WS(CHAR(10),
    'Room Inclusions:',
    'Kubo Rooms (Fan/AC): Day Tour & Overnight Available',
    'With WiFi',
    'Free use of Griller',
    'Common CR',
    'Free Tent Pitch (Pwede mag dala ng sariling Tent)',
    'Free Use of Swimming Pool',
    'Bed Capacity good for 5 persons',
    'Extra mattress Available',
    'Additional ₱250 if magluluto',
    'Overnight Time: 10 AM Check-in | 9 AM Check-Out',
    'Details:',
    'Beach Front + Swimming Pool Access',
    'No Entrance Fee',
    'No Corkage Fee',
    'Highlight:',
    'Perfect for Family, Barkada & Team Outing!')
WHERE name='Demo · Garden Cottage 01' AND type='cottage';
CREATE TABLE reservations (id INT AUTO_INCREMENT PRIMARY KEY, reference VARCHAR(30) NOT NULL UNIQUE, guest_id INT NOT NULL, facility_id INT NOT NULL, arrival DATE NOT NULL, departure DATE NOT NULL, guests_count INT NOT NULL, nightly_rate DECIMAL(12,2) NOT NULL, total DECIMAL(12,2) NOT NULL, status ENUM('pending','confirmed','checked_in','checked_out','cancelled','rejected') NOT NULL DEFAULT 'pending', notes VARCHAR(1000) NOT NULL DEFAULT '', created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(guest_id) REFERENCES guests(id), FOREIGN KEY(facility_id) REFERENCES facilities(id), FOREIGN KEY(created_by) REFERENCES users(id), INDEX(facility_id,arrival,departure,status), INDEX(guest_id,status), CHECK(departure>arrival), CHECK(guests_count>0), CHECK(total>=0)) ENGINE=InnoDB;
CREATE TABLE payments (id INT AUTO_INCREMENT PRIMARY KEY, reservation_id INT NOT NULL, amount DECIMAL(12,2) NOT NULL, method ENUM('cash','bank_transfer','ewallet','card_record') NOT NULL, reference VARCHAR(100) NOT NULL DEFAULT '', notes VARCHAR(500) NOT NULL DEFAULT '', recorded_by INT NOT NULL, voided_at DATETIME NULL, void_reason VARCHAR(500) NULL, voided_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(reservation_id) REFERENCES reservations(id), FOREIGN KEY(recorded_by) REFERENCES users(id), FOREIGN KEY(voided_by) REFERENCES users(id), INDEX(reservation_id,voided_at), CHECK(amount>0)) ENGINE=InnoDB;
CREATE TABLE maintenance (id INT AUTO_INCREMENT PRIMARY KEY, facility_id INT NOT NULL, title VARCHAR(150) NOT NULL, description VARCHAR(2000) NOT NULL, priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal', status ENUM('open','in_progress','completed','cancelled') NOT NULL DEFAULT 'open', assigned_to INT NULL, resolution VARCHAR(2000) NOT NULL DEFAULT '', created_by INT NOT NULL, completed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(facility_id) REFERENCES facilities(id), FOREIGN KEY(assigned_to) REFERENCES users(id), FOREIGN KEY(created_by) REFERENCES users(id), INDEX(status,priority)) ENGINE=InnoDB;
CREATE TABLE devices (id INT AUTO_INCREMENT PRIMARY KEY, facility_id INT NOT NULL, name VARCHAR(100) NOT NULL, key_hash CHAR(64) NOT NULL UNIQUE, active TINYINT NOT NULL DEFAULT 1, min_temp DECIMAL(6,2) NOT NULL DEFAULT 15, max_temp DECIMAL(6,2) NOT NULL DEFAULT 40, stale_minutes INT NOT NULL DEFAULT 5, last_seen DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY(facility_id) REFERENCES facilities(id), CHECK(min_temp<max_temp), CHECK(stale_minutes BETWEEN 1 AND 1440)) ENGINE=InnoDB;
CREATE TABLE readings (id BIGINT AUTO_INCREMENT PRIMARY KEY, device_id INT NOT NULL, sample_id VARCHAR(80) NOT NULL, temperature DECIMAL(6,2) NOT NULL, received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(device_id) REFERENCES devices(id), UNIQUE(device_id,sample_id), INDEX(device_id,received_at), CHECK(temperature BETWEEN -55 AND 125)) ENGINE=InnoDB;
CREATE TABLE notifications (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message VARCHAR(500) NOT NULL, read_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id), INDEX(user_id,read_at)) ENGINE=InnoDB;
CREATE TABLE audit_logs (id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(60) NOT NULL, entity VARCHAR(60) NOT NULL, entity_id INT NULL, details VARCHAR(1000) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id), INDEX(created_at), INDEX(entity,entity_id)) ENGINE=InnoDB;
CREATE TABLE auth_attempts (id BIGINT AUTO_INCREMENT PRIMARY KEY, bucket CHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(bucket,created_at)) ENGINE=InnoDB;
CREATE TABLE password_resets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB;

-- Additive upgrade for installed databases. Existing bookings and payments are retained.
CREATE TABLE IF NOT EXISTS cash_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL UNIQUE,
    reservation_id INT NOT NULL,
    guest_id INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    facility_name VARCHAR(100) NOT NULL,
    arrival DATE NOT NULL,
    departure DATE NOT NULL,
    total_at_issue DECIMAL(12,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    notes VARCHAR(500) NOT NULL DEFAULT '',
    state ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    payment_id INT NULL UNIQUE,
    created_by INT NOT NULL,
    cancelled_reason VARCHAR(500) NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(reservation_id) REFERENCES reservations(id),
    FOREIGN KEY(guest_id) REFERENCES guests(id),
    FOREIGN KEY(payment_id) REFERENCES payments(id),
    FOREIGN KEY(created_by) REFERENCES users(id),
    INDEX(reservation_id,state),
    INDEX(guest_id,created_at),
    CHECK(amount>0)
) ENGINE=InnoDB;
