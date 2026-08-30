-- =====================================================
-- Gumming Sheet Price Calculator - Database Schema
-- =====================================================
-- Import this file to create the database and seed
-- it with the initial pricing values from the spec.
-- =====================================================

CREATE DATABASE IF NOT EXISTS gumming_calculator
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gumming_calculator;

-- ---------------------------------------------------
-- paper_rates
-- ---------------------------------------------------
CREATE TABLE paper_rates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    paper_size      VARCHAR(20)   NOT NULL,   -- e.g. '20x30'
    paper_quality   VARCHAR(20)   NOT NULL,   -- e.g. 'Normal' / 'Fasson'
    gsm             INT           NOT NULL DEFAULT 80,
    rate_per_sheet  DECIMAL(10,2) NOT NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_paper (paper_size, paper_quality)
) ENGINE=InnoDB;

INSERT INTO paper_rates (paper_size, paper_quality, gsm, rate_per_sheet) VALUES
('20x30', 'Normal', 80, 8.50),
('20x30', 'Fasson', 80, 12.50),
('18x23', 'Normal', 80, 7.50),
('15x20', 'Normal', 80, 4.50),
('15x20', 'Fasson', 80, 6.50);

-- ---------------------------------------------------
-- printing_rates
-- (single active row is used; kept as a table so it
--  is still admin-editable / audit-friendly)
-- ---------------------------------------------------
CREATE TABLE printing_rates (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    first_block_rate  DECIMAL(10,2) NOT NULL,
    next_block_rate   DECIMAL(10,2) NOT NULL,
    threshold         INT           NOT NULL,
    is_active         TINYINT(1)    NOT NULL DEFAULT 1,
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO printing_rates (first_block_rate, next_block_rate, threshold, is_active) VALUES
(1540.00, 480.00, 1050, 1);

-- ---------------------------------------------------
-- lamination_rates
-- one row per paper size, holding that size's base
-- area cost plus the glossy/matte divisors
-- ---------------------------------------------------
CREATE TABLE lamination_rates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    paper_size      VARCHAR(20)   NOT NULL,
    base_cost       DECIMAL(10,2) NOT NULL,
    glossy_divisor  DECIMAL(10,2) NOT NULL,
    matte_divisor   DECIMAL(10,2) NOT NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_size (paper_size)
) ENGINE=InnoDB;

INSERT INTO lamination_rates (paper_size, base_cost, glossy_divisor, matte_divisor) VALUES
('20x30', 600.00, 300.00, 150.00),
('18x23', 414.00, 300.00, 150.00),
('15x20', 300.00, 300.00, 150.00);

-- ---------------------------------------------------
-- calculator_settings (key/value store)
-- ---------------------------------------------------
CREATE TABLE calculator_settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(50)   NOT NULL UNIQUE,
    setting_value DECIMAL(10,4) NOT NULL,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO calculator_settings (setting_key, setting_value) VALUES
('margin_percent', 9.00),
('gst_percent', 18.00),
('delivery_rate_per_kg', 2.50),
('delivery_fixed_amount', 50.00),
('minimum_quantity', 100.00);

-- ---------------------------------------------------
-- admin_users
-- ---------------------------------------------------
CREATE TABLE admin_users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin login: username = admin / password = admin123
-- (hash generated with PHP password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO admin_users (username, password_hash) VALUES
('admin', '$2y$10$92IX1IcFyBIvhOWkK3Cv0e6WQP.LSCiUqK9U6QO7RyDwLzS.7QK5S');

-- ---------------------------------------------------
-- rate_history (bonus - audit trail)
-- ---------------------------------------------------
CREATE TABLE rate_history (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    table_name   VARCHAR(50)   NOT NULL,
    record_id    INT           NOT NULL,
    field_name   VARCHAR(50)   NOT NULL,
    old_value    VARCHAR(50),
    new_value    VARCHAR(50),
    changed_by   VARCHAR(50),
    changed_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------
-- orders (so submitted jobs / server totals are stored)
-- ---------------------------------------------------
CREATE TABLE orders (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    job_name            VARCHAR(150) NOT NULL,
    paper_id             INT NOT NULL,
    quantity            INT NOT NULL,
    printing_type       VARCHAR(30) NOT NULL,
    lamination_type     VARCHAR(30) NOT NULL,
    paper_cost          DECIMAL(10,2) NOT NULL,
    printing_cost       DECIMAL(10,2) NOT NULL,
    lamination_cost     DECIMAL(10,2) NOT NULL,
    base_cost           DECIMAL(10,2) NOT NULL,
    margin_amount       DECIMAL(10,2) NOT NULL,
    gst_amount          DECIMAL(10,2) NOT NULL,
    delivery_charge     DECIMAL(10,2) NOT NULL,
    total_before_discount DECIMAL(10,2) NOT NULL,
    discount            DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_payable      INT NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paper_id) REFERENCES paper_rates(id)
) ENGINE=InnoDB;
