# GitHub Pages Manual Setup

## ⚠️ Required: Enable GitHub Pages Manually

GitHub Actions cannot automatically enable GitHub Pages due to permission restrictions. You must enable it manually:

## Step-by-Step Instructions

1. **Go to Repository Settings**
   - Navigate to: https://github.com/mohagy/roulette/settings/pages

2. **Configure Pages Source**
   - Under **"Source"**, select:
     - **Source**: `Deploy from a branch`
     - **Branch**: `main`
     - **Folder**: `/ (root)`

3. **Click "Save"**

4. **Wait for Initial Setup**
   - GitHub will create the Pages site (takes 1-2 minutes)
   - You'll see a green checkmark when ready

5. **Verify Deployment**
   - After enabling, the GitHub Actions workflow will automatically deploy
   - Check the Actions tab: https://github.com/mohagy/roulette/actions
   - Your site will be available at: https://mohagy.github.io/roulette/

## After Enabling Pages

Once GitHub Pages is enabled, the workflow will work automatically on every push to `main`.

## Alternative: Use Firebase Hosting (Recommended)

Since your app uses Firebase, **Firebase Hosting is already set up and working**:

- ✅ Already deployed: https://roulette-2f902.web.app
- ✅ Better performance with CDN
- ✅ Works perfectly with Firebase Realtime Database
- ✅ No manual setup required

**You can skip GitHub Pages entirely and just use Firebase Hosting!**

