--GMT HOTEL AND EVENTS CENTRE: Hotel Management System
-- Database Schema (MySQL 8+)

SET FOREIGN_KEY_CHECKS = 0;
CREATE DATABASE IF NOT EXISTS gmt_hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gmt_hotel;

-- ACCESS CONTROL
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,          
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,               
    action VARCHAR(50) NOT NULL,                
    UNIQUE KEY uniq_module_action (module, action)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NULL,                          
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('active','suspended') DEFAULT 'active',
    failed_login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    remember_token VARCHAR(255) NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- STAFF
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    position VARCHAR(50) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    role_id INT NULL,
    status ENUM('active','on_leave','terminated') DEFAULT 'active',
    date_employed DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- ROOMS
CREATE TABLE room_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,                  
    description TEXT NULL,
    base_price DECIMAL(12,2) NOT NULL,
    capacity INT NOT NULL DEFAULT 2,
    amenities TEXT NULL,                        
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    room_type_id INT NOT NULL,
    floor INT NOT NULL,
    price_per_night DECIMAL(12,2) NOT NULL,     
    capacity INT NOT NULL DEFAULT 2,
    description TEXT NULL,
    amenities TEXT NULL,
    image_path VARCHAR(255) NULL,
    status ENUM('available','reserved','occupied','cleaning','maintenance','out_of_service') DEFAULT 'available',
    maintenance_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (room_type_id) REFERENCES room_types(id),
    INDEX idx_status (status),
    INDEX idx_floor (floor)
) ENGINE=InnoDB;

-- GUESTS
CREATE TABLE guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(100) NULL,
    address VARCHAR(255) NULL,
    country VARCHAR(60) NULL,
    id_type VARCHAR(40) NULL,                   
    id_number VARCHAR(60) NULL,
    date_of_birth DATE NULL,
    emergency_contact_name VARCHAR(100) NULL,
    emergency_contact_phone VARCHAR(30) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_name (full_name),
    INDEX idx_phone (phone)
) ENGINE=InnoDB;

-- RESERVATIONS / CHECK-IN / CHECK-OUT
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_code VARCHAR(30) NOT NULL UNIQUE,   
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    number_of_guests INT NOT NULL DEFAULT 1,
    room_rate DECIMAL(12,2) NOT NULL,
    number_of_nights INT NOT NULL,
    discount DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    status ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending',
    special_requests TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_dates (check_in_date, check_out_date),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    checked_in_by INT NULL,
    check_in_time DATETIME NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (checked_in_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE checkouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    checked_out_by INT NULL,
    check_out_time DATETIME NOT NULL,
    additional_charges DECIMAL(12,2) DEFAULT 0,
    outstanding_balance DECIMAL(12,2) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (checked_out_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- HOUSEKEEPING
CREATE TABLE housekeeping_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    assigned_staff_id INT NULL,
    task VARCHAR(100) NOT NULL,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status ENUM('pending','in_progress','completed','inspected') DEFAULT 'pending',
    time_assigned DATETIME NULL,
    time_completed DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (assigned_staff_id) REFERENCES staff(id)
) ENGINE=InnoDB;

-- PAYMENTS / INVOICES
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guest_id INT NULL,
    reservation_id INT NULL,
    event_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','pos','card','other') NOT NULL,
    reference_number VARCHAR(60) NULL,
    staff_id INT NULL,
    notes TEXT NULL,
    paid_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB;

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    reservation_id INT NULL,
    event_id INT NULL,
    guest_id INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2) NOT NULL,
    status ENUM('draft','issued','paid','void') DEFAULT 'issued',
    issued_by INT NULL,
    issued_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- EVENTS
CREATE TABLE event_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_code VARCHAR(30) NOT NULL UNIQUE,
    client_name VARCHAR(100) NOT NULL,
    client_phone VARCHAR(30) NULL,
    client_email VARCHAR(100) NULL,
    event_name VARCHAR(150) NOT NULL,
    event_type ENUM('wedding','conference','birthday','meeting','seminar','party','corporate','other') NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue VARCHAR(100) NOT NULL,
    number_of_guests INT NOT NULL,
    package_id INT NULL,
    price DECIMAL(12,2) NOT NULL,
    deposit DECIMAL(12,2) DEFAULT 0,
    balance DECIMAL(12,2) NOT NULL,
    status ENUM('inquiry','confirmed','completed','cancelled') DEFAULT 'inquiry',
    special_requirements TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (package_id) REFERENCES event_packages(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_event_date (event_date)
) ENGINE=InnoDB;

-- RESTAURANT / POS
CREATE TABLE restaurant_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_number VARCHAR(20) NOT NULL UNIQUE,
    capacity INT NOT NULL DEFAULT 4,
    status ENUM('available','occupied','reserved') DEFAULT 'available'
) ENGINE=InnoDB;

CREATE TABLE menu_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    description TEXT NULL,
    is_available TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    table_id INT NULL,
    guest_id INT NULL,
    reservation_id INT NULL,               
    order_type ENUM('dine_in','room_charge','takeaway') DEFAULT 'dine_in',
    status ENUM('open','served','paid','cancelled') DEFAULT 'open',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    staff_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES restaurant_tables(id),
    FOREIGN KEY (guest_id) REFERENCES guests(id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
) ENGINE=InnoDB;

-- EXPENSES
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('utilities','maintenance','salaries','supplies','food','events','transportation','other') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('cash','bank_transfer','pos','card','other') NOT NULL,
    staff_id INT NULL,
    receipt_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
) ENGINE=InnoDB;

