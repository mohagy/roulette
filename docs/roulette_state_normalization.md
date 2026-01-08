# Roulette State Table Normalization

## Overview

This document describes the normalization of the `roulette_state` table to improve data storage efficiency and make the database more normalized. The previous implementation stored historical data in concatenated arrays/strings within each new row, which was inefficient and made it difficult to query specific historical information.

## Changes Made

### 1. Table Structure Changes

#### Previous Structure
```sql
CREATE TABLE roulette_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    roll_history TEXT,          -- Comma-separated list of previous winning numbers
    roll_colors TEXT,           -- Comma-separated list of previous winning colors
    last_draw VARCHAR(10),      -- e.g., "#128"
    next_draw VARCHAR(10),      -- e.g., "#129"
    current_draw INT,
    countdown_time INT,
    end_time VARCHAR(20),
    updated_at TIMESTAMP,
    current_draw_number INT,
    winning_number INT,
    next_draw_winning_number INT,
    manual_mode TINYINT(1)
)
```

#### New Structure
```sql
CREATE TABLE roulette_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_type VARCHAR(50) NOT NULL,  -- Type of state change (draw_result, timer_update, etc.)
    draw_number INT NOT NULL,         -- Current draw number
    next_draw_number INT NOT NULL,    -- Next draw number
    countdown_time INT DEFAULT 180,   -- Countdown timer in seconds
    end_time VARCHAR(20) DEFAULT NULL, -- End time in milliseconds timestamp
    winning_number INT DEFAULT NULL,  -- Winning number for current draw
    next_winning_number INT DEFAULT NULL, -- Winning number for next draw (if manually set)
    manual_mode TINYINT(1) DEFAULT 0, -- Whether manual mode is enabled
    additional_data JSON DEFAULT NULL, -- Any additional data specific to this state change
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

#### New Draw History Table
```sql
CREATE TABLE roulette_draw_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    draw_number INT NOT NULL,         -- The draw number
    winning_number INT NOT NULL,      -- The winning number (0-36)
    winning_color VARCHAR(10) NOT NULL, -- Color of the winning number (red, black, green)
    draw_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, -- When the draw occurred
    is_manual TINYINT(1) NOT NULL DEFAULT 0, -- Whether this was a manual draw
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (draw_number)
)
```

### 2. Key Improvements

1. **Normalized Data Storage**: Each row now represents a specific state change event rather than carrying forward all historical data.

2. **Typed State Changes**: The `state_type` field indicates what kind of state change occurred (e.g., 'draw_result', 'timer_update', 'mode_change').

3. **Separate Draw History**: Historical draw results are now stored in a dedicated `roulette_draw_history` table, making it easier to query past results.

4. **Flexible Additional Data**: The `additional_data` JSON field allows storing event-specific data without requiring schema changes.

5. **Better Indexing**: Indexes on key fields improve query performance.

### 3. Modified Files

The following files were updated to work with the new structure:

- `save_state.php`: Updated to store state changes with appropriate state types
- `load_state.php`: Updated to load and process state data from the new structure
- `api/save_draw_result.php`: Updated to store draw results in both tables
- `sql/normalize_roulette_state.sql`: SQL script for the table structure changes
- `migrate_roulette_state.php`: Migration script to convert existing data

## Migration Process

To migrate existing data to the new structure:

1. Run the migration script:
   ```
   php migrate_roulette_state.php
   ```

2. The script will:
   - Create a backup of the current `roulette_state` table
   - Create the new normalized table structure
   - Migrate data from the old structure to the new one
   - Extract historical draw data into the `roulette_draw_history` table

3. If any errors occur during migration, the script will attempt to restore from the backup.

## Querying the New Structure

### Getting the Current State

```sql
SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1;
```

### Getting Historical Draw Results

```sql
SELECT * FROM roulette_draw_history ORDER BY draw_number DESC LIMIT 10;
```

### Finding State Changes of a Specific Type

```sql
SELECT * FROM roulette_state WHERE state_type = 'draw_result' ORDER BY id DESC LIMIT 5;
```

### Getting State at a Specific Draw Number

```sql
SELECT * FROM roulette_state WHERE draw_number = 123 ORDER BY id DESC LIMIT 1;
```

## Backward Compatibility

The updated code maintains backward compatibility by:

1. Returning the same JSON structure in API responses
2. Storing formatted draw numbers (e.g., "#128") in the `additional_data` field
3. Maintaining the roll history and colors in the `additional_data` field

## Benefits of the New Structure

1. **Improved Data Integrity**: Each state change is a clean, separate record
2. **Better Query Performance**: Easier to query specific historical information
3. **Reduced Redundancy**: No duplication of historical data in each new row
4. **More Detailed History**: Each state change includes its specific purpose
5. **Future Extensibility**: New state types can be added without schema changes
