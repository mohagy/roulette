# Video Ads Player for Roulette TV Display

This directory contains the video advertisements that will be displayed in the draggable video player on the roulette TV display.

## Supported Video Files

The player supports MP4 video files. The following files are currently configured to play:

- `adds1.mp4`
- `adds2.mp4`
- `adds3.mp4`
- `adds4.mp4`

## Adding Your Own Videos

1. Simply place your MP4 video files in this directory
2. Name them `adds1.mp4`, `adds2.mp4`, etc. to match the expected file names
3. The video player will automatically cycle through these videos

## Player Features

The video ads player includes the following features:

- **Draggable**: Click and drag the gold header to move the player around the screen
- **Resizable**: Drag the corner handle to resize the player
- **Controls**: Play/pause, mute/unmute buttons in the header
- **Close Button**: Hide the player (it will reappear when the page is refreshed)
- **Position Memory**: The player remembers its position and size between page loads

## Adjusting Player Settings

You can modify the default settings in the `tvdisplay/js/ads-player.js` file:

```javascript
const config = {
    basePath: '../adds/',  // Path to the ads directory
    initialPosition: { x: 20, y: 100 },  // Initial position
    width: 320,            // Default width
    height: 240,           // Default height
    autoplay: true,        // Auto-play videos
    loop: true,            // Loop the playlist
    muted: true,           // Start muted
    draggable: true,       // Allow dragging
    controls: true,        // Show video controls
    resizable: true,       // Allow resizing
    zIndex: 1000,          // Z-index for the player
    storageKey: 'adsPlayerSettings' // Local storage key
};
``` 