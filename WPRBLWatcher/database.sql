-- RBL Watcher Database Schema

CREATE DATABASE IF NOT EXISTS rbl_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rbl_monitor;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP addresses table (1000 per user limit)
CREATE TABLE IF NOT EXISTS ip_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL, -- IPv4 or IPv6
    label VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_ip (user_id, ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBLs table
CREATE TABLE IF NOT EXISTS rbls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    dns_suffix VARCHAR(255) NOT NULL,
    enabled TINYINT(1) DEFAULT 1,
    requires_paid TINYINT(1) DEFAULT 0,
    rate_limit_delay_ms INT DEFAULT 0, -- Delay in milliseconds between queries
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- RBL check results table
CREATE TABLE IF NOT EXISTS rbl_check_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address_id INT NOT NULL,
    rbl_id INT NOT NULL,
    is_listed TINYINT(1) NOT NULL DEFAULT 0,
    response_text TEXT,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ip_address_id) REFERENCES ip_addresses(id) ON DELETE CASCADE,
    FOREIGN KEY (rbl_id) REFERENCES rbls(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ip_rbl (ip_address_id, rbl_id),
    INDEX idx_ip_address_id (ip_address_id),
    INDEX idx_rbl_id (rbl_id),
    INDEX idx_checked_at (checked_at),
    INDEX idx_is_listed (is_listed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User preferences table
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    report_frequency ENUM('daily', 'weekly') DEFAULT 'daily',
    report_day INT DEFAULT NULL, -- Day of week (0=Sunday, 6=Saturday) for weekly reports
    email_notifications TINYINT(1) DEFAULT 1,
    last_report_sent TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check history table for tracking
CREATE TABLE IF NOT EXISTS check_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    check_started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    check_completed_at TIMESTAMP NULL,
    total_ips INT DEFAULT 0,
    total_checks INT DEFAULT 0,
    blacklisted_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_check_started_at (check_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

