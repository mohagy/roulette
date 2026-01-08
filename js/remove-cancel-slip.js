/**
 * Remove Cancel Slip Button
 * 
 * This script removes any existing Cancel Slip button from the DOM
 * and clears related localStorage data.
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Checking for Cancel Slip button to remove...');
    
    // Remove the button if it exists
    const cancelSlipButton = document.getElementById('cancel-slip-button');
    if (cancelSlipButton) {
        cancelSlipButton.remove();
        console.log('Cancel Slip button removed from DOM');
    }
    
    // Also remove any floating-button with cancel-slip-button class
    const cancelButtons = document.querySelectorAll('.cancel-slip-button');
    cancelButtons.forEach(button => {
        button.remove();
        console.log('Additional Cancel Slip button removed');
    });
    
    // Clear localStorage data related to the button
    if (localStorage.getItem('cancelSlipButtonPosition')) {
        localStorage.removeItem('cancelSlipButtonPosition');
        console.log('Cancel Slip button position data cleared from localStorage');
    }
    
    // Remove any existing modal
    const cancelSlipModal = document.getElementById('cancel-slip-modal');
    if (cancelSlipModal) {
        cancelSlipModal.remove();
        console.log('Cancel Slip modal removed from DOM');
    }
});
