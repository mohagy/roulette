// Script to increase the bet display height
(function() {
  // Get the bet display container
  const betDisplay = document.getElementById('bet-display-container');
  
  if (betDisplay) {
    // Set a much taller height to reach the custom amount button
    betDisplay.style.height = '750px';
    console.log('Setting bet display height to 750px');
    
    // Also update the max-height of the bet display body
    const betDisplayBody = betDisplay.querySelector('.bet-display-body');
    if (betDisplayBody) {
      const headerHeight = betDisplay.querySelector('.bet-display-header').offsetHeight || 50;
      const stakeControlHeight = betDisplay.querySelector('.stake-control').offsetHeight || 40;
      betDisplayBody.style.maxHeight = (750 - headerHeight - stakeControlHeight) + 'px';
      console.log('Updated bet display body max-height');
    }
    
    // Update the bet display list
    const betDisplayList = betDisplay.querySelector('.bet-display-list');
    if (betDisplayList) {
      betDisplayList.style.maxHeight = '850px';
      console.log('Updated bet display list max-height');
    }
    
    // Make sure other parameters are set properly
    betDisplay.style.maxHeight = 'none';
    
    // Let the user know we've attempted to make changes
    console.log('All height adjustments completed successfully');
    alert('Bet display height has been increased to reach the custom amount button');
  } else {
    console.error('Could not find bet display container');
    alert('Error: Could not find bet display container');
  }
})(); 