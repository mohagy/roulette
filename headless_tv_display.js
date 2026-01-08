#!/usr/bin/env node
/**
 * Headless TV Display Simulator (Node.js + Puppeteer)
 * 
 * This script runs the TV display in a headless browser using Puppeteer.
 * It provides an alternative to the Python solution.
 * 
 * Installation:
 *   npm install puppeteer
 * 
 * Usage:
 *   node headless_tv_display.js
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

class HeadlessTVDisplay {
    constructor(config = {}) {
        this.config = {
            url: 'http://localhost/slipp/tvdisplay/index.html',
            checkInterval: 30000, // 30 seconds
            restartInterval: 3600000, // 1 hour
            headless: true,
            viewport: { width: 1920, height: 1080 },
            userAgent: 'HeadlessTVDisplay/1.0',
            ...config
        };
        
        this.browser = null;
        this.page = null;
        this.startTime = new Date();
        this.lastRestart = new Date();
        this.running = false;
        
        // Setup logging
        this.logFile = 'headless_tv_display.log';
        
        // Setup signal handlers
        process.on('SIGINT', () => this.shutdown('SIGINT'));
        process.on('SIGTERM', () => this.shutdown('SIGTERM'));
    }
    
    log(level, message, data = null) {
        const timestamp = new Date().toISOString();
        const logMessage = `${timestamp} - ${level} - ${message}${data ? ' - ' + JSON.stringify(data) : ''}`;
        
        console.log(logMessage);
        fs.appendFileSync(this.logFile, logMessage + '\n');
    }
    
    async createBrowser() {
        try {
            this.log('INFO', 'Creating Puppeteer browser...');
            
            this.browser = await puppeteer.launch({
                headless: this.config.headless,
                args: [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--disable-web-security',
                    '--allow-running-insecure-content',
                    '--disable-extensions',
                    '--disable-plugins',
                    '--disable-images',
                    `--window-size=${this.config.viewport.width},${this.config.viewport.height}`
                ],
                defaultViewport: this.config.viewport
            });
            
            this.page = await this.browser.newPage();
            await this.page.setUserAgent(this.config.userAgent);
            
            // Enable console logging from the page
            this.page.on('console', msg => {
                this.log('PAGE', `Console ${msg.type()}: ${msg.text()}`);
            });
            
            // Handle page errors
            this.page.on('error', err => {
                this.log('ERROR', 'Page error:', err.message);
            });
            
            this.log('INFO', 'Puppeteer browser created successfully');
            return true;
            
        } catch (error) {
            this.log('ERROR', 'Failed to create browser:', error.message);
            return false;
        }
    }
    
    async loadTVDisplay() {
        try {
            this.log('INFO', `Loading TV display: ${this.config.url}`);
            
            await this.page.goto(this.config.url, {
                waitUntil: 'networkidle2',
                timeout: 60000
            });
            
            // Wait for JavaScript to initialize
            await this.page.waitForTimeout(10000);
            
            // Check if systems are initialized
            const systemCheck = await this.page.evaluate(() => {
                return {
                    title: document.title,
                    url: window.location.href,
                    hasDataPersistence: typeof window.DataPersistence !== 'undefined',
                    hasTabVisibilityManager: typeof window.TabVisibilityManager !== 'undefined',
                    hasDrawSync: typeof window.DrawSync !== 'undefined',
                    timestamp: new Date().toISOString()
                };
            });
            
            this.log('INFO', 'TV display loaded successfully', systemCheck);
            return true;
            
        } catch (error) {
            this.log('ERROR', 'Failed to load TV display:', error.message);
            return false;
        }
    }
    
    async checkPageHealth() {
        try {
            const health = await this.page.evaluate(() => {
                return {
                    url: window.location.href,
                    title: document.title,
                    readyState: document.readyState,
                    timestamp: new Date().toISOString(),
                    hasDataPersistence: typeof window.DataPersistence !== 'undefined',
                    hasTabVisibilityManager: typeof window.TabVisibilityManager !== 'undefined',
                    hasDrawSync: typeof window.DrawSync !== 'undefined'
                };
            });
            
            if (health.readyState === 'complete') {
                this.log('DEBUG', 'Page health check passed', health);
                return true;
            } else {
                this.log('WARN', 'Page health check failed', health);
                return false;
            }
            
        } catch (error) {
            this.log('ERROR', 'Health check error:', error.message);
            return false;
        }
    }
    
    async getDrawInfo() {
        try {
            const drawInfo = await this.page.evaluate(() => {
                return {
                    currentDrawNumber: window.currentDrawNumber || 'unknown',
                    rolledNumbersCount: window.rolledNumbersArray ? window.rolledNumbersArray.length : 0,
                    lastUpdate: new Date().toISOString()
                };
            });
            
            return drawInfo;
            
        } catch (error) {
            this.log('ERROR', 'Failed to get draw info:', error.message);
            return null;
        }
    }
    
    async restartBrowser() {
        this.log('INFO', 'Restarting browser...');
        
        if (this.browser) {
            try {
                await this.browser.close();
            } catch (error) {
                this.log('WARN', 'Error closing browser:', error.message);
            }
        }
        
        if (await this.createBrowser() && await this.loadTVDisplay()) {
            this.lastRestart = new Date();
            this.log('INFO', 'Browser restarted successfully');
            return true;
        } else {
            this.log('ERROR', 'Failed to restart browser');
            return false;
        }
    }
    
    async run() {
        this.log('INFO', 'Starting Headless TV Display Simulator');
        this.running = true;
        
        // Create initial browser
        if (!await this.createBrowser()) {
            this.log('ERROR', 'Failed to create initial browser');
            return false;
        }
        
        // Load TV display
        if (!await this.loadTVDisplay()) {
            this.log('ERROR', 'Failed to load initial TV display');
            return false;
        }
        
        // Main monitoring loop
        while (this.running) {
            try {
                // Check if it's time to restart
                const now = new Date();
                if (now - this.lastRestart > this.config.restartInterval) {
                    if (!await this.restartBrowser()) {
                        break;
                    }
                }
                
                // Check page health
                if (!await this.checkPageHealth()) {
                    this.log('WARN', 'Page health check failed, attempting to reload...');
                    if (!await this.loadTVDisplay()) {
                        this.log('ERROR', 'Failed to reload page, restarting browser...');
                        if (!await this.restartBrowser()) {
                            break;
                        }
                    }
                }
                
                // Get and log draw information
                const drawInfo = await this.getDrawInfo();
                if (drawInfo) {
                    this.log('INFO', 'Draw info', drawInfo);
                }
                
                // Wait before next check
                await new Promise(resolve => setTimeout(resolve, this.config.checkInterval));
                
            } catch (error) {
                this.log('ERROR', 'Unexpected error in main loop:', error.message);
                await new Promise(resolve => setTimeout(resolve, 10000));
            }
        }
        
        await this.stop();
        return true;
    }
    
    async stop() {
        this.log('INFO', 'Stopping Headless TV Display Simulator');
        this.running = false;
        
        if (this.browser) {
            try {
                await this.browser.close();
                this.log('INFO', 'Browser closed successfully');
            } catch (error) {
                this.log('ERROR', 'Error closing browser:', error.message);
            }
        }
        
        const uptime = new Date() - this.startTime;
        this.log('INFO', `Total uptime: ${Math.floor(uptime / 1000)} seconds`);
    }
    
    async shutdown(signal) {
        this.log('INFO', `Received ${signal}, shutting down gracefully...`);
        await this.stop();
        process.exit(0);
    }
}

// Main execution
async function main() {
    const config = {
        url: 'http://localhost/slipp/tvdisplay/index.html',
        checkInterval: 30000, // 30 seconds
        restartInterval: 3600000, // 1 hour
        headless: true, // Set to false for debugging
        viewport: { width: 1920, height: 1080 },
        userAgent: 'HeadlessTVDisplay/1.0'
    };
    
    const simulator = new HeadlessTVDisplay(config);
    
    try {
        await simulator.run();
    } catch (error) {
        console.error('Fatal error:', error);
        process.exit(1);
    }
}

if (require.main === module) {
    main();
}
