/**
 * Feature Removal Patch
 *
 * This script disables the spin, reset, payout, and cancel ticket functionality
 * that was removed from the interface. The countdown timer functionality is preserved.
 */

document.addEventListener('DOMContentLoaded', function() {
  // Wait for scripts.js to fully load
  setTimeout(function() {
    patchFunctionalities();
  }, 1500);

  // Run again after a longer delay to catch any late-initialized components
  setTimeout(function() {
    patchFunctionalities();
    removePayoutButton(); // Explicit removal of payout button
  }, 3000);

  // Run multiple times to ensure the payout button is removed
  for (let i = 1; i <= 10; i++) {
    setTimeout(function() {
      removePayoutButton();
    }, 3000 + (i * 1000)); // Run every second for 10 seconds after initial 3 seconds
  }

  // Also run on any DOM changes to catch dynamic additions
  setupMutationObserver();
});

function setupMutationObserver() {
  // Create a mutation observer to watch for DOM changes
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      // Check if nodes were added
      if (mutation.addedNodes.length) {
        // Check each added node
        mutation.addedNodes.forEach(function(node) {
          // If it's an element node
          if (node.nodeType === 1) {
            // Check if it's a payout button or contains one
            if (node.classList && node.classList.contains('button-payout')) {
              console.log('Mutation observer caught payout button, removing it');
              node.parentNode.removeChild(node);
            } else if (node.querySelector) {
              const payoutBtn = node.querySelector('.button-payout');
              if (payoutBtn) {
                console.log('Mutation observer caught container with payout button, removing button');
                payoutBtn.parentNode.removeChild(payoutBtn);
              }
            }
          }
        });
      }
    });

    // Also check for any payout buttons that might have been missed
    removePayoutButton();
  });

  // Start observing the document with the configured parameters
  observer.observe(document.body, { childList: true, subtree: true });
  console.log('Mutation observer set up to catch dynamic payout button additions');
}

function patchFunctionalities() {
  console.log('Patching to disable removed functionalities...');

  // Create empty stub functions to replace the original functionalities
  // This prevents errors when these functions are called elsewhere in the code

  // Disable spin functionality
  window.rouletteWheelAnimation = function() {
    console.log('Spin functionality has been disabled.');
    return false;
  };

  // Disable reset functionality
  if (typeof window.reset === 'function') {
    window.reset = function() {
      console.log('Reset functionality has been disabled.');
      return false;
    };
  }

  // Completely override the initializePayoutModal function
  window.initializePayoutModal = function() {
    console.log('Payout functionality has been disabled.');
    // Actively remove any payout button that might have been created
    removePayoutButton();
    return false;
  };

  // Override the original function to prevent it from running
  const originalInitializePayoutModal = window.initializePayoutModal;
  window.initializePayoutModal = function() {
    console.log('Intercepted attempt to initialize payout modal');
    removePayoutButton();
    return false;
  };

  window.collectWinnings = function() {
    console.log('Collect winnings functionality has been disabled.');
    return false;
  };

  // Disable cancel ticket functionality
  window.initializeCancelTicketModal = function() {
    console.log('Cancel ticket functionality has been disabled.');
    return false;
  };

  // Remove event listeners related to the removed buttons
  removeButtonEventListeners();

  // Remove the payout button completely
  removePayoutButton();

  // Add CSS to ensure the button is hidden
  addHidingCSS();

  // Ensure the timer container is visible
  showTimerContainer();

  console.log('Feature removal patch applied successfully!');
}

// Function to completely remove the payout button
function removePayoutButton() {
  console.log('Actively removing payout button...');

  // Remove any existing payout button
  const payoutButtons = document.querySelectorAll('.button-payout');
  if (payoutButtons.length > 0) {
    console.log(`Found ${payoutButtons.length} payout button(s), removing them`);
    payoutButtons.forEach(button => {
      if (button && button.parentNode) {
        button.parentNode.removeChild(button);
      }
    });
  }

  // Also remove the payout modal if it exists
  const payoutModal = document.querySelector('.payout-modal');
  if (payoutModal) {
    console.log('Found payout modal, removing it');
    payoutModal.parentNode.removeChild(payoutModal);
  }

  // Override the initializePayoutModal function in scripts.js
  // by hijacking any attempt to add a payout button
  if (!window._originalAppendChild) {
    window._originalAppendChild = Element.prototype.appendChild;
    Element.prototype.appendChild = function(child) {
      // Block any attempt to add a payout button
      if (child &&
          child.className &&
          typeof child.className === 'string' &&
          child.className.includes('button-payout')) {
        console.log('Blocked attempt to add payout button');
        return null;
      }
      return window._originalAppendChild.call(this, child);
    };
  }

  // Also override the insertBefore method to prevent payout button insertion
  if (!window._originalInsertBefore) {
    window._originalInsertBefore = Element.prototype.insertBefore;
    Element.prototype.insertBefore = function(newNode, referenceNode) {
      // Block any attempt to add a payout button
      if (newNode &&
          newNode.className &&
          typeof newNode.className === 'string' &&
          newNode.className.includes('button-payout')) {
        console.log('Blocked attempt to insert payout button');
        return null;
      }
      return window._originalInsertBefore.call(this, newNode, referenceNode);
    };
  }

  // Periodically check and remove any payout buttons that might be added
  if (!window._payoutRemovalInterval) {
    window._payoutRemovalInterval = setInterval(function() {
      const payoutBtns = document.querySelectorAll('.button-payout');
      if (payoutBtns.length > 0) {
        console.log(`Found ${payoutBtns.length} payout button(s) during interval check, removing them`);
        payoutBtns.forEach(btn => {
          if (btn && btn.parentNode) {
            btn.parentNode.removeChild(btn);
          }
        });
      }
    }, 500); // Check more frequently (every 500ms)
  }
}

