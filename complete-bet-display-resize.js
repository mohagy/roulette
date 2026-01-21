// Script to enable complete resizing freedom for bet display
(function() {
  // Get the bet display container
  const betDisplay = document.getElementById('bet-display-container');
  
  if (betDisplay) {
    console.log('Found bet display container, removing all size restrictions...');
    
    // Remove all size restrictions
    betDisplay.style.height = 'auto';
    betDisplay.style.maxHeight = 'none';
    betDisplay.style.minHeight = '100px'; // Just a small minimum for usability
    
    // Remove restrictions from inner elements
    const betDisplayBody = betDisplay.querySelector('.bet-display-body');
    if (betDisplayBody) {
      betDisplayBody.style.maxHeight = 'none';
      console.log('Removed max-height from bet display body');
    }
    
    const betDisplayList = betDisplay.querySelector('.bet-display-list');
    if (betDisplayList) {
      betDisplayList.style.maxHeight = 'none';
      console.log('Removed max-height from bet display list');
    }
    
    // Add top resize handles
    function addTopResizeHandles() {
      // Create north (top) resize handle
      const northHandle = document.createElement('div');
      northHandle.className = 'resize-handle resize-n';
      northHandle.setAttribute('data-resize', 'n');
      betDisplay.appendChild(northHandle);
      
      // Create northeast (top-right) resize handle
      const northeastHandle = document.createElement('div');
      northeastHandle.className = 'resize-handle resize-ne';
      northeastHandle.setAttribute('data-resize', 'ne');
      betDisplay.appendChild(northeastHandle);
      
      // Create northwest (top-left) resize handle
      const northwestHandle = document.createElement('div');
      northwestHandle.className = 'resize-handle resize-nw';
      northwestHandle.setAttribute('data-resize', 'nw');
      betDisplay.appendChild(northwestHandle);
      
      console.log('Added top resize handles');
    }
    
    // Apply CSS directly to make it fully resizable
    const style = document.createElement('style');
    style.textContent = `
      /* Remove all size restrictions */
      .bet-display-container {
        max-height: none !important;
        height: auto !important;
        min-height: 100px !important;
        resize: both !important; /* Enable native browser resizing */
        overflow: hidden !important;
        z-index: 9999 !important; /* Ensure it's above other elements */
      }
      
      /* Remove restrictions from inner elements */
      .bet-display-body, 
      .bet-display-list {
        max-height: none !important;
        overflow-y: auto !important;
      }
      
      /* Enhance existing resize handles */
      .resize-handle {
        position: absolute;
        background-color: rgba(255, 255, 255, 0.1);
        z-index: 10000;
        transition: background-color 0.2s;
      }
      
      .resize-handle:hover {
        background-color: rgba(255, 255, 255, 0.3) !important;
      }
      
      /* Make all resize handles more grabbable */
      .resize-handle.resize-s,
      .resize-handle.resize-se,
      .resize-handle.resize-sw {
        height: 20px !important;
        cursor: ns-resize !important;
      }
      
      /* Top resize handles */
      .resize-handle.resize-n {
        cursor: ns-resize !important;
        height: 20px !important;
        left: 20px !important;
        right: 20px !important;
        top: 0 !important;
      }
      
      .resize-handle.resize-ne {
        cursor: nesw-resize !important;
        width: 20px !important;
        height: 20px !important;
        right: 0 !important;
        top: 0 !important;
      }
      
      .resize-handle.resize-nw {
        cursor: nwse-resize !important;
        width: 20px !important;
        height: 20px !important;
        left: 0 !important;
        top: 0 !important;
      }
      
      /* Override any JS limitations during resize */
      .bet-display-container.resizing {
        max-height: none !important;
        height: auto !important;
      }
      
      /* Prevent horizontal red line boundary */
      .bet-display-container, 
      .bet-display-container * {
        max-height: none !important;
        overflow: visible;
      }
    `;
    document.head.appendChild(style);
    
    // Add top resize handles to DOM
    addTopResizeHandles();
    
    // Modify the resize logic to handle top resizing
    function enhanceResizeCapability() {
      // Check if we can find any resize-related functions
      if (window.doResize || window.startResize) {
        console.log('Found resize functions to enhance');
        
        // Create our own resize handlers
        const originalStartResize = window.startResize;
        const originalDoResize = window.doResize;
        const originalEndResize = window.endResize;
        
        // Find all resize handles and add our listeners
        document.querySelectorAll('.resize-handle').forEach(handle => {
          // Remove existing listeners if any
          handle.removeEventListener('mousedown', originalStartResize);
          
          // Add our enhanced listener
          handle.addEventListener('mousedown', function(e) {
            // Only resize with left mouse button
            if (e.button !== 0) return;
            
            const resizeDirection = handle.getAttribute('data-resize');
            console.log('Starting resize in direction:', resizeDirection);
            
            let isResizing = true;
            
            // Initial positions
            const startX = e.clientX;
            const startY = e.clientY;
            
            const rect = betDisplay.getBoundingClientRect();
            const startWidth = rect.width;
            const startHeight = rect.height;
            const startTop = rect.top;
            const startLeft = rect.left;
            
            // Add a class to indicate resizing state
            betDisplay.classList.add('resizing');
            
            // Prevent text selection during resize
            e.preventDefault();
            
            // Function to handle resize movement
            function enhancedDoResize(e) {
              if (!isResizing) return;
              
              // Make sure nothing restricts our resizing
              betDisplay.style.maxHeight = 'none';
              betDisplay.style.height = 'auto';
              
              let newWidth = startWidth;
              let newHeight = startHeight;
              let newTop = startTop;
              let newLeft = startLeft;
              
              // Handle different resize directions
              switch (resizeDirection) {
                case 'n': // North (top)
                  let dyN = e.clientY - startY;
                  newHeight = startHeight - dyN;
                  newTop = startTop + dyN;
                  break;
                  
                case 'ne': // Northeast (top-right corner)
                  let dyNE = e.clientY - startY;
                  let dxNE = e.clientX - startX;
                  newHeight = startHeight - dyNE;
                  newWidth = startWidth + dxNE;
                  newTop = startTop + dyNE;
                  break;
                  
                case 'nw': // Northwest (top-left corner)
                  let dyNW = e.clientY - startY;
                  let dxNW = e.clientX - startX;
                  newHeight = startHeight - dyNW;
                  newWidth = startWidth - dxNW;
                  newTop = startTop + dyNW;
                  newLeft = startLeft + dxNW;
                  break;
                  
                case 'e': // East (right)
                  newWidth = startWidth + (e.clientX - startX);
                  break;
                  
                case 'w': // West (left)
                  let dxW = e.clientX - startX;
                  newWidth = startWidth - dxW;
                  newLeft = startLeft + dxW;
                  break;
                  
                case 's': // South (bottom)
                  newHeight = startHeight + (e.clientY - startY);
                  break;
                  
                case 'se': // Southeast (bottom-right corner)
                  newWidth = startWidth + (e.clientX - startX);
                  newHeight = startHeight + (e.clientY - startY);
                  break;
                  
                case 'sw': // Southwest (bottom-left corner)
                  let dxSW = e.clientX - startX;
                  newWidth = startWidth - dxSW;
                  newLeft = startLeft + dxSW;
                  newHeight = startHeight + (e.clientY - startY);
                  break;
              }
              
              // Apply minimum dimensions
              newWidth = Math.max(150, newWidth);
              newHeight = Math.max(100, newHeight);
              
              // Apply the new dimensions
              betDisplay.style.width = newWidth + 'px';
              betDisplay.style.height = newHeight + 'px';
              betDisplay.style.top = newTop + 'px';
              betDisplay.style.left = newLeft + 'px';
              
              // Update the body max-height to match the new container height
              if (betDisplayBody) {
                const headerHeight = betDisplay.querySelector('.bet-display-header').offsetHeight || 40;
                const stakeControlHeight = betDisplay.querySelector('.stake-control').offsetHeight || 40;
                betDisplayBody.style.maxHeight = (newHeight - headerHeight - stakeControlHeight) + 'px';
              }
            }
            
            // Function to end resizing
            function enhancedEndResize() {
              if (!isResizing) return;
              
              isResizing = false;
              betDisplay.classList.remove('resizing');
              
              // Remove event listeners
              document.removeEventListener('mousemove', enhancedDoResize);
              document.removeEventListener('mouseup', enhancedEndResize);
            }
            
            // Add event listeners for resize
            document.addEventListener('mousemove', enhancedDoResize);
            document.addEventListener('mouseup', enhancedEndResize);
          });
        });
        
        console.log('Successfully enhanced resize capability with top-edge resizing');
      } else {
        console.log('Could not find original resize functions to enhance');
      }
    }
    
    // Try to enhance the resize capability
    try {
      enhanceResizeCapability();
    } catch (e) {
      console.error('Error enhancing resize capability:', e);
    }
    
    // Success message
    console.log('Applied all enhancements for unlimited resizing in all directions');
    alert('Enhancements applied! You should now be able to:\n1. Resize downward past the red line\n2. Resize from the top edge\n3. Resize in any direction without limitations');
  } else {
    console.error('Could not find bet display container');
    alert('Error: Could not find bet display container');
  }
})(); 