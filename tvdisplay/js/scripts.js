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

  if (playAudio) {
    ballSpinSound.play();
  }
  winAmount = 0;
  winAmountOnScreen = 0;
  cashSumBefore = cashSum;

  // 🔥 FIREBASE MODE: Get winning number from Firebase instead of generating
  // If Firebase is available, wait for the latest draw result
  if (window.FirebaseService && window.FirebaseDrawManager) {
    try {
      console.log('🔥 TV Display: Getting winning number from Firebase...');
      
      // Get current draw number
      const drawInfo = await FirebaseService.GameState.getDrawInfo();
      const currentDraw = drawInfo?.currentDraw || drawInfo?.nextDraw - 1;
      
      if (currentDraw) {
        // Get the latest draw result from Firebase
        const latestDraw = await FirebaseDrawManager.getDraw(currentDraw);
        
        if (latestDraw && latestDraw.winningNumber !== undefined) {
          rouletteNumber = latestDraw.winningNumber;
          console.log('✅ TV Display: Got winning number from Firebase:', rouletteNumber, 'for draw', currentDraw);
        } else {
          // No draw result yet, wait a bit and check again
          console.log('⏳ TV Display: No draw result in Firebase yet, waiting...');
          
          // Wait up to 5 seconds for draw result
          let attempts = 0;
          while (attempts < 10 && !latestDraw) {
            await new Promise(resolve => setTimeout(resolve, 500));
            const checkDraw = await FirebaseDrawManager.getDraw(currentDraw);
            if (checkDraw && checkDraw.winningNumber !== undefined) {
              rouletteNumber = checkDraw.winningNumber;
              console.log('✅ TV Display: Got winning number after waiting:', rouletteNumber);
              break;
            }
            attempts++;
          }
          
          // If still no result, generate random (fallback)
          if (rouletteNumber === undefined) {
            console.warn('⚠️ TV Display: No Firebase result, using random number as fallback');
            rouletteNumber = Math.floor(Math.random() * rouletteNumbersAmount + 0);
          }
        }
      } else {
        // No draw number available, generate random (fallback)
        console.warn('⚠️ TV Display: No draw number available, using random number');
        rouletteNumber = Math.floor(Math.random() * rouletteNumbersAmount + 0);
      }
    } catch (error) {
      console.error('❌ TV Display: Error getting number from Firebase, using random:', error);
      rouletteNumber = Math.floor(Math.random() * rouletteNumbersAmount + 0);
    }
  } else {
    // Firebase not available, generate random (fallback)
    console.log('⚠️ TV Display: Firebase not available, generating random number');
    rouletteNumber = Math.floor(Math.random() * rouletteNumbersAmount + 0);
  }

  // 🚀 INSTANT STORAGE: Save winning number immediately after getting it
  console.log('⚡ INSTANT SAVE: Winning number:', rouletteNumber, '- Saving immediately...');
  saveWinningNumberInstantly(rouletteNumber);

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

  let rouletteWheelAnimation = () => {
    $(".ball-container").html('<div class="ball-spinner"><div class="ball"></div></div>');
    var ballContainer = document.querySelector(".ball-spinner");
    var sheet = document.createElement("style");

    for (let i = 0; i < rouletteNumbersAmount; i++) {
      if (rouletteNumber == rouletteNumbersArray[i]) {
        var ballLandingNumber = i;
      }
    }

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

  rouletteWheelAnimation();

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

    // Update the display with the complete array (including historical data)
    setTimeout(function () {
      console.log('🎯 LAST ROLL DISPLAY: Updating DOM elements');

      for (let i = 0; i < rolledNumbersArray.length && i < 5; i++) {
        let rolledNumberIndex = i + 1;
        $(`.roll${rolledNumberIndex}`).html(rolledNumbersArray[i]);

        switch (rolledNumbersColorArray[i]) {
          case "red":
            $(`.roll${rolledNumberIndex}`).removeClass("roll-black").removeClass("roll-green").addClass("roll-red");
            break;
          case "black":
            $(`.roll${rolledNumberIndex}`).removeClass("roll-red").removeClass("roll-green").addClass("roll-black");
            break;
          case "green":
            $(`.roll${rolledNumberIndex}`).removeClass("roll-red").removeClass("roll-black").addClass("roll-green");
            break;
        }

        console.log(`🎯 LAST ROLL DISPLAY: Set .roll${rolledNumberIndex} to ${rolledNumbersArray[i]} (${rolledNumbersColorArray[i]})`);
      }

      console.log('🎯 LAST ROLL DISPLAY: DOM update completed');
    }, 5000);

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
 */
async function saveWinningNumberInstantly(winningNumber) {
  const saveStartTime = performance.now();

  try {
    console.log('⚡ INSTANT SAVE: Starting immediate storage for number:', winningNumber);

    // Use high-performance storage if available
    if (window.HighPerformanceStorage && typeof window.HighPerformanceStorage.saveWinningNumber === 'function') {
      console.log('🚀 INSTANT SAVE: Using High-Performance Storage');

      const result = await window.HighPerformanceStorage.saveWinningNumber(winningNumber, null, {
        instant: true,
        source: 'tv_display_instant',
        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
      });

      const saveTime = performance.now() - saveStartTime;

      if (result.success) {
        console.log(`✅ INSTANT SAVE: SUCCESS in ${saveTime.toFixed(2)}ms - Number ${winningNumber} saved instantly!`);

        // Dispatch instant save event for real-time monitoring
        dispatchInstantSaveEvent(winningNumber, saveTime, result);

      } else {
        console.error('❌ INSTANT SAVE: High-Performance Storage failed:', result.error);
        // Fallback to triple storage
        await fallbackToTripleStorage(winningNumber, saveStartTime);
      }

    } else {
      console.warn('⚠️ INSTANT SAVE: High-Performance Storage not available, using fallback');
      await fallbackToTripleStorage(winningNumber, saveStartTime);
    }

  } catch (error) {
    console.error('💥 INSTANT SAVE: Error during instant save:', error);
    await fallbackToTripleStorage(winningNumber, saveStartTime);
  }
}

/**
 * Fallback to triple storage system
 */
async function fallbackToTripleStorage(winningNumber, saveStartTime) {
  try {
    console.log('🔄 INSTANT SAVE: Falling back to Triple Storage');

    if (window.TripleStorage && typeof window.TripleStorage.saveSpin === 'function') {
      const result = await window.TripleStorage.saveSpin(winningNumber, null, {
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
      // Auto-spin when timer reaches zero - will read winning number from Firebase
      if (!$(".roulette-wheel-container").hasClass("roulette-wheel-visible")) {
        console.log('🔥 TV Display: Countdown reached zero, auto-spinning (reading from Firebase)...');
        $(".button-spin").click();
      }

      // Reset timer after spin to the next 3-minute interval
      setTimeout(() => {
        const nextDraw = calculateNextDrawTime();
        countdownTime = nextDraw.secondsRemaining;
        localStorage.setItem('countdownEndTime', nextDraw.timestamp.toString());
        saveRollHistory(); // Save the updated timer to database
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
      // Try to load from Firebase first (if available)
      if (window.FirebaseService && window.FirebaseDrawManager) {
        try {
          console.log('🔥 Loading game state from Firebase...');
          
          // Get current game state from Firebase
          const gameState = await FirebaseDrawManager.getCurrentDrawState();
          const drawInfo = await FirebaseService.GameState.getDrawInfo();
          
          if (gameState || drawInfo) {
            console.log('✅ Loaded game state from Firebase:', { gameState, drawInfo });
            
            // Update roll history from Firebase
            if (gameState?.rollHistory && gameState.rollHistory.length > 0) {
              rolledNumbersArray = gameState.rollHistory.slice(0, 5);
              rolledNumbersColorArray = (gameState.rollColors || []).slice(0, 5);
              
              // Update localStorage
              localStorage.setItem('rolledNumbersArray', JSON.stringify(rolledNumbersArray));
              localStorage.setItem('rolledNumbersColorArray', JSON.stringify(rolledNumbersColorArray));
              
              console.log('✅ Updated roll history from Firebase:', rolledNumbersArray, rolledNumbersColorArray);
              
              // Update display
              for (let i = 0; i < rolledNumbersArray.length; i++) {
                const rolledNumberIndex = i + 1;
                $(`.roll${rolledNumberIndex}`).html(rolledNumbersArray[i]);
                
                switch (rolledNumbersColorArray[i]) {
                  case "red":
                    $(`.roll${rolledNumberIndex}`).removeClass("roll-black").removeClass("roll-green").addClass("roll-red");
                    break;
                  case "black":
                    $(`.roll${rolledNumberIndex}`).removeClass("roll-red").removeClass("roll-green").addClass("roll-black");
                    break;
                  case "green":
                    $(`.roll${rolledNumberIndex}`).removeClass("roll-red").removeClass("roll-black").addClass("roll-green");
                    break;
                }
              }
            }
            
            // Update draw numbers
            if (drawInfo?.currentDraw && drawInfo?.nextDraw) {
              currentDrawNumber = drawInfo.currentDraw;
              localStorage.setItem('currentDrawNumber', currentDrawNumber.toString());
              console.log('✅ Updated draw numbers from Firebase:', { current: drawInfo.currentDraw, next: drawInfo.nextDraw });
            }
          }
        } catch (firebaseError) {
          console.warn('⚠️ Could not load from Firebase, falling back to server:', firebaseError);
        }
      }
      
      // Load from server (fallback or additional data)
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
        // Parse the analytics data
        if (data.all_spins) {
          allSpins = JSON.parse(data.all_spins);
          console.log('Loaded allSpins:', allSpins);
        }

        if (data.number_frequency) {
          numberFrequency = JSON.parse(data.number_frequency);
          console.log('Loaded numberFrequency:', numberFrequency);
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

// Function to update analytics display
function updateAnalytics() {
  console.log('Updating analytics display with data:', {
    allSpins: allSpins,
    numberFrequency: numberFrequency,
    currentDrawNumber: currentDrawNumber
  });

  if (!Array.isArray(allSpins) || allSpins.length === 0) {
    console.warn('No spin data available for analytics');
    return;
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
    .map(([number, count]) => ({ number: parseInt(number), count }))
    .sort((a, b) => b.count - a.count);

  console.log('Sorted numbers by frequency:', sortedNumbers);

  // Display Hot numbers (top 5 most frequent)
  const hotNumbers = sortedNumbers.slice(0, 5).filter(item => item.count > 0);
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

  // Display Cold numbers (5 least frequent that have appeared at least once)
  const nonZeroAppearances = sortedNumbers.filter(item => item.count > 0);
  const coldNumbers = nonZeroAppearances.length > 5 ?
                      nonZeroAppearances.slice(-5).reverse() :
                      nonZeroAppearances.slice().reverse();

  console.log('Cold numbers:', coldNumbers);

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
  const historyToShow = allSpins.slice(0, 8);
  historyToShow.forEach((number, index) => {
    const colorClass = number === 0 ? 'green' :
                      rouletteNumbersRed.includes(number) ? 'red' : 'black';

    // Calculate draw number with proper sequential logic
    // Since currentDrawNumber is the NEXT draw number, we need to subtract (index + 1)
    // This ensures the most recent spin shows the current draw number, not the next one
    let baseDrawNumber = currentDrawNumber || 1;

    // If the base is too low to show 8 sequential draws, adjust it
    if (baseDrawNumber <= historyToShow.length) {
      baseDrawNumber = historyToShow.length + 1;
    }

    // Calculate the draw number for this spin (newest first)
    const drawNum = baseDrawNumber - (index + 1);

    $('#number-history').append(`
      <div class="history-item">
        <div class="history-draw">Draw #${drawNum}</div>
        <div class="history-number ${colorClass}">${number}</div>
      </div>
    `);
  });

  console.log('Analytics display updated');
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
