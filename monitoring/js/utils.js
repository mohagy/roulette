/**
 * Utility Functions for Monitoring App
 */

// API Base URL - adjust for your server
const API_BASE = window.location.hostname === 'localhost' 
    ? 'http://localhost/slipp/api' 
    : 'https://roulette.aruka.app/slipp/api';

/**
 * Get color for roulette number
 */
function getColorForNumber(number) {
    if (number === 0) return 'green';
    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    return redNumbers.includes(parseInt(number)) ? 'red' : 'black';
}

/**
 * Calculate draw time from draw number
 */
function calculateDrawTime(drawNumber) {
    const minutes = (drawNumber - 1) * 3;
    const hours = Math.floor(minutes / 60) % 24;
    const mins = minutes % 60;
    return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0') + ':00';
}

/**
 * Format number badge HTML
 */
function formatNumberBadge(number, color) {
    return `<span class="number-badge number-${color}">${number}</span>`;
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

/**
 * Format date/time
 */
function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Get severity badge class
 */
function getSeverityClass(severity) {
    const classes = {
        'critical': 'badge-danger',
        'high': 'badge-warning',
        'medium': 'badge-info',
        'low': 'badge-secondary'
    };
    return classes[severity] || 'badge-secondary';
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Show loading indicator
 */
function showLoading(elementId) {
    const el = document.getElementById(elementId);
    if (el) {
        el.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</div>';
    }
}

/**
 * Show error message
 */
function showError(elementId, message) {
    const el = document.getElementById(elementId);
    if (el) {
        el.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }
}

/**
 * Fetch with error handling and timeout
 */
async function safeFetch(url, options = {}) {
    const timeoutMs = 10000; // 10 second timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
    
    try {
        const fetchUrl = url + (url.includes('?') ? '&' : '?') + '_cb=' + Date.now();
        console.log('Fetching:', fetchUrl);
        
        const response = await fetch(fetchUrl, {
            ...options,
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('Fetch success:', url, data.status || 'OK');
        return data;
    } catch (error) {
        clearTimeout(timeoutId);
        
        if (error.name === 'AbortError') {
            console.error('Fetch timeout:', url);
            throw new Error(`Request timeout after ${timeoutMs}ms`);
        } else {
            console.error('Fetch error:', url, error.message);
            throw error;
        }
    }
}

// Export
window.API_BASE = API_BASE;
window.getColorForNumber = getColorForNumber;
window.calculateDrawTime = calculateDrawTime;
window.formatNumberBadge = formatNumberBadge;
window.formatCurrency = formatCurrency;
window.formatDateTime = formatDateTime;
window.getSeverityClass = getSeverityClass;
window.debounce = debounce;
window.showLoading = showLoading;
window.showError = showError;
window.safeFetch = safeFetch;

