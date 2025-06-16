-- V3__test_migration.sql
-- Test migration to verify system functionality

CREATE TABLE IF NOT EXISTS `test_migration` (
    `id` int NOT NULL AUTO_INCREMENT,
    `test_column` varchar(50) NOT NULL,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