// Function to add CSS that ensures the payout button is hidden
function addHidingCSS() {
  // Create a style element if it doesn't exist
  if (!document.getElementById('feature-removal-styles')) {
    const style = document.createElement('style');
    style.id = 'feature-removal-styles';
    style.textContent = `
      /* Hide payout button and related elements */
      .button-payout,
      .payout-modal,
      [class*="payout-"] {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        position: absolute !important;
        left: -9999px !important;
        width: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
        z-index: -9999 !important;
      }

      /* Hide other removed features */
      .payout-modal,
      .cancel-ticket-modal,
      .button-spin,
      .button-reset,
      .button-cancel-ticket,
      .alert-spin-result,
      .draw-container {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
        position: absolute !important;
        left: -9999px !important;
      }

      /* Ensure timer container is visible */
      .timer-container {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        pointer-events: auto !important;
        position: relative !important;
        left: auto !important;
      }
    `;
    document.head.appendChild(style);
    console.log('Added CSS to ensure payout button remains hidden');
  }
}

// Function to ensure timer container is visible and working
function showTimerContainer() {
  console.log('Ensuring timer container is visible and functional');

  // Make sure the timer container is visible
  const timerContainer = document.querySelector('.timer-container');
  if (timerContainer) {
    timerContainer.style.display = 'flex';
    timerContainer.style.visibility = 'visible';
    timerContainer.style.opacity = '1';
    timerContainer.style.position = 'relative';
    timerContainer.style.left = 'auto';
    timerContainer.style.pointerEvents = 'auto';
    console.log('Timer container styling reset to visible');
  }

  // Ensure countdown timer is working
  if (typeof window.startCountdown === 'function' && typeof window.updateCountdownDisplay === 'function') {
    console.log('Countdown timer functions found, ensuring they are NOT overridden');

    // If there's no countdown interval running, start it
    if (!window.countdownInterval) {
      console.log('No countdown interval detected, attempting to start one');

      // Check if we need to initialize countdown time
      if (typeof window.countdownTime === 'undefined') {
        window.countdownTime = 120;
      }

      // Only start a new countdown if not already running
      setTimeout(() => {
        if (!window.countdownInterval) {
          console.log('Starting countdown timer');
          window.startCountdown();
        }
      }, 500);
    }
  }
}

function removeButtonEventListeners() {
  // Safely remove event listeners for elements that might no longer exist
  // This prevents errors when trying to access removed elements

  function safelyRemoveListeners(selector) {
    const elements = document.querySelectorAll(selector);
    if (elements.length > 0) {
      console.log(`Removing event listeners from ${elements.length} ${selector} elements`);
      elements.forEach(element => {
        // Clone the element and replace it with the clone to remove all event listeners
        if (element && element.parentNode) {
          const newElement = element.cloneNode(true);
          element.parentNode.replaceChild(newElement, element);
        }
      });
    }
  }

  // Remove listeners from the spin button if it still exists
  safelyRemoveListeners('.button-spin');

  // Remove listeners from the reset button if it still exists
  safelyRemoveListeners('.button-reset');

  // Remove listeners from payout-related elements
  safelyRemoveListeners('.button-payout');
  safelyRemoveListeners('.scan-button');
  safelyRemoveListeners('.verify-button');
  safelyRemoveListeners('.collect-button');
  safelyRemoveListeners('.payout-close');
  safelyRemoveListeners('.barcode-tab');
  safelyRemoveListeners('.manual-tab');

  // Remove listeners from cancel-ticket-related elements
  safelyRemoveListeners('.button-cancel-ticket');
  safelyRemoveListeners('.scan-cancel-button');
  safelyRemoveListeners('.verify-cancel-button');
  safelyRemoveListeners('.confirm-cancel-ticket-button');

  // Also remove any click handlers on the menu container that might add the payout button
  const menuContainer = document.querySelector('.menu-container');
  if (menuContainer) {
    const newMenuContainer = menuContainer.cloneNode(true);
    if (menuContainer.parentNode) {
      menuContainer.parentNode.replaceChild(newMenuContainer, menuContainer);
    }
  }
}

// Call updateUI on page load to ensure CSS is applied
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(updateUI, 500);
  // Run again after a delay to ensure it catches everything
  setTimeout(updateUI, 2000);
});

function updateUI() {
  console.log('Updating UI to hide removed features...');

  // Add CSS to hide elements
  addHidingCSS();

  // Make sure the timer is visible
  showTimerContainer();

  // Remove the payout button if it exists
  removePayoutButton();
}