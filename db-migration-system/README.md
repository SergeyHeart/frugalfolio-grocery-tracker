# Database Migration System

A simple database migration system for FrugalFolio project.

## How It Works

Migrations are SQL files that make changes to the database schema. They are executed in order based on their version numbers (V1, V2, V3, etc.).

### File Naming Convention
```
V{number}__{description}.sql
```
Example: `V1__create_users_table.sql`

### Directory Structure
```
db-migration-system/
├── migrations/
│   ├── schema/     # Database structure changes
│   └── data/       # Data changes (if needed)
├── logs/           # Migration logs
└── src/           # Migration system code
```

## How to Use

### Adding a New Migration
1. Create a new SQL file in `migrations/schema/`
2. Name it following the convention: `V{next_number}__{description}.sql`
3. Write your SQL changes in the file

Example:
```sql
SET autocommit=0;
START TRANSACTION;

ALTER TABLE table_name
ADD COLUMN new_column VARCHAR(255);

COMMIT;
```

### Running Migrations
```bash
cd /path/to/db-migration-system
php migrate.php
```

### Check Migration Status
```sql
SELECT * FROM grocery_expenses_db.schema_versions;
```

### Reverting Changes
To revert a change, create a new migration that undoes the previous change.
Example: To remove a column, create `V{next_number}__remove_column.sql`

## Common Migration Examples

### Adding a Column
```sql
SET autocommit=0;
START TRANSACTION;

ALTER TABLE table_name
ADD COLUMN column_name VARCHAR(255);

COMMIT;
```

### Removing a Column
```sql
SET autocommit=0;
START TRANSACTION;

ALTER TABLE table_name
DROP COLUMN column_name;

COMMIT;
```

### Creating a New Table
```sql
SET autocommit=0;
START TRANSACTION;

CREATE TABLE new_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    column_name VARCHAR(255)
);

COMMIT;
```

### Adding an Index
```sql
SET autocommit=0;
START TRANSACTION;

CREATE INDEX index_name ON table_name (column_name);

COMMIT;
```
