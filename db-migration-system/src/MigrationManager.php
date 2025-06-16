<?php

namespace DbMigration;

require_once __DIR__ . '/config/DatabaseConfig.php';

use DbMigration\Config\DatabaseConfig;
use PDO;
use PDOException;

class MigrationManager {
    private $pdo;
    private $schemaPath;
    private $dataPath;
    private $logPath;

    public function __construct() {
        $this->schemaPath = __DIR__ . '/../migrations/schema/';
        $this->dataPath = __DIR__ . '/../migrations/data/';
        $this->logPath = __DIR__ . '/../logs/';
        $this->connect();
        $this->ensureVersionTable();
        $this->log("Migration manager initialized successfully");
    }

    private function connect() {
        $config = DatabaseConfig::getConfig();
        try {
            $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
            // First connect without database to ensure we can create it if needed
            $this->pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            // Try to create database if it doesn't exist
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['dbname']}`");
            
            // Now connect with the database selected
            $this->pdo = new PDO(
                $dsn . ";dbname={$config['dbname']}",
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            $this->log("Database connection established successfully");
        } catch (PDOException $e) {
            $error = "Connection failed: " . $e->getMessage();
            $this->log($error);
            throw new PDOException($error);
        }
    }

    private function ensureVersionTable() {
        $sql = file_get_contents($this->schemaPath . 'V1__schema_versions.sql');
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            die("Failed to create version table: " . $e->getMessage());
        }
    }

    public function migrate() {
        // Get all migration files
        $files = glob($this->schemaPath . 'V*__*.sql');
        sort($files); // Ensure order by version

        foreach ($files as $file) {
            $this->processMigrationFile($file);
        }
    }

    private function processMigrationFile($file) {
        $filename = basename($file);
        preg_match('/V(\d+)__(.+)\.sql/', $filename, $matches);
        
        if (count($matches) !== 3) {
            $error = "Invalid migration filename format: $filename";
            $this->log($error);
            throw new Exception($error);
        }

        $version = $matches[1];
        if ($this->isVersionApplied($version)) {
            $this->log("Skipping already applied migration: $filename");
            return;
        }

        $description = str_replace('_', ' ', $matches[2]);
        $sql = file_get_contents($file);
        if ($sql === false) {
            $error = "Failed to read migration file: $filename";
            $this->log($error);
            throw new Exception($error);
        }
        
        $checksum = hash('sha256', $sql);
        $this->log("Processing migration: $filename");

        try {
            // Start transaction
            $this->pdo->beginTransaction();
            $this->log("Transaction started for: $filename");
            
            // Split SQL into individual statements
            $statements = array_filter(
                array_map('trim', 
                    explode(';', $sql)
                ),
                'strlen'
            );
            
            // Execute each statement
            foreach ($statements as $statement) {
                $this->pdo->exec($statement);
            }
            
            // Record migration
            $stmt = $this->pdo->prepare("
                INSERT INTO schema_versions (version, description, script_name, checksum) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$version, $description, $filename, $checksum]);
            
            // Commit transaction
            $this->pdo->commit();
            $this->log("Successfully applied migration: $filename");
            
        } catch (Exception $e) {
            $error = "Failed to apply migration $filename: " . $e->getMessage();
            $this->log($error);
            
            try {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                    $this->log("Transaction rolled back for: $filename");
                }
            } catch (Exception $rollbackError) {
                $this->log("Rollback failed: " . $rollbackError->getMessage());
            }
            
            throw new Exception($error);
        }
    }

    private function isVersionApplied($version) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM schema_versions WHERE version = ?");
        $stmt->execute([$version]);
        return $stmt->fetchColumn() !== false;
    }

    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->logPath . 'migrations.log', $logMessage, FILE_APPEND);
    }
}
