# Roulette POS System

A professional Roulette Point of Sale system with real-time synchronization using Firebase Realtime Database.

## 🚀 Live Demo

- **Firebase Hosting**: https://roulette-2f902.web.app (Recommended)
- **GitHub Pages**: https://mohagy.github.io/roulette/ (Static only - PHP features disabled)

## ⚠️ Important Notes

### GitHub Pages Limitations

GitHub Pages only supports **static websites** (HTML, CSS, JavaScript). The following features **will NOT work** on GitHub Pages:

- ❌ PHP backend APIs
- ❌ MySQL database connections
- ❌ Server-side authentication
- ❌ Any PHP file processing

### Recommended: Firebase Hosting

Since this application uses Firebase Realtime Database, **Firebase Hosting is the recommended hosting solution**:

```bash
# Deploy to Firebase Hosting
firebase deploy --only hosting
```

Your app will be available at: `https://roulette-2f902.web.app`

## 🛠️ Features

- Real-time roulette game synchronization
- Firebase Realtime Database integration
- TV display mode
- Betting slip management
- Cash management
- Commission tracking
- Transaction history
- User authentication
- Multi-draw betting system

## 📁 Project Structure

```
├── index.html          # Main application
├── tvdisplay/          # TV display interface
├── js/                 # JavaScript modules
├── css/                # Stylesheets
├── php/                # PHP backend (not available on GitHub Pages)
├── api/                # API endpoints (not available on GitHub Pages)
└── firebase.json       # Firebase configuration
```

## 🔥 Firebase Integration

The application uses Firebase Realtime Database for:
- Real-time game state synchronization
- Draw results storage
- Analytics data
- Betting slips
- User data

## 🚀 Getting Started

### Local Development

1. Clone the repository:
```bash
git clone https://github.com/mohagy/roulette.git
cd roulette
```

2. For local development with PHP:
   - Use XAMPP or similar PHP server
   - Configure MySQL database
   - Update database credentials in `php/db_connect.php`

3. For static hosting (Firebase/GitHub Pages):
   - The app will use Firebase for all backend operations
   - No PHP or MySQL required

### Firebase Setup

1. Install Firebase CLI:
```bash
npm install -g firebase-tools
```

2. Login to Firebase:
```bash
firebase login
```

3. Deploy:
```bash
firebase deploy
```

## 📝 License

This project is proprietary software.

## 👤 Author

mohagy (nathonheart@gmail.com)
