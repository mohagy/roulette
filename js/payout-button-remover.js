/**
 * Payout Button Remover
 * 
 * This script is specifically designed to remove the payout button from the interface.
 * It runs after all other scripts have loaded to ensure the button is completely removed.
 */

(function() {
  // Function to remove the payout button
  function removePayoutButton() {
    console.log('Payout button remover running...');
    
    // Remove any existing payout buttons
    const payoutButtons = document.querySelectorAll('.button-payout');
    if (payoutButtons.length > 0) {
      console.log(`Found ${payoutButtons.length} payout button(s), removing them`);
      payoutButtons.forEach(button => {
        if (button && button.parentNode) {
          button.parentNode.removeChild(button);
        }
      });
    }
    
    // Add CSS to hide the payout button
    const style = document.createElement('style');
    style.textContent = `
      .button-payout {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        position: absolute !important;
        left: -9999px !important;
        width: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
      }
    `;
    document.head.appendChild(style);
  }
  
  // Run the removal function when the DOM is fully loaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      // Run immediately
      removePayoutButton();
      
      // Run again after a short delay to catch any late additions
      setTimeout(removePayoutButton, 1000);
      setTimeout(removePayoutButton, 2000);
      setTimeout(removePayoutButton, 3000);
      
      // Set up an interval to continuously check for and remove the button
      setInterval(removePayoutButton, 2000);
    });
  } else {
    // DOM already loaded, run immediately
    removePayoutButton();
    
    // Run again after a short delay
    setTimeout(removePayoutButton, 1000);
    setTimeout(removePayoutButton, 2000);
    setTimeout(removePayoutButton, 3000);
    
    // Set up an interval to continuously check for and remove the button
    setInterval(removePayoutButton, 2000);
  }
})();
