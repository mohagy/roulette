/**
 * Draw Header Management
 * Handles the interactive draw number header that allows selecting future draws
 */

class DrawHeader {
    constructor() {
        this.container = document.getElementById('drawHeaderContainer');
        this.titleBar = document.getElementById('drawHeaderTitle');
        this.drawNumbersRow = document.getElementById('drawNumbersRow');
        this.minimizeBtn = document.getElementById('minimizeHeader');
        this.closeBtn = document.getElementById('closeHeader');
        this.overlay = document.getElementById('overlay');
        this.dialog = document.getElementById('drawSelectDialog');
        this.confirmBtn = document.getElementById('confirmDrawSelection');
        this.cancelBtn = document.getElementById('cancelDrawSelection');
        this.selectedDrawSpan = document.getElementById('selectedDrawNumber');
        
        this.currentDrawNumber = null;
        this.selectedDrawNumber = null;
        this.isDragging = false;
        this.dragOffset = { x: 0, y: 0 };
        this.position = { x: 0, y: 0 };
        this.isMinimized = false;
        this.isHidden = false;
        
        // Create dialog elements if they don't exist
        this.ensureDialogElementsExist();
        
        this.initHeader();
    }
    
    ensureDialogElementsExist() {
        // Check if overlay exists, if not create it
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.id = 'overlay';
            this.overlay.className = 'overlay';
            document.body.appendChild(this.overlay);
        }
        