-- NOTIFICATIONS / AUDIT / SETTINGS
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                       
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,           
    module VARCHAR(50) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_module (module),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(60) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- SEED: roles, default settings, default admin
INSERT INTO roles (name, description) VALUES
('Super Admin', 'Full system access'),
('Manager', 'Operational management access'),
('Receptionist', 'Front desk operations'),
('Accountant', 'Financial records and reporting'),
('Housekeeping', 'Room status and cleaning tasks'),
('Event Manager', 'Events centre bookings'),
('Restaurant Staff', 'Restaurant and POS operations');

INSERT INTO settings (setting_key, setting_value) VALUES
('hotel_name', 'GMT Hotel and Events Centre'),
('address', 'Iwo, Osun State, Nigeria'),
('phone', ''),
('email', ''),
('currency_code', 'NGN'),
('currency_symbol', '₦'),
('tax_rate', '7.5'),
('invoice_prefix', 'GMT-INV-'),
('reservation_prefix', 'GMT-'),
('date_format', 'd M Y'),
('time_format', 'H:i'),
('whatsapp_country_code', '234');

-- Default administrator. Username: admin, password: ChangeMe!2026
-- Hash generated with password_hash('ChangeMe!2026', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password_hash, role_id, status) VALUES
('admin', 'admin@gmthotel.local', '$2y$10$examplehashREPLACEONFIRSTRUN.......................', 1, 'active');

-- SEED: realistic sample data (rooms, staff, guests)
-- Believable Nigerian hospitality data. No "John Doe" / "Test User" placeholders
INSERT INTO room_types (name, description, base_price, capacity, amenities) VALUES
('Standard Room', 'Comfortable well-appointed room with garden view', 35000.00, 2, 'Wi-Fi, AC, Flat-screen TV, Mini-fridge'),
('Deluxe Room', 'Spacious room with premium furnishing and city view', 55000.00, 2, 'Wi-Fi, AC, Smart TV, Mini-bar, Work desk'),
('Executive Suite', 'Suite with separate living area and premium amenities', 95000.00, 3, 'Wi-Fi, AC, Smart TV, Mini-bar, Jacuzzi, Lounge area'),
('Presidential Suite', 'Our finest suite with panoramic views and butler service', 180000.00, 4, 'Wi-Fi, AC, Smart TV, Full bar, Jacuzzi, Private lounge, Butler service');

INSERT INTO rooms (room_number, room_type_id, floor, price_per_night, capacity, status) VALUES
('101', 1, 1, 35000.00, 2, 'available'),
('102', 1, 1, 35000.00, 2, 'available'),
('103', 1, 1, 35000.00, 2, 'cleaning'),
('104', 1, 1, 35000.00, 2, 'available'),
('201', 2, 2, 55000.00, 2, 'available'),
('202', 2, 2, 55000.00, 2, 'available'),
('203', 2, 2, 55000.00, 2, 'maintenance'),
('301', 3, 3, 95000.00, 3, 'available'),
('302', 3, 3, 95000.00, 3, 'available'),
('401', 4, 4, 180000.00, 4, 'available');

INSERT INTO staff (full_name, department, position, phone, email, role_id, status, date_employed) VALUES
('Adaeze Okonkwo', 'Front Office', 'Receptionist', '08031234501', 'adaeze.okonkwo@gmthotel.local', 3, 'active', '2023-03-14'),
('Ibrahim Suleiman', 'Housekeeping', 'Housekeeping Supervisor', '08031234502', 'ibrahim.suleiman@gmthotel.local', 5, 'active', '2022-07-01'),
('Ngozi Eze', 'Housekeeping', 'Room Attendant', '08031234503', 'ngozi.eze@gmthotel.local', 5, 'active', '2024-01-20'),
('Tunde Bakare', 'Front Office', 'Manager', '08031234504', 'tunde.bakare@gmthotel.local', 2, 'active', '2021-05-10'),
('Fatima Bello', 'Accounts', 'Accountant', '08031234505', 'fatima.bello@gmthotel.local', 4, 'active', '2022-11-03'),
('Chinedu Okafor', 'Events', 'Event Manager', '08031234506', 'chinedu.okafor@gmthotel.local', 6, 'active', '2023-09-18');

INSERT INTO guests (full_name, phone, email, address, country, id_type, id_number, date_of_birth) VALUES
('Folake Adeyemi', '08051112233', 'folake.adeyemi@example.com', '14 Freedom Way, Lekki', 'Nigeria', 'National ID', 'NIN-20481193', '1988-04-12'),
('Emeka Nwosu', '08161223344', 'emeka.nwosu@example.com', '7 Ahmadu Bello Way, Kaduna', 'Nigeria', 'Driver''s Licence', 'DL-77213', '1991-11-02'),
('Grace Umoh', '08079988776', 'grace.umoh@example.com', '22 Ikot Ekpene Road, Uyo', 'Nigeria', 'Passport', 'A04552178', '1985-07-25');

INSERT INTO event_packages (name, description, price) VALUES
('Intimate Wedding Package', 'Venue, decor, and catering for up to 100 guests', 850000.00),
('Grand Wedding Package', 'Full events centre, premium decor, catering, and MC for up to 400 guests', 2200000.00),
('Corporate Conference Package', 'Full-day hall hire with AV equipment, catering, and breakout space', 650000.00),
('Birthday Celebration Package', 'Venue, decor, and light catering for up to 80 guests', 400000.00),
('Business Meeting Package', 'Half-day boardroom hire with refreshments', 150000.00);
