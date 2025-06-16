-- V1__schema_versions.sql
-- Initial schema versioning table

CREATE TABLE IF NOT EXISTS `schema_versions` (
    `version` VARCHAR(20) NOT NULL,
    `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `description` VARCHAR(255) NOT NULL,
    `script_name` VARCHAR(255) NOT NULL,
    `checksum` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
