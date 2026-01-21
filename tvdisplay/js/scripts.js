const resizeWindow = () => {
  const sizeGuidelines = () => {
    if (window.innerWidth > 1024) {
      $(".betting-area")
        .width(window.innerWidth * 0.75)
        .height(window.innerWidth * 0.28);
    }

    if (window.innerWidth > 414 && window.innerWidth <= 1024) {
      $(".betting-area")
        .width(window.innerHeight - 208)
        .height((window.innerHeight - 192) * 0.45);
    }

    if (window.innerWidth <= 414) {
      $(".betting-area")
        .width(window.innerHeight - 192)
        .height((window.innerHeight - 192) * 0.45);
    }
  };

  if (window.innerWidth <= 1024) {
    $(".website-wrapper").height(window.innerHeight);
  }

  window.addEventListener("resize", () => {
    $(".website-wrapper").height(window.innerHeight);
    sizeGuidelines();
  });

  sizeGuidelines();
};

resizeWindow();

// Create DetailedDrawManager interface for compatibility with new save-detailed-draw.js
// This initializes the DrawResults module and makes it available as DetailedDrawManager
$(document).ready(function() {
  // Start real-time debug panel updates when page loads
  setTimeout(() => {
    if (typeof startDebugPanelRealTimeUpdates === 'function') {
      startDebugPanelRealTimeUpdates();
    }
  }, 2000); // Wait 2 seconds for all scripts to load
  if (window.DrawResults && typeof window.DrawResults.initialize === 'function') {
    try {
      // Initialize DrawResults with proper config
      const drawResultsModule = window.DrawResults.initialize({
        autoSave: true,
        debug: true,
        tableName: "TV Display",
        dealerName: "Auto Dealer"
      });

      // Create an alias to match scripts.js expectations
      window.DetailedDrawManager = {
        saveCurrentSpinResult: function(number, options) {
          console.log('Saving spin result via DetailedDrawManager:', number, options);
          return drawResultsModule.saveDrawResult({
            winningNumber: parseInt(number),
            winningColor: getNumberColor(number),
            gameSessionId: options.sessionId || null,
            dealerId: options.dealerId || "Auto Dealer",
            tableId: options.tableId || "TV Display",
            totalBets: options.total_bets || 0,
            totalPayout: options.total_payout || 0,
            playerCount: options.player_count || 0,
            notes: options.notes || "Saved from TV Display"
          });
        }
      };

      console.log('DetailedDrawManager initialized successfully');
    } catch (error) {
      console.error('Failed to initialize DetailedDrawManager:', error);
    }
  }
});

// Helper function to determine number color
function getNumberColor(number) {
  number = parseInt(number);
  if (number === 0) return 'green';
  if (rouletteNumbersRed.includes(number)) return 'red';
  return 'black';
}

const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
const rouletteNumbersBlack = [2, 4, 6, 8, 11, 10, 13, 15, 17, 20, 24, 22, 26, 28, 29, 31, 33, 35];
const rouletteNumbersArray = [
  0,
  32,
  15,
  19,
  4,
  21,
  2,
  25,
  17,
  34,
  6,
  27,
  13,
  36,
  11,
  30,
  8,
  23,
  10,
  5,
  24,
  16,
  33,
  1,
  20,
  14,
  31,
  9,
  22,
  18,
  29,
  7,
  28,
  12,
  35,
  3,
  26
];
const betRangeArray = [
  { name: "column-1st12", rangeStart: 1, rangeEnd: 12 },
  { name: "column-2nd12", rangeStart: 13, rangeEnd: 24 },
  { name: "column-3rd12", rangeStart: 25, rangeEnd: 36 },
  { name: "column-1to18", rangeStart: 1, rangeEnd: 18 },
  { name: "column-19to36", rangeStart: 19, rangeEnd: 36 }
];
const rouletteNumbersAmount = 37;

let activeChip = "betting-chip-menu5";
let activeChipNumber = 5;

let rolledNumbersArray = [];
let rolledNumbersColorArray = [];
const mouseEventType = ["click", "mouseover"];

// Track the last draw number that had a result saved
// This prevents multiple results from being saved for the same draw number
let lastSavedDrawNumber = 0;

// Tutorial highlighting state management
let tutorialHighlightedDrawNumber = 0; // Track which draw number had tutorial run
let isTutorialRunning = false; // Prevent multiple simultaneous tutorial sequences
let tutorialTimeoutIds = []; // Store timeout IDs to allow clearing if needed

// Replace localStorage methods with API calls for state persistence
// Save roll history to database
function saveRollHistory() {
  try {
    // Make sure arrays are properly initialized if empty
    if (!Array.isArray(rolledNumbersArray)) rolledNumbersArray = [];
    if (!Array.isArray(rolledNumbersColorArray)) rolledNumbersColorArray = [];

    // Log data for debugging
    console.log('Saving roll history:', rolledNumbersArray, rolledNumbersColorArray);

    // Draw number display removed from TV interface - use internal tracking
    const lastDraw = currentDrawNumber > 1 ? `#${currentDrawNumber - 1}` : '#0';
    const nextDraw = `#${currentDrawNumber}`;

    // Get the saved end time from localStorage
    const savedEndTime = localStorage.getItem('countdownEndTime');
    const currentTime = new Date().getTime();

    // Calculate remaining time in seconds
    let remainingTime = countdownTime;
    if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
      const remainingTimeMs = parseInt(savedEndTime) - currentTime;
      remainingTime = Math.max(0, Math.floor(remainingTimeMs / 1000));
    }

    // Ensure countdown time is valid
    if (typeof remainingTime !== 'number' || isNaN(remainingTime)) {
      // Calculate a new end time based on real-time
      const nextDraw = calculateNextDrawTime();
      remainingTime = nextDraw.secondsRemaining;
    }

    // Prepare data for saving - ensure we're sending proper string representations
    const gameState = {
      numbers: rolledNumbersArray.length > 0 ? rolledNumbersArray.toString() : '',
      colors: rolledNumbersColorArray.length > 0 ? rolledNumbersColorArray.toString() : '',
      lastDraw: lastDraw,
      nextDraw: nextDraw,
      timer: remainingTime,
      endTime: savedEndTime || (new Date().getTime() + (remainingTime * 1000)).toString()
    };

    console.log('Saving game state to database:', gameState);

    // Send data to server - using absolute path from domain root
    fetch('/slipp/save_state.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(gameState)
    })
    .then(response => response.json())
    .then(data => {
      console.log('Game state saved to database:', data);

      // Also save to localStorage as backup
      localStorage.setItem('rolledNumbersArray', JSON.stringify(rolledNumbersArray));
      localStorage.setItem('rolledNumbersColorArray', JSON.stringify(rolledNumbersColorArray));
    })
    .catch(error => {
      console.error('Error saving roll history to database:', error);
      // Fallback to localStorage if database save fails
      localStorage.setItem('rolledNumbersArray', JSON.stringify(rolledNumbersArray));
      localStorage.setItem('rolledNumbersColorArray', JSON.stringify(rolledNumbersColorArray));
    });
  } catch (error) {
    console.error('Error saving roll history:', error);
  }
}

// Load saved roll history from database
async function loadRollHistory() {
  try {
    console.log("Attempting to load game state from database...");
    const response = await fetch('/slipp/load_state.php');
    const data = await response.json();

    if (data.status === 'success') {
      console.log('Game state loaded from database:', data);

      // Handle empty values or single values
      const historyStr = data.roll_history || '';
      const colorsStr = data.roll_colors || '';

      // Parse the roll history and colors
      let numbers = [];
      let colors = [];

      if (historyStr && historyStr.length > 0 && historyStr !== '[]') {
        numbers = historyStr.split(',').map(num => {
          // Handle potential empty values
          return num.trim() ? parseInt(num.trim()) : null;
        }).filter(num => num !== null);
      }

      if (colorsStr && colorsStr.length > 0 && colorsStr !== '[]') {
        colors = colorsStr.split(',').map(color => color.trim()).filter(color => color);
      }

      console.log('Parsed numbers:', numbers);
      console.log('Parsed colors:', colors);

      // Only update arrays if we have valid data
      if (numbers.length > 0) {
        rolledNumbersArray = numbers;
        console.log('Updated rolledNumbersArray:', rolledNumbersArray);

        // Update currentDrawNumber based on the number of rolls
        // that have occurred - this ensures draw numbers stay in sync
        currentDrawNumber = Math.max(rolledNumbersArray.length, currentDrawNumber);
        console.log('Updated currentDrawNumber based on rolls:', currentDrawNumber);
      } else {
        rolledNumbersArray = [];
      }

      if (colors.length > 0) {
        rolledNumbersColorArray = colors;
        console.log('Updated rolledNumbersColorArray:', rolledNumbersColorArray);
      } else {
        rolledNumbersColorArray = [];
      }

      // Draw number display removed from TV interface - update internal tracking only
      if (data.last_draw) {
        console.log('Loaded last_draw from server:', data.last_draw);

        // Extract number from last_draw (format: #N)
        const lastDrawNum = parseInt(data.last_draw.replace('#', ''));
        if (!isNaN(lastDrawNum) && lastDrawNum > currentDrawNumber) {
          currentDrawNumber = lastDrawNum;
          console.log('Updated currentDrawNumber from last_draw:', currentDrawNumber);
        }
      }

      if (data.next_draw) {
        console.log('Loaded next_draw from server:', data.next_draw);

        // Extract number from next_draw (format: #N)
        const nextDrawNum = parseInt(data.next_draw.replace('#', ''));
        if (!isNaN(nextDrawNum) && nextDrawNum > currentDrawNumber + 1) {
          currentDrawNumber = nextDrawNum - 1;
          console.log('Updated currentDrawNumber from next_draw:', currentDrawNumber);
        }
      }

      // Update draw number display to sync with rolledNumbersArray
      updateDrawNumberDisplay();

      // Handle countdown time
      if (data.end_time && !isNaN(parseInt(data.end_time))) {
        // If we have an end time stored in the database, use that
        const endTime = parseInt(data.end_time);
        const currentTime = new Date().getTime();
        const remainingTimeMs = endTime - currentTime;

        if (remainingTimeMs > 0) {
          // End time is still in the future, use it
          countdownTime = Math.floor(remainingTimeMs / 1000);
          localStorage.setItem('countdownEndTime', endTime.toString());
          console.log('Using saved end time from database, countdown:', countdownTime);
        } else {
          // End time has passed, calculate a new one based on real-time
          const nextDraw = calculateNextDrawTime();
          countdownTime = nextDraw.secondsRemaining;
          localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
          console.log('Saved end time expired, using real-time calculation:', countdownTime);
        }
      } else if (data.countdown_time) {
        // Fall back to countdown_time if end_time is not available
        const savedTime = parseInt(data.countdown_time);

        if (!isNaN(savedTime) && savedTime > 0) {
          // Calculate a new end time based on the saved countdown
          const newEndTime = new Date().getTime() + (savedTime * 1000);
          localStorage.setItem('countdownEndTime', newEndTime.toString());
          countdownTime = savedTime;
          console.log('Using countdown time from database:', countdownTime);
        } else {
          // Invalid saved time, calculate a new one based on real-time
          const nextDraw = calculateNextDrawTime();
          countdownTime = nextDraw.secondsRemaining;
          localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
          console.log('Invalid saved time, using real-time calculation:', countdownTime);
        }
      } else {
        // No saved time, calculate a new one based on real-time
        const nextDraw = calculateNextDrawTime();
        countdownTime = nextDraw.secondsRemaining;
        localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
        console.log('No saved time, using real-time calculation:', countdownTime);
      }

      // Update localStorage backup
      localStorage.setItem('rolledNumbersArray', JSON.stringify(rolledNumbersArray));
      localStorage.setItem('rolledNumbersColorArray', JSON.stringify(rolledNumbersColorArray));

      // Display roll history
      displayRollHistory();

      console.log('Game state loaded successfully:', rolledNumbersArray, rolledNumbersColorArray);
      return true;
    } else {
      console.warn('No game state found in database:', data.message);
      // Try to load from localStorage as fallback
      const savedRolledNumbersArray = localStorage.getItem('rolledNumbersArray');
      const savedRolledNumbersColorArray = localStorage.getItem('rolledNumbersColorArray');

      if (savedRolledNumbersArray && savedRolledNumbersColorArray) {
        rolledNumbersArray = JSON.parse(savedRolledNumbersArray);
        rolledNumbersColorArray = JSON.parse(savedRolledNumbersColorArray);

        // Update currentDrawNumber based on the number of rolls
        currentDrawNumber = Math.max(rolledNumbersArray.length, currentDrawNumber);

        // Display saved history immediately
        displayRollHistory();
        console.log('Roll history loaded from localStorage');

        // Update draw number display
        updateDrawNumberDisplay();

        return true;
      }

      // If no data found anywhere, initialize with empty arrays
      rolledNumbersArray = [];
      rolledNumbersColorArray = [];
      return false;
    }
  } catch (error) {
    console.error('Error loading roll history from database:', error);
    // Try to load from localStorage as fallback
    const savedRolledNumbersArray = localStorage.getItem('rolledNumbersArray');
    const savedRolledNumbersColorArray = localStorage.getItem('rolledNumbersColorArray');

    if (savedRolledNumbersArray && savedRolledNumbersColorArray) {
      rolledNumbersArray = JSON.parse(savedRolledNumbersArray);
      rolledNumbersColorArray = JSON.parse(savedRolledNumbersColorArray);

      // Update currentDrawNumber based on the number of rolls
      currentDrawNumber = Math.max(rolledNumbersArray.length, currentDrawNumber);

      // Display saved history immediately
      displayRollHistory();
      console.log('Roll history loaded from localStorage (fallback)');

      // Update draw number display
      updateDrawNumberDisplay();

      return true;
    }

    // If no data found anywhere, initialize with empty arrays
    rolledNumbersArray = [];
    rolledNumbersColorArray = [];
    return false;
  }
}

// Display the roll history in the UI
function displayRollHistory() {
  console.log('🎲 DISPLAY ROLL HISTORY: Displaying roll history');
  console.log('🎲 DISPLAY ROLL HISTORY: rolledNumbersArray:', rolledNumbersArray);
  console.log('🎲 DISPLAY ROLL HISTORY: rolledNumbersColorArray:', rolledNumbersColorArray);

  // Ensure we have valid arrays
  if (!Array.isArray(rolledNumbersArray) || !Array.isArray(rolledNumbersColorArray)) {
    console.error('🎲 DISPLAY ROLL HISTORY: Invalid roll history data', rolledNumbersArray, rolledNumbersColorArray);
    return;
  }

  // Only clear and update if we have data to display
  if (rolledNumbersArray.length === 0) {
    console.log('🎲 DISPLAY ROLL HISTORY: No data to display, keeping existing display');
    return;
  }

  console.log('🎲 DISPLAY ROLL HISTORY: Clearing existing display and updating with new data');

  // Clear existing display first to prevent issues
  for (let i = 1; i <= 5; i++) {
    $(`.roll${i}`).html('');
    $(`.roll${i}`).removeClass("roll-red roll-black roll-green");
  }

  // Now display the roll history
  for (let i = 0; i < rolledNumbersArray.length && i < 5; i++) {
    let rolledNumberIndex = i + 1;

    // Ensure we have a valid number
    if (rolledNumbersArray[i] !== undefined && rolledNumbersArray[i] !== null) {
      $(`.roll${rolledNumberIndex}`).html(rolledNumbersArray[i]);

      // Make sure we have a matching color entry
      const colorClass = (i < rolledNumbersColorArray.length)
                       ? rolledNumbersColorArray[i]
                       : getNumberColor(rolledNumbersArray[i]);

      switch (colorClass) {
        case "red":
          $(`.roll${rolledNumberIndex}`).removeClass("roll-black roll-green").addClass("roll-red");
          break;
        case "black":
          $(`.roll${rolledNumberIndex}`).removeClass("roll-red roll-green").addClass("roll-black");
          break;
        case "green":
          $(`.roll${rolledNumberIndex}`).removeClass("roll-red").removeClass("roll-black").addClass("roll-green");
          break;
      }

      console.log(`🎲 DISPLAY ROLL HISTORY: Set .roll${rolledNumberIndex} to ${rolledNumbersArray[i]} (${colorClass})`);
    }
  }

  console.log('🎲 DISPLAY ROLL HISTORY: Display update completed');
}

const chipPutSound = new Audio("sounds/chip-put.mp3");
const selectSound = new Audio("sounds/chip-select.mp3");
const menuSound = new Audio("sounds/menu.mp3");
const ballSpinSound = new Audio("sounds/ball-spin.mp3");
const winSound = new Audio("sounds/win.mp3");
const winChipsSound = new Audio("sounds/win-chips.mp3");
const ambientSound = new Audio("sounds/ambient-sounds.mp3");
const backgroundMusic = new Audio("sounds/background-music.mp3");

var playAudio = true;
var userInteraction = false;

$(".website-wrapper").click(function () {
  userInteraction = true;
  if (playAudio) {
    ambientSound.play();
    backgroundMusic.play();
  }
});

ambientSound.loop = true;
backgroundMusic.loop = true;

const classColorName = (functionType) => {
  let className;
  functionType == "mouseover" ? (className = "white-area") : (className = "marked-area");
  return className;
};

