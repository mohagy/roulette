<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firestore Diagnostic Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
        }
        .status-box {
            background: #2a2a2a;
            border: 2px solid #444;
            border-radius: 8px;
            padding: 20px;
            margin: 10px 0;
        }
        .status-box.success { border-color: #4caf50; }
        .status-box.error { border-color: #f44336; }
        .status-box.warning { border-color: #ff9800; }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #444;
        }
        .status-item:last-child { border-bottom: none; }
        .status-label { font-weight: bold; }
        .status-value { color: #aaa; }
        .status-value.success { color: #4caf50; }
        .status-value.error { color: #f44336; }
        .status-value.warning { color: #ff9800; }
        button {
            background: #4caf50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        button:hover { background: #45a049; }
        button:disabled { background: #666; cursor: not-allowed; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th, .data-table td {
            border: 1px solid #444;
            padding: 8px;
            text-align: left;
        }
        .data-table th {
            background: #333;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background: #2a2a2a;
        }
        .log-container {
            background: #000;
            color: #0f0;
            padding: 15px;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin-top: 20px;
        }
        .log-entry {
            margin: 2px 0;
            padding: 2px 0;
        }
        .log-entry.error { color: #f44; }
        .log-entry.warning { color: #fa0; }
        .log-entry.success { color: #0f0; }
        .collection-section {
            margin: 20px 0;
        }
        h2 {
            color: #4caf50;
            border-bottom: 2px solid #4caf50;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>🔥 Firestore Diagnostic Tool</h1>
    <p>This tool helps diagnose Firestore connectivity and shows real-time data from your Firebase project.</p>
    
    <div class="status-box" id="connectionStatus">
        <h2>Connection Status</h2>
        <div class="status-item">
            <span class="status-label">Firebase SDK:</span>
            <span class="status-value" id="firebaseSdk">Checking...</span>
        </div>
        <div class="status-item">
            <span class="status-label">Firestore SDK:</span>
            <span class="status-value" id="firestoreSdk">Checking...</span>
        </div>
        <div class="status-item">
            <span class="status-label">Firestore Instance:</span>
            <span class="status-value" id="firestoreInstance">Checking...</span>
        </div>
        <div class="status-item">
            <span class="status-label">FirestoreService:</span>
            <span class="status-value" id="firestoreService">Checking...</span>
        </div>
        <div class="status-item">
            <span class="status-label">Connection State:</span>
            <span class="status-value" id="connectionState">Checking...</span>
        </div>
        <div class="status-item">
            <span class="status-label">Project ID:</span>
            <span class="status-value" id="projectId">superbet-830b0</span>
        </div>
    </div>

    <div class="status-box">
        <h2>Actions</h2>
        <button onclick="runFullDiagnostic()">🔄 Run Full Diagnostic</button>
        <button onclick="testWrite()">✍️ Test Write to Firestore</button>
        <button onclick="loadAllData()">📊 Load All Firestore Data</button>
        <button onclick="clearLogs()">🗑️ Clear Logs</button>
    </div>

    <div class="collection-section" id="spinCommandsSection" style="display: none;">
        <h2>Spin Commands Collection</h2>
        <div id="spinCommandsData"></div>
    </div>

    <div class="collection-section" id="gameStateSection" style="display: none;">
        <h2>Game State Collection</h2>
        <div id="gameStateData"></div>
    </div>

    <div class="collection-section" id="winningNumbersSection" style="display: none;">
        <h2>Winning Numbers Collection</h2>
        <div id="winningNumbersData"></div>
    </div>

    <div class="log-container" id="logContainer"></div>

    <!-- Firebase SDKs -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-database-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-firestore-compat.js"></script>
    
    <!-- Firebase Configuration and Service -->
    <script src="../js/firebase-config.js"></script>
    <script src="../js/firestore-service.js"></script>

    <script>
        let spinCommandsListener = null;
        let gameStateListener = null;
        let winningNumbersListener = null;

        function log(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            const timestamp = new Date().toLocaleTimeString();
            entry.textContent = `[${timestamp}] ${message}`;
            logContainer.appendChild(entry);
            logContainer.scrollTop = logContainer.scrollHeight;
            console.log(message);
        }

        function clearLogs() {
            document.getElementById('logContainer').innerHTML = '';
        }

        function updateStatus(elementId, value, type = 'info') {
            const element = document.getElementById(elementId);
            element.textContent = value;
            element.className = `status-value ${type}`;
        }

        async function checkConnection() {
            log('🔍 Checking Firebase connection...', 'info');
            
            // Check Firebase SDK
            if (typeof firebase !== 'undefined') {
                updateStatus('firebaseSdk', '✅ Loaded', 'success');
                log('✅ Firebase SDK loaded', 'success');
            } else {
                updateStatus('firebaseSdk', '❌ Not loaded', 'error');
                log('❌ Firebase SDK not loaded', 'error');
                return false;
            }

            // Check Firestore SDK
            if (typeof firebase.firestore === 'function') {
                updateStatus('firestoreSdk', '✅ Loaded', 'success');
                log('✅ Firestore SDK loaded', 'success');
            } else {
                updateStatus('firestoreSdk', '❌ Not loaded', 'error');
                log('❌ Firestore SDK not loaded', 'error');
                return false;
            }

            // Check Firestore instance
            if (window.firebaseFirestore) {
                updateStatus('firestoreInstance', '✅ Available', 'success');
                log('✅ Firestore instance available', 'success');
            } else {
                updateStatus('firestoreInstance', '❌ Not available', 'error');
                log('❌ Firestore instance not available', 'error');
                return false;
            }

            // Check FirestoreService
            if (window.FirestoreService) {
                updateStatus('firestoreService', '✅ Available', 'success');
                log('✅ FirestoreService available', 'success');
                
                if (window.FirestoreService.isAvailable()) {
                    updateStatus('connectionState', '✅ Connected', 'success');
                    log('✅ FirestoreService.isAvailable() = true', 'success');
                } else {
                    updateStatus('connectionState', '⚠️ Not available', 'warning');
                    log('⚠️ FirestoreService.isAvailable() = false', 'warning');
                }
            } else {
                updateStatus('firestoreService', '❌ Not available', 'error');
                updateStatus('connectionState', '❌ Not connected', 'error');
                log('❌ FirestoreService not available', 'error');
                return false;
            }

            return true;
        }

        async function testWrite() {
            log('✍️ Testing write operation to Firestore...', 'info');
            
            if (!window.FirestoreService || !window.FirestoreService.isAvailable()) {
                log('❌ FirestoreService not available for write test', 'error');
                return;
            }

            try {
                const testData = {
                    countdownEndTime: Date.now() + 180000,
                    countdownTime: 180,
                    isRunning: true,
                    test: true,
                    timestamp: new Date().toISOString()
                };

                await window.FirestoreService.writeTimerState(
                    testData.countdownEndTime,
                    testData.countdownTime,
                    testData.isRunning
                );

                log('✅ Write test successful - Timer state written to Firestore', 'success');
                
                // Also test spin command
                const syncTimestamp = new Date(Date.now() + 2000);
                const commandId = await window.FirestoreService.writeSpinCommand(7, 1, syncTimestamp, 'test');
                log(`✅ Write test successful - Spin command written: ${commandId}`, 'success');
                
            } catch (error) {
                log('❌ Write test failed: ' + error.message, 'error');
                log('Error details: ' + JSON.stringify(error), 'error');
            }
        }

        async function loadAllData() {
            log('📊 Loading all Firestore data...', 'info');
            
            if (!window.firebaseFirestore) {
                log('❌ Firestore not available', 'error');
                return;
            }

            const firestore = window.firebaseFirestore;

            // Load Spin Commands
            try {
                log('📋 Loading spinCommands collection...', 'info');
                const spinCommandsSnapshot = await firestore.collection('spinCommands')
                    .orderBy('createdAt', 'desc')
                    .limit(10)
                    .get();
                
                const spinCommands = [];
                spinCommandsSnapshot.forEach(doc => {
                    const data = doc.data();
                    spinCommands.push({
                        id: doc.id,
                        ...data,
                        createdAt: data.createdAt?.toDate?.() || data.createdAt,
                        syncTimestamp: data.syncTimestamp?.toDate?.() || data.syncTimestamp
                    });
                });
                
                displaySpinCommands(spinCommands);
                log(`✅ Loaded ${spinCommands.length} spin commands`, 'success');
            } catch (error) {
                log('❌ Error loading spinCommands: ' + error.message, 'error');
            }

            // Load Game State
            try {
                log('📋 Loading gameState collection...', 'info');
                const gameStateDoc = await firestore.collection('gameState').doc('current').get();
                
                if (gameStateDoc.exists) {
                    displayGameState(gameStateDoc.data());
                    log('✅ Loaded gameState', 'success');
                } else {
                    log('⚠️ gameState document does not exist', 'warning');
                }
            } catch (error) {
                log('❌ Error loading gameState: ' + error.message, 'error');
            }

            // Load Winning Numbers
            try {
                log('📋 Loading winningNumbers collection...', 'info');
                const winningNumbersSnapshot = await firestore.collection('winningNumbers')
                    .orderBy('drawNumber', 'desc')
                    .limit(10)
                    .get();
                
                const winningNumbers = [];
                winningNumbersSnapshot.forEach(doc => {
                    const data = doc.data();
                    winningNumbers.push({
                        id: doc.id,
                        ...data,
                        timestamp: data.timestamp?.toDate?.() || data.timestamp
                    });
                });
                
                displayWinningNumbers(winningNumbers);
                log(`✅ Loaded ${winningNumbers.length} winning numbers`, 'success');
            } catch (error) {
                log('❌ Error loading winningNumbers: ' + error.message, 'error');
            }

            // Set up real-time listeners
            setupRealtimeListeners();
        }

        function displaySpinCommands(commands) {
            const container = document.getElementById('spinCommandsData');
            const section = document.getElementById('spinCommandsSection');
            
            if (commands.length === 0) {
                container.innerHTML = '<p style="color: #ff9800;">No spin commands found in Firestore</p>';
                section.style.display = 'block';
                return;
            }

            let html = '<table class="data-table"><thead><tr><th>Command ID</th><th>Winning Number</th><th>Draw Number</th><th>Source</th><th>Sync Timestamp</th><th>Created At</th></tr></thead><tbody>';
            
            commands.forEach(cmd => {
                const syncTime = cmd.syncTimestamp instanceof Date ? cmd.syncTimestamp.toLocaleString() : (cmd.syncTimestamp || 'N/A');
                const created = cmd.createdAt instanceof Date ? cmd.createdAt.toLocaleString() : (cmd.createdAt || 'N/A');
                
                html += `<tr>
                    <td>${cmd.id || cmd.commandId || 'N/A'}</td>
                    <td>${cmd.winningNumber ?? 'N/A'}</td>
                    <td>${cmd.drawNumber ?? 'N/A'}</td>
                    <td>${cmd.source || 'N/A'}</td>
                    <td>${syncTime}</td>
                    <td>${created}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
            section.style.display = 'block';
        }

        function displayGameState(state) {
            const container = document.getElementById('gameStateData');
            const section = document.getElementById('gameStateSection');
            
            if (!state) {
                container.innerHTML = '<p style="color: #ff9800;">No game state found</p>';
                section.style.display = 'block';
                return;
            }

            let html = '<table class="data-table"><thead><tr><th>Property</th><th>Value</th></tr></thead><tbody>';
            
            for (const [key, value] of Object.entries(state)) {
                let displayValue = value;
                if (value instanceof Date) {
                    displayValue = value.toLocaleString();
                } else if (typeof value === 'object') {
                    displayValue = JSON.stringify(value, null, 2);
                }
                html += `<tr><td>${key}</td><td><pre style="margin:0;white-space:pre-wrap;">${displayValue}</pre></td></tr>`;
            }
            
            html += '</tbody></table>';
            container.innerHTML = html;
            section.style.display = 'block';
        }

        function displayWinningNumbers(numbers) {
            const container = document.getElementById('winningNumbersData');
            const section = document.getElementById('winningNumbersSection');
            
            if (numbers.length === 0) {
                container.innerHTML = '<p style="color: #ff9800;">No winning numbers found in Firestore</p>';
                section.style.display = 'block';
                return;
            }

            let html = '<table class="data-table"><thead><tr><th>Draw Number</th><th>Winning Number</th><th>Color</th><th>Source</th><th>Timestamp</th></tr></thead><tbody>';
            
            numbers.forEach(num => {
                const timestamp = num.timestamp instanceof Date ? num.timestamp.toLocaleString() : (num.timestamp || 'N/A');
                
                html += `<tr>
                    <td>${num.drawNumber ?? 'N/A'}</td>
                    <td>${num.winningNumber ?? 'N/A'}</td>
                    <td>${num.color || 'N/A'}</td>
                    <td>${num.source || 'N/A'}</td>
                    <td>${timestamp}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
            section.style.display = 'block';
        }

        function setupRealtimeListeners() {
            if (!window.FirestoreService || !window.FirestoreService.isAvailable()) {
                log('⚠️ FirestoreService not available for real-time listeners', 'warning');
                return;
            }

            log('👂 Setting up real-time listeners...', 'info');

            // Listen to spin commands
            if (spinCommandsListener) {
                spinCommandsListener();
            }
            spinCommandsListener = window.FirestoreService.listenToSpinCommands((command) => {
                log(`🔥 REAL-TIME: Spin command received - Number: ${command.winningNumber}, Draw: ${command.drawNumber}`, 'success');
                loadAllData(); // Refresh data
            });

            // Listen to game state
            if (gameStateListener) {
                gameStateListener();
            }
            gameStateListener = window.FirestoreService.listenToGameState((gameState) => {
                log(`🔥 REAL-TIME: Game state updated`, 'success');
                displayGameState(gameState);
            });

            // Listen to timer state
            window.FirestoreService.listenToTimerState((timerData) => {
                log(`⏱️ REAL-TIME: Timer state updated - ${timerData.countdownTime || 'N/A'}s remaining`, 'success');
            });

            log('✅ Real-time listeners set up', 'success');
        }

        async function runFullDiagnostic() {
            log('🔄 Running full diagnostic...', 'info');
            clearLogs();
            
            const connected = await checkConnection();
            if (!connected) {
                log('❌ Connection check failed', 'error');
                return;
            }

            await new Promise(resolve => setTimeout(resolve, 1000));
            
            await testWrite();
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            await loadAllData();
            
            log('✅ Full diagnostic completed', 'success');
        }

        // Auto-run on load
        window.addEventListener('load', () => {
            setTimeout(() => {
                checkConnection();
            }, 2000);
        });

        // Listen for Firestore ready
        window.addEventListener('firestore-ready', () => {
            log('🔥 Firestore ready event received', 'success');
            checkConnection();
        });
    </script>
</body>
</html>

