-- V4__add_notes_to_expenses.sql
-- Add notes column to grocery_expenses table for additional item details

SET autocommit=0;
START TRANSACTION;

ALTER TABLE `grocery_expenses`
ADD COLUMN `notes` TEXT NULL DEFAULT NULL
COMMENT 'Additional notes or details about the grocery item'
AFTER `purchase_date`;

COMMIT;
