/**
 * Timer Synchronization
 * 
 * This script ensures that the countdown timer is synchronized between
 * the betting interface (index.html) and TV display (tvdisplay/index.html)
 */

document.addEventListener('DOMContentLoaded', function() {
  // Wait for scripts.js to fully load
  setTimeout(function() {
    initTimerSync();
    
    // Make sure draw container is hidden
    const drawContainer = document.querySelector('.draw-container');
    if (drawContainer) {
      drawContainer.style.display = 'none';
      drawContainer.style.visibility = 'hidden';
      drawContainer.style.opacity = '0';
    }
  }, 1000);
});

function initTimerSync() {
  console.log('Initializing timer synchronization...');
  
  // Check if we're on the correct page
  const timerContainer = document.querySelector('.timer-container');
  const countdownDisplay = document.getElementById('countdown-timer');
  
  if (!timerContainer || !countdownDisplay) {
    console.error('Timer container or countdown display not found');
    return;
  }
  
  // Ensure the timer container is visible and draw container is hidden
  timerContainer.style.display = 'flex';
  timerContainer.style.visibility = 'visible';
  timerContainer.style.opacity = '1';
  
  // Function to fetch current timer from TV display
  function syncTimerWithTVDisplay() {
    console.log('Syncing timer with TV display...');
    
    fetch('sync_timer.php')
      .then(response => response.json())
      .then(data => {
        if (data.success && data.countdownTime !== undefined) {
          console.log('Received countdown time from server:', data.countdownTime);
          
          // Update the local timer
          if (window.countdownTime !== data.countdownTime) {
            console.log('Updating countdown time from', window.countdownTime, 'to', data.countdownTime);
            window.countdownTime = data.countdownTime;
            
            // Update localStorage
            const newEndTime = new Date().getTime() + (window.countdownTime * 1000);
            localStorage.setItem('countdownEndTime', newEndTime.toString());
            
            // If countdown interval isn't running, start it
            if (!window.countdownInterval) {
              console.log('Starting countdown interval');
              if (typeof window.startCountdown === 'function') {
                window.startCountdown();
              }
            } else {
              // Just update the display
              if (typeof window.updateCountdownDisplay === 'function') {
                window.updateCountdownDisplay();
              } else {
                // Manual update if function not available
                const minutes = Math.floor(window.countdownTime / 60);
                const seconds = window.countdownTime % 60;
                countdownDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
              }
            }
          }
        } else {
          console.error('Invalid response from server:', data);
        }
      })
      .catch(error => {
        console.error('Error syncing timer:', error);
      });
  }
  
  // Create PHP endpoint for timer synchronization if it doesn't exist
  function createSyncTimerEndpoint() {
    const syncTimerPHP = `<?php
// Timer Synchronization Endpoint
header('Content-Type: application/json');

// Get countdown time from database
$db_file = dirname(__FILE__) . '/../tvdisplay/db/rolls.json';
$response = ['success' => false];

if (file_exists($db_file)) {
    $data = json_decode(file_get_contents($db_file), true);
    
    if (isset($data['countdownTime'])) {
        $response = [
            'success' => true,
            'countdownTime' => (int)$data['countdownTime']
        ];
    }
}

echo json_encode($response);
`;

    // Send this code to the server to create the file
    fetch('create_sync_timer.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        file_content: syncTimerPHP
      }),
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('Sync timer endpoint created successfully');
      } else {
        console.error('Failed to create sync timer endpoint:', data.message);
      }
    })
    .catch(error => {
      console.error('Error creating sync timer endpoint:', error);
    });
  }
  
  // Just in case, create the PHP endpoint
  fetch('sync_timer.php')
    .then(response => {
      if (!response.ok) {
        // Endpoint doesn't exist, create it
        createSyncTimerEndpoint();
      }
    })
    .catch(() => {
      // Error occurs if file doesn't exist
      createSyncTimerEndpoint();
    });
  
  // Immediately sync timer
  syncTimerWithTVDisplay();
  
  // Periodically sync timer (every 5 seconds)
  setInterval(syncTimerWithTVDisplay, 5000);
}

// Create a helper function to create the sync timer endpoint file
function createSyncTimerFile() {
  // Create a simple form to post the file content
  const form = document.createElement('form');
  form.style.display = 'none';
  form.method = 'POST';
  form.action = 'create_sync_timer.php';
  
  const textarea = document.createElement('textarea');
  textarea.name = 'file_content';
  textarea.value = `<?php
// Timer Synchronization Endpoint
header('Content-Type: application/json');

// Get countdown time from database
$db_file = dirname(__FILE__) . '/../tvdisplay/db/rolls.json';
$response = ['success' => false];

if (file_exists($db_file)) {
    $data = json_decode(file_get_contents($db_file), true);
    
    if (isset($data['countdownTime'])) {
        $response = [
            'success' => true,
            'countdownTime' => (int)$data['countdownTime']
        ];
    }
}

echo json_encode($response);
`;
  
  const submit = document.createElement('input');
  submit.type = 'submit';
  submit.value = 'Create';
  
  form.appendChild(textarea);
  form.appendChild(submit);
  document.body.appendChild(form);
  
  // For debugging only
  console.log('Form created for manual submission');
} 