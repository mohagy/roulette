/**
 * Utility Functions
 * Formatting, date/time, DOM helpers, and validation functions
 */

const Utils = {
    /**
     * Format currency
     */
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount || 0);
    },

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return new Intl.NumberFormat('en-US').format(num || 0);
    },

    /**
     * Format time (HH:MM)
     */
    formatTime(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
    },

    /**
     * Format date (YYYY-MM-DD)
     */
    formatDate(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        return date.toISOString().split('T')[0];
    },

    /**
     * Format datetime
     */
    formatDateTime(date) {
        if (!date || date === '-') return '-';
        
        try {
            // Handle string dates
            if (typeof date === 'string') {
                // Try parsing as ISO string first
                let parsedDate = new Date(date);
                
                // If invalid, try other formats
                if (isNaN(parsedDate.getTime())) {
                    // Try as timestamp (if it's a number string)
                    if (!isNaN(date)) {
                        parsedDate = new Date(parseInt(date) * 1000); // Convert seconds to milliseconds if needed
                    }
                }
                
                // If still invalid, return original string
                if (isNaN(parsedDate.getTime())) {
                    return date;
                }
                
                date = parsedDate;
            }
            
            // Format the date
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        } catch (e) {
            console.error('Error formatting date:', date, e);
            return date.toString();
        }
    },

    /**
     * Get time ago string
     */
    getTimeAgo(date) {
        if (typeof date === 'string') {
            date = new Date(date);
        }
        const seconds = Math.floor((new Date() - date) / 1000);
        
        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
        return `${Math.floor(seconds / 86400)} days ago`;
    },

    /**
     * Calculate time until next draw
     */
    getTimeUntilNextDraw(currentTime, drawIntervalMinutes = 3) {
        const now = currentTime || new Date();
        const currentMinute = now.getMinutes();
        const nextDrawMinute = Math.ceil(currentMinute / drawIntervalMinutes) * drawIntervalMinutes;
        
        const nextDraw = new Date(now);
        nextDraw.setMinutes(nextDrawMinute, 0, 0);
        
        if (nextDraw <= now) {
            nextDraw.setHours(nextDraw.getHours() + 1);
        }
        
        const diff = nextDraw - now;
        const minutes = Math.floor(diff / 60000);
        const seconds = Math.floor((diff % 60000) / 1000);
        
        return {
            total: diff,
            minutes,
            seconds,
            formatted: `${minutes}:${seconds.toString().padStart(2, '0')}`
        };
    },

    /**
     * Get roulette number color (European Roulette)
     */
    getNumberColor(number) {
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        if (number === 0) return 'green';
        return redNumbers.includes(number) ? 'red' : 'black';
    },

    /**
     * Check if number is red
     */
    isRed(number) {
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        if (number === 0) return false;
        return redNumbers.includes(number);
    },

    /**
     * Check if number is black
     */
    isBlack(number) {
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        if (number === 0) return false;
        return !redNumbers.includes(number);
    },

    /**
     * Validate number (0-36)
     */
    validateNumber(num) {
        const n = parseInt(num);
        return !isNaN(n) && n >= 0 && n <= 36;
    },

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Show loading state
     */
    showLoading(element, show = true) {
        if (!element) return;
        if (show) {
            element.classList.add('loading');
            element.style.opacity = '0.6';
            element.style.pointerEvents = 'none';
        } else {
            element.classList.remove('loading');
            element.style.opacity = '1';
            element.style.pointerEvents = 'auto';
        }
    },

    /**
     * Show error message
     */
    showError(container, message) {
        if (!container) return;
        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                ${message}
            </div>
        `;
    },

    /**
     * Clear element content
     */
    clearElement(element) {
        if (element) {
            element.innerHTML = '';
        }
    },

    /**
     * Create element with attributes
     */
    createElement(tag, attributes = {}, textContent = '') {
        const element = document.createElement(tag);
        Object.entries(attributes).forEach(([key, value]) => {
            if (key === 'class') {
                element.className = value;
            } else if (key === 'data') {
                Object.entries(value).forEach(([dataKey, dataValue]) => {
                    element.setAttribute(`data-${dataKey}`, dataValue);
                });
            } else {
                element.setAttribute(key, value);
            }
        });
        if (textContent) {
            element.textContent = textContent;
        }
        return element;
    },

    /**
     * Get element safely
     */
    $(selector) {
        return document.querySelector(selector);
    },

    /**
     * Get all elements safely
     */
    $$(selector) {
        return document.querySelectorAll(selector);
    },

    /**
     * Scroll to element smoothly
     */
    scrollTo(element, offset = 0) {
        if (!element) return;
        const elementPosition = element.getBoundingClientRect().top;
        const offsetPosition = elementPosition + window.pageYOffset - offset;
        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });
    }
};

