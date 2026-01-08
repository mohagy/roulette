/**
 * Complete Database Migration to Firebase using Node.js
 * 
 * This script exports ALL MySQL tables and migrates them to Firebase Realtime Database
 * 
 * Usage: node migrate-to-firebase.js
 */

const https = require('https');
const mysql = require('mysql2/promise');

const FIREBASE_URL = 'https://roulette-2f902-default-rtdb.firebaseio.com';

// MySQL connection config
const dbConfig = {
    host: 'localhost',
    user: 'root',
    password: '',
    database: 'roulette'
};

/**
 * Write data to Firebase using REST API
 */
function writeToFirebase(path, data) {
    return new Promise((resolve, reject) => {
        const url = `${FIREBASE_URL}/${path}.json`;
        const dataStr = JSON.stringify(data);
        
        const options = {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(dataStr)
            }
        };
        
        const req = https.request(url, options, (res) => {
            let responseData = '';
            res.on('data', (chunk) => {
                responseData += chunk;
            });
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    resolve(true);
                } else {
                    reject(new Error(`HTTP ${res.statusCode}: ${responseData}`));
                }
            });
        });
        
        req.on('error', reject);
        req.write(dataStr);
        req.end();
    });
}

/**
 * Batch write to Firebase
 */
async function batchWriteToFirebase(path, dataArray) {
    let success = 0;
    let failed = 0;
    
    for (const [key, data] of Object.entries(dataArray)) {
        try {
            await writeToFirebase(`${path}/${key}`, data);
            success++;
            if (success % 10 === 0) {
                console.log(`  Migrated ${success} items...`);
            }
        } catch (error) {
            failed++;
            console.error(`  Failed to write ${path}/${key}:`, error.message);
        }
    }
    
    return { success, failed };
}

/**
 * Convert MySQL row to Firebase-compatible object
 */
function convertRowToFirebase(row) {
    const converted = {};
    for (const [key, value] of Object.entries(row)) {
        if (value === null) {
            converted[key] = null;
        } else if (value instanceof Date) {
            converted[key] = value.toISOString();
        } else if (typeof value === 'bigint') {
            converted[key] = Number(value);
        } else if (Buffer.isBuffer(value)) {
            converted[key] = value.toString('base64');
        } else {
            converted[key] = value;
        }
    }
    return converted;
}

/**
 * Get primary key column name for a table
 */
async function getPrimaryKey(connection, tableName) {
    try {
        const [rows] = await connection.execute(
            `SHOW KEYS FROM \`${tableName}\` WHERE Key_name = 'PRIMARY'`
        );
        if (rows.length > 0) {
            return rows[0].Column_name;
        }
        // If no primary key, try to find an auto-increment column
        const [columns] = await connection.execute(
            `SHOW COLUMNS FROM \`${tableName}\` WHERE Extra LIKE '%auto_increment%'`
        );
        if (columns.length > 0) {
            return columns[0].Field;
        }
        return null;
    } catch (error) {
        return null;
    }
}

/**
 * Migrate a single table to Firebase
 */
async function migrateTable(connection, tableName, results) {
    try {
        console.log(`\n📋 Migrating table: ${tableName}...`);
        
        // Get all rows from the table
        const [rows] = await connection.execute(`SELECT * FROM \`${tableName}\``);
        
        if (rows.length === 0) {
            console.log(`   ⚠️  Table ${tableName} is empty, skipping`);
            return;
        }
        
        // Get primary key
        const primaryKey = await getPrimaryKey(connection, tableName);
        
        // Convert rows to Firebase format
        const tableData = {};
        for (const row of rows) {
            const convertedRow = convertRowToFirebase(row);
            // Use primary key as Firebase key, or use row index
            const key = primaryKey && row[primaryKey] !== undefined 
                ? String(row[primaryKey]) 
                : `row_${rows.indexOf(row)}`;
            tableData[key] = convertedRow;
        }
        
        // Write to Firebase under mysql_tables/{tableName}
        const firebasePath = `mysql_tables/${tableName}`;
        const batchResult = await batchWriteToFirebase(firebasePath, tableData);
        
        results.migrations.push(`${tableName} → ${firebasePath}/ (${batchResult.success} rows)`);
        results.counts[tableName] = batchResult.success;
        
        if (batchResult.failed > 0) {
            results.errors.push(`${tableName}: ${batchResult.failed} rows failed`);
        }
        
        console.log(`   ✅ Migrated ${batchResult.success} rows from ${tableName}`);
        
    } catch (error) {
        console.error(`   ❌ Error migrating ${tableName}:`, error.message);
        results.errors.push(`${tableName}: ${error.message}`);
    }
}

