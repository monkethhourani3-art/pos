-- ================================================
-- Restaurant POS System Database Schema
-- Created: 2025-12-15
-- Database: MySQL 8.0+
-- ================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+03:00';

-- Create Database
CREATE DATABASE IF NOT EXISTS `restaurant_pos` 
DEFAULT CHARACTER SET utf8mb4 
DEFAULT COLLATE utf8mb4_unicode_ci;

USE `restaurant_pos`;

-- ================================================
-- USERS AND AUTHENTICATION TABLES
-- ================================================

-- Users table
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) DEFAULT NULL,
    `password_hash` varchar(255) NOT NULL,
    `first_name` varchar(50) NOT NULL,
    `last_name` varchar(50) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `avatar` varchar(255) DEFAULT NULL,
    `branch_id` int(11) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `last_login_at` timestamp NULL DEFAULT NULL,
    `login_attempts` int(11) DEFAULT 0,
    `locked_until` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_branch_active` (`branch_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table
CREATE TABLE `roles` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE `permissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `display_name` varchar(150) NOT NULL,
    `description` text DEFAULT NULL,
    `group_name` varchar(50) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions junction table
CREATE TABLE `role_permissions` (
    `role_id` int(11) NOT NULL,
    `permission_id` int(11) NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `permission_id` (`permission_id`),
    CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User roles junction table
CREATE TABLE `user_roles` (
    `user_id` int(11) NOT NULL,
    `role_id` int(11) NOT NULL,
    `branch_id` int(11) DEFAULT NULL,
    `assigned_by` int(11) DEFAULT NULL,
    `assigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`, `branch_id`),
    KEY `role_id` (`role_id`),
    KEY `branch_id` (`branch_id`),
    KEY `assigned_by` (`assigned_by`),
    CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_roles_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- RESTAURANT STRUCTURE TABLES
-- ================================================

-- Branches table (optional)
CREATE TABLE `branches` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `display_name` varchar(150) NOT NULL,
    `address` text DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `tax_number` varchar(50) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Areas (dining areas)
CREATE TABLE `areas` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) DEFAULT NULL,
    `name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `sort_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `branch_id` (`branch_id`),
    CONSTRAINT `areas_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tables
CREATE TABLE `tables` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) DEFAULT NULL,
    `area_id` int(11) DEFAULT NULL,
    `table_number` varchar(10) NOT NULL,
    `table_name` varchar(50) DEFAULT NULL,
    `capacity` int(11) DEFAULT 4,
    `status` enum('available','occupied','reserved','cleaning','out_of_service') DEFAULT 'available',
    `x_position` int(11) DEFAULT 0,
    `y_position` int(11) DEFAULT 0,
    `sort_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `branch_table` (`branch_id`, `table_number`),
    KEY `area_id` (`area_id`),
    KEY `status` (`status`),
    CONSTRAINT `tables_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- PRODUCTS AND MENU TABLES
-- ================================================

-- Categories
CREATE TABLE `categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) DEFAULT NULL,
    `name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `sort_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `branch_id` (`branch_id`),
    CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE `products` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) DEFAULT NULL,
    `category_id` int(11) DEFAULT NULL,
    `sku` varchar(50) DEFAULT NULL,
    `name_ar` varchar(100) NOT NULL,
    `name_en` varchar(100) NOT NULL,
    `description_ar` text DEFAULT NULL,
    `description_en` text DEFAULT NULL,
    `image` varchar(255) DEFAULT NULL,
    `type` enum('item','combo','service') DEFAULT 'item',
    `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
    `cost_price` decimal(10,2) DEFAULT 0.00,
    `preparation_time` int(11) DEFAULT 0,
    `is_available` tinyint(1) DEFAULT 1,
    `is_featured` tinyint(1) DEFAULT 0,
    `sort_order` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `branch_sku` (`branch_id`, `sku`),
    KEY `category_id` (`category_id`),
    KEY `type_available` (`type`, `is_available`),
    KEY `featured` (`is_featured`),
    CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product modifiers (options)
