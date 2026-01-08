/**
 * Barcode Display Fix
 * Ensures barcode elements are properly styled and visible
 */

(function() {
    'use strict';
    
    /**
     * Apply barcode styles dynamically
     */
    function applyBarcodeStyles() {
        // Check if styles are already applied
        if (document.getElementById('barcode-fix-styles')) {
            return;
        }
        
        // Create style element
        const style = document.createElement('style');
        style.id = 'barcode-fix-styles';
        style.textContent = `
            /* Barcode Display Fix Styles */
            .barcode-container {
                text-align: center !important;
                margin: 20px 0 !important;
                padding: 10px !important;
                background: #f8f9fa !important;
                border-radius: 4px !important;
                display: block !important;
                visibility: visible !important;
            }

            .css-barcode {
                display: flex !important;
                justify-content: center !important;
                align-items: flex-end !important;
                height: 40px !important;
                width: 95% !important;
                margin: 10px auto !important;
                background: white !important;
                padding: 5px !important;
                border-radius: 2px !important;
                visibility: visible !important;
            }

            .bar {
                height: 100% !important;
                display: inline-block !important;
                background: #000 !important;
                margin-right: 1px !important;
                visibility: visible !important;
            }

            .bar.thin {
                width: 1px !important;
                height: 80% !important;
            }

            .bar.medium {
                width: 2px !important;
                height: 90% !important;
            }

            .bar.thick {
                width: 3px !important;
                height: 100% !important;
            }

            .barcode-number {
                font-size: 12px !important;
                font-weight: bold !important;
                margin-top: 8px !important;
                color: #333 !important;
                letter-spacing: 2px !important;
                font-family: 'Courier New', monospace !important;
                display: block !important;
                visibility: visible !important;
            }
            
            /* Print-specific styles */
            .print-slip-body .barcode-container {
                background: rgba(248, 249, 250, 0.8) !important;
                border: 1px solid #ddd !important;
            }
            
            .print-slip-body .css-barcode {
                height: 35px !important;
                width: 90% !important;
                border: 1px solid #ddd !important;
            }
            
            .print-slip-body .barcode-number {
                font-size: 11px !important;
                color: #000 !important;
                letter-spacing: 1.5px !important;
            }
        `;
        
        // Add to head
        document.head.appendChild(style);
        console.log('✅ Barcode display fix styles applied');
    }
    
    /**
     * Fix barcode elements
     */
    function fixBarcodeElements() {
        const barcodeContainers = document.querySelectorAll('.barcode-container');
        
        barcodeContainers.forEach(container => {
            // Ensure container is visible
            container.style.display = 'block';
            container.style.visibility = 'visible';
            
            // Find barcode bars
            const bars = container.querySelectorAll('.bar');
            bars.forEach(bar => {
                bar.style.display = 'inline-block';
                bar.style.visibility = 'visible';
                bar.style.background = '#000';
                
                // Ensure proper heights
                if (bar.classList.contains('thin')) {
                    bar.style.width = '1px';
                    bar.style.height = '80%';
                } else if (bar.classList.contains('medium')) {
                    bar.style.width = '2px';
                    bar.style.height = '90%';
                } else if (bar.classList.contains('thick')) {
                    bar.style.width = '3px';
                    bar.style.height = '100%';
                }
            });
            
            // Find barcode number
            const barcodeNumber = container.querySelector('.barcode-number');
            if (barcodeNumber) {
                barcodeNumber.style.display = 'block';
                barcodeNumber.style.visibility = 'visible';
                barcodeNumber.style.textAlign = 'center';
            }
        });
        
        if (barcodeContainers.length > 0) {
            console.log(`✅ Fixed ${barcodeContainers.length} barcode container(s)`);
        }
    }
    
    /**
     * Initialize barcode fix
     */
    function init() {
        // Apply styles immediately
        applyBarcodeStyles();
        
        // Fix existing elements
        fixBarcodeElements();
        
        // Watch for new barcode elements
        const observer = new MutationObserver(function(mutations) {
            let shouldFix = false;
            
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && node.classList.contains('barcode-container')) {
                            shouldFix = true;
                        } else if (node.querySelector && node.querySelector('.barcode-container')) {
                            shouldFix = true;
                        }
                    }
                });
            });
            
            if (shouldFix) {
                setTimeout(fixBarcodeElements, 100);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        console.log('✅ Barcode display fix initialized');
    }
    
    /**
     * Test barcode display
     */
    function testBarcodeDisplay() {
        const barcodeContainers = document.querySelectorAll('.barcode-container');
        
        console.log('🔍 Barcode Display Test Results:');
        console.log(`   Found ${barcodeContainers.length} barcode container(s)`);
        
        barcodeContainers.forEach((container, index) => {
            const bars = container.querySelectorAll('.bar');
            const number = container.querySelector('.barcode-number');
            
            console.log(`   Container ${index + 1}:`);
            console.log(`     - Bars: ${bars.length}`);
            console.log(`     - Number: ${number ? number.textContent : 'Not found'}`);
            console.log(`     - Visible: ${getComputedStyle(container).display !== 'none'}`);
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expose test function globally
    window.testBarcodeDisplay = testBarcodeDisplay;
    
    // Auto-test after 2 seconds
    setTimeout(testBarcodeDisplay, 2000);
    
})();
