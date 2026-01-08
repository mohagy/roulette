/**
 * High-Performance Storage Client for TV Display
 *
 * Optimized for maximum speed with:
 * - Asynchronous operations
 * - Connection pooling simulation
 * - Retry mechanisms
 * - Performance monitoring
 * - Batch processing capabilities
 */

class HighPerformanceStorage {
    constructor() {
        this.apiEndpoints = {
            ultraFast: '../php/ultra_fast_storage_api.php',
            highPerf: '../php/high_performance_storage_api.php',
            fallback: '../php/triple_storage_api.php'
        };

        this.performanceMetrics = {
            totalRequests: 0,
            successfulRequests: 0,
            averageResponseTime: 0,
            fastestResponse: Infinity,
            slowestResponse: 0
        };

        this.requestQueue = [];
        this.isProcessingQueue = false;
        this.maxRetries = 3;
        this.retryDelay = 100; // milliseconds

        console.log('🚀 High-Performance Storage initialized');
    }

    /**
     * Save winning number with maximum speed
     */
    async saveWinningNumber(winningNumber, drawNumber, options = {}) {
        const startTime = performance.now();

        const data = {
            winning_number: parseInt(winningNumber),
            draw_number: parseInt(drawNumber),
            timestamp: options.timestamp || new Date().toISOString().slice(0, 19).replace('T', ' ')
        };

        console.log('⚡ FAST SAVE: Starting', data);

        try {
            // Try ultra-fast endpoint first
            const result = await this.sendRequest(this.apiEndpoints.ultraFast, data, startTime);

            if (result.success) {
                console.log('✅ ULTRA-FAST SAVE: Success in', result.responseTime, 'ms');

                // Notify any monitoring systems
                this.notifyStorageComplete(data, result);

                return result;
            }

            // Fallback to high-performance endpoint
            console.log('⚠️ Ultra-fast failed, trying high-performance endpoint');
            const fallbackResult = await this.sendRequest(this.apiEndpoints.highPerf, data, startTime);

            if (fallbackResult.success) {
                console.log('✅ HIGH-PERF SAVE: Success in', fallbackResult.responseTime, 'ms');
                return fallbackResult;
            }

            throw new Error('Both optimized endpoints failed');

        } catch (error) {
            console.error('❌ FAST SAVE: Failed', error);

            // Queue for retry
            this.queueForRetry(data, options);

            return {
                success: false,
                error: error.message,
                responseTime: performance.now() - startTime
            };
        }
    }