CREATE TABLE `product_modifiers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `name_ar` varchar(100) NOT NULL,
    `name_en` varchar(100) NOT NULL,
    `type` enum('single','multiple','required') DEFAULT 'single',
    `min_select` int(11) DEFAULT 0,
    `max_select` int(11) DEFAULT 1,
    `sort_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    CONSTRAINT `product_modifiers_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modifier items
CREATE TABLE `modifier_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `modifier_id` int(11) NOT NULL,
    `name_ar` varchar(100) NOT NULL,
    `name_en` varchar(100) NOT NULL,
    `price_modifier` decimal(10,2) DEFAULT 0.00,
    `is_default` tinyint(1) DEFAULT 0,
    `is_available` tinyint(1) DEFAULT 1,
    `sort_order` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `modifier_id` (`modifier_id`),
    CONSTRAINT `modifier_items_ibfk_1` FOREIGN KEY (`modifier_id`) REFERENCES `product_modifiers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product prices (for size variants etc.)
CREATE TABLE `product_prices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `product_id` int(11) NOT NULL,
    `name_ar` varchar(100) DEFAULT NULL,
    `name_en` varchar(100) DEFAULT NULL,
    `price` decimal(10,2) NOT NULL,
    `cost_price` decimal(10,2) DEFAULT 0.00,
    `is_default` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`),
    KEY `default_active` (`is_default`, `is_active`),
    CONSTRAINT `product_prices_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TAXES AND FEES
-- ================================================

-- Taxes
CREATE TABLE `taxes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `type` enum('percentage','fixed') DEFAULT 'percentage',
    `rate` decimal(5,2) NOT NULL,
    `is_included` tinyint(1) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- ORDERS TABLES
-- ================================================

-- Orders
CREATE TABLE `orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_number` varchar(20) NOT NULL,
    `branch_id` int(11) DEFAULT NULL,
    `table_id` int(11) DEFAULT NULL,
    `user_id` int(11) DEFAULT NULL,
    `order_type` enum('dine_in','takeaway','delivery','pickup') NOT NULL,
    `status` enum('draft','sent_to_kitchen','preparing','ready','served','out_for_delivery','closed','cancelled') DEFAULT 'draft',
    `customer_name` varchar(100) DEFAULT NULL,
    `customer_phone` varchar(20) DEFAULT NULL,
    `customer_address` text DEFAULT NULL,
    `subtotal` decimal(10,2) DEFAULT 0.00,
    `tax_amount` decimal(10,2) DEFAULT 0.00,
    `discount_amount` decimal(10,2) DEFAULT 0.00,
    `total_amount` decimal(10,2) DEFAULT 0.00,
    `paid_amount` decimal(10,2) DEFAULT 0.00,
    `notes` text DEFAULT NULL,
    `kitchen_notes` text DEFAULT NULL,
    `delivery_time` int(11) DEFAULT NULL,
    `estimated_ready_time` timestamp NULL DEFAULT NULL,
    `actual_ready_time` timestamp NULL DEFAULT NULL,
    `sent_to_kitchen_at` timestamp NULL DEFAULT NULL,
    `closed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `order_number` (`order_number`),
    KEY `branch_status` (`branch_id`, `status`),
    KEY `user_status` (`user_id`, `status`),
    KEY `order_type` (`order_type`),
    KEY `created_at` (`created_at`),
    KEY `table_status` (`table_id`, `status`),
    CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
    CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE SET NULL,
    CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items
CREATE TABLE `order_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `product_id` int(11) NOT NULL,
    `product_price_id` int(11) DEFAULT NULL,
    `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
    `unit_price` decimal(10,2) NOT NULL,
    `total_price` decimal(10,2) NOT NULL,
    `notes` text DEFAULT NULL,
    `status` enum('ordered','preparing','ready','served','cancelled') DEFAULT 'ordered',
    `preparation_time` int(11) DEFAULT 0,
    `kitchen_notes` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`),
    KEY `product_id` (`product_id`),
    KEY `product_price_id` (`product_price_id`),
    KEY `status` (`status`),
    CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`product_price_id`) REFERENCES `product_prices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order item modifiers
CREATE TABLE `order_item_modifiers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_item_id` int(11) NOT NULL,
    `modifier_item_id` int(11) NOT NULL,
    `quantity` decimal(8,2) DEFAULT 1.00,
    `unit_price` decimal(10,2) DEFAULT 0.00,
    `total_price` decimal(10,2) DEFAULT 0.00,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `order_item_id` (`order_item_id`),
    KEY `modifier_item_id` (`modifier_item_id`),
    CONSTRAINT `order_item_modifiers_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `order_item_modifiers_ibfk_2` FOREIGN KEY (`modifier_item_id`) REFERENCES `modifier_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- INVOICES AND PAYMENTS
-- ================================================

