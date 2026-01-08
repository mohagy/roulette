/**
 * Force True Fullscreen
 * Emergency script to ensure 100% fullscreen coverage
 */
(function() {
    console.log('🖥️ FORCE TRUE FULLSCREEN: Loading emergency fullscreen script...');
    
    /**
     * Force absolute fullscreen coverage
     */
    function forceTrueFullscreen() {
        console.log('🖥️ FORCE TRUE FULLSCREEN: Applying emergency fullscreen...');
        
        // Apply to HTML element
        const html = document.documentElement;
        html.style.margin = '0 !important';
        html.style.padding = '0 !important';
        html.style.width = '100vw !important';
        html.style.height = '100vh !important';
        html.style.overflow = 'hidden !important';
        html.style.position = 'fixed !important';
        html.style.top = '0 !important';
        html.style.left = '0 !important';
        html.style.right = '0 !important';
        html.style.bottom = '0 !important';
        html.style.backgroundColor = '#000 !important';
        
        // Apply to BODY element
        const body = document.body;
        body.style.margin = '0 !important';
        body.style.padding = '0 !important';
        body.style.width = '100vw !important';
        body.style.height = '100vh !important';
        body.style.overflow = 'hidden !important';
        body.style.position = 'fixed !important';
        body.style.top = '0 !important';
        body.style.left = '0 !important';
        body.style.right = '0 !important';
        body.style.bottom = '0 !important';
        body.style.backgroundColor = '#000 !important';
        
        // Apply to main wrapper
        const wrapper = document.querySelector('.website-wrapper') || document.querySelector('#website-wrapper');
        if (wrapper) {
            wrapper.style.margin = '0 !important';
            wrapper.style.padding = '0 !important';
            wrapper.style.width = '100vw !important';
            wrapper.style.height = '100vh !important';
            wrapper.style.position = 'absolute !important';
            wrapper.style.top = '0 !important';
            wrapper.style.left = '0 !important';
            wrapper.style.right = '0 !important';
            wrapper.style.bottom = '0 !important';
        }
        
        // Apply to roulette table
        const rouletteTable = document.querySelector('.roulette-table');
        if (rouletteTable) {
            rouletteTable.style.margin = '0 !important';
            rouletteTable.style.padding = '0 !important';
            rouletteTable.style.width = '100vw !important';
            rouletteTable.style.height = '100vh !important';
            rouletteTable.style.position = 'absolute !important';
            rouletteTable.style.top = '0 !important';
            rouletteTable.style.left = '0 !important';
            rouletteTable.style.right = '0 !important';
            rouletteTable.style.bottom = '0 !important';
        }
        
        // Add fullscreen classes
        html.classList.add('fullscreen-mode');
        body.classList.add('fullscreen-mode');
        
        // Inject emergency CSS
        const emergencyCSS = `
            <style id="emergency-fullscreen-css">
                html.fullscreen-mode, body.fullscreen-mode {
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100vw !important;
                    height: 100vh !important;
                    overflow: hidden !important;
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: 0 !important;
                    background-color: #000 !important;
                }
                
                .fullscreen-mode .website-wrapper,
                .fullscreen-mode #website-wrapper,
                .fullscreen-mode .roulette-table {
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100vw !important;
                    height: 100vh !important;
                    position: absolute !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: 0 !important;
                    border: none !important;
                    outline: none !important;
                    transform: none !important;
                }
                
                .fullscreen-mode * {
                    box-sizing: border-box !important;
                }
            </style>
        `;
        
        // Remove existing emergency CSS if present
        const existingCSS = document.getElementById('emergency-fullscreen-css');
        if (existingCSS) {
            existingCSS.remove();
        }
        
        // Add emergency CSS
        document.head.insertAdjacentHTML('beforeend', emergencyCSS);
        
        console.log('✅ FORCE TRUE FULLSCREEN: Emergency fullscreen applied!');
        
        return {
            html: html,
            body: body,
            wrapper: wrapper,
            rouletteTable: rouletteTable
        };
    }
    
    /**
     * Exit forced fullscreen
     */
    function exitForcedFullscreen() {
        console.log('🖥️ FORCE TRUE FULLSCREEN: Exiting emergency fullscreen...');
        
        // Remove styles from HTML
        const html = document.documentElement;
        html.style.margin = '';
        html.style.padding = '';
        html.style.width = '';
        html.style.height = '';
        html.style.overflow = '';
        html.style.position = '';
        html.style.top = '';
        html.style.left = '';
        html.style.right = '';
        html.style.bottom = '';
        html.style.backgroundColor = '';
        
        // Remove styles from BODY
        const body = document.body;
        body.style.margin = '';
        body.style.padding = '';
        body.style.width = '';
        body.style.height = '';
        body.style.overflow = '';
        body.style.position = '';
        body.style.top = '';
        body.style.left = '';
        body.style.right = '';
        body.style.bottom = '';
        body.style.backgroundColor = '';
        
        // Remove styles from wrapper
        const wrapper = document.querySelector('.website-wrapper') || document.querySelector('#website-wrapper');
        if (wrapper) {
            wrapper.style.margin = '';
            wrapper.style.padding = '';
            wrapper.style.width = '';
            wrapper.style.height = '';
            wrapper.style.position = '';
            wrapper.style.top = '';
            wrapper.style.left = '';
            wrapper.style.right = '';
            wrapper.style.bottom = '';
        }
        
        // Remove styles from roulette table
        const rouletteTable = document.querySelector('.roulette-table');
        if (rouletteTable) {
            rouletteTable.style.margin = '';
            rouletteTable.style.padding = '';
            rouletteTable.style.width = '';
            rouletteTable.style.height = '';
            rouletteTable.style.position = '';
            rouletteTable.style.top = '';
            rouletteTable.style.left = '';
            rouletteTable.style.right = '';
            rouletteTable.style.bottom = '';
        }
        
        // Remove fullscreen classes
        html.classList.remove('fullscreen-mode');
        body.classList.remove('fullscreen-mode');
        
        // Remove emergency CSS
        const emergencyCSS = document.getElementById('emergency-fullscreen-css');
        if (emergencyCSS) {
            emergencyCSS.remove();
        }
        
        console.log('✅ FORCE TRUE FULLSCREEN: Emergency fullscreen removed!');
    }
    
    /**
     * Check if currently in forced fullscreen
     */
    function isForcedFullscreen() {
        return document.documentElement.classList.contains('fullscreen-mode') &&
               document.getElementById('emergency-fullscreen-css') !== null;
    }
    
    /**
     * Toggle forced fullscreen
     */
    function toggleForcedFullscreen() {
        if (isForcedFullscreen()) {
            exitForcedFullscreen();
        } else {
            forceTrueFullscreen();
        }
    }
    
    // Make functions available globally
    window.forceTrueFullscreen = forceTrueFullscreen;
    window.exitForcedFullscreen = exitForcedFullscreen;
    window.toggleForcedFullscreen = toggleForcedFullscreen;
    window.isForcedFullscreen = isForcedFullscreen;
    
    console.log('🖥️ FORCE TRUE FULLSCREEN: Emergency script loaded!');
    console.log('💡 Use these emergency commands:');
    console.log('   forceTrueFullscreen() - Force absolute fullscreen');
    console.log('   exitForcedFullscreen() - Exit forced fullscreen');
    console.log('   toggleForcedFullscreen() - Toggle forced fullscreen');
    console.log('   isForcedFullscreen() - Check if in forced mode');
})();
