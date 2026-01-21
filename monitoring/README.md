# Casino Monitoring & Audit Dashboard

A comprehensive HTML/JavaScript monitoring application for casino operations, deployable on GitHub Pages with Firebase Firestore for real-time updates.

## Features

### 📊 Dashboard Tab
- **Live Alerts**: Real-time alerts from Firebase (critical, high, medium, low severity)
- **Today's Stats**: Total alerts, critical alerts, payouts, shops monitoring
- **Shop Performance**: Real-time shop metrics with payout ratios

### 📈 Analytics Tab
- **Server Time & Current Draw**: Live server time and draw number
- **Analytics History**: Last 20 draws with winning numbers, colors, sources
- **Preset Schedule**: Active preset schedule with pattern information
- **Forced Numbers**: Upcoming forced numbers (manual, preset, automatic)
- **Comparison**: Analytics vs Preset vs Forced number comparison

### ⏰ Draw Monitor Tab
- **Draw Timer**: Countdown to next draw
- **Preset Schedule**: Full schedule view with current draw highlighted
- **Forced Number Checker**: Check and apply forced numbers
- **Manual Number Control**: Set manual winning numbers for testing

### 🔧 Maintenance Tab
- **System Health Checks**:
  - Database connectivity
  - API endpoint health
  - Firebase connection status
  - Recent transaction sync
  - System performance metrics
- **System Information**: Version, last update, Firebase status, API URL

## File Structure

```
monitoring/
├── index.html                 # Main dashboard (all-in-one)
├── login.html                 # Staff authentication
├── config/
│   └── firebase-config.js     # Firebase configuration
├── js/
│   ├── auth.js                # Firebase Auth
│   ├── dashboard.js           # Main dashboard logic
│   ├── analytics.js           # Analytics verification features
│   ├── draw-monitor.js        # Draw monitoring features
│   ├── maintenance.js         # Maintenance checks
│   ├── alerts.js              # Alert system
│   └── utils.js               # Utility functions
├── css/
│   └── styles.css             # All styles
└── README.md                  # This file
```

## Setup Instructions

### 1. Firebase Configuration

The app uses your existing Firebase project (`superbet-830b0`). The configuration is already set in `config/firebase-config.js`.

**Firebase Firestore Collections Structure:**
```
monitoring_alerts/{alertId}      # Real-time alerts (collection)
monitoring_shops/{shopId}        # Shop performance data (collection)
monitoring_payouts/{payoutId}    # Payout feed (collection)
monitoring_stats/live            # Dashboard statistics (document)
```

### 2. API Configuration

Update the API base URL in `js/utils.js`:

```javascript
const API_BASE = window.location.hostname === 'localhost' 
    ? 'http://localhost/slipp/api' 
    : 'https://your-domain.com/slipp/api';
```

### 3. Authentication

Default monitoring staff credentials:
- **Viewer**: `monitor001` / `monitor123`
- **Analyst**: `monitor002` / `monitor123`
- **Auditor**: `monitor003` / `monitor123`
- **Supervisor**: `supervisor` / `super123`

**Note**: In production, implement proper authentication (Firebase Auth or your API).

### 4. Database Setup

Only one new table is needed:

```sql
CREATE TABLE IF NOT EXISTS monitoring_alerts (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    related_shop_id INT,
    related_user_id INT,
    related_slip_id INT,
    related_transaction_id INT,
    related_draw_number INT,
    status ENUM('new', 'acknowledged', 'investigating', 'resolved') DEFAULT 'new',
    acknowledged_by VARCHAR(50),
    resolved_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_at DATETIME,
    resolved_at DATETIME,
    FOREIGN KEY (related_shop_id) REFERENCES betting_shops(shop_id),
    FOREIGN KEY (related_user_id) REFERENCES users(user_id),
    FOREIGN KEY (related_slip_id) REFERENCES betting_slips(slip_id),
    FOREIGN KEY (related_transaction_id) REFERENCES transactions(transaction_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);
```

### 5. PHP Sync Script (Optional)

Create a PHP script to sync MySQL data to Firebase Firestore. This should run periodically (cron job every 1-5 minutes):

```php
// api/monitoring/sync_to_firebase.php
// Syncs alerts, shop performance, and stats to Firebase
```

### 6. Deploy to GitHub Pages

1. Create a GitHub repository
2. Push the `monitoring/` folder contents to the repository
3. Enable GitHub Pages in repository settings
4. Access via: `https://yourusername.github.io/repository-name/`

## Usage

### Accessing the Dashboard

1. Navigate to `login.html`
2. Enter monitoring staff credentials
3. You'll be redirected to the main dashboard

### Features Overview

**Dashboard Tab:**
- View real-time alerts (updates automatically via Firebase)
- Monitor shop performance
- View today's statistics

**Analytics Tab:**
- Verify analytics data matches preset schedule
- Compare analytics history with forced numbers
- Check for discrepancies

**Draw Monitor Tab:**
- Monitor current draw and countdown
- View preset schedule
- Set manual numbers for testing
- Check forced numbers

**Maintenance Tab:**
- Run system health checks
- Monitor database connectivity
- Check API endpoint status
- Verify Firebase connection

## Real-time Updates

The dashboard uses Firebase Firestore listeners for real-time updates:
- **Alerts**: New alerts appear instantly
- **Shop Performance**: Updates automatically
- **Stats**: Live statistics from Firebase

## Browser Compatibility

- Chrome/Edge (recommended)
- Firefox
- Safari
- Mobile browsers (responsive design)

## Security Notes

1. **Authentication**: Currently uses simple username/password. In production, implement:
   - Firebase Authentication
   - JWT tokens
   - API-based authentication

2. **Firebase Security Rules**: Set up Firestore security rules to restrict access:
   ```javascript
   match /monitoring/{document=**} {
     allow read, write: if request.auth != null;
   }
   ```

3. **API Security**: Ensure your API endpoints are secured and require authentication.

## Troubleshooting

### Firebase Not Connecting
- Check Firebase configuration in `config/firebase-config.js`
- Verify Firebase SDK is loaded
- Check browser console for errors

### API Errors
- Verify API base URL in `js/utils.js`
- Check CORS settings on your server
- Ensure API endpoints are accessible

### No Real-time Updates
- Verify Firebase Firestore is enabled
- Check Firebase security rules
- Ensure sync script is running (if using PHP sync)

## Support

For issues or questions, check:
- Browser console for errors
- Firebase console for Firestore data
- API response in Network tab

## License

Internal use only - Casino Monitoring System

