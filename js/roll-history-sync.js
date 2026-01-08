/**
 * Roll History Synchronization
 * This module handles synchronizing roll history between TV display and the main game interface
 */

// Global variables to store the roll history
let rollHistoryNumbers = [];
let rollHistoryColors = [];
let lastSyncTimestamp = 0;
const SYNC_INTERVAL = 5000; // milliseconds

/**
 * Initialize the roll history synchronization
 */
function initRollHistorySync() {
    console.log('=== Roll History Sync: Initializing... ===');
    
    // Check if the roll history elements exist
    let rollElements = [];
    for (let i = 1; i <= 5; i++) {
        const element = $(`.roll${i}`);
        rollElements.push(element.length > 0);
    }
    
    console.log('Roll elements found:', rollElements);
    
    // Load initial roll history
    loadRollHistory();
    
    // Set up periodic syncing
    setInterval(loadRollHistory, SYNC_INTERVAL);
    
    console.log('=== Roll History Sync: Initialization complete ===');
}

/**
 * Load roll history from server or localStorage
 */
async function loadRollHistory() {
    try {
        console.log('Roll History Sync: Loading roll history...');
        
        // First try to load from server
        const response = await fetch('/slipp/load_state.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            console.log('Roll History Sync: Data loaded from server:', data);
            
            // Parse the roll history and colors
            processRollHistoryData(data);
        } else {
            console.warn('Roll History Sync: No roll history found on server, trying localStorage...');
            
            // Try to load from localStorage as fallback
            loadFromLocalStorage();
        }
    } catch (error) {
        console.error('Roll History Sync: Error loading roll history from server:', error);
        
        // Fall back to localStorage
        loadFromLocalStorage();
    }
}

/**
 * Process roll history data from server
 */
function processRollHistoryData(data) {
    // Handle empty values or single values
    const historyStr = data.roll_history || '';
    const colorsStr = data.roll_colors || '';
    
    console.log('Roll History Sync: Processing history:', historyStr);
    console.log('Roll History Sync: Processing colors:', colorsStr);
    
    // Parse the roll history and colors
    let numbers = [];
    let colors = [];
    
    if (historyStr && historyStr.length > 0 && historyStr !== '[]') {
        numbers = historyStr.split(',').map(num => {
            // Handle potential empty values
            return num.trim() ? parseInt(num.trim()) : null;
        }).filter(num => num !== null);
    }
    
    if (colorsStr && colorsStr.length > 0 && colorsStr !== '[]') {
        colors = colorsStr.split(',').map(color => color.trim()).filter(color => color);
    }
    
    // Only update if we have valid data
    if (numbers.length > 0) {
        rollHistoryNumbers = numbers;
        console.log('Roll History Sync: Updated rollHistoryNumbers:', rollHistoryNumbers);
    }
    
    if (colors.length > 0) {
        rollHistoryColors = colors;
        console.log('Roll History Sync: Updated rollHistoryColors:', rollHistoryColors);
    }
    
    // Display the updated roll history
    displayRollHistory();
}

/**
 * Load roll history from localStorage
 */
function loadFromLocalStorage() {
    try {
        const savedRollHistoryNumbers = localStorage.getItem('rolledNumbersArray');
        const savedRollHistoryColors = localStorage.getItem('rolledNumbersColorArray');
        
        console.log('Roll History Sync: localStorage rolledNumbersArray:', savedRollHistoryNumbers);
        console.log('Roll History Sync: localStorage rolledNumbersColorArray:', savedRollHistoryColors);
        
        if (savedRollHistoryNumbers && savedRollHistoryColors) {
            try {
                rollHistoryNumbers = JSON.parse(savedRollHistoryNumbers);
                rollHistoryColors = JSON.parse(savedRollHistoryColors);
            } catch (e) {
                console.error('Roll History Sync: Error parsing localStorage data:', e);
                return;
            }
            
            console.log('Roll History Sync: Data loaded from localStorage:');
            console.log('  Numbers:', rollHistoryNumbers);
            console.log('  Colors:', rollHistoryColors);
            
            // Display the loaded history
            displayRollHistory();
        } else {
            console.warn('Roll History Sync: No roll history found in localStorage');
        }
    } catch (error) {
        console.error('Roll History Sync: Error loading roll history from localStorage:', error);
    }
}

/**
 * Display the roll history in the main interface
 */
function displayRollHistory() {
    console.log('Roll History Sync: Displaying roll history:');
    console.log('  Numbers:', rollHistoryNumbers);
    console.log('  Colors:', rollHistoryColors);
    
    // Ensure we have valid arrays
    if (!Array.isArray(rollHistoryNumbers) || !Array.isArray(rollHistoryColors)) {
        console.error('Roll History Sync: Invalid roll history data');
        return;
    }
    
    // Reverse the arrays to show newest first (same as TV display)
    const displayNumbers = [...rollHistoryNumbers];
    const displayColors = [...rollHistoryColors];
    
    console.log('Roll History Sync: Display arrays:');
    console.log('  Numbers:', displayNumbers);
    console.log('  Colors:', displayColors);
    
    // Clear existing display first to prevent issues
    for (let i = 1; i <= 5; i++) {
        const element = $(`.roll${i}`);
        console.log(`Roll History Sync: Clearing element .roll${i}, exists:`, element.length > 0);
        element.html('');
        element.removeClass("roll-red roll-black roll-green");
    }
    
    // Now display the roll history
    for (let i = 0; i < displayNumbers.length && i < 5; i++) {
        let rolledNumberIndex = i + 1;
        
        // Ensure we have a valid number
        if (displayNumbers[i] !== undefined && displayNumbers[i] !== null) {
            const element = $(`.roll${rolledNumberIndex}`);
            
            if (element.length === 0) {
                console.error(`Roll History Sync: Element .roll${rolledNumberIndex} not found`);
                continue;
            }
            
            console.log(`Roll History Sync: Setting .roll${rolledNumberIndex} to ${displayNumbers[i]}`);
            element.html(displayNumbers[i]);
            
            // Make sure we have a matching color entry
            const colorClass = (i < displayColors.length) 
                           ? displayColors[i] 
                           : getNumberColor(displayNumbers[i]);
            
            console.log(`Roll History Sync: Setting .roll${rolledNumberIndex} color to ${colorClass}`);
            
            switch (colorClass) {
                case "red":
                    element.removeClass("roll-black roll-green").addClass("roll-red");
                    break;
                case "black":
                    element.removeClass("roll-red roll-green").addClass("roll-black");
                    break;
                case "green":
                    element.removeClass("roll-red roll-black").addClass("roll-green");
                    break;
            }
        }
    }
    
    console.log('Roll History Sync: Roll history display complete');
}

/**
 * Get the color of a specific number
 */
function getNumberColor(number) {
    if (number === 0) {
        return "green";
    } else if (rouletteNumbersRed.includes(number)) {
        return "red";
    } else {
        return "black";
    }
}

// Initialize the roll history sync when the document is ready
$(document).ready(function() {
    // Small delay to ensure all other elements are loaded
    setTimeout(initRollHistorySync, 500);
}); 