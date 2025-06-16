-- V6__remove_test_migration_table.sql
-- Remove the test migration table that was created in V3

SET autocommit=0;
START TRANSACTION;

DROP TABLE IF EXISTS `test_migration`;

COMMIT;
