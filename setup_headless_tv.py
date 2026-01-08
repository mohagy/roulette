#!/usr/bin/env python3
"""
Roulette Headless TV Display Setup Script

This script automatically installs dependencies and sets up the headless TV display
for the roulette system. It handles all the configuration and testing.

Usage:
    python setup_headless_tv.py
"""

import os
import sys
import subprocess
import platform
import urllib.request
import json
from pathlib import Path

class RouletteHeadlessSetup:
    def __init__(self):
        self.system = platform.system().lower()
        self.python_exe = sys.executable
        self.script_dir = Path(__file__).parent.absolute()
        self.requirements = [
            'selenium>=4.0.0',
            'webdriver-manager>=3.8.0',
            'requests>=2.25.0'
        ]
        
    def print_header(self):
        """Print setup header"""
        print("=" * 60)
        print("🎯 ROULETTE HEADLESS TV DISPLAY SETUP")
        print("=" * 60)
        print(f"System: {platform.system()} {platform.release()}")
        print(f"Python: {sys.version}")
        print(f"Script directory: {self.script_dir}")
        print("=" * 60)
    
    def check_python_version(self):
        """Check if Python version is compatible"""
        print("🔍 Checking Python version...")
        
        if sys.version_info < (3, 7):
            print("❌ Python 3.7 or higher is required")
            print(f"   Current version: {sys.version}")
            return False
        
        print(f"✅ Python version OK: {sys.version.split()[0]}")
        return True
    
    def install_dependencies(self):
        """Install required Python packages"""
        print("📦 Installing dependencies...")
        
        for requirement in self.requirements:
            try:
                print(f"   Installing {requirement}...")
                subprocess.run([
                    self.python_exe, '-m', 'pip', 'install', requirement
                ], check=True, capture_output=True, text=True)
                print(f"   ✅ {requirement} installed successfully")
                
            except subprocess.CalledProcessError as e:
                print(f"   ❌ Failed to install {requirement}")
                print(f"   Error: {e.stderr}")
                return False
        
        print("✅ All dependencies installed successfully")
        return True
    
    def test_selenium_setup(self):
        """Test if Selenium and WebDriver are working"""
        print("🧪 Testing Selenium setup...")
        
        try:
            from selenium import webdriver
            from selenium.webdriver.chrome.options import Options
            from webdriver_manager.chrome import ChromeDriverManager
            from selenium.webdriver.chrome.service import Service
            
            print("   ✅ Selenium imports successful")
            
            # Test WebDriver creation
            print("   🔧 Testing Chrome WebDriver...")
            chrome_options = Options()
            chrome_options.add_argument('--headless')
            chrome_options.add_argument('--no-sandbox')
            chrome_options.add_argument('--disable-dev-shm-usage')
            
            service = Service(ChromeDriverManager().install())
            driver = webdriver.Chrome(service=service, options=chrome_options)
            
            # Test basic navigation
            driver.get('data:text/html,<html><body><h1>Test</h1></body></html>')
            title = driver.title
            driver.quit()
            
            print("   ✅ Chrome WebDriver test successful")
            return True
            
        except Exception as e:
            print(f"   ❌ Selenium test failed: {e}")
            return False
    
    def test_localhost_connection(self):
        """Test connection to localhost roulette system"""
        print("🌐 Testing localhost connection...")
        
        test_urls = [
            'http://localhost/slipp/',
            'http://localhost/slipp/tvdisplay/index.html',
            'http://localhost/slipp/api/get_next_draw_number.php'
        ]
        
        for url in test_urls:
            try:
                print(f"   Testing {url}...")
                with urllib.request.urlopen(url, timeout=10) as response:
                    if response.status == 200:
                        print(f"   ✅ {url} - OK")
                    else:
                        print(f"   ⚠️ {url} - HTTP {response.status}")
                        
            except Exception as e:
                print(f"   ❌ {url} - Failed: {e}")
                return False
        
        print("✅ Localhost connection test successful")
        return True
    
    def create_startup_script(self):
        """Create a convenient startup script"""
        print("📝 Creating startup script...")
        
        if self.system == 'windows':
            script_name = 'start_headless_tv.bat'
            script_content = f'''@echo off
echo Starting Roulette Headless TV Display...
cd /d "{self.script_dir}"
"{self.python_exe}" headless_tv_display.py
pause
'''
        else:
            script_name = 'start_headless_tv.sh'
            script_content = f'''#!/bin/bash
echo "Starting Roulette Headless TV Display..."
cd "{self.script_dir}"
"{self.python_exe}" headless_tv_display.py
'''
        
        script_path = self.script_dir / script_name
        
        try:
            with open(script_path, 'w') as f:
                f.write(script_content)
            
            if self.system != 'windows':
                os.chmod(script_path, 0o755)
            
            print(f"   ✅ Startup script created: {script_name}")
            return True
            
        except Exception as e:
            print(f"   ❌ Failed to create startup script: {e}")
            return False
    
    def create_config_file(self):
        """Create a configuration file for easy customization"""
        print("⚙️ Creating configuration file...")
        
        config = {
            "url": "http://localhost/slipp/tvdisplay/index.html",
            "check_interval": 15,
            "restart_interval": 7200,
            "headless": True,
            "window_size": [1920, 1080],
            "log_level": "INFO",
            "roulette_specific": {
                "monitor_draw_numbers": True,
                "detect_sequence_gaps": True,
                "validate_systems": True,
                "emergency_restart_on_gap": True
            }
        }
        
        config_path = self.script_dir / 'headless_tv_config.json'
        
        try:
            with open(config_path, 'w') as f:
                json.dump(config, f, indent=4)
            
            print(f"   ✅ Configuration file created: headless_tv_config.json")
            return True
            
        except Exception as e:
            print(f"   ❌ Failed to create config file: {e}")
            return False
    
    def run_setup(self):
        """Run the complete setup process"""
        self.print_header()
        
        steps = [
            ("Python Version Check", self.check_python_version),
            ("Install Dependencies", self.install_dependencies),
            ("Test Selenium Setup", self.test_selenium_setup),
            ("Test Localhost Connection", self.test_localhost_connection),
            ("Create Startup Script", self.create_startup_script),
            ("Create Config File", self.create_config_file)
        ]
        
        for step_name, step_func in steps:
            print(f"\n🔄 {step_name}...")
            if not step_func():
                print(f"\n❌ Setup failed at: {step_name}")
                return False
        
        self.print_success_message()
        return True
    
    def print_success_message(self):
        """Print success message with usage instructions"""
        print("\n" + "=" * 60)
        print("🎉 SETUP COMPLETED SUCCESSFULLY!")
        print("=" * 60)
        print("\n📋 NEXT STEPS:")
        print("\n1. Start the headless TV display:")
        
        if self.system == 'windows':
            print("   • Double-click: start_headless_tv.bat")
            print("   • Or run: python headless_tv_display.py")
        else:
            print("   • Run: ./start_headless_tv.sh")
            print("   • Or run: python headless_tv_display.py")
        
        print("\n2. Monitor the logs:")
        print("   • Check: headless_tv_display.log")
        print("   • Watch console output for status updates")
        
        print("\n3. Verify operation:")
        print("   • Look for 'TV display loaded successfully' message")
        print("   • Check draw number updates every 15 seconds")
        print("   • Close your browser - system continues running!")
        
        print("\n4. Configuration:")
        print("   • Edit: headless_tv_config.json")
        print("   • Restart the script to apply changes")
        
        print("\n🎯 BENEFITS:")
        print("   ✅ No more idle tab issues")
        print("   ✅ No more draw number skipping")
        print("   ✅ 24/7 continuous operation")
        print("   ✅ Automatic error recovery")
        print("   ✅ Sequence gap detection")
        
        print("\n" + "=" * 60)
        print("Your roulette system is now ready for headless operation!")
        print("=" * 60)

def main():
    """Main entry point"""
    setup = RouletteHeadlessSetup()
    
    try:
        success = setup.run_setup()
        if success:
            print("\n🚀 Ready to start headless TV display!")
        else:
            print("\n❌ Setup failed. Please check the errors above.")
            sys.exit(1)
            
    except KeyboardInterrupt:
        print("\n\n⚠️ Setup interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Unexpected error during setup: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
