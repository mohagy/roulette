// Advanced Solution for Red Line Boundary Issue
(function() {
  // Utility function to log actions
  function logAction(action) {
    console.log("%c[RED LINE FIX] %c" + action, "color: red; font-weight: bold;", "color: black;");
  }
  
  logAction("Advanced fix script started");
  
  // Wait for DOM to be fully loaded
  function ensureDomLoaded(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
    } else {
      callback();
    }
  }
  
  ensureDomLoaded(function() {
    logAction("DOM loaded, applying fixes");
    
    // APPROACH 1: Fix all overflow properties in the entire document
    function fixAllOverflows() {
      const allElements = document.querySelectorAll('body, html, .website-wrapper, .betting-area, .roulette-table, .bet-display-container, #bet-display-container, .bet-display-body');
      
      allElements.forEach(el => {
        if (el) {
          el.style.setProperty('overflow', 'visible', 'important');
          el.style.setProperty('max-height', 'none', 'important');
        }
      });
      
      logAction("Applied overflow fixes to major containers");
    }
    
    // APPROACH 2: Fix the bet display container specifically
    function fixBetDisplay() {
      // Find the bet display using multiple selectors
      const betDisplay = document.getElementById('bet-display-container') || 
                        document.querySelector('.bet-display-container') || 
                        document.querySelector('[id*="bet-display"]') ||
                        document.querySelector('[class*="bet-display"]');
      
      if (betDisplay) {
        logAction("Found bet display, applying specific fixes");
        
        // 1. Make it position absolute so it can extend beyond boundaries
        betDisplay.style.setProperty('position', 'absolute', 'important');
        
        // 2. Ensure it has no max-height constraints
        betDisplay.style.setProperty('max-height', 'none', 'important');
        
        // 3. Make sure it can overflow any parent containers
        betDisplay.style.setProperty('overflow', 'visible', 'important');
        
        // 4. Set a high z-index to ensure it appears above any boundaries
        betDisplay.style.setProperty('z-index', '9999', 'important');
        
        // 5. Allow full height expansion
        betDisplay.style.setProperty('height', 'auto', 'important');
        
        // 6. Fix the internal bodies as well
        const betDisplayBody = betDisplay.querySelector('.bet-display-body');
        if (betDisplayBody) {
          betDisplayBody.style.setProperty('max-height', 'none', 'important');
          betDisplayBody.style.setProperty('overflow', 'visible', 'important');
          logAction("Fixed bet display body");
        }
        
        // 7. Ensure any resize handles are working properly
        const resizeHandles = betDisplay.querySelectorAll('.ui-resizable-handle');
        if (resizeHandles.length > 0) {
          resizeHandles.forEach(handle => {
            handle.style.setProperty('z-index', '10000', 'important');
          });
          logAction("Fixed resize handles");
        }
      } else {
        logAction("Warning: Could not find bet display container");
      }
    }
    
    // APPROACH 3: Apply CSS directly to the page
    function applyGlobalCSS() {
      const styleTag = document.createElement('style');
      styleTag.id = 'red-line-fix-styles';
      
      styleTag.textContent = `
        /* Fix for main containers */
        body, html, .website-wrapper, .betting-area, .roulette-table {
          overflow: visible !important;
          max-height: none !important;
        }
        
        /* Fix for bet display */
        .bet-display-container, #bet-display-container, [id*="bet-display"], [class*="bet-display"] {
          position: absolute !important;
          max-height: none !important;
          height: auto !important;
          overflow: visible !important;
          z-index: 9999 !important;
        }
        
        /* Fix for bet display body */
        .bet-display-body, .bet-display-container .bet-display-body {
          max-height: none !important;
          overflow: visible !important;
        }
        
        /* Fix for resize handles */
        .ui-resizable-handle {
          z-index: 10000 !important;
        }
        
        /* Prevent any red lines from limiting height */
        [style*="color: red"], [style*="color:red"], [style*="border-color: red"], [style*="border-color:red"],
        [style*="background-color: red"], [style*="background-color:red"], [style*="background: red"], [style*="background:red"] {
          overflow: visible !important;
          height: auto !important;
          max-height: none !important;
        }
      `;
      
      document.head.appendChild(styleTag);
      logAction("Applied global CSS fixes");
    }
    
    // APPROACH 4: Find and remove any potential red lines
    function findAndRemoveRedLines() {
      const allElements = document.querySelectorAll('*');
      
      allElements.forEach(el => {
        const styles = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        
        // Check if element is horizontal (much wider than tall)
        const isHorizontal = rect.width > rect.height * 3 && rect.height < 10;
        
        // Check if element has red color or border
        const hasRed = 
          styles.color === 'rgb(255, 0, 0)' || 
          styles.backgroundColor === 'rgb(255, 0, 0)' || 
          styles.borderColor === 'rgb(255, 0, 0)' ||
          styles.color === 'red' || 
          styles.backgroundColor === 'red' || 
          styles.borderColor === 'red';
        
        if (isHorizontal && hasRed) {
          // Either remove the element or make it not a boundary
          el.style.setProperty('overflow', 'visible', 'important');
          el.style.setProperty('height', '0', 'important');
          el.style.setProperty('pointer-events', 'none', 'important');
          logAction("Fixed potential red line element");
        }
      });
    }
    
    // APPROACH 5: Fix any jQuery UI resizable constraints if present
    function fixJQueryResizable() {
      if (window.jQuery && jQuery.ui) {
        logAction("jQuery UI detected, attempting to fix resizable constraints");
        
        const betDisplay = document.getElementById('bet-display-container') || 
                          document.querySelector('.bet-display-container') || 
                          document.querySelector('[id*="bet-display"]') ||
                          document.querySelector('[class*="bet-display"]');
        
        if (betDisplay && jQuery(betDisplay).resizable) {
          try {
            // Destroy any existing resizable
            jQuery(betDisplay).resizable('destroy');
            
            // Reapply with no containment
            jQuery(betDisplay).resizable({
              handles: 'all',
              minWidth: 150,
              minHeight: 100,
              containment: false,
              start: function(event, ui) {
                // Ensure no constraints during resize
                jQuery(this).css({
                  'max-height': 'none',
                  'overflow': 'visible'
                });
              }
            });
            
            logAction("Fixed jQuery UI resizable constraints");
          } catch (err) {
            logAction("Error fixing jQuery resizable: " + err.message);
          }
        }
      }
    }
    
    // Apply all approaches
    try {
      fixAllOverflows();
      fixBetDisplay();
      applyGlobalCSS();
      findAndRemoveRedLines();
      
      // Only try jQuery fix after a short delay to ensure jQuery is loaded
      setTimeout(fixJQueryResizable, 500);
      
      logAction("All fixes have been applied!");
      
      // Create notification
      const notification = document.createElement('div');
      notification.style.position = 'fixed';
      notification.style.top = '10px';
      notification.style.left = '50%';
      notification.style.transform = 'translateX(-50%)';
      notification.style.background = 'rgba(0,0,0,0.8)';
      notification.style.color = '#fff';
      notification.style.padding = '10px 20px';
      notification.style.borderRadius = '5px';
      notification.style.zIndex = '99999';
      notification.style.fontFamily = 'Arial, sans-serif';
      notification.style.fontSize = '14px';
      notification.textContent = '✅ Red Line Boundary Fixes Applied!';
      
      document.body.appendChild(notification);
      
      // Remove notification after 5 seconds
      setTimeout(function() {
        if (document.body.contains(notification)) {
          document.body.removeChild(notification);
        }
      }, 5000);
      
      // Set up a mutation observer to reapply fixes if the DOM changes
      const observer = new MutationObserver(function(mutations) {
        // Check if changes affect our target areas
        for (let mutation of mutations) {
          if (mutation.type === 'childList' || mutation.type === 'attributes') {
            const target = mutation.target;
            if (target.id === 'bet-display-container' || 
                target.classList.contains('bet-display-container') ||
                target.classList.contains('betting-area') ||
                target.classList.contains('website-wrapper')) {
              
              logAction("Detected DOM changes, reapplying fixes");
              fixBetDisplay();
              break;
            }
          }
        }
      });
      
      // Start observing
      observer.observe(document.body, { 
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['style', 'class']
      });
      
    } catch (err) {
      console.error("[RED LINE FIX] Error:", err);
    }
  });
})(); 