const rowsBetRange = () => {
  for (let className = 1; className <= 3; className++) {
    let divNumber;
    switch (className) {
      case 1:
        divNumber = 0;
        break;
      case 2:
        divNumber = 2;
        break;
      case 3:
        divNumber = 1;
        break;
    }
    mouseEventType.forEach((functionType) => {
      $(`.bet2to1-${className}`).on(functionType, function () {
        for (let i = 1; i < rouletteNumbersAmount; i++) {
          if (i % 3 == divNumber) $(`.number${i}`).addClass(classColorName(functionType));
        }
      });
    });
  }
};

const columnBetRange = () => {
  mouseEventType.forEach((functionType) => {
    betRangeArray.forEach((el) => {
      $(`.${el.name}`).on(functionType, function () {
        for (let i = el.rangeStart; i <= el.rangeEnd; i++) {
          $(`.number${i}`).addClass(classColorName(functionType));
        }
      });
    });
  });
};

const columnEvenOdd = () => {
  ["column-even", "column-odd"].forEach((className) => {
    let index;
    className == "column-even" ? (index = 0) : (index = 1);
    mouseEventType.forEach((functionType) => {
      $(`.${className}`).on(functionType, function () {
        for (let i = 1; i < rouletteNumbersAmount; i++) {
          if (i % 2 == index) {
            $(`.number${i}`).addClass(classColorName(functionType));
          }
        }
      });
    });
  });
};

const columnRedBlack = () => {
  mouseEventType.forEach((functionType) => {
    ["red", "black"].forEach((className) => {
      $(`.column-${className}`).on(functionType, function () {
        let firstCharUppercase = className[0].toUpperCase() + className.substring(1);
        for (let i = 0; i < 18; i++) {
          $(`.number${eval(`rouletteNumbers${firstCharUppercase}[i]`)}`).addClass(classColorName(functionType));
        }
      });
    });
  });
};

const regularNumbers = () => {
  mouseEventType.forEach((functionType) => {
    $(".regular").on(functionType, function () {
      for (let i = 0; i < rouletteNumbersAmount; i++) {
        if ($(this).hasClass(`regular${i}`)) {
          $(`.number${i}`).addClass(classColorName(functionType));
        }
      }
    });
  });
};

const cornerNumbers = () => {
  mouseEventType.forEach((functionType) => {
    $(".corner").on(functionType, function () {
      for (let i = 1; i < rouletteNumbersAmount; i++) {
        if ($(this).hasClass(`corner${i}`)) {
          switch (i % 3) {
            case 2:
              if (i == 2) {
                for (let a = 0; a < 3; a++) {
                  $(`.number${a}`).addClass(classColorName(functionType));
                }
              } else {
                document
                  .querySelectorAll(`.number${i} ,.number${i - 3}, .number${i - 4}, .number${i - 1}`)
                  .forEach((el) => el.classList.add(classColorName(functionType)));
              }
              break;
            case 0:
              document
                .querySelectorAll(`.number${i} ,.number${i - 3}, .number${i - 4}, .number${i - 1}`)
                .forEach((el) => el.classList.add(classColorName(functionType)));
              break;
            default:
              for (let a = i - 3; a < i + 3; a++) {
                if (i == 1) {
                  for (let c = 0; c < 4; c++) {
                    $(`.number${c}`).addClass(classColorName(functionType));
                  }
                } else {
                  $(`.number${a}`).addClass(classColorName(functionType));
                }
              }
          }
        }
      }
    });
  });
};

const lineNumbers = () => {
  mouseEventType.forEach((functionType) => {
    $(`.line`).on(functionType, function () {
      let index = 0;
      for (let i = 0; i < rouletteNumbersAmount; i++) {
        if ($(this).hasClass(`line${i}`)) {
          $(`.number${i}`).addClass(classColorName(functionType));
          index = i - 3;
          if (i <= 3) {
            index = 0;
          }
          $(`.number${index}`).addClass(classColorName(functionType));
        }
      }
    });
  });
};

const betweenNumbers = (className, functionType) => {
  mouseEventType.forEach((functionType) => {
    $(`.between`).on(functionType, function () {
      for (let i = 0; i < rouletteNumbersAmount; i++) {
        if ($(this).hasClass(`between${i}`)) {
          if (i % 3 == 1) {
            for (let a = i; a < i + 3; a++) {
              $(`.number${a}`).addClass(classColorName(functionType));
            }
          } else {
            document.querySelectorAll(`.number${i} ,.number${i - 1}`).forEach((el) => el.classList.add(classColorName(functionType)));
          }
        }
      }
    });
  });
};

rowsBetRange();
columnBetRange();
columnEvenOdd();
columnRedBlack();
regularNumbers();
cornerNumbers();
lineNumbers();
betweenNumbers();

document.querySelectorAll(`.number, .bottom-column`).forEach((el) => {
  el.addEventListener("mouseover", function () {
    $(this).addClass("white-area");
  });
});

document.querySelectorAll(`.number, .bottom-column`).forEach((el) => {
  el.addEventListener("mouseleave", function () {
    $(this).removeClass("white-area");
  });
});

$(".part").mouseleave(function () {
  $(".number").removeClass("white-area");
});

const chipSelection = () => {
  $(".betting-chip-menu").click(function () {
    $(".betting-chip-menu").removeClass("active-chip");
    $(this).addClass("active-chip");
    activeChipNumber = Number($(this).attr("id").substr(4));
    if (playAudio) {
      selectSound.play();
    }
  });

  $(".betting-chip-menu").mouseover(function () {
    if (playAudio && userInteraction) {
      menuSound.play();
    }
  });

  $(`.${activeChip}`).addClass("active-chip");
};

chipSelection();

//Chips placing start
var betSum = 0;
var cashSum = 1000;
var minBet = 5;
var maxBet = 1000;
var areaChipCount = 0;
var bankSum = cashSum;
$(".cash-total").html(`${cashSum}.00`);

$(".part").click(function () {
  if (bankSum >= betSum + activeChipNumber) {
    if (maxBet >= betSum + activeChipNumber) {
      if (playAudio) {
        chipPutSound.play();
      }

      betSum = betSum + activeChipNumber;
      cashSum = cashSum - activeChipNumber;
      $(".bet-total").html(`${betSum}.00`);
      $(".cash-total").html(`${cashSum}.00`);

      if ($(this).has(".betting-chip").length) {
        areaChipCount = Number(jQuery(this).children(".betting-chip").attr("id"));
        areaChipCount = areaChipCount + activeChipNumber;
        if (areaChipCount == 5) {
          activeChip = 10;
        } else if (areaChipCount >= 10 && areaChipCount < 20) {
          activeChip = 10;
        } else if (areaChipCount >= 20 && areaChipCount < 50) {
          activeChip = 20;
        } else if (areaChipCount >= 50 && areaChipCount < 100) {
          activeChip = 50;
        } else if (areaChipCount >= 100 && areaChipCount < 200) {
          activeChip = 100;
        } else if (areaChipCount >= 200) {
          activeChip = 200;
        }
        $(this).html(
          `<div id="${areaChipCount}" class="betting-chip betting-chip-shadow betting-chip${activeChip}">${areaChipCount}</div>`
        );
      } else {
        $(this).html(
          `<div id="${activeChipNumber}" class="betting-chip betting-chip-shadow betting-chip${activeChipNumber}">${activeChipNumber}</div>`
        );
      }
    } else {
      $(".alert-max-bet").addClass("alert-message-visible");
    }
  } else {
    $(".alert-money").addClass("alert-message-visible");
  }
});

$(".circle-overlay").mouseover(function () {
  if (playAudio && userInteraction) {
    menuSound.play();
  }
});

$(".circle-overlay").click(function () {
  if (playAudio) {
    selectSound.play();
  }
});

$(".button-sound").click(function () {
  if ($(".cross-line").hasClass("cross-line-display")) {
    $(".cross-line").removeClass("cross-line-display");
    playAudio = true;
  } else {
    $(".cross-line").addClass("cross-line-display");
    playAudio = false;
    ambientSound.pause();
    backgroundMusic.pause();
  }
});

$(".button-reset").click(function () {
  $(".number").removeClass("marked-area");
  $(".part").html("");
  $(".bet-total").html("0.00");
  cashSum = cashSum + betSum;
  $(".cash-total").html(`${cashSum}.00`);
  betSum = 0;
});
//Chips placing end

var cashSumBefore = 0;
var winAmountOnScreen;

