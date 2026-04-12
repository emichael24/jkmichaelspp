# Capacitor Setup Guide — JK Michaels Mobile App

## Prerequisites
- Node.js 18+
- Android Studio (for Android builds)
- Xcode 15+ (for iOS builds, macOS only)

## Initial Setup

```bash
# Install Capacitor core and CLI
npm install @capacitor/core @capacitor/cli

# Install platform packages
npm install @capacitor/android @capacitor/ios

# Install required plugins
npm install @capacitor/splash-screen @capacitor/push-notifications
npm install @capacitor/camera @capacitor/filesystem @capacitor/network
npm install @capacitor/status-bar @capacitor/keyboard
npm install @capacitor/haptics @capacitor/app @capacitor/browser

# Initialize Capacitor (already configured via capacitor.config.json)
npx cap init

# Add platforms
npx cap add android
npx cap add ios
```

## Build & Deploy

### Development
```bash
# Build the web app
npm run build

# Sync web assets to native projects
npx cap sync

# Open in Android Studio
npx cap open android

# Open in Xcode
npx cap open ios
```

### Live Reload (Development)
```bash
# Start Vite dev server
npm run dev

# Then update capacitor.config.json server url:
# "server": { "url": "http://YOUR-LOCAL-IP:5173" }

npx cap run android --livereload
npx cap run ios --livereload
```

## App Icons & Splash Screens

Place source images in the project:
- `resources/icon.png` — 1024×1024 app icon
- `resources/splash.png` — 2732×2732 splash screen

```bash
# Install icon/splash generator
npm install -D @capacitor/assets

# Generate all sizes
npx capacitor-assets generate
```

## Push Notifications Setup

### Android (Firebase)
1. Create Firebase project at https://console.firebase.google.com
2. Add Android app with package name `com.jkmichaels.mastery`
3. Download `google-services.json` → `android/app/google-services.json`

### iOS (APNs)
1. Enable Push Notifications capability in Xcode
2. Generate APNs key in Apple Developer portal
3. Upload key to your push notification service

## Play Store Submission
```bash
# Build release APK/AAB
cd android
./gradlew bundleRelease

# Output: android/app/build/outputs/bundle/release/app-release.aab
```

## App Store Submission
1. Open `ios/App/App.xcworkspace` in Xcode
2. Set version and build number
3. Archive: Product → Archive
4. Upload via Xcode Organizer or Transporter
