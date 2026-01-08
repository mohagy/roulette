/**
 * Master-Client Synchronization System
 * Enables browser-to-browser communication for synchronized roulette displays
 */
(function() {
    console.log('🎰 Master-Client Sync: Initializing...');
    
    // Configuration
    const SYNC_CONFIG = {
        channelName: 'roulette-sync-channel',
        storageKey: 'roulette-game-state',
        heartbeatInterval: 5000,
        stateUpdateInterval: 1000
    };
    
    // Sync state
    let syncState = {
        isMaster: false,
        isClient: false,
        channel: null,
        gameState: {
            currentNumber: null,
            previousNumbers: [],
            timeRemaining: 120000,
            isSpinning: false,
            gamePhase: 'betting',
            lastUpdate: Date.now(),
            masterId: null,
            sessionId: generateSessionId(),
            // Wheel animation state
            wheelAnimation: {
                isActive: false,
                ballLandingNumber: null,
                animationStartTime: null,
                animationDuration: 5000
            },
            // Spin result state
            spinResult: {
                winningNumber: null,
                color: null,
                isHighLow: null,
                isOddEven: null,
                resultDisplayTime: null
            },
            // Betting state
            bettingState: {
                betsAllowed: true,
                noMoreBetsTime: null
            },
            // NEW: Analytics state
            analyticsState: {
                panelsVisible: false,
                leftSidebarVisible: false,
                rightSidebarVisible: false,
                footerBarVisible: false,
                lastAnalyticsUpdate: null,
                analyticsData: {
                    allSpins: [],
                    numberFrequency: {},
                    hotNumbers: [],
                    coldNumbers: [],
                    colorDistribution: {},
                    oddEvenDistribution: {},
                    highLowDistribution: {},
                    dozensDistribution: {},
                    columnsDistribution: {},
                    last8Spins: [],
                    numberHistoryHTML: ''
                }
            }
        },
        connectedClients: new Set(),
        lastHeartbeat: Date.now()
    };
    
    /**
     * Generate unique session ID
     */
    function generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    /**
     * Initialize as master display
     */
    function initializeMaster() {
        console.log('👑 Initializing as MASTER display');
        syncState.isMaster = true;
        syncState.gameState.masterId = syncState.gameState.sessionId;

        // Create broadcast channel
        if (typeof BroadcastChannel !== 'undefined') {
            try {
                syncState.channel = new BroadcastChannel(SYNC_CONFIG.channelName);
                syncState.channel.onmessage = handleMasterMessage;
                syncState.channel.onerror = (error) => {
                    console.error('📡 Master broadcast channel error:', error);
                };
                console.log('📡 Master broadcast channel created successfully');
            } catch (error) {
                console.error('📡 Failed to create master broadcast channel:', error);
            }
        } else {
            console.warn('⚠️ BroadcastChannel not supported, using localStorage fallback');
        }

        // Start broadcasting game state
        startMasterBroadcast();

        // Capture initial game state
        setTimeout(() => {
            captureInitialGameState();
        }, 1000); // Delay to ensure DOM is ready

        // Hook into existing game events
        hookMasterGameEvents();

        console.log('👑 Master initialization complete');
    }
    
    /**
     * Initialize as client display
     */
    function initializeClient() {
        console.log('📺 Initializing as CLIENT display');
        syncState.isClient = true;

        // Ensure client mode is set
        window.CLIENT_MODE = true;
        document.body.classList.add('client-mode');

        // Create broadcast channel
        if (typeof BroadcastChannel !== 'undefined') {
            try {
                syncState.channel = new BroadcastChannel(SYNC_CONFIG.channelName);
                syncState.channel.onmessage = handleClientMessage;
                syncState.channel.onerror = (error) => {
                    console.error('📡 Client broadcast channel error:', error);
                };
                console.log('📡 Client broadcast channel created successfully');
            } catch (error) {
                console.error('📡 Failed to create client broadcast channel:', error);
                startStoragePolling();
            }
        } else {
            console.warn('⚠️ BroadcastChannel not supported, using localStorage fallback');
            startStoragePolling();
        }

        // Disable client interactions first
        disableClientInteractions();

        // Disable any existing game logic
        disableClientGameLogic();

        // Request initial state
        setTimeout(() => {
            requestGameState();
        }, 500);

        // Start heartbeat
        startClientHeartbeat();

        console.log('📺 Client initialization complete');
    }
    
    /**
     * Handle messages received by master
     */
    function handleMasterMessage(event) {
        const message = event.data;
        
        switch (message.type) {
            case 'client_connect':
                console.log('📺 Client connected:', message.clientId);
                syncState.connectedClients.add(message.clientId);

                // Capture fresh game state and send to new client
                captureInitialGameState();
                broadcastGameState();
                break;
                
            case 'client_heartbeat':
                syncState.connectedClients.add(message.clientId);
                break;
                
            case 'request_state':
                console.log('📨 State request from client:', message.clientId);
                broadcastGameState();
                break;

            case 'analytics_sync_request':
                console.log('📊 Master: Received analytics sync request from client:', message.clientId);
                handleAnalyticsSyncRequest(message);
                break;
        }
    }
    
    /**
     * Handle messages received by client
     */
    function handleClientMessage(event) {
        const message = event.data;
        
        switch (message.type) {
            case 'game_state':
                console.log('📨 Received game state from master');
                updateClientGameState(message.gameState);
                break;
                
            case 'timer_update':
                console.log('📺 Client: Received timer update:', message.timerText || message.timeRemaining);
                updateClientTimer(message.timeRemaining, message.timerText);
                // Update the sync state
                syncState.gameState.timeRemaining = message.timeRemaining;
                syncState.gameState.lastUpdate = Date.now();
                break;

            case 'spin_start':
                console.log('🎰 Client: Received spin start command');
                handleClientSpinStart(message);
                break;

            case 'wheel_animation':
                console.log('🎡 Client: Received wheel animation data');
                handleClientWheelAnimation(message);
                break;

            case 'spin_result':
                console.log('🎯 Client: Received spin result');
                handleClientSpinResult(message);
                break;

            case 'game_phase_change':
                console.log('🎮 Client: Game phase changed to:', message.phase);
                handleClientGamePhaseChange(message);
                break;

            case 'no_more_bets':
                console.log('🚫 Client: No more bets');
                handleClientNoMoreBets(message);
                break;

            case 'analytics_visibility':
                console.log('📊 Client: Analytics visibility changed');
                handleClientAnalyticsVisibility(message);
                break;

            case 'analytics_data':
                console.log('📊 Client: Analytics data update received');
                handleClientAnalyticsData(message);
                break;

            case 'analytics_full_sync':
                console.log('📊 Client: Full analytics sync received');
                handleClientAnalyticsFullSync(message);
                break;
                
            case 'spin_start':
                handleClientSpinStart();
                break;
                
            case 'spin_result':
                handleClientSpinResult(message.result, message.previousNumbers);
                break;
                
            case 'master_heartbeat':
                syncState.lastHeartbeat = Date.now();
                break;
        }
    }
    
    /**
     * Start master broadcast system
     */
    function startMasterBroadcast() {
        // Broadcast game state every second
        setInterval(() => {
            broadcastGameState();
            broadcastHeartbeat();
        }, SYNC_CONFIG.stateUpdateInterval);
        
        console.log('📡 Master broadcast started');
    }
    
    /**
     * Capture initial game state from DOM
     */
    function captureInitialGameState() {
        console.log('👑 Master: Capturing initial game state');

        // Capture timer
        const timerElement = document.getElementById('countdown-timer');
        if (timerElement && timerElement.textContent !== '--:--') {
            const timerText = timerElement.textContent;
            const [minutes, seconds] = timerText.split(':').map(Number);
            syncState.gameState.timeRemaining = (minutes * 60 + seconds) * 1000;
        }

        // Capture previous numbers
        updatePreviousNumbers();

        console.log('👑 Master: Initial game state captured:', syncState.gameState);
    }

    /**
     * Broadcast current game state
     */
    function broadcastGameState() {
        const message = {
            type: 'game_state',
            timestamp: Date.now(),
            gameState: syncState.gameState
        };

        if (syncState.channel) {
            syncState.channel.postMessage(message);
        }

        // Also store in localStorage as fallback
        localStorage.setItem(SYNC_CONFIG.storageKey, JSON.stringify(message));
    }
    
    /**
     * Broadcast master heartbeat
     */
    function broadcastHeartbeat() {
        const message = {
            type: 'master_heartbeat',
            timestamp: Date.now(),
            masterId: syncState.gameState.masterId,
            connectedClients: syncState.connectedClients.size
        };
        
        if (syncState.channel) {
            syncState.channel.postMessage(message);
        }
    }
    
    /**
     * Hook into existing game events on master
     */
    function hookMasterGameEvents() {
        console.log('👑 Master: Hooking into game events...');

        // Monitor timer changes with multiple methods for reliability
        const timerElement = document.getElementById('countdown-timer');
        if (timerElement) {
            console.log('👑 Master: Found timer element, setting up monitoring');

            let lastTimerText = '';

            // Method 1: MutationObserver for DOM changes
            const observer = new MutationObserver(() => {
                checkAndBroadcastTimerUpdate();
            });
            observer.observe(timerElement, { childList: true, characterData: true, subtree: true });

            // Method 2: Periodic polling as backup
            setInterval(() => {
                checkAndBroadcastTimerUpdate();
            }, 500); // Check every 500ms

            // Method 3: Hook into the updateCountdownDisplay function if it exists
            if (window.updateCountdownDisplay && typeof window.updateCountdownDisplay === 'function') {
                const originalUpdate = window.updateCountdownDisplay;
                window.updateCountdownDisplay = function(...args) {
                    const result = originalUpdate.apply(this, args);
                    // Small delay to ensure DOM is updated
                    setTimeout(checkAndBroadcastTimerUpdate, 10);
                    return result;
                };
                console.log('👑 Master: Hooked into updateCountdownDisplay function');
            }

            function checkAndBroadcastTimerUpdate() {
                const timerText = timerElement.textContent;
                if (timerText && timerText !== '--:--' && timerText.includes(':') && timerText !== lastTimerText) {
                    const parts = timerText.split(':');
                    if (parts.length === 2) {
                        const minutes = parseInt(parts[0]) || 0;
                        const seconds = parseInt(parts[1]) || 0;
                        const timeRemaining = (minutes * 60 + seconds) * 1000;

                        console.log(`👑 Master: Timer update detected: ${timerText} (${timeRemaining}ms)`);
                        syncState.gameState.timeRemaining = timeRemaining;
                        syncState.gameState.lastUpdate = Date.now();
                        lastTimerText = timerText;

                        // Broadcast timer update
                        if (syncState.channel) {
                            syncState.channel.postMessage({
                                type: 'timer_update',
                                timeRemaining: timeRemaining,
                                timerText: timerText,
                                timestamp: Date.now()
                            });
                            console.log(`👑 Master: Broadcasted timer update: ${timerText}`);
                        }
                    }
                }
            }

            // Initial timer capture
            setTimeout(checkAndBroadcastTimerUpdate, 1000);
            console.log('👑 Master: Timer monitoring set up successfully');
        } else {
            console.warn('👑 Master: Timer element not found');
        }

        // Set up spin button monitoring
        setupSpinButtonMonitoring();

        // Set up wheel animation monitoring
        setupWheelAnimationMonitoring();

        // Set up result monitoring
        setupResultMonitoring();

        // Set up analytics monitoring
        setupAnalyticsMonitoring();

        // Monitor for spin events
        monitorSpinEvents();

        // Monitor for result changes
        monitorResultChanges();

        console.log('👑 Master: Game event hooks complete');
    }
    
    /**
     * Monitor spin events
     */
    function monitorSpinEvents() {
        // Override existing spin function if it exists
        if (window.startSpin && !window.originalStartSpin) {
            window.originalStartSpin = window.startSpin;
            window.startSpin = function(...args) {
                console.log('🎰 Master: Spin started');
                syncState.gameState.isSpinning = true;
                syncState.gameState.gamePhase = 'spinning';
                
                // Broadcast spin start
                if (syncState.channel) {
                    syncState.channel.postMessage({
                        type: 'spin_start',
                        timestamp: Date.now()
                    });
                }
                
                return window.originalStartSpin.apply(this, args);
            };
        }
    }
    
    /**
     * Monitor result changes
     */
    function monitorResultChanges() {
        // Monitor previous numbers display
        const rollElements = document.querySelectorAll('.roll');
        if (rollElements.length > 0) {
            rollElements.forEach((element, index) => {
                const observer = new MutationObserver(() => {
                    if (index === 0) { // Latest result
                        const number = parseInt(element.textContent);
                        if (!isNaN(number) && number !== syncState.gameState.currentNumber) {
                            console.log('🎯 Master: New result detected:', number);
                            
                            syncState.gameState.currentNumber = number;
                            syncState.gameState.isSpinning = false;
                            syncState.gameState.gamePhase = 'result';
                            
                            // Update previous numbers
                            updatePreviousNumbers();
                            
                            // Broadcast result
                            if (syncState.channel) {
                                syncState.channel.postMessage({
                                    type: 'spin_result',
                                    result: {
                                        number: number,
                                        color: getNumberColor(number)
                                    },
                                    previousNumbers: syncState.gameState.previousNumbers,
                                    timestamp: Date.now()
                                });
                            }
                        }
                    }
                });
                
                observer.observe(element, { childList: true, characterData: true, subtree: true });
            });
        }
    }
    
    /**
     * Update previous numbers from DOM
     */
    function updatePreviousNumbers() {
        const rollElements = document.querySelectorAll('.roll');
        syncState.gameState.previousNumbers = [];

        rollElements.forEach((element, index) => {
            const number = parseInt(element.textContent);
            if (!isNaN(number)) {
                syncState.gameState.previousNumbers.push({
                    number: number,
                    color: getNumberColor(number),
                    position: index // Track position for proper ordering
                });
            }
        });

        console.log('📊 Master: Updated previous numbers:', syncState.gameState.previousNumbers);
    }
    
    /**
     * Get number color
     */
    function getNumberColor(number) {
        if (number === 0) return 'green';
        const redNumbers = [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
        return redNumbers.includes(number) ? 'red' : 'black';
    }
    
    /**
     * Request game state from master
     */
    function requestGameState() {
        if (syncState.channel) {
            syncState.channel.postMessage({
                type: 'request_state',
                clientId: syncState.gameState.sessionId,
                timestamp: Date.now()
            });
        }
    }
    
    /**
     * Start client heartbeat
     */
    function startClientHeartbeat() {
        // Send connect message
        if (syncState.channel) {
            syncState.channel.postMessage({
                type: 'client_connect',
                clientId: syncState.gameState.sessionId,
                timestamp: Date.now()
            });
        }
        
        // Send periodic heartbeat
        setInterval(() => {
            if (syncState.channel) {
                syncState.channel.postMessage({
                    type: 'client_heartbeat',
                    clientId: syncState.gameState.sessionId,
                    timestamp: Date.now()
                });
            }
        }, SYNC_CONFIG.heartbeatInterval);
    }
    
    /**
     * Update client game state
     */
    function updateClientGameState(gameState) {
        console.log('📺 Client: Updating game state:', gameState);
        syncState.gameState = { ...gameState };

        // Update timer
        if (gameState.timeRemaining !== undefined) {
            // Calculate timer text from timeRemaining
            const totalSeconds = Math.floor(gameState.timeRemaining / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            const timerText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            updateClientTimer(gameState.timeRemaining, timerText);
        }

        // Update current number
        if (gameState.currentNumber !== null && gameState.currentNumber !== undefined) {
            updateClientCurrentNumber(gameState.currentNumber);
        }

        // Update previous numbers
        if (gameState.previousNumbers && Array.isArray(gameState.previousNumbers)) {
            updateClientPreviousNumbers(gameState.previousNumbers);
        }

        // Update game phase
        if (gameState.gamePhase) {
            updateClientGamePhase(gameState.gamePhase);
        }

        console.log('📺 Client: Game state update complete');
    }
    
    /**
     * Set up spin button monitoring on master
     */
    function setupSpinButtonMonitoring() {
        console.log('👑 Master: Setting up spin button monitoring');

        // Monitor for spin button clicks
        const spinButton = document.querySelector('.button-spin');
        if (spinButton) {
            // Override the click handler to broadcast spin start
            const originalClickHandler = spinButton.onclick;

            spinButton.addEventListener('click', function(event) {
                console.log('👑 Master: Spin button clicked - broadcasting spin start');

                // Update game state
                syncState.gameState.isSpinning = true;
                syncState.gameState.gamePhase = 'spinning';
                syncState.gameState.bettingState.betsAllowed = false;
                syncState.gameState.bettingState.noMoreBetsTime = Date.now();

                // Broadcast spin start to clients
                if (syncState.channel) {
                    syncState.channel.postMessage({
                        type: 'spin_start',
                        timestamp: Date.now(),
                        gamePhase: 'spinning',
                        bettingState: syncState.gameState.bettingState
                    });
                    console.log('👑 Master: Broadcasted spin start');
                }

                // Broadcast no more bets
                setTimeout(() => {
                    if (syncState.channel) {
                        syncState.channel.postMessage({
                            type: 'no_more_bets',
                            timestamp: Date.now()
                        });
                        console.log('👑 Master: Broadcasted no more bets');
                    }
                }, 100);
            });

            console.log('👑 Master: Spin button monitoring set up successfully');
        } else {
            console.warn('👑 Master: Spin button not found');
        }
    }

    /**
     * Set up wheel animation monitoring on master
     */
    function setupWheelAnimationMonitoring() {
        console.log('👑 Master: Setting up wheel animation monitoring');

        // Monitor for wheel animation start
        const wheelContainer = document.querySelector('.roulette-wheel-container');
        if (wheelContainer) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const classList = wheelContainer.classList;

                        if (classList.contains('roulette-wheel-visible') && !syncState.gameState.wheelAnimation.isActive) {
                            console.log('👑 Master: Wheel animation started');

                            // Capture the winning number and animation data
                            const winningNumber = window.rouletteNumber;
                            const ballLandingNumber = calculateBallLandingNumber(winningNumber);

                            // Update sync state
                            syncState.gameState.wheelAnimation.isActive = true;
                            syncState.gameState.wheelAnimation.ballLandingNumber = ballLandingNumber;
                            syncState.gameState.wheelAnimation.animationStartTime = Date.now();

                            // Broadcast wheel animation data
                            if (syncState.channel) {
                                syncState.channel.postMessage({
                                    type: 'wheel_animation',
                                    winningNumber: winningNumber,
                                    ballLandingNumber: ballLandingNumber,
                                    animationStartTime: Date.now(),
                                    animationDuration: 5000,
                                    timestamp: Date.now()
                                });
                                console.log(`👑 Master: Broadcasted wheel animation for number ${winningNumber}`);
                            }
                        }

                        if (!classList.contains('roulette-wheel-visible') && syncState.gameState.wheelAnimation.isActive) {
                            console.log('👑 Master: Wheel animation ended');
                            syncState.gameState.wheelAnimation.isActive = false;
                            syncState.gameState.isSpinning = false;
                            syncState.gameState.gamePhase = 'results';
                        }
                    }
                });
            });

            observer.observe(wheelContainer, { attributes: true });
            console.log('👑 Master: Wheel animation monitoring set up successfully');
        } else {
            console.warn('👑 Master: Wheel container not found');
        }
    }

    /**
     * Set up result monitoring on master
     */
    function setupResultMonitoring() {
        console.log('👑 Master: Setting up result monitoring');

        // Monitor for result display
        const resultAlert = document.querySelector('.alert-spin-result');
        if (resultAlert) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const classList = resultAlert.classList;

                        if (classList.contains('alert-message-visible')) {
                            console.log('👑 Master: Result displayed');

                            // Capture result data
                            const winningNumber = window.rouletteNumber;
                            const color = getNumberColor(winningNumber);
                            const isHighLow = winningNumber >= 19 ? 'HIGH' : 'LOW';
                            const isOddEven = winningNumber % 2 === 1 ? 'ODD' : 'EVEN';
                            const previousNumbers = window.rolledNumbersArray || [];

                            // Update sync state
                            syncState.gameState.spinResult = {
                                winningNumber: winningNumber,
                                color: color,
                                isHighLow: isHighLow,
                                isOddEven: isOddEven,
                                resultDisplayTime: Date.now()
                            };
                            syncState.gameState.currentNumber = winningNumber;
                            syncState.gameState.previousNumbers = previousNumbers.slice(0, 5);

                            // Broadcast result
                            if (syncState.channel) {
                                syncState.channel.postMessage({
                                    type: 'spin_result',
                                    winningNumber: winningNumber,
                                    color: color,
                                    isHighLow: isHighLow,
                                    isOddEven: isOddEven,
                                    previousNumbers: previousNumbers.slice(0, 5),
                                    timestamp: Date.now()
                                });
                                console.log(`👑 Master: Broadcasted result ${winningNumber} ${color}`);
                            }
                        }

                        if (!classList.contains('alert-message-visible') && syncState.gameState.gamePhase === 'results') {
                            console.log('👑 Master: Result hidden - returning to betting phase');
                            syncState.gameState.gamePhase = 'betting';
                            syncState.gameState.bettingState.betsAllowed = true;

                            // Broadcast phase change
                            if (syncState.channel) {
                                syncState.channel.postMessage({
                                    type: 'game_phase_change',
                                    phase: 'betting',
                                    bettingState: syncState.gameState.bettingState,
                                    timestamp: Date.now()
                                });
                                console.log('👑 Master: Broadcasted return to betting phase');
                            }
                        }
                    }
                });
            });

            observer.observe(resultAlert, { attributes: true });
            console.log('👑 Master: Result monitoring set up successfully');
        } else {
            console.warn('👑 Master: Result alert not found');
        }
    }

    /**
     * Calculate ball landing number from winning number
     */
    function calculateBallLandingNumber(winningNumber) {
        const rouletteNumbersArray = window.rouletteNumbersArray || [0,32,15,19,4,21,2,25,17,34,6,27,13,36,11,30,8,23,10,5,24,16,33,1,20,14,31,9,22,18,29,7,28,12,35,3,26];
        const rouletteNumbersAmount = window.rouletteNumbersAmount || 37;

        for (let i = 0; i < rouletteNumbersAmount; i++) {
            if (winningNumber === rouletteNumbersArray[i]) {
                return i;
            }
        }
        return 0;
    }

    /**
     * Get color of a number
     */
    function getNumberColor(number) {
        const rouletteNumbersRed = window.rouletteNumbersRed || [1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36];
        const rouletteNumbersBlack = window.rouletteNumbersBlack || [2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35];

        if (number === 0) return 'green';
        if (rouletteNumbersRed.includes(number)) return 'red';
        if (rouletteNumbersBlack.includes(number)) return 'black';
        return 'green';
    }

    /**
     * Set up analytics monitoring on master
     */
    function setupAnalyticsMonitoring() {
        console.log('👑 Master: Setting up analytics monitoring');

        // Monitor analytics panel visibility changes
        setupAnalyticsPanelMonitoring();

        // Monitor analytics data changes
        setupAnalyticsDataMonitoring();

        // Monitor analytics button clicks
        setupAnalyticsButtonMonitoring();

        // Monitor keyboard shortcuts
        setupAnalyticsKeyboardMonitoring();

        console.log('👑 Master: Analytics monitoring set up successfully');
    }

    /**
     * Monitor analytics panel visibility
     */
    function setupAnalyticsPanelMonitoring() {
        const leftSidebar = document.querySelector('.analytics-left-sidebar');
        const rightSidebar = document.querySelector('.analytics-right-sidebar');
        // Note: No footer bar exists in the HTML, only left and right sidebars

        if (leftSidebar || rightSidebar) {
            console.log('👑 Master: Found analytics panels', {
                left: !!leftSidebar,
                right: !!rightSidebar
            });

            // Use MutationObserver to detect visibility changes
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' &&
                        (mutation.attributeName === 'class' || mutation.attributeName === 'style')) {

                        const leftVisible = leftSidebar && (leftSidebar.classList.contains('visible') ||
                                          (leftSidebar.style.display !== 'none' && leftSidebar.style.display !== ''));
                        const rightVisible = rightSidebar && (rightSidebar.classList.contains('visible') ||
                                           (rightSidebar.style.display !== 'none' && rightSidebar.style.display !== ''));
                        const anyVisible = leftVisible || rightVisible;

                        // Check if visibility state changed
                        if (syncState.gameState.analyticsState.panelsVisible !== anyVisible ||
                            syncState.gameState.analyticsState.leftSidebarVisible !== leftVisible ||
                            syncState.gameState.analyticsState.rightSidebarVisible !== rightVisible) {

                            console.log('👑 Master: Analytics visibility changed', {
                                left: leftVisible,
                                right: rightVisible,
                                any: anyVisible
                            });

                            // Update sync state
                            syncState.gameState.analyticsState.panelsVisible = anyVisible;
                            syncState.gameState.analyticsState.leftSidebarVisible = leftVisible;
                            syncState.gameState.analyticsState.rightSidebarVisible = rightVisible;
                            syncState.gameState.analyticsState.footerBarVisible = false; // No footer bar exists

                            // Broadcast visibility change
                            if (syncState.channel) {
                                syncState.channel.postMessage({
                                    type: 'analytics_visibility',
                                    panelsVisible: anyVisible,
                                    leftSidebarVisible: leftVisible,
                                    rightSidebarVisible: rightVisible,
                                    footerBarVisible: false, // No footer bar exists
                                    timestamp: Date.now()
                                });
                                console.log('👑 Master: Broadcasted analytics visibility change');
                            }
                        }
                    }
                });
            });

            // Observe all analytics panels
            if (leftSidebar) {
                observer.observe(leftSidebar, { attributes: true });
                console.log('👑 Master: Observing left sidebar');
            }
            if (rightSidebar) {
                observer.observe(rightSidebar, { attributes: true });
                console.log('👑 Master: Observing right sidebar');
            }

            console.log('👑 Master: Analytics panel monitoring set up');
        } else {
            console.warn('👑 Master: No analytics panels found');
        }
    }

    /**
     * Monitor analytics data changes
     */
    function setupAnalyticsDataMonitoring() {
        // Monitor global analytics variables
        let lastAllSpinsLength = 0;
        let lastNumberFrequency = {};
        let lastNumberHistoryHTML = '';

        // Also monitor DOM changes in number history
        const numberHistoryContainer = document.getElementById('number-history');
        if (numberHistoryContainer) {
            const historyObserver = new MutationObserver(() => {
                const currentHistoryHTML = numberHistoryContainer.innerHTML;
                if (currentHistoryHTML !== lastNumberHistoryHTML) {
                    console.log('👑 Master: Number history DOM changed');
                    lastNumberHistoryHTML = currentHistoryHTML;

                    // Trigger analytics data broadcast
                    setTimeout(() => {
                        broadcastAnalyticsFullSync();
                    }, 100);
                }
            });

            historyObserver.observe(numberHistoryContainer, {
                childList: true,
                subtree: true,
                characterData: true
            });
            console.log('👑 Master: Number history DOM monitoring set up');
        }

        setInterval(() => {
            // Check if analytics data has changed
            const currentAllSpins = window.allSpins || [];
            const currentNumberFrequency = window.numberFrequency || {};

            if (currentAllSpins.length !== lastAllSpinsLength ||
                JSON.stringify(currentNumberFrequency) !== JSON.stringify(lastNumberFrequency)) {

                console.log('👑 Master: Analytics data changed');

                // Capture current analytics data
                const analyticsData = captureAnalyticsData();

                // Update sync state
                syncState.gameState.analyticsState.analyticsData = analyticsData;
                syncState.gameState.analyticsState.lastAnalyticsUpdate = Date.now();

                // Broadcast analytics data
                if (syncState.channel) {
                    syncState.channel.postMessage({
                        type: 'analytics_data',
                        analyticsData: analyticsData,
                        timestamp: Date.now()
                    });
                    console.log('👑 Master: Broadcasted analytics data update');
                }

                // Update tracking variables
                lastAllSpinsLength = currentAllSpins.length;
                lastNumberFrequency = { ...currentNumberFrequency };
            }
        }, 1000); // Check every second

        console.log('👑 Master: Analytics data monitoring set up');
    }

    /**
     * Monitor analytics button clicks
     */
    function setupAnalyticsButtonMonitoring() {
        const analyticsButton = document.getElementById('analytics-button');
        if (analyticsButton) {
            // Override the existing click handler
            const originalHandler = analyticsButton.onclick;

            analyticsButton.addEventListener('click', function() {
                console.log('👑 Master: Analytics button clicked');

                // Trigger analytics update first
                if (typeof window.updateAnalytics === 'function') {
                    window.updateAnalytics();
                }

                // Small delay to allow panels to show/hide and data to update
                setTimeout(() => {
                    broadcastAnalyticsFullSync();
                }, 300);

                // Another broadcast after a longer delay to ensure everything is synced
                setTimeout(() => {
                    broadcastAnalyticsFullSync();
                }, 1000);
            });
            console.log('👑 Master: Analytics button monitoring set up');
        } else {
            console.warn('👑 Master: Analytics button not found');
        }
    }

    /**
     * Monitor analytics keyboard shortcuts
     */
    function setupAnalyticsKeyboardMonitoring() {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'a' || e.key === 'A') {
                console.log('👑 Master: Analytics keyboard shortcut pressed');

                // Trigger analytics update first
                if (typeof window.updateAnalytics === 'function') {
                    window.updateAnalytics();
                }

                // Small delay to allow panels to show/hide and data to update
                setTimeout(() => {
                    broadcastAnalyticsFullSync();
                }, 300);

                // Another broadcast after a longer delay
                setTimeout(() => {
                    broadcastAnalyticsFullSync();
                }, 1000);
            }
        });
        console.log('👑 Master: Analytics keyboard monitoring set up');
    }

    /**
     * Capture current analytics data
     */
    function captureAnalyticsData() {
        const analyticsData = {
            allSpins: window.allSpins || [],
            numberFrequency: window.numberFrequency || {},
            currentDrawNumber: window.currentDrawNumber || 1,
            rolledNumbersArray: window.rolledNumbersArray || [],
            rolledNumbersColorArray: window.rolledNumbersColorArray || []
        };

        // Capture DOM-based analytics if available
        try {
            // Hot numbers
            const hotNumbersContainer = document.getElementById('hot-numbers');
            if (hotNumbersContainer) {
                analyticsData.hotNumbersHTML = hotNumbersContainer.innerHTML;
            }

            // Cold numbers
            const coldNumbersContainer = document.getElementById('cold-numbers');
            if (coldNumbersContainer) {
                analyticsData.coldNumbersHTML = coldNumbersContainer.innerHTML;
            }

            // Last 8 spins / Number history
            const numberHistoryContainer = document.getElementById('number-history');
            if (numberHistoryContainer) {
                analyticsData.numberHistoryHTML = numberHistoryContainer.innerHTML;
                console.log('👑 Master: Captured number history HTML:', numberHistoryContainer.innerHTML.length, 'characters');
            } else {
                console.warn('👑 Master: Number history container not found');
            }

            // Distribution data
            analyticsData.distributions = {
                red: {
                    percentage: document.getElementById('red-percentage')?.textContent || '0%',
                    count: document.getElementById('red-count')?.textContent || '(0)'
                },
                black: {
                    percentage: document.getElementById('black-percentage')?.textContent || '0%',
                    count: document.getElementById('black-count')?.textContent || '(0)'
                },
                green: {
                    percentage: document.getElementById('green-percentage')?.textContent || '0%',
                    count: document.getElementById('green-count')?.textContent || '(0)'
                },
                odd: {
                    percentage: document.getElementById('odd-percentage')?.textContent || '0%',
                    count: document.getElementById('odd-count')?.textContent || '(0)'
                },
                even: {
                    percentage: document.getElementById('even-percentage')?.textContent || '0%',
                    count: document.getElementById('even-count')?.textContent || '(0)'
                },
                high: {
                    percentage: document.getElementById('high-percentage')?.textContent || '0%',
                    count: document.getElementById('high-count')?.textContent || '(0)'
                },
                low: {
                    percentage: document.getElementById('low-percentage')?.textContent || '0%',
                    count: document.getElementById('low-count')?.textContent || '(0)'
                }
            };

        } catch (error) {
            console.warn('👑 Master: Error capturing DOM analytics data:', error);
        }

        return analyticsData;
    }

    /**
     * Broadcast full analytics sync
     */
    function broadcastAnalyticsFullSync() {
        if (!syncState.channel) return;

        const analyticsData = captureAnalyticsData();
        const leftSidebar = document.querySelector('.analytics-left-sidebar');
        const rightSidebar = document.querySelector('.analytics-right-sidebar');

        const leftVisible = leftSidebar && (leftSidebar.classList.contains('visible') ||
                           (leftSidebar.style.display !== 'none' && leftSidebar.style.display !== ''));
        const rightVisible = rightSidebar && (rightSidebar.classList.contains('visible') ||
                            (rightSidebar.style.display !== 'none' && rightSidebar.style.display !== ''));
        const anyVisible = leftVisible || rightVisible;

        console.log('👑 Master: Broadcasting full analytics sync', {
            left: leftVisible,
            right: rightVisible,
            any: anyVisible,
            dataKeys: Object.keys(analyticsData)
        });

        syncState.channel.postMessage({
            type: 'analytics_full_sync',
            analyticsData: analyticsData,
            panelsVisible: anyVisible,
            leftSidebarVisible: leftVisible,
            rightSidebarVisible: rightVisible,
            footerBarVisible: false, // No footer bar exists
            timestamp: Date.now()
        });

        console.log('👑 Master: Broadcasted full analytics sync');
    }

    /**
     * Handle analytics sync request from client
     */
    function handleAnalyticsSyncRequest(message) {
        console.log('👑 Master: Handling analytics sync request from client:', message.clientId);

        // Immediately broadcast current analytics state
        broadcastAnalyticsFullSync();

        // Also trigger analytics update to ensure fresh data
        if (typeof window.updateAnalytics === 'function') {
            try {
                window.updateAnalytics();
                console.log('👑 Master: Triggered analytics update');

                // Broadcast again after update
                setTimeout(() => {
                    broadcastAnalyticsFullSync();
                }, 500);
            } catch (error) {
                console.warn('👑 Master: Error updating analytics:', error);
            }
        }
    }

    /**
     * Update client timer
     */
    function updateClientTimer(timeRemaining, timerText) {
        const timerElement = document.getElementById('countdown-timer');
        if (timerElement) {
            if (timerText) {
                // Use the exact timer text from master
                timerElement.textContent = timerText;
                console.log(`📺 Client: Timer updated to: ${timerText}`);

                // Also update the global countdownTime variable if it exists
                if (typeof window.countdownTime !== 'undefined') {
                    const parts = timerText.split(':');
                    if (parts.length === 2) {
                        const minutes = parseInt(parts[0]) || 0;
                        const seconds = parseInt(parts[1]) || 0;
                        window.countdownTime = (minutes * 60) + seconds;
                        console.log(`📺 Client: Updated countdownTime variable to: ${window.countdownTime}s`);
                    }
                }
            } else {
                // Calculate from timeRemaining
                const totalSeconds = Math.floor(timeRemaining / 1000);
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                const calculatedText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                timerElement.textContent = calculatedText;

                // Update the global countdownTime variable
                if (typeof window.countdownTime !== 'undefined') {
                    window.countdownTime = totalSeconds;
                    console.log(`📺 Client: Updated countdownTime variable to: ${window.countdownTime}s`);
                }

                console.log(`📺 Client: Timer calculated to: ${calculatedText}`);
            }

            // Apply timer warning styles if needed
            if (timerElement.textContent) {
                const parts = timerElement.textContent.split(':');
                if (parts.length === 2) {
                    const totalSeconds = (parseInt(parts[0]) * 60) + parseInt(parts[1]);
                    if (totalSeconds <= 10) {
                        timerElement.classList.add('timer-warning');
                    } else {
                        timerElement.classList.remove('timer-warning');
                    }
                }
            }
        } else {
            console.warn('📺 Client: Timer element not found');
        }
    }
    
    /**
     * Update client current number
     */
    function updateClientCurrentNumber(number) {
        // Update any current number displays
        const numberElements = document.querySelectorAll('.current-number, .winning-number');
        numberElements.forEach(element => {
            element.textContent = number;
        });
    }
    
    /**
     * Update client previous numbers
     */
    function updateClientPreviousNumbers(previousNumbers) {
        console.log('📺 Client: Updating previous numbers:', previousNumbers);

        const rollElements = document.querySelectorAll('.roll');
        rollElements.forEach((element, index) => {
            if (previousNumbers[index]) {
                const result = previousNumbers[index];
                element.textContent = result.number;

                // Clear existing color classes
                element.className = element.className.replace(/roll-(red|black|green)/g, '');

                // Add proper classes
                element.className = `roll roll${index + 1}`;
                if (result.color) {
                    element.classList.add(`roll-${result.color}`);
                }

                console.log(`📺 Client: Set roll${index + 1} to ${result.number} (${result.color})`);
            } else {
                // Clear empty slots
                element.textContent = '';
                element.className = `roll roll${index + 1}`;
            }
        });
    }
    
    /**
     * Update client game phase
     */
    function updateClientGamePhase(phase) {
        document.body.setAttribute('data-game-phase', phase);
    }
    
    /**
     * Disable spin button on clients
     */
    function disableClientSpinButton() {
        const spinButton = document.querySelector('.button-spin');
        if (spinButton) {
            // Store original click handler
            const originalHandler = spinButton.onclick;

            // Replace with disabled handler
            spinButton.onclick = function(event) {
                event.preventDefault();
                event.stopPropagation();
                console.log('📺 Client: Spin button disabled - controlled by master');
                return false;
            };

            // Also add event listener to override any other handlers
            spinButton.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                console.log('📺 Client: Spin button click blocked');
                return false;
            }, true);

            // Visual indication that button is disabled
            spinButton.style.opacity = '0.7';
            spinButton.style.cursor = 'not-allowed';
            spinButton.title = 'Controlled by master display';

            console.log('📺 Client: Spin button disabled successfully');
        }
    }

    /**
     * Set up client animation handlers
     */
    function setupClientAnimationHandlers() {
        console.log('📺 Client: Setting up animation handlers');

        // Override roulette wheel animation function to prevent auto-execution
        if (window.rouletteWheelAnimation && typeof window.rouletteWheelAnimation === 'function') {
            window.originalRouletteWheelAnimation = window.rouletteWheelAnimation;
            window.rouletteWheelAnimation = function() {
                console.log('📺 Client: Wheel animation blocked - waiting for master sync');
                return false;
            };
            console.log('📺 Client: Overrode rouletteWheelAnimation function');
        }
    }

    /**
     * Handle spin start on client
     */
    function handleClientSpinStart(message) {
        console.log('🎰 Client: Spin started');

        // Update game state
        syncState.gameState.isSpinning = true;
        syncState.gameState.gamePhase = message.gamePhase || 'spinning';
        syncState.gameState.bettingState = message.bettingState || { betsAllowed: false };

        // Close analytics panels if they're open
        $('.analytics-left-sidebar').fadeOut(300).removeClass('visible');
        $('.analytics-footer-bar').fadeOut(300).removeClass('visible');
        $('.analytics-right-sidebar').fadeOut(300).removeClass('visible');
        $('body').removeClass('analytics-active');

        // Play spin sound if available
        if (window.playAudio && window.ballSpinSound) {
            window.ballSpinSound.play();
        }

        console.log('📺 Client: Spin start processed');
    }

    /**
     * Handle wheel animation on client
     */
    function handleClientWheelAnimation(message) {
        console.log('🎡 Client: Starting synchronized wheel animation');

        // Update sync state
        syncState.gameState.wheelAnimation = {
            isActive: true,
            ballLandingNumber: message.ballLandingNumber,
            animationStartTime: message.animationStartTime,
            animationDuration: message.animationDuration || 5000
        };

        // Set the winning number
        window.rouletteNumber = message.winningNumber;

        // Execute synchronized wheel animation
        executeClientWheelAnimation(message.winningNumber, message.ballLandingNumber);

        console.log(`📺 Client: Wheel animation started for number ${message.winningNumber}`);
    }

    /**
     * Execute wheel animation on client
     */
    function executeClientWheelAnimation(winningNumber, ballLandingNumber) {
        // Create ball animation
        $(".ball-container").html('<div class="ball-spinner"><div class="ball"></div></div>');
        var ballContainer = document.querySelector(".ball-spinner");
        var sheet = document.createElement("style");

        const rouletteNumbersAmount = window.rouletteNumbersAmount || 37;

        sheet.textContent = `
        @-webkit-keyframes ball-container-animation{
          0%{
            transform: rotate(1440deg);
          }
          100%{
            transform: rotate(${(360 / rouletteNumbersAmount) * ballLandingNumber}deg);
          }
        }
        @keyframes ball-container-animation{
          0%{
            transform: rotate(1440deg);
          }
          100%{
            transform: rotate(${(360 / rouletteNumbersAmount) * ballLandingNumber}deg);
          }
        }`;

        ballContainer.appendChild(sheet);

        // Show wheel and start animation
        $(".roulette-wheel-container").addClass("z-index-visible").addClass("roulette-wheel-visible");
        $(".roulette-wheel-main").addClass("roulette-wheel-spin");
        $(".roulette-cross-shadow").addClass("roulette-wheel-spin");
        $(".roulette-cross").addClass("roulette-wheel-spin");

        console.log('📺 Client: Wheel animation elements activated');
    }

    /**
     * Handle spin result on client
     */
    function handleClientSpinResult(message) {
        console.log('🎯 Client: Processing spin result:', message.winningNumber);

        // Update sync state
        syncState.gameState.spinResult = {
            winningNumber: message.winningNumber,
            color: message.color,
            isHighLow: message.isHighLow,
            isOddEven: message.isOddEven,
            resultDisplayTime: Date.now()
        };
        syncState.gameState.currentNumber = message.winningNumber;
        syncState.gameState.previousNumbers = message.previousNumbers || [];

        // Update display elements
        updateClientResultDisplay(message);
        updateClientPreviousNumbers(message.previousNumbers);

        console.log(`📺 Client: Result processed - ${message.winningNumber} ${message.color}`);
    }

    /**
     * Update client result display
     */
    function updateClientResultDisplay(result) {
        // Update result elements
        const rollNumber = document.querySelector('.roll-number');
        if (rollNumber) {
            rollNumber.textContent = result.winningNumber;
        }

        const highLow = document.querySelector('.high-low');
        if (highLow) {
            highLow.textContent = result.isHighLow;
        }

        const oddEven = document.querySelector('.odd-even');
        if (oddEven) {
            oddEven.textContent = result.isOddEven;
        }

        // Update result container color
        const results = document.querySelector('.results');
        if (results) {
            results.classList.remove('roll-red', 'roll-black', 'roll-green');
            results.classList.add(`roll-${result.color}`);
        }

        // Show result alert
        setTimeout(() => {
            const alertResult = document.querySelector('.alert-spin-result');
            if (alertResult) {
                alertResult.classList.add('alert-message-visible');
            }

            const resultsElement = document.querySelector('.results');
            if (resultsElement) {
                resultsElement.classList.add('alert-message-opacity');
            }
        }, 5000);

        console.log('📺 Client: Result display updated');
    }

    /**
     * Handle game phase change on client
     */
    function handleClientGamePhaseChange(message) {
        console.log('🎮 Client: Game phase changed to:', message.phase);

        syncState.gameState.gamePhase = message.phase;
        syncState.gameState.bettingState = message.bettingState || { betsAllowed: true };

        if (message.phase === 'betting') {
            // Hide result display
            const alertResult = document.querySelector('.alert-spin-result');
            if (alertResult) {
                alertResult.classList.remove('alert-message-visible');
            }

            const results = document.querySelector('.results');
            if (results) {
                results.classList.remove('alert-message-opacity');
                setTimeout(() => {
                    results.classList.remove('roll-red', 'roll-black', 'roll-green');
                }, 1000);
            }

            // Hide wheel
            const wheelContainer = document.querySelector('.roulette-wheel-container');
            if (wheelContainer) {
                wheelContainer.classList.remove('roulette-wheel-visible');
                setTimeout(() => {
                    wheelContainer.classList.remove('z-index-visible');
                }, 1000);
            }

            // Reset animation classes
            $(".roulette-wheel-main").removeClass("roulette-wheel-spin");
            $(".roulette-cross-shadow").removeClass("roulette-wheel-spin");
            $(".roulette-cross").removeClass("roulette-wheel-spin");

            // Clean up
            $(".number-glow-container").html("");
            $(".ball-container").html("");

            // Show analytics panels
            setTimeout(() => {
                $('.analytics-left-sidebar').fadeIn(300).addClass('visible');
                $('.analytics-footer-bar').fadeIn(300).addClass('visible');
                $('.analytics-right-sidebar').fadeIn(300).addClass('visible');
                $('body').addClass('analytics-active');
            }, 1200);

            console.log('📺 Client: Returned to betting phase');
        }
    }

    /**
     * Handle no more bets on client
     */
    function handleClientNoMoreBets(message) {
        console.log('🚫 Client: No more bets');

        syncState.gameState.bettingState.betsAllowed = false;
        syncState.gameState.bettingState.noMoreBetsTime = message.timestamp;

        // Could add visual indication here if needed
        console.log('📺 Client: Betting disabled');
    }

    /**
     * Set up client analytics handlers
     */
    function setupClientAnalyticsHandlers() {
        console.log('📺 Client: Setting up analytics handlers');

        // Disable analytics button on clients
        disableClientAnalyticsControls();

        // Override analytics functions
        overrideClientAnalyticsFunctions();

        console.log('📺 Client: Analytics handlers set up successfully');
    }

    /**
     * Disable analytics controls on clients
     */
    function disableClientAnalyticsControls() {
        // Disable analytics button
        const analyticsButton = document.getElementById('analytics-button');
        if (analyticsButton) {
            analyticsButton.onclick = function(event) {
                event.preventDefault();
                event.stopPropagation();
                console.log('📺 Client: Analytics button disabled - controlled by master');
                return false;
            };

            analyticsButton.style.opacity = '0.7';
            analyticsButton.style.cursor = 'not-allowed';
            analyticsButton.title = 'Analytics controlled by master display';
            console.log('📺 Client: Analytics button disabled');
        }

        // Disable analytics close buttons
        const closeButtons = document.querySelectorAll('.analytics-close');
        closeButtons.forEach(button => {
            button.onclick = function(event) {
                event.preventDefault();
                event.stopPropagation();
                console.log('📺 Client: Analytics close button disabled');
                return false;
            };
            button.style.opacity = '0.7';
            button.style.cursor = 'not-allowed';
        });

        // Disable keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'a' || e.key === 'A') {
                e.preventDefault();
                e.stopPropagation();
                console.log('📺 Client: Analytics keyboard shortcut disabled');
                return false;
            }
        }, true);

        console.log('📺 Client: Analytics controls disabled');
    }

    /**
     * Override analytics functions on clients
     */
    function overrideClientAnalyticsFunctions() {
        // Override updateAnalytics function
        if (window.updateAnalytics && typeof window.updateAnalytics === 'function') {
            window.originalUpdateAnalytics = window.updateAnalytics;
            window.updateAnalytics = function() {
                console.log('📺 Client: updateAnalytics blocked - waiting for master sync');
                return false;
            };
            console.log('📺 Client: Overrode updateAnalytics function');
        }

        // Override saveAnalyticsData function
        if (window.saveAnalyticsData && typeof window.saveAnalyticsData === 'function') {
            window.originalSaveAnalyticsData = window.saveAnalyticsData;
            window.saveAnalyticsData = function() {
                console.log('📺 Client: saveAnalyticsData blocked - master handles saving');
                return false;
            };
            console.log('📺 Client: Overrode saveAnalyticsData function');
        }

        // Override loadAnalyticsData function
        if (window.loadAnalyticsData && typeof window.loadAnalyticsData === 'function') {
            window.originalLoadAnalyticsData = window.loadAnalyticsData;
            window.loadAnalyticsData = function() {
                console.log('📺 Client: loadAnalyticsData blocked - receiving from master');
                return false;
            };
            console.log('📺 Client: Overrode loadAnalyticsData function');
        }
    }

    /**
     * Handle analytics visibility change on client
     */
    function handleClientAnalyticsVisibility(message) {
        console.log('📺 Client: Updating analytics visibility', message);

        // Update sync state
        syncState.gameState.analyticsState.panelsVisible = message.panelsVisible;
        syncState.gameState.analyticsState.leftSidebarVisible = message.leftSidebarVisible;
        syncState.gameState.analyticsState.rightSidebarVisible = message.rightSidebarVisible;
        syncState.gameState.analyticsState.footerBarVisible = message.footerBarVisible;

        // Apply visibility changes
        const leftSidebar = document.querySelector('.analytics-left-sidebar');
        const rightSidebar = document.querySelector('.analytics-right-sidebar');
        // Note: No footer bar exists in the HTML

        console.log('📺 Client: Applying analytics visibility', {
            left: message.leftSidebarVisible,
            right: message.rightSidebarVisible,
            leftElement: !!leftSidebar,
            rightElement: !!rightSidebar
        });

        if (leftSidebar) {
            if (message.leftSidebarVisible) {
                $(leftSidebar).fadeIn(300).addClass('visible');
                console.log('📺 Client: Showing left sidebar');
            } else {
                $(leftSidebar).fadeOut(300).removeClass('visible');
                console.log('📺 Client: Hiding left sidebar');
            }
        }

        if (rightSidebar) {
            if (message.rightSidebarVisible) {
                $(rightSidebar).fadeIn(300).addClass('visible');
                console.log('📺 Client: Showing right sidebar');
            } else {
                $(rightSidebar).fadeOut(300).removeClass('visible');
                console.log('📺 Client: Hiding right sidebar');
            }
        }

        // Update body class
        if (message.panelsVisible) {
            document.body.classList.add('analytics-active');
        } else {
            document.body.classList.remove('analytics-active');
        }

        console.log('📺 Client: Analytics visibility updated successfully');
    }

    /**
     * Handle analytics data update on client
     */
    function handleClientAnalyticsData(message) {
        console.log('📺 Client: Updating analytics data');

        // Update sync state
        syncState.gameState.analyticsState.analyticsData = message.analyticsData;
        syncState.gameState.analyticsState.lastAnalyticsUpdate = Date.now();

        // Apply analytics data
        applyClientAnalyticsData(message.analyticsData);

        console.log('📺 Client: Analytics data updated successfully');
    }

    /**
     * Handle full analytics sync on client
     */
    function handleClientAnalyticsFullSync(message) {
        console.log('📺 Client: Processing full analytics sync');

        // Update visibility
        handleClientAnalyticsVisibility(message);

        // Update data
        handleClientAnalyticsData(message);

        console.log('📺 Client: Full analytics sync completed');
    }

    /**
     * Apply analytics data to client display
     */
    function applyClientAnalyticsData(analyticsData) {
        try {
            // Update global variables
            if (analyticsData.allSpins) {
                window.allSpins = [...analyticsData.allSpins];
            }
            if (analyticsData.numberFrequency) {
                window.numberFrequency = { ...analyticsData.numberFrequency };
            }
            if (analyticsData.currentDrawNumber) {
                window.currentDrawNumber = analyticsData.currentDrawNumber;
            }
            if (analyticsData.rolledNumbersArray) {
                window.rolledNumbersArray = [...analyticsData.rolledNumbersArray];
            }
            if (analyticsData.rolledNumbersColorArray) {
                window.rolledNumbersColorArray = [...analyticsData.rolledNumbersColorArray];
            }

            // Update DOM elements with HTML content
            if (analyticsData.hotNumbersHTML) {
                const hotContainer = document.getElementById('hot-numbers');
                if (hotContainer) {
                    hotContainer.innerHTML = analyticsData.hotNumbersHTML;
                }
            }

            if (analyticsData.coldNumbersHTML) {
                const coldContainer = document.getElementById('cold-numbers');
                if (coldContainer) {
                    coldContainer.innerHTML = analyticsData.coldNumbersHTML;
                }
            }

            // Update number history (last 8 spins)
            if (analyticsData.numberHistoryHTML) {
                const historyContainer = document.getElementById('number-history');
                if (historyContainer) {
                    historyContainer.innerHTML = analyticsData.numberHistoryHTML;
                    console.log('📺 Client: Applied number history HTML:', analyticsData.numberHistoryHTML.length, 'characters');
                } else {
                    console.warn('📺 Client: Number history container not found');
                }
            }

            // Update distribution displays
            if (analyticsData.distributions) {
                updateClientDistributionDisplays(analyticsData.distributions);
            }

            console.log('📺 Client: Analytics data applied to display');

        } catch (error) {
            console.error('📺 Client: Error applying analytics data:', error);
        }
    }

    /**
     * Update distribution displays on client
     */
    function updateClientDistributionDisplays(distributions) {
        // Color distribution
        if (distributions.red) {
            const redPercentage = document.getElementById('red-percentage');
            const redCount = document.getElementById('red-count');
            if (redPercentage) redPercentage.textContent = distributions.red.percentage;
            if (redCount) redCount.textContent = distributions.red.count;
        }

        if (distributions.black) {
            const blackPercentage = document.getElementById('black-percentage');
            const blackCount = document.getElementById('black-count');
            if (blackPercentage) blackPercentage.textContent = distributions.black.percentage;
            if (blackCount) blackCount.textContent = distributions.black.count;
        }

        if (distributions.green) {
            const greenPercentage = document.getElementById('green-percentage');
            const greenCount = document.getElementById('green-count');
            if (greenPercentage) greenPercentage.textContent = distributions.green.percentage;
            if (greenCount) greenCount.textContent = distributions.green.count;
        }

        // Odd/Even distribution
        if (distributions.odd) {
            const oddPercentage = document.getElementById('odd-percentage');
            const oddCount = document.getElementById('odd-count');
            if (oddPercentage) oddPercentage.textContent = distributions.odd.percentage;
            if (oddCount) oddCount.textContent = distributions.odd.count;
        }

        if (distributions.even) {
            const evenPercentage = document.getElementById('even-percentage');
            const evenCount = document.getElementById('even-count');
            if (evenPercentage) evenPercentage.textContent = distributions.even.percentage;
            if (evenCount) evenCount.textContent = distributions.even.count;
        }

        // High/Low distribution
        if (distributions.high) {
            const highPercentage = document.getElementById('high-percentage');
            const highCount = document.getElementById('high-count');
            if (highPercentage) highPercentage.textContent = distributions.high.percentage;
            if (highCount) highCount.textContent = distributions.high.count;
        }

        if (distributions.low) {
            const lowPercentage = document.getElementById('low-percentage');
            const lowCount = document.getElementById('low-count');
            if (lowPercentage) lowPercentage.textContent = distributions.low.percentage;
            if (lowCount) lowCount.textContent = distributions.low.count;
        }

        console.log('📺 Client: Distribution displays updated');
    }

    /**
     * Request initial analytics sync from master
     */
    function requestInitialAnalyticsSync() {
        if (!syncState.channel) return;

        console.log('📺 Client: Requesting initial analytics sync from master');

        // Send request to master for current analytics state
        syncState.channel.postMessage({
            type: 'analytics_sync_request',
            clientId: generateSessionId(),
            timestamp: Date.now()
        });

        // Also try to get analytics data after a short delay
        setTimeout(() => {
            if (syncState.channel) {
                syncState.channel.postMessage({
                    type: 'analytics_sync_request',
                    clientId: generateSessionId(),
                    timestamp: Date.now()
                });
                console.log('📺 Client: Sent follow-up analytics sync request');
            }
        }, 2000);
    }
    
    /**
     * Disable client interactions
     */
    function disableClientInteractions() {
        // Disable betting controls
        const bettingElements = document.querySelectorAll('.betting-chip, .part, .button');
        bettingElements.forEach(element => {
            element.style.pointerEvents = 'none';
            element.style.opacity = '0.7';
        });

        // Add client mode indicator
        document.body.classList.add('client-mode');

        console.log('📺 Client interactions disabled');
    }

    /**
     * Disable client game logic
     */
    function disableClientGameLogic() {
        console.log('📺 Disabling client game logic...');

        // Disable countdown timer - this is critical!
        if (window.countdownInterval) {
            clearInterval(window.countdownInterval);
            window.countdownInterval = null;
            console.log('📺 Disabled countdown interval');
        }

        // Override countdown functions but preserve updateCountdownDisplay for sync updates
        if (window.startCountdown) {
            window.originalStartCountdown = window.startCountdown;
            window.startCountdown = function() {
                console.log('📺 Client: Countdown disabled - timer synced from master');
                return false;
            };
            console.log('📺 Disabled startCountdown function');
        }

        // Don't disable updateCountdownDisplay - we need it for applying sync updates
        // Instead, we'll control when it's called
        console.log('📺 Keeping updateCountdownDisplay function for sync updates');

        // Disable spin button on clients
        disableClientSpinButton();

        // Set up client-side animation handlers
        setupClientAnimationHandlers();

        // Set up client-side analytics handlers
        setupClientAnalyticsHandlers();

        // Request initial analytics sync from master
        requestInitialAnalyticsSync();

        // Disable any existing timers or intervals that might be running game logic
        if (window.gameTimer) {
            clearInterval(window.gameTimer);
            window.gameTimer = null;
            console.log('📺 Disabled game timer');
        }

        // Disable spin functions
        if (window.startSpin) {
            window.originalStartSpin = window.startSpin;
            window.startSpin = function() {
                console.log('📺 Client: Spin function disabled - waiting for master');
                return false;
            };
            console.log('📺 Disabled spin function');
        }

        // Disable any result generation functions
        if (window.generateResult) {
            window.originalGenerateResult = window.generateResult;
            window.generateResult = function() {
                console.log('📺 Client: Result generation disabled - waiting for master');
                return null;
            };
            console.log('📺 Disabled result generation');
        }

        // Disable forced number handling
        if (window.handleForcedNumber) {
            window.originalHandleForcedNumber = window.handleForcedNumber;
            window.handleForcedNumber = function() {
                console.log('📺 Client: Forced number handling disabled - waiting for master');
                return false;
            };
            console.log('📺 Disabled forced number handling');
        }

        // Disable auto-spin functionality
        if (window.autoSpin) {
            window.originalAutoSpin = window.autoSpin;
            window.autoSpin = function() {
                console.log('📺 Client: Auto-spin disabled - waiting for master');
                return false;
            };
            console.log('📺 Disabled auto-spin');
        }

        // Clear any localStorage countdown data to prevent interference
        localStorage.removeItem('countdownEndTime');
        console.log('📺 Cleared countdown localStorage data');

        console.log('📺 Client game logic disabled successfully');
    }
    
    /**
     * Show master status (disabled)
     */
    function showMasterStatus() {
        // Status display disabled
    }

    /**
     * Show client status (disabled)
     */
    function showClientStatus() {
        // Status display disabled
    }

    /**
     * Update master status (disabled)
     */
    function updateMasterStatus() {
        // Status display disabled
    }

    /**
     * Show status indicator (disabled)
     */
    function showStatus(title, description, color) {
        // Status display disabled
    }
    
    /**
     * Update client current number
     */
    function updateClientCurrentNumber(number) {
        const currentNumberElement = document.getElementById('current-number');
        if (currentNumberElement) {
            currentNumberElement.textContent = number;
        }
    }

    /**
     * Update client previous numbers (enhanced version)
     */
    function updateClientPreviousNumbers(numbers) {
        if (!Array.isArray(numbers)) return;

        console.log('📺 Client: Updating previous numbers:', numbers);

        // Update the global arrays to match master
        if (window.rolledNumbersArray !== numbers) {
            window.rolledNumbersArray = [...numbers];
            console.log('📺 Client: Updated rolledNumbersArray');
        }

        // Update the display elements
        for (let i = 0; i < 5; i++) {
            const rollElement = document.querySelector(`.roll${i + 1}`);
            if (rollElement) {
                if (i < numbers.length) {
                    rollElement.textContent = numbers[i];

                    // Set color class based on number
                    const color = getNumberColor(numbers[i]);
                    rollElement.classList.remove('roll-red', 'roll-black', 'roll-green');
                    rollElement.classList.add(`roll-${color}`);

                    console.log(`📺 Client: Set .roll${i + 1} to ${numbers[i]} (${color})`);
                } else {
                    rollElement.textContent = '';
                    rollElement.classList.remove('roll-red', 'roll-black', 'roll-green');
                }
            }
        }

        console.log('📺 Client: Previous numbers updated successfully');
    }

    /**
     * Start localStorage polling (fallback)
     */
    function startStoragePolling() {
        setInterval(() => {
            const stored = localStorage.getItem(SYNC_CONFIG.storageKey);
            if (stored) {
                try {
                    const message = JSON.parse(stored);
                    if (message.type === 'game_state') {
                        updateClientGameState(message.gameState);
                    }
                } catch (error) {
                    console.error('Error parsing stored game state:', error);
                }
            }
        }, 1000);
    }
    
    // Public API
    window.MasterClientSync = {
        initializeMaster: initializeMaster,
        initializeClient: initializeClient,
        isMaster: () => syncState.isMaster,
        isClient: () => syncState.isClient,
        getGameState: () => syncState.gameState,
        getConnectedClients: () => syncState.connectedClients.size
    };
    
    console.log('🎰 Master-Client Sync: Loaded successfully!');
    console.log('💡 Use MasterClientSync.initializeMaster() for master display');
    console.log('💡 Use MasterClientSync.initializeClient() for client displays');
})();