//Play button start
$(".button-spin").click(async function () {
  win = false;

  // Close analytics panels if they're open
  $('.analytics-left-sidebar').fadeOut(300).removeClass('visible');
  $('.analytics-footer-bar').fadeOut(300).removeClass('visible');
  $('.analytics-right-sidebar').fadeOut(300).removeClass('visible');
  $('body').removeClass('analytics-active');

  // Remove bet check and always proceed with spin
  // Stop the countdown when manually spinning
  clearInterval(countdownInterval);
  
  // 🎓 Clear tutorial highlights if user manually spins before tutorial completes
  clearAllTutorialHighlights();

  if (playAudio) {
    ballSpinSound.play();
  }
  winAmount = 0;
  winAmountOnScreen = 0;
  cashSumBefore = cashSum;

  // ⚠️ CRITICAL: Check for manually forced number FIRST (manual > preset > random)
  // Manual forced numbers should always override preset schedule
  let manualForcedNumber = null;
  let manualForcedFound = false;
  let presetNumberFound = false; // Track if preset number was found (from API or preset schedule)
  let drawNumberToCheck = 0; // Start with 0 - will be set by API
  let apiCurrentDraw = 0; // Store API current draw for debug panel
  let apiNextDraw = 0; // Store API next draw for debug panel
  
  // ⏰ CRITICAL: ALWAYS get draw number from SERVER API FIRST (server-time-based calculation)
  // DO NOT use local currentDrawNumber - it may be stale
  // This ensures all devices use the same draw number regardless of their local clock
  try {
    console.log('🎯 Fetching current draw number from API (server-time-based)...');
    const drawResponse = await fetch(`/slipp/api/get_current_draw.php?_cb=${Date.now()}`);
    
    if (drawResponse.ok) {
      const drawData = await drawResponse.json();
      if (drawData.status === 'success' && drawData.data) {
        // ⚠️ CRITICAL: Use NEXT draw number for preset lookup (users place bets on next draw)
        // The current_draw_number is the draw that's ending/completing
        // The next_draw_number is the draw that's about to start (this is what we need)
        apiCurrentDraw = parseInt(drawData.data.current_draw_number || 0);
        apiNextDraw = parseInt(drawData.data.next_draw_number || (apiCurrentDraw + 1));
        
        // 🐛 Update debug panel with draw numbers from API
        updateDebugPanel({
          currentDraw: apiCurrentDraw,
          nextDraw: apiNextDraw
        });
        
        // Use next draw for preset lookup (this is the draw that's about to start)
        if (!isNaN(apiNextDraw) && apiNextDraw > 0) {
          drawNumberToCheck = apiNextDraw;
          currentDrawNumber = apiNextDraw; // Update local variable to match API
          console.log('✅ Got NEXT draw number from API (server-time-based):', drawNumberToCheck, '(current:', apiCurrentDraw, ')');
          
          // Log server time info if available
          if (drawData.data.server_time) {
            console.log('⏰ Server time:', drawData.data.server_time.formatted, '(' + drawData.data.server_time.timezone + ')');
            console.log('⏰ Server time breakdown: ' + drawData.data.server_time.hour + ':' + drawData.data.server_time.minute + ':' + drawData.data.server_time.second);
          }
        } else {
          console.warn('⚠️ API returned invalid draw number:', apiNextDraw);
        }
      } else {
        console.warn('⚠️ API did not return next_draw_number, using current_draw_number + 1');
        apiCurrentDraw = parseInt(drawData.data.current_draw_number || 0);
        apiNextDraw = apiCurrentDraw + 1;
        if (!isNaN(apiCurrentDraw) && apiCurrentDraw > 0) {
          drawNumberToCheck = apiNextDraw;
          currentDrawNumber = drawNumberToCheck;
          console.log('✅ Using calculated next draw number:', drawNumberToCheck);
        }
        
        // 🐛 Update debug panel
        updateDebugPanel({
          currentDraw: apiCurrentDraw,
          nextDraw: apiNextDraw
        });
      }
    }
    
    // ⚠️ DO NOT use client-side time calculation - always trust server time
    // If API fails, that's an error condition, not a reason to use local time
    if (drawNumberToCheck === 0) {
      console.error('❌ CRITICAL: Failed to get draw number from server - cannot proceed safely');
      // Fallback only if absolutely necessary
      if (Array.isArray(rolledNumbersArray) && rolledNumbersArray.length > 0) {
        drawNumberToCheck = rolledNumbersArray.length + 1;
        console.warn('⚠️ Using fallback: draw number from roll history:', drawNumberToCheck);
      } else {
        drawNumberToCheck = 1;
        console.warn('⚠️ Using fallback: default draw number:', drawNumberToCheck);
      }
    }
    
  } catch (error) {
    console.error('❌ Error fetching draw number from API:', error);
    // Only use fallback if API completely fails
    if (Array.isArray(rolledNumbersArray) && rolledNumbersArray.length > 0) {
      drawNumberToCheck = rolledNumbersArray.length + 1;
      console.warn('⚠️ Using fallback: draw number from roll history:', drawNumberToCheck);
    } else {
      drawNumberToCheck = 1;
      console.warn('⚠️ Using fallback: default draw number:', drawNumberToCheck);
    }
  }
  
  // ⚠️ CRITICAL: Check for forced numbers (manual OR preset) BEFORE checking preset schedule separately
  // Manual forced numbers take priority over preset schedule
  // Preset schedule numbers take priority over random generation
  // Check BOTH current draw AND next draw (drawNumberToCheck might be current or next)
  if (drawNumberToCheck > 0) {
    try {
      // First check the current draw number
      console.log('🔍 Checking for forced number (manual or preset) for draw #' + drawNumberToCheck + '...');
      const forcedResponse = await fetch(`/slipp/api/direct_forced_number.php?draw_number=${drawNumberToCheck}&_cb=${Date.now()}`);
      if (forcedResponse.ok) {
        const forcedData = await forcedResponse.json();
        console.log('🔍 Forced number API response:', forcedData);
        
        // ✅ FIX: Accept BOTH manual forced numbers AND preset schedule numbers
        // Manual forced numbers (source='manual') take priority
        // Preset schedule numbers (source='preset_schedule') are also valid
        if (forcedData.status === 'success' && forcedData.has_forced_number) {
          const forcedNum = parseInt(forcedData.forced_number);
          
          // If it's a manual forced number, mark it as such
          if (forcedData.source === 'manual') {
            manualForcedNumber = forcedNum;
            manualForcedFound = true;
            console.log('✅ Found MANUAL forced number for draw #' + drawNumberToCheck + ':', forcedNum);
          } else if (forcedData.source === 'preset_schedule') {
            // Preset schedule number - use it but don't mark as manual
            console.log('✅ Found PRESET SCHEDULE number for draw #' + drawNumberToCheck + ':', forcedNum);
            presetNumberFound = true; // Mark as preset found
          } else {
            // Automatic forced number - use it as fallback
            console.log('✅ Found AUTOMATIC forced number for draw #' + drawNumberToCheck + ':', forcedNum);
          }
          
          // Set the roulette number regardless of source
          rouletteNumber = forcedNum;
          
          // Determine color
          if (forcedNum === 0) {
            rouletteColor = 'green';
          } else {
            const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
            rouletteColor = redNumbers.includes(forcedNum) ? 'red' : 'black';
          }
          
          console.log('✅ Using forced number from API:', forcedNum, '(' + rouletteColor + ')', '- Source:', forcedData.source || 'unknown');
          
          // 🐛 Update debug panel
          updateDebugPanel({
            forcedNumber: forcedNum,
            manualForced: forcedData.source === 'manual' ? forcedNum : null,
            presetNumber: forcedData.source === 'preset_schedule' ? forcedNum : null,
            manualFound: forcedData.source === 'manual',
            presetFound: forcedData.source === 'preset_schedule',
            drawToCheck: drawNumberToCheck
          });
        } else {
          console.log('ℹ️ No forced number found in API response for draw #' + drawNumberToCheck);
          
          // 🐛 Update debug panel
          updateDebugPanel({
            forcedNumber: null,
            manualForced: null,
            presetNumber: null,
            manualFound: false,
            presetFound: false
          });
        }
      }
      
      // Also check next draw in case the forced number was set for the next draw
      if (!manualForcedFound && !presetNumberFound && drawNumberToCheck < 480) {
        const nextDrawCheck = drawNumberToCheck + 1;
        console.log('🔍 Also checking next draw #' + nextDrawCheck + ' for forced number...');
        const nextForcedResponse = await fetch(`/slipp/api/direct_forced_number.php?draw_number=${nextDrawCheck}&_cb=${Date.now()}`);
        if (nextForcedResponse.ok) {
          const nextForcedData = await nextForcedResponse.json();
          if (nextForcedData.status === 'success' && nextForcedData.has_forced_number) {
            const nextForcedNum = parseInt(nextForcedData.forced_number);
            
            // If it's a manual forced number, mark it as such
            if (nextForcedData.source === 'manual') {
              manualForcedNumber = nextForcedNum;
              manualForcedFound = true;
            } else if (nextForcedData.source === 'preset_schedule') {
              presetNumberFound = true;
            }
            
            drawNumberToCheck = nextDrawCheck; // Update to use next draw
            rouletteNumber = nextForcedNum;
            
            // Determine color
            if (nextForcedNum === 0) {
              rouletteColor = 'green';
            } else {
              const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
              rouletteColor = redNumbers.includes(nextForcedNum) ? 'red' : 'black';
            }
            
            console.log('✅ Found forced number for next draw #' + nextDrawCheck + ':', nextForcedNum, '(' + rouletteColor + ')', '- Source:', nextForcedData.source || 'unknown');
          }
        }
      }
    } catch (e) {
      console.warn('⚠️ Error checking for forced number:', e);
    }
  }
  
  // 🎯 PRESET NUMBER MODE: Check for preset schedule number (only if no forced number found yet)
  // This takes priority over random generation to ensure preset schedule is followed
  // Note: presetNumberFound may already be set to true if we found a preset from direct_forced_number.php
  
  // Now check for preset number using the synchronized draw number (only if no forced number found yet)
  if (drawNumberToCheck > 0 && !manualForcedFound && !presetNumberFound) {
    try {
      console.log('🎯 Checking preset schedule for draw #' + drawNumberToCheck + '...');
      const presetResponse = await fetch(`/slipp/api/get_current_preset.php?draw_number=${drawNumberToCheck}&_cb=${Date.now()}`);
      
      if (presetResponse.ok) {
        const presetData = await presetResponse.json();
        
        if (presetData.status === 'success' && presetData.data && presetData.data.winning_number !== null && presetData.data.winning_number !== undefined) {
          // ⏰ If current draw's time has passed, prefer the NEXT draw (when timer hits 00:00)
          if (!presetData.data.time_valid) {
            console.log('⏰ Draw #' + drawNumberToCheck + ' time has passed (scheduled: ' + presetData.data.scheduled_time + '), checking next draw first...');
            const nextDrawNumber = drawNumberToCheck + 1;
            
            try {
              const nextPresetResponse = await fetch(`/slipp/api/get_current_preset.php?draw_number=${nextDrawNumber}&_cb=${Date.now()}`);
              if (nextPresetResponse.ok) {
                const nextPresetData = await nextPresetResponse.json();
                if (nextPresetData.status === 'success' && nextPresetData.data && nextPresetData.data.winning_number !== null && nextPresetData.data.winning_number !== undefined) {
                  if (nextPresetData.data.time_valid) {
                    // Use next draw's preset (timer just hit 00:00, new draw starting)
                    rouletteNumber = parseInt(nextPresetData.data.winning_number);
                    presetNumberFound = true;
                    drawNumberToCheck = nextDrawNumber;
                    currentDrawNumber = nextDrawNumber;
                    console.log('✅ Using next draw #' + nextDrawNumber + ' (timer hit 00:00):', rouletteNumber, '(' + nextPresetData.data.color + ')');
                    console.log('📋 Pattern:', nextPresetData.data.pattern || 'N/A');
                  } else {
                    // Next draw also expired, use current draw's preset as fallback
                    rouletteNumber = parseInt(presetData.data.winning_number);
                    presetNumberFound = true;
                    console.log('✅ Using current draw #' + drawNumberToCheck + ' (next draw also expired):', rouletteNumber, '(' + presetData.data.color + ')');
                  }
                } else {
                  // No preset for next draw, use current draw's preset
                  rouletteNumber = parseInt(presetData.data.winning_number);
                  presetNumberFound = true;
                  console.log('✅ Using current draw #' + drawNumberToCheck + ' (no preset for next):', rouletteNumber, '(' + presetData.data.color + ')');
                }
              } else {
                // Next draw check failed, use current draw's preset
                rouletteNumber = parseInt(presetData.data.winning_number);
                presetNumberFound = true;
                console.log('✅ Using current draw #' + drawNumberToCheck + ' (next draw check failed):', rouletteNumber, '(' + presetData.data.color + ')');
              }
            } catch (nextError) {
              console.warn('⚠️ Error checking next draw:', nextError);
              // Fallback: use current draw's preset
              rouletteNumber = parseInt(presetData.data.winning_number);
              presetNumberFound = true;
              console.log('✅ Using current draw #' + drawNumberToCheck + ' (error checking next):', rouletteNumber, '(' + presetData.data.color + ')');
            }
          } else {
            // Time is valid - use current draw's preset
            rouletteNumber = parseInt(presetData.data.winning_number);
            presetNumberFound = true;
            console.log('✅ Found preset number from schedule for draw #' + drawNumberToCheck + ':', rouletteNumber, '(' + presetData.data.color + ') - Time valid');
            
            // 🐛 Update debug panel
            updateDebugPanel({
              presetNumber: rouletteNumber,
              presetFound: true,
              forcedNumber: rouletteNumber
            });
          }
          
          if (presetNumberFound) {
            console.log('📋 Pattern:', presetData.data.pattern || 'N/A');
            console.log('⏰ Scheduled time:', presetData.data.scheduled_time || 'N/A', '- Current time:', new Date().toLocaleTimeString());
          }
        } else {
          console.log('ℹ️ No preset number found for draw #' + drawNumberToCheck + ' (status: ' + (presetData.status || 'unknown') + ') - ' + (presetData.message || 'Schedule not found'));
          // Log more details for debugging
          console.log('🔍 Preset API response:', presetData);
        }
      } else {
        console.warn('⚠️ Error fetching preset number:', presetResponse.status, presetResponse.statusText);
      }
    } catch (error) {
      console.warn('⚠️ Error checking preset schedule:', error);
    }
  }
  
  // ❌ RANDOM NUMBERS DISABLED: Only use preset schedule numbers (if no manual forced number)
  // Try multiple draw numbers when timer reaches 00:00 to find valid preset
  if (!presetNumberFound && !manualForcedFound) {
    console.log('⚠️ No preset number found for draw #' + drawNumberToCheck + ', trying adjacent draws...');
    
    // Try next draw first
    const nextDrawNumber = drawNumberToCheck + 1;
    try {
      const nextPresetResponse = await fetch(`/slipp/api/get_current_preset.php?draw_number=${nextDrawNumber}&_cb=${Date.now()}`);
      
      if (nextPresetResponse.ok) {
        const nextPresetData = await nextPresetResponse.json();
        
        if (nextPresetData.status === 'success' && nextPresetData.data && nextPresetData.data.winning_number !== null && nextPresetData.data.winning_number !== undefined) {
          // Only use if time is valid (don't use presets that have passed their time)
          if (nextPresetData.data.time_valid) {
            rouletteNumber = parseInt(nextPresetData.data.winning_number);
            presetNumberFound = true;
            drawNumberToCheck = nextDrawNumber; // Update to next draw number
            currentDrawNumber = nextDrawNumber; // Sync local variable
            console.log('✅ Found preset number from next draw #' + nextDrawNumber + ':', rouletteNumber, '(' + nextPresetData.data.color + ')');
            console.log('📋 Pattern:', nextPresetData.data.pattern || 'N/A');
          } else {
            console.log('ℹ️ Preset found for draw #' + nextDrawNumber + ' but time has passed (scheduled: ' + nextPresetData.data.scheduled_time + ')');
          }
        }
      }
    } catch (error) {
      console.warn('⚠️ Error checking next draw preset:', error);
    }
    
    // If still not found, try previous draw (in case API draw number is ahead of schedule)
    if (!presetNumberFound && drawNumberToCheck > 1) {
      const prevDrawNumber = drawNumberToCheck - 1;
      try {
        const prevPresetResponse = await fetch(`/slipp/api/get_current_preset.php?draw_number=${prevDrawNumber}&_cb=${Date.now()}`);
        
        if (prevPresetResponse.ok) {
          const prevPresetData = await prevPresetResponse.json();
          
          if (prevPresetData.status === 'success' && prevPresetData.data && prevPresetData.data.winning_number !== null && prevPresetData.data.winning_number !== undefined) {
            // Only use if time is valid
            if (prevPresetData.data.time_valid) {
              rouletteNumber = parseInt(prevPresetData.data.winning_number);
              presetNumberFound = true;
              drawNumberToCheck = prevDrawNumber;
              currentDrawNumber = prevDrawNumber;
              console.log('✅ Found preset number from previous draw #' + prevDrawNumber + ':', rouletteNumber, '(' + prevPresetData.data.color + ')');
              console.log('📋 Pattern:', prevPresetData.data.pattern || 'N/A');
            }
          }
        }
      } catch (error) {
        console.warn('⚠️ Error checking previous draw preset:', error);
      }
    }
    
    // If still no preset found, this is an error - DO NOT use fallback
    // ⚠️ CRITICAL: Only do this if no manual forced number was found
    // If manual forced number exists, we already have rouletteNumber set correctly
    if (!presetNumberFound && !manualForcedFound) {
      console.error('❌ CRITICAL: No preset number found for draw #' + drawNumberToCheck + ' (or adjacent draws)');
      console.error('❌ This should not happen - preset schedule must exist for all draws');
      console.error('❌ Wheel will NOT spin - preset schedule is required');
      
      // DO NOT use last rolled number as fallback - this causes wrong numbers to appear
      // Instead, show an error and prevent the spin
      alert('Error: No preset number found for draw #' + drawNumberToCheck + '. Please check the preset schedule.');
      return; // Stop the spin - do not proceed without a valid preset number
    } else if (manualForcedFound) {
      console.log('✅ Using manually forced number for animation:', rouletteNumber);
    } else if (presetNumberFound) {
      console.log('✅ Using preset schedule number for animation:', rouletteNumber);
    }
  }
  
  // ⚠️ CRITICAL: Final check - if manual forced number exists, ensure it's being used for animation
  // Do NOT allow preset check to overwrite manual forced number
  if (manualForcedFound && manualForcedNumber !== null) {
    rouletteNumber = manualForcedNumber;
    console.log('🔒 LOCKING: Using manually forced number for animation:', rouletteNumber, '(preventing preset override)');
  }
  
  // Log the final rouletteNumber that will be used for animation
  console.log('🎯 FINAL rouletteNumber for animation:', rouletteNumber, '(manualForced:', manualForcedFound, ', presetFound:', presetNumberFound, ')');
  
  // 🐛 Update debug panel with final state before save check
  updateDebugPanel({
    currentDraw: apiCurrentDraw || currentDrawNumber || 0,
    nextDraw: apiNextDraw || (apiCurrentDraw ? apiCurrentDraw + 1 : 0),
    forcedNumber: rouletteNumber || 0,
    presetNumber: presetNumberFound ? rouletteNumber : null,
    manualForced: manualForcedFound ? manualForcedNumber : null,
    drawToCheck: drawNumberToCheck || 0,
    lastSavedDraw: lastSavedDrawNumber || 0,
    willSave: false, // Will be updated below
    presetFound: presetNumberFound,
    manualFound: manualForcedFound
  });
  
  // 🎯 CHECK IF WE SHOULD SAVE THIS RESULT
  // Only save the FIRST result for each draw number
  // Multiple spins can happen, but only the first one counts and gets saved
  let shouldSaveResult = false;
  
  // Save if we have either a preset number OR a manually forced number
  if ((presetNumberFound || manualForcedFound) && rouletteNumber > 0 && drawNumberToCheck > 0) {
    // Check if we've already saved a result for this draw number
    if (drawNumberToCheck !== lastSavedDrawNumber) {
      // This is the first spin for this draw number - allow save
      // The API's time_valid check already handles whether the scheduled time has passed
      shouldSaveResult = true;
      console.log('✅ First result for draw #' + drawNumberToCheck + ' - will be saved');
      
      // 🐛 Update debug panel
      updateDebugPanel({
        willSave: true
      });
    } else {
      console.log('⏭️ Result already saved for draw #' + drawNumberToCheck + ' - discarding duplicate result (display only)');
      console.log('ℹ️ Wheel will spin and show the preset number, but result will not be saved to database');
      
      // 🐛 Update debug panel
      updateDebugPanel({
        willSave: false
      });
    }
  } else if (!presetNumberFound) {
    console.log('ℹ️ No preset number found - wheel will spin but result will not be saved');
    
    // 🐛 Update debug panel
    updateDebugPanel({
      willSave: false
    });
  } else {
    console.log('ℹ️ Invalid preset number or draw number - skipping save');
    
    // 🐛 Update debug panel
    updateDebugPanel({
      willSave: false
    });
  }
  
  // Store the draw number we're using (even if we won't save)
  const currentSpinDrawNumber = drawNumberToCheck;
  
  // 🚀 INSTANT STORAGE: Save winning number only if this is the first result for this draw number
  if (shouldSaveResult && rouletteNumber > 0) {
    console.log('⚡ INSTANT SAVE: Saving first result for draw #' + currentSpinDrawNumber + ':', rouletteNumber);
    // ⏰ CRITICAL: Pass the draw number to save function to ensure correct draw number is saved
    await saveWinningNumberInstantly(rouletteNumber, currentSpinDrawNumber);
    lastSavedDrawNumber = currentSpinDrawNumber; // Mark this draw number as saved
    
    // 🐛 Update debug panel after save
    updateDebugPanel({
      lastSavedDraw: lastSavedDrawNumber,
      willSave: false // Already saved
    });
    
    // ⏰ CRITICAL: After saving, set currentDrawNumber to NEXT draw (saved draw + 1)
    // This ensures analytics display shows the correct draw numbers
    // The API returns the CURRENT draw (which we just saved), so we need to increment it
    currentDrawNumber = currentSpinDrawNumber + 1;
    console.log('✅ Set currentDrawNumber to NEXT draw after save:', currentDrawNumber, '(just saved draw #' + currentSpinDrawNumber + ')');
    
    // 🎓 Reset tutorial tracking for the new draw
    tutorialHighlightedDrawNumber = 0;
    isTutorialRunning = false;
    clearAllTutorialHighlights();
    
    // Optionally sync with server to verify we're still in the correct time window
    // But we use savedDrawNumber + 1 as the primary source since we know we just saved it
    try {
      const syncResponse = await fetch(`/slipp/api/get_current_draw.php?_cb=${Date.now()}`);
      if (syncResponse.ok) {
        const syncData = await syncResponse.json();
        if (syncData.status === 'success' && syncData.data && syncData.data.current_draw_number) {
          const syncedDrawNumber = parseInt(syncData.data.current_draw_number);
          if (!isNaN(syncedDrawNumber) && syncedDrawNumber > 0) {
            // Server returns CURRENT draw, but we need NEXT draw for analytics
            // So if server says current is 223 (which we just saved), next should be 224
            // But if server is ahead (e.g., 224), we should use that + 1 = 225
            // For now, we trust our saved draw number + 1
            console.log('📊 Server reports current draw:', syncedDrawNumber, '- We just saved draw #' + currentSpinDrawNumber + ', so next is:', currentDrawNumber);
          }
        }
      }
    } catch (syncError) {
      // Non-critical: Just log the error, we already have currentDrawNumber set correctly
      console.warn('⚠️ Could not verify draw number with server (non-critical):', syncError);
    }
  } else {
    console.log('⏭️ SKIP SAVE: Duplicate or invalid result for draw #' + currentSpinDrawNumber + ' - display only (not saved)');
    // Don't increment draw number if we didn't save - allows multiple spins for same draw
  }

  // If there are bets placed, process them
  if (betSum > 0) {
    function areaBetCheck(columnName, columnNumber, equation, winMultiplier) {
      if ($(`.${columnName}${columnNumber} div`).hasClass("betting-chip")) {
        var areaChipCount = Number(jQuery(`.${columnName}${columnNumber}`).children(".betting-chip").attr("id"));
        if (equation) {
          win = true;
          winAmount = areaChipCount * winMultiplier;
          winAmountOnScreen = winAmountOnScreen + areaChipCount * winMultiplier;
        }
        cashSum = cashSum + winAmount;
        winAmount = 0;
      }
    }

    // Process bets only if they exist
    areaBetCheck("column-even", "", rouletteNumber % 2 == 0 && rouletteNumber != 0, 2);
    areaBetCheck("column-odd", "", rouletteNumber % 2 == 1, 2);

    areaBetCheck("column-1to18", "", rouletteNumber <= 18 && rouletteNumber != 0, 2);
    areaBetCheck("column-19to36", "", rouletteNumber >= 19, 2);

    areaBetCheck("column-1st12", "", rouletteNumber <= 12 && rouletteNumber != 0, 3);
    areaBetCheck("column-2nd12", "", rouletteNumber >= 13 && rouletteNumber <= 24, 3);
    areaBetCheck("column-3rd12", "", rouletteNumber >= 25, 3);

    areaBetCheck("bet2to1-1", "", rouletteNumber % 3 == 0 && rouletteNumber != 0, 3);
    areaBetCheck("bet2to1-2", "", rouletteNumber % 3 == 2, 3);
    areaBetCheck("bet2to1-3", "", rouletteNumber % 3 == 1, 3);

    for (let i = 0; i <= 36; i++) {
      //Black and red numbers check
      if (i < 18) {
        areaBetCheck("column-red", "", rouletteNumber == rouletteNumbersRed[i], 2);
        areaBetCheck("column-black", "", rouletteNumber == rouletteNumbersBlack[i], 2);
      }
      //Regular numbers check
      areaBetCheck("regular", i, rouletteNumber == i, 36);

      if (i > 0) {
        //Line check
        if (i > 3) {
          areaBetCheck("line", i, rouletteNumber == i || rouletteNumber == i - 3, 18);
        } else {
          areaBetCheck("line", i, rouletteNumber == i || rouletteNumber == 0, 18);
        }

        //Between check
        if (i % 3 == 1) {
          areaBetCheck("between", i, rouletteNumber == i || rouletteNumber == i + 1 || rouletteNumber == i + 2, 12);
        } else {
          areaBetCheck("between", i, rouletteNumber == i || rouletteNumber == i - 1, 18);
        }

        //Corners check
        if (i == 1) {
          areaBetCheck(
            "corner",
            i,
            rouletteNumber == i || rouletteNumber == i + 1 || rouletteNumber == i + 2 || rouletteNumber == i - 1,
            9
          );
        } else if (i == 2 || i == 3) {
          areaBetCheck("corner", i, rouletteNumber == i || rouletteNumber == i - 1 || rouletteNumber == 0, 12);
        } else if (i > 3) {
          areaBetCheck(
            "corner",
            i,
            rouletteNumber == i || rouletteNumber == i - 3 || rouletteNumber == i - 4 || rouletteNumber == i - 1,
            9
          );
        }
      }
    }
  }

  //Marking roulette wheel with number glow start
  var tableNumbersWithChips = [];
  for (let i = 0; i <= 36; i++) {
    if ($(`.number${i}`).hasClass("marked-area")) {
      tableNumbersWithChips.push(i);
    }
  }

  for (let a = 0; a <= 36; a++) {
    for (let b = 0; b < tableNumbersWithChips.length; b++) {
      if (tableNumbersWithChips[b] == rouletteNumbersArray[a]) {
        $(".number-glow-container").append(`<div class="number-glow number-glow${a}"></div>`);
        let rotateAngle = (360 / rouletteNumbersAmount) * a;
        document.querySelector(`.number-glow${a}`).style.transform = `rotate(${rotateAngle}deg)`;
      }
    }
  }
  //Marking roulette wheel with number glow ends

  let rouletteWheelAnimation = async () => {
    // ⚠️ CRITICAL: Final check RIGHT BEFORE animation to ensure correct number is used
    // This ensures we ALWAYS use the scheduled forced/preset number, even if it changed
    console.log('🎬 ANIMATION: Starting final forced/preset number check...');
    
    // Get current draw number first
    let drawNumberToUse = currentDrawNumber || 0;
    try {
      const drawResponse = await fetch(`/slipp/api/get_current_draw.php?_cb=${Date.now()}`);
      if (drawResponse.ok) {
        const drawData = await drawResponse.json();
        if (drawData.status === 'success' && drawData.data) {
          drawNumberToUse = parseInt(drawData.data.next_draw_number || drawData.data.current_draw_number || currentDrawNumber || 0);
        }
      }
    } catch (error) {
      console.warn('⚠️ ANIMATION: Could not fetch current draw, using local:', drawNumberToUse);
    }
    
    // Priority: Manual forced > Preset schedule > Automatic forced
    let finalNumber = rouletteNumber; // Default to current value
    let numberSource = 'unknown';
    
    if (drawNumberToUse > 0) {
      try {
        // Check for forced number (includes manual and preset)
        const forcedResponse = await fetch(`/slipp/api/direct_forced_number.php?draw_number=${drawNumberToUse}&_cb=${Date.now()}`);
        if (forcedResponse.ok) {
          const forcedData = await forcedResponse.json();
          if (forcedData.status === 'success' && forcedData.has_forced_number) {
            finalNumber = parseInt(forcedData.forced_number);
            numberSource = forcedData.source || 'unknown';
            console.log('✅ ANIMATION: Found forced number from API:', finalNumber, '- Source:', numberSource, '- Draw:', drawNumberToUse);
          } else {
            // No forced number, check preset schedule directly
            try {
              const presetResponse = await fetch(`/slipp/api/get_current_preset.php?draw_number=${drawNumberToUse}&_cb=${Date.now()}`);
              if (presetResponse.ok) {
                const presetData = await presetResponse.json();
                if (presetData.status === 'success' && presetData.data && presetData.data.winning_number !== null) {
                  finalNumber = parseInt(presetData.data.winning_number);
                  numberSource = 'preset_schedule';
                  console.log('✅ ANIMATION: Found preset number from API:', finalNumber, '- Draw:', drawNumberToUse);
                }
              }
            } catch (presetError) {
              console.warn('⚠️ ANIMATION: Error checking preset:', presetError);
            }
          }
        }
      } catch (forcedError) {
        console.warn('⚠️ ANIMATION: Error checking forced number:', forcedError);
      }
    }
    
    // Update rouletteNumber to the final number
    rouletteNumber = finalNumber;
    console.log('🎯 ANIMATION: Final number set to:', rouletteNumber, '- Source:', numberSource, '- Draw:', drawNumberToUse);
    
    // Validate rouletteNumber is valid (0-36)
    if (isNaN(rouletteNumber) || rouletteNumber < 0 || rouletteNumber > 36) {
      console.error('❌ ANIMATION: Invalid rouletteNumber:', rouletteNumber, '- Defaulting to 0');
      rouletteNumber = 0;
    }
    
    $(".ball-container").html('<div class="ball-spinner"><div class="ball"></div></div>');
    var ballContainer = document.querySelector(".ball-spinner");
    var sheet = document.createElement("style");

    // Find the index of the number in the roulette array
    var ballLandingNumber = 0; // Default to 0
    for (let i = 0; i < rouletteNumbersAmount; i++) {
      if (rouletteNumber == rouletteNumbersArray[i]) {
        ballLandingNumber = i;
        break;
      }
    }
    
    // Safety check: if number not found, default to index 0
    if (ballLandingNumber === undefined || ballLandingNumber < 0 || ballLandingNumber >= rouletteNumbersAmount) {
      console.warn('⚠️ ANIMATION: Number', rouletteNumber, 'not found in array, using index 0');
      ballLandingNumber = 0;
    }
    
    console.log('🎯 ANIMATION: Ball landing on index', ballLandingNumber, 'for number', rouletteNumber);

    sheet.textContent = `
    @-webkit-keyframes ball-container-animation{
      0%{
        transform: rotate(1440deg);
      }
      100%{
        transform: rotate(${(360 / rouletteNumbersAmount) * ballLandingNumber}deg);
      }`;

    ballContainer.appendChild(sheet);

    $(".roulette-wheel-container").addClass("z-index-visible").addClass("roulette-wheel-visible");
    $(".roulette-wheel-main").addClass("roulette-wheel-spin");
    $(".roulette-cross-shadow").addClass("roulette-wheel-spin");
    $(".roulette-cross").addClass("roulette-wheel-spin");
  };

  // Await the async function to ensure forced number is fetched before animation
  await rouletteWheelAnimation();

  const lastRollColor = () => {
    let lastRoll;
    for (let a = 0; a < 18; a++) {
      if (rouletteNumber == rouletteNumbersRed[a]) {
        lastRoll = "red";
      }
      if (rouletteNumber == rouletteNumbersBlack[a]) {
        lastRoll = "black";
      }
      if (rouletteNumber == 0) {
        lastRoll = "green";
      }
    }
    return lastRoll;
  };

  const lastRollDisplay = () => {
    // Get the color of the last roll
    const currentColor = lastRollColor();

    console.log('🎯 LAST ROLL DISPLAY: Processing new spin result:', rouletteNumber, 'with color:', currentColor);
    console.log('🎯 LAST ROLL DISPLAY: Current arrays before update:');
    console.log('  rolledNumbersArray:', rolledNumbersArray);
    console.log('  rolledNumbersColorArray:', rolledNumbersColorArray);

    // CRITICAL: Preserve existing historical data from data persistence system
    // Only initialize arrays if they are truly undefined/null, not if they're empty
    if (!Array.isArray(rolledNumbersArray)) {
      console.log('🎯 LAST ROLL DISPLAY: Initializing rolledNumbersArray (was not an array)');
      rolledNumbersArray = [];
    }
    if (!Array.isArray(rolledNumbersColorArray)) {
      console.log('🎯 LAST ROLL DISPLAY: Initializing rolledNumbersColorArray (was not an array)');
      rolledNumbersColorArray = [];
    }

    // Check if this number is already at the front (avoid duplicates from multiple calls)
    if (rolledNumbersArray.length === 0 || rolledNumbersArray[0] !== rouletteNumber) {
      console.log('🎯 LAST ROLL DISPLAY: Adding new result to arrays');

      // Add new roll to the beginning of the arrays
      rolledNumbersColorArray.unshift(currentColor);
      rolledNumbersArray.unshift(rouletteNumber);

      // Limit to 5 items (preserve historical data, just trim excess)
      if (rolledNumbersArray.length > 5) {
        rolledNumbersArray = rolledNumbersArray.slice(0, 5);
        rolledNumbersColorArray = rolledNumbersColorArray.slice(0, 5);
        console.log('🎯 LAST ROLL DISPLAY: Trimmed arrays to 5 items');
      }

      // Also update allSpins array for analytics consistency
      if (Array.isArray(window.allSpins)) {
        // Check if this number is already at the front of allSpins
        if (window.allSpins.length === 0 || window.allSpins[0] !== rouletteNumber) {
          window.allSpins.unshift(rouletteNumber);
          if (window.allSpins.length > 100) {
            window.allSpins = window.allSpins.slice(0, 100);
          }
          console.log('🎯 LAST ROLL DISPLAY: Updated allSpins array');
        }
      }

      // Save to database to persist the new result
      saveRollHistory();

      // Also save analytics data
      if (typeof saveAnalyticsData === 'function') {
        saveAnalyticsData();
      }

      // Update draw number display after each spin
      updateDrawNumberDisplay();
      console.log('🎯 LAST ROLL DISPLAY: Updated draw number display');
    } else {
      console.log('🎯 LAST ROLL DISPLAY: Number already at front, skipping duplicate');
    }

    // Log current state after update
    console.log('🎯 LAST ROLL DISPLAY: Arrays after update:');
    console.log('  rolledNumbersArray:', rolledNumbersArray);
    console.log('  rolledNumbersColorArray:', rolledNumbersColorArray);

    // ✅ CRITICAL FIX: Do NOT update DOM here - let the API refresh handle it
    // The API refresh (at 2 seconds after spin) will update the rolls container
    // with data from the database, ensuring consistency with the history display
    // This prevents mismatches between local arrays and database data
    console.log('🎯 LAST ROLL DISPLAY: Skipping DOM update - API refresh will handle it for consistency');

    return currentColor;
  };

  const resultsDisplay = () => {
    setTimeout(function () {
      $(".alert-spin-result").addClass("alert-message-visible");
      $(".results").addClass("alert-message-opacity");
    }, 5000);

    $(".results").addClass(`roll-${lastRollColor()}`);

    if (rouletteNumber < 19) {
      $(".high-low").html("LOW");
    } else {
      $(".high-low").html("HIGH");
    }

    if (rouletteNumber % 2 == 1) {
      $(".odd-even").html("ODD");
    } else {
      $(".odd-even").html("EVEN");
    }

    $(".roll-number").html(rouletteNumber);

    if (win == true) {
      $(".win-lose").html("YOU WON");
      $('.cashTotalAmount').html(cashSum);
    } else {
      $(".win-lose").html("");
    }

    if (winAmountOnScreen > 0) {
      $(".win-amount").html(`$${winAmountOnScreen}.00`);
    } else {
      $(".win-amount").html("");
    }
    bankSum = cashSum;
  };

  lastRollDisplay();
  resultsDisplay();

  // Auto-retract wheel after 15 seconds
  setTimeout(function() {
    // Update cash total if win
    if (win == true) {
      for (let i = 1; i <= 10; i++) {
        (function (i) {
          setTimeout(function () {
            cashSumBefore = cashSumBefore + winAmountOnScreen / 10;
            $(".cash-total").html(`${Math.round(cashSumBefore)}.00`);
          }, 50 * i);
        })(i);
      }

      setTimeout(function () {
        if (playAudio) {
          winChipsSound.play();
        }
      }, 1000);
    }

    // Hide result display first
    $(".results").removeClass("alert-message-opacity");
    $(".alert-spin-result").removeClass("alert-message-visible");

    // Retract wheel
    $(".roulette-wheel-container").removeClass("roulette-wheel-visible");
    setTimeout(function () {
      $(".roulette-wheel-container").removeClass("z-index-visible");
    }, 1000);

    // Reset animation classes
    $(".roulette-wheel-main").removeClass("roulette-wheel-spin");
    $(".roulette-cross-shadow").removeClass("roulette-wheel-spin");
    $(".roulette-cross").removeClass("roulette-wheel-spin");

    // Clean up
    $(".number-glow-container").html("");
    setTimeout(function () {
      $(".results").removeClass("roll-red roll-black roll-green");
    }, 1000);

    $(".ball-container").html("");
    $(".part").html("");

    $(".bet-total").html("0.00");
    betSum = 0;
    if (cashSum <= 0) {
      $(".alert-game-over").addClass("alert-message-visible");
    }

    // Record spin for analytics (but skip duplicate database saves since we already saved instantly)
    console.log('📊 ANALYTICS: Recording spin for analytics only (instant save already completed)');
    recordSpinForAnalyticsOnly(rouletteNumber);

    // ✅ CRITICAL: Refresh both displays from API after spin completes to ensure consistency
    // Wait a bit for the database to be updated, then refresh from API
    setTimeout(async () => {
      console.log('🔄 REFRESH: Refreshing both displays from API after spin...');
      try {
        const cacheBuster = Date.now();
        const response = await fetch(`/slipp/api/get_analytics_history.php?limit=5&_cb=${cacheBuster}`, {
          cache: 'no-store',
          headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
          }
        });
        
        if (response.ok) {
          const data = await response.json();
          if (data.status === 'success' && data.data && Array.isArray(data.data.draws) && data.data.draws.length > 0) {
            // Extract winning numbers from draws (most recent first)
            const recentDraws = data.data.draws.sort((a, b) => b.draw_number - a.draw_number).slice(0, 5);
            const recentNumbers = recentDraws.map(draw => parseInt(draw.winning_number));
            
            // Generate colors from the draws
            const recentColors = recentDraws.map(draw => {
              return draw.winning_color || 'black';
            });
            
            // Update global arrays
            window.rolledNumbersArray = recentNumbers;
            window.rolledNumbersColorArray = recentColors;
            
            // Update roulette-rolls-container DOM
            for (let i = 0; i < 5; i++) {
              const element = document.querySelector(`.roll${i + 1}`);
              if (element && i < recentNumbers.length) {
                const number = recentNumbers[i];
                const color = recentColors[i] || 'black';
                element.innerHTML = number;
                element.classList.remove('roll-red', 'roll-black', 'roll-green');
                element.classList.add(`roll-${color}`);
              }
            }
            
            console.log('✅ REFRESH: Updated rolls display from API:', recentNumbers);
          }
        }
      } catch (error) {
        console.warn('⚠️ REFRESH: Error refreshing displays:', error);
      }
      
      // Also refresh history display
      if (typeof displayNumberHistoryWithActualDrawNumbers === 'function') {
        displayNumberHistoryWithActualDrawNumbers();
      }
    }, 2000); // Wait 2 seconds for database to be updated

    // Show analytics panels - no need to call updateAnalytics() again as it's already called in recordSpinForAnalytics()
    setTimeout(function() {
      $('.analytics-left-sidebar').fadeIn(300).addClass('visible');
      $('.analytics-footer-bar').fadeIn(300).addClass('visible');
      $('.analytics-right-sidebar').fadeIn(300).addClass('visible');
      $('body').addClass('analytics-active');
    }, 1200);

    // Reset countdown timer to next 3-minute interval
    setTimeout(() => {
      // Calculate the next draw time based on real-time of day
      const nextDraw = calculateNextDrawTime();
      countdownTime = nextDraw.secondsRemaining;
      localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
      startCountdown();
    }, 1000);

    // Save detailed draw result if DetailedDrawManager is available
    if (window.DetailedDrawManager && typeof window.DetailedDrawManager.saveCurrentSpinResult === 'function') {
      try {
        window.DetailedDrawManager.saveCurrentSpinResult(rouletteNumber, {
          draw_number: currentDrawNumber,
          total_bets: betSum || 0,
          total_bet_amount: betSum || 0,
          total_payout: winAmountOnScreen || 0,
          sessionId: 'tv-display-session-' + new Date().toISOString().split('T')[0],
          dealerId: "Auto Dealer",
          tableId: "TV Display",
          player_count: 1, // Since this is a TV display, assuming 1 player
          notes: win ? "Win: $" + winAmountOnScreen : "No win"
        })
        .then(result => {
          console.log('Detailed draw result saved successfully', result);
        })
        .catch(error => {
          console.error('Error saving detailed draw result:', error);
        });
      } catch (error) {
        console.error('Error calling DetailedDrawManager:', error);
      }
    } else {
      console.warn('DetailedDrawManager not available - draw results will not be saved');
    }
  }, 15000);
});

