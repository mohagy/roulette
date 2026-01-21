// DEBUG TOOL to identify what's causing the red line boundary
(function() {
  console.log("RED LINE BOUNDARY DEBUG TOOL ACTIVATED");
  
  // Create a debug panel to display information
  const debugPanel = document.createElement('div');
  debugPanel.style.position = 'fixed';
  debugPanel.style.top = '10px';
  debugPanel.style.right = '10px';
  debugPanel.style.width = '400px';
  debugPanel.style.height = '300px';
  debugPanel.style.background = 'rgba(0,0,0,0.8)';
  debugPanel.style.color = '#fff';
  debugPanel.style.padding = '10px';
  debugPanel.style.zIndex = '10000';
  debugPanel.style.overflow = 'auto';
  debugPanel.style.fontFamily = 'monospace';
  debugPanel.style.fontSize = '12px';
  debugPanel.style.border = '1px solid #555';
  
  // Add title
  debugPanel.innerHTML = '<h2 style="color:yellow;margin-top:0">Red Line Debug Tool</h2>';
  
  // Function to check an element and analyze potential constraints
  function analyzeElement(element, label) {
    const styles = window.getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    
    let output = `
      <div style="margin-bottom:10px;border-bottom:1px solid #555;padding-bottom:10px">
        <strong style="color:lightgreen">${label}:</strong><br>
        <span style="color:orange">Position: ${styles.position}</span><br>
        <span>Width: ${rect.width}px, Height: ${rect.height}px</span><br>
        <span>Top: ${rect.top}px, Bottom: ${rect.bottom}px</span><br>
        <span>Max-height: ${styles.maxHeight}, Overflow: ${styles.overflow}</span><br>
        <span>Z-index: ${styles.zIndex}</span>
      </div>
    `;
    
    return output;
  }
  
  // Analyze all potential containers
  let analysisHtml = '';
  
  const betDisplay = document.getElementById('bet-display-container') || document.querySelector('.bet-display-container');
  if (betDisplay) {
    analysisHtml += analyzeElement(betDisplay, 'Bet Display Container');
    
    // Also log the bet display's position to console
    console.log("Bet Display Position:", betDisplay.getBoundingClientRect());
    
    // Highlight the bet display with a yellow border
    betDisplay.style.border = '2px solid yellow';
  }
  
  const bettingArea = document.querySelector('.betting-area');
  if (bettingArea) {
    analysisHtml += analyzeElement(bettingArea, 'Betting Area');
    console.log("Betting Area Position:", bettingArea.getBoundingClientRect());
  }
  
  const websiteWrapper = document.querySelector('.website-wrapper');
  if (websiteWrapper) {
    analysisHtml += analyzeElement(websiteWrapper, 'Website Wrapper');
  }
  
  const betDisplayBody = document.querySelector('.bet-display-body');
  if (betDisplayBody) {
    analysisHtml += analyzeElement(betDisplayBody, 'Bet Display Body');
  }
  
  debugPanel.innerHTML += analysisHtml;
  
  // Add button to try fix the issues
  const fixButton = document.createElement('button');
  fixButton.innerText = 'Apply Fix';
  fixButton.style.background = 'green';
  fixButton.style.color = 'white';
  fixButton.style.padding = '5px 10px';
  fixButton.style.border = 'none';
  fixButton.style.marginTop = '10px';
  fixButton.style.cursor = 'pointer';
  
  fixButton.onclick = function() {
    // 1. Fix the bet display container
    if (betDisplay) {
      betDisplay.style.position = 'absolute';
      betDisplay.style.maxHeight = 'none';
      betDisplay.style.overflow = 'visible';
      betDisplay.style.zIndex = '9999';
    }
    
    // 2. Fix the betting area
    if (bettingArea) {
      bettingArea.style.overflow = 'visible';
    }
    
    // 3. Fix website wrapper
    if (websiteWrapper) {
      websiteWrapper.style.overflow = 'visible';
    }
    
    // 4. Fix the bet display body
    if (betDisplayBody) {
      betDisplayBody.style.maxHeight = 'none';
    }
    
    // Apply global style changes
    const styleTag = document.createElement('style');
    styleTag.textContent = `
      * {
        overflow: visible !important;
      }
      
      .bet-display-container, #bet-display-container {
        position: absolute !important;
        max-height: none !important;
        height: auto !important;
        z-index: 9999 !important;
      }
    `;
    document.head.appendChild(styleTag);
    
    debugPanel.innerHTML += '<div style="color:lime;margin-top:10px">Fix applied. Try resizing now!</div>';
  };
  
  debugPanel.appendChild(fixButton);
  
  // Add close button
  const closeButton = document.createElement('button');
  closeButton.innerText = 'Close';
  closeButton.style.background = 'red';
  closeButton.style.color = 'white';
  closeButton.style.padding = '5px 10px';
  closeButton.style.border = 'none';
  closeButton.style.marginTop = '10px';
  closeButton.style.marginLeft = '10px';
  closeButton.style.cursor = 'pointer';
  
  closeButton.onclick = function() {
    document.body.removeChild(debugPanel);
  };
  
  debugPanel.appendChild(closeButton);
  
  document.body.appendChild(debugPanel);
  
  // Check if there's anything literally red and horizontal on the page
  const allElements = document.querySelectorAll('*');
  let redElements = [];
  
  allElements.forEach(el => {
    const styles = window.getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    
    // Check if element is horizontal (much wider than tall)
    const isHorizontal = rect.width > rect.height * 5 && rect.height < 10;
    
    // Check if element has red color or border
    const hasRed = 
      styles.backgroundColor.includes('rgb(255, 0, 0)') || 
      styles.backgroundColor.includes('red') ||
      styles.borderColor.includes('rgb(255, 0, 0)') ||
      styles.borderColor.includes('red');
    
    if (isHorizontal && hasRed) {
      redElements.push(el);
      console.log("Potential red line found:", el);
      el.style.border = '3px solid yellow';
    }
  });
  
  if (redElements.length > 0) {
    debugPanel.innerHTML += `<div style="color:red;margin-top:10px">Found ${redElements.length} potential red line elements (highlighted in yellow)</div>`;
  }
})(); 