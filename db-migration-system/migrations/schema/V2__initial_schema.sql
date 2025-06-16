-- V2__initial_schema.sql
-- Initial database schema

CREATE TABLE IF NOT EXISTS `users` (
    `user_id` int NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `categories` (
    `category_id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `expense_categories` (
    `expense_id` int unsigned NOT NULL,
    `category_id` int NOT NULL,
    PRIMARY KEY (`expense_id`,`category_id`),
    KEY `fk_category` (`category_id`),
    CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_expense` FOREIGN KEY (`expense_id`) REFERENCES `grocery_expenses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `grocery_expenses` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `item_name` varchar(255) NOT NULL,
    `quantity` int unsigned NOT NULL,
    `weight` decimal(10,3) NOT NULL,
    `unit` varchar(10) NOT NULL,
    `price_per_unit` decimal(10,2) unsigned NOT NULL,
    `is_weight_based` tinyint NOT NULL,
    `total_price` decimal(10,2) DEFAULT NULL,
    `shop` varchar(50) NOT NULL,
    `purchase_date` date NOT NULL,
    `user_id` int NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_expense_user` (`user_id`),
    CONSTRAINT `fk_expense_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