/**
 * 🚀 INSTANT STORAGE FUNCTION
 * Saves winning number immediately after generation, before animation
 * @param {number} winningNumber - The winning number (0-36)
 * @param {number} drawNumber - The draw number to save with (MUST be provided)
 */
async function saveWinningNumberInstantly(winningNumber, drawNumber) {
  const saveStartTime = performance.now();

  try {
    // ⏰ CRITICAL: Validate draw number is provided
    if (!drawNumber || drawNumber <= 0) {
      console.error('❌ INSTANT SAVE: Invalid draw number provided:', drawNumber);
      throw new Error('Draw number is required and must be greater than 0');
    }

    console.log('⚡ INSTANT SAVE: Starting immediate storage for number:', winningNumber, 'draw #' + drawNumber);

    // Use high-performance storage if available
    if (window.HighPerformanceStorage && typeof window.HighPerformanceStorage.saveWinningNumber === 'function') {
      console.log('🚀 INSTANT SAVE: Using High-Performance Storage with draw #' + drawNumber);

      // ⏰ CRITICAL: Pass the draw number to the storage function
      const result = await window.HighPerformanceStorage.saveWinningNumber(winningNumber, drawNumber, {
        instant: true,
        source: 'tv_display_instant',
        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
      });

      const saveTime = performance.now() - saveStartTime;

      if (result.success) {
        console.log(`✅ INSTANT SAVE: SUCCESS in ${saveTime.toFixed(2)}ms - Number ${winningNumber} saved instantly!`);

        // Update currentDrawNumber if returned from the save result
        if (result.data && result.data.draw_number) {
          currentDrawNumber = result.data.draw_number;
          console.log('✅ Updated currentDrawNumber from save result:', currentDrawNumber);
        } else if (currentDrawNumber > 0) {
          // Increment draw number for next spin if not provided by save result
          currentDrawNumber++;
          console.log('✅ Incremented currentDrawNumber to:', currentDrawNumber);
        }

        // Dispatch instant save event for real-time monitoring
        dispatchInstantSaveEvent(winningNumber, saveTime, result);

      } else {
        console.error('❌ INSTANT SAVE: High-Performance Storage failed:', result.error);
        // Fallback to triple storage with draw number
        await fallbackToTripleStorage(winningNumber, saveStartTime, drawNumber);
      }

    } else {
      console.warn('⚠️ INSTANT SAVE: High-Performance Storage not available, using fallback');
      await fallbackToTripleStorage(winningNumber, saveStartTime, drawNumber);
    }

  } catch (error) {
    console.error('💥 INSTANT SAVE: Error during instant save:', error);
    await fallbackToTripleStorage(winningNumber, saveStartTime, drawNumber);
  }
}

