<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 Database Storage Speed Test - Real-Time Monitoring</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }

        .content {
            padding: 30px;
        }

        .test-controls {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #007bff;
        }

        .button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #28a745;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .real-time-monitor {
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            height: 300px;
            overflow-y: auto;
            margin-bottom: 30px;
            border: 2px solid #333;
        }

        .log-entry {
            margin: 5px 0;
            padding: 5px;
            border-left: 3px solid #00ff00;
            padding-left: 10px;
        }

        .log-success { border-left-color: #00ff00; color: #00ff00; }
        .log-error { border-left-color: #ff0000; color: #ff0000; }
        .log-warning { border-left-color: #ffaa00; color: #ffaa00; }
        .log-info { border-left-color: #00aaff; color: #00aaff; }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            width: 0%;
            transition: width 0.3s ease;
        }

        .endpoint-comparison {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .endpoint-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .endpoint-title {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }

        .speed-excellent { color: #28a745; }
        .speed-good { color: #ffc107; }
        .speed-poor { color: #dc3545; }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        #speedChart {
            width: 100%;
            height: 300px;
        }

        .test-results {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .input-group {
            margin: 10px 0;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-online { background: #28a745; }
        .status-offline { background: #dc3545; }
        .status-testing { background: #ffc107; animation: pulse 1s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Database Storage Speed Test</h1>
            <p>Real-Time Monitoring of Winning Number Storage Performance</p>
        </div>

        <div class="content">
            <!-- Test Controls -->
            <div class="test-controls">
                <h3>🎮 Test Controls</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div class="input-group">
                        <label>Test Endpoint:</label>
                        <select id="testEndpoint">
                            <option value="ultra_fast">Ultra Fast API</option>
                            <option value="high_performance">High Performance API</option>
                            <option value="triple_storage">Original Triple Storage</option>
                            <option value="all">All Endpoints</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Number of Tests:</label>
                        <input type="number" id="testCount" value="10" min="1" max="100">
                    </div>
                    <div class="input-group">
                        <label>Test Interval (ms):</label>
                        <input type="number" id="testInterval" value="100" min="50" max="5000">
                    </div>
                </div>

                <button class="button" onclick="startSingleTest()">🎯 Single Test</button>
                <button class="button" onclick="startBatchTest()">📊 Batch Test</button>
                <button class="button" onclick="startStressTest()">⚡ Stress Test</button>
                <button class="button" onclick="startConcurrentTest()">🔄 Concurrent Test</button>
                <button class="button" onclick="verifyDatabaseStorage()">🔍 Verify Database</button>
                <button class="button" onclick="clearResults()">🗑️ Clear Results</button>
                <button class="button" onclick="exportResults()">📥 Export Results</button>
            </div>

            <!-- Real-Time Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="avgResponseTime">0</div>
                    <div class="stat-label">Average Response Time (ms)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="fastestTime">0</div>
                    <div class="stat-label">Fastest Time (ms)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="slowestTime">0</div>
                    <div class="stat-label">Slowest Time (ms)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="successRate">0%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="totalTests">0</div>
                    <div class="stat-label">Total Tests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="testsPerSecond">0</div>
                    <div class="stat-label">Tests Per Second</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div id="progressContainer" style="display: none;">
                <h4>Test Progress</h4>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
                <div id="progressText">0 / 0 tests completed</div>
            </div>

            <!-- Real-Time Monitor -->
            <div>
                <h3>📡 Real-Time Monitor</h3>
                <div style="margin-bottom: 15px;">
                    <button class="button" onclick="startRealTimeMonitoring()" id="startMonitorBtn">🔴 Start TV Display Monitoring</button>
                    <button class="button" onclick="stopRealTimeMonitoring()" id="stopMonitorBtn" style="display: none;">⏹️ Stop Monitoring</button>
                    <span id="monitoringStatus" style="margin-left: 15px; font-weight: bold;">Monitoring: OFF</span>
                </div>
                <div class="real-time-monitor" id="realTimeMonitor">
                    <div class="log-entry log-info">🚀 Database Storage Speed Test initialized</div>
                    <div class="log-entry log-info">📊 Ready to test storage performance</div>
                    <div class="log-entry log-warning">🎮 Start TV Display Monitoring to see real-time results from tvdisplay/index.html</div>
                </div>
            </div>

            <!-- Endpoint Comparison -->
            <div class="endpoint-comparison">
                <div class="endpoint-card">
                    <div class="endpoint-title">
                        <span class="status-indicator status-offline" id="ultraFastStatus"></span>
                        Ultra Fast API
                    </div>
                    <div>Last Response: <span id="ultraFastTime">-</span></div>
                    <div>Success Rate: <span id="ultraFastSuccess">-</span></div>
                    <div>Average: <span id="ultraFastAvg">-</span></div>
                </div>

                <div class="endpoint-card">
                    <div class="endpoint-title">
                        <span class="status-indicator status-offline" id="highPerfStatus"></span>
                        High Performance API
                    </div>
                    <div>Last Response: <span id="highPerfTime">-</span></div>
                    <div>Success Rate: <span id="highPerfSuccess">-</span></div>
                    <div>Average: <span id="highPerfAvg">-</span></div>
                </div>

                <div class="endpoint-card">
                    <div class="endpoint-title">
                        <span class="status-indicator status-offline" id="tripleStorageStatus"></span>
                        Original Triple Storage
                    </div>
                    <div>Last Response: <span id="tripleStorageTime">-</span></div>
                    <div>Success Rate: <span id="tripleStorageSuccess">-</span></div>
                    <div>Average: <span id="tripleStorageAvg">-</span></div>
                </div>
            </div>

            <!-- Speed Chart -->
            <div class="chart-container">
                <h3>📈 Response Time Chart</h3>
                <canvas id="speedChart"></canvas>
            </div>

            <!-- Test Results -->
            <div class="test-results">
                <h3>📋 Detailed Test Results</h3>
                <div id="testResultsTable">
                    <p>No test results yet. Run a test to see detailed results.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Global variables
        let testResults = [];
        let isTestingActive = false;
        let testStartTime = 0;
        let speedChart = null;
        let realTimeMonitoring = false;
        let monitoringInterval = null;
        let lastCheckedId = 0;

        // API endpoints
        const endpoints = {
            ultra_fast: '/slipp/php/ultra_fast_storage_api.php',
            high_performance: '/slipp/php/high_performance_storage_api.php',
            triple_storage: '/slipp/php/triple_storage_api.php'
        };

        // Initialize chart
        function initChart() {
            const ctx = document.getElementById('speedChart').getContext('2d');
            speedChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: [],
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Response Time (ms)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Test Number'
                            }
                        }
                    }
                }
            });
        }

        // Log message to monitor
        function logMessage(message, type = 'info') {
            const monitor = document.getElementById('realTimeMonitor');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            monitor.appendChild(logEntry);
            monitor.scrollTop = monitor.scrollHeight;
        }

        // Update statistics
        function updateStats() {
            if (testResults.length === 0) return;

            const responseTimes = testResults.map(r => r.responseTime).filter(t => t > 0);
            const successfulTests = testResults.filter(r => r.success);

            if (responseTimes.length > 0) {
                const avg = responseTimes.reduce((a, b) => a + b, 0) / responseTimes.length;
                const fastest = Math.min(...responseTimes);
                const slowest = Math.max(...responseTimes);

                document.getElementById('avgResponseTime').textContent = avg.toFixed(2);
                document.getElementById('fastestTime').textContent = fastest.toFixed(2);
                document.getElementById('slowestTime').textContent = slowest.toFixed(2);
            }

            const successRate = (successfulTests.length / testResults.length * 100).toFixed(1);
            document.getElementById('successRate').textContent = successRate + '%';
            document.getElementById('totalTests').textContent = testResults.length;

            // Calculate tests per second
            if (testStartTime > 0) {
                const elapsed = (Date.now() - testStartTime) / 1000;
                const testsPerSecond = (testResults.length / elapsed).toFixed(2);
                document.getElementById('testsPerSecond').textContent = testsPerSecond;
            }
        }

        // Update chart
        function updateChart() {
            if (!speedChart || testResults.length === 0) return;

            const labels = testResults.map((_, index) => index + 1);
            const data = testResults.map(r => r.responseTime);

            speedChart.data.labels = labels;
            speedChart.data.datasets[0].data = data;
            speedChart.update();
        }

        // Test single endpoint
        async function testEndpoint(endpointName, testNumber = 1) {
            const url = endpoints[endpointName];
            const testData = {
                winning_number: Math.floor(Math.random() * 37),
                draw_number: 10000 + testNumber,
                timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
            };

            logMessage(`🎯 Testing ${endpointName} - Test #${testNumber}`, 'info');

            const startTime = performance.now();

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(testData)
                });

                const endTime = performance.now();
                const responseTime = endTime - startTime;

                const result = await response.json();
                const success = response.ok && result.status === 'success';

                const testResult = {
                    endpoint: endpointName,
                    testNumber: testNumber,
                    responseTime: responseTime,
                    success: success,
                    timestamp: new Date(),
                    testData: testData,
                    response: result
                };

                testResults.push(testResult);

                if (success) {
                    logMessage(`✅ ${endpointName} - ${responseTime.toFixed(2)}ms - SUCCESS`, 'success');
                } else {
                    logMessage(`❌ ${endpointName} - ${responseTime.toFixed(2)}ms - FAILED`, 'error');
                }

                updateStats();
                updateChart();
                updateEndpointStatus(endpointName, responseTime, success);

                return testResult;

            } catch (error) {
                const endTime = performance.now();
                const responseTime = endTime - startTime;

                logMessage(`💥 ${endpointName} - ERROR: ${error.message}`, 'error');

                const testResult = {
                    endpoint: endpointName,
                    testNumber: testNumber,
                    responseTime: responseTime,
                    success: false,
                    timestamp: new Date(),
                    testData: testData,
                    error: error.message
                };

                testResults.push(testResult);
                updateStats();
                updateEndpointStatus(endpointName, responseTime, false);

                return testResult;
            }
        }

        // Update endpoint status indicators
        function updateEndpointStatus(endpointName, responseTime, success) {
            const statusMap = {
                ultra_fast: 'ultraFastStatus',
                high_performance: 'highPerfStatus',
                triple_storage: 'tripleStorageStatus'
            };

            const timeMap = {
                ultra_fast: 'ultraFastTime',
                high_performance: 'highPerfTime',
                triple_storage: 'tripleStorageTime'
            };

            const statusElement = document.getElementById(statusMap[endpointName]);
            const timeElement = document.getElementById(timeMap[endpointName]);

            if (statusElement) {
                statusElement.className = `status-indicator ${success ? 'status-online' : 'status-offline'}`;
            }

            if (timeElement) {
                const timeClass = responseTime < 50 ? 'speed-excellent' : responseTime < 100 ? 'speed-good' : 'speed-poor';
                timeElement.innerHTML = `<span class="${timeClass}">${responseTime.toFixed(2)}ms</span>`;
            }
        }

        // Start single test
        async function startSingleTest() {
            if (isTestingActive) return;

            isTestingActive = true;
            testStartTime = Date.now();

            const endpoint = document.getElementById('testEndpoint').value;

            if (endpoint === 'all') {
                logMessage('🚀 Starting single test on all endpoints', 'info');
                await testEndpoint('ultra_fast', 1);
                await testEndpoint('high_performance', 1);
                await testEndpoint('triple_storage', 1);
            } else {
                await testEndpoint(endpoint, 1);
            }

            isTestingActive = false;
            logMessage('✅ Single test completed', 'success');
        }

        // Start batch test
        async function startBatchTest() {
            if (isTestingActive) return;

            isTestingActive = true;
            testStartTime = Date.now();

            const endpoint = document.getElementById('testEndpoint').value;
            const testCount = parseInt(document.getElementById('testCount').value);
            const interval = parseInt(document.getElementById('testInterval').value);

            logMessage(`🚀 Starting batch test: ${testCount} tests on ${endpoint}`, 'info');

            document.getElementById('progressContainer').style.display = 'block';

            for (let i = 1; i <= testCount; i++) {
                if (endpoint === 'all') {
                    await testEndpoint('ultra_fast', i);
                    await testEndpoint('high_performance', i);
                    await testEndpoint('triple_storage', i);
                } else {
                    await testEndpoint(endpoint, i);
                }

                // Update progress
                const progress = (i / testCount) * 100;
                document.getElementById('progressBar').style.width = progress + '%';
                document.getElementById('progressText').textContent = `${i} / ${testCount} tests completed`;

                // Wait for interval (except on last iteration)
                if (i < testCount) {
                    await new Promise(resolve => setTimeout(resolve, interval));
                }
            }

            document.getElementById('progressContainer').style.display = 'none';
            isTestingActive = false;
            logMessage('✅ Batch test completed', 'success');
        }

        // Start stress test
        async function startStressTest() {
            if (isTestingActive) return;

            isTestingActive = true;
            testStartTime = Date.now();

            logMessage('⚡ Starting stress test: 50 rapid requests', 'warning');

            const promises = [];
            for (let i = 1; i <= 50; i++) {
                promises.push(testEndpoint('ultra_fast', i));
            }

            await Promise.all(promises);

            isTestingActive = false;
            logMessage('✅ Stress test completed', 'success');
        }

        // Start concurrent test
        async function startConcurrentTest() {
            if (isTestingActive) return;

            isTestingActive = true;
            testStartTime = Date.now();

            logMessage('🔄 Starting concurrent test: 10 simultaneous requests per endpoint', 'warning');

            const promises = [];

            // Test each endpoint with 10 concurrent requests
            for (const endpointName of Object.keys(endpoints)) {
                for (let i = 1; i <= 10; i++) {
                    promises.push(testEndpoint(endpointName, i));
                }
            }

            await Promise.all(promises);

            isTestingActive = false;
            logMessage('✅ Concurrent test completed', 'success');
        }

        // Clear results
        function clearResults() {
            testResults = [];
            testStartTime = 0;

            // Reset stats
            document.getElementById('avgResponseTime').textContent = '0';
            document.getElementById('fastestTime').textContent = '0';
            document.getElementById('slowestTime').textContent = '0';
            document.getElementById('successRate').textContent = '0%';
            document.getElementById('totalTests').textContent = '0';
            document.getElementById('testsPerSecond').textContent = '0';

            // Clear chart
            if (speedChart) {
                speedChart.data.labels = [];
                speedChart.data.datasets[0].data = [];
                speedChart.update();
            }

            // Clear monitor
            document.getElementById('realTimeMonitor').innerHTML = `
                <div class="log-entry log-info">🚀 Database Storage Speed Test initialized</div>
                <div class="log-entry log-info">📊 Ready to test storage performance</div>
            `;

            logMessage('🗑️ Results cleared', 'info');
        }

        // Verify database storage
        async function verifyDatabaseStorage() {
            logMessage('🔍 Starting database verification...', 'info');

            try {
                const response = await fetch('verify_database_storage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'verify_recent_storage',
                        limit: 10
                    })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    logMessage(`✅ Database verification complete: ${result.records_found} recent records found`, 'success');
                    logMessage(`📊 Latest record: Draw ${result.latest_record.draw_number}, Number ${result.latest_record.winning_number}`, 'info');

                    // Show detailed verification results
                    const verificationHtml = `
                        <div class="alert alert-success">
                            <h4>✅ Database Verification Results</h4>
                            <p><strong>Records Found:</strong> ${result.records_found}</p>
                            <p><strong>Latest Record:</strong> Draw #${result.latest_record.draw_number}, Number ${result.latest_record.winning_number}</p>
                            <p><strong>Storage Time:</strong> ${result.latest_record.timestamp}</p>
                            <p><strong>Database Response Time:</strong> ${result.query_time}ms</p>
                        </div>
                    `;

                    document.getElementById('testResultsTable').innerHTML = verificationHtml + document.getElementById('testResultsTable').innerHTML;
                } else {
                    logMessage(`❌ Database verification failed: ${result.message}`, 'error');
                }

            } catch (error) {
                logMessage(`💥 Database verification error: ${error.message}`, 'error');
            }
        }

        // Export results
        function exportResults() {
            if (testResults.length === 0) {
                alert('No test results to export');
                return;
            }

            const csv = [
                ['Test Number', 'Endpoint', 'Response Time (ms)', 'Success', 'Timestamp'].join(','),
                ...testResults.map(r => [
                    r.testNumber,
                    r.endpoint,
                    r.responseTime.toFixed(2),
                    r.success,
                    r.timestamp.toISOString()
                ].join(','))
            ].join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `storage_speed_test_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            logMessage('📥 Results exported to CSV', 'success');
        }

        // Start real-time monitoring of TV display results
        function startRealTimeMonitoring() {
            if (realTimeMonitoring) return;

            realTimeMonitoring = true;
            lastCheckedId = 0;

            document.getElementById('startMonitorBtn').style.display = 'none';
            document.getElementById('stopMonitorBtn').style.display = 'inline-block';
            document.getElementById('monitoringStatus').innerHTML = '<span style="color: #28a745;">Monitoring: ON 🟢</span>';

            logMessage('🔴 LIVE MONITORING: Started watching for TV display results', 'success');
            logMessage('🎮 Spin the roulette on tvdisplay/index.html to see real-time storage speed!', 'info');

            // Check for new records every 500ms
            monitoringInterval = setInterval(checkForNewRecords, 500);
        }

        // Stop real-time monitoring
        function stopRealTimeMonitoring() {
            if (!realTimeMonitoring) return;

            realTimeMonitoring = false;

            if (monitoringInterval) {
                clearInterval(monitoringInterval);
                monitoringInterval = null;
            }

            document.getElementById('startMonitorBtn').style.display = 'inline-block';
            document.getElementById('stopMonitorBtn').style.display = 'none';
            document.getElementById('monitoringStatus').innerHTML = '<span style="color: #dc3545;">Monitoring: OFF 🔴</span>';

            logMessage('⏹️ LIVE MONITORING: Stopped', 'warning');
        }

        // Check for new records in database
        async function checkForNewRecords() {
            try {
                const response = await fetch('verify_database_storage.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'get_new_records',
                        last_id: lastCheckedId
                    })
                });

                const result = await response.json();

                if (result.status === 'success' && result.new_records && result.new_records.length > 0) {
                    for (const record of result.new_records) {
                        const timeSinceCreated = new Date() - new Date(record.created_at);

                        logMessage(
                            `⚡ LIVE RESULT: Draw #${record.draw_number} → Number ${record.winning_number} (${record.color}) - Stored in ${timeSinceCreated}ms`,
                            'success'
                        );

                        // Update statistics with live data
                        const liveTestResult = {
                            endpoint: 'live_tv_display',
                            testNumber: record.id,
                            responseTime: timeSinceCreated,
                            success: true,
                            timestamp: new Date(record.created_at),
                            testData: {
                                winning_number: record.winning_number,
                                draw_number: record.draw_number
                            },
                            response: {
                                status: 'success',
                                live: true
                            }
                        };

                        testResults.push(liveTestResult);
                        updateStats();
                        updateChart();

                        lastCheckedId = Math.max(lastCheckedId, record.id);
                    }
                }

            } catch (error) {
                logMessage(`❌ MONITORING ERROR: ${error.message}`, 'error');
            }
        }

        // Handle instant save events from TV display
        function handleInstantSaveEvent(event) {
            const data = event.detail;
            logMessage(
                `⚡ INSTANT SAVE: Number ${data.winningNumber} → Saved in ${data.saveTime.toFixed(2)}ms (IMMEDIATE!)`,
                'success'
            );

            // Add to test results as instant save
            const instantTestResult = {
                endpoint: 'instant_tv_display',
                testNumber: Date.now(),
                responseTime: data.saveTime,
                success: true,
                timestamp: new Date(data.timestamp),
                testData: {
                    winning_number: data.winningNumber,
                    instant: true
                },
                response: {
                    status: 'success',
                    instant: true,
                    source: data.source
                }
            };

            testResults.push(instantTestResult);
            updateStats();
            updateChart();
        }

        // Handle TV display messages
        function handleTVDisplayMessage(event) {
            if (event.data && event.data.type === 'instantWinningNumberSaved') {
                handleInstantSaveEvent({ detail: event.data.data });
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initChart();

            // Set up event listeners for instant saves
            window.addEventListener('instantWinningNumberSaved', handleInstantSaveEvent);
            window.addEventListener('message', handleTVDisplayMessage);

            logMessage('🎉 Database Storage Speed Test ready!', 'success');
            logMessage('💡 TIP: Start monitoring to see real-time results from TV display', 'info');
            logMessage('⚡ INSTANT SAVE: Listening for immediate TV display saves', 'info');
        });
    </script>
</body>
</html>
