/**
 * API Client
 * Centralized API communication with error handling and retry logic
 */

class APIClient {
    constructor() {
        this.retryAttempts = 3;
        this.retryDelay = 1000;
        this.timeout = 10000;
        this.enableCacheBusting = true;
    }

    /**
     * Generate cache-busting parameter
     */
    getCacheBuster() {
        return this.enableCacheBusting ? `&_cb=${Date.now()}` : '';
    }

    /**
     * Make API request with retry logic
     */
    async request(url, options = {}) {
        // Determine if this is a GET request
        const isGetRequest = !options.method || options.method.toUpperCase() === 'GET';
        
        // Only add cache-buster to URL for GET requests
        // For POST requests, cache-buster should be in the body if needed
        let fullUrl = url;
        if (isGetRequest && this.enableCacheBusting) {
            const cacheBuster = this.getCacheBuster();
            fullUrl = url + (url.includes('?') ? cacheBuster : `?${cacheBuster.substring(1)}`);
        }
        
        const defaultOptions = {
            method: 'GET',
            headers: {},
            timeout: this.timeout
        };

        // Only set Content-Type for JSON, not for FormData
        if (options.body && !(options.body instanceof FormData) && !(options.body instanceof URLSearchParams)) {
            defaultOptions.headers['Content-Type'] = 'application/json';
        }

        const requestOptions = { ...defaultOptions, ...options };

        let lastError;
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                console.log(`📡 API Request (attempt ${attempt}/${this.retryAttempts}): ${fullUrl}`);
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), this.timeout);
                
                const response = await fetch(fullUrl, {
                    ...requestOptions,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);

                if (!response.ok) {
                    const errorText = await response.text().catch(() => 'Unable to read error response');
                    throw new Error(`HTTP ${response.status}: ${errorText.substring(0, 200)}`);
                }

                const data = await response.json();
                console.log(`✅ API Response: ${fullUrl}`, data);
                return data;
            } catch (error) {
                lastError = error;
                console.error(`❌ API Request failed (attempt ${attempt}/${this.retryAttempts}):`, error);
                
                if (attempt < this.retryAttempts) {
                    const delay = this.retryDelay * attempt;
                    console.log(`⏳ Retrying in ${delay}ms...`);
                    await new Promise(resolve => setTimeout(resolve, delay));
                }
            }
        }

        throw new Error(`API request failed after ${this.retryAttempts} attempts: ${lastError.message}`);
    }

    /**
     * GET request
     */
    async get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.request(fullUrl);
    }

    /**
     * POST request
     */
    async post(url, data = {}) {
        console.log('📤 POST request to:', url, 'with data:', data);
        
        // Convert data to URLSearchParams for PHP $_POST compatibility
        // PHP $_POST expects application/x-www-form-urlencoded format
        const urlParams = new URLSearchParams();
        Object.keys(data).forEach(key => {
            const value = data[key];
            urlParams.append(key, value);
            console.log(`📤 POST parameter: ${key} = ${value} (type: ${typeof value})`);
        });
        
        const bodyString = urlParams.toString();
        console.log('📤 POST body string:', bodyString);
        
        // Use URLSearchParams for simple POST data (PHP $_POST expects this format)
        return this.request(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: bodyString
        });
    }

    /**
     * Fetch upcoming draws
     */
    async getUpcomingDraws(count = 10) {
        return this.get('../../api/upcoming_draws_stats.php', { count });
    }

    /**
     * Fetch bet distribution for a draw
     */
    async getBetDistribution(drawNumber) {
        return this.get('../../php/get_bet_distribution.php', { draw: drawNumber });
    }

    /**
     * Fetch draw info
     */
    async getDrawInfo() {
        return this.get('../../api/draw_info.php');
    }

    /**
     * Toggle manual/auto mode
     * @param {string} mode - The mode to set: 'automatic' or 'manual'
     */
    async toggleMode(mode) {
        if (!mode) {
            throw new Error('Mode parameter is required');
        }
        console.log('🔄 toggleMode called with mode:', mode);
        const result = await this.post('../../api/toggle_mode.php', {
            mode: mode
        });
        console.log('✅ toggleMode result:', result);
        return result;
    }

    /**
     * Set winning number
     */
    async setWinningNumber(drawNumber, winningNumber, keepAutoMode = false) {
        return this.post('../../api/set_winning_number.php', {
            draw_number: drawNumber,
            winning_number: winningNumber,
            keep_auto_mode: keepAutoMode
        });
    }

    /**
     * Save preset schedule
     */
    async savePresetSchedule(scheduleData) {
        return this.post('../../api/save_preset_schedule.php', scheduleData);
    }

    /**
     * Load preset schedule
     */
    async loadPresetSchedule(date = null, drawNumber = null) {
        const params = {};
        if (date) params.date = date;
        if (drawNumber) params.draw_number = drawNumber;
        return this.get('../../api/load_preset_schedule.php', params);
    }

    /**
     * Check preset schedule status
     */
    async checkPresetSchedule(date = null) {
        const params = {};
        if (date) params.date = date;
        return this.get('../../api/check_preset_schedule.php', params);
    }

    /**
     * Get forced number
     * @param {number|null} drawNumber - Optional specific draw number to check
     */
    async getForcedNumber(drawNumber = null) {
        const params = {};
        if (drawNumber !== null && drawNumber !== undefined) {
            params.draw_number = drawNumber;
        }
        return this.get('../../api/direct_forced_number.php', params);
    }

    /**
     * Get current preset number
     */
    async getCurrentPreset(drawNumber) {
        return this.get('../../api/get_current_preset.php', { draw_number: drawNumber });
    }

    /**
     * Get number analytics (uncalled numbers, lowest/highest payout)
     */
    async getNumberAnalytics(drawNumber = null) {
        const params = {};
        if (drawNumber !== null && drawNumber !== undefined) {
            params.draw_number = drawNumber;
        }
        return this.get('../../api/number_analytics.php', params);
    }

    /**
     * Get slip analytics (betting slips and which would win for a test number)
     */
    async getSlipAnalytics(drawNumber, testNumber = null) {
        const params = { draw_number: drawNumber };
        if (testNumber !== null && testNumber !== undefined) {
            params.test_number = testNumber;
        }
        return this.get('../../api/slip_analytics.php', params);
    }
}

// Create global instance
const apiClient = new APIClient();

