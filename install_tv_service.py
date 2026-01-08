#!/usr/bin/env python3
"""
TV Display Service Installer

This script helps you install the headless TV display as a system service
that automatically starts when your computer boots and runs continuously.

For Windows: Creates a Windows Service
For Linux: Creates a systemd service

Usage:
    python install_tv_service.py [install|uninstall|start|stop|status]
"""

import os
import sys
import platform
import subprocess
import shutil
from pathlib import Path

class TVServiceInstaller:
    def __init__(self):
        self.system = platform.system().lower()
        self.script_dir = Path(__file__).parent.absolute()
        self.service_name = "headless-tv-display"
        self.service_description = "Headless TV Display Simulator for Roulette System"
        
    def is_admin(self):
        """Check if running with admin privileges"""
        try:
            if self.system == "windows":
                import ctypes
                return ctypes.windll.shell32.IsUserAnAdmin()
            else:
                return os.geteuid() == 0
        except:
            return False
    
    def install_windows_service(self):
        """Install as Windows Service"""
        print("Installing Windows Service...")
        
        # Create service wrapper script
        wrapper_script = self.script_dir / "tv_service_wrapper.py"
        wrapper_content = f'''
import sys
import os
import servicemanager
import win32serviceutil
import win32service
import win32event
import subprocess
from pathlib import Path

class TVDisplayService(win32serviceutil.ServiceFramework):
    _svc_name_ = "{self.service_name}"
    _svc_display_name_ = "{self.service_description}"
    _svc_description_ = "{self.service_description}"
    
    def __init__(self, args):
        win32serviceutil.ServiceFramework.__init__(self, args)
        self.hWaitStop = win32event.CreateEvent(None, 0, 0, None)
        self.process = None
    
    def SvcStop(self):
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        if self.process:
            self.process.terminate()
        win32event.SetEvent(self.hWaitStop)
    
    def SvcDoRun(self):
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION_TYPE,
            servicemanager.PYS_SERVICE_STARTED,
            (self._svc_name_, '')
        )
        
        script_path = Path(__file__).parent / "headless_tv_display.py"
        self.process = subprocess.Popen([
            sys.executable, str(script_path)
        ], cwd=str(script_path.parent))
        
        win32event.WaitForSingleObject(self.hWaitStop, win32event.INFINITE)

if __name__ == '__main__':
    win32serviceutil.HandleCommandLine(TVDisplayService)
'''
        
        with open(wrapper_script, 'w') as f:
            f.write(wrapper_content)
        
        # Install the service
        try:
            subprocess.run([
                sys.executable, str(wrapper_script), "install"
            ], check=True)
            print("✅ Windows Service installed successfully!")
            print(f"Service name: {self.service_name}")
            print("Use 'python install_tv_service.py start' to start the service")
            return True
        except subprocess.CalledProcessError as e:
            print(f"❌ Failed to install Windows Service: {e}")
            return False
    
    def install_linux_service(self):
        """Install as Linux systemd service"""
        print("Installing Linux systemd service...")
        
        service_file = f"/etc/systemd/system/{self.service_name}.service"
        python_path = sys.executable
        script_path = self.script_dir / "headless_tv_display.py"
        
        service_content = f"""[Unit]
Description={self.service_description}
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory={self.script_dir}
ExecStart={python_path} {script_path}
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
"""
        
        try:
            # Write service file
            with open(service_file, 'w') as f:
                f.write(service_content)
            
            # Reload systemd and enable service
            subprocess.run(["systemctl", "daemon-reload"], check=True)
            subprocess.run(["systemctl", "enable", self.service_name], check=True)
            
            print("✅ Linux systemd service installed successfully!")
            print(f"Service name: {self.service_name}")
            print("Use 'python install_tv_service.py start' to start the service")
            return True
            
        except subprocess.CalledProcessError as e:
            print(f"❌ Failed to install Linux service: {e}")
            return False
        except PermissionError:
            print("❌ Permission denied. Please run with sudo.")
            return False
    
    def uninstall_service(self):
        """Uninstall the service"""
        print("Uninstalling service...")
        
        if self.system == "windows":
            wrapper_script = self.script_dir / "tv_service_wrapper.py"
            if wrapper_script.exists():
                try:
                    subprocess.run([
                        sys.executable, str(wrapper_script), "remove"
                    ], check=True)
                    wrapper_script.unlink()
                    print("✅ Windows Service uninstalled successfully!")
                    return True
                except subprocess.CalledProcessError as e:
                    print(f"❌ Failed to uninstall Windows Service: {e}")
                    return False
            else:
                print("❌ Service wrapper not found")
                return False
        else:
            service_file = f"/etc/systemd/system/{self.service_name}.service"
            try:
                subprocess.run(["systemctl", "stop", self.service_name], check=False)
                subprocess.run(["systemctl", "disable", self.service_name], check=False)
                if os.path.exists(service_file):
                    os.remove(service_file)
                subprocess.run(["systemctl", "daemon-reload"], check=True)
                print("✅ Linux service uninstalled successfully!")
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to uninstall Linux service: {e}")
                return False
    
    def start_service(self):
        """Start the service"""
        print("Starting service...")
        
        if self.system == "windows":
            try:
                subprocess.run(["sc", "start", self.service_name], check=True)
                print("✅ Service started successfully!")
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to start service: {e}")
                return False
        else:
            try:
                subprocess.run(["systemctl", "start", self.service_name], check=True)
                print("✅ Service started successfully!")
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to start service: {e}")
                return False
    
    def stop_service(self):
        """Stop the service"""
        print("Stopping service...")
        
        if self.system == "windows":
            try:
                subprocess.run(["sc", "stop", self.service_name], check=True)
                print("✅ Service stopped successfully!")
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to stop service: {e}")
                return False
        else:
            try:
                subprocess.run(["systemctl", "stop", self.service_name], check=True)
                print("✅ Service stopped successfully!")
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to stop service: {e}")
                return False
    
    def status_service(self):
        """Check service status"""
        print("Checking service status...")
        
        if self.system == "windows":
            try:
                result = subprocess.run(
                    ["sc", "query", self.service_name], 
                    capture_output=True, text=True
                )
                print(result.stdout)
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to check service status: {e}")
                return False
        else:
            try:
                subprocess.run(["systemctl", "status", self.service_name], check=False)
                return True
            except subprocess.CalledProcessError as e:
                print(f"❌ Failed to check service status: {e}")
                return False

