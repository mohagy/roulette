/**
 * SaveDetailedDraw.js
 *
 * This module monitors the TV display for winning numbers and saves them to the database
 * It uses MutationObserver to detect when a new winning number appears on the screen
 * and then saves it to the database using the save_winning_number.php API
 */

const SaveDetailedDraw = (function() {
    // Store private variables
    let initialized = false;
    let observer = null;
    let lastSavedNumber = null;
    let lastSavedDraw = null;
    let processingQueue = [];
    let isProcessing = false;
    let observerTimeout = null;
    let savedDrawIds = new Set(); // Track already saved draw IDs

    /**
     * Log messages to console with module prefix
     */
    function log(message, data) {
        const prefix = '[SaveDetailedDraw]';
        if (data) {
            console.log(prefix, message, data);
        } else {
            console.log(prefix, message);
        }
    }

    /**
     * Initialize the module
     */
    function init() {
        if (initialized) {
            log('Already initialized');
            return;
        }

        log('Initializing winning number detection');
        setupWinningNumberObserver();
        setupEventListeners();

        // Check for winning number after a delay to allow the page to fully load
        setTimeout(checkForWinningNumber, 2000);

        initialized = true;
    }

    /**
     * Set up MutationObserver to watch for winning number changes
     */
    function setupWinningNumberObserver() {
        // Create an observer instance with debouncing
        observer = new MutationObserver(function(mutations) {
            // Clear any existing timeout
            if (observerTimeout) {
                clearTimeout(observerTimeout);
            }

            // Debounce to prevent multiple rapid calls
            observerTimeout = setTimeout(() => {
                // Only check specific mutations that could contain winning number displays
                const relevantMutation = mutations.some(mutation => {
                    if (mutation.type === 'childList') {
                        // Check if this mutation is related to the results display
                        return mutation.target.classList &&
                               (mutation.target.classList.contains('results') ||
                                mutation.target.classList.contains('roll-number') ||
                                mutation.target.closest('.results') !== null);
                    }
                    return false;
                });

                if (relevantMutation) {
                    checkForWinningNumber();
                }
            }, 500); // 500ms debounce
        });

        // Start observing with a more specific configuration
        const config = {
            childList: true,
            subtree: true,
            characterData: false, // Don't need to observe text changes
            attributes: false     // Don't need to observe attribute changes
        };

        // Only observe specific sections where winning numbers appear
        const resultsContainer = document.querySelector('.alert-spin-result, .results');
        if (resultsContainer) {
            observer.observe(resultsContainer, config);
            log('Observing results container for changes');
        } else {
            // Fallback to observing the body
            observer.observe(document.body, config);
            log('Observing document body for changes (fallback)');
        }
    }

    /**
     * Set up event listeners for game events
     */
    function setupEventListeners() {
        // Listen for new game results if there's a custom event
        document.addEventListener('roulette_spin_complete', debounce(handleWinningNumberEvent, 300));
        document.addEventListener('game_result', debounce(handleWinningNumberEvent, 300));

        log('Event listeners set up with debouncing');
    }

    /**
     * Debounce function to prevent multiple rapid calls
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Handle winning number events
     */
    function handleWinningNumberEvent(event) {
        log('Received winning number event', event);

        if (event.detail && event.detail.number !== undefined) {
            const winningNumber = parseInt(event.detail.number);
            const drawNumber = event.detail.drawNumber || getCurrentDrawNumber();
            const winningColor = event.detail.color || determineWinningColor(winningNumber);

            // Create a unique draw ID to help track duplicates
            const drawId = `DRAW-${drawNumber}-${winningNumber}`;

            // Check if already saved
            if (savedDrawIds.has(drawId)) {
                log('Already saved this draw, skipping', { drawId });
                return;
            }

            savedDrawIds.add(drawId);
            saveWinningNumber(winningNumber, drawNumber, winningColor, drawId);
        } else {
            // If event doesn't contain the data, check DOM
            checkForWinningNumber();
        }
    }

    /**
     * Check the DOM for a winning number
     */
    function checkForWinningNumber() {
        // Look for the winning number in the DOM
        // This selector should target where the winning number is displayed
        const numberElement = document.querySelector('.roll-number, .results .roll-number, .alert-spin-result .results .roll-number');

        if (numberElement) {
            const winningNumberText = numberElement.textContent.trim();
            const winningNumber = parseInt(winningNumberText);

            if (!isNaN(winningNumber)) {
                // Get color from class or determine it
                let winningColor = 'unknown';

                // Check for color classes on the element
                if (numberElement.classList.contains('roll-red') ||
                    numberElement.closest('.results')?.classList.contains('roll-red')) {
                    winningColor = 'red';
                } else if (numberElement.classList.contains('roll-black') ||
                           numberElement.closest('.results')?.classList.contains('roll-black')) {
                    winningColor = 'black';
                } else if (numberElement.classList.contains('roll-green') ||
                           numberElement.closest('.results')?.classList.contains('roll-green')) {
                    winningColor = 'green';
                } else {
                    // Determine color based on number
                    winningColor = determineWinningColor(winningNumber);
                }

                const drawNumber = getCurrentDrawNumber();

                // Create a unique identifier for this draw
                const drawId = `DRAW-${drawNumber}-${winningNumber}`;

                // Check if this is a new winning number and hasn't been saved
                if ((lastSavedNumber !== winningNumber || lastSavedDraw !== drawNumber) && !savedDrawIds.has(drawId)) {
                    savedDrawIds.add(drawId);

                    // Save the winning number and also ensure the next draw is properly set up
                    saveWinningNumber(winningNumber, drawNumber, winningColor, drawId);

                    // If we have the DrawControlConnection available, save the automatic selection for the next draw
                    // This ensures we only create the next draw when a real spin has occurred
                    if (window.drawControlConnection && typeof window.drawControlConnection.saveAutomaticSelection === 'function') {
                        setTimeout(() => {
                            log('Triggering automatic selection for next draw');
                            window.drawControlConnection.saveAutomaticSelection();
                        }, 1000); // Delay to ensure the current draw is fully processed
                    }
                } else {
                    log('Skipping already saved draw', { number: winningNumber, draw: drawNumber, drawId });
                }
            }
        }
    }

    /**
     * Get current draw number from the DOM or local storage
     */
    function getCurrentDrawNumber() {
        // Try to get draw number from DOM
        const drawElement = document.querySelector('.draw-number, .current-draw, .next-draw .draw-number');

        if (drawElement) {
            const drawText = drawElement.textContent.trim();
            const match = drawText.match(/\d+/);
            if (match) {
                const drawNumber = parseInt(match[0]);

                // Debug logging for draw number source tracking
                if (window.drawNumberDebug) {
                    window.drawNumberDebug.logSource('DOM element', drawNumber, 'querySelector(.draw-number)');
                }

                return drawNumber;
            }
        }

        // Fallback to local storage or analytics
        const analyticsData = localStorage.getItem('rouletteAnalytics');
        if (analyticsData) {
            try {
                const data = JSON.parse(analyticsData);
                if (data.currentDrawNumber) {
                    // Debug logging for draw number source tracking
                    if (window.drawNumberDebug) {
                        window.drawNumberDebug.logSource('localStorage', data.currentDrawNumber, 'rouletteAnalytics.currentDrawNumber');
                    }

                    return data.currentDrawNumber;
                }
            } catch (e) {
                log('Error parsing analytics data', e);
            }
        }

        // Default to 1 if we can't find it (never use timestamp-based calculation)
        log('Warning: Could not find draw number from DOM or storage, defaulting to 1');

        // Debug logging for draw number source tracking
        if (window.drawNumberDebug) {
            window.drawNumberDebug.logSource('save-detailed-draw.js fallback', 1, 'getCurrentDrawNumber() fallback');
        }

        return 1;
    }

    /**
     * Determine the color of a winning number
     */
    function determineWinningColor(number) {
        if (number === 0) {
            return 'green';
        }

        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

        if (redNumbers.includes(number)) {
            return 'red';
        } else {
            return 'black';
        }
    }

    /**
     * Save the winning number to the database
     */
    function saveWinningNumber(winningNumber, drawNumber, winningColor, drawId) {
        // Use provided drawId or generate one
        drawId = drawId || `DRAW-${drawNumber}-${winningNumber}`;

        // Prevent duplicate saves with exact same data
        if (lastSavedNumber === winningNumber && lastSavedDraw === drawNumber) {
            log('Number already saved, skipping', { number: winningNumber, draw: drawNumber, drawId });
            return;
        }

        // Add to processing queue with deduplication
        // Check if this exact combination is already in the queue
        const isDuplicate = processingQueue.some(item =>
            item.winningNumber === winningNumber &&
            item.drawNumber === drawNumber);

        if (isDuplicate) {
            log('Duplicate item in queue, skipping', { number: winningNumber, draw: drawNumber });
            return;
        }

        // Add to processing queue with drawId for tracking
        processingQueue.push({
            winningNumber,
            drawNumber,
            winningColor,
            drawId,
            timestamp: new Date().toISOString()
        });

        // Start processing if not already
        if (!isProcessing) {
            processQueue();
        }
    }

    /**
     * Process the queue of winning numbers to save
     */
    function processQueue() {
        if (processingQueue.length === 0) {
            isProcessing = false;
            return;
        }

        isProcessing = true;
        const item = processingQueue.shift();

        // Double-check if this has been saved already
        if (lastSavedNumber === item.winningNumber && lastSavedDraw === item.drawNumber) {
            log('Skipping already processed item', item);
            // Continue with next item
            processQueue();
            return;
        }

        // Save the data via API
        const data = {
            winning_number: item.winningNumber,
            draw_number: item.drawNumber,
            winning_color: item.winningColor,
            timestamp: item.timestamp,
            draw_id: item.drawId
        };

        log('Saving winning number', data);

        fetch('../php/save_winning_number.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(responseData => {
            if (responseData.status === 'success') {
                log('Winning number saved successfully', responseData);

                // Update last saved values
                lastSavedNumber = item.winningNumber;
                lastSavedDraw = item.drawNumber;

                // Add to saved IDs set to prevent duplicates
                savedDrawIds.add(item.drawId);

                // Trigger custom event for success
                triggerSaveSuccessEvent(data);
            } else {
                log('Error saving winning number', responseData);
            }

            // Wait a moment before processing the next item to prevent rapid saves
            setTimeout(() => {
                processQueue();
            }, 200);
        })
        .catch(error => {
            log('Error calling save API', error);

            // Process next item after a delay
            setTimeout(() => {
                processQueue();
            }, 200);
        });
    }

    /**
     * Trigger a custom event when a winning number is saved
     */
    function triggerSaveSuccessEvent(data) {
        const event = new CustomEvent('winning_number_saved', {
            detail: {
                winning_number: data.winning_number,
                draw_number: data.draw_number,
                winning_color: data.winning_color,
                timestamp: data.timestamp,
                draw_id: data.draw_id
            }
        });

        document.dispatchEvent(event);
    }

    /**
     * Manual save function for testing
     */
    function manualSave(winningNumber, drawNumber, winningColor) {
        winningNumber = parseInt(winningNumber);
        drawNumber = parseInt(drawNumber);

        if (isNaN(winningNumber) || isNaN(drawNumber)) {
            log('Invalid number or draw');
            return;
        }

        if (!winningColor) {
            winningColor = determineWinningColor(winningNumber);
        }

        // Create a unique draw ID
        const drawId = `DRAW-${drawNumber}-${winningNumber}-manual-${Date.now()}`;

        saveWinningNumber(winningNumber, drawNumber, winningColor, drawId);
    }

    /**
     * Clear saved draw IDs - useful for testing or resets
     */
    function clearSavedDrawIds() {
        savedDrawIds.clear();
        log('Cleared saved draw IDs');
    }

    // Public API
    return {
        init: init,
        manualSave: manualSave,
        clearSavedDrawIds: clearSavedDrawIds
    };
})();

// Make available globally
window.SaveDetailedDraw = SaveDetailedDraw;