/**
 * Fallback to triple storage system
 * @param {number} winningNumber - The winning number (0-36)
 * @param {number} saveStartTime - Performance timestamp
 * @param {number} drawNumber - The draw number to save with (optional, will use currentDrawNumber if not provided)
 */
async function fallbackToTripleStorage(winningNumber, saveStartTime, drawNumber = null) {
  try {
    // Use provided draw number or fall back to currentDrawNumber
    const finalDrawNumber = drawNumber || currentDrawNumber || 1;
    console.log('🔄 INSTANT SAVE: Falling back to Triple Storage with draw #' + finalDrawNumber);

    if (window.TripleStorage && typeof window.TripleStorage.saveSpin === 'function') {
      // ⏰ CRITICAL: Pass the draw number to TripleStorage.saveSpin
      const result = await window.TripleStorage.saveSpin(winningNumber, finalDrawNumber, {
        instant: true,
        source: 'tv_display_instant_fallback'
      });

      const saveTime = performance.now() - saveStartTime;
      console.log(`✅ INSTANT SAVE: Fallback SUCCESS in ${saveTime.toFixed(2)}ms`);

      // Dispatch instant save event
      dispatchInstantSaveEvent(winningNumber, saveTime, result);

    } else {
      console.error('❌ INSTANT SAVE: No storage systems available');
    }

  } catch (error) {
    console.error('💥 INSTANT SAVE: Fallback also failed:', error);
  }
}

/**
 * Dispatch instant save event for real-time monitoring
 */
function dispatchInstantSaveEvent(winningNumber, saveTime, result) {
  try {
    // Create custom event for instant save
    const instantSaveEvent = new CustomEvent('instantWinningNumberSaved', {
      detail: {
        winningNumber: winningNumber,
        saveTime: saveTime,
        timestamp: new Date().toISOString(),
        result: result,
        source: 'tv_display_instant',
        instant: true
      }
    });

    // Dispatch to window for monitoring systems
    window.dispatchEvent(instantSaveEvent);

    // Also send to parent window if in iframe
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({
        type: 'instantWinningNumberSaved',
        data: instantSaveEvent.detail
      }, '*');
    }

    console.log('📡 INSTANT SAVE: Event dispatched for real-time monitoring');

  } catch (error) {
    console.warn('⚠️ INSTANT SAVE: Failed to dispatch event:', error);
  }
}

// Hide alert messages when clicking anywhere
$(".alert-message-container").click(function () {
  $(".alert-message-container").removeClass("alert-message-visible");
  $(".results").removeClass("alert-message-opacity");
  // Hide alert-bets message as well
  $(".alert-bets").removeClass("alert-message-visible");
});

// Countdown Timer Functionality
let countdownInterval;
let countdownTime = 180; // 3 minutes in seconds (changed from 2 minutes)
const countdownDisplay = document.getElementById('countdown-timer');

// Function to calculate the next draw time based on real-time of day
function calculateNextDrawTime() {
  const now = new Date();
  const currentMinutes = now.getMinutes();
  const currentSeconds = now.getSeconds();

  // Calculate minutes until next 3-minute interval
  // We want draws to happen every 3 minutes: at :00, :03, :06, :09, etc.
  const minutesUntilNextDraw = 3 - (currentMinutes % 3);
  let secondsUntilNextDraw = (minutesUntilNextDraw * 60) - currentSeconds;

  // If we're exactly at a 3-minute mark, set for the next one
  if (secondsUntilNextDraw === 0 || secondsUntilNextDraw === 180) {
    secondsUntilNextDraw = 180;
  }

  console.log(`Next draw in ${Math.floor(secondsUntilNextDraw/60)}:${(secondsUntilNextDraw%60).toString().padStart(2, '0')} (${secondsUntilNextDraw} seconds)`);

  // Calculate the exact timestamp for the next draw
  const nextDrawTime = new Date(now.getTime() + (secondsUntilNextDraw * 1000));

  return {
    secondsRemaining: secondsUntilNextDraw,
    timestamp: nextDrawTime.getTime()
  };
}

function startCountdown() {
  // Clear any existing interval
  if (countdownInterval) {
    clearInterval(countdownInterval);
  }

  // Calculate the next draw time based on real-time of day
  const nextDraw = calculateNextDrawTime();
  countdownTime = nextDraw.secondsRemaining;

  // Store the exact timestamp of the next draw
  localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());

  console.log('Starting countdown with time:', countdownTime, 'seconds until', new Date(nextDraw.timestamp).toLocaleTimeString());

  // Set initial display
  updateCountdownDisplay();

  countdownInterval = setInterval(() => {
    // Calculate the exact time remaining based on the stored end time
    const savedEndTime = localStorage.getItem('countdownEndTime');
    const currentTime = new Date().getTime();

    if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
      const remainingTimeMs = parseInt(savedEndTime) - currentTime;
      countdownTime = Math.max(0, Math.floor(remainingTimeMs / 1000));
    } else {
      // Fallback to decrementing if no end time is saved
      countdownTime--;
    }

    updateCountdownDisplay();

    if (countdownTime <= 0) {
      clearInterval(countdownInterval);
      // Auto-spin when timer reaches zero - no bet check
      if (!$(".roulette-wheel-container").hasClass("roulette-wheel-visible")) {
        $(".button-spin").click();
      }

      // Reset timer after spin to the next 3-minute interval
      setTimeout(() => {
        const nextDraw = calculateNextDrawTime();
        countdownTime = nextDraw.secondsRemaining;
        localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
        saveRollHistory(); // Save the updated timer to database
        
        // 🎓 Reset tutorial tracking for the new draw
        tutorialHighlightedDrawNumber = 0;
        isTutorialRunning = false;
        clearAllTutorialHighlights();
        
        startCountdown();
      }, 16000); // Wait for spin animation to complete (15s display + 1s buffer)
    } else {
      // Save countdown time to database every 10 seconds to avoid excessive updates
      if (countdownTime % 10 === 0) {
        saveRollHistory();
      }
    }
  }, 1000);
}

function updateCountdownDisplay() {
  const minutes = Math.floor(countdownTime / 60);
  const seconds = countdownTime % 60;
  countdownDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

  // Add visual effect when time is running low
  if (countdownTime <= 10) {
    countdownDisplay.classList.add('timer-warning');

    // Show "No More Bets" alert during last 10 seconds
    $(".alert-bets .alert-message").text("NO MORE BETS");
    $(".alert-bets").addClass("alert-message-visible");
  } else {
    countdownDisplay.classList.remove('timer-warning');

    // Hide the alert when not in final countdown
    if ($(".alert-bets .alert-message").text() === "NO MORE BETS") {
      $(".alert-bets").removeClass("alert-message-visible");
    }
  }

  // Auto-close analytics panels 10 seconds before countdown ends
  if (countdownTime === 10) {
    $('.analytics-left-sidebar').fadeOut(300).removeClass('visible');
    $('.analytics-footer-bar').fadeOut(300).removeClass('visible');
    $('.analytics-right-sidebar').fadeOut(300).removeClass('visible');
    $('body').removeClass('analytics-active');
  }

  // If countdown reaches 0, hide the No More Bets message
  if (countdownTime === 0) {
    $(".alert-bets").removeClass("alert-message-visible");
  }

  // 🎓 TUTORIAL HIGHLIGHTING: Trigger at 60 seconds (after 2s delay) and clear at 30 seconds
  if (countdownTime === 60) {
    // Wait 2 seconds, then start tutorial highlighting
    setTimeout(() => {
      // Double-check we're still at 60 seconds (or close to it) before starting
      const savedEndTime = localStorage.getItem('countdownEndTime');
      const currentTime = new Date().getTime();
      if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
        const remainingTimeMs = parseInt(savedEndTime) - currentTime;
        const remainingTimeSec = Math.floor(remainingTimeMs / 1000);
        // Only start if we're still around 58-60 seconds (accounting for the 2s delay)
        if (remainingTimeSec >= 58 && remainingTimeSec <= 60) {
          startTutorialHighlighting();
        }
      }
    }, 2000);
  }

  // Clear tutorial highlights when timer reaches 30 seconds
  if (countdownTime === 30) {
    clearAllTutorialHighlights();
  }
}

/**
 * 🐛 Update debug panel with forced number and protection stats
 * @param {Object} data - Debug data to update
 */
function updateDebugPanel(data) {
  try {
    const panel = document.getElementById('debug-forced-number-panel');
    if (!panel) return;
    
    // Update individual fields if provided
    if (data.currentDraw !== undefined) {
      const el = document.getElementById('debug-current-draw');
      if (el) el.textContent = data.currentDraw || '--';
    }
    
    if (data.nextDraw !== undefined) {
      const el = document.getElementById('debug-next-draw');
      if (el) el.textContent = data.nextDraw || '--';
    }
    
    if (data.forcedNumber !== undefined) {
      const el = document.getElementById('debug-forced-number');
      if (el) {
        el.textContent = data.forcedNumber !== null && data.forcedNumber !== undefined ? data.forcedNumber : '--';
        el.style.color = data.forcedNumber > 0 || data.forcedNumber === 0 ? '#4CAF50' : '#888';
      }
    }
    
    if (data.presetNumber !== undefined) {
      const el = document.getElementById('debug-preset-number');
      if (el) {
        el.textContent = data.presetNumber !== null ? data.presetNumber : '--';
        el.style.color = data.presetNumber !== null ? '#9C27B0' : '#888';
      }
    }
    
    if (data.manualForced !== undefined) {
      const el = document.getElementById('debug-manual-forced');
      if (el) {
        el.textContent = data.manualForced !== null ? data.manualForced : '--';
        el.style.color = data.manualForced !== null ? '#F44336' : '#888';
      }
    }
    
    if (data.drawToCheck !== undefined) {
      const el = document.getElementById('debug-draw-to-check');
      if (el) el.textContent = data.drawToCheck || '--';
    }
    
    if (data.lastSavedDraw !== undefined) {
      const el = document.getElementById('debug-last-saved');
      if (el) el.textContent = data.lastSavedDraw || '--';
    }
    
    if (data.willSave !== undefined) {
      const el = document.getElementById('debug-will-save');
      if (el) {
        el.textContent = data.willSave ? 'YES' : 'NO';
        el.style.color = data.willSave ? '#4CAF50' : '#F44336';
      }
    }
    
    if (data.presetFound !== undefined) {
      const el = document.getElementById('debug-preset-found');
      if (el) {
        el.textContent = data.presetFound ? 'YES' : 'NO';
        el.style.color = data.presetFound ? '#4CAF50' : '#F44336';
      }
    }
    
    if (data.manualFound !== undefined) {
      const el = document.getElementById('debug-manual-found');
      if (el) {
        el.textContent = data.manualFound ? 'YES' : 'NO';
        el.style.color = data.manualFound ? '#4CAF50' : '#F44336';
      }
    }
  } catch (error) {
    console.warn('Error updating debug panel:', error);
  }
}

/**
 * Update debug panel's rolls container (sync with actual roulette-rolls-container)
 */
function updateDebugPanelRolls() {
  try {
    const debugRollsContainer = document.getElementById('debug-rolls-container');
    if (!debugRollsContainer) return;
    
    // Get the actual rolls from the main roulette-rolls-container
    const rolls = [];
    for (let i = 1; i <= 5; i++) {
      const rollElement = document.querySelector(`.roll${i}`);
      if (rollElement) {
        const number = rollElement.textContent.trim();
        const isRed = rollElement.classList.contains('roll-red');
        const isGreen = rollElement.classList.contains('roll-green');
        const isBlack = rollElement.classList.contains('roll-black');
        
        rolls.push({
          number: number || '--',
          color: isRed ? 'red' : (isGreen ? 'green' : (isBlack ? 'black' : 'black'))
        });
      } else {
        rolls.push({ number: '--', color: 'black' });
      }
    }
    
    // Update debug rolls display
    rolls.forEach((roll, index) => {
      const debugRoll = debugRollsContainer.querySelector(`.debug-roll${index + 1}`);
      if (debugRoll) {
        debugRoll.textContent = roll.number;
        debugRoll.classList.remove('debug-roll-red', 'debug-roll-black', 'debug-roll-green');
        debugRoll.classList.add(`debug-roll-${roll.color}`);
        
        // Apply color styles
        if (roll.color === 'red') {
          debugRoll.style.background = 'rgba(244, 67, 54, 0.3)';
          debugRoll.style.borderColor = 'rgba(244, 67, 54, 0.8)';
          debugRoll.style.color = '#f44336';
        } else if (roll.color === 'green') {
          debugRoll.style.background = 'rgba(76, 175, 80, 0.3)';
          debugRoll.style.borderColor = 'rgba(76, 175, 80, 0.8)';
          debugRoll.style.color = '#4CAF50';
        } else {
          debugRoll.style.background = 'rgba(33, 33, 33, 0.5)';
          debugRoll.style.borderColor = 'rgba(255, 255, 255, 0.3)';
          debugRoll.style.color = '#fff';
        }
      }
    });
    
    return rolls;
  } catch (error) {
    console.warn('Error updating debug panel rolls:', error);
    return [];
  }
}

/**
 * Update debug panel's history container (sync with actual number-history-container)
 */
function updateDebugPanelHistory() {
  try {
    const debugHistoryContainer = document.getElementById('debug-history-container');
    if (!debugHistoryContainer) return [];
    
    // Get the actual history from the main number-history-container
    const historyItems = [];
    const historyElements = document.querySelectorAll('#number-history .history-item');
    
    historyElements.forEach((item) => {
      const drawElement = item.querySelector('.history-draw');
      const numberElement = item.querySelector('.history-number');
      
      if (drawElement && numberElement) {
        const drawText = drawElement.textContent.trim();
        const number = numberElement.textContent.trim();
        const isRed = numberElement.classList.contains('red');
        const isGreen = numberElement.classList.contains('green');
        const isBlack = numberElement.classList.contains('black');
        
        historyItems.push({
          draw: drawText,
          number: number,
          color: isRed ? 'red' : (isGreen ? 'green' : (isBlack ? 'black' : 'black'))
        });
      }
    });
    
    // Update debug history display
    debugHistoryContainer.innerHTML = '';
    historyItems.forEach((item) => {
      const itemDiv = document.createElement('div');
      itemDiv.style.cssText = 'padding: 4px; border-radius: 4px; background: rgba(255,255,255,0.05); text-align: center;';
      
      const drawDiv = document.createElement('div');
      drawDiv.textContent = item.draw;
      drawDiv.style.cssText = 'font-size: 9px; color: #888; margin-bottom: 2px;';
      
      const numberDiv = document.createElement('div');
      numberDiv.textContent = item.number;
      numberDiv.style.cssText = `font-weight: bold; font-size: 12px; color: ${item.color === 'red' ? '#f44336' : (item.color === 'green' ? '#4CAF50' : '#fff')};`;
      
      itemDiv.appendChild(drawDiv);
      itemDiv.appendChild(numberDiv);
      debugHistoryContainer.appendChild(itemDiv);
    });
    
    return historyItems;
  } catch (error) {
    console.warn('Error updating debug panel history:', error);
    return [];
  }
}

/**
 * Sync rolls container with history API to ensure consistency
 */
