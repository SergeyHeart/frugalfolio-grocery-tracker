<?php

require_once __DIR__ . '/src/MigrationManager.php';

try {
    $manager = new DbMigration\MigrationManager();
    $manager->migrate();
    echo "Migrations completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
