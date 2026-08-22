-- ============================================================
--  STAR PUBLICATION — MySQL Database Schema
--  Import via phpMyAdmin  OR  run: mysql -u root -p < db.sql
--  (config.php also auto-creates this database on first run)
-- ============================================================

CREATE DATABASE IF NOT EXISTS star_publication
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE star_publication;

-- ------------------------------------------------------------
-- Registered users (from the Login / Register page)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)    NOT NULL,
  email         VARCHAR(150)    NOT NULL UNIQUE,
  phone         VARCHAR(15)     NOT NULL,
  address       VARCHAR(255)    NOT NULL,
  pincode       VARCHAR(10)     NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Contact-form enquiries
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  phone      VARCHAR(15)  DEFAULT NULL,
  message    TEXT         NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Admin accounts (default admin auto-seeded by config.php:
--   admin@starpublication.in / admin123  — change after first login!)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Service requests (enquiry → confirmed → payment_submitted → completed)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS requests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  status       ENUM('pending','confirmed','payment_submitted','completed','rejected') NOT NULL DEFAULT 'pending',
  confirmed_at DATETIME NULL,
  paid_at      DATETIME NULL,
  completed_at DATETIME NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Payment submissions (₹1500 file processing charge)
-- Screenshots stored in /uploads/payments/
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  amount     DECIMAL(10,2) NOT NULL DEFAULT 1500.00,
  utr        VARCHAR(50)   NOT NULL,
  payer_name VARCHAR(100)  DEFAULT NULL,
  screenshot VARCHAR(255)  DEFAULT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_req (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