async function syncRollsContainerWithHistory() {
  try {
    const cacheBuster = Date.now();
    const response = await fetch(`/slipp/api/get_analytics_history.php?limit=5&_cb=${cacheBuster}`, {
      method: 'GET',
      headers: {
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache',
        'Expires': '0'
      },
      cache: 'no-store'
    });

    if (response.ok) {
      const data = await response.json();
      if (data.status === 'success' && data.data && Array.isArray(data.data.draws) && data.data.draws.length > 0) {
        // Extract winning numbers from draws (most recent first)
        const recentDraws = data.data.draws.sort((a, b) => b.draw_number - a.draw_number).slice(0, 5);
        const recentNumbers = recentDraws.map(draw => parseInt(draw.winning_number));
        
        // Generate colors from the draws
        const rouletteNumbersRed = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        const colors = recentDraws.map(draw => {
          const num = parseInt(draw.winning_number);
          if (num === 0) return 'green';
          return rouletteNumbersRed.includes(num) ? 'red' : 'black';
        });
        
        // Update rolls container DOM (roll1 is most recent, roll5 is oldest)
        for (let i = 0; i < 5; i++) {
          const element = document.querySelector(`.roll${i + 1}`);
          if (element && i < recentNumbers.length) {
            const number = recentNumbers[i];
            const color = colors[i] || 'black';
            
            element.innerHTML = number;
            element.classList.remove('roll-red', 'roll-black', 'roll-green');
            element.classList.add(`roll-${color}`);
          }
        }
        
        // Update global arrays for consistency
        window.rolledNumbersArray = recentNumbers;
        window.rolledNumbersColorArray = colors;
        
        console.log('✅ SYNC: Rolls container synced with history API:', recentNumbers);
        return true;
      }
    }
    return false;
  } catch (error) {
    console.warn('⚠️ SYNC: Error syncing rolls container:', error);
    return false;
  }
}

/**
 * Check if rolls and history match
 */
function checkDebugPanelMatch() {
  try {
    const matchText = document.getElementById('debug-match-text');
    const matchStatus = document.getElementById('debug-match-status');
    if (!matchText || !matchStatus) return;
    
    // Get rolls (first 5 from history should match the 5 rolls)
    const rolls = updateDebugPanelRolls();
    const history = updateDebugPanelHistory();
    
    if (rolls.length === 0 || history.length === 0) {
      matchText.textContent = 'No data';
      matchText.style.color = '#888';
      matchStatus.style.background = 'rgba(255,255,255,0.1)';
      return;
    }
    
    // Compare first 5 numbers from history with rolls
    // History is most recent first (index 0 = most recent)
    // Rolls: roll1 = most recent, roll5 = oldest
    let matches = 0;
    let mismatches = [];
    
    for (let i = 0; i < Math.min(5, rolls.length, history.length); i++) {
      const rollNum = parseInt(rolls[i].number);
      const historyNum = parseInt(history[i].number);
      
      if (!isNaN(rollNum) && !isNaN(historyNum)) {
        if (rollNum === historyNum) {
          matches++;
        } else {
          mismatches.push({ index: i + 1, roll: rollNum, history: historyNum });
        }
      }
    }
    
    // Update match status
    if (mismatches.length === 0 && matches === Math.min(5, rolls.length, history.length)) {
      matchText.textContent = '✅ MATCHING';
      matchText.style.color = '#4CAF50';
      matchStatus.style.background = 'rgba(76, 175, 80, 0.2)';
      matchStatus.style.border = '1px solid rgba(76, 175, 80, 0.5)';
    } else if (mismatches.length > 0) {
      matchText.textContent = `❌ MISMATCH (${mismatches.length} of ${Math.min(5, rolls.length, history.length)})`;
      matchText.style.color = '#f44336';
      matchStatus.style.background = 'rgba(244, 67, 54, 0.2)';
      matchStatus.style.border = '1px solid rgba(244, 67, 54, 0.5)';
      console.warn('⚠️ Debug panel: Data mismatch detected:', mismatches);
      
      // Auto-sync if mismatch detected
      console.log('🔄 Auto-syncing rolls container with history API...');
      syncRollsContainerWithHistory();
    } else {
      matchText.textContent = '⚠️ PARTIAL';
      matchText.style.color = '#ff9800';
      matchStatus.style.background = 'rgba(255, 152, 0, 0.2)';
      matchStatus.style.border = '1px solid rgba(255, 152, 0, 0.5)';
    }
  } catch (error) {
    console.warn('Error checking debug panel match:', error);
  }
}

/**
 * 🔄 Real-time debug panel update - fetches live data from APIs
 */
let debugPanelUpdateInterval = null;

async function updateDebugPanelRealTime() {
  try {
    const panel = document.getElementById('debug-forced-number-panel');
    if (!panel) {
      console.log('🐛 Debug panel: Panel not found, skipping update');
      return; // Panel not found, don't update
    }
    
    console.log('🔄 Debug panel: Starting real-time update...');
    
    // Fetch current draw numbers
    try {
      const drawResponse = await fetch(`/slipp/api/get_current_draw.php?_cb=${Date.now()}`);
      if (drawResponse.ok) {
        const drawData = await drawResponse.json();
        console.log('🔄 Debug panel: Draw data received:', drawData);
        
        if (drawData.status === 'success' && drawData.data) {
          const currentDraw = parseInt(drawData.data.current_draw_number || 0);
          const nextDraw = parseInt(drawData.data.next_draw_number || (currentDraw + 1));
          
          console.log('🔄 Debug panel: Current Draw:', currentDraw, ', Next Draw:', nextDraw);
          
          // Update draw numbers immediately
          updateDebugPanel({
            currentDraw: currentDraw,
            nextDraw: nextDraw,
            drawToCheck: nextDraw
          });
          
          // Check for forced number for the next draw (the one that will be used)
          if (nextDraw > 0) {
            try {
              const forcedResponse = await fetch(`/slipp/api/direct_forced_number.php?draw_number=${nextDraw}&_cb=${Date.now()}`);
              if (forcedResponse.ok) {
                const forcedData = await forcedResponse.json();
                console.log('🔄 Debug panel: Forced data received:', forcedData);
                
                if (forcedData.status === 'success' && forcedData.has_forced_number) {
                  const forcedNum = parseInt(forcedData.forced_number);
                  
                  console.log('🔄 Debug panel: Found forced number:', forcedNum, 'Source:', forcedData.source);
                  
                  updateDebugPanel({
                    forcedNumber: forcedNum,
                    manualForced: forcedData.source === 'manual' ? forcedNum : null,
                    presetNumber: forcedData.source === 'preset_schedule' ? forcedNum : null,
                    manualFound: forcedData.source === 'manual',
                    presetFound: forcedData.source === 'preset_schedule',
                    drawToCheck: nextDraw
                  });
                } else {
                  // No forced number found - also check preset schedule directly
                  console.log('🔄 Debug panel: No forced number found, checking preset schedule...');
                  
                  try {
                    const presetResponse = await fetch(`/slipp/api/get_current_preset.php?draw_number=${nextDraw}&_cb=${Date.now()}`);
                    if (presetResponse.ok) {
                      const presetData = await presetResponse.json();
                      if (presetData.status === 'success' && presetData.data && presetData.data.winning_number) {
                        const presetNum = parseInt(presetData.data.winning_number);
                        console.log('🔄 Debug panel: Found preset number:', presetNum);
                        
                        updateDebugPanel({
                          forcedNumber: presetNum,
                          presetNumber: presetNum,
                          presetFound: true,
                          manualFound: false,
                          manualForced: null,
                          drawToCheck: nextDraw
                        });
                      } else {
                        // No preset either
                        updateDebugPanel({
                          forcedNumber: null,
                          manualForced: null,
                          presetNumber: null,
                          manualFound: false,
                          presetFound: false,
                          drawToCheck: nextDraw
                        });
                      }
                    }
                  } catch (presetError) {
                    console.warn('Debug panel: Error fetching preset:', presetError);
                    // No forced number found
                    updateDebugPanel({
                      forcedNumber: null,
                      manualForced: null,
                      presetNumber: null,
                      manualFound: false,
                      presetFound: false,
                      drawToCheck: nextDraw
                    });
                  }
                }
              }
            } catch (forcedError) {
              console.warn('Debug panel: Error fetching forced number:', forcedError);
            }
          }
        }
      } else {
        console.warn('Debug panel: Draw API response not OK:', drawResponse.status);
      }
    } catch (drawError) {
      console.warn('Debug panel: Error fetching draw numbers:', drawError);
    }
    
    // Update last saved draw (from global variable if available)
    if (typeof lastSavedDrawNumber !== 'undefined') {
      updateDebugPanel({
        lastSavedDraw: lastSavedDrawNumber || 0
      });
    }
    
    // Update willSave status (check if next draw is already saved)
    if (typeof lastSavedDrawNumber !== 'undefined' && typeof nextDraw !== 'undefined') {
      const willSave = (lastSavedDrawNumber !== nextDraw);
      updateDebugPanel({
        willSave: willSave
      });
    }
    
    // Sync rolls container with history API to ensure consistency
    await syncRollsContainerWithHistory();
    
    // Update rolls and history displays in debug panel (sync with actual displays)
    updateDebugPanelRolls();
    updateDebugPanelHistory();
    checkDebugPanelMatch();
    
    console.log('🔄 Debug panel: Real-time update completed');
    
  } catch (error) {
    console.warn('Debug panel: Error in real-time update:', error);
  }
}

/**
 * Start real-time debug panel updates
 */
function startDebugPanelRealTimeUpdates() {
  // Clear any existing interval
  if (debugPanelUpdateInterval) {
    clearInterval(debugPanelUpdateInterval);
  }
  
  // Update immediately (don't wait for first interval)
  updateDebugPanelRealTime();
  
  // Update every 500ms (0.5 seconds) for more responsive real-time statistics
  debugPanelUpdateInterval = setInterval(updateDebugPanelRealTime, 500);
  
  console.log('🔄 Debug panel real-time updates started (updating every 0.5 seconds)');
}

/**
 * Stop real-time debug panel updates
 */
function stopDebugPanelRealTimeUpdates() {
  if (debugPanelUpdateInterval) {
    clearInterval(debugPanelUpdateInterval);
    debugPanelUpdateInterval = null;
    console.log('🛑 Debug panel real-time updates stopped');
  }
}

/**
 * Clear all tutorial highlights from the board
 */
function clearAllTutorialHighlights() {
  // Remove white-area class from all numbers
  $(".number").removeClass("white-area");
  
  // Remove visual indicator from betting area buttons
  $(".bottom-column").removeClass("tutorial-highlight");
  
  // Clear any pending tutorial timeouts
  tutorialTimeoutIds.forEach(timeoutId => clearTimeout(timeoutId));
  tutorialTimeoutIds = [];
  
  // Reset tutorial running flag
  isTutorialRunning = false;
  
  console.log('🎓 Tutorial highlights cleared');
}

/**
 * Highlight a specific betting area for the tutorial
 * @param {string} areaName - The area to highlight (e.g., 'column-1st12', 'column-even', 'bet2to1-1')
 */
function highlightTutorialArea(areaName) {
  // Clear previous highlights first
  $(".number").removeClass("white-area");
  $(".bottom-column").removeClass("tutorial-highlight");
  
  console.log('🎓 Highlighting tutorial area:', areaName);
  
  // Highlight the betting area button itself
  $(`.${areaName}`).addClass("tutorial-highlight");
  
  // Highlight the appropriate numbers based on area type
  switch(areaName) {
    case 'column-1st12':
      // 1st 12: numbers 1-12
      for (let i = 1; i <= 12; i++) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'column-2nd12':
      // 2nd 12: numbers 13-24
      for (let i = 13; i <= 24; i++) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'column-3rd12':
      // 3rd 12: numbers 25-36
      for (let i = 25; i <= 36; i++) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'column-1to18':
      // 1 to 18: numbers 1-18
      for (let i = 1; i <= 18; i++) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'column-even':
      // Even: numbers 2, 4, 6, ..., 36
      for (let i = 2; i <= 36; i += 2) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'column-odd':
      // Odd: numbers 1, 3, 5, ..., 35
      for (let i = 1; i <= 35; i += 2) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'column-red':
      // Red: numbers in rouletteNumbersRed array
      rouletteNumbersRed.forEach(num => {
        $(`.number${num}`).addClass("white-area");
      });
      break;
      
    case 'column-black':
      // Black: numbers in rouletteNumbersBlack array
      rouletteNumbersBlack.forEach(num => {
        $(`.number${num}`).addClass("white-area");
      });
      break;
      
    case 'column-19to36':
      // 19 to 36: numbers 19-36
      for (let i = 19; i <= 36; i++) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'bet2to1-1':
      // 2 to 1-1: numbers where i % 3 == 0 (3, 6, 9, ..., 36)
      for (let i = 3; i <= 36; i += 3) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'bet2to1-2':
      // 2 to 1-2: numbers where i % 3 == 2 (2, 5, 8, ..., 35)
      for (let i = 2; i <= 35; i += 3) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    case 'bet2to1-3':
      // 2 to 1-3: numbers where i % 3 == 1 (1, 4, 7, ..., 34)
      for (let i = 1; i <= 34; i += 3) {
        $(`.number${i}`).addClass("white-area");
      }
      break;
      
    default:
      console.warn('Unknown tutorial area:', areaName);
  }
}

/**
 * Start the tutorial highlighting sequence
 * Highlights all betting areas one by one, 2 seconds each
 */
function startTutorialHighlighting() {
  // Get current draw number to track if tutorial already ran
  const currentDraw = currentDrawNumber || 1;
  
  // Check if tutorial already ran for this draw
  if (tutorialHighlightedDrawNumber === currentDraw) {
    console.log('🎓 Tutorial already ran for draw #' + currentDraw + ', skipping');
    return;
  }
  
  // Prevent multiple simultaneous tutorial sequences
  if (isTutorialRunning) {
    console.log('🎓 Tutorial already running, skipping');
    return;
  }
  
  // Mark tutorial as running and record the draw number
  isTutorialRunning = true;
  tutorialHighlightedDrawNumber = currentDraw;
  
  console.log('🎓 Starting tutorial highlighting for draw #' + currentDraw);
  
  // Define the sequence of areas to highlight (12 areas, 2 seconds each = 24 seconds total)
  const tutorialAreas = [
    'column-1st12',
    'column-2nd12',
    'column-3rd12',
    'column-1to18',
    'column-even',
    'column-red',
    'column-black',
    'column-odd',
    'column-19to36',
    'bet2to1-1',
    'bet2to1-2',
    'bet2to1-3'
  ];
  
  // Clear any existing timeouts
  tutorialTimeoutIds.forEach(timeoutId => clearTimeout(timeoutId));
  tutorialTimeoutIds = [];
  
  // Sequence through all areas with 2-second intervals
  tutorialAreas.forEach((areaName, index) => {
    const timeoutId = setTimeout(() => {
      highlightTutorialArea(areaName);
      
      // After the last area, clear highlights after 2 seconds
      if (index === tutorialAreas.length - 1) {
        const clearTimeoutId = setTimeout(() => {
          // Don't clear if timer has already reached 30 seconds (clearAllTutorialHighlights will handle it)
          const savedEndTime = localStorage.getItem('countdownEndTime');
          const currentTime = new Date().getTime();
          if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
            const remainingTimeMs = parseInt(savedEndTime) - currentTime;
            const remainingTimeSec = Math.floor(remainingTimeMs / 1000);
            // Only clear if we're still above 30 seconds
            if (remainingTimeSec > 30) {
              clearAllTutorialHighlights();
            }
          }
        }, 2000);
        tutorialTimeoutIds.push(clearTimeoutId);
      }
    }, index * 2000); // 2 seconds per area
    
    tutorialTimeoutIds.push(timeoutId);
  });
}

// Update visibilitychange event listener to save state when tab becomes invisible
document.addEventListener('visibilitychange', function() {
  if (document.visibilityState === 'visible') {
    // Page is visible again, recalculate timer from stored end time
    const savedEndTime = localStorage.getItem('countdownEndTime');
    const currentTime = new Date().getTime();

    if (savedEndTime && !isNaN(parseInt(savedEndTime))) {
      const remainingTimeMs = parseInt(savedEndTime) - currentTime;
      const remainingTimeSec = Math.floor(remainingTimeMs / 1000);

      // Clear existing interval
      if (countdownInterval) {
        clearInterval(countdownInterval);
      }

      if (remainingTimeSec > 0) {
        // Resume countdown from correct time
        countdownTime = remainingTimeSec;

        // Update the display immediately
        updateCountdownDisplay();

        // Restart the countdown interval
        countdownInterval = setInterval(() => {
          const currentTime = new Date().getTime();
          const remainingTimeMs = parseInt(savedEndTime) - currentTime;
          countdownTime = Math.max(0, Math.floor(remainingTimeMs / 1000));

          updateCountdownDisplay();

          if (countdownTime <= 0) {
            clearInterval(countdownInterval);
            // Auto-spin when timer reaches zero
            if (!$(".roulette-wheel-container").hasClass("roulette-wheel-visible")) {
              $(".button-spin").click();
            }

            // Reset timer after spin to the next 3-minute interval
            setTimeout(() => {
              const nextDraw = calculateNextDrawTime();
              countdownTime = nextDraw.secondsRemaining;
              localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
              saveRollHistory();
              
              // 🎓 Reset tutorial tracking for the new draw
              tutorialHighlightedDrawNumber = 0;
              isTutorialRunning = false;
              clearAllTutorialHighlights();
              
              startCountdown();
            }, 16000);
          }
        }, 1000);
      } else {
        // Time expired while tab was inactive
        if (!$(".roulette-wheel-container").hasClass("roulette-wheel-visible")) {
          $(".button-spin").click();
        }
        // Reset timer after triggering spin
        setTimeout(() => {
          const nextDraw = calculateNextDrawTime();
          countdownTime = nextDraw.secondsRemaining;
          localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
          startCountdown();

          // Save countdown time to database
          saveRollHistory();
        }, 16000);
      }
    } else {
      // No saved end time, calculate a new one based on real-time
      startCountdown();
    }
  } else {
    // Page is hidden, save state to database
    saveRollHistory();
  }
});