def main():
    if len(sys.argv) < 2:
        print("Usage: python install_tv_service.py [install|uninstall|start|stop|status]")
        sys.exit(1)
    
    action = sys.argv[1].lower()
    installer = TVServiceInstaller()
    
    # Check for admin privileges for install/uninstall
    if action in ['install', 'uninstall'] and not installer.is_admin():
        print("❌ Admin privileges required for install/uninstall operations")
        if installer.system == "windows":
            print("Please run as Administrator")
        else:
            print("Please run with sudo")
        sys.exit(1)
    
    if action == "install":
        print(f"Installing TV Display Service on {installer.system.title()}...")
        
        # Check dependencies
        script_path = installer.script_dir / "headless_tv_display.py"
        if not script_path.exists():
            print(f"❌ Required script not found: {script_path}")
            sys.exit(1)
        
        if installer.system == "windows":
            success = installer.install_windows_service()
        else:
            success = installer.install_linux_service()
        
        if success:
            print("\\n🎉 Installation complete!")
            print("\\nNext steps:")
            print("1. Start the service: python install_tv_service.py start")
            print("2. Check status: python install_tv_service.py status")
            print("3. View logs: check headless_tv_display.log")
        
    elif action == "uninstall":
        installer.uninstall_service()
        
    elif action == "start":
        installer.start_service()
        
    elif action == "stop":
        installer.stop_service()
        
    elif action == "status":
        installer.status_service()
        
    else:
        print(f"❌ Unknown action: {action}")
        print("Valid actions: install, uninstall, start, stop, status")
        sys.exit(1)

if __name__ == "__main__":
    main()
