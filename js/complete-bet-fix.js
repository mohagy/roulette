/**
 * Fix for Complete Bet functionality
 * This script fixes the issue with complete bets not showing up in the "Your Bets" area
 */

// Wait for the document to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
  console.log('Complete bet fix loaded');

  // Override the placeCompleteBet function with our fixed version
  window.placeCompleteBet = function(number) {
    console.log('Placing complete bet on number:', number);
    if (number < 0 || number > 36) return; // Validate number

    // Get all bets that involve this number
    const bets = getCompleteBets(number);
    console.log('Complete bet positions:', bets);

    // Check if we're removing or adding bets
    let isRemovingBets = false;
    let totalChipsToAdd = 0;
    let totalChipsToRemove = 0;

    // First pass - check if we're removing or adding bets
    bets.forEach(betSelector => {
      const element = document.querySelector(betSelector);
      if (element && $(element).has(".betting-chip").length) {
        // This bet already exists, we'll be removing it
        isRemovingBets = true;
        const existingValue = Number($(element).children(".betting-chip").attr("id"));
        totalChipsToRemove += existingValue;
      } else {
        // This is a new bet, we'll be adding it
        totalChipsToAdd += activeChipNumber;
      }
    });

    console.log('Complete bet analysis:', {
      isRemovingBets,
      totalChipsToAdd,
      totalChipsToRemove,
      activeChipNumber,
      betSum,
      cashSum
    });

    // If we're removing bets, we don't need to check for money
    if (!isRemovingBets) {
      // Check if player has enough money
      if (cashSum < totalChipsToAdd) {
        $(".alert-money").addClass("alert-message-visible");
        setTimeout(() => {
          $(".alert-money").removeClass("alert-message-visible");
        }, 2000);
        return;
      }

      // Check if this would exceed max bet
      if (betSum + totalChipsToAdd > maxBet) {
        $(".alert-max-bet").addClass("alert-message-visible");
        setTimeout(() => {
          $(".alert-max-bet").removeClass("alert-message-visible");
        }, 2000);
        return;
      }
    }

    // Process all positions
    bets.forEach(betSelector => {
      const element = document.querySelector(betSelector);
      if (!element) {
        console.warn('Element not found for selector:', betSelector);
        return;
      }

      if ($(element).has(".betting-chip").length) {
        // Element already has chips, remove them
        const existingValue = Number($(element).children(".betting-chip").attr("id"));

        // Get the bet ID to remove it from the tracker
        const betId = betTracker.generateBetId(element);

        // Remove the chip visually
        $(element).html("");

        // Update bet sum and cash sum
        betSum = betSum - existingValue;
        cashSum = cashSum + existingValue;
        $(".bet-total").html(`${betSum.toFixed(2)}`);
        $(".cash-total").html(`${cashSum.toFixed(2)}`);

        // Remove from bet tracker
        betTracker.removeBetById(betId);
      } else {
        // Element has no chips yet, add a new bet
        let chipClass = getChipClass(activeChipNumber);

        // Add the chip visually
        $(element).html(
          `<div id="${activeChipNumber}" class="betting-chip betting-chip-shadow ${chipClass}">${activeChipNumber}</div>`
        );

        // Update bet sum and cash sum
        betSum = betSum + activeChipNumber;
        cashSum = cashSum - activeChipNumber;
        $(".bet-total").html(`${betSum.toFixed(2)}`);
        $(".cash-total").html(`${cashSum.toFixed(2)}`);

        // Add to bet tracker - this is the key part that was missing
        betTracker.addBet(element, activeChipNumber);
      }
    });

    // Play chip sound (once, not for each bet)
    if (playAudio) {
      if (isRemovingBets) {
        selectSound.play();
      } else {
        chipPutSound.play();
      }
    }

    // Exit complete bet mode
    isCompleteBetMode = false;
    $(".button-complete").removeClass("active-button");

    console.log('Complete bet placed successfully');
  };

  // Re-attach the click handler for the Complete button
  $(".button-complete").off('click').on('click', function() {
    if (isCompleteBetMode) {
      // Turn off complete bet mode
      isCompleteBetMode = false;
      $(this).removeClass("active-button");
      console.log('Complete bet mode deactivated');
    } else {
      // Turn on complete bet mode
      isCompleteBetMode = true;
      $(this).addClass("active-button");
      console.log('Complete bet mode activated');

      // Show a message to indicate complete bet mode is active
      const tooltip = document.querySelector(".bet-type-tooltip");
      if (tooltip) {
        tooltip.textContent = "Click on a number to place a complete bet";
        tooltip.style.display = 'block';
        tooltip.classList.add('visible');
        setTimeout(() => {
          if (isCompleteBetMode) {
            tooltip.classList.remove('visible');
            tooltip.style.display = '';
          }
        }, 2000);
      }
    }

    if (playAudio) {
      selectSound.play();
    }
  });

  // Re-attach click handler for number elements to support complete bets
  $(".number").off('click.completeBet').on('click.completeBet', function() {
    if (isCompleteBetMode) {
      // Only process for the numbered elements (0-36)
      for (let i = 0; i <= 36; i++) {
        if ($(this).hasClass(`number${i}`)) {
          console.log('Complete bet triggered on number:', i);
          placeCompleteBet(i);
          return;
        }
      }
    }
  });

  console.log('Complete bet functionality fixed - bets will now show in Your Bets area');
});