// Initialize countdown when document is ready
$(document).ready(function() {
  // First load game state from database
  console.log("Document ready - loading game state from database");

  // Initialize number frequency to ensure it's properly set up
  initializeNumberFrequency();

  // Define a function to load all game state
  async function initializeGameState() {
    try {
      // First load roll history
      await loadRollHistory();

      // Then load analytics data
      await loadAnalyticsData();

      // Ensure draw numbers are updated based on roll history
      syncDrawNumbersWithRollHistory();

      // Ensure number frequency is properly initialized after loading
      initializeNumberFrequency();

      // Start the countdown timer with the loaded value
      startCountdown();

      console.log("Game state successfully loaded and initialized");
    } catch (error) {
      console.error("Error initializing game state:", error);

      // Fall back to just starting the countdown if loading fails
      startCountdown();
    }
  }

  // Call the initialization function
  initializeGameState();

  // Also start countdown after manual spin by clicking on the result screen
  $(".alert-spin-result").click(function() {
    // Since we're handling this interaction as a manual spin restart,
    // calculate the next draw time based on real-time of day
    const nextDraw = calculateNextDrawTime();
    countdownTime = nextDraw.secondsRemaining;
    localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
    saveRollHistory(); // Save the current state to database
    startCountdown();
  });
});

// Helper function to sync draw numbers with roll history
function syncDrawNumbersWithRollHistory() {
  // Make sure we have proper arrays
  if (!Array.isArray(rolledNumbersArray)) rolledNumbersArray = [];

  // Update currentDrawNumber based on the roll history length if necessary
  // This ensures draw numbers are always at least as large as the number of rolls
  if (rolledNumbersArray.length > currentDrawNumber) {
    console.log('Syncing draw number with roll history:',
                'Current:', currentDrawNumber,
                'Roll history length:', rolledNumbersArray.length);
    currentDrawNumber = rolledNumbersArray.length;

    // Update the display
    updateDrawNumberDisplay();

    // Save the updated state
    saveAnalyticsData();
  }
}

// Analytics functionality
// Store up to 100 spins
let allSpins = [];
let numberFrequency = {};
let maxSpinsToStore = 100;
let currentDrawNumber = 0; // Initialize draw number counter

// Initialize number frequency if empty
function initializeNumberFrequency() {
  if (!numberFrequency || typeof numberFrequency !== 'object') {
    numberFrequency = {};
  }

  // Make sure all roulette numbers (0-36) have an entry
  for (let i = 0; i <= 36; i++) {
    if (numberFrequency[i] === undefined) {
      numberFrequency[i] = 0;
    }
  }

  console.log('Number frequency initialized:', numberFrequency);
}

// Save analytics data to database
function saveAnalyticsData() {
  try {
    // First save to localStorage as backup
    localStorage.setItem('allSpins', JSON.stringify(allSpins));
    localStorage.setItem('numberFrequency', JSON.stringify(numberFrequency));
    localStorage.setItem('currentDrawNumber', currentDrawNumber.toString());

    // Prepare data for server
    const analyticsData = {
      allSpins: allSpins,
      numberFrequency: numberFrequency,
      currentDrawNumber: currentDrawNumber
    };

    console.log('Saving analytics data to server:', analyticsData);

    // Send to server using fetch API
    fetch('/slipp/save_analytics.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(analyticsData)
    })
    .then(response => response.json())
    .then(data => {
      console.log('Analytics data saved to database:', data);
    })
    .catch(error => {
      console.error('Error saving analytics data to database:', error);
      // Database save failed, but we still have localStorage backup
    });

    // Also save countdown and draw numbers to database
    saveRollHistory();

    console.log('Analytics data saved');
  } catch (error) {
    console.error('Error saving analytics data:', error);
  }
}

// Load analytics data from database
async function loadAnalyticsData() {
  try {
    console.log('Loading analytics data from server...');
    const response = await fetch('/slipp/load_analytics.php');
    const data = await response.json();

    if (data.status === 'success') {
      console.log('Analytics data loaded from database:', data);

      try {
        // ✅ CRITICAL FIX: ALWAYS prioritize rolledNumbersArray as source of truth for analytics
        // This ensures analytics ALWAYS match the spin history bar display
        // Check if rolledNumbersArray is already loaded and populated (it should be if loadRollHistory was called first)
        if (Array.isArray(rolledNumbersArray) && rolledNumbersArray.length > 0) {
          console.log('🔄 ALWAYS syncing analytics from rolledNumbersArray (source of truth):', rolledNumbersArray);
          
          // Use rolledNumbersArray as allSpins (they should be the same)
          // Take last 100 spins (most recent first)
          allSpins = rolledNumbersArray.slice(0, 100);
          
          // Recalculate numberFrequency from actual spin history
          numberFrequency = {};
          for (let i = 0; i <= 36; i++) {
            numberFrequency[i] = 0;
          }
          
          // Count frequencies from actual spin history
          rolledNumbersArray.forEach(number => {
            if (number !== null && number !== undefined) {
              const num = parseInt(number);
              if (!isNaN(num) && num >= 0 && num <= 36) {
                numberFrequency[num] = (numberFrequency[num] || 0) + 1;
              }
            }
          });
          
          console.log('✅ Analytics ALWAYS synced from rolledNumbersArray. allSpins:', allSpins, 'numberFrequency:', numberFrequency);
          console.log('🛡️ Ignoring stale database data to prevent overwriting correct analytics');
        } else {
          // Fallback: Use data from API ONLY if rolledNumbersArray is completely empty
          console.log('⚠️ rolledNumbersArray not available, using API data (will sync when rolledNumbersArray loads)');
          
          if (data.all_spins) {
            const apiAllSpins = JSON.parse(data.all_spins);
            // Only use if rolledNumbersArray is still empty
            if (!Array.isArray(rolledNumbersArray) || rolledNumbersArray.length === 0) {
              allSpins = apiAllSpins;
              console.log('Loaded allSpins from API (temporary, will sync when rolledNumbersArray loads):', allSpins);
            } else {
              console.log('⚠️ Ignoring API all_spins because rolledNumbersArray is now available');
            }
          }

          if (data.number_frequency) {
            const apiNumberFrequency = JSON.parse(data.number_frequency);
            // Only use if rolledNumbersArray is still empty
            if (!Array.isArray(rolledNumbersArray) || rolledNumbersArray.length === 0) {
              numberFrequency = apiNumberFrequency;
              console.log('Loaded numberFrequency from API (temporary, will sync when rolledNumbersArray loads):', numberFrequency);
            } else {
              console.log('⚠️ Ignoring API number_frequency because rolledNumbersArray is now available');
            }
          }
        }

        if (data.current_draw_number !== undefined) {
          currentDrawNumber = parseInt(data.current_draw_number);
          console.log('Loaded currentDrawNumber:', currentDrawNumber);
        }

        // Initialize number frequency if empty
        if (Object.keys(numberFrequency).length === 0) {
          for (let i = 0; i <= 36; i++) {
            numberFrequency[i] = 0;
          }
        }

        // Update the analytics display
        updateAnalytics();
        updateDrawNumberDisplay();

        return true;
      } catch (parseError) {
        console.error('Error parsing analytics data:', parseError);
        loadAnalyticsFromLocalStorage();
      }
    } else {
      console.warn('Error loading analytics from server:', data.message);
      loadAnalyticsFromLocalStorage();
    }
  } catch (error) {
    console.error('Error loading analytics data from database:', error);
    loadAnalyticsFromLocalStorage();
  }

  return false;
}

// Fallback to load from localStorage
function loadAnalyticsFromLocalStorage() {
  console.log('Falling back to localStorage for analytics data');

  try {
    const savedAllSpins = localStorage.getItem('allSpins');
    const savedNumberFrequency = localStorage.getItem('numberFrequency');
    const savedCurrentDrawNumber = localStorage.getItem('currentDrawNumber');

    if (savedAllSpins) {
      allSpins = JSON.parse(savedAllSpins);
      console.log('Loaded allSpins from localStorage:', allSpins);
    }

    if (savedNumberFrequency) {
      numberFrequency = JSON.parse(savedNumberFrequency);
      console.log('Loaded numberFrequency from localStorage:', numberFrequency);
    }

    if (savedCurrentDrawNumber) {
      currentDrawNumber = parseInt(savedCurrentDrawNumber);
      console.log('Loaded currentDrawNumber from localStorage:', currentDrawNumber);
    }

    // Initialize number frequency if empty
    if (Object.keys(numberFrequency).length === 0) {
      for (let i = 0; i <= 36; i++) {
        numberFrequency[i] = 0;
      }
    }

    // Update displays
    updateAnalytics();
    updateDrawNumberDisplay();

    return true;
  } catch (error) {
    console.error('Error loading analytics from localStorage:', error);
    return false;
  }
}

// Update draw numbers display - REMOVED FROM TV DISPLAY
function updateDrawNumberDisplay() {
  console.log('Draw number display removed from TV interface, currentDrawNumber =', currentDrawNumber);

  // Make sure currentDrawNumber is in sync with the number of spins
  if (Array.isArray(rolledNumbersArray) && rolledNumbersArray.length > 0) {
    // Ensure currentDrawNumber is at least as large as the number of recorded spins
    if (currentDrawNumber < rolledNumbersArray.length) {
      currentDrawNumber = rolledNumbersArray.length;
      console.log('Corrected currentDrawNumber to match spin count:', currentDrawNumber);
    }
  }

  // Draw number display elements have been removed from TV display for cleaner presentation
  console.log('Draw number tracking maintained internally - Current draw:', currentDrawNumber);
}

// Helper function to sync analytics from rolledNumbersArray (source of truth)
function syncAnalyticsFromRollHistory() {
  // Always sync analytics from rolledNumbersArray if available
  if (Array.isArray(rolledNumbersArray) && rolledNumbersArray.length > 0) {
    console.log('🔄 Syncing analytics from rolledNumbersArray (source of truth):', rolledNumbersArray);
    
    // Use rolledNumbersArray as allSpins
    allSpins = rolledNumbersArray.slice(0, 100);
    
    // Recalculate numberFrequency from actual spin history
    numberFrequency = {};
    for (let i = 0; i <= 36; i++) {
      numberFrequency[i] = 0;
    }
    
    // Count frequencies from actual spin history
    rolledNumbersArray.forEach(number => {
      if (number !== null && number !== undefined) {
        const num = parseInt(number);
        if (!isNaN(num) && num >= 0 && num <= 36) {
          numberFrequency[num] = (numberFrequency[num] || 0) + 1;
        }
      }
    });
    
    console.log('✅ Analytics synced from rolledNumbersArray. allSpins:', allSpins, 'numberFrequency:', numberFrequency);
    return true;
  }
  return false;
}

// Function to update analytics display
function updateAnalytics() {
  // ✅ CRITICAL: Always sync from rolledNumbersArray before updating display
  // This ensures analytics NEVER show stale data from database
  syncAnalyticsFromRollHistory();
  
  console.log('Updating analytics display with data:', {
    allSpins: allSpins,
    numberFrequency: numberFrequency,
    currentDrawNumber: currentDrawNumber,
    rolledNumbersArray: rolledNumbersArray
  });

  if (!Array.isArray(allSpins) || allSpins.length === 0) {
    // Try to sync one more time from rolledNumbersArray
    if (syncAnalyticsFromRollHistory() && allSpins.length > 0) {
      console.log('✅ Analytics synced on demand from rolledNumbersArray');
    } else {
      console.warn('No spin data available for analytics');
      return;
    }
  }

  // Clear current displays
  $('#hot-numbers').empty();
  $('#cold-numbers').empty();
  $('#number-history').empty();

  // Calculate statistics
  let redCount = 0;
  let blackCount = 0;
  let greenCount = 0;
  let oddCount = 0;
  let evenCount = 0;
  let lowCount = 0; // 1-18
  let highCount = 0; // 19-36
  let firstDozenCount = 0; // 1-12
  let secondDozenCount = 0; // 13-24
  let thirdDozenCount = 0; // 25-36
  let firstColumnCount = 0; // 1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34
  let secondColumnCount = 0; // 2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35
  let thirdColumnCount = 0; // 3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36

  // Calculate frequencies
  allSpins.forEach(spin => {
    // Add to color counts
    if (spin === 0) {
      greenCount++;
    } else if (rouletteNumbersRed.includes(spin)) {
      redCount++;
    } else if (rouletteNumbersBlack.includes(spin)) {
      blackCount++;
    }

    // Skip zero for the remaining calculations
    if (spin === 0) return;

    // Add to odd/even counts
    if (spin % 2 === 1) {
      oddCount++;
    } else {
      evenCount++;
    }

    // Add to high/low counts
    if (spin <= 18) {
      lowCount++;
    } else {
      highCount++;
    }

    // Add to dozen counts
    if (spin <= 12) {
      firstDozenCount++;
    } else if (spin <= 24) {
      secondDozenCount++;
    } else {
      thirdDozenCount++;
    }

    // Add to column counts
    if (spin % 3 === 1) {
      firstColumnCount++;
    } else if (spin % 3 === 2) {
      secondColumnCount++;
    } else if (spin % 3 === 0) {
      thirdColumnCount++;
    }
  });

  const totalNonZeroSpins = allSpins.length - greenCount;

  // Update distribution percentages and counts
  $('#red-percentage').text(`${Math.round((redCount / allSpins.length) * 100)}%`);
  $('#red-count').text(`(${redCount})`);

  $('#black-percentage').text(`${Math.round((blackCount / allSpins.length) * 100)}%`);
  $('#black-count').text(`(${blackCount})`);

  $('#green-percentage').text(`${Math.round((greenCount / allSpins.length) * 100)}%`);
  $('#green-count').text(`(${greenCount})`);

  // Odd/Even (excluding zero)
  if (totalNonZeroSpins > 0) {
    $('#odd-percentage').text(`${Math.round((oddCount / totalNonZeroSpins) * 100)}%`);
    $('#odd-count').text(`(${oddCount})`);

    $('#even-percentage').text(`${Math.round((evenCount / totalNonZeroSpins) * 100)}%`);
    $('#even-count').text(`(${evenCount})`);

    // High/Low (excluding zero)
    $('#low-percentage').text(`${Math.round((lowCount / totalNonZeroSpins) * 100)}%`);
    $('#low-count').text(`(${lowCount})`);

    $('#high-percentage').text(`${Math.round((highCount / totalNonZeroSpins) * 100)}%`);
    $('#high-count').text(`(${highCount})`);

    // Dozens (excluding zero)
    $('#first-dozen-percentage').text(`${Math.round((firstDozenCount / totalNonZeroSpins) * 100)}%`);
    $('#first-dozen-count').text(`(${firstDozenCount})`);

    $('#second-dozen-percentage').text(`${Math.round((secondDozenCount / totalNonZeroSpins) * 100)}%`);
    $('#second-dozen-count').text(`(${secondDozenCount})`);

    $('#third-dozen-percentage').text(`${Math.round((thirdDozenCount / totalNonZeroSpins) * 100)}%`);
    $('#third-dozen-count').text(`(${thirdDozenCount})`);

    // Columns (excluding zero)
    $('#first-column-percentage').text(`${Math.round((firstColumnCount / totalNonZeroSpins) * 100)}%`);
    $('#first-column-count').text(`(${firstColumnCount})`);

    $('#second-column-percentage').text(`${Math.round((secondColumnCount / totalNonZeroSpins) * 100)}%`);
    $('#second-column-count').text(`(${secondColumnCount})`);

    $('#third-column-percentage').text(`${Math.round((thirdColumnCount / totalNonZeroSpins) * 100)}%`);
    $('#third-column-count').text(`(${thirdColumnCount})`);
  }

  // Debug frequency counters
  console.log('Number frequency data:', numberFrequency);

  // Prepare Hot & Cold numbers
  const sortedNumbers = Object.entries(numberFrequency)
    .map(([number, count]) => ({ number: parseInt(number), count: parseInt(count) || 0 }))
    .sort((a, b) => b.count - a.count);

  console.log('Sorted numbers by frequency:', sortedNumbers);

  // Display Hot numbers (top 5 most frequent that have appeared at least once)
  const hotNumbers = sortedNumbers.filter(item => item.count > 0).slice(0, 5);
  console.log('Hot numbers:', hotNumbers);

  if (hotNumbers.length === 0) {
    $('#hot-numbers').append('<div class="no-data">No hot numbers yet</div>');
  } else {
    hotNumbers.forEach(item => {
      const colorClass = item.number === 0 ? 'green' :
                        rouletteNumbersRed.includes(item.number) ? 'red' : 'black';
      $('#hot-numbers').append(`
        <div class="number-item ${colorClass}">
          ${item.number}
          <span class="number-count">${item.count}</span>
        </div>
      `);
    });
  }

  // Display Cold numbers (5 least frequent, including zero counts)
  // First get numbers that have appeared (count > 0), sorted by frequency (ascending)
  const appearedNumbers = sortedNumbers.filter(item => item.count > 0);
  
  // If we have more than 5 numbers that have appeared, take the 5 least frequent
  // Otherwise, include numbers with 0 counts to fill up to 5
  let coldNumbers;
  if (appearedNumbers.length > 5) {
    // More than 5 numbers have appeared - take the 5 least frequent
    coldNumbers = appearedNumbers.slice(-5).reverse();
  } else {
    // Fewer than 5 numbers have appeared - include zeros to show what hasn't appeared
    const zeroCountNumbers = sortedNumbers.filter(item => item.count === 0);
    // Combine least frequent appeared numbers with zero count numbers
    const leastFrequentAppeared = appearedNumbers.slice().reverse();
    const combinedCold = [...leastFrequentAppeared, ...zeroCountNumbers].slice(0, 5);
    coldNumbers = combinedCold;
  }

  console.log('Cold numbers (least frequent):', coldNumbers);

  if (coldNumbers.length === 0) {
    $('#cold-numbers').append('<div class="no-data">No cold numbers yet</div>');
  } else {
    coldNumbers.forEach(item => {
      const colorClass = item.number === 0 ? 'green' :
                        rouletteNumbersRed.includes(item.number) ? 'red' : 'black';
      $('#cold-numbers').append(`
        <div class="number-item ${colorClass}">
          ${item.number}
          <span class="number-count">${item.count}</span>
        </div>
      `);
    });
  }

  // Display number history (last 8 spins in reverse order - newest first)
  // ⏰ CRITICAL: Fetch actual draw numbers from database instead of calculating
  // This ensures we show the correct draw numbers that were actually saved
  displayNumberHistoryWithActualDrawNumbers(allSpins.slice(0, 8));

  console.log('Analytics display updated');
}