-- Invoices
CREATE TABLE `invoices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` varchar(20) NOT NULL,
    `order_id` int(11) NOT NULL,
    `branch_id` int(11) DEFAULT NULL,
    `subtotal` decimal(10,2) DEFAULT 0.00,
    `tax_amount` decimal(10,2) DEFAULT 0.00,
    `discount_amount` decimal(10,2) DEFAULT 0.00,
    `total_amount` decimal(10,2) DEFAULT 0.00,
    `paid_amount` decimal(10,2) DEFAULT 0.00,
    `change_amount` decimal(10,2) DEFAULT 0.00,
    `payment_status` enum('pending','paid','partial','refunded','void') DEFAULT 'pending',
    `printed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `invoice_number` (`invoice_number`),
    KEY `order_id` (`order_id`),
    KEY `branch_id` (`branch_id`),
    KEY `payment_status` (`payment_status`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE `payments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` int(11) NOT NULL,
    `payment_method` enum('cash','card','mixed','wallet','bank_transfer') NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `card_type` varchar(20) DEFAULT NULL,
    `card_last_four` varchar(4) DEFAULT NULL,
    `reference_number` varchar(50) DEFAULT NULL,
    `tip_amount` decimal(10,2) DEFAULT 0.00,
    `processed_by` int(11) DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `invoice_id` (`invoice_id`),
    KEY `processed_by` (`processed_by`),
    CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discounts
CREATE TABLE `discounts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `type` enum('percentage','fixed','buy_x_get_y') DEFAULT 'percentage',
    `value` decimal(10,2) NOT NULL,
    `min_order_amount` decimal(10,2) DEFAULT 0.00,
    `max_discount_amount` decimal(10,2) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `starts_at` timestamp NULL DEFAULT NULL,
    `ends_at` timestamp NULL DEFAULT NULL,
    `usage_limit` int(11) DEFAULT NULL,
    `used_count` int(11) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order discounts
