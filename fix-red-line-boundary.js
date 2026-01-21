// DIRECT FIX for the red line boundary issue
(function() {
  console.log("STARTING RED LINE BOUNDARY FIX");
  
  try {
    // 1. Find and fix the roulette table container
    const rouletteTable = document.querySelector('.roulette-table');
    if (rouletteTable) {
      // Fix the roulette table container itself
      rouletteTable.style.overflow = 'visible';
      console.log("Fixed roulette table overflow");
    }
    
    // 2. Find and fix the website wrapper
    const websiteWrapper = document.querySelector('.website-wrapper') || document.getElementById('website-wrapper');
    if (websiteWrapper) {
      websiteWrapper.style.overflow = 'visible';
      console.log("Fixed website wrapper overflow");
    }
    
    // 3. Find and fix the betting area specifically (this likely has the red line)
    const bettingArea = document.querySelector('.betting-area');
    if (bettingArea) {
      bettingArea.style.overflow = 'visible';
      console.log("Fixed betting area overflow");
    }
    
    // 4. Fix body and html overflow
    document.body.style.overflow = 'visible';
    document.documentElement.style.overflow = 'visible';
    
    // 5. Now find and fix the bet display container
    const betDisplay = document.getElementById('bet-display-container');
    if (betDisplay) {
      // Apply FIXED height initially to force it below the red line
      betDisplay.style.height = '600px';
      betDisplay.style.maxHeight = 'none';
      betDisplay.style.overflow = 'visible';
      betDisplay.style.position = 'absolute'; 
      betDisplay.style.zIndex = '9999';
      
      console.log("Forced bet display to extend beyond red line");
      
      // Make it appear below the red line
      setTimeout(() => {
        // After forcing it to be tall, make it properly resizable
        betDisplay.style.height = 'auto';
        betDisplay.style.resize = 'both';
        
        // Force all inner elements to exceed red line too
        const betDisplayBody = betDisplay.querySelector('.bet-display-body');
        if (betDisplayBody) {
          betDisplayBody.style.maxHeight = 'none';
        }
      }, 100);
    }
    
    // 6. Apply global CSS fix to ensure EVERYTHING can exceed the red line
    const styleTag = document.createElement('style');
    styleTag.textContent = `
      /* Ensure everything can extend past the red line */
      body, html, .website-wrapper, .roulette-table, .betting-area {
        overflow: visible !important;
      }
      
      /* Fix the bet display container specifically */
      .bet-display-container, #bet-display-container {
        overflow: visible !important;
        max-height: none !important;
        z-index: 9999 !important;
        position: absolute !important;
      }
      
      /* Make sure any child elements don't restrict it */
      .bet-display-body, .bet-display-list {
        max-height: none !important;
        overflow: auto !important;
      }
      
      /* Make resize handles more visible */
      .resize-handle {
        background-color: rgba(255, 255, 255, 0.3) !important;
      }
    `;
    document.head.appendChild(styleTag);
    
    alert("Red line boundary fix applied! You should now be able to resize past the red line and grab the top edge to resize upward.");
  } catch (error) {
    console.error("Error fixing red line boundary:", error);
    alert("Error fixing red line boundary: " + error.message);
  }
})(); 