/**
 * ⏰ CRITICAL: Display number history with ACTUAL draw numbers from database
 * This fetches the actual draw numbers that were saved, not calculated ones
 */
// Prevent multiple simultaneous calls to displayNumberHistoryWithActualDrawNumbers
let isUpdatingHistory = false;

async function displayNumberHistoryWithActualDrawNumbers(numbers) {
  // Prevent race conditions - if already updating, wait for it to complete
  if (isUpdatingHistory) {
    console.log('⏳ History update already in progress, skipping duplicate call');
    return;
  }
  
  isUpdatingHistory = true;
  
  try {
    // ⏰ CRITICAL: Ensure container is cleared before appending (in case function called multiple times)
    // Note: updateAnalytics() should have already cleared it, but we'll clear it here too for safety
    $('#number-history').empty();
    
    // Fetch last 8 draws from new analytics_history API (uses preset_schedule and server time)
    // Add cache-busting and ensure no-cache headers
    const cacheBuster = Date.now();
    const response = await fetch(`/slipp/api/get_analytics_history.php?limit=8&_cb=${cacheBuster}`, {
      cache: 'no-store',
      headers: {
        'Cache-Control': 'no-cache, no-store, must-revalidate',
        'Pragma': 'no-cache'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      
      if (data.status === 'success' && data.data && Array.isArray(data.data.draws) && data.data.draws.length > 0) {
        // Use analytics_history draws (have actual draw numbers from preset_schedule and server time)
        console.log('✅ Using analytics history from new table:', data.data);
        console.log('✅ Current draw number:', data.data.current_draw_number, 'Server time:', data.data.server_time);
        
        // ⏰ CRITICAL: Sort by draw_number DESC first to ensure correct order
        const sortedData = data.data.draws.sort((a, b) => b.draw_number - a.draw_number);
        
        // ⏰ CRITICAL: Only show unique draws (avoid duplicates)
        // Use a Map to ensure we keep the most recent entry for each draw number
        const uniqueDrawsMap = new Map();
        
        sortedData.forEach((draw) => {
          const drawNum = draw.draw_number;
          // If we haven't seen this draw number, or this one is more recent (shouldn't happen with new query, but safety check)
          if (!uniqueDrawsMap.has(drawNum)) {
            uniqueDrawsMap.set(drawNum, draw);
          }
        });
        
        // Convert map to array and sort by draw_number DESC (most recent first)
        const uniqueDraws = Array.from(uniqueDrawsMap.values())
          .sort((a, b) => b.draw_number - a.draw_number)
          .slice(0, 8); // Limit to 8 most recent unique draws
        
        console.log('✅ Displaying unique draws:', uniqueDraws.map(d => `#${d.draw_number}: ${d.winning_number}`));
        
        // ⏰ CRITICAL: Clear container completely and verify it's empty before appending
        const historyContainer = $('#number-history');
        historyContainer.empty();
        
        // Double-check: Verify container is actually empty (prevent duplicates from partial clears)
        if (historyContainer.children().length > 0) {
          console.warn('⚠️ Container not empty after clear, forcing empty');
          historyContainer.html('');
        }
        
        // Build HTML string first, then append once (more efficient and prevents partial renders)
        let historyHTML = '';
        uniqueDraws.forEach((draw, index) => {
          const number = draw.winning_number;
          const drawNum = draw.draw_number;
          const colorClass = number === 0 ? 'green' :
                            rouletteNumbersRed.includes(number) ? 'red' : 'black';

          historyHTML += `
            <div class="history-item">
              <div class="history-draw">Draw #${drawNum}</div>
              <div class="history-number ${colorClass}">${number}</div>
            </div>
          `;
        });
        
        // Append all at once
        historyContainer.html(historyHTML);
        
        // Final verification: Check for duplicates
        const displayedDrawNumbers = [];
        historyContainer.find('.history-item').each(function() {
          const drawText = $(this).find('.history-draw').text();
          const drawNum = drawText.replace('Draw #', '');
          if (displayedDrawNumbers.includes(drawNum)) {
            console.error('❌ DUPLICATE DETECTED:', drawNum);
            $(this).remove(); // Remove duplicate
          } else {
            displayedDrawNumbers.push(drawNum);
          }
        });
        
        isUpdatingHistory = false;
        return; // Exit early if we got data from database
      } else {
        console.warn('⚠️ API returned empty or invalid data:', data);
      }
    } else {
      console.error('❌ Failed to fetch recent draws:', response.status, response.statusText);
    }
    
    
    // Fallback: Calculate draw numbers (for backward compatibility)
    console.warn('⚠️ Could not fetch actual draw numbers, falling back to calculation');
    const historyToShow = numbers || [];
    let baseDrawNumber = currentDrawNumber || 1;
    
    // If the base is too low to show 8 sequential draws, adjust it
    if (baseDrawNumber <= historyToShow.length) {
      baseDrawNumber = historyToShow.length + 1;
    }
    
    // Clear container before appending fallback data
    $('#number-history').empty();
    
    historyToShow.forEach((number, index) => {
      const colorClass = number === 0 ? 'green' :
                        rouletteNumbersRed.includes(number) ? 'red' : 'black';
      const drawNum = baseDrawNumber - (index + 1);

      $('#number-history').append(`
        <div class="history-item">
          <div class="history-draw">Draw #${drawNum}</div>
          <div class="history-number ${colorClass}">${number}</div>
        </div>
      `);
    });
    
    isUpdatingHistory = false;
    
  } catch (error) {
    console.error('❌ Error fetching actual draw numbers:', error);
    isUpdatingHistory = false;
    
    // Fallback to calculation on error
    const historyToShow = numbers || [];
    let baseDrawNumber = currentDrawNumber || 1;
    
    if (baseDrawNumber <= historyToShow.length) {
      baseDrawNumber = historyToShow.length + 1;
    }
    
    // Clear container before appending error fallback data
    $('#number-history').empty();
    
    historyToShow.forEach((number, index) => {
      const colorClass = number === 0 ? 'green' :
                        rouletteNumbersRed.includes(number) ? 'red' : 'black';
      const drawNum = baseDrawNumber - (index + 1);

      $('#number-history').append(`
        <div class="history-item">
          <div class="history-draw">Draw #${drawNum}</div>
          <div class="history-number ${colorClass}">${number}</div>
        </div>
      `);
    });
  }
}

// Analytics-only function (no database save since instant save already completed)
function recordSpinForAnalyticsOnly(number) {
  console.log('📊 ANALYTICS ONLY: Updating local analytics for number:', number, '(database already saved instantly)');

  try {
    // Update local analytics for display only
    updateLocalAnalyticsFromInstantSave(number);

    // Update displays
    updateAnalytics();
    updateDrawNumberDisplay();

    console.log('✅ ANALYTICS ONLY: Local analytics updated successfully');

  } catch (error) {
    console.error('❌ ANALYTICS ONLY: Error updating local analytics:', error);
  }
}

// Enhanced spin result function using Safe Spin API with database safeguards
async function recordSpinForAnalytics(number) {
  console.log('🛡️ SAFE RECORDING: Number:', number, 'Using Safe Spin API with Database Safeguards');

  try {
    // Use Safe Spin API with database-level safeguards
    const response = await fetch('/slipp/php/safe_spin_api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        winning_number: number,
        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
      })
    });

    const result = await response.json();

    if (result.status === 'success') {
      console.log('✅ SAFE RECORDING: Spin saved with safeguards', result);

      // Update local variables to match what was saved
      currentDrawNumber = result.data.draw_number;

      // Update local analytics for display
      updateLocalAnalyticsFromSafeSave(number, result.data.draw_number);

      console.log('🛡️ RECORDING COMPLETE: Safe draw number:', result.data.draw_number, '| Safeguards Active:', result.data.safeguards_active);

    } else {
      throw new Error(result.message || 'Safe spin API failed');
    }

    // Update displays
    updateAnalytics();
    updateDrawNumberDisplay();

  } catch (error) {
    console.error('❌ SAFE RECORDING ERROR:', error);

    // Fallback to legacy system on error
    console.warn('⚠️ Falling back to legacy analytics system');
    updateLegacyAnalytics(number);
    updateAnalytics();
    updateDrawNumberDisplay();
  }
}

// Helper function to update local analytics from instant save (no database interaction)
function updateLocalAnalyticsFromInstantSave(number) {
  console.log('📊 Updating local analytics from instant save (display only)');

  // Make sure numberFrequency is initialized
  initializeNumberFrequency();

  // Add to beginning of array (newest first)
  allSpins.unshift(number);
  console.log('Added number to allSpins:', number);

  // Increment frequency counter
  numberFrequency[number]++;
  console.log('Incremented frequency for number', number, 'to', numberFrequency[number]);

  // Limit the number of stored spins
  if (allSpins.length > maxSpinsToStore) {
    const removedNumber = allSpins.pop();
    numberFrequency[removedNumber]--;
    console.log('Removed oldest spin number', removedNumber, ', new frequency:', numberFrequency[removedNumber]);
  }

  console.log('📊 Local analytics updated for instant save (display only)');
}

// Helper function to update local analytics from safe save result
function updateLocalAnalyticsFromSafeSave(number, drawNumber) {
  console.log('🛡️ Updating local analytics from safe save with safeguards');

  // Make sure numberFrequency is initialized
  initializeNumberFrequency();

  // Add to beginning of array (newest first)
  allSpins.unshift(number);
  console.log('Added number to allSpins:', number);

  // Increment frequency counter
  numberFrequency[number]++;
  console.log('Incremented frequency for number', number, 'to', numberFrequency[number]);

  // Limit the number of stored spins
  if (allSpins.length > maxSpinsToStore) {
    const removedNumber = allSpins.pop();
    numberFrequency[removedNumber]--;
    console.log('Removed oldest spin number', removedNumber, ', new frequency:', numberFrequency[removedNumber]);
  }

  console.log('🛡️ Local analytics updated for SAFE draw #', drawNumber);
}

// Helper function to update local analytics from sequential save result
function updateLocalAnalyticsFromSequentialSave(number, drawNumber) {
  console.log('📊 Updating local analytics from sequential save');

  // Make sure numberFrequency is initialized
  initializeNumberFrequency();

  // Add to beginning of array (newest first)
  allSpins.unshift(number);
  console.log('Added number to allSpins:', number);

  // Increment frequency counter
  numberFrequency[number]++;
  console.log('Incremented frequency for number', number, 'to', numberFrequency[number]);

  // Limit the number of stored spins
  if (allSpins.length > maxSpinsToStore) {
    const removedNumber = allSpins.pop();
    numberFrequency[removedNumber]--;
    console.log('Removed oldest spin number', removedNumber, ', new frequency:', numberFrequency[removedNumber]);
  }

  console.log('✅ Local analytics updated for draw #', drawNumber);
}

// Helper function to update local analytics from triple storage result
function updateLocalAnalyticsFromTripleStorage(number) {
  console.log('📊 Updating local analytics from triple storage');

  // Make sure numberFrequency is initialized
  initializeNumberFrequency();

  // Add to beginning of array (newest first)
  allSpins.unshift(number);
  console.log('Added number to allSpins:', number);

  // Increment frequency counter
  numberFrequency[number]++;
  console.log('Incremented frequency for number', number, 'to', numberFrequency[number]);

  // Limit the number of stored spins
  if (allSpins.length > maxSpinsToStore) {
    const removedNumber = allSpins.pop();
    numberFrequency[removedNumber]--;
    console.log('Removed oldest spin number', removedNumber, ', new frequency:', numberFrequency[removedNumber]);
  }
}

// Legacy analytics update function (fallback)
function updateLegacyAnalytics(number) {
  console.log('📊 Using legacy analytics system');

  // Make sure numberFrequency is initialized
  initializeNumberFrequency();

  // Add to beginning of array (newest first)
  allSpins.unshift(number);
  console.log('Added number to allSpins:', number);

  // Increment frequency counter
  numberFrequency[number]++;
  console.log('Incremented frequency for number', number, 'to', numberFrequency[number]);

  // Limit the number of stored spins
  if (allSpins.length > maxSpinsToStore) {
    const removedNumber = allSpins.pop();
    numberFrequency[removedNumber]--;
    console.log('Removed oldest spin number', removedNumber, ', new frequency:', numberFrequency[removedNumber]);
  }

  // Save analytics data and update database
  saveAnalyticsData();

  // Also explicitly save roll history to ensure draw numbers are saved
  saveRollHistory();

  // Increment draw number AFTER updating analytics and displays
  currentDrawNumber++;
  console.log('Incremented draw number to:', currentDrawNumber);
}

// Show/hide analytics panels - Three-part layout
$('#analytics-button').on('click', function() {
  // Show all three analytics panels
  $('.analytics-left-sidebar').fadeIn(300).addClass('visible');
  $('.analytics-footer-bar').fadeIn(300).addClass('visible');
  $('.analytics-right-sidebar').fadeIn(300).addClass('visible');
  $('body').addClass('analytics-active');
  updateAnalytics();
});

// Close buttons for each panel
$('.left-close').on('click', function() {
  $('.analytics-left-sidebar').fadeOut(300).removeClass('visible');
  checkAndRemoveAnalyticsActive();
});

$('.footer-close').on('click', function() {
  $('.analytics-footer-bar').fadeOut(300).removeClass('visible');
  checkAndRemoveAnalyticsActive();
});

$('.right-close').on('click', function() {
  $('.analytics-right-sidebar').fadeOut(300).removeClass('visible');
  checkAndRemoveAnalyticsActive();
});

// Close all analytics panels (for backward compatibility)
$('.analytics-close').on('click', function() {
  $('.analytics-left-sidebar').fadeOut(300).removeClass('visible');
  $('.analytics-footer-bar').fadeOut(300).removeClass('visible');
  $('.analytics-right-sidebar').fadeOut(300).removeClass('visible');
  $('body').removeClass('analytics-active');
});

// Helper function to check if all panels are closed and remove analytics-active class
function checkAndRemoveAnalyticsActive() {
  setTimeout(function() {
    const isAnyVisible = $('.analytics-left-sidebar').is(':visible') ||
                        $('.analytics-footer-bar').is(':visible') ||
                        $('.analytics-right-sidebar').is(':visible');
    if (!isAnyVisible) {
      $('body').removeClass('analytics-active');
    }
  }, 350); // Wait for fade out animation to complete
}

// Keyboard shortcut to toggle analytics panels (A key)
$(document).on('keydown', function(e) {
  if (e.key === 'a' || e.key === 'A') {
    // Check if any panel is visible
    const isVisible = $('.analytics-left-sidebar').is(':visible') ||
                     $('.analytics-footer-bar').is(':visible') ||
                     $('.analytics-right-sidebar').is(':visible');

    if (isVisible) {
      // Hide all panels
      $('.analytics-left-sidebar').fadeOut(300).removeClass('visible');
      $('.analytics-footer-bar').fadeOut(300).removeClass('visible');
      $('.analytics-right-sidebar').fadeOut(300).removeClass('visible');
      $('body').removeClass('analytics-active');
    } else {
      // Show all panels
      $('.analytics-left-sidebar').fadeIn(300).addClass('visible');
      $('.analytics-footer-bar').fadeIn(300).addClass('visible');
      $('.analytics-right-sidebar').fadeIn(300).addClass('visible');
      $('body').addClass('analytics-active');
      updateAnalytics();
    }
  }
});

// Remove the existing alert-spin-result click handler
// since we now handle this automatically
$(".alert-spin-result").off('click');

$(".answer").mouseover(function () {
  if (playAudio) {
    menuSound.play();
  }
});

$(".answer-yes").click(function () {
  $(".alert-game-over").removeClass("alert-message-visible");

  // Reset roulette display data
  rolledNumbersArray = [];
  rolledNumbersColorArray = [];
  cashSum = 1000;
  bankSum = cashSum;
  betSum = 0;
  $(".roll").html("");
  $(".roll").removeClass("roll-red roll-black roll-green");
  $(".cash-total").html(`${cashSum}.00`);
  $(".bet-total").html(`${betSum}.00`);

  // Ask if user wants to reset history
  if (confirm("Would you like to also reset the game history?")) {
    // Reset analytics data
    allSpins = [];
    currentDrawNumber = 0;
    numberFrequency = {};
    for (let i = 0; i <= 36; i++) {
      numberFrequency[i] = 0;
    }

    // Update displays
    updateAnalytics();
    updateDrawNumberDisplay();

    // Save reset analytics data
    saveAnalyticsData();
  }

  // Save cleared roll history to localStorage
  saveRollHistory();
});

$(".answer-no").click(function () {
  $(".alert-game-over").removeClass("alert-message-visible");
});


