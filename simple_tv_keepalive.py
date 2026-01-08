#!/usr/bin/env python3
"""
Simple TV Display Keep-Alive

A lightweight alternative that periodically pings the TV display endpoints
to keep the system active without running a full browser.

This is useful if you just need to keep the backend systems active.

Requirements:
    pip install requests

Usage:
    python simple_tv_keepalive.py
"""

import time
import requests
import logging
import signal
import sys
import json
from datetime import datetime, timedelta
from urllib.parse import urljoin

class TVKeepAlive:
    def __init__(self, config=None):
        """Initialize the TV keep-alive system"""
        self.config = config or {
            'base_url': 'http://localhost/slipp/',
            'endpoints': [
                'api/get_next_draw_number.php',
                'api/safe_draw_advance.php?action=info',
                'api/tv_sync.php',
                'php/get_draw_history.php'
            ],
            'ping_interval': 30,  # Ping every 30 seconds
            'timeout': 10,  # Request timeout
            'log_level': logging.INFO
        }
        
        self.running = False
        self.start_time = datetime.now()
        self.stats = {
            'total_requests': 0,
            'successful_requests': 0,
            'failed_requests': 0,
            'last_success': None,
            'last_failure': None
        }
        
        # Setup logging
        logging.basicConfig(
            level=self.config['log_level'],
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler('tv_keepalive.log'),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)
        
        # Setup signal handlers
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
    
    def signal_handler(self, signum, frame):
        """Handle shutdown signals gracefully"""
        self.logger.info(f"Received signal {signum}, shutting down gracefully...")
        self.stop()
        sys.exit(0)
    
    def ping_endpoint(self, endpoint):
        """Ping a specific endpoint"""
        url = urljoin(self.config['base_url'], endpoint)
        
        try:
            self.logger.debug(f"Pinging: {url}")
            
            response = requests.get(
                url,
                timeout=self.config['timeout'],
                headers={
                    'User-Agent': 'TVKeepAlive/1.0',
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            )
            
            self.stats['total_requests'] += 1
            
            if response.status_code == 200:
                self.stats['successful_requests'] += 1
                self.stats['last_success'] = datetime.now()
                
                # Try to parse JSON response for additional info
                try:
                    data = response.json()
                    self.logger.debug(f"Response from {endpoint}: {data}")
                    return True, data
                except:
                    self.logger.debug(f"Non-JSON response from {endpoint}: {response.text[:100]}")
                    return True, response.text[:100]
            else:
                self.stats['failed_requests'] += 1
                self.stats['last_failure'] = datetime.now()
                self.logger.warning(f"HTTP {response.status_code} from {endpoint}")
                return False, f"HTTP {response.status_code}"
                
        except requests.exceptions.Timeout:
            self.stats['failed_requests'] += 1
            self.stats['last_failure'] = datetime.now()
            self.logger.error(f"Timeout pinging {endpoint}")
            return False, "Timeout"
            
        except requests.exceptions.ConnectionError:
            self.stats['failed_requests'] += 1
            self.stats['last_failure'] = datetime.now()
            self.logger.error(f"Connection error pinging {endpoint}")
            return False, "Connection Error"
            
        except Exception as e:
            self.stats['failed_requests'] += 1
            self.stats['last_failure'] = datetime.now()
            self.logger.error(f"Unexpected error pinging {endpoint}: {e}")
            return False, str(e)
    
    def ping_all_endpoints(self):
        """Ping all configured endpoints"""
        results = {}
        
        for endpoint in self.config['endpoints']:
            success, data = self.ping_endpoint(endpoint)
            results[endpoint] = {
                'success': success,
                'data': data,
                'timestamp': datetime.now().isoformat()
            }
        
        return results
    
    def get_system_status(self):
        """Get overall system status"""
        uptime = datetime.now() - self.start_time
        success_rate = (self.stats['successful_requests'] / max(self.stats['total_requests'], 1)) * 100
        
        return {
            'uptime_seconds': int(uptime.total_seconds()),
            'uptime_formatted': str(uptime),
            'total_requests': self.stats['total_requests'],
            'successful_requests': self.stats['successful_requests'],
            'failed_requests': self.stats['failed_requests'],
            'success_rate': round(success_rate, 2),
            'last_success': self.stats['last_success'].isoformat() if self.stats['last_success'] else None,
            'last_failure': self.stats['last_failure'].isoformat() if self.stats['last_failure'] else None
        }
    
    def run(self):
        """Main execution loop"""
        self.logger.info("Starting TV Keep-Alive System")
        self.logger.info(f"Base URL: {self.config['base_url']}")
        self.logger.info(f"Endpoints: {self.config['endpoints']}")
        self.logger.info(f"Ping interval: {self.config['ping_interval']} seconds")
        
        self.running = True
        
        while self.running:
            try:
                # Ping all endpoints
                results = self.ping_all_endpoints()
                
                # Log summary
                successful_endpoints = sum(1 for r in results.values() if r['success'])
                total_endpoints = len(results)
                
                self.logger.info(f"Ping cycle complete: {successful_endpoints}/{total_endpoints} endpoints successful")
                
                # Log detailed results at debug level
                for endpoint, result in results.items():
                    if result['success']:
                        self.logger.debug(f"✅ {endpoint}: OK")
                    else:
                        self.logger.warning(f"❌ {endpoint}: {result['data']}")
                
                # Log system status periodically
                if self.stats['total_requests'] % 10 == 0:  # Every 10 cycles
                    status = self.get_system_status()
                    self.logger.info(f"System status: {status}")
                
                # Wait before next cycle
                time.sleep(self.config['ping_interval'])
                
            except KeyboardInterrupt:
                self.logger.info("Received keyboard interrupt")
                break
            except Exception as e:
                self.logger.error(f"Unexpected error in main loop: {e}")
                time.sleep(10)  # Wait before retrying
        
        self.stop()
    
    def stop(self):
        """Stop the keep-alive system"""
        self.logger.info("Stopping TV Keep-Alive System")
        self.running = False
        
        # Log final statistics
        final_status = self.get_system_status()
        self.logger.info(f"Final statistics: {final_status}")

def main():
    """Main entry point"""
    config = {
        'base_url': 'http://localhost/slipp/',
        'endpoints': [
            'api/get_next_draw_number.php',
            'api/safe_draw_advance.php?action=info',
            'api/tv_sync.php',
            'php/get_draw_history.php',
            'api/cashier_draw_sync.php'
        ],
        'ping_interval': 30,  # Ping every 30 seconds
        'timeout': 10,  # Request timeout
        'log_level': logging.INFO
    }
    
    keepalive = TVKeepAlive(config)
    
    try:
        keepalive.run()
    except Exception as e:
        logging.error(f"Fatal error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