    /**
     * Send request with performance monitoring
     */
    async sendRequest(endpoint, data, startTime) {
        const requestStart = performance.now();

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const responseTime = performance.now() - requestStart;
            const totalTime = performance.now() - startTime;

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();

            // Update performance metrics
            this.updatePerformanceMetrics(responseTime, true);

            return {
                success: result.status === 'success',
                data: result,
                responseTime: responseTime,
                totalTime: totalTime
            };

        } catch (error) {
            const responseTime = performance.now() - requestStart;
            this.updatePerformanceMetrics(responseTime, false);

            throw error;
        }
    }

    /**
     * Queue failed requests for retry
     */
    queueForRetry(data, options = {}) {
        const retryItem = {
            data: data,
            options: options,
            attempts: 0,
            maxRetries: this.maxRetries,
            queuedAt: Date.now()
        };

        this.requestQueue.push(retryItem);
        console.log('📋 QUEUE: Added item for retry', retryItem);

        // Process queue if not already processing
        if (!this.isProcessingQueue) {
            this.processQueue();
        }
    }

    /**
     * Process retry queue
     */
    async processQueue() {
        if (this.requestQueue.length === 0) {
            this.isProcessingQueue = false;
            return;
        }

        this.isProcessingQueue = true;
        console.log('🔄 QUEUE: Processing', this.requestQueue.length, 'items');

        while (this.requestQueue.length > 0) {
            const item = this.requestQueue.shift();

            if (item.attempts >= item.maxRetries) {
                console.error('❌ QUEUE: Max retries exceeded for item', item);
                continue;
            }

            item.attempts++;

            try {
                console.log(`🔄 QUEUE: Retry attempt ${item.attempts}/${item.maxRetries}`);

                const result = await this.saveWinningNumber(
                    item.data.winning_number,
                    item.data.draw_number,
                    item.options
                );

                if (result.success) {
                    console.log('✅ QUEUE: Retry successful');
                } else {
                    // Re-queue if not at max retries
                    if (item.attempts < item.maxRetries) {
                        this.requestQueue.push(item);
                    }
                }

            } catch (error) {
                console.error('❌ QUEUE: Retry failed', error);

                // Re-queue if not at max retries
                if (item.attempts < item.maxRetries) {
                    this.requestQueue.push(item);
                }
            }

            // Small delay between retries
            await new Promise(resolve => setTimeout(resolve, this.retryDelay));
        }

        this.isProcessingQueue = false;
        console.log('✅ QUEUE: Processing complete');
    }

    /**
     * Update performance metrics
     */
    updatePerformanceMetrics(responseTime, success) {
        this.performanceMetrics.totalRequests++;

        if (success) {
            this.performanceMetrics.successfulRequests++;
        }

        // Update response time metrics
        this.performanceMetrics.fastestResponse = Math.min(this.performanceMetrics.fastestResponse, responseTime);
        this.performanceMetrics.slowestResponse = Math.max(this.performanceMetrics.slowestResponse, responseTime);

        // Calculate rolling average
        const totalSuccessTime = this.performanceMetrics.averageResponseTime * (this.performanceMetrics.successfulRequests - 1);
        this.performanceMetrics.averageResponseTime = (totalSuccessTime + responseTime) / this.performanceMetrics.successfulRequests;
    }

    /**
     * Get performance statistics
     */
    getPerformanceStats() {
        const stats = {
            ...this.performanceMetrics,
            successRate: (this.performanceMetrics.successfulRequests / this.performanceMetrics.totalRequests * 100).toFixed(2) + '%',
            queueLength: this.requestQueue.length,
            isProcessingQueue: this.isProcessingQueue
        };

        console.log('📊 PERFORMANCE STATS:', stats);
        return stats;
    }

    /**
     * Batch save multiple winning numbers
     */
    async batchSave(winningNumbers) {
        console.log('📦 BATCH SAVE: Starting batch of', winningNumbers.length, 'items');

        const promises = winningNumbers.map(item =>
            this.saveWinningNumber(item.winning_number, item.draw_number, item.options)
        );

        const results = await Promise.allSettled(promises);

        const successful = results.filter(r => r.status === 'fulfilled' && r.value.success).length;
        const failed = results.length - successful;

        console.log(`📦 BATCH SAVE: Complete - ${successful} successful, ${failed} failed`);

        return {
            total: results.length,
            successful: successful,
            failed: failed,
            results: results
        };
    }

    /**
     * Notify monitoring systems of storage completion
     */
    notifyStorageComplete(data, result) {
        try {
            // Create custom event for real-time monitoring
            const storageEvent = new CustomEvent('winningNumberStored', {
                detail: {
                    winningNumber: data.winning_number,
                    drawNumber: data.draw_number,
                    timestamp: data.timestamp,
                    responseTime: result.responseTime,
                    totalTime: result.totalTime,
                    endpoint: 'ultra_fast',
                    success: result.success,
                    storedAt: new Date().toISOString()
                }
            });

            // Dispatch event for any listeners
            window.dispatchEvent(storageEvent);

            // Also try to notify parent window if in iframe
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'winningNumberStored',
                    data: storageEvent.detail
                }, '*');
            }

            console.log('📡 NOTIFICATION: Storage event dispatched', storageEvent.detail);

        } catch (error) {
            console.warn('⚠️ NOTIFICATION: Failed to dispatch storage event', error);
        }
    }

    /**
     * Test performance with multiple requests
     */
    async performanceTest(iterations = 10) {
        console.log('🧪 PERFORMANCE TEST: Starting', iterations, 'iterations');

        const testData = [];
        for (let i = 0; i < iterations; i++) {
            testData.push({
                winning_number: Math.floor(Math.random() * 37),
                draw_number: 9999 + i,
                options: { test: true }
            });
        }

        const startTime = performance.now();
        const results = await this.batchSave(testData);
        const totalTime = performance.now() - startTime;

        console.log('🧪 PERFORMANCE TEST: Complete in', totalTime.toFixed(2), 'ms');
        console.log('📊 Test Results:', results);

        return {
            ...results,
            totalTime: totalTime,
            averageTimePerRequest: totalTime / iterations
        };
    }
}

// Create global instance
window.HighPerformanceStorage = new HighPerformanceStorage();

// Enhanced integration with existing TV display
if (typeof window.TripleStorage !== 'undefined') {
    // Override existing save method with high-performance version
    const originalSaveSpin = window.TripleStorage.saveSpin;

    window.TripleStorage.saveSpin = async function(winningNumber, drawNumber, options = {}) {
        console.log('🔄 OVERRIDE: Using high-performance storage');

        // Use high-performance storage
        const result = await window.HighPerformanceStorage.saveWinningNumber(winningNumber, drawNumber, options);

        if (!result.success) {
            console.log('⚠️ FALLBACK: Using original triple storage');
            return originalSaveSpin.call(this, winningNumber, drawNumber, options);
        }

        return result;
    };
}

console.log('🚀 High-Performance Storage client loaded and ready');