/**
 * Migrate special roulette tables to their Firebase paths (for backward compatibility)
 */
async function migrateSpecialTables(connection, results) {
    console.log('\n🎯 Migrating special roulette tables to Firebase paths...\n');
    
    // Get latest draw number once for all special tables
    let latestDrawNumber = 0;
    try {
        const [latestRows] = await connection.execute(
            "SELECT MAX(draw_number) as max_draw FROM detailed_draw_results"
        );
        latestDrawNumber = latestRows[0]?.max_draw || 0;
    } catch (error) {
        console.log('   ⚠️  Could not get latest draw number');
    }
    
    // 1. ROULETTE_STATE → gameState/current
    try {
        console.log('1️⃣  Migrating roulette_state → gameState/current...');
        const [stateRows] = await connection.execute(
            "SELECT * FROM roulette_state ORDER BY id DESC LIMIT 1"
        );
        
        const [last5Rows] = await connection.execute(
            "SELECT * FROM detailed_draw_results ORDER BY draw_number DESC LIMIT 5"
        );
        
        const rollHistory = [];
        const rollColors = [];
        for (const draw of last5Rows.reverse()) {
            rollHistory.push(parseInt(draw.winning_number || 0));
            rollColors.push(draw.winning_color || draw.color || 'black');
        }
        
        if (stateRows.length > 0) {
            const state = stateRows[0];
            const gameState = {
                drawNumber: latestDrawNumber > 0 ? latestDrawNumber : (state.draw_number || 1),
                nextDrawNumber: latestDrawNumber > 0 ? latestDrawNumber + 1 : (state.next_draw_number || 2),
                winningNumber: last5Rows.length > 0 ? parseInt(last5Rows[0].winning_number || 0) : (state.winning_number || null),
                nextWinningNumber: state.next_winning_number || null,
                manualMode: (state.manual_mode || 0) == 1,
                rollHistory: rollHistory,
                rollColors: rollColors,
                lastDrawFormatted: latestDrawNumber > 0 ? `#${latestDrawNumber}` : '#0',
                nextDrawFormatted: latestDrawNumber > 0 ? `#${latestDrawNumber + 1}` : '#1',
                updatedAt: state.updated_at || new Date().toISOString()
            };
            
            await writeToFirebase('gameState/current', gameState);
            results.migrations.push('roulette_state → gameState/current');
            console.log('   ✅ Migrated game state\n');
        }
    } catch (error) {
        console.error('   ❌ Error migrating roulette_state:', error.message);
    }
    
    // 2. DETAILED_DRAW_RESULTS → draws/
    try {
        console.log('2️⃣  Migrating detailed_draw_results → draws/...');
        const [drawRows] = await connection.execute(
            "SELECT * FROM detailed_draw_results ORDER BY draw_number ASC"
        );
        
        const drawsData = {};
        for (const draw of drawRows) {
            const drawNumber = parseInt(draw.draw_number || draw.id);
            if (drawNumber <= 0) continue;
            
            drawsData[drawNumber] = {
                drawId: draw.draw_id || `DRAW-${new Date(draw.timestamp || Date.now()).toISOString().slice(0,10).replace(/-/g,'')}-${drawNumber}`,
                drawNumber: drawNumber,
                winningNumber: parseInt(draw.winning_number || 0),
                winningColor: draw.winning_color || draw.color || 'black',
                isManual: parseInt(draw.is_manual || 0),
                notes: draw.notes || '',
                timestamp: draw.timestamp || draw.created_at || new Date().toISOString(),
                createdAt: draw.created_at || new Date().toISOString()
            };
        }
        
        if (Object.keys(drawsData).length > 0) {
            const drawResult = await batchWriteToFirebase('draws', drawsData);
            results.migrations.push(`detailed_draw_results → draws/ (${drawResult.success} draws)`);
            results.counts.draws = drawResult.success;
            console.log(`   ✅ Migrated ${drawResult.success} draws\n`);
        }
    } catch (error) {
        console.error('   ❌ Error migrating detailed_draw_results:', error.message);
    }
    
    // 3. ROULETTE_ANALYTICS → analytics/current
    try {
        console.log('3️⃣  Migrating roulette_analytics → analytics/current...');
        const [analyticsRows] = await connection.execute(
            "SELECT * FROM roulette_analytics WHERE id = 1"
        );
        
        const [allDrawRows] = await connection.execute(
            "SELECT winning_number FROM detailed_draw_results ORDER BY draw_number ASC"
        );
        
        const allSpinsFromDraws = [];
        const numberFrequencyFromDraws = new Array(37).fill(0);
        
        for (const draw of allDrawRows.reverse()) {
            const num = parseInt(draw.winning_number || 0);
            if (num >= 0 && num <= 36) {
                if (allSpinsFromDraws.length < 100) {
                    allSpinsFromDraws.unshift(num);
                }
                numberFrequencyFromDraws[num]++;
            }
        }
        
        const analyticsData = {
            allSpins: allSpinsFromDraws,
            numberFrequency: numberFrequencyFromDraws,
            currentDrawNumber: latestDrawNumber > 0 ? latestDrawNumber : 1,
            lastUpdated: analyticsRows[0]?.last_updated || analyticsRows[0]?.created_at || new Date().toISOString()
        };
        
        await writeToFirebase('analytics/current', analyticsData);
        results.migrations.push('roulette_analytics → analytics/current');
        console.log('   ✅ Migrated analytics\n');
    } catch (error) {
        console.error('   ❌ Error migrating roulette_analytics:', error.message);
    }
    
    // 4. BETTING_SLIPS → bettingSlips/
    try {
        console.log('4️⃣  Migrating betting_slips → bettingSlips/...');
        const [slipRows] = await connection.execute(
            "SELECT * FROM betting_slips ORDER BY slip_id ASC"
        );
        
        const slipsData = {};
        for (const slip of slipRows) {
            const slipId = slip.slip_id || slip.slip_number;
            slipsData[slipId] = {
                slipId: slipId,
                slipNumber: slip.slip_number || '',
                userId: parseInt(slip.user_id || 0),
                totalStake: parseFloat(slip.total_stake || 0),
                potentialPayout: parseFloat(slip.potential_payout || 0),
                isPaid: (slip.is_paid || 0) == 1,
                isCancelled: (slip.is_cancelled || 0) == 1,
                drawNumber: parseInt(slip.draw_number || 0),
                winningNumber: slip.winning_number ? parseInt(slip.winning_number) : null,
                createdAt: slip.created_at || new Date().toISOString(),
                updatedAt: slip.updated_at || new Date().toISOString()
            };
        }
        
        if (Object.keys(slipsData).length > 0) {
            const slipResult = await batchWriteToFirebase('bettingSlips', slipsData);
            results.migrations.push(`betting_slips → bettingSlips/ (${slipResult.success} slips)`);
            console.log(`   ✅ Migrated ${slipResult.success} betting slips\n`);
        }
    } catch (error) {
        console.error('   ❌ Error migrating betting_slips:', error.message);
    }
    
    // 5. BETS → bets/
    try {
        console.log('5️⃣  Migrating bets → bets/...');
        const [betRows] = await connection.execute(
            "SELECT * FROM bets ORDER BY bet_id ASC"
        );
        
        const betsData = {};
        for (const bet of betRows) {
            betsData[bet.bet_id] = {
                betId: bet.bet_id,
                userId: parseInt(bet.user_id || 0),
                betType: bet.bet_type || '',
                betDescription: bet.bet_description || '',
                betAmount: parseFloat(bet.bet_amount || 0),
                multiplier: parseFloat(bet.multiplier || 0),
                potentialReturn: parseFloat(bet.potential_return || 0),
                createdAt: bet.created_at || new Date().toISOString()
            };
        }
        
        if (Object.keys(betsData).length > 0) {
            const betResult = await batchWriteToFirebase('bets', betsData);
            results.migrations.push(`bets → bets/ (${betResult.success} bets)`);
            console.log(`   ✅ Migrated ${betResult.success} bets\n`);
        }
    } catch (error) {
        console.error('   ❌ Error migrating bets:', error.message);
    }
    
    // 6. UPDATE DRAW INFO
    try {
        console.log('6️⃣  Updating drawInfo...');
        const drawInfo = {
            currentDraw: latestDrawNumber > 0 ? latestDrawNumber : 1,
            nextDraw: latestDrawNumber > 0 ? latestDrawNumber + 1 : 2
        };
        
        await writeToFirebase('gameState/drawInfo', drawInfo);
        results.migrations.push('drawInfo → gameState/drawInfo');
        console.log('   ✅ Updated drawInfo\n');
    } catch (error) {
        console.error('   ❌ Error updating drawInfo:', error.message);
    }
}

