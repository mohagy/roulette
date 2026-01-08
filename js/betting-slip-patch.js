/**
 * Betting Slip Draw Number Patch
 *
 * This script patches the betting slip functionality to include the draw number
 * in the betting slip preview and printed receipt.
 */

document.addEventListener('DOMContentLoaded', function() {
  // Wait for scripts.js to fully load
  setTimeout(function() {
    patchBettingSlip();
  }, 1000);
});

function patchBettingSlip() {
  // Store the original printBettingSlip function if it exists
  if (typeof betTracker !== 'undefined' && typeof betTracker.printBettingSlip === 'function') {
    console.log('Patching betting slip functionality to include draw numbers...');

    // Store the original function
    const originalPrintBettingSlip = betTracker.printBettingSlip;

    // Replace with our new function
    betTracker.printBettingSlip = function() {
      // Check if multi-draw mode is active
      if (typeof window.HybridMultiDraw !== 'undefined' && window.HybridMultiDraw.isActive()) {
        console.log('🎰 Multi-draw mode detected - creating multiple slips');
        return this.handleMultiDrawPrint();
      }

      // Single draw mode - generate a simple random barcode number
      const barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();

      // Format current date and time for the receipt
      const now = new Date();
      const dateTimeStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();

      // Get the selected draw number
      let drawNumber;

      // First, check if we have a selectedDrawNumber from the draw header
      if (typeof window.selectedDrawNumber === 'number' && window.selectedDrawNumber > 0) {
        // It's a valid number, use it
        drawNumber = window.selectedDrawNumber;
        console.log('Using selected draw number:', drawNumber);
      }
      // Check if we have a selected draw element in the UI
      else if (document.querySelector('.draw-number.selected')) {
        // Get the draw number from the selected element
        const selectedElement = document.querySelector('.draw-number.selected');
        if (selectedElement && selectedElement.dataset && selectedElement.dataset.draw) {
          drawNumber = parseInt(selectedElement.dataset.draw);
          console.log('Using draw number from selected UI element:', drawNumber);
        }
      }
      // If drawHeader is available, use the current draw number from it
      else if (window.drawHeader && typeof window.drawHeader.currentDrawNumber === 'number' && window.drawHeader.currentDrawNumber > 0) {
        drawNumber = window.drawHeader.currentDrawNumber;
        console.log('Using current draw number from drawHeader:', drawNumber);
      }
      // Try to get the next draw number from the UI
      else if (document.getElementById('next-draw-number')) {
        const nextDrawText = document.getElementById('next-draw-number').textContent;
        const match = nextDrawText.match(/#(\d+)/);
        if (match && match[1]) {
          drawNumber = parseInt(match[1], 10);
          console.log('Using next draw number from UI:', drawNumber);
        }
      }
      // Try the getCurrentDrawNumber function
      else if (typeof getCurrentDrawNumber === 'function') {
        drawNumber = getCurrentDrawNumber();
        console.log('Using draw number from getCurrentDrawNumber():', drawNumber);
      }
      // Last resort
      else {
        // Check if any draw numbers are visible in the UI
        const drawNumbers = document.querySelectorAll('.draw-number');
        if (drawNumbers && drawNumbers.length > 0 && drawNumbers[0].dataset && drawNumbers[0].dataset.draw) {
          // Use the first (current) draw number
          drawNumber = parseInt(drawNumbers[0].dataset.draw);
          console.log('Using first visible draw number from UI:', drawNumber);
        } else {
          // Absolute fallback - use 19 as default based on the header
          drawNumber = 19;
          console.log('Falling back to default draw number (19)');
        }
      }

      // Ensure it's a valid number
      drawNumber = Number(drawNumber) || 19; // Default to draw 19 if invalid

      console.log('Final draw number for betting slip:', drawNumber);

      // Calculate total stakes and potential return
      let totalStakes = 0;
      let totalPotentialReturn = 0;
      this.bets.forEach(bet => {
        totalStakes += bet.amount;
        totalPotentialReturn += bet.potentialReturn;
      });

      // Register the ticket with the ticket manager (if ticketManager is defined)
      if (typeof ticketManager !== 'undefined') {
        ticketManager.addTicket(barcodeNumber, this.bets, totalStakes, totalPotentialReturn);
      }

      // Save the betting slip to the database, including the draw number
      if (typeof saveBettingSlipToDatabase === 'function') {
        // Make sure to pass the draw number to the database save function
        if (typeof window.originalSaveBettingSlipToDatabase === 'function') {
          // If we have a modified version with draw number support
          console.log(`Saving betting slip to database with draw #${drawNumber}`);
          saveBettingSlipToDatabase(barcodeNumber, this.bets, totalStakes, totalPotentialReturn);
        } else {
          // Create the formData directly for more control
          const formData = new FormData();
          formData.append('action', 'save_slip');
          formData.append('barcode', barcodeNumber);
          formData.append('bets', JSON.stringify(this.bets));
          formData.append('total_stakes', totalStakes);
          formData.append('potential_return', totalPotentialReturn);
          formData.append('date', new Date().toISOString());
          formData.append('draw_number', drawNumber);

          // Make the AJAX request
          const xhr = new XMLHttpRequest();
          xhr.open('POST', 'php/slip_api.php', true);
          xhr.onload = function() {
            if (xhr.status === 200) {
              console.log('Betting slip saved successfully with draw #' + drawNumber);
            } else {
              console.error('Error saving betting slip:', xhr.responseText);
            }
          };
          xhr.onerror = function() {
            console.error('Network error while saving betting slip');
          };
          xhr.send(formData);
        }
      }

      // Check if print modal already exists, if not, create it
      let existingModal = document.querySelector('.print-slip-modal');
      if (existingModal) {
        // Modal already exists, remove the X button if it's there
        const closeButton = existingModal.querySelector('.print-slip-close');
        if (closeButton) {
          console.log('🎰 PATCH: Removing existing X button from modal');
          closeButton.remove();
        }
        // Make existing modal draggable
        this.makePrintSlipModalDraggable();
      } else {
        // Create new modal without X button
        // Create the print modal structure
        const printModal = document.createElement('div');
        printModal.className = 'print-slip-modal';
        printModal.innerHTML = `
          <div class="print-slip-container">
            <div class="print-slip-header">
              <h2>Betting Slip Preview</h2>
            </div>
            <div class="print-slip-body">
              <div class="print-slip-content"></div>
              <div class="print-slip-actions">
                <button class="print-action-button"><i class="fas fa-print"></i> Print Slip</button>
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(printModal);

        // Make the modal draggable
        this.makePrintSlipModalDraggable();

        // Add event listener for the print button (no close button anymore)
        document.querySelector('.print-action-button').addEventListener('click', () => {
          // Create a hidden iframe for printing just the receipt
          const iframe = document.createElement('iframe');
          iframe.style.display = 'none';
          document.body.appendChild(iframe);

          const slipContent = document.querySelector('.print-slip-content').innerHTML;

          // Write the content to the iframe
          const doc = iframe.contentDocument || iframe.contentWindow.document;
          doc.write(`
            <html>
            <head>
              <title>Roulette Betting Slip</title>
              <style>
                body {
                  font-family: 'Courier New', monospace;
                  padding: 20px;
                  max-width: 350px;
                  margin: 0 auto;
                  background-color: white;
                  color: black;
                }
                .header {
                  text-align: center;
                  margin-bottom: 20px;
                  border-bottom: 2px dotted #000;
                  padding-bottom: 10px;
                }
                .header h1 {
                  margin: 0;
                  font-size: 24px;
                }
                .header p {
                  margin: 5px 0;
                  font-size: 14px;
                }
                .bet-item {
                  margin-bottom: 15px;
                  padding: 10px;
                  border-bottom: 1px dotted #ccc;
                }
                .bet-type {
                  font-weight: bold;
                  margin-bottom: 5px;
                }
                .bet-details {
                  display: flex;
                  justify-content: space-between;
                }
                .summary {
                  margin-top: 20px;
                  padding-top: 10px;
                  border-top: 2px dotted #000;
                  font-weight: bold;
                }
                .summary-row {
                  display: flex;
                  justify-content: space-between;
                  margin-bottom: 5px;
                }
                .barcode-container {
                  text-align: center;
                  margin: 20px 0;
                }
                .barcode-number {
                  font-size: 12px;
                  margin-top: 5px;
                }
                .footer {
                  text-align: center;
                  margin-top: 20px;
                  font-size: 12px;
                  border-top: 2px dotted #000;
                  padding-top: 10px;
                }
                .css-barcode {
                  display: flex;
                  justify-content: center;
                  height: 40px;
                  width: 95%;
                  margin: 10px auto;
                }
                .bar {
                  height: 100%;
                  width: 2px;
                  display: inline-block;
                  background: black;
                  margin-right: 1px;
                }
                .bar.thin {
                  width: 1px;
                }
                .bar.medium {
                  width: 2px;
                }
                .bar.thick {
                  width: 3px;
                }
              </style>
            </head>
            <body>
              ${slipContent}
            </body>
            </html>
          `);
          doc.close();

          // Print the iframe content
          iframe.contentWindow.focus();
          iframe.contentWindow.print();

          // Remove the iframe after printing
          setTimeout(() => {
            document.body.removeChild(iframe);
            // Close the modal after printing
            document.querySelector('.print-slip-modal').classList.remove('visible');

            // Clear the board for new bets WITHOUT refunding money
            this.clearBoardForNewBets();

            // Show confirmation message
            const message = document.createElement('div');
            message.style.position = 'fixed';
            message.style.top = '20px';
            message.style.left = '50%';
            message.style.transform = 'translateX(-50%)';
            message.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
            message.style.color = '#fff';
            message.style.padding = '10px 20px';
            message.style.borderRadius = '5px';
            message.style.zIndex = '10000';
            message.style.fontFamily = 'Arial, sans-serif';
            message.style.fontSize = '14px';
            message.textContent = 'Betting slip printed and saved. Board cleared for new bets!';
            document.body.appendChild(message);

            // Remove the message after 3 seconds
            setTimeout(() => {
              document.body.removeChild(message);
            }, 3000);
          }, 500);
        });
      } // End of else block for creating new modal

      // Generate the receipt HTML
      let receiptHTML = `
        <div class="header">
          <h1>ROULETTE BETTING SLIP</h1>
          <p>${dateTimeStr}</p>
          <p>Player ID: GUEST</p>
          <p>Draw #: ${drawNumber}</p>
        </div>

        <div class="bets-list">
      `;

      // Add each bet to the slip
      this.bets.forEach((bet, index) => {
        receiptHTML += `
          <div class="bet-item">
            <div class="bet-type">${index + 1}. ${bet.type.toUpperCase()}: ${bet.description}</div>
            <div class="bet-details">
              <div>Stake: $${bet.amount.toFixed(2)}</div>
              <div>Pays: ${this.getMultiplier(bet.type)}:1</div>
            </div>
            <div class="bet-details">
              <div></div>
              <div>Return: $${bet.potentialReturn.toFixed(2)}</div>
            </div>
          </div>
        `;
      });

      // Add summary and barcode
      receiptHTML += `
        </div>

        <div class="summary">
          <div class="summary-row">
            <div>Total Stakes:</div>
            <div>$${totalStakes.toFixed(2)}</div>
          </div>
          <div class="summary-row">
            <div>Draw Number:</div>
            <div>#${drawNumber}</div>
          </div>
        </div>

        <div class="barcode-container">
          <!-- CSS-based barcode as fallback -->
          <div class="css-barcode">
            ${this.generateCSSBarcode(barcodeNumber)}
          </div>
          <div class="barcode-number">${barcodeNumber}</div>
        </div>

        <div class="footer">
          <p>Good luck!</p>
          <p>This betting slip is for entertainment purposes only.</p>
          <p>Not redeemable for real money.</p>
        </div>
      `;

      // Update the modal content
      document.querySelector('.print-slip-content').innerHTML = receiptHTML;

      // Show the modal
      document.querySelector('.print-slip-modal').classList.add('visible');
    };

    // Add draggable functionality to the modal
    betTracker.makePrintSlipModalDraggable = function() {
      const modal = document.querySelector('.print-slip-modal');
      const container = modal.querySelector('.print-slip-container');
      const header = container.querySelector('.print-slip-header');

      if (!modal || !container || !header) return;

      console.log('🎰 PATCH: Making print slip modal draggable');

      // Add cursor style to header to indicate it's draggable
      header.style.cursor = 'move';
      header.style.userSelect = 'none';

      let isDragging = false;
      let currentX;
      let currentY;
      let initialX;
      let initialY;
      let xOffset = 0;
      let yOffset = 0;

      // Mouse events
      header.addEventListener('mousedown', dragStart);
      document.addEventListener('mousemove', dragMove);
      document.addEventListener('mouseup', dragEnd);

      // Touch events for mobile
      header.addEventListener('touchstart', dragStart);
      document.addEventListener('touchmove', dragMove);
      document.addEventListener('touchend', dragEnd);

      function dragStart(e) {
        if (e.type === 'touchstart') {
          initialX = e.touches[0].clientX - xOffset;
          initialY = e.touches[0].clientY - yOffset;
        } else {
          initialX = e.clientX - xOffset;
          initialY = e.clientY - yOffset;
        }

        if (e.target === header || header.contains(e.target)) {
          isDragging = true;
          container.style.transition = 'none';
        }
      }

      function dragMove(e) {
        if (isDragging) {
          e.preventDefault();

          if (e.type === 'touchmove') {
            currentX = e.touches[0].clientX - initialX;
            currentY = e.touches[0].clientY - initialY;
          } else {
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
          }

          xOffset = currentX;
          yOffset = currentY;

          // Apply transform to move the container
          container.style.transform = `translate(${currentX}px, ${currentY}px)`;
        }
      }

      function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
        container.style.transition = '';
      }
    };

    // Add multi-draw handling function
    betTracker.handleMultiDrawPrint = function() {
      console.log('🎰 Handling multi-draw betting slip creation');

      // Get multi-draw state
      const multiDrawState = window.HybridMultiDraw.getState();
      console.log('🔍 DEBUG: Retrieved multiDrawState from HybridMultiDraw:', multiDrawState);

      if (!multiDrawState.isActive || !multiDrawState.drawRange) {
        console.error('Multi-draw mode not properly configured');
        console.error('🔍 DEBUG: isActive:', multiDrawState.isActive);
        console.error('🔍 DEBUG: drawRange:', multiDrawState.drawRange);
        alert('Multi-draw configuration error. Please reconfigure and try again.');
        return;
      }

      if (this.bets.length === 0) {
        alert('Please place some bets first!');
        return;
      }

      // Calculate totals for confirmation
      let totalStakes = 0;
      let totalPotentialReturn = 0;
      this.bets.forEach(bet => {
        totalStakes += bet.amount;
        totalPotentialReturn += bet.potentialReturn;
      });

      const totalCost = totalStakes * multiDrawState.drawCount;
      console.log('🔍 DEBUG: Calculated totals - totalStakes:', totalStakes, 'totalCost:', totalCost);

      // Show comprehensive multi-draw preview modal
      this.showMultiDrawPreview(multiDrawState, totalStakes, totalCost);
    };

    // Create a single draw slip
    betTracker.createSingleDrawSlip = function(drawNumber, baseTime) {
      try {
        // Generate unique barcode for this slip
        const barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();

        // Calculate totals
        let totalStakes = 0;
        let totalPotentialReturn = 0;
        this.bets.forEach(bet => {
          totalStakes += bet.amount;
          totalPotentialReturn += bet.potentialReturn;
        });

        // Set the draw number for the betting system
        window.selectedDrawNumber = drawNumber;

        // Register with ticket manager
        if (typeof ticketManager !== 'undefined') {
          ticketManager.addTicket(barcodeNumber, this.bets, totalStakes, totalPotentialReturn);
        }

        // Save to database
        this.saveSingleSlipToDatabase(barcodeNumber, drawNumber, totalStakes, totalPotentialReturn);

        return {
          success: true,
          drawNumber: drawNumber,
          barcodeNumber: barcodeNumber,
          totalStakes: totalStakes,
          totalPotentialReturn: totalPotentialReturn,
          timestamp: baseTime
        };

      } catch (error) {
        console.error(`Error creating slip for draw ${drawNumber}:`, error);
        return {
          success: false,
          drawNumber: drawNumber,
          error: error.message
        };
      }
    };

    // Save single slip to database
    betTracker.saveSingleSlipToDatabase = function(barcodeNumber, drawNumber, totalStakes, totalPotentialReturn) {
      if (typeof saveBettingSlipToDatabase === 'function') {
        if (typeof window.originalSaveBettingSlipToDatabase === 'function') {
          console.log(`Saving betting slip to database with draw #${drawNumber}`);
          saveBettingSlipToDatabase(barcodeNumber, this.bets, totalStakes, totalPotentialReturn);
        } else {
          // Create the formData directly
          const formData = new FormData();
          formData.append('action', 'save_slip');
          formData.append('barcode', barcodeNumber);
          formData.append('bets', JSON.stringify(this.bets));
          formData.append('total_stakes', totalStakes);
          formData.append('potential_return', totalPotentialReturn);
          formData.append('date', new Date().toISOString());
          formData.append('draw_number', drawNumber);

          // Make the AJAX request
          const xhr = new XMLHttpRequest();
          xhr.open('POST', 'php/slip_api.php', true);
          xhr.onload = function() {
            if (xhr.status === 200) {
              console.log('Betting slip saved successfully with draw #' + drawNumber);
            } else {
              console.error('Error saving betting slip:', xhr.responseText);
            }
          };
          xhr.onerror = function() {
            console.error('Network error while saving betting slip');
          };
          xhr.send(formData);
        }
      }
    };

    // Show multi-draw preview modal (REMOVED - using the comprehensive version below)

    // Add styles for multi-draw preview
    betTracker.addMultiDrawPreviewStyles = function() {
      const styleId = 'multi-draw-preview-styles';
      if (document.getElementById(styleId)) return;

      const style = document.createElement('style');
      style.id = styleId;
      style.textContent = `
        .multi-draw-preview-modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.8);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 10000;
          font-family: Arial, sans-serif;
        }

        .preview-modal-container {
          background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.95) 100%);
          border: 2px solid #ffcc00;
          border-radius: 12px;
          max-width: 90%;
          width: 1000px;
          max-height: 90vh;
          display: flex;
          flex-direction: column;
          overflow: hidden;
        }

        .preview-modal-header {
          background: #ffcc00;
          color: #1a1a1a;
          padding: 15px 20px;
          text-align: center;
          cursor: move;
        }

        .preview-modal-header h2 {
          margin: 0 0 5px 0;
          font-size: 18px;
        }

        .preview-summary {
          font-size: 14px;
          font-weight: bold;
        }

        .preview-modal-body {
          flex: 1;
          overflow-y: auto;
          padding: 20px;
        }

        .preview-slips-container {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
          gap: 20px;
          max-height: 60vh;
          overflow-y: auto;
        }

        .slip-preview-card {
          background: rgba(255, 255, 255, 0.1);
          border: 1px solid rgba(255, 204, 0, 0.3);
          border-radius: 8px;
          overflow: hidden;
        }

        .slip-preview-header {
          background: rgba(255, 204, 0, 0.2);
          padding: 10px 15px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          border-bottom: 1px solid rgba(255, 204, 0, 0.3);
        }

        .slip-number {
          font-size: 12px;
          color: #ccc;
          font-weight: bold;
        }

        .slip-draw {
          font-size: 14px;
          color: #ffcc00;
          font-weight: bold;
        }

        .slip-preview-content {
          padding: 15px;
          color: #fff;
        }

        .slip-info {
          margin-bottom: 15px;
          background: rgba(255, 255, 255, 0.05);
          padding: 10px;
          border-radius: 6px;
        }

        .slip-info-row {
          display: flex;
          justify-content: space-between;
          margin-bottom: 5px;
          font-size: 12px;
        }

        .slip-info-row:last-child {
          margin-bottom: 0;
        }

        .slip-info-row span:first-child {
          color: #ccc;
        }

        .slip-info-row span:last-child {
          color: #ffcc00;
          font-weight: bold;
        }

        .slip-bets h4 {
          color: #ffcc00;
          margin: 0 0 10px 0;
          font-size: 14px;
        }

        .slip-bet-item {
          margin-bottom: 10px;
          padding: 8px;
          background: rgba(255, 255, 255, 0.05);
          border-radius: 4px;
        }

        .bet-description {
          font-size: 12px;
          font-weight: bold;
          margin-bottom: 5px;
          color: #fff;
        }

        .bet-amounts {
          display: flex;
          justify-content: space-between;
          font-size: 11px;
          color: #ccc;
        }

        .slip-summary {
          margin-top: 15px;
          padding-top: 10px;
          border-top: 1px solid rgba(255, 204, 0, 0.3);
        }

        .summary-row {
          display: flex;
          justify-content: space-between;
          font-weight: bold;
          color: #ffcc00;
        }

        .preview-modal-footer {
          background: rgba(255, 255, 255, 0.05);
          padding: 15px 20px;
          border-top: 1px solid rgba(255, 204, 0, 0.3);
        }

        .print-confirmation-message {
          background: rgba(0, 123, 255, 0.1);
          border: 1px solid rgba(0, 123, 255, 0.3);
          border-radius: 6px;
          padding: 10px 15px;
          margin-bottom: 15px;
          text-align: center;
        }

        .print-confirmation-message p {
          margin: 0;
          color: #007bff;
          font-size: 13px;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
        }

        .print-confirmation-message i {
          color: #007bff;
        }

        .preview-footer-buttons {
          display: flex;
          justify-content: space-between;
          gap: 15px;
        }

        .preview-btn {
          flex: 1;
          padding: 12px 20px;
          border: none;
          border-radius: 6px;
          font-weight: bold;
          font-size: 14px;
          cursor: pointer;
          transition: all 0.3s ease;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
        }

        .cancel-preview-btn {
          background: #ff6b6b;
          color: white;
        }

        .cancel-preview-btn:hover {
          background: #ee5a52;
        }

        .print-all-btn {
          background: #00b894;
          color: white;
        }

        .print-all-btn:hover {
          background: #00a085;
        }

        /* Scrollbar styling */
        .preview-slips-container::-webkit-scrollbar,
        .preview-modal-body::-webkit-scrollbar {
          width: 6px;
        }

        .preview-slips-container::-webkit-scrollbar-track,
        .preview-modal-body::-webkit-scrollbar-track {
          background: rgba(255, 255, 255, 0.1);
        }

        .preview-slips-container::-webkit-scrollbar-thumb,
        .preview-modal-body::-webkit-scrollbar-thumb {
          background: #ffcc00;
          border-radius: 3px;
        }
      `;

      document.head.appendChild(style);
    };

    // Make preview modal draggable
    betTracker.makePreviewModalDraggable = function(modal) {
      const header = modal.querySelector('.preview-modal-header');
      const container = modal.querySelector('.preview-modal-container');

      let isDragging = false;
      let currentX, currentY, initialX, initialY;
      let xOffset = 0, yOffset = 0;

      header.addEventListener('mousedown', (e) => {
        initialX = e.clientX - xOffset;
        initialY = e.clientY - yOffset;
        isDragging = true;
        container.style.transition = 'none';
      });

      document.addEventListener('mousemove', (e) => {
        if (isDragging) {
          e.preventDefault();
          currentX = e.clientX - initialX;
          currentY = e.clientY - initialY;
          xOffset = currentX;
          yOffset = currentY;
          container.style.transform = `translate(${currentX}px, ${currentY}px)`;
        }
      });

      document.addEventListener('mouseup', () => {
        isDragging = false;
        container.style.transition = '';
      });
    };

    // Execute multi-draw print after preview confirmation
    betTracker.executeMultiDrawPrint = function(multiDrawState, totalStakes, totalCost) {
      console.log('🎰 Executing multi-draw print after preview confirmation');

      // Create slips for each draw
      const slipsCreated = [];
      const currentTime = new Date();

      try {
        for (let drawNum = multiDrawState.drawRange.start; drawNum <= multiDrawState.drawRange.end; drawNum++) {
          const slipResult = this.createSingleDrawSlip(drawNum, currentTime);
          if (slipResult.success) {
            slipsCreated.push(slipResult);
          }
        }

        // Print all slips to physical printer
        this.printMultiDrawSlips(slipsCreated, currentTime);

        // Show success message
        this.showMultiDrawSuccess(slipsCreated, totalCost);

        // Clear bets and disable multi-draw
        this.clearBoardForNewBets();
        if (typeof window.HybridMultiDraw.disable === 'function') {
          window.HybridMultiDraw.disable();
        }

      } catch (error) {
        console.error('Error creating multi-draw slips:', error);
        alert('Error creating betting slips. Please try again.');
      }
    };

    // Print multiple slips to physical printer
    betTracker.printMultiDrawSlips = function(slipsCreated, currentTime) {
      console.log('🎰 Printing multiple betting slips to physical printer');

      if (!slipsCreated || slipsCreated.length === 0) {
        console.error('No slips to print');
        return;
      }

      // Create a combined print document for all slips
      const iframe = document.createElement('iframe');
      iframe.style.display = 'none';
      document.body.appendChild(iframe);

      const doc = iframe.contentDocument || iframe.contentWindow.document;

      // Start building the combined print document
      let combinedHTML = `
        <html>
        <head>
          <title>Multi-Draw Betting Slips</title>
          <style>
            body {
              font-family: 'Courier New', monospace;
              font-size: 12px;
              margin: 0;
              padding: 0;
              line-height: 1.2;
            }
            .slip-container {
              width: 100%;
              margin-bottom: 20px;
              page-break-after: always;
              border: 1px dashed #000;
              padding: 10px;
            }
            .slip-container:last-child {
              page-break-after: auto;
            }
            .header {
              text-align: center;
              margin-bottom: 10px;
              border-bottom: 1px solid #000;
              padding-bottom: 5px;
            }
            .header h1 {
              margin: 0;
              font-size: 16px;
              font-weight: bold;
            }
            .bets-list {
              margin: 10px 0;
            }
            .bet-item {
              margin-bottom: 8px;
              border-bottom: 1px dotted #ccc;
              padding-bottom: 5px;
            }
            .bet-type {
              font-weight: bold;
              margin-bottom: 2px;
            }
            .bet-details {
              display: flex;
              justify-content: space-between;
              font-size: 11px;
            }
            .summary {
              border-top: 2px solid #000;
              padding-top: 5px;
              margin-top: 10px;
              font-weight: bold;
            }
            .barcode {
              text-align: center;
              margin-top: 10px;
              font-family: 'Courier New', monospace;
              border-top: 1px solid #000;
              padding-top: 5px;
            }
            .disclaimer {
              font-size: 10px;
              font-style: italic;
              margin-top: 5px;
              text-align: center;
            }
          </style>
        </head>
        <body>
      `;

      // Generate HTML for each slip
      slipsCreated.forEach((slip, index) => {
        const dateTimeStr = currentTime.toLocaleDateString() + ' ' + currentTime.toLocaleTimeString();

        combinedHTML += `
          <div class="slip-container">
            <div class="header">
              <h1>ROULETTE BETTING SLIP</h1>
              <p>${dateTimeStr}</p>
              <p>Player ID: GUEST</p>
              <p>Draw #: ${slip.drawNumber}</p>
              <p>Slip ${index + 1} of ${slipsCreated.length}</p>
            </div>

            <div class="bets-list">
        `;

        // Add each bet to the slip
        this.bets.forEach((bet, betIndex) => {
          combinedHTML += `
            <div class="bet-item">
              <div class="bet-type">${betIndex + 1}. ${bet.type.toUpperCase()}: ${bet.description}</div>
              <div class="bet-details">
                <div>Stake: $${bet.amount.toFixed(2)}</div>
                <div>Pays: ${this.getMultiplier(bet.type)}:1</div>
              </div>
              <div class="bet-details">
                <div></div>
                <div>Return: $${bet.potentialReturn.toFixed(2)}</div>
              </div>
            </div>
          `;
        });

        combinedHTML += `
            </div>

            <div class="summary">
              <div style="display: flex; justify-content: space-between;">
                <span>Total Stakes:</span>
                <span>$${slip.totalStakes.toFixed(2)}</span>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span>Potential Return:</span>
                <span>$${slip.totalPotentialReturn.toFixed(2)}</span>
              </div>
            </div>

            <div class="barcode">
              <div style="font-size: 20px; letter-spacing: 2px;">||||| |||| ||||| |||| |||||</div>
              <div style="margin-top: 5px;">${slip.barcodeNumber}</div>
            </div>

            <div class="disclaimer">
              Good luck! Please keep this slip safe for prize collection.
            </div>
          </div>
        `;
      });

      combinedHTML += `
        </body>
        </html>
      `;

      // Write the content to the iframe
      doc.write(combinedHTML);
      doc.close();

      // Wait for content to load, then print
      setTimeout(() => {
        try {
          iframe.contentWindow.focus();
          iframe.contentWindow.print();

          console.log(`🎰 Successfully sent ${slipsCreated.length} betting slips to printer`);

          // Remove the iframe after printing
          setTimeout(() => {
            if (document.body.contains(iframe)) {
              document.body.removeChild(iframe);
            }
          }, 1000);

        } catch (error) {
          console.error('Error printing multi-draw slips:', error);
          alert('Error printing slips. Please try again or check your printer.');

          // Clean up iframe on error
          if (document.body.contains(iframe)) {
            document.body.removeChild(iframe);
          }
        }
      }, 500);
    };

    // Show comprehensive multi-draw preview modal
    betTracker.showMultiDrawPreview = function(multiDrawState, totalStakes, totalCost) {
      console.log('🎰 Showing comprehensive multi-draw preview modal');
      console.log('🔍 DEBUG: multiDrawState received:', multiDrawState);
      console.log('🔍 DEBUG: drawRange:', multiDrawState.drawRange);
      console.log('🔍 DEBUG: drawCount:', multiDrawState.drawCount);

      // Validate multiDrawState
      if (!multiDrawState || !multiDrawState.drawRange) {
        console.error('❌ Invalid multiDrawState or missing drawRange');
        alert('Multi-draw configuration error. Please reconfigure and try again.');
        return;
      }

      // Generate preview data for all slips (without saving to database)
      const currentTime = new Date();
      const dateTimeStr = currentTime.toLocaleDateString() + ' ' + currentTime.toLocaleTimeString();
      const previewSlips = [];

      // Generate preview data for each draw
      console.log(`🔍 DEBUG: Generating slips for draws ${multiDrawState.drawRange.start} to ${multiDrawState.drawRange.end}`);
      for (let drawNum = multiDrawState.drawRange.start; drawNum <= multiDrawState.drawRange.end; drawNum++) {
        const barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();
        console.log(`🔍 DEBUG: Creating slip for draw #${drawNum}`);
        previewSlips.push({
          drawNumber: drawNum,
          barcodeNumber: barcodeNumber,
          totalStakes: totalStakes,
          bets: [...this.bets], // Copy of current bets
          dateTime: dateTimeStr
        });
      }

      console.log('🔍 DEBUG: Generated preview slips:', previewSlips);

      // Create comprehensive preview modal
      const previewModal = document.createElement('div');
      previewModal.className = 'multi-draw-preview-modal';
      previewModal.innerHTML = `
        <div class="preview-modal-container">
          <div class="preview-modal-header">
            <h2>🎯 Multi-Draw Betting Slips Preview</h2>
            <div class="preview-summary">
              <span>${multiDrawState.drawCount} slips • Total: $${totalCost.toFixed(2)}</span>
            </div>
          </div>
          <div class="preview-modal-body">
            <div class="preview-slips-container">
              ${previewSlips.map((slip, index) => this.generateSlipPreviewHTML(slip, index + 1, multiDrawState.drawCount)).join('')}
            </div>
          </div>
          <div class="preview-modal-footer">
            <div class="print-confirmation-message">
              <p><i class="fas fa-info-circle"></i> Clicking "Print All Slips" will send all ${multiDrawState.drawCount} betting slips to your printer and save them to the database.</p>
            </div>
            <div class="preview-footer-buttons">
              <button class="preview-btn cancel-preview-btn">
                <i class="fas fa-times"></i> Cancel
              </button>
              <button class="preview-btn print-all-btn">
                <i class="fas fa-print"></i> Print All ${multiDrawState.drawCount} Slips
              </button>
            </div>
          </div>
        </div>
      `;

      // Add styles for the preview modal
      this.addMultiDrawPreviewStyles();

      // Add to body
      document.body.appendChild(previewModal);

      // Setup event handlers
      previewModal.querySelector('.cancel-preview-btn').addEventListener('click', () => {
        document.body.removeChild(previewModal);
        this.removeMultiDrawPreviewStyles();
      });

      previewModal.querySelector('.print-all-btn').addEventListener('click', () => {
        document.body.removeChild(previewModal);
        this.removeMultiDrawPreviewStyles();
        this.executeMultiDrawPrint(multiDrawState, totalStakes, totalCost);
      });

      // Make modal draggable
      this.makePreviewModalDraggable(previewModal);
    };

    // Generate HTML for individual slip preview
    betTracker.generateSlipPreviewHTML = function(slip, slipIndex, totalSlips) {
      let slipHTML = `
        <div class="slip-preview-card">
          <div class="slip-preview-header">
            <div class="slip-number">Slip ${slipIndex} of ${totalSlips}</div>
            <div class="slip-draw">Draw #${slip.drawNumber}</div>
          </div>
          <div class="slip-preview-content">
            <div class="slip-info-section">
              <div class="slip-info-header">ROULETTE BETTING SLIP</div>
              <div class="slip-info-details">
                <div class="slip-info-row">
                  <span>Date/Time:</span>
                  <span>${slip.dateTime}</span>
                </div>
                <div class="slip-info-row">
                  <span>Player ID:</span>
                  <span>GUEST</span>
                </div>
                <div class="slip-info-row">
                  <span>Draw #:</span>
                  <span>${slip.drawNumber}</span>
                </div>
                <div class="slip-info-row">
                  <span>Barcode:</span>
                  <span>${slip.barcodeNumber}</span>
                </div>
              </div>
            </div>

            <div class="slip-bets-section">
              <h4>Bets:</h4>
              <div class="slip-bets-list">
                ${slip.bets.map((bet, index) => `
                  <div class="slip-bet-item">
                    <div class="bet-header">${index + 1}. ${bet.type.toUpperCase()}: ${bet.description}</div>
                    <div class="bet-details">
                      <div class="bet-detail">
                        <span>Stake:</span>
                        <span>$${bet.amount.toFixed(2)}</span>
                      </div>
                      <div class="bet-detail">
                        <span>Pays:</span>
                        <span>${this.getMultiplier(bet.type)}:1</span>
                      </div>
                      <div class="bet-detail">
                        <span>Return:</span>
                        <span>$${bet.potentialReturn.toFixed(2)}</span>
                      </div>
                    </div>
                  </div>
                `).join('')}
              </div>
            </div>

            <div class="slip-summary-section">
              <div class="summary-row">
                <span>Total Stakes:</span>
                <span>$${slip.totalStakes.toFixed(2)}</span>
              </div>
              <div class="summary-row">
                <span>Draw Number:</span>
                <span>#${slip.drawNumber}</span>
              </div>
            </div>

            <div class="slip-barcode-section">
              <div class="barcode-placeholder">
                <div class="barcode-visual">||||| |||| ||||| |||| |||||</div>
                <div class="barcode-number">${slip.barcodeNumber}</div>
              </div>
            </div>
          </div>
        </div>
      `;

      return slipHTML;
    };

    // Show multi-draw success message
    betTracker.showMultiDrawSuccess = function(slipsCreated, totalCost) {
      const successCount = slipsCreated.filter(slip => slip.success).length;

      // Create a beautiful success modal
      const successModal = document.createElement('div');
      successModal.className = 'multi-draw-success-modal';
      successModal.innerHTML = `
        <div class="success-modal-container">
          <div class="success-modal-header">
            <h2>🎯 Multi-Draw Betting Complete!</h2>
          </div>
          <div class="success-modal-body">
            <div class="success-stats">
              <div class="success-stat">
                <div class="stat-value">${successCount}</div>
                <div class="stat-label">Slips Printed</div>
              </div>
              <div class="success-stat">
                <div class="stat-value">$${totalCost.toFixed(2)}</div>
                <div class="stat-label">Total Cost</div>
              </div>
            </div>
            <div class="success-details">
              <h3>Printed Slips:</h3>
              <div class="slips-list">
                ${slipsCreated.map(slip =>
                  `<div class="slip-item">
                    <span>Draw #${slip.drawNumber}</span>
                    <span>${slip.barcodeNumber}</span>
                    <span>$${slip.totalStakes.toFixed(2)}</span>
                  </div>`
                ).join('')}
              </div>
            </div>
            <div class="success-message">
              <p>✅ All betting slips have been sent to the printer!</p>
              <p>Please collect your printed slips from the printer.</p>
            </div>
            <div class="success-actions">
              <button class="success-close-btn">Continue Betting</button>
            </div>
          </div>
        </div>
      `;

      // Add styles for the success modal
      const style = document.createElement('style');
      style.textContent = `
        .multi-draw-success-modal {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: rgba(0, 0, 0, 0.8);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 10000;
          font-family: Arial, sans-serif;
        }

        .success-modal-container {
          background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.95) 100%);
          border: 2px solid #ffcc00;
          border-radius: 12px;
          max-width: 500px;
          width: 90%;
          max-height: 80vh;
          overflow-y: auto;
        }

        .success-modal-header {
          background: #ffcc00;
          color: #1a1a1a;
          padding: 15px 20px;
          text-align: center;
          border-radius: 10px 10px 0 0;
        }

        .success-modal-header h2 {
          margin: 0;
          font-size: 18px;
        }

        .success-modal-body {
          padding: 20px;
          color: #fff;
        }

        .success-stats {
          display: flex;
          justify-content: space-around;
          margin-bottom: 20px;
        }

        .success-stat {
          text-align: center;
          background: rgba(255, 255, 255, 0.1);
          padding: 15px;
          border-radius: 8px;
          border: 1px solid rgba(255, 204, 0, 0.3);
        }

        .stat-value {
          font-size: 24px;
          font-weight: bold;
          color: #ffcc00;
          margin-bottom: 5px;
        }

        .stat-label {
          font-size: 12px;
          color: #ccc;
          text-transform: uppercase;
        }

        .success-details h3 {
          color: #ffcc00;
          margin-bottom: 10px;
          font-size: 14px;
        }

        .slips-list {
          max-height: 200px;
          overflow-y: auto;
          background: rgba(255, 255, 255, 0.05);
          border-radius: 6px;
          padding: 10px;
        }

        .slip-item {
          display: flex;
          justify-content: space-between;
          padding: 8px 0;
          border-bottom: 1px solid rgba(255, 255, 255, 0.1);
          font-size: 12px;
        }

        .slip-item:last-child {
          border-bottom: none;
        }

        .success-message {
          background: rgba(0, 184, 148, 0.1);
          border: 1px solid rgba(0, 184, 148, 0.3);
          border-radius: 6px;
          padding: 15px;
          margin: 15px 0;
          text-align: center;
        }

        .success-message p {
          margin: 5px 0;
          color: #00b894;
        }

        .success-actions {
          text-align: center;
          margin-top: 20px;
        }

        .success-close-btn {
          background: #00b894;
          color: white;
          border: none;
          padding: 12px 30px;
          border-radius: 6px;
          font-weight: bold;
          cursor: pointer;
          transition: background 0.3s ease;
        }

        .success-close-btn:hover {
          background: #00a085;
        }
      `;

      document.head.appendChild(style);
      document.body.appendChild(successModal);

      // Add close functionality
      successModal.querySelector('.success-close-btn').addEventListener('click', () => {
        document.body.removeChild(successModal);
        document.head.removeChild(style);
      });

      // Auto-close after 10 seconds
      setTimeout(() => {
        if (document.body.contains(successModal)) {
          document.body.removeChild(successModal);
          document.head.removeChild(style);
        }
      }, 10000);
    };

    // Add debugging function for troubleshooting
    betTracker.debugMultiDrawState = function() {
      console.log('🔍 DEBUG: Multi-Draw State Debugging');
      console.log('🔍 DEBUG: HybridMultiDraw available:', typeof window.HybridMultiDraw !== 'undefined');

      if (typeof window.HybridMultiDraw !== 'undefined') {
        const state = window.HybridMultiDraw.getState();
        console.log('🔍 DEBUG: Current HybridMultiDraw state:', state);
        console.log('🔍 DEBUG: isActive:', state.isActive);
        console.log('🔍 DEBUG: drawRange:', state.drawRange);
        console.log('🔍 DEBUG: drawCount:', state.drawCount);
        console.log('🔍 DEBUG: startDraw:', state.startDraw);

        if (state.drawRange) {
          console.log('🔍 DEBUG: Draw range start:', state.drawRange.start);
          console.log('🔍 DEBUG: Draw range end:', state.drawRange.end);
        }
      } else {
        console.error('❌ HybridMultiDraw not available');
      }

      console.log('🔍 DEBUG: Current bets:', this.bets);
      console.log('🔍 DEBUG: Bets count:', this.bets ? this.bets.length : 0);
    };

    console.log('Betting slip patched successfully with multi-draw support!');
    console.log('💡 TIP: Use betTracker.debugMultiDrawState() to debug multi-draw issues');
  } else {
    console.error('Failed to patch betting slip: betTracker or printBettingSlip function not found.');
  }
}