// TV Display Debug Script
// Run this in the browser console on the TV display page to diagnose issues

console.log('🔍 TV DISPLAY DEBUG: Starting diagnostic...');

// Check current state of all relevant variables
console.log('📊 CURRENT STATE:');
console.log('  window.allSpins:', window.allSpins);
console.log('  window.rolledNumbersArray:', window.rolledNumbersArray);
console.log('  window.rolledNumbersColorArray:', window.rolledNumbersColorArray);
console.log('  window.currentDrawNumber:', window.currentDrawNumber);

// Check if data persistence system is loaded
console.log('🔧 SYSTEM STATUS:');
console.log('  DataPersistence loaded:', typeof window.DataPersistence);
console.log('  displayRollHistory function:', typeof window.displayRollHistory);
console.log('  updateRecentNumbers function:', typeof window.updateRecentNumbers);

// Check current DOM elements
console.log('🖥️ DOM ELEMENTS:');
for (let i = 1; i <= 5; i++) {
    const element = document.querySelector(`.roll${i}`);
    if (element) {
        console.log(`  .roll${i}: "${element.textContent}" (classes: ${element.className})`);
    } else {
        console.log(`  .roll${i}: NOT FOUND`);
    }
}

// Test loading data from database
async function testDatabaseLoad() {
    console.log('🔄 TESTING DATABASE LOAD:');
    try {
        const response = await fetch('/slipp/load_analytics.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            const allSpins = JSON.parse(data.all_spins || '[]');
            console.log('  Database all_spins:', allSpins);
            console.log('  Database current_draw_number:', data.current_draw_number);
            console.log('  Recent 5 from DB:', allSpins.slice(0, 5));
            
            return allSpins.slice(0, 5);
        } else {
            console.error('  Database error:', data.message);
            return null;
        }
    } catch (error) {
        console.error('  Database fetch error:', error);
        return null;
    }
}

// Test manual DOM update
function testManualDOMUpdate(numbers) {
    console.log('🔧 TESTING MANUAL DOM UPDATE:');
    console.log('  Numbers to display:', numbers);
    
    const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    
    for (let i = 0; i < numbers.length && i < 5; i++) {
        const element = document.querySelector(`.roll${i + 1}`);
        if (element) {
            const number = numbers[i];
            element.innerHTML = number;
            
            // Clear existing classes
            element.classList.remove("roll-red", "roll-black", "roll-green");
            
            // Determine color
            let colorClass = 'green';
            if (number === 0) {
                colorClass = 'green';
            } else if (rouletteNumbersRed.includes(parseInt(number))) {
                colorClass = 'red';
            } else {
                colorClass = 'black';
            }
            
            element.classList.add(`roll-${colorClass}`);
            console.log(`  Set .roll${i + 1} to ${number} (${colorClass})`);
        } else {
            console.error(`  Element .roll${i + 1} not found`);
        }
    }
}

// Test all display functions
function testDisplayFunctions() {
    console.log('🎯 TESTING DISPLAY FUNCTIONS:');
    
    if (typeof window.displayRollHistory === 'function') {
        console.log('  Calling displayRollHistory...');
        try {
            window.displayRollHistory();
            console.log('  ✅ displayRollHistory completed');
        } catch (error) {
            console.error('  ❌ displayRollHistory error:', error);
        }
    } else {
        console.log('  ❌ displayRollHistory not available');
    }
    
    if (typeof window.updateRecentNumbers === 'function') {
        console.log('  Calling updateRecentNumbers...');
        try {
            window.updateRecentNumbers();
            console.log('  ✅ updateRecentNumbers completed');
        } catch (error) {
            console.error('  ❌ updateRecentNumbers error:', error);
        }
    } else {
        console.log('  ❌ updateRecentNumbers not available');
    }
    
    if (typeof window.DataPersistence !== 'undefined' && typeof window.DataPersistence.forceUpdateRecentNumbersDOM === 'function') {
        console.log('  Calling DataPersistence.forceUpdateRecentNumbersDOM...');
        try {
            window.DataPersistence.forceUpdateRecentNumbersDOM();
            console.log('  ✅ forceUpdateRecentNumbersDOM completed');
        } catch (error) {
            console.error('  ❌ forceUpdateRecentNumbersDOM error:', error);
        }
    } else {
        console.log('  ❌ DataPersistence.forceUpdateRecentNumbersDOM not available');
    }
}

// Run full diagnostic
async function runFullDiagnostic() {
    console.log('🚀 RUNNING FULL TV DISPLAY DIAGNOSTIC');
    console.log('=====================================');
    
    // Get database data
    const dbNumbers = await testDatabaseLoad();
    
    console.log('\n📊 COMPARISON:');
    console.log('  Database recent 5:', dbNumbers);
    console.log('  Current rolledNumbersArray:', window.rolledNumbersArray);
    
    // Test display functions
    console.log('\n🎯 TESTING FUNCTIONS:');
    testDisplayFunctions();
    
    // Wait a moment and check DOM again
    setTimeout(() => {
        console.log('\n🖥️ DOM AFTER FUNCTION CALLS:');
        for (let i = 1; i <= 5; i++) {
            const element = document.querySelector(`.roll${i}`);
            if (element) {
                console.log(`  .roll${i}: "${element.textContent}" (classes: ${element.className})`);
            }
        }
        
        // If still not matching, try manual update
        if (dbNumbers && dbNumbers.length > 0) {
            console.log('\n🔧 TRYING MANUAL DOM UPDATE:');
            testManualDOMUpdate(dbNumbers);
            
            setTimeout(() => {
                console.log('\n🖥️ DOM AFTER MANUAL UPDATE:');
                for (let i = 1; i <= 5; i++) {
                    const element = document.querySelector(`.roll${i}`);
                    if (element) {
                        console.log(`  .roll${i}: "${element.textContent}" (classes: ${element.className})`);
                    }
                }
                console.log('\n✅ DIAGNOSTIC COMPLETE');
            }, 1000);
        }
    }, 2000);
}

// Auto-run diagnostic
runFullDiagnostic();

// Make functions available globally for manual testing
window.debugTV = {
    testDatabaseLoad,
    testManualDOMUpdate,
    testDisplayFunctions,
    runFullDiagnostic
};

console.log('🔍 Debug functions available as window.debugTV');
console.log('  Usage: window.debugTV.runFullDiagnostic()');