        // Check if dialog exists, if not create it
        if (!this.dialog) {
            this.dialog = document.createElement('div');
            this.dialog.id = 'drawSelectDialog';
            this.dialog.className = 'draw-select-dialog';
            this.dialog.innerHTML = `
                <h3>Confirm Draw Selection</h3>
                <p>You are about to place bets for draw <span id="selectedDrawNumber"></span>.</p>
                <p>Are you sure you want to continue?</p>
                <div class="buttons">
                    <button class="confirm-btn" id="confirmDrawSelection">Confirm</button>
                    <button class="cancel-btn" id="cancelDrawSelection">Cancel</button>
                </div>
            `;
            document.body.appendChild(this.dialog);
            
            // Update references
            this.confirmBtn = document.getElementById('confirmDrawSelection');
            this.cancelBtn = document.getElementById('cancelDrawSelection');
            this.selectedDrawSpan = document.getElementById('selectedDrawNumber');
        }
    }
    
    initHeader() {
        // Make header draggable
        this.titleBar.addEventListener('mousedown', this.startDrag.bind(this));
        document.addEventListener('mousemove', this.doDrag.bind(this));
        document.addEventListener('mouseup', this.stopDrag.bind(this));
        
        // Touch events for mobile
        this.titleBar.addEventListener('touchstart', this.startDrag.bind(this));
        document.addEventListener('touchmove', this.doDrag.bind(this));
        document.addEventListener('touchend', this.stopDrag.bind(this));
        
        // Minimize and close controls
        this.minimizeBtn.addEventListener('click', this.toggleMinimize.bind(this));
        this.closeBtn.addEventListener('click', this.toggleVisibility.bind(this));
        
        // Setup draw number selection
        this.setupDrawNumberSelection();
        
        // Dialog buttons
        this.confirmBtn.addEventListener('click', this.confirmDrawSelection.bind(this));
        this.cancelBtn.addEventListener('click', this.hideDialog.bind(this));
        
        // Initial load of draw numbers
        this.loadDrawNumbers();
        
        // Periodic refresh
        setInterval(() => this.loadDrawNumbers(), 60000); // Refresh every minute
    }
    
    startDrag(e) {
        if (e.target === this.titleBar || e.target.parentNode === this.titleBar) {
            e.preventDefault();
            this.isDragging = true;
            
            // Get the current position of the header
            const rect = this.container.getBoundingClientRect();
            
            // For touch events
            if (e.type === 'touchstart') {
                this.dragOffset.x = e.touches[0].clientX - rect.left;
                this.dragOffset.y = e.touches[0].clientY - rect.top;
            } else {
                // For mouse events
                this.dragOffset.x = e.clientX - rect.left;
                this.dragOffset.y = e.clientY - rect.top;
            }
            
            this.container.style.transition = 'none';
            this.container.style.transform = 'none';
            this.container.style.left = rect.left + 'px';
            this.container.style.top = rect.top + 'px';
        }
    }
    
    doDrag(e) {
        if (!this.isDragging) return;
        e.preventDefault();
        
        let clientX, clientY;
        
        // For touch events
        if (e.type === 'touchmove') {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            // For mouse events
            clientX = e.clientX;
            clientY = e.clientY;
        }
        
        // Calculate new position
        const left = clientX - this.dragOffset.x;
        const top = clientY - this.dragOffset.y;
        
        // Update position
        this.container.style.left = left + 'px';
        this.container.style.top = top + 'px';
        
        // Store position for later use
        this.position.x = left;
        this.position.y = top;
    }
    
    stopDrag() {
        this.isDragging = false;
        this.container.style.transition = 'all 0.3s ease';
        
        // Save position to localStorage
        localStorage.setItem('drawHeaderPosition', JSON.stringify(this.position));
    }
    
    toggleMinimize() {
        this.isMinimized = !this.isMinimized;
        
        if (this.isMinimized) {
            this.container.classList.add('minimized');
            this.minimizeBtn.textContent = '+';
        } else {
            this.container.classList.remove('minimized');
            this.minimizeBtn.textContent = '−';
        }
        
        // Save state to localStorage
        localStorage.setItem('drawHeaderMinimized', this.isMinimized);
    }
    
    toggleVisibility() {
        this.isHidden = !this.isHidden;
        
        if (this.isHidden) {
            this.container.style.display = 'none';
        } else {
            this.container.style.display = 'flex';
        }
        
        // Save state to localStorage
        localStorage.setItem('drawHeaderHidden', this.isHidden);
    }
    
    async loadDrawNumbers() {
        // Use Firebase to get current draw numbers
        if (window.FirebaseDrawManager && window.FirebaseService) {
            try {
                const gameState = await FirebaseDrawManager.getCurrentDrawState();
                const drawInfo = await FirebaseService.GameState.getDrawInfo();
                
                if (gameState && drawInfo) {
                    const currentDraw = drawInfo.currentDraw || gameState.drawNumber || 1;
                    const nextDraw = drawInfo.nextDraw || gameState.nextDrawNumber || 2;
                    
                    // Generate draw numbers array (current, next, and 8 future draws)
                    const drawNumbers = [currentDraw];
                    for (let i = 1; i <= 9; i++) {
                        drawNumbers.push(currentDraw + i);
                    }
                    
                    this.currentDrawNumber = currentDraw;
                    this.updateDrawNumbersDisplay(drawNumbers);
                    return;
                }
            } catch (error) {
                console.error('Error loading draw numbers from Firebase:', error);
            }
        }
        
        // Fallback to server fetch if Firebase is not available
        fetch('draw_header.php?ajax=1')
            .then(response => response.json())
            .then(data => {
                this.currentDrawNumber = data.currentDrawNumber;
                this.updateDrawNumbers(data.drawNumbers);
            })
            .catch(error => {
                console.error('Error loading draw numbers:', error);
            });
    }
    
    updateDrawNumbers(drawNumbers) {
        // Clear existing draw numbers
        this.drawNumbersRow.innerHTML = '';
        
        // Add new draw numbers
        drawNumbers.forEach((drawNumber, index) => {
            const drawElement = document.createElement('div');
            drawElement.className = 'draw-number';
            drawElement.dataset.draw = drawNumber;
            drawElement.innerHTML = `
                #${drawNumber}
                <div class="label">
                    ${index === 0 ? 'Current' : (index === 1 ? 'Next' : 'Future')}
                </div>
            `;
            
            // Add appropriate classes
            if (index === 0) {
                drawElement.classList.add('current');
            }
            
            if (this.selectedDrawNumber === drawNumber) {
                drawElement.classList.add('selected');
            }
            
            this.drawNumbersRow.appendChild(drawElement);
        });
        
        // Reattach event listeners
        this.setupDrawNumberSelection();
    }
    
    setupDrawNumberSelection() {
        const drawNumbers = this.drawNumbersRow.querySelectorAll('.draw-number');
        
        drawNumbers.forEach(element => {
            // Remove existing click event to prevent duplicates
            element.removeEventListener('click', this._clickHandler);
            
            // Create a new click handler
            this._clickHandler = () => {
                const drawNumber = parseInt(element.dataset.draw);
                console.log('Draw number clicked:', drawNumber);
                this.showDrawSelectionDialog(drawNumber);
            };
            
            // Add the new click handler
            element.addEventListener('click', this._clickHandler);
            
            // Add visual feedback on hover
            element.addEventListener('mouseover', () => {
                if (!element.classList.contains('current') && !element.classList.contains('selected')) {
                    element.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
                }
            });
            
            element.addEventListener('mouseout', () => {
                if (!element.classList.contains('current') && !element.classList.contains('selected')) {
                    element.style.backgroundColor = '';
                }
            });
        });
    }
    
    showDrawSelectionDialog(drawNumber) {
        // Ensure dialog elements exist
        this.ensureDialogElementsExist();
        
        // Make sure drawNumber is a numeric value
        drawNumber = parseInt(drawNumber);
        
        // Update the display text
        if (this.selectedDrawSpan) {
            this.selectedDrawSpan.textContent = '#' + drawNumber;
        }
        
        // Store only the numeric value
        this.selectedDrawNumber = drawNumber;
        
        // Show dialog and overlay with fade-in effect
        this.overlay.style.opacity = '0';
        this.dialog.style.opacity = '0';
        
        this.overlay.style.display = 'block';
        this.dialog.style.display = 'block';
        
        // Trigger reflow
        this.overlay.offsetHeight;
        this.dialog.offsetHeight;
        
        // Fade in
        this.overlay.style.transition = 'opacity 0.3s ease';
        this.dialog.style.transition = 'opacity 0.3s ease';
        this.overlay.style.opacity = '1';
        this.dialog.style.opacity = '1';
    }
    
    hideDialog() {
        this.overlay.style.opacity = '0';
        this.dialog.style.opacity = '0';
        
        setTimeout(() => {
            this.overlay.style.display = 'none';
            this.dialog.style.display = 'none';
        }, 300);
    }
    
    confirmDrawSelection() {
        // Update UI to show the selected draw
        const drawElements = this.drawNumbersRow.querySelectorAll('.draw-number');
        
        drawElements.forEach(element => {
            const elementDrawNumber = parseInt(element.dataset.draw);
            element.classList.remove('selected');
            
            if (elementDrawNumber === this.selectedDrawNumber) {
                element.classList.add('selected');
            }
        });
        
        // Update global variable for other parts of the application to use
        window.selectedDrawNumber = parseInt(this.selectedDrawNumber);
        
        // Trigger custom event for other components to listen to
        const event = new CustomEvent('drawNumberSelected', {
            detail: { drawNumber: this.selectedDrawNumber }
        });
        document.dispatchEvent(event);
        
        // Hide dialog
        this.hideDialog();
        
        // Notify user
        this.showNotification(`Bets will be placed for Draw #${this.selectedDrawNumber}`);
    }
    
    showNotification(message) {
        // Create a notification element
        const notification = document.createElement('div');
        notification.className = 'draw-notification';
        notification.textContent = message;
        notification.style.position = 'fixed';
        notification.style.bottom = '20px';
        notification.style.left = '50%';
        notification.style.transform = 'translateX(-50%)';
        notification.style.backgroundColor = '#2ecc71';
        notification.style.color = 'white';
        notification.style.padding = '10px 20px';
        notification.style.borderRadius = '5px';
        notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        notification.style.zIndex = '10000';
        
        // Add to body
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.5s';
            
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 500);
        }, 3000);
    }
    
    // Method to be called from external code to show the header if hidden
    show() {
        this.isHidden = false;
        this.container.style.display = 'flex';
        localStorage.setItem('drawHeaderHidden', false);
    }
    
    // Load saved state from localStorage
    loadSavedState() {
        // Load position
        const savedPosition = localStorage.getItem('drawHeaderPosition');
        if (savedPosition) {
            try {
                const position = JSON.parse(savedPosition);
                this.container.style.transform = 'none';
                this.container.style.left = position.x + 'px';
                this.container.style.top = position.y + 'px';
                this.position = position;
            } catch (e) {
                console.error('Error parsing saved position:', e);
            }
        }
        
        // Load minimized state
        const minimized = localStorage.getItem('drawHeaderMinimized') === 'true';
        if (minimized) {
            this.isMinimized = true;
            this.container.classList.add('minimized');
            this.minimizeBtn.textContent = '+';
        }
        
        // Load hidden state
        const hidden = localStorage.getItem('drawHeaderHidden') === 'true';
        if (hidden) {
            this.isHidden = true;
            this.container.style.display = 'none';
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Global variable to access the header from other scripts
    window.drawHeader = new DrawHeader();
    
    // Load saved state
    window.drawHeader.loadSavedState();
    
    // Force re-initialization after a short delay to ensure all elements are properly loaded
    setTimeout(() => {
        if (window.drawHeader) {
            // Re-setup the draw number selection
            window.drawHeader.setupDrawNumberSelection();
        }
    }, 1000);
}); 