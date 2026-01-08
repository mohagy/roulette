#!/usr/bin/env python3
"""
Headless TV Display Simulator

This script runs the TV display (tvdisplay/index.html) in a headless browser
that continues running even when your main browser is closed. This eliminates
the idle tab issue and ensures continuous operation.

Requirements:
    pip install selenium webdriver-manager requests beautifulsoup4

Usage:
    python headless_tv_display.py
"""

import time
import logging
import signal
import sys
import json
import requests
from datetime import datetime, timedelta
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, WebDriverException
from webdriver_manager.chrome import ChromeDriverManager

class HeadlessTVDisplay:
    def __init__(self, config=None):
        """Initialize the headless TV display simulator for roulette system"""
        self.config = config or {
            'url': 'http://localhost/slipp/tvdisplay/index.html',
            'check_interval': 15,  # Check every 15 seconds for roulette system
            'restart_interval': 7200,  # Restart every 2 hours for stability
            'headless': True,
            'window_size': (1920, 1080),
            'user_agent': 'RouletteHeadlessTVDisplay/1.0',
            'log_level': logging.INFO,
            'roulette_specific': {
                'monitor_draw_numbers': True,
                'detect_sequence_gaps': True,
                'validate_systems': True,
                'emergency_restart_on_gap': True
            }
        }

        self.driver = None
        self.start_time = datetime.now()
        self.last_restart = datetime.now()
        self.running = False

        # Roulette-specific tracking
        self.last_draw_number = None
        self.draw_history = []
        self.sequence_gaps_detected = []
        self.system_status = {
            'TabVisibilityManager': False,
            'DrawNumberManager': False,
            'DataPersistence': False,
            'DrawSync': False
        }

        # Setup logging
        logging.basicConfig(
            level=self.config['log_level'],
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler('headless_tv_display.log'),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)

        # Setup signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)

    def signal_handler(self, signum, frame):
        """Handle shutdown signals gracefully"""
        self.logger.info(f"Received signal {signum}, shutting down gracefully...")
        self.stop()
        sys.exit(0)

    def create_driver(self):
        """Create and configure the Chrome WebDriver"""
        try:
            chrome_options = Options()

            if self.config['headless']:
                chrome_options.add_argument('--headless')

            # Essential Chrome options for headless operation
            chrome_options.add_argument('--no-sandbox')
            chrome_options.add_argument('--disable-dev-shm-usage')
            chrome_options.add_argument('--disable-gpu')
            chrome_options.add_argument('--disable-web-security')
            chrome_options.add_argument('--allow-running-insecure-content')
            chrome_options.add_argument('--disable-extensions')
            chrome_options.add_argument('--disable-plugins')
            chrome_options.add_argument('--disable-images')  # Faster loading
            chrome_options.add_argument(f'--window-size={self.config["window_size"][0]},{self.config["window_size"][1]}')
            chrome_options.add_argument(f'--user-agent={self.config["user_agent"]}')

            # Disable logging to reduce noise
            chrome_options.add_argument('--log-level=3')
            chrome_options.add_experimental_option('excludeSwitches', ['enable-logging'])
            chrome_options.add_experimental_option('useAutomationExtension', False)

            # Install and setup ChromeDriver automatically
            service = Service(ChromeDriverManager().install())

            driver = webdriver.Chrome(service=service, options=chrome_options)
            driver.set_page_load_timeout(60)
            driver.implicitly_wait(10)

            self.logger.info("Chrome WebDriver created successfully")
            return driver

        except Exception as e:
            self.logger.error(f"Failed to create WebDriver: {e}")
            return None

    def load_tv_display(self):
        """Load the TV display page"""
        try:
            self.logger.info(f"Loading TV display: {self.config['url']}")
            self.driver.get(self.config['url'])

            # Wait for the page to load completely
            WebDriverWait(self.driver, 30).until(
                EC.presence_of_element_located((By.TAG_NAME, "body"))
            )

            # Wait for JavaScript to initialize
            time.sleep(10)

            # Check if the page loaded successfully
            title = self.driver.title
            self.logger.info(f"TV display loaded successfully. Title: {title}")

            # Execute JavaScript to ensure systems are initialized
            self.driver.execute_script("""
                console.log('Headless TV Display: Page loaded and JavaScript executing');

                // Ensure all systems are initialized
                if (typeof window.DataPersistence !== 'undefined') {
                    console.log('DataPersistence system detected');
                }
                if (typeof window.TabVisibilityManager !== 'undefined') {
                    console.log('TabVisibilityManager system detected');
                }
                if (typeof window.DrawSync !== 'undefined') {
                    console.log('DrawSync system detected');
                }
            """)

            return True

        except TimeoutException:
            self.logger.error("Timeout waiting for TV display to load")
            return False
        except Exception as e:
            self.logger.error(f"Failed to load TV display: {e}")
            return False

    def check_page_health(self):
        """Check if the page is still responsive and functioning"""
        try:
            # Check if the page is still loaded
            current_url = self.driver.current_url
            if not current_url.startswith('http'):
                self.logger.warning("Page appears to be unloaded")
                return False

            # Execute a simple JavaScript check
            result = self.driver.execute_script("""
                return {
                    url: window.location.href,
                    title: document.title,
                    readyState: document.readyState,
                    timestamp: new Date().toISOString(),
                    hasDataPersistence: typeof window.DataPersistence !== 'undefined',
                    hasTabVisibilityManager: typeof window.TabVisibilityManager !== 'undefined',
                    hasDrawSync: typeof window.DrawSync !== 'undefined'
                };
            """)

            if result and result.get('readyState') == 'complete':
                self.logger.debug(f"Page health check passed: {result}")
                return True
            else:
                self.logger.warning(f"Page health check failed: {result}")
                return False

        except WebDriverException as e:
            self.logger.error(f"WebDriver error during health check: {e}")
            return False
        except Exception as e:
            self.logger.error(f"Unexpected error during health check: {e}")
            return False

    def get_draw_info(self):
        """Get current draw information from the page with roulette-specific details"""
        try:
            result = self.driver.execute_script("""
                return {
                    currentDrawNumber: window.currentDrawNumber || 'unknown',
                    rolledNumbersCount: window.rolledNumbersArray ? window.rolledNumbersArray.length : 0,
                    lastUpdate: new Date().toISOString(),
                    // Roulette-specific system status
                    systems: {
                        TabVisibilityManager: typeof window.TabVisibilityManager !== 'undefined',
                        DrawNumberManager: typeof window.DrawNumberManager !== 'undefined',
                        DataPersistence: typeof window.DataPersistence !== 'undefined',
                        DrawSync: typeof window.DrawSync !== 'undefined'
                    },
                    // Additional roulette data
                    allSpins: window.allSpins ? window.allSpins.slice(0, 5) : [],
                    analytics: window.rouletteAnalytics || null,
                    tabVisibilityState: window.TabVisibilityManager ?
                        window.TabVisibilityManager.isVisible() : 'unknown'
                };
            """)

            # Update system status tracking
            if result and 'systems' in result:
                self.system_status.update(result['systems'])

            return result
        except Exception as e:
            self.logger.error(f"Failed to get draw info: {e}")
            return None

    def validate_roulette_systems(self):
        """Validate that all roulette systems are properly loaded"""
        try:
            systems_check = self.driver.execute_script("""
                const systems = {
                    TabVisibilityManager: typeof window.TabVisibilityManager !== 'undefined',
                    DrawNumberManager: typeof window.DrawNumberManager !== 'undefined',
                    DataPersistence: typeof window.DataPersistence !== 'undefined',
                    DrawSync: typeof window.DrawSync !== 'undefined'
                };

                const details = {};

                // Check TabVisibilityManager
                if (systems.TabVisibilityManager) {
                    details.TabVisibilityManager = {
                        isVisible: window.TabVisibilityManager.isVisible(),
                        isCatchUpInProgress: window.TabVisibilityManager.isCatchUpInProgress()
                    };
                }

                // Check DataPersistence
                if (systems.DataPersistence) {
                    details.DataPersistence = {
                        isLoading: window.DataPersistence.state ? window.DataPersistence.state.isLoading : false,
                        lastLoadTime: window.DataPersistence.state ? window.DataPersistence.state.lastLoadTime : null
                    };
                }

                return {
                    systems: systems,
                    details: details,
                    allSystemsLoaded: Object.values(systems).every(loaded => loaded)
                };
            """)

            self.system_status.update(systems_check['systems'])

            if not systems_check['allSystemsLoaded']:
                missing_systems = [name for name, loaded in systems_check['systems'].items() if not loaded]
                self.logger.warning(f"Missing roulette systems: {missing_systems}")
                return False

            self.logger.info("All roulette systems validated successfully")
            return True

        except Exception as e:
            self.logger.error(f"Failed to validate roulette systems: {e}")
            return False

    def detect_draw_sequence_gaps(self, current_draw):
        """Detect if there are gaps in the draw sequence"""
        if not self.config['roulette_specific']['detect_sequence_gaps']:
            return False

        if self.last_draw_number is not None and current_draw != 'unknown':
            try:
                current_num = int(current_draw)
                last_num = int(self.last_draw_number)

                # Check for sequence gap
                if current_num > last_num + 1:
                    gap_size = current_num - last_num - 1
                    gap_info = {
                        'from': last_num,
                        'to': current_num,
                        'missing': list(range(last_num + 1, current_num)),
                        'gap_size': gap_size,
                        'timestamp': datetime.now().isoformat()
                    }

                    self.sequence_gaps_detected.append(gap_info)
                    self.logger.error(f"🚨 DRAW SEQUENCE GAP DETECTED: {gap_info}")

                    # Emergency restart if configured
                    if self.config['roulette_specific']['emergency_restart_on_gap']:
                        self.logger.error("Emergency restart triggered due to sequence gap")
                        return True

                # Update draw history
                self.draw_history.append({
                    'draw_number': current_num,
                    'timestamp': datetime.now().isoformat()
                })

                # Keep only last 20 draws in history
                if len(self.draw_history) > 20:
                    self.draw_history = self.draw_history[-20:]

                self.last_draw_number = current_draw

            except (ValueError, TypeError):
                self.logger.warning(f"Invalid draw number format: {current_draw}")

        return False

    def restart_driver(self):
        """Restart the WebDriver to prevent memory leaks"""
        self.logger.info("Restarting WebDriver...")

        if self.driver:
            try:
                self.driver.quit()
            except:
                pass

        self.driver = self.create_driver()
        if self.driver and self.load_tv_display():
            self.last_restart = datetime.now()
            self.logger.info("WebDriver restarted successfully")
            return True
        else:
            self.logger.error("Failed to restart WebDriver")
            return False

    def run(self):
        """Main execution loop"""
        self.logger.info("Starting Headless TV Display Simulator")
        self.running = True

        # Create initial driver
        self.driver = self.create_driver()
        if not self.driver:
            self.logger.error("Failed to create initial WebDriver")
            return False

        # Load the TV display
        if not self.load_tv_display():
            self.logger.error("Failed to load initial TV display")
            return False

        # Main monitoring loop
        while self.running:
            try:
                # Check if it's time to restart (prevent memory leaks)
                if datetime.now() - self.last_restart > timedelta(seconds=self.config['restart_interval']):
                    if not self.restart_driver():
                        break

                # Check page health
                if not self.check_page_health():
                    self.logger.warning("Page health check failed, attempting to reload...")
                    if not self.load_tv_display():
                        self.logger.error("Failed to reload page, restarting driver...")
                        if not self.restart_driver():
                            break

                # Validate roulette systems (every 5th check)
                if self.config['roulette_specific']['validate_systems']:
                    check_count = getattr(self, '_check_count', 0) + 1
                    self._check_count = check_count

                    if check_count % 5 == 0:  # Every 5th check
                        if not self.validate_roulette_systems():
                            self.logger.warning("Roulette systems validation failed, restarting...")
                            if not self.restart_driver():
                                break

                # Get and log current draw information
                draw_info = self.get_draw_info()
                if draw_info:
                    # Check for draw sequence gaps
                    if self.config['roulette_specific']['detect_sequence_gaps']:
                        current_draw = draw_info.get('currentDrawNumber', 'unknown')
                        if self.detect_draw_sequence_gaps(current_draw):
                            # Emergency restart triggered
                            if not self.restart_driver():
                                break
                            continue

                    # Log comprehensive draw information
                    self.logger.info(f"🎯 Roulette Status - Draw: {draw_info.get('currentDrawNumber', 'unknown')}, "
                                   f"Spins: {draw_info.get('rolledNumbersCount', 0)}, "
                                   f"Systems: {sum(self.system_status.values())}/4 loaded, "
                                   f"TabVisible: {draw_info.get('tabVisibilityState', 'unknown')}")

                    # Log detailed info at debug level
                    self.logger.debug(f"Full draw info: {draw_info}")

                # Wait before next check
                time.sleep(self.config['check_interval'])

            except KeyboardInterrupt:
                self.logger.info("Received keyboard interrupt")
                break
            except Exception as e:
                self.logger.error(f"Unexpected error in main loop: {e}")
                time.sleep(10)  # Wait before retrying

        self.stop()
        return True

    def stop(self):
        """Stop the simulator and cleanup with roulette-specific reporting"""
        self.logger.info("Stopping Roulette Headless TV Display Simulator")
        self.running = False

        # Log roulette-specific statistics
        uptime = datetime.now() - self.start_time
        self.logger.info(f"📊 ROULETTE SESSION SUMMARY:")
        self.logger.info(f"   Total uptime: {uptime}")
        self.logger.info(f"   Last draw number: {self.last_draw_number}")
        self.logger.info(f"   Draw history count: {len(self.draw_history)}")
        self.logger.info(f"   Sequence gaps detected: {len(self.sequence_gaps_detected)}")
        self.logger.info(f"   System status: {self.system_status}")

        if self.sequence_gaps_detected:
            self.logger.warning(f"🚨 GAPS DETECTED DURING SESSION: {self.sequence_gaps_detected}")

        if self.driver:
            try:
                self.driver.quit()
                self.logger.info("WebDriver closed successfully")
            except Exception as e:
                self.logger.error(f"Error closing WebDriver: {e}")

def main():
    """Main entry point for Roulette Headless TV Display"""
    config = {
        'url': 'http://localhost/slipp/tvdisplay/index.html',
        'check_interval': 15,  # Check every 15 seconds for roulette system
        'restart_interval': 7200,  # Restart every 2 hours for stability
        'headless': True,  # Set to False for debugging
        'window_size': (1920, 1080),
        'user_agent': 'RouletteHeadlessTVDisplay/1.0',
        'log_level': logging.INFO,
        'roulette_specific': {
            'monitor_draw_numbers': True,
            'detect_sequence_gaps': True,
            'validate_systems': True,
            'emergency_restart_on_gap': True
        }
    }

    simulator = HeadlessTVDisplay(config)

    try:
        simulator.run()
    except Exception as e:
        logging.error(f"Fatal error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
