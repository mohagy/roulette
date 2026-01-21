/**
 * Test Draw Numbers
 * 
 * This script tests the draw number retrieval functions to ensure they're working correctly.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Wait for all scripts to load
    setTimeout(function() {
        console.log('Testing draw number retrieval functions...');
        
        // Test the global getCurrentDrawNumber function
        if (typeof getCurrentDrawNumber === 'function') {
            const globalDrawNumber = getCurrentDrawNumber();
            console.log('Global getCurrentDrawNumber() returned:', globalDrawNumber);
        } else {
            console.log('Global getCurrentDrawNumber() function not found');
        }
        
        // Test betTracker.getCurrentDrawNumber method
        if (typeof betTracker !== 'undefined' && typeof betTracker.getCurrentDrawNumber === 'function') {
            const betTrackerDrawNumber = betTracker.getCurrentDrawNumber();
            console.log('betTracker.getCurrentDrawNumber() returned:', betTrackerDrawNumber);
        } else {
            console.log('betTracker.getCurrentDrawNumber() method not found');
        }
        
        // Test the draw-betting-integration.js getCurrentDrawNumber function
        if (typeof window.drawBettingIntegration !== 'undefined' && typeof window.drawBettingIntegration.getCurrentDrawNumber === 'function') {
            const drawBettingDrawNumber = window.drawBettingIntegration.getCurrentDrawNumber();
            console.log('drawBettingIntegration.getCurrentDrawNumber() returned:', drawBettingDrawNumber);
        } else {
            console.log('drawBettingIntegration.getCurrentDrawNumber() function not found');
        }
        
        // Test the elegant-cancel-button.js getElegantCurrentDrawNumber function
        if (typeof getElegantCurrentDrawNumber === 'function') {
            const elegantDrawNumber = getElegantCurrentDrawNumber();
            console.log('getElegantCurrentDrawNumber() returned:', elegantDrawNumber);
        } else {
            console.log('getElegantCurrentDrawNumber() function not found');
        }
        
        // Read the draw numbers directly from the UI
        const nextDrawElement = document.getElementById('next-draw-number');
        if (nextDrawElement) {
            console.log('next-draw-number element text content:', nextDrawElement.textContent);
            const match = nextDrawElement.textContent.match(/#(\d+)/);
            if (match && match[1]) {
                console.log('Extracted draw number from UI:', parseInt(match[1], 10));
            } else {
                console.log('Failed to extract draw number from UI');
            }
        } else {
            console.log('next-draw-number element not found in the DOM');
        }
        
        // Test the betting slip generation
        console.log('Testing betting slip generation...');
        if (typeof betTracker !== 'undefined' && typeof betTracker.printBettingSlip === 'function') {
            // Add a dummy bet for testing
            if (betTracker.bets.length === 0) {
                betTracker.bets.push({
                    type: 'straight',
                    description: 'Straight Up on 1',
                    amount: 100,
                    potentialReturn: 3600
                });
            }
            
            // Print the betting slip
            console.log('Calling betTracker.printBettingSlip()...');
            // Uncomment the line below to actually test the betting slip generation
            // betTracker.printBettingSlip();
        } else {
            console.log('betTracker.printBettingSlip() method not found');
        }
    }, 2000);
});
