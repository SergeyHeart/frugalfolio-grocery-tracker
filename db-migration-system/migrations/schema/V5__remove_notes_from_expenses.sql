-- V5__remove_notes_from_expenses.sql
-- Remove notes column from grocery_expenses table

SET autocommit=0;
START TRANSACTION;

ALTER TABLE `grocery_expenses`
DROP COLUMN `notes`;

COMMIT;
