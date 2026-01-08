/**
 * Complete Button Diagnostic Tool
 * 
 * This script will help us understand exactly what's happening to the complete button
 * and why it stops working after page load.
 */

(function() {
    console.log('🔍 Complete Button Diagnostic - Starting...');
    
    let diagnosticInterval = null;
    let buttonObserver = null;
    let clickAttempts = 0;
    
    function log(message, ...args) {
        console.log(`[DIAGNOSTIC] ${message}`, ...args);
    }
    
    /**
     * Comprehensive button analysis
     */
    function analyzeCompleteButton() {
        log('=== COMPLETE BUTTON ANALYSIS ===');
        
        // Find all potential complete buttons
        const selectors = [
            '.button-complete',
            '.floating-complete-button',
            '[data-button="complete"]',
            '.button:contains("COMPLETE")',
            '.menu-container .button'
        ];
        
        let foundButtons = [];
        
        selectors.forEach(selector => {
            try {
                const elements = document.querySelectorAll(selector);
                if (elements.length > 0) {
                    log(`Found ${elements.length} elements with selector: ${selector}`);
                    elements.forEach((el, index) => {
                        foundButtons.push({
                            element: el,
                            selector: selector,
                            index: index
                        });
                    });
                }
            } catch (e) {
                // Skip invalid selectors
            }
        });
        
        // Also search by text content
        const allButtons = document.querySelectorAll('.button, button, [role="button"]');
        allButtons.forEach((button, index) => {
            if (button.textContent && button.textContent.toLowerCase().includes('complete')) {
                log(`Found button with COMPLETE text: ${button.textContent.trim()}`);
                foundButtons.push({
                    element: button,
                    selector: 'text-based',
                    index: index
                });
            }
        });
        
        log(`Total buttons found: ${foundButtons.length}`);
        
        // Analyze each button
        foundButtons.forEach((buttonInfo, index) => {
            analyzeButtonDetails(buttonInfo.element, index, buttonInfo.selector);
        });
        
        return foundButtons;
    }
    
    /**
     * Analyze specific button details
     */
    function analyzeButtonDetails(button, index, selector) {
        log(`--- Button ${index + 1} (${selector}) ---`);
        
        // Basic properties
        log(`Element:`, button);
        log(`Tag: ${button.tagName}`);
        log(`Classes: ${button.className}`);
        log(`ID: ${button.id || 'none'}`);
        log(`Text: "${button.textContent?.trim() || 'none'}"`);
        
        // Visibility and interaction
        const style = window.getComputedStyle(button);
        log(`Display: ${style.display}`);
        log(`Visibility: ${style.visibility}`);
        log(`Opacity: ${style.opacity}`);
        log(`Pointer Events: ${style.pointerEvents}`);
        log(`Z-Index: ${style.zIndex}`);
        log(`Position: ${style.position}`);
        
        // Disabled state
        log(`Disabled: ${button.disabled}`);
        log(`Aria-disabled: ${button.getAttribute('aria-disabled')}`);
        
        // Event listeners
        const events = getEventListeners ? getEventListeners(button) : 'DevTools required';
        log(`Event Listeners:`, events);
        
        // Parent container
        log(`Parent: ${button.parentElement?.tagName}.${button.parentElement?.className}`);
        
        // Overlapping elements check
        const rect = button.getBoundingClientRect();
        log(`Position: x=${rect.x}, y=${rect.y}, w=${rect.width}, h=${rect.height}`);
        
        const centerX = rect.x + rect.width / 2;
        const centerY = rect.y + rect.height / 2;
        const elementAtCenter = document.elementFromPoint(centerX, centerY);
        
        if (elementAtCenter !== button) {
            log(`⚠️ BLOCKING ELEMENT DETECTED at center:`, elementAtCenter);
            log(`Blocking element classes: ${elementAtCenter?.className}`);
            log(`Blocking element z-index: ${window.getComputedStyle(elementAtCenter).zIndex}`);
        } else {
            log(`✅ Button is clickable at center`);
        }
        
        // Test click simulation
        testButtonClick(button, index);
        
        log(`--- End Button ${index + 1} ---\n`);
    }
    
    /**
     * Test button click simulation
     */
    function testButtonClick(button, index) {
        log(`Testing click on button ${index + 1}...`);
        
        try {
            // Create and dispatch click event
            const clickEvent = new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
                view: window
            });
            
            const result = button.dispatchEvent(clickEvent);
            log(`Click event dispatched: ${result}`);
            
            // Also try direct onclick if it exists
            if (button.onclick) {
                log(`Direct onclick found, calling...`);
                button.onclick(clickEvent);
            } else {
                log(`No direct onclick handler`);
            }
            
        } catch (e) {
            log(`❌ Error testing click:`, e);
        }
    }
    
    /**
     * Monitor button changes
     */
    function startButtonMonitoring() {
        log('Starting button monitoring...');
        
        // Periodic analysis
        diagnosticInterval = setInterval(() => {
            const buttons = analyzeCompleteButton();
            if (buttons.length === 0) {
                log('⚠️ NO COMPLETE BUTTONS FOUND!');
            }
        }, 2000);
        
        // DOM mutation observer
        if (window.MutationObserver) {
            buttonObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'childList' || mutation.type === 'attributes') {
                        const target = mutation.target;
                        if (target.classList && (target.classList.contains('button-complete') || 
                            target.classList.contains('menu-container'))) {
                            log('🔄 Complete button or menu container changed:', mutation);
                        }
                    }
                });
            });
            
            buttonObserver.observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'disabled']
            });
            
            log('DOM mutation observer started');
        }
    }
    
    /**
     * Force create a working complete button
     */
    function forceCreateWorkingButton() {
        log('🔧 Force creating working complete button...');
        
        // Remove any existing floating buttons
        const existingFloating = document.querySelectorAll('.diagnostic-complete-button');
        existingFloating.forEach(btn => btn.remove());
        
        // Create a new button
        const button = document.createElement('div');
        button.className = 'diagnostic-complete-button';
        button.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                width: 100px;
                height: 100px;
                background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                cursor: pointer;
                z-index: 99999;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                user-select: none;
            ">
                COMPLETE<br>TEST
            </div>
        `;
        
        // Add click handler
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            clickAttempts++;
            log(`🎯 Diagnostic button clicked! Attempt #${clickAttempts}`);
            
            // Toggle global complete bet mode
            if (typeof window.isCompleteBetMode !== 'undefined') {
                window.isCompleteBetMode = !window.isCompleteBetMode;
                log(`Complete bet mode set to: ${window.isCompleteBetMode}`);
                
                // Update button color
                const innerDiv = button.querySelector('div');
                if (window.isCompleteBetMode) {
                    innerDiv.style.background = 'linear-gradient(45deg, #11998e, #38ef7d)';
                    innerDiv.innerHTML = 'COMPLETE<br>ACTIVE';
                } else {
                    innerDiv.style.background = 'linear-gradient(45deg, #ff6b6b, #4ecdc4)';
                    innerDiv.innerHTML = 'COMPLETE<br>TEST';
                }
            } else {
                log('❌ window.isCompleteBetMode not found');
            }
        });
        
        document.body.appendChild(button);
        log('✅ Diagnostic complete button created in top-right corner');
    }
    
    /**
     * Initialize diagnostic
     */
    function initialize() {
        log('🔍 Initializing complete button diagnostic...');
        
        // Initial analysis
        setTimeout(() => {
            analyzeCompleteButton();
            startButtonMonitoring();
            forceCreateWorkingButton();
        }, 1000);
        
        // Analysis after feature removal patch
        setTimeout(() => {
            log('🔍 POST-PATCH ANALYSIS (after 4000ms)');
            analyzeCompleteButton();
        }, 4000);
        
        log('🔍 Diagnostic initialized');
    }
    
    // Expose functions globally
    window.CompleteButtonDiagnostic = {
        analyzeCompleteButton,
        forceCreateWorkingButton,
        testAllButtons: () => {
            const buttons = analyzeCompleteButton();
            return buttons;
        },
        getClickAttempts: () => clickAttempts
    };
    
    // Initialize
    initialize();
    
    log('🔍 Complete Button Diagnostic loaded successfully!');
})();
