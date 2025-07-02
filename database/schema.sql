-- Create database
CREATE DATABASE IF NOT EXISTS theater_management;
USE theater_management;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Theater Halls table
CREATE TABLE IF NOT EXISTS theater_halls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reservations table
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    theater_hall_id INT NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_description TEXT,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('pending', 'reserved', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (theater_hall_id) REFERENCES theater_halls(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_theater_hall (theater_hall_id),
    INDEX idx_dates (start_datetime, end_datetime)
);

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Insert default admin user (password: admin123 - change this in production)
INSERT INTO users (username, email, password_hash, full_name, role, is_approved)
VALUES ('admin', 'admin@helwan.edu.eg', '$2y$10$Xbd6DT3y3H9Ur7RrpdS6b.tLjRvX0DuRBUbAXTqwIXYTKgNw7WKkS', 'System Administrator', 'admin', TRUE);

-- Insert sample theater hall
INSERT INTO theater_halls (name, capacity, description, is_active)
VALUES ('Main Hall', 500, 'Main theater hall with 500 seating capacity', TRUE);

-- Add last_login and last_ip fields to users table
ALTER TABLE users 
ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL AFTER updated_at,
ADD COLUMN last_ip VARCHAR(45) DEFAULT NULL AFTER last_login,
ADD INDEX idx_last_login (last_login);

-- Update the Auth class to update these fields on login
