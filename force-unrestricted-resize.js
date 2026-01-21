// DIRECT APPROACH to force unrestricted resizing
(function() {
  console.log("STARTING AGGRESSIVE UNRESTRICTED RESIZING FIX");
  
  // Break through any size limitations by modifying the DOM structure directly
  try {
    // 1. Find the bet display container and force its styles
    const betDisplay = document.getElementById('bet-display-container');
    if (!betDisplay) {
      // Try to find it by class if ID doesn't work
      betDisplay = document.querySelector('.bet-display-container');
    }
    
    if (betDisplay) {
      console.log("Found bet display container, applying aggressive fixes...");
      
      // 2. Set inline styles with !important flag via style attribute
      betDisplay.setAttribute('style', 'height: auto !important; max-height: none !important; min-height: 100px !important; position: absolute !important; z-index: 9999 !important; overflow: visible !important;');
      
      // 3. Force inner elements to have unlimited height
      const allChildren = betDisplay.querySelectorAll('*');
      allChildren.forEach(el => {
        el.style.maxHeight = 'none';
        el.style.overflow = 'visible';
      });
      
      // 4. Specifically target the body and list elements
      const betDisplayBody = betDisplay.querySelector('.bet-display-body');
      if (betDisplayBody) {
        betDisplayBody.setAttribute('style', 'max-height: none !important; height: auto !important; overflow-y: auto !important;');
      }
      
      const betDisplayList = betDisplay.querySelector('.bet-display-list');
      if (betDisplayList) {
        betDisplayList.setAttribute('style', 'max-height: none !important; height: auto !important; overflow-y: auto !important;');
      }
      
      // 5. Force override any CSS restrictions with an injected style tag with extreme specificity
      const styleOverride = document.createElement('style');
      styleOverride.id = 'extreme-override-styles';
      styleOverride.innerHTML = `
        /* Super high specificity to override any CSS rules */
        html body #website-wrapper .roulette-table #bet-display-container,
        html body #website-wrapper .roulette-table .bet-display-container,
        html body .website-wrapper .roulette-table #bet-display-container,
        html body .website-wrapper .roulette-table .bet-display-container,
        #bet-display-container,
        .bet-display-container {
          height: auto !important;
          max-height: none !important;
          min-height: 100px !important;
          position: absolute !important;
          z-index: 9999 !important;
          overflow: visible !important;
        }

        html body #website-wrapper .roulette-table #bet-display-container *,
        html body #website-wrapper .roulette-table .bet-display-container *,
        html body .website-wrapper .roulette-table #bet-display-container *,
        html body .website-wrapper .roulette-table .bet-display-container *,
        #bet-display-container *,
        .bet-display-container * {
          max-height: none !important;
        }
        
        html body .bet-display-body,
        .bet-display-body {
          max-height: none !important;
          height: auto !important;
          overflow-y: auto !important;
        }
        
        html body .bet-display-list,
        .bet-display-list {
          max-height: none !important;
          height: auto !important;
          overflow-y: auto !important;
        }
        
        /* Force the container to extend past the red line */
        .roulette-table {
          overflow: visible !important;
        }
        
        /* Make sure the resize handles are visible and usable */
        .resize-handle {
          width: 20px !important;
          height: 20px !important;
          background-color: rgba(255, 255, 255, 0.2) !important;
          position: absolute !important;
          z-index: 10000 !important;
        }
        
        /* Ensure handle positions */
        .resize-s, .resize-se, .resize-sw {
          bottom: 0 !important;
        }
        
        .resize-e, .resize-ne, .resize-se {
          right: 0 !important;
        }
        
        .resize-w, .resize-nw, .resize-sw {
          left: 0 !important;
        }
        
        .resize-n, .resize-ne, .resize-nw {
          top: 0 !important;
        }
      `;
      document.head.appendChild(styleOverride);
      
      // 6. Add additional resize handles if needed
      if (!betDisplay.querySelector('.resize-n')) {
        console.log("Adding additional resize handles for all directions");
        
        const directions = ['n', 'ne', 'nw'];
        
        directions.forEach(dir => {
          const handle = document.createElement('div');
          handle.className = `resize-handle resize-${dir}`;
          handle.setAttribute('data-resize', dir);
          
          // Set specific styles based on direction
          if (dir === 'n') {
            handle.style.left = '20px';
            handle.style.right = '20px';
            handle.style.top = '0';
            handle.style.height = '20px';
            handle.style.cursor = 'ns-resize';
          } else if (dir === 'ne') {
            handle.style.right = '0';
            handle.style.top = '0';
            handle.style.width = '20px';
            handle.style.height = '20px';
            handle.style.cursor = 'nesw-resize';
          } else if (dir === 'nw') {
            handle.style.left = '0';
            handle.style.top = '0';
            handle.style.width = '20px';
            handle.style.height = '20px';
            handle.style.cursor = 'nwse-resize';
          }
          
          handle.style.position = 'absolute';
          handle.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
          handle.style.zIndex = '10000';
          
          betDisplay.appendChild(handle);
        });
      }
      
      // 7. Add a mutation observer to constantly ensure our styles are applied
      const observer = new MutationObserver((mutations) => {
        betDisplay.style.maxHeight = 'none';
        betDisplay.style.height = 'auto';
        
        if (betDisplayBody) {
          betDisplayBody.style.maxHeight = 'none';
        }
        
        if (betDisplayList) {
          betDisplayList.style.maxHeight = 'none';
        }
      });
      
      observer.observe(betDisplay, { attributes: true, attributeFilter: ['style'] });
      
      // 8. Add drag event listeners for the top resize handles
      document.querySelectorAll('.resize-n, .resize-ne, .resize-nw').forEach(handle => {
        handle.addEventListener('mousedown', function(e) {
          e.preventDefault();
          const dir = handle.getAttribute('data-resize');
          
          const startY = e.clientY;
          const startX = e.clientX;
          const rect = betDisplay.getBoundingClientRect();
          const startHeight = rect.height;
          const startWidth = rect.width;
          const startTop = rect.top;
          const startLeft = rect.left;
          
          function moveHandle(e) {
            // Calculate new dimensions
            let newHeight, newWidth, newTop, newLeft;
            
            // Vertical resizing (all top handles)
            const dy = e.clientY - startY;
            newHeight = Math.max(100, startHeight - dy);
            newTop = startTop + (startHeight - newHeight);
            
            // Additional horizontal resizing for corner handles
            if (dir === 'ne') {
              // Northeast: also resize right edge
              const dx = e.clientX - startX;
              newWidth = Math.max(150, startWidth + dx);
              newLeft = startLeft;
            } else if (dir === 'nw') {
              // Northwest: also resize left edge
              const dx = e.clientX - startX;
              newWidth = Math.max(150, startWidth - dx);
              newLeft = startLeft + (startWidth - newWidth);
            } else {
              // North: only vertical resize
              newWidth = startWidth;
              newLeft = startLeft;
            }
            
            // Apply new dimensions
            betDisplay.style.height = newHeight + 'px';
            betDisplay.style.width = newWidth + 'px';
            betDisplay.style.top = newTop + 'px';
            betDisplay.style.left = newLeft + 'px';
            
            // Also update the body height
            if (betDisplayBody) {
              const headerHeight = betDisplay.querySelector('.bet-display-header')?.offsetHeight || 50;
              const stakeControlHeight = betDisplay.querySelector('.stake-control')?.offsetHeight || 40;
              betDisplayBody.style.maxHeight = (newHeight - headerHeight - stakeControlHeight) + 'px';
            }
          }
          
          function releaseHandle() {
            document.removeEventListener('mousemove', moveHandle);
            document.removeEventListener('mouseup', releaseHandle);
          }
          
          document.addEventListener('mousemove', moveHandle);
          document.addEventListener('mouseup', releaseHandle);
        });
      });
      
      console.log("AGGRESSIVE FIX COMPLETE - Try resizing now");
      alert("Extreme unrestricted resizing has been applied! You should now be able to:\n1. Resize past the red line\n2. Resize from the top edge\n3. Move and resize without limitations");
    } else {
      console.error("Could not find the bet display container!");
      alert("ERROR: Could not find the bet display container!");
    }
  } catch (error) {
    console.error("Error applying fixes:", error);
    alert("Error while applying fixes: " + error.message);
  }
})(); 