async function migrateDatabase() {
    console.log('🔥 Starting Complete Database Migration to Firebase...\n');
    
    let connection;
    
    try {
        // Connect to MySQL
        console.log('📊 Connecting to MySQL database...');
        connection = await mysql.createConnection(dbConfig);
        console.log('✅ Connected to MySQL\n');
        
        const results = {
            migrations: [],
            counts: {},
            errors: []
        };
        
        // Get all table names
        console.log('📋 Discovering all tables in database...');
        const [tables] = await connection.execute(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?",
            [dbConfig.database]
        );
        
        const tableNames = tables.map(t => t.TABLE_NAME);
        console.log(`   ✅ Found ${tableNames.length} tables: ${tableNames.join(', ')}\n`);
        
        // Migrate special roulette tables first (for backward compatibility)
        await migrateSpecialTables(connection, results);
        
        // Migrate all tables to mysql_tables/ path
        console.log('\n📦 Migrating all tables to mysql_tables/ path...\n');
        for (const tableName of tableNames) {
            await migrateTable(connection, tableName, results);
        }
        
        // ============================================
        // SUMMARY
        // ============================================
        console.log('\n\n🎉 Migration Complete!\n');
        console.log('📊 Summary:');
        console.log(`   ✅ ${results.migrations.length} migrations completed`);
        console.log(`   📋 ${tableNames.length} tables migrated`);
        
        if (Object.keys(results.counts).length > 0) {
            console.log('\n   📈 Row counts:');
            for (const [key, value] of Object.entries(results.counts)) {
                console.log(`      ${key}: ${value} rows`);
            }
        }
        
        if (results.errors.length > 0) {
            console.log('\n   ⚠️  Errors:');
            results.errors.forEach(err => console.log(`      ${err}`));
        }
        
        console.log('\n🔗 View your data at:');
        console.log('   https://console.firebase.google.com/project/roulette-2f902/database');
        console.log('\n   Special paths:');
        console.log('   - gameState/current (roulette state)');
        console.log('   - draws/ (draw results)');
        console.log('   - analytics/current (analytics)');
        console.log('   - bettingSlips/ (betting slips)');
        console.log('   - bets/ (bets)');
        console.log('   - mysql_tables/ (all MySQL tables)\n');
        
    } catch (error) {
        console.error('\n❌ Migration failed:', error);
        process.exit(1);
    } finally {
        if (connection) {
            await connection.end();
        }
    }
}

// Run migration
migrateDatabase().catch(console.error);

