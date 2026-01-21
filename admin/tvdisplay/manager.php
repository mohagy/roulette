<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}
$current_page = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Display Manager - Roulette Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .tvdisplay-container {
            background: white;
            border-radius: 0.35rem;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        }
        .setting-group {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e3e6f0;
        }
        .setting-group:last-child {
            border-bottom: none;
        }
        .setting-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 8px;
            display: block;
        }
        .setting-description {
            font-size: 0.875rem;
            color: #858796;
            margin-bottom: 10px;
        }
        .firebase-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .firebase-status.connected {
            background-color: #1cc88a;
            color: white;
        }
        .firebase-status.disconnected {
            background-color: #e74a3b;
            color: white;
        }
        .btn-tvdisplay {
            padding: 8px 20px;
            font-weight: 600;
            border-radius: 0.35rem;
        }
        .preview-box {
            background: #f8f9fc;
            border: 2px dashed #e3e6f0;
            border-radius: 0.35rem;
            padding: 20px;
            text-align: center;
            min-height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
       .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0.35rem;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            font-weight: 700;
        }
        .stat-card p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-tv text-primary"></i> TV Display Manager
                </h1>
                <div>
                    <a href="../../tvdisplay/" target="_blank" class="btn btn-primary btn-tvdisplay">
                        <i class="fas fa-external-link-alt"></i> Open TV Display
                    </a>
                </div>
            </div>

            <!-- Firebase Status -->
            <div class="row">
                <div class="col-md-12">
                    <div class="tvdisplay-container">
                        <h5 class="mb-3"><i class="fas fa-database"></i> Firebase Connection</h5>
                        <div id="firebaseStatus" class="firebase-status disconnected">
                            <i class="fas fa-spinner fa-spin"></i> Checking...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Display Controls -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="tvdisplay-container">
                        <h5 class="mb-4"><i class="fas fa-chart-bar"></i> Analytics Display</h5>
                        
                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="analytics_enabled" class="form-check-input me-2">
                                Show Analytics Panels
                            </label>
                            <p class="setting-description">Display hot/cold numbers, color distribution, and statistics</p>
                        </div>

                        <div class="setting-group">
                            <label class="setting-label">Analytics Update Interval (seconds)</label>
                            <p class="setting-description">How often to refresh analytics data</p>
                            <input type="number" id="analytics_interval" class="form-control" min="5" max="60" value="15">
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="tvdisplay-container">
                        <h5 class="mb-4"><i class="fas fa-clock"></i> Timer Settings</h5>
                        
                        <div class="setting-group">
                            <label class="setting-label">Timer Duration (seconds)</label>
                            <p class="setting-description">Countdown timer between spins (default: 180)</p>
                            <input type="number" id="timer_duration" class="form-control" min="10" max="300" value="180">
                        </div>

                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="auto_play_enabled" class="form-check-input me-2">
                                Auto-Play Enabled
                            </label>
                            <p class="setting-description">Automatically spin when timer reaches zero</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wheel & Animation -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="tvdisplay-container">
                        <h5 class="mb-4"><i class="fas fa-circle-notch"></i> Wheel Animation</h5>
                        
                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="wheel_animation_enabled" class="form-check-input me-2" checked>
                                Enable Wheel Animation
                            </label>
                            <p class="setting-description">Show spinning wheel animation during draws</p>
                        </div>

                        <div class="setting-group">
                            <label class="setting-label">Animation Duration (seconds)</label>
                            <p class="setting-description">How long the wheel spins (default: 5)</p>
                            <input type="number" id="animation_duration" class="form-control" min="3" max="10" value="5">
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="tvdisplay-container">
                        <h5 class="mb-4"><i class="fas fa-dice"></i> Force Numbers</h5>
                        
                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="force_numbers_enabled" class="form-check-input me-2">
                                Enable Force Numbers
                            </label>
                            <p class="setting-description">Allow forced numbers from preset schedule</p>
                        </div>

                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="auto_apply_forced" class="form-check-input me-2">
                                Auto-Apply Forced Numbers
                            </label>
                            <p class="setting-description">Automatically apply forced numbers when available</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="tvdisplay-container">
                        <h5 class="mb-4"><i class="fas fa-toggle-on"></i> Display Controls</h5>
                        
                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="spin_button_visible" class="form-check-input me-2" checked>
                                Show Spin Button
                            </label>
                            <p class="setting-description">Display manual spin button on TV</p>
                        </div>

                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="notifications_enabled" class="form-check-input me-2" checked>
                                Enable Notifications
                            </label>
                            <p class="setting-description">Show result notifications and alerts</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="tvdisplay-container">
                        <h5 class="mb-4"><i class="fas fa-user-shield"></i> Access Control</h5>
                        
                        <div class="setting-group">
                            <label class="setting-label">
                                <input type="checkbox" id="login_required" class="form-check-input me-2">
                                Require Login
                            </label>
                            <p class="setting-description">Require authentication to access TV display</p>
                        </div>

                        <div class="setting-group">
                            <label class="setting-label">Session Timeout (minutes)</label>
                            <p class="setting-description">Auto-logout after inactivity (0 = no timeout)</p>
                            <input type="number" id="session_timeout" class="form-control" min="0" max="120" value="0">
                        </div>
                        
                        <div class="setting-group">
                            <a href="../../tvdisplay/login.php" target="_blank" class="btn btn-info btn-sm">
                                <i class="fas fa-sign-in-alt"></i> Test Login Page
                            </a>
                            <a href="../../tvdisplay/logout.php" target="_blank" class="btn btn-warning btn-sm">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="row">
                <div class="col-12">
                    <div class="tvdisplay-container text-end">
                        <button id="saveSettings" class="btn btn-success btn-tvdisplay">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Helper function to get cookie value
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        }

        // Load settings from localStorage (client-side only, no database)
        function loadSettings() {
            // Load from localStorage or use defaults
            $('#analytics_enabled').prop('checked', localStorage.getItem('tv_analytics_enabled') !== 'false');
            $('#analytics_interval').val(localStorage.getItem('tv_analytics_interval') || '15');
            $('#timer_duration').val(localStorage.getItem('tv_timer_duration') || '180');
            $('#auto_play_enabled').prop('checked', localStorage.getItem('tv_auto_play') !== 'false');
            $('#wheel_animation_enabled').prop('checked', localStorage.getItem('tv_wheel_animation') !== 'false');
            $('#animation_duration').val(localStorage.getItem('tv_animation_duration') || '5');
            $('#force_numbers_enabled').prop('checked', localStorage.getItem('tv_force_numbers') === 'true');
            $('#auto_apply_forced').prop('checked', localStorage.getItem('tv_auto_apply_forced') === 'true');
            $('#spin_button_visible').prop('checked', localStorage.getItem('tv_spin_button') !== 'false');
            $('#notifications_enabled').prop('checked', localStorage.getItem('tv_notifications') !== 'false');
            
            // Load login settings from cookies first (they take precedence)
            const loginRequiredCookie = getCookie('tv_login_required');
            const sessionTimeoutCookie = getCookie('tv_session_timeout');
            
            if (loginRequiredCookie !== null) {
                $('#login_required').prop('checked', loginRequiredCookie === 'true');
            } else {
                // Fallback to localStorage if cookie not found
                $('#login_required').prop('checked', localStorage.getItem('tv_login_required') === 'true');
            }
            
            if (sessionTimeoutCookie !== null) {
                $('#session_timeout').val(sessionTimeoutCookie);
            } else {
                // Fallback to localStorage if cookie not found
                $('#session_timeout').val(localStorage.getItem('tv_session_timeout') || '0');
            }
        }

        // Check Firebase status
        function checkFirebaseStatus() {
            $.get('../../api/tvdisplay/firebase_status.php', function(response) {
                const status = $('#firebaseStatus');
                if (response.data.config_exists) {
                    status.removeClass('disconnected').addClass('connected');
                    status.html('<i class="fas fa-check-circle"></i> Connected');
                } else {
                    status.removeClass('connected').addClass('disconnected');
                    status.html('<i class="fas fa-times-circle"></i> Not Configured');
                }
            });
        }

        // Save settings to localStorage and cookies (for login settings)
        $('#saveSettings').click(function() {
            // Save display settings to localStorage
            localStorage.setItem('tv_analytics_enabled', $('#analytics_enabled').is(':checked'));
            localStorage.setItem('tv_analytics_interval', $('#analytics_interval').val());
            localStorage.setItem('tv_timer_duration', $('#timer_duration').val());
            localStorage.setItem('tv_auto_play', $('#auto_play_enabled').is(':checked'));
            localStorage.setItem('tv_wheel_animation', $('#wheel_animation_enabled').is(':checked'));
            localStorage.setItem('tv_animation_duration', $('#animation_duration').val());
            localStorage.setItem('tv_force_numbers', $('#force_numbers_enabled').is(':checked'));
            localStorage.setItem('tv_auto_apply_forced', $('#auto_apply_forced').is(':checked'));
            localStorage.setItem('tv_spin_button', $('#spin_button_visible').is(':checked'));
            localStorage.setItem('tv_notifications', $('#notifications_enabled').is(':checked'));
            
            // Save login settings to cookies (accessible by PHP)
            const loginRequired = $('#login_required').is(':checked');
            const sessionTimeout = $('#session_timeout').val();
            
            // Set cookies for login settings (expires in 1 year)
            // Path must be /slipp/tvdisplay/ so it's accessible from TV display
            document.cookie = `tv_login_required=${loginRequired}; path=/slipp/tvdisplay/; max-age=${365 * 24 * 60 * 60}`;
            document.cookie = `tv_session_timeout=${sessionTimeout}; path=/slipp/tvdisplay/; max-age=${365 * 24 * 60 * 60}`;
            
            // Also set with root path as fallback
            document.cookie = `tv_login_required=${loginRequired}; path=/; max-age=${365 * 24 * 60 * 60}`;
            document.cookie = `tv_session_timeout=${sessionTimeout}; path=/; max-age=${365 * 24 * 60 * 60}`;
            
            // Also save to localStorage for JavaScript access
            localStorage.setItem('tv_login_required', loginRequired);
            localStorage.setItem('tv_session_timeout', sessionTimeout);

            alert('✅ Display settings saved! Login settings will apply immediately. Other settings will apply when the TV display is refreshed.');
        });

        // Save settings to localStorage and cookies (for login settings)
        $('#saveSettings').click(function() {
            console.log('💾 Saving settings...');
            
            // Save display settings to localStorage
            localStorage.setItem('tv_analytics_enabled', $('#analytics_enabled').is(':checked'));
            localStorage.setItem('tv_analytics_interval', $('#analytics_interval').val());
            localStorage.setItem('tv_timer_duration', $('#timer_duration').val());
            localStorage.setItem('tv_auto_play', $('#auto_play_enabled').is(':checked'));
            localStorage.setItem('tv_wheel_animation', $('#wheel_animation_enabled').is(':checked'));
            localStorage.setItem('tv_animation_duration', $('#animation_duration').val());
            localStorage.setItem('tv_force_numbers', $('#force_numbers_enabled').is(':checked'));
            localStorage.setItem('tv_auto_apply_forced', $('#auto_apply_forced').is(':checked'));
            localStorage.setItem('tv_spin_button', $('#spin_button_visible').is(':checked'));
            localStorage.setItem('tv_notifications', $('#notifications_enabled').is(':checked'));
            
            // Save login settings to cookies (accessible by PHP)
            const loginRequired = $('#login_required').is(':checked');
            const sessionTimeout = $('#session_timeout').val();
            
            console.log('🔐 Login settings:', { loginRequired, sessionTimeout });
            
            // Set cookies for login settings (expires in 1 year)
            // Path must be /slipp/tvdisplay/ so it's accessible from TV display
            document.cookie = `tv_login_required=${loginRequired}; path=/slipp/tvdisplay/; max-age=${365 * 24 * 60 * 60}`;
            document.cookie = `tv_session_timeout=${sessionTimeout}; path=/slipp/tvdisplay/; max-age=${365 * 24 * 60 * 60}`;
            
            // Also set with root path as fallback
            document.cookie = `tv_login_required=${loginRequired}; path=/; max-age=${365 * 24 * 60 * 60}`;
            document.cookie = `tv_session_timeout=${sessionTimeout}; path=/; max-age=${365 * 24 * 60 * 60}`;
            
            // Also save to localStorage for JavaScript access
            localStorage.setItem('tv_login_required', loginRequired);
            localStorage.setItem('tv_session_timeout', sessionTimeout);
            
            console.log('✅ Settings saved! Cookies:', document.cookie);
            alert('✅ Display settings saved! Login settings will apply immediately. Other settings will apply when the TV display is refreshed.');
        });

        // Initialize
        $(document).ready(function() {
            console.log('🚀 TV Display Manager initialized');
            
            // Verify button exists
            const saveButton = $('#saveSettings');
            if (saveButton.length === 0) {
                console.error('❌ Save button not found!');
            } else {
                console.log('✅ Save button found');
            }
            
            loadSettings();
            checkFirebaseStatus();
            setInterval(checkFirebaseStatus, 30000); // Check every 30 seconds
            
            // Debug: Log current cookie values
            console.log('🍪 Current cookies:', document.cookie);
            console.log('🔐 Login required cookie:', getCookie('tv_login_required'));
            console.log('⏱️ Session timeout cookie:', getCookie('tv_session_timeout'));
        });
    </script>
</body>
</html>
