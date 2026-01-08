/**
 * Fixed Buttons Handler
 * 
 * This script handles the event listeners for the fixed buttons in the bet display footer.
 */

document.addEventListener('DOMContentLoaded', function() {
  // Wait for scripts.js to fully load
  setTimeout(function() {
    initializeFixedButtons();
  }, 1000);
});

function initializeFixedButtons() {
  console.log('Initializing fixed buttons in the bet display footer...');
  
  // Print betting slip button
  const printButton = document.getElementById('print-betting-slip-btn');
  if (printButton) {
    printButton.addEventListener('click', function() {
      console.log('Print betting slip button clicked');
      if (typeof betTracker !== 'undefined' && typeof betTracker.printBettingSlip === 'function') {
        betTracker.printBettingSlip();
      } else {
        console.error('betTracker or printBettingSlip function not found');
      }
    });
  } else {
    console.error('Print betting slip button not found');
  }
  
  // Cancel bets button
  const cancelButton = document.getElementById('cancel-betting-slip-btn');
  if (cancelButton) {
    cancelButton.addEventListener('click', function() {
      console.log('Cancel bets button clicked');
      // Only show confirmation modal if there are bets to cancel
      if (typeof betTracker !== 'undefined' && betTracker.bets && betTracker.bets.length > 0) {
        const cancelSlipModal = document.querySelector('.cancel-slip-modal');
        if (cancelSlipModal) {
          cancelSlipModal.classList.add('visible');
        } else {
          console.error('Cancel slip modal not found');
        }
      }
    });
  } else {
    console.error('Cancel bets button not found');
  }
  
  console.log('Fixed buttons initialized successfully!');
}
