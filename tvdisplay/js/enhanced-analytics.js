/**
 * Enhanced Analytics Panel
 * Adds beautiful animations and visual effects to the Spin Analytics panel
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Enhanced Analytics Panel');
    initEnhancedAnalytics();
});

/**
 * Initialize all enhanced analytics features
 */
function initEnhancedAnalytics() {
    // Create particle effects
    createParticles();

    // Add animation class to panel when shown
    setupPanelAnimations();

    // Add 3D hover effects to sections
    setupSectionHoverEffects();

    // Add counter animations to distribution values
    setupCounterAnimations();

    // Ensure panel fits on screen without scrolling
    ensurePanelFitsOnScreen();
}

/**
 * Create particle effects in the analytics panel background
 */
function createParticles() {
    const particlesContainer = document.querySelector('.analytics-particles');
    if (!particlesContainer) return;

    // Clear any existing particles
    particlesContainer.innerHTML = '';

    // Create particles
    const particleCount = 30;
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';

        // Random size between 2px and 6px
        const size = Math.random() * 4 + 2;
        particle.style.width = `${size}px`;
        particle.style.height = `${size}px`;

        // Random position
        particle.style.left = `${Math.random() * 100}%`;
        particle.style.top = `${Math.random() * 100}%`;

        // Random opacity
        particle.style.opacity = Math.random() * 0.5 + 0.1;

        // Add animation with random duration and delay
        const duration = Math.random() * 20 + 10;
        const delay = Math.random() * 10;
        particle.style.animation = `float ${duration}s ${delay}s infinite ease-in-out`;

        // Add to container
        particlesContainer.appendChild(particle);
    }

    // Add float animation if not already defined
    if (!document.querySelector('style#particle-animations')) {
        const style = document.createElement('style');
        style.id = 'particle-animations';
        style.textContent = `
            @keyframes float {
                0%, 100% { transform: translate(0, 0); }
                25% { transform: translate(${Math.random() * 30 - 15}px, ${Math.random() * 30 - 15}px); }
                50% { transform: translate(${Math.random() * 30 - 15}px, ${Math.random() * 30 - 15}px); }
                75% { transform: translate(${Math.random() * 30 - 15}px, ${Math.random() * 30 - 15}px); }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Setup animations for the panel opening and closing
 */
function setupPanelAnimations() {
    const analyticsPanel = document.querySelector('.analytics-panel');
    const analyticsButton = document.getElementById('analytics-button');
    const closeButton = document.querySelector('.analytics-close');

    if (!analyticsPanel || !analyticsButton || !closeButton) return;

    // Override the default click handlers
    analyticsButton.addEventListener('click', function(e) {
        // Remove any existing event handlers
        e.stopPropagation();

        // Show the panel with animation
        analyticsPanel.style.display = 'block';
        analyticsPanel.classList.add('visible');

        // Update analytics data
        if (typeof updateAnalytics === 'function') {
            updateAnalytics();
        }

        // Refresh particle effects
        createParticles();
    });

    closeButton.addEventListener('click', function() {
        // Add fade-out animation
        analyticsPanel.classList.remove('visible');

        // Hide after animation completes
        setTimeout(function() {
            analyticsPanel.style.display = 'none';
        }, 500);
    });
}

/**
 * Add 3D hover effects to analytics sections
 */
function setupSectionHoverEffects() {
    const sections = document.querySelectorAll('.analytics-section');

    sections.forEach(section => {
        section.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left; // x position within the element
            const y = e.clientY - rect.top;  // y position within the element

            // Calculate rotation based on mouse position
            // The further from center, the more rotation
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateY = ((x - centerX) / centerX) * 5; // Max 5 degrees
            const rotateX = ((centerY - y) / centerY) * 5; // Max 5 degrees

            // Apply the transform
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
        });

        section.addEventListener('mouseleave', function() {
            // Reset transform on mouse leave
            this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
        });
    });
}

/**
 * Add counter animations to distribution values
 */
function setupCounterAnimations() {
    // Override the updateAnalytics function to add animations
    if (typeof window.originalUpdateAnalytics !== 'function') {
        window.originalUpdateAnalytics = window.updateAnalytics;

        window.updateAnalytics = function() {
            // Call the original function first
            const result = window.originalUpdateAnalytics.apply(this, arguments);

            // Add animations to the values
            animateDistributionValues();

            return result;
        };
    }
}

/**
 * Animate the distribution values with counting effect
 */
function animateDistributionValues() {
    const distValues = document.querySelectorAll('.dist-value');

    distValues.forEach(value => {
        // Get the target percentage
        const targetValue = value.textContent;

        // Start from 0%
        value.textContent = '0%';

        // Extract the number from the percentage
        const targetNumber = parseInt(targetValue);

        // Animate the counting
        if (!isNaN(targetNumber)) {
            let currentNumber = 0;
            const duration = 1000; // 1 second
            const interval = 20; // Update every 20ms
            const steps = duration / interval;
            const increment = targetNumber / steps;

            const counter = setInterval(() => {
                currentNumber += increment;

                if (currentNumber >= targetNumber) {
                    currentNumber = targetNumber;
                    clearInterval(counter);
                }

                value.textContent = `${Math.round(currentNumber)}%`;
            }, interval);
        }
    });
}

/**
 * Ensure the analytics panel fits on the screen without scrolling
 */
function ensurePanelFitsOnScreen() {
    const analyticsPanel = document.querySelector('.analytics-panel');
    if (!analyticsPanel) return;

    // Function to adjust panel size based on screen dimensions
    function adjustPanelSize() {
        // Get viewport dimensions
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        // Calculate optimal panel dimensions
        const optimalHeight = Math.min(viewportHeight * 0.9, 900); // 90% of viewport height or 900px max
        const optimalWidth = Math.min(viewportWidth * 0.95, 1400); // 95% of viewport width or 1400px max

        // Apply dimensions
        analyticsPanel.style.maxHeight = `${optimalHeight}px`;
        analyticsPanel.style.width = `${optimalWidth}px`;

        // Adjust content layout based on width
        const analyticsContent = document.querySelector('.analytics-content');
        if (analyticsContent) {
            if (viewportWidth < 1200) {
                analyticsContent.style.gridTemplateColumns = viewportWidth < 768 ? '1fr' : 'repeat(2, 1fr)';
            } else {
                analyticsContent.style.gridTemplateColumns = 'repeat(3, 1fr)';
            }
        }

        // Adjust section heights to be more compact if needed
        const sections = document.querySelectorAll('.analytics-section');
        if (viewportHeight < 800) {
            sections.forEach(section => {
                section.style.minHeight = '120px';
                section.style.padding = '10px';
            });
        }
    }

    // Adjust on initial load
    adjustPanelSize();

    // Adjust when panel is shown
    const analyticsButton = document.getElementById('analytics-button');
    if (analyticsButton) {
        analyticsButton.addEventListener('click', () => {
            setTimeout(adjustPanelSize, 10);
        });
    }

    // Adjust on window resize
    window.addEventListener('resize', adjustPanelSize);
}