CREATE TABLE `order_discounts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `discount_id` int(11) DEFAULT NULL,
    `name` varchar(100) NOT NULL,
    `type` enum('percentage','fixed') NOT NULL,
    `value` decimal(10,2) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `applied_by` int(11) DEFAULT NULL,
    `reason` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `order_id` (`order_id`),
    KEY `discount_id` (`discount_id`),
    KEY `applied_by` (`applied_by`),
    CONSTRAINT `order_discounts_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `order_discounts_ibfk_2` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `order_discounts_ibfk_3` FOREIGN KEY (`applied_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds
CREATE TABLE `refunds` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` int(11) NOT NULL,
    `payment_id` int(11) DEFAULT NULL,
    `amount` decimal(10,2) NOT NULL,
    `reason` text NOT NULL,
    `refunded_by` int(11) NOT NULL,
    `refunded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `approval_required` tinyint(1) DEFAULT 1,
    `approved_by` int(11) DEFAULT NULL,
    `approved_at` timestamp NULL DEFAULT NULL,
    `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
    `notes` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `invoice_id` (`invoice_id`),
    KEY `payment_id` (`payment_id`),
    KEY `refunded_by` (`refunded_by`),
    KEY `approved_by` (`approved_by`),
    CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `refunds_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `refunds_ibfk_3` FOREIGN KEY (`refunded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `refunds_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- SHIFTS AND CASH MANAGEMENT
-- ================================================

-- Shifts
CREATE TABLE `shifts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) DEFAULT NULL,
    `opened_by` int(11) NOT NULL,
    `opened_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `closed_by` int(11) DEFAULT NULL,
    `closed_at` timestamp NULL DEFAULT NULL,
    `opening_cash` decimal(10,2) NOT NULL DEFAULT 0.00,
    `total_sales` decimal(10,2) DEFAULT 0.00,
    `cash_sales` decimal(10,2) DEFAULT 0.00,
    `card_sales` decimal(10,2) DEFAULT 0.00,
    `total_payments` decimal(10,2) DEFAULT 0.00,
    `total_refunds` decimal(10,2) DEFAULT 0.00,
    `paid_out_amount` decimal(10,2) DEFAULT 0.00,
    `expected_cash` decimal(10,2) DEFAULT 0.00,
    `actual_cash` decimal(10,2) DEFAULT 0.00,
    `cash_difference` decimal(10,2) DEFAULT 0.00,
    `notes` text DEFAULT NULL,
    `status` enum('open','closed') DEFAULT 'open',
    PRIMARY KEY (`id`),
    KEY `branch_id` (`branch_id`),
    KEY `opened_by` (`opened_by`),
    KEY `status` (`status`),
    KEY `opened_at` (`opened_at`),
    CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
    CONSTRAINT `shifts_ibfk_2` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `shifts_ibfk_3` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shift cash movements
CREATE TABLE `shift_cash_movements` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `shift_id` int(11) NOT NULL,
    `type` enum('in','out') NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `reason` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `recorded_by` int(11) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `shift_id` (`shift_id`),
    KEY `type` (`type`),
    KEY `recorded_by` (`recorded_by`),
    CONSTRAINT `shift_cash_movements_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `shift_cash_movements_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- AUDIT LOG AND SETTINGS
-- ================================================

-- Audit logs
CREATE TABLE `audit_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `table_name` varchar(50) DEFAULT NULL,
    `record_id` int(11) DEFAULT NULL,
    `old_values` json DEFAULT NULL,
    `new_values` json DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `action` (`action`),
    KEY `table_record` (`table_name`, `record_id`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings
CREATE TABLE `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `branch_id` int(11) DEFAULT NULL,
    `key` varchar(100) NOT NULL,
    `value` text DEFAULT NULL,
    `type` enum('string','integer','float','boolean','json') DEFAULT 'string',
    `description` text DEFAULT NULL,
    `is_public` tinyint(1) DEFAULT 0,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `branch_key` (`branch_id`, `key`),
    KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================
-- INSERT SAMPLE DATA
-- ================================================

-- Insert default branch
INSERT INTO `branches` (`name`, `display_name`, `address`, `phone`, `email`) VALUES
('main', 'المطعم الرئيسي', 'شارع الرشيد، بغداد', '+964123456789', 'info@restaurant.com');

-- Insert roles
INSERT INTO `roles` (`name`, `display_name`, `description`) VALUES
('owner', 'مالك', 'يملك جميع الصلاحيات'),
('admin', 'مدير', 'إدارة شاملة للنظام'),
('manager', 'مدير مطعم', 'إدارة الأصناف والأسعار والتقارير'),
('cashier', 'كاشير', 'إنشاء طلبات وفواتير وقبض'),
('waiter', 'نادل', 'فتح طاولات وإرسال للمطبخ'),
('kitchen', 'مطبخ', 'شاشة المطبخ وإدارة الطلبات'),
('delivery', 'توصيل', 'استلام طلبات التوصيل');

-- Insert permissions
INSERT INTO `permissions` (`name`, `display_name`, `description`, `group_name`) VALUES
-- Order permissions
('orders.create', 'إنشاء طلبات', 'يمكن إنشاء طلبات جديدة', 'orders'),
('orders.edit', 'تعديل طلبات', 'يمكن تعديل الطلبات المفتوحة', 'orders'),
('orders.cancel', 'إلغاء طلبات', 'يمكن إلغاء الطلبات', 'orders'),
('orders.send_to_kitchen', 'إرسال للمطبخ', 'يمكن إرسال الطلبات للمطبخ', 'orders'),
('orders.view', 'عرض الطلبات', 'يمكن عرض جميع الطلبات', 'orders'),

-- Payment permissions
('payments.create', 'إنشاء مدفوعات', 'يمكن إنشاء مدفوعات', 'payments'),
('payments.refund', 'استرداد مدفوعات', 'يمكن استرداد المدفوعات', 'payments'),
('payments.void', 'إلغاء فواتير', 'يمكن إلغاء الفواتير المدفوعة', 'payments'),

-- Discount permissions
('discounts.apply', 'تطبيق خصومات', 'يمكن تطبيق خصومات', 'discounts'),
('discounts.manage', 'إدارة خصومات', 'يمكن إدارة نظام الخصومات', 'discounts'),

-- Product permissions
('products.manage', 'إدارة المنتجات', 'يمكن إدارة المنتجات والأسعار', 'products'),
('products.view', 'عرض المنتجات', 'يمكن عرض المنتجات', 'products'),

-- Shift permissions
('shifts.open', 'فتح وردية', 'يمكن فتح وردية جديدة', 'shifts'),
('shifts.close', 'إغلاق وردية', 'يمكن إغلاق الوردية', 'shifts'),
('shifts.manage', 'إدارة ورديات', 'يمكن إدارة جميع الورديات', 'shifts'),

-- Report permissions
('reports.view', 'عرض تقارير', 'يمكن عرض التقارير', 'reports'),
('reports.export', 'تصدير تقارير', 'يمكن تصدير التقارير', 'reports'),
('reports.financial', 'تقارير مالية', 'يمكن عرض التقارير المالية', 'reports'),

-- User permissions
('users.manage', 'إدارة المستخدمين', 'يمكن إدارة المستخدمين والصلاحيات', 'users'),
('users.view', 'عرض المستخدمين', 'يمكن عرض المستخدمين', 'users'),

-- System permissions
('settings.manage', 'إدارة الإعدادات', 'يمكن إدارة إعدادات النظام', 'settings'),
('backup.create', 'إنشاء نسخة احتياطية', 'يمكن إنشاء نسخة احتياطية', 'system');

-- Assign permissions to roles
-- Owner gets all permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Admin gets most permissions except some system ones
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` 
WHERE `name` NOT IN ('users.manage', 'backup.create');

-- Manager permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` 
WHERE `name` IN (
    'orders.create', 'orders.edit', 'orders.view',
    'payments.create', 'payments.refund', 'payments.void',
    'discounts.apply', 'discounts.manage',
    'products.manage', 'products.view',
    'shifts.open', 'shifts.close', 'shifts.manage',
    'reports.view', 'reports.export', 'reports.financial'
);

-- Cashier permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` 
WHERE `name` IN (
    'orders.create', 'orders.edit', 'orders.send_to_kitchen', 'orders.view',
    'payments.create',
    'discounts.apply',
    'shifts.open', 'shifts.close',
    'products.view'
);

-- Waiter permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` 
WHERE `name` IN (
    'orders.create', 'orders.edit', 'orders.send_to_kitchen', 'orders.view',
    'products.view'
);

-- Kitchen permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` 
WHERE `name` IN (
    'orders.view'
);

-- Delivery permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 7, id FROM `permissions` 
WHERE `name` IN (
    'orders.view'
);

-- Insert sample users (password: password123)
INSERT INTO `users` (`username`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `branch_id`) VALUES
('admin', 'admin@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'أحمد', 'المدير', '+964123456789', 1),
('cashier', 'cashier@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'فاطمة', 'الكاشير', '+964123456790', 1),
('waiter', 'waiter@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'محمد', 'النادل', '+964123456791', 1),
('kitchen', 'kitchen@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'علي', 'الشيف', '+964123456792', 1);

-- Assign roles to users
INSERT INTO `user_roles` (`user_id`, `role_id`, `branch_id`) VALUES
(1, 1, 1), -- admin is owner
(2, 4, 1), -- cashier
(3, 5, 1), -- waiter
(4, 6, 1); -- kitchen

-- Insert default areas
INSERT INTO `areas` (`branch_id`, `name`, `display_name`, `sort_order`) VALUES
(1, 'main_hall', 'الصالة الرئيسية', 1),
(1, 'vip', 'الصالات المميزة', 2),
(1, 'outdoor', 'المساحة الخارجية', 3);

-- Insert sample tables
INSERT INTO `tables` (`branch_id`, `area_id`, `table_number`, `table_name`, `capacity`) VALUES
(1, 1, 'T01', 'طاولة 1', 4),
(1, 1, 'T02', 'طاولة 2', 4),
(1, 1, 'T03', 'طاولة 3', 6),
(1, 1, 'T04', 'طاولة 4', 2),
(1, 2, 'VIP1', 'طاولة مميزة 1', 8),
(1, 2, 'VIP2', 'طاولة مميزة 2', 8),
(1, 3, 'O01', 'طاولة خارجية 1', 4),
(1, 3, 'O02', 'طاولة خارجية 2', 4);

-- Insert categories
INSERT INTO `categories` (`branch_id`, `name`, `display_name`, `sort_order`) VALUES
(1, 'appetizers', 'المقبلات', 1),
(1, 'main_courses', 'الأطباق الرئيسية', 2),
(1, 'beverages', 'المشروبات', 3),
(1, 'desserts', 'الحلويات', 4),
(1, 'grill', 'المشاوي', 5);

-- Insert sample products
INSERT INTO `products` (`branch_id`, `category_id`, `name_ar`, `name_en`, `description_ar`, `description_en`, `base_price`) VALUES
-- Appetizers
(1, 1, 'حمص بالطحينة', 'Hummus with Tahini', 'حمص بالطحينة مع زيت الزيتون', 'Traditional hummus with tahini and olive oil', 15000.00),
(1, 1, 'متبل', 'Mutabal', 'سلطة باذنجان بالطحينة', 'Grilled eggplant salad with tahini', 12000.00),
(1, 1, 'تبولة', 'Tabbouleh', 'سلطة البرغل بالطماطم والخضار', 'Parsley and bulgur salad with tomatoes', 10000.00),

-- Main courses
(1, 2, 'كباب لحم', 'Beef Kebab', 'كباب لحم مشوي مع الأرز', 'Grilled beef kebab with rice', 25000.00),
(1, 2, 'كباب دجاج', 'Chicken Kebab', 'كباب دجاج مشوي مع الأرز', 'Grilled chicken kebab with rice', 20000.00),
(1, 2, 'برياني لحم', 'Beef Biryani', 'أرز برياني باللحم والخضار', 'Aromatic rice with beef and spices', 28000.00),
(1, 2, 'برياني دجاج', 'Chicken Biryani', 'أرز برياني بالدجاج والخضار', 'Aromatic rice with chicken and spices', 23000.00),

-- Beverages
(1, 3, 'شاي أحمر', 'Black Tea', 'شاي أحمر بالسكر', 'Traditional black tea with sugar', 3000.00),
(1, 3, 'قهوة عربية', 'Arabic Coffee', 'قهوة عربية تقليدية', 'Traditional Arabic coffee', 5000.00),
(1, 3, 'عصير برتقال', 'Orange Juice', 'عصير برتقال طازج', 'Fresh orange juice', 8000.00),
(1, 3, 'ماء معدني', 'Mineral Water', 'ماء معدني 500 مل', 'Mineral water 500ml', 2000.00),

-- Desserts
(1, 4, 'كنافة نابلسية', 'Knafeh Nabulsieh', 'كنافة نابلسية بالجبن', 'Traditional knafeh with cheese', 12000.00),
(1, 4, 'بقلاوة', 'Baklava', 'بقلاوة بالفستق الحلبي', 'Baklava with pistachios', 15000.00),
(1, 4, 'مهلبية', 'Muhallabia', 'مهلبية بالحليب والورد', 'Rice pudding with milk and rose water', 8000.00),

-- Grill
(1, 5, 'شيش لحم', 'Lamb Shish', 'شيش لحم مشوي', 'Grilled lamb skewers', 30000.00),
(1, 5, 'شيش دجاج', 'Chicken Shish', 'شيش دجاج مشوي', 'Grilled chicken skewers', 25000.00),
(1, 5, 'كباب لحم', 'Beef Kebab', 'كباب لحم مشوي', 'Grilled beef kebab', 28000.00);

-- Insert sample taxes
INSERT INTO `taxes` (`name`, `display_name`, `type`, `rate`, `is_included`) VALUES
('service', 'رسوم الخدمة', 'percentage', 10.00, 1),
('vat', 'ضريبة القيمة المضافة', 'percentage', 15.00, 0);

-- Insert sample discounts
INSERT INTO `discounts` (`name`, `type`, `value`, `min_order_amount`, `max_discount_amount`, `is_active`) VALUES
('happy_hour', 'percentage', 20.00, 50000.00, 20000.00, 1),
('lunch_special', 'fixed', 5000.00, 30000.00, NULL, 1),
('student_discount', 'percentage', 15.00, 20000.00, 10000.00, 1);

-- Insert system settings
INSERT INTO `settings` (`branch_id`, `key`, `value`, `type`, `description`) VALUES
(1, 'restaurant_name', 'مطعم النخيل', 'string', 'اسم المطعم'),
(1, 'restaurant_name_en', 'Al-Nakhil Restaurant', 'string', 'اسم المطعم بالإنجليزية'),
(1, 'currency', 'IQD', 'string', 'العملة'),
(1, 'currency_symbol', 'د.ع', 'string', 'رمز العملة'),
(1, 'currency_position', 'after', 'string', 'موضع العملة'),
(1, 'tax_included', '1', 'boolean', 'الضرائب مشمولة في السعر'),
(1, 'default_tax_rate', '15.0', 'float', 'معدل الضريبة الافتراضي'),
(1, 'receipt_footer', 'شكراً لزيارتكم', 'string', 'نص في أسفل الإيصال'),
(1, 'kitchen_polling_interval', '3', 'integer', 'فترة تحديث شاشة المطبخ بالثواني'),
(1, 'timezone', 'Asia/Baghdad', 'string', 'المنطقة الزمنية'),
(1, 'date_format', 'Y-m-d', 'string', 'تنسيق التاريخ'),
(1, 'time_format', 'H:i:s', 'string', 'تنسيق الوقت'),
(1, 'language', 'ar', 'string', 'اللغة الافتراضية'),
(1, 'backup_retention_days', '30', 'integer', 'فترة الاحتفاظ بالنسخ الاحتياطية');

COMMIT;