DROP DATABASE IF EXISTS ParadiseResort;
CREATE DATABASE ParadiseResort;
USE ParadiseResort;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE gruppi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    script_name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE user_gruppi (
    user_id INT,
    group_id INT,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES gruppi(id) ON DELETE CASCADE
);

CREATE TABLE group_services (
    group_id INT,
    service_id INT,
    PRIMARY KEY (group_id, service_id),
    FOREIGN KEY (group_id) REFERENCES gruppi(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE room_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2) NOT NULL,
    capacity INT NOT NULL,
    image_url VARCHAR(255)
);

CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    category_id INT NOT NULL,
    floor INT NOT NULL,
    status ENUM('available', 'maintenance', 'cleaning') DEFAULT 'available',
    FOREIGN KEY (category_id) REFERENCES room_categories(id) ON DELETE RESTRICT
);

CREATE TABLE booking_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    status_id INT NOT NULL,
    check_in_date DATE NOT NULL,
    check_out_date DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    staff_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    FOREIGN KEY (status_id) REFERENCES booking_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    is_suspended TINYINT DEFAULT 0
);

CREATE TABLE booking_amenities (
    booking_id INT,
    amenity_id INT,
    quantity INT DEFAULT 1,
    PRIMARY KEY (booking_id, amenity_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    invoice_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_category_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_category_id) REFERENCES room_categories(id) ON DELETE CASCADE
);

CREATE TABLE ticket_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE maintenance_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT DEFAULT NULL,
    reported_by_user_id INT NOT NULL,
    status_id INT NOT NULL,
    issue_description TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES ticket_statuses(id) ON DELETE RESTRICT
);

CREATE TABLE restaurant_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    meal_type ENUM('Pranzo', 'Cena') NOT NULL,
    reservation_time TIME NOT NULL,
    guests INT NOT NULL DEFAULT 1,
    status ENUM('Pending', 'Confirmed', 'Cancelled') DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
