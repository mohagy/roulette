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

let activeChip = "betting-chip-custom";
let activeChipNumber = 4000;

let rolledNumbersArray = [];
let rolledNumbersColorArray = [];
const mouseEventType = ["click", "mouseover"];

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
          // Special handling for splits between 0 and numbers 1, 2, 3
          if (i <= 3) {
            $(`.number0`).addClass(classColorName(functionType));
          } else {
            index = i - 3;
            $(`.number${index}`).addClass(classColorName(functionType));
          }
        }
      }
    });
  });
};

// Initialize the 'with0' class for elements that represent splits with 0
const initializeSplitBets = function() {
  // Find the special zero split elements and add the with0 class
  // These are typically the elements that are positioned between 0 and other numbers

  // Add with0 class to line elements for numbers 1, 2, 3 (splits with 0)
  document.querySelectorAll('.line1, .line2, .line3').forEach(function(element) {
    // Add the with0 class to mark these as splits with 0
    element.classList.add('with0');
  });

  // Create a special marker for the split between 0 and 2
  // This is typically a between2 element that should be near the 0
  const zero = document.querySelector('.number0');
  if (zero) {
    const zeroBounds = zero.getBoundingClientRect();

    // Find all between2 elements
    document.querySelectorAll('.between2').forEach(function(element) {
      const elemBounds = element.getBoundingClientRect();

      // If this between2 element is close to the 0 element, it's the 0-2 split
      // Otherwise, it's the regular 1-2 split
      const distance = Math.sqrt(
        Math.pow(zeroBounds.left - elemBounds.left, 2) +
        Math.pow(zeroBounds.top - elemBounds.top, 2)
      );

      // If close to zero, mark as a with0 split
      if (distance < 150) { // Adjust threshold as needed
        element.classList.add('with0');
      }
    });
  }
};

// Call this function when the document is ready
document.addEventListener('DOMContentLoaded', initializeSplitBets);

// Now define the other functions
const betweenNumbers = (className, functionType) => {
  mouseEventType.forEach((functionType) => {
    $(`.between`).on(functionType, function () {
      // Handle regular split between 1 and 2 if no with0 class
      if ($(this).hasClass('between2') && !$(this).hasClass('with0')) {
        document.querySelectorAll(`.number1, .number2`).forEach((el) => el.classList.add(classColorName(functionType)));
        return;
      }

      for (let i = 0; i < rouletteNumbersAmount; i++) {
        if ($(this).hasClass(`between${i}`)) {
          // Special case for split bets involving 0
          if (i == 2 && $(this).hasClass('with0')) {
            // Split between 0 and 2
            document.querySelectorAll(`.number0, .number2`).forEach((el) => el.classList.add(classColorName(functionType)));
          }
          else if (i % 3 == 1) {
            for (let a = i; a < i + 3; a++) {
              $(`.number${a}`).addClass(classColorName(functionType));
            }
          } else {
            document.querySelectorAll(`.number${i}, .number${i - 1}`).forEach((el) => el.classList.add(classColorName(functionType)));
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

// Bet type tooltip functionality
const betTypeTooltip = document.querySelector(".bet-type-tooltip");
const betTypeDescriptions = {
  // Regular (straight up) bets
  "regular": "Straight Up - Pays 35:1",
  "regular0": "Straight Up (0) - Pays 35:1",

  // Column bets
  "bet2to1-1": "Column (3,6,9...) - Pays 2:1",
  "bet2to1-2": "Column (2,5,8...) - Pays 2:1",
  "bet2to1-3": "Column (1,4,7...) - Pays 2:1",

  // Dozen bets
  "column-1st12": "1st Dozen (1-12) - Pays 2:1",
  "column-2nd12": "2nd Dozen (13-24) - Pays 2:1",
  "column-3rd12": "3rd Dozen (25-36) - Pays 2:1",

  // Even money bets
  "column-1to18": "Low Numbers (1-18) - Pays 1:1",
  "column-19to36": "High Numbers (19-36) - Pays 1:1",
  "column-even": "Even Numbers - Pays 1:1",
  "column-odd": "Odd Numbers - Pays 1:1",
  "column-red": "Red Numbers - Pays 1:1",
  "column-black": "Black Numbers - Pays 1:1",

  // Special bets
  "corner": "Corner Bet - Pays 8:1",
  "line": "Line Bet - Pays 17:1",
  "between": "Split Bet - Pays 17:1"
};

// Track mouse movement for tooltip positioning
document.addEventListener('mousemove', function(e) {
  // Position the tooltip near the cursor
  betTypeTooltip.style.left = (e.clientX + 15) + 'px';
  betTypeTooltip.style.top = (e.clientY + 15) + 'px';
});

// Show tooltip on hovering betting areas
document.querySelectorAll('.part').forEach(function(element) {
  element.addEventListener('mouseover', function(e) {
    let tooltipText = '';
    let classFound = false;

    // Check for specific bet types
    Object.keys(betTypeDescriptions).forEach(function(className) {
      if (element.classList.contains(className)) {
        tooltipText = betTypeDescriptions[className];
        classFound = true;
        return;
      }
    });

    // Handle corner bets with specific numbers
    if (element.classList.contains('corner')) {
      for (let i = 1; i < rouletteNumbersAmount; i++) {
        if (element.classList.contains(`corner${i}`)) {
          // Special case for the First Four bet (0, 1, 2, 3)
          if (i === 1) {
            tooltipText = `First 4 Bet (0,1,2,3) - Pays 8:1`;
          }
          // Special case for Six Line (Double Street) bets
          // These occur at the corners where two rows meet
          else if (i === 4) {
            // Corner between 1-2-3 and 4-5-6
            tooltipText = `Six Line Bet (1,2,3,4,5,6) - Pays 5:1`;
          } else if (i === 7) {
            // Corner between 4-5-6 and 7-8-9
            tooltipText = `Six Line Bet (4,5,6,7,8,9) - Pays 5:1`;
          } else if (i === 10) {
            // Corner between 7-8-9 and 10-11-12
            tooltipText = `Six Line Bet (7,8,9,10,11,12) - Pays 5:1`;
          } else if (i === 13) {
            // Corner between 10-11-12 and 13-14-15
            tooltipText = `Six Line Bet (10,11,12,13,14,15) - Pays 5:1`;
          } else if (i === 16) {
            // Corner between 13-14-15 and 16-17-18
            tooltipText = `Six Line Bet (13,14,15,16,17,18) - Pays 5:1`;
          } else if (i === 19) {
            // Corner between 16-17-18 and 19-20-21
            tooltipText = `Six Line Bet (16,17,18,19,20,21) - Pays 5:1`;
          } else if (i === 22) {
            // Corner between 19-20-21 and 22-23-24
            tooltipText = `Six Line Bet (19,20,21,22,23,24) - Pays 5:1`;
          } else if (i === 25) {
            // Corner between 22-23-24 and 25-26-27
            tooltipText = `Six Line Bet (22,23,24,25,26,27) - Pays 5:1`;
          } else if (i === 28) {
            // Corner between 25-26-27 and 28-29-30
            tooltipText = `Six Line Bet (25,26,27,28,29,30) - Pays 5:1`;
          } else if (i === 31) {
            // Corner between 28-29-30 and 31-32-33
            tooltipText = `Six Line Bet (28,29,30,31,32,33) - Pays 5:1`;
          } else if (i === 34) {
            // Corner between 31-32-33 and 34-35-36
            tooltipText = `Six Line Bet (31,32,33,34,35,36) - Pays 5:1`;
          } else {
            // Standard corner bet
            tooltipText = `Corner Bet (${getCornerNumbers(i)}) - Pays 8:1`;
          }
          classFound = true;
          break;
        }
      }
    }

    // Handle line bets with specific numbers
    if (element.classList.contains('line')) {
      for (let i = 1; i < rouletteNumbersAmount; i++) {
        if (element.classList.contains(`line${i}`)) {
          // Check specifically for splits between 0 and numbers 1, 2, 3
          if (i <= 3) {
            // These are split bets between 0 and 1, 0 and 2, 0 and 3
            tooltipText = `Split Bet (0,${i}) - Pays 17:1`;
          }
          // Special case for vertical split bets (like between 1-4, 2-5, etc.)
          else if (element.classList.contains('center')) {
            // Vertical splits
            if (i === 4 || i === 5 || i === 6) {
              // Second row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 7 || i === 8 || i === 9) {
              // Third row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 10 || i === 11 || i === 12) {
              // Fourth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 13 || i === 14 || i === 15) {
              // Fifth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 16 || i === 17 || i === 18) {
              // Sixth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 19 || i === 20 || i === 21) {
              // Seventh row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 22 || i === 23 || i === 24) {
              // Eighth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 25 || i === 26 || i === 27) {
              // Ninth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 28 || i === 29 || i === 30) {
              // Tenth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 31 || i === 32 || i === 33) {
              // Eleventh row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else if (i === 34 || i === 35 || i === 36) {
              // Twelfth row numbers
              let splitNumbers = `${i-3},${i}`;
              tooltipText = `Split Bet (${splitNumbers}) - Pays 17:1`;
            } else {
              // Default line bet
              let lineNumbers = `${i-3},${i}`;
              tooltipText = `Line Bet (${lineNumbers}) - Pays 17:1`;
            }
          } else {
            // Regular line bet (not a vertical split)
            let lineNumbers = `${i-3},${i}`;
            tooltipText = `Line Bet (${lineNumbers}) - Pays 18:1`;
          }
          classFound = true;
          break;
        }
      }
    }

    // Handle split (between) bets with specific numbers
    if (element.classList.contains('between')) {
      for (let i = 1; i < rouletteNumbersAmount; i++) {
        if (element.classList.contains(`between${i}`)) {
          let splitNumbers;
          // Special case for the split between 0 and 2
          if (i === 2 && element.classList.contains('with0')) {
            splitNumbers = `0,${i}`;
            tooltipText = `Split Bet (${splitNumbers}) - Pays 18:1`;
          }
          // Regular split between 1 and 2
          else if (i === 2 && !element.classList.contains('with0')) {
            splitNumbers = `1,${i}`;
            tooltipText = `Split Bet (${splitNumbers}) - Pays 18:1`;
          }
          else if (i % 3 == 1) {
            splitNumbers = `${i},${i+1},${i+2}`;
            tooltipText = `Street Bet (${splitNumbers}) - Pays 12:1`;
          } else {
            splitNumbers = `${i-1},${i}`;
            tooltipText = `Split Bet (${splitNumbers}) - Pays 18:1`;
          }
          classFound = true;
          break;
        }
      }
    }

    // Handle regular number bets
    if (element.classList.contains('regular') && !classFound) {
      for (let i = 0; i < rouletteNumbersAmount; i++) {
        if (element.classList.contains(`regular${i}`)) {
          tooltipText = `Straight Up (${i}) - Pays 36:1`;
          break;
        }
      }
    }

    // If tooltip text is set, show the tooltip
    if (tooltipText) {
      betTypeTooltip.textContent = tooltipText;
      betTypeTooltip.classList.add('visible');
    }
  });

  element.addEventListener('mouseleave', function() {
    betTypeTooltip.classList.remove('visible');
  });
});

// Helper function to get corner numbers
function getCornerNumbers(i) {
  if (i == 1) {
    return "0,1,2,3";
  } else if (i == 2 || i == 3) {
    return `0,${i-1},${i}`;
  } else if (i > 3) {
    return `${i-4},${i-3},${i-1},${i}`;
  }
  return "";
}

const chipSelection = () => {
  $("#chipCustom").addClass("active-chip");

  $("#chipCustom").click(function() {
    if (playAudio) {
      selectSound.play();
    }
    openCustomAmountModal();
  });
};

// Custom amount functionality has been removed
const handleCustomAmount = () => {
  // Set a default bet amount
  activeChipNumber = 100;
  console.log('Custom amount functionality has been removed. Using default bet amount:', activeChipNumber);
};

// Initialize
chipSelection();
handleCustomAmount();

//Chips placing start
var betSum = 0;
var cashSum = 0; // Will be loaded from database
var minBet = 100;
var maxBet = 50000;
var areaChipCount = 0;
var bankSum = 0; // Will be loaded from database

// Initialize cash from database when CashManager is ready
$(document).ready(function() {
  // Try to run auto fix transactions (only works on local PHP server)
  fetch('auto_fix_transactions.php')
    .then(() => {
      console.log('Auto fix transactions completed');
    })
    .catch(() => {
      console.log('Auto fix transactions skipped (not on PHP server)');
    })
    .finally(() => {

      // Now initialize the CashManager with the updated balance
      if (typeof CashManager !== 'undefined') {
        CashManager.init().then(balance => {
          cashSum = balance;
          bankSum = balance;
          $(".cash-total").html(`${CashManager.formatCash(cashSum)}`);
          console.log('Cash initialized from database:', cashSum);

          // Store the initial cash amount in localStorage for reference
          localStorage.setItem('initialCashAmount', cashSum);
        })
        .catch(error => {
          console.error('Error initializing CashManager:', error);
          // Fallback to default value on error
          cashSum = 1000;
          bankSum = cashSum;
          $(".cash-total").html(`${cashSum}.00`);
        });

        // Register for balance updates
        CashManager.onBalanceUpdate(function(newBalance) {
          cashSum = newBalance;
          bankSum = newBalance;
          console.log('Cash balance updated via callback:', newBalance);
        });
      } else {
        console.error('CashManager not found. Cash will not persist.');
        // Fallback to default value
        cashSum = 1000;
        bankSum = cashSum;
        $(".cash-total").html(`${cashSum}.00`);
      }
    })
    .catch(error => {
      console.error('Error running auto fix transactions:', error);

      // Initialize CashManager anyway
      if (typeof CashManager !== 'undefined') {
        CashManager.init().then(balance => {
          cashSum = balance;
          bankSum = balance;
          $(".cash-total").html(`${CashManager.formatCash(cashSum)}`);
        });
      } else {
        cashSum = 1000;
        bankSum = cashSum;
        $(".cash-total").html(`${cashSum}.00`);
      }
    });

  // When page is refreshed, we don't need to do anything special
  // since we're not updating the database when placing bets

  // Check for original cash amount when page loads
  setTimeout(function() {
    // Clear any stored original cash amount from previous sessions
    // This ensures we always start with the correct amount from the database
    localStorage.removeItem('originalCashAmount');

    console.log('Page loaded, using cash amount from database');
  }, 1000);
});

// Add a style for the active button
$("<style>")
  .prop("type", "text/css")
  .html(`
    .active-button .circle {
      background-color: rgba(255, 255, 255, 0.3);
      box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
    }
    .button-complete .icon {
      transform: rotate(0deg);
      transition: transform 0.3s ease;
    }
    .active-button .icon {
      transform: rotate(45deg);
    }
  `)
  .appendTo("head");

// Update the part click handler to track bets
$(".part").click(function() {
  // Check if we're in complete bet mode and this is a number
  if (isCompleteBetMode && $(this).hasClass("regular")) {
    // Get the number from the class name
    for (let i = 0; i <= 36; i++) {
      if ($(this).hasClass(`regular${i}`)) {
        placeCompleteBet(i);
        return;
      }
    }
  }

  // Regular betting logic
  if (bankSum >= betSum + activeChipNumber) {
    if (maxBet >= betSum + activeChipNumber) {
      if (playAudio) {
        chipPutSound.play();
      }

      betSum = betSum + activeChipNumber;

      // Only update the local cash amount, don't update the database yet
      // The database will only be updated when the betting slip is printed/sold
      cashSum = cashSum - activeChipNumber;
      $(".cash-total").html(`${cashSum.toFixed(2)}`);

      // Store the original cash amount from the database for restoration if needed
      if (!localStorage.getItem('originalCashAmount')) {
        if (typeof CashManager !== 'undefined') {
          localStorage.setItem('originalCashAmount', CashManager.getBalance().toString());
        } else {
          localStorage.setItem('originalCashAmount', bankSum.toString());
        }
      }

      $(".bet-total").html(`${betSum}.00`);

      if ($(this).has(".betting-chip").length) {
        // If a bet already exists, remove it instead of adding to it
        areaChipCount = Number(jQuery(this).children(".betting-chip").attr("id"));

        // Special handling for number 0
        if ($(this).hasClass("regular0") || $(this).hasClass("number0")) {
          console.log("Removing bet on number 0");

          // Find all bets on number 0 in the tracker
          const number0Bets = betTracker.bets.filter(bet =>
            bet.description === "Straight Up on 0"
          );

          if (number0Bets.length > 0) {
            // Calculate total amount to refund
            let totalAmount = 0;
            number0Bets.forEach(bet => {
              totalAmount += bet.amount;
            });

            // Remove chips from all number 0 elements
            $(".regular0, .number0").html("");

            // Update bet sum and cash sum
            betSum = betSum - totalAmount;
            cashSum = cashSum + totalAmount;
            $(".bet-total").html(`${betSum.toFixed(2)}`);
            $(".cash-total").html(`${cashSum.toFixed(2)}`);

            // Update the bank sum to match the cash sum
            bankSum = cashSum;

            // Remove all number 0 bets from the tracker
            betTracker.bets = betTracker.bets.filter(bet =>
              bet.description !== "Straight Up on 0"
            );

            // Update the display
            betTracker.updateDisplay();

            // Play sound for removing chip
            if (playAudio) {
              selectSound.play();
            }

            return;
          }
        }

        // Special handling for number 1
        if ($(this).hasClass("regular1") || $(this).hasClass("number1")) {
          console.log("Removing bet on number 1");

          // Find all bets on number 1 in the tracker
          const number1Bets = betTracker.bets.filter(bet =>
            bet.description === "Straight Up on 1"
          );

          if (number1Bets.length > 0) {
            // Calculate total amount to refund
            let totalAmount = 0;
            number1Bets.forEach(bet => {
              totalAmount += bet.amount;
            });

            // Remove chips from all number 1 elements
            $(".regular1, .number1").html("");

            // Update bet sum and cash sum
            betSum = betSum - totalAmount;
            cashSum = cashSum + totalAmount;
            $(".bet-total").html(`${betSum.toFixed(2)}`);
            $(".cash-total").html(`${cashSum.toFixed(2)}`);

            // Update the bank sum to match the cash sum
            bankSum = cashSum;

            // Remove all number 1 bets from the tracker
            betTracker.bets = betTracker.bets.filter(bet =>
              bet.description !== "Straight Up on 1"
            );

            // Update the display
            betTracker.updateDisplay();

            // Play sound for removing chip
            if (playAudio) {
              selectSound.play();
            }

            return;
          }
        }

        // For all other numbers, proceed with normal removal
        // Get the bet ID to remove it from the tracker
        const betId = betTracker.generateBetId(this);

        // Remove the chip visually
        $(this).html("");

        // Update bet sum and cash sum
        betSum = betSum - areaChipCount;
        cashSum = cashSum + areaChipCount;
        $(".bet-total").html(`${betSum.toFixed(2)}`);
        $(".cash-total").html(`${cashSum.toFixed(2)}`);

        // Update the bank sum to match the cash sum
        bankSum = cashSum;

        // Remove from bet tracker
        betTracker.removeBetById(betId);

        // Play sound for removing chip
        if (playAudio) {
          selectSound.play();
        }
      } else {
        // For new chips, use the appropriate chip class based on the amount
        let chipClass = getChipClass(activeChipNumber);

        $(this).html(
          `<div id="${activeChipNumber}" class="betting-chip betting-chip-shadow ${chipClass}">${activeChipNumber}</div>`
        );

        // Special handling for number 0 when adding a bet
        if ($(this).hasClass("regular0") || $(this).hasClass("number0")) {
          console.log("Adding bet on number 0 with special handling");

          // First, check if there are any existing bets on number 0
          const existingNumber0Bets = betTracker.bets.filter(bet =>
            bet.description === "Straight Up on 0"
          );

          // Also check if there are any existing bets on number 1 that might have been added incorrectly
          const existingNumber1Bets = betTracker.bets.filter(bet =>
            bet.description === "Straight Up on 1"
          );

          // Remove any existing bets on number 1 that might have been added incorrectly
          if (existingNumber1Bets.length > 0) {
            console.log("Removing incorrectly added number 1 bets");
            betTracker.bets = betTracker.bets.filter(bet =>
              bet.description !== "Straight Up on 1"
            );

            // Clear any chips from number 1 elements
            $(".regular1, .number1").html("");
          }

          // Create a direct bet object instead of using addBet to avoid any side effects
          const betId = betTracker.generateBetId(this);
          const betType = betTracker.getBetType(this);
          const betInfo = "Straight Up on 0"; // Force the correct description
          const multiplier = betTracker.getMultiplier(betType);
          const potentialReturn = activeChipNumber + (activeChipNumber * multiplier);
          const elementSelector = betTracker.getElementSelector(this);

          // Check if bet already exists
          const existingBetIndex = betTracker.bets.findIndex(bet => bet.id === betId);

          if (existingBetIndex !== -1) {
            // Update existing bet
            betTracker.bets[existingBetIndex].amount = activeChipNumber;
            betTracker.bets[existingBetIndex].potentialReturn = potentialReturn;
          } else {
            // Add new bet directly to the bets array
            betTracker.bets.push({
              id: betId,
              element: this,
              elementSelector: elementSelector,
              type: betType,
              description: betInfo,
              amount: activeChipNumber,
              multiplier: multiplier,
              potentialReturn: potentialReturn
            });
          }

          // Make sure all number 0 elements have chips
          $(".regular0, .number0").each(function() {
            if (!$(this).has(".betting-chip").length) {
              const chipClass = getChipClass(activeChipNumber);
              $(this).html(`<div id="${activeChipNumber}" class="betting-chip betting-chip-shadow ${chipClass}">${activeChipNumber}</div>`);
            }
          });

          // Check again for any incorrectly added number 1 bets that might have been added
          const newNumber1Bets = betTracker.bets.filter(bet =>
            bet.description === "Straight Up on 1" &&
            !$(".regular1, .number1").has(".betting-chip").length
          );

          // Remove any incorrectly added number 1 bets
          if (newNumber1Bets.length > 0) {
            console.log("Removing incorrectly added number 1 bets after adding number 0");
            betTracker.bets = betTracker.bets.filter(bet =>
              bet.description !== "Straight Up on 1" ||
              $(".regular1, .number1").has(".betting-chip").length
            );
          }

          // Update the display
          betTracker.updateDisplay();
        }
        // Special handling for number 1 when adding a bet
        else if ($(this).hasClass("regular1") || $(this).hasClass("number1")) {
          console.log("Adding bet on number 1 with special handling");

          // Now add the bet on number 1
          betTracker.addBet(this, activeChipNumber);

          // Make sure all number 1 elements have chips
          $(".regular1, .number1").each(function() {
            if (!$(this).has(".betting-chip").length) {
              const chipClass = getChipClass(activeChipNumber);
              $(this).html(`<div id="${activeChipNumber}" class="betting-chip betting-chip-shadow ${chipClass}">${activeChipNumber}</div>`);
            }
          });

          // Update the display
          betTracker.updateDisplay();
        } else {
          // For all other numbers, proceed with normal bet addition
          // Check if this is a regular number bet (straight up)
          if ($(this).hasClass("regular")) {
            console.log("Adding straight up bet with special handling");

            // Get the number from the class name
            let betNumber = null;
            for (let i = 2; i <= 36; i++) {
              if ($(this).hasClass(`regular${i}`)) {
                betNumber = i;
                break;
              }
            }

            if (betNumber !== null) {
              console.log(`Adding bet on number ${betNumber}`);

              // Check if there are any existing bets on number 1 that might be added incorrectly
              const existingNumber1Bets = betTracker.bets.filter(bet =>
                bet.description === "Straight Up on 1" &&
                !$(".regular1, .number1").has(".betting-chip").length
              );

              // Remove any existing bets on number 1 that might have been added incorrectly
              if (existingNumber1Bets.length > 0) {
                console.log("Removing incorrectly added number 1 bets");
                betTracker.bets = betTracker.bets.filter(bet =>
                  bet.description !== "Straight Up on 1" ||
                  $(".regular1, .number1").has(".betting-chip").length
                );
              }
            }
          }

          betTracker.addBet(this, activeChipNumber);
        }
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

  // Reset bet sum
  betSum = 0;

  // Clear bet tracker
  betTracker.clearAllBets();

  // Restore the original cash amount from the database
  const originalCashAmount = localStorage.getItem('originalCashAmount');
  if (originalCashAmount) {
    // Restore the original cash amount
    cashSum = parseFloat(originalCashAmount);
    bankSum = cashSum;
    $(".cash-total").html(`${cashSum.toFixed(2)}`);

    // Clear the stored original cash amount
    localStorage.removeItem('originalCashAmount');

    console.log('Cash restored to original amount:', cashSum);
  } else if (typeof CashManager !== 'undefined') {
    // If no original amount is stored, refresh from the database
    CashManager.refreshBalance()
      .then(newBalance => {
        console.log('Cash refreshed from database:', newBalance);
        cashSum = newBalance;
        bankSum = newBalance;
        $(".cash-total").html(`${CashManager.formatCash(cashSum)}`);
      })
      .catch(error => {
        console.error('Error refreshing cash balance:', error);
      });
  }
});
//Chips placing end

var cashSumBefore = 0;
var winAmountOnScreen;

//Play button start
$(".button-spin").click(function () {
  win = false;

  if (betSum == 0) {
    $(".alert-bets").addClass("alert-message-visible");
  } else {
    if (playAudio) {
      ballSpinSound.play();
    }
    winAmount = 0;
    winAmountOnScreen = 0;
    cashSumBefore = cashSum;

    rouletteNumber = Math.floor(Math.random() * rouletteNumbersAmount + 0);

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
          // For bets between 0 and 1, 2, or 3 - these are split bets
          areaBetCheck("line", i, rouletteNumber == i || rouletteNumber == 0, 18); // Payout is 18:1 for split bets
        }

        //Between check
        if (i == 2 && $(this).hasClass("with0")) {
          // Special case for split between 0 and 2
          areaBetCheck("between", i, rouletteNumber == i || rouletteNumber == 0, 18);
        } else if (i == 2 && !$(this).hasClass("with0")) {
          // Regular split between 1 and 2
          areaBetCheck("between", i, rouletteNumber == i || rouletteNumber == 1, 18);
        } else if (i % 3 == 1) {
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

    //Marking roulette wheel with number glow start
    var tableNumbersWithChips = [];
    for (let i = 0; i <= 36; i++) {
      if ($(`.number${i}`).hasClass("marked-area")) {
        tableNumbersWithChips.push(i);
      }
    }

    for (let a = 0; a < 37; a++) {
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
      rolledNumbersColorArray.splice(0, 0, lastRollColor());

      rolledNumbersArray.splice(0, 0, rouletteNumber);

      if (rolledNumbersArray.length > 5) {
        rolledNumbersArray.splice(-1, 1);
        rolledNumbersColorArray.splice(-1, 1);
      }

      // Save to localStorage for synchronization between pages
      localStorage.setItem('rolledNumbersArray', JSON.stringify(rolledNumbersArray));
      localStorage.setItem('rolledNumbersColorArray', JSON.stringify(rolledNumbersColorArray));

      // Save to server for persistent storage
      saveRollHistoryToServer();

      setTimeout(function () {
        for (i = 0; i < rolledNumbersArray.length; i++) {
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
        }
      }, 50000);

      return lastRollColor;
    };

    const resultsDisplay = () => {
      setTimeout(function () {
        $(".alert-spin-result").addClass("alert-message-visible");
        $(".results").addClass("alert-message-opacity");
      }, 50000);

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
        setTimeout(function () {
          if (playAudio) {
            winSound.play();
          }
        }, 5300);
      } else {
        $(".win-lose").html("");  // Changed from "NO WIN" to empty string
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
  }
});

$(".alert-message-container").click(function () {
  $(".alert-message-container").removeClass("alert-message-visible");
});

$(".alert-spin-result").click(function () {
  // Get the current winning number
  const winningNumber = parseInt($(".roll-number").text());

  // Mark the draw as called with the winning number
  ticketManager.setDrawCalled(winningNumber);

  // Add winnings to cash balance
  if (winAmountOnScreen > 0) {
    // Use CashManager to add winnings
    if (typeof CashManager !== 'undefined') {
      CashManager.addCash(winAmountOnScreen, 'win', null, `Win on number ${winningNumber}`);
    } else {
      // Fallback animation if CashManager is not available
      for (let i = 1; i <= 10; i++) {
        (function (i) {
          setTimeout(function () {
            cashSumBefore = cashSumBefore + winAmountOnScreen / 10;
            $(".cash-total").html(`${Math.round(cashSumBefore)}.00`);
          }, 50 * i);
        })(i);
      }
    }
  }

  $(".roulette-wheel-container").removeClass("roulette-wheel-visible");
  setTimeout(function () {
    $(".roulette-wheel-container").removeClass("z-index-visible");
  }, 1000);

  $(".number").removeClass("marked-area");

  $(".results").removeClass("alert-message-opacity");
  setTimeout(function () {
    $(".alert-spin-result").removeClass("alert-message-visible");
  }, 1000);

  if (win == true) {
    setTimeout(function () {
      if (playAudio) {
        winChipsSound.play();
      }
    }, 500);
  }

  $(".roulette-wheel-main").removeClass("roulette-wheel-spin");
  $(".roulette-cross-shadow").removeClass("roulette-wheel-spin");
  $(".roulette-cross").removeClass("roulette-wheel-spin");

  $(".number-glow-container").html("");

  setTimeout(function () {
    $(".results").removeClass("roll-red roll-black roll-green");
  }, 1000);

  $(".ball-container").html("");
  $(".part").html("");

  $(".bet-total").html("0.00");
  betSum = 0;

  // Clear bet tracker
  betTracker.clearAllBets();

  if (cashSum <= 0) {
    $(".alert-game-over").addClass("alert-message-visible");
  }
});

$(".answer").mouseover(function () {
  if (playAudio) {
    menuSound.play();
  }
});

$(".answer-yes").click(function () {
  $(".alert-game-over").removeClass("alert-message-visible");
  rolledNumbersArray = [];
  rolledNumbersColorArray = [];

  // Reset cash to 1000 using CashManager
  if (typeof CashManager !== 'undefined') {
    // First get current balance
    CashManager.refreshBalance().then(currentBalance => {
      // Calculate amount to add to reach 1000
      const amountToAdd = 1000 - currentBalance;
      if (amountToAdd > 0) {
        // Add cash to reach 1000
        CashManager.addCash(amountToAdd, 'admin', null, 'Game reset - cash refill');
      } else if (amountToAdd < 0) {
        // Remove cash to reach 1000
        CashManager.removeCash(-amountToAdd, 'admin', null, 'Game reset - cash adjustment');
      }
    });
  } else {
    // Fallback if CashManager is not available
    cashSum = 1000;
    bankSum = cashSum;
    $(".cash-total").html(`${cashSum}.00`);
  }

  betSum = 0;
  $(".roll").html("");
  $(".roll").removeClass("roll-red roll-black roll-green");
  $(".bet-total").html(`${betSum}.00`);
});

$(".answer-no").click(function () {
  $(".alert-game-over").removeClass("alert-message-visible");
});

rowsBetRange();
columnBetRange();
columnEvenOdd();
columnRedBlack();
regularNumbers();
cornerNumbers();
lineNumbers();
betweenNumbers();

// Complete bet functionality
let isCompleteBetMode = false;

// Function to place a complete bet on a number
function placeCompleteBet(number) {
  if (number < 0 || number > 36) return; // Validate number

  // Get all bets that involve this number
  const bets = getCompleteBets(number);

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

  // If we're removing bets, we don't need to check for money
  if (!isRemovingBets) {
    // Check if player has enough money
    if (bankSum < betSum + totalChipsToAdd) {
      $(".alert-money").addClass("alert-message-visible");
      return;
    }

    // Check if this would exceed max bet
    if (maxBet < betSum + totalChipsToAdd) {
      $(".alert-max-bet").addClass("alert-message-visible");
      return;
    }
  }

  // Process all positions
  bets.forEach(betSelector => {
    const element = document.querySelector(betSelector);
    if (!element) return;

    // placeBetOnElement will handle adding or removing the bet
    placeBetOnElement(betSelector, activeChipNumber);

    // Only add to bet tracker if we're adding a new bet
    // (placeBetOnElement handles removing from the tracker)
    if (!$(element).has(".betting-chip").length) {
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
}

// Function to get all bet positions for a complete bet on a number
function getCompleteBets(number) {
  const bets = [];

  // 1. Straight up bet (the number itself)
  bets.push(`.regular${number}`);

  // 2. Split bets
  // For number 0, special handling
  if (number === 0) {
    bets.push(`.line1`); // Split 0-1
    bets.push(`.line2`); // Split 0-2
    bets.push(`.line3`); // Split 0-3

    // Add First 4 bet (0,1,2,3)
    bets.push(`.corner1`);

    // Add corner bets for 0,1,2 and 0,2,3
    bets.push(`.corner2`); // Corner 0,1,2
    bets.push(`.corner3`); // Corner 0,2,3
  }
  // Special handling for number 1
  else if (number === 1) {
    // Regular splits for number 1
    bets.push(`.between2`); // Split 1-2
    bets.push(`.line4`);    // Split 1-4

    // Additional bets specific to number 1
    bets.push(`.line1`);    // Split 0-1
    bets.push(`.corner2`);  // Corner 0,1,2
    bets.push(`.corner1`);  // First 4 bet (0,1,2,3)

    // Standard corner bet that includes number 1
    bets.push(`.corner4`);  // Corner 1,2,4,5

    // Street bet for 1,2,3
    bets.push(`.between1`);
  }
  // Special handling for number 2
  else if (number === 2) {
    // Regular splits for number 2
    bets.push(`.between2`); // Split 1-2
    bets.push(`.between3`); // Split 2-3
    bets.push(`.line5`);    // Split 2-5

    // Additional bets specific to number 2
    bets.push(`.line2`);    // Split 0-2
    bets.push(`.corner2`);  // Corner 0,1,2
    bets.push(`.corner3`);  // Corner 0,2,3
    bets.push(`.corner1`);  // First 4 bet (0,1,2,3)

    // Standard corner bets that include number 2
    bets.push(`.corner5`);  // Corner 1,2,4,5
    bets.push(`.corner6`);  // Corner 2,3,5,6

    // Street bet for 1,2,3
    bets.push(`.between1`);
  }
  // Special handling for number 3
  else if (number === 3) {
    // Regular splits for number 3
    bets.push(`.between3`); // Split 2-3
    bets.push(`.line6`);    // Split 3-6

    // Additional bets specific to number 3
    bets.push(`.line3`);    // Split 0-3
    bets.push(`.corner3`);  // Corner 0,2,3
    bets.push(`.corner1`);  // First 4 bet (0,1,2,3)

    // Standard corner bet that includes number 3
    bets.push(`.corner6`);  // Corner 2,3,5,6

    // Street bet for 1,2,3
    bets.push(`.between1`);
  }
  else {
    // Horizontal splits (right and left)
    if (number % 3 !== 0) {
      // Not in the right column, has a right split
      bets.push(`.between${number + 1}`);
    }
    if (number % 3 !== 1) {
      // Not in the left column, has a left split
      bets.push(`.between${number}`);
    }

    // Vertical splits (top and bottom)
    if (number <= 33) {
      // Not in the bottom row, has a bottom split
      bets.push(`.line${number + 3}`);
    }
    if (number >= 4) {
      // Not in the top row, has a top split
      bets.push(`.line${number}`);
    }
  }

  // 3. Corner bets - skip for 0, 2, and 3 as they have special handling
  if (number !== 0 && number !== 2 && number !== 3) {
    // Top-left corner
    if (number >= 5 && number % 3 !== 1) {
      bets.push(`.corner${number}`);
    }

    // Top-right corner
    if (number >= 4 && number % 3 !== 0) {
      bets.push(`.corner${number + 1}`);
    }

    // Bottom-left corner
    if (number <= 33 && number % 3 !== 1) {
      bets.push(`.corner${number + 3}`);
    }

    // Bottom-right corner
    if (number <= 32 && number % 3 !== 0) {
      bets.push(`.corner${number + 4}`);
    }
  }

  // 4. Street bet (row of 3) - skip for 0, 2, and 3 as they're handled above
  if (number !== 0 && number !== 2 && number !== 3) {
    const streetStart = number - ((number - 1) % 3);
    bets.push(`.between${streetStart}`);
  }

  // 5. Six line bet (double street)
  if (number !== 0) {
    const row = Math.ceil(number / 3);
    if (row > 1) {
      // Has a six line above
      const topStreetStart = number - ((number - 1) % 3) - 3;
      bets.push(`.corner${topStreetStart + 3}`);
    }
    if (row < 12) {
      // Has a six line below
      const streetStart = number - ((number - 1) % 3);
      if (streetStart + 3 <= 34) {
        bets.push(`.corner${streetStart + 3}`);
      }
    }
  }

  // 6. Return unique selectors
  return [...new Set(bets)];
}

// Function to place bet on element
function placeBetOnElement(selector, chipValue) {
  const element = document.querySelector(selector);
  if (!element) return;

  if ($(element).has(".betting-chip").length) {
    // Element already has chips, remove them instead of adding more
    // This is for the complete bet functionality which should follow the same behavior
    // as the regular click handler

    // Get the existing value
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

    // Play sound for removing chip
    if (playAudio) {
      selectSound.play();
    }
  } else {
    // Element has no chips yet
    let chipClass = getChipClass(chipValue);

    $(element).html(
      `<div id="${chipValue}" class="betting-chip betting-chip-shadow ${chipClass}">${chipValue}</div>`
    );
  }
}

// Function to get appropriate chip class based on amount
function getChipClass(amount) {
  // Always use the custom amount styling for all chips
  let baseClass = "betting-chip-custom-amount";

  // Add a class for very large numbers to reduce font size
  if (amount >= 1000 || amount.toString().length >= 4) {
    baseClass += " betting-chip-large-amount";
  }

  return baseClass;
}

// Bet tracking system for the display window
const betTracker = {
  bets: [],

  // Add or update a bet
  addBet: function(element, amount) {
    const betId = this.generateBetId(element);
    const betType = this.getBetType(element);
    const betInfo = this.getBetInfo(element, betType);
    const multiplier = this.getMultiplier(betType);
    // Calculate potential return: original stake + winnings
    const potentialReturn = amount + (amount * multiplier);

    // Store element selector to reference this bet later
    const elementSelector = this.getElementSelector(element);

    // Check if this is a straight up bet on a number other than 0 or 1
    if (betInfo.startsWith("Straight Up on ") &&
        betInfo !== "Straight Up on 0" &&
        betInfo !== "Straight Up on 1") {
      console.log(`Processing bet on ${betInfo}`);

      // Check if there are any existing bets on number 1 that might have been added incorrectly
      const existingNumber1Bets = this.bets.filter(bet =>
        bet.description === "Straight Up on 1" &&
        !$(".regular1, .number1").has(".betting-chip").length
      );

      // Remove any existing bets on number 1 that might have been added incorrectly
      if (existingNumber1Bets.length > 0) {
        console.log("Removing incorrectly added number 1 bets in addBet function");
        this.bets = this.bets.filter(bet =>
          bet.description !== "Straight Up on 1" ||
          $(".regular1, .number1").has(".betting-chip").length
        );
      }
    }

    // Special handling for number 0 straight up bet to prevent duplicates
    if (betInfo === "Straight Up on 0") {
      console.log("Processing bet on number 0");

      // First, check if there are any existing bets on number 1 that might have been added incorrectly
      const existingNumber1Bets = this.bets.filter(bet =>
        bet.description === "Straight Up on 1"
      );

      // Remove any existing bets on number 1 that might have been added incorrectly
      if (existingNumber1Bets.length > 0) {
        console.log("Removing incorrectly added number 1 bets when adding number 0");
        this.bets = this.bets.filter(bet =>
          bet.description !== "Straight Up on 1"
        );

        // Clear any chips from number 1 elements
        document.querySelectorAll('.regular1, .number1').forEach(el => {
          el.innerHTML = '';
        });
      }

      // Now handle existing number 0 bets
      const existingNumber0Bets = this.bets.filter(bet =>
        bet.description === "Straight Up on 0"
      );

      if (existingNumber0Bets.length > 0) {
        console.log(`Found ${existingNumber0Bets.length} existing bets on number 0`);

        // Keep only the first bet and update it
        const keepBet = existingNumber0Bets[0];
        keepBet.amount = amount;
        keepBet.potentialReturn = potentialReturn;

        // Remove any additional bets on number 0
        if (existingNumber0Bets.length > 1) {
          console.log(`Removing ${existingNumber0Bets.length - 1} duplicate bets on number 0`);
          this.bets = this.bets.filter(bet =>
            bet.description !== "Straight Up on 0" || bet === keepBet
          );
        }

        // Make sure all number 0 elements have chips
        document.querySelectorAll('.regular0, .number0').forEach(el => {
          // Clear any existing chips
          el.innerHTML = '';

          // Add the chip to this element
          const chipClass = getChipClass(amount);
          el.innerHTML = `<div id="${amount}" class="betting-chip betting-chip-shadow ${chipClass}">${amount}</div>`;
        });

        this.updateDisplay();
        return;
      }
    }

    // Special handling for number 1 straight up bet to prevent duplicates
    if (betInfo === "Straight Up on 1") {
      console.log("Processing bet on number 1");

      // Now handle existing number 1 bets
      const existingNumber1Bets = this.bets.filter(bet =>
        bet.description === "Straight Up on 1"
      );

      if (existingNumber1Bets.length > 0) {
        console.log(`Found ${existingNumber1Bets.length} existing bets on number 1`);

        // Keep only the first bet and update it
        const keepBet = existingNumber1Bets[0];
        keepBet.amount = amount;
        keepBet.potentialReturn = potentialReturn;

        // Remove any additional bets on number 1
        if (existingNumber1Bets.length > 1) {
          console.log(`Removing ${existingNumber1Bets.length - 1} duplicate bets on number 1`);
          this.bets = this.bets.filter(bet =>
            bet.description !== "Straight Up on 1" || bet === keepBet
          );
        }

        // Make sure all number 1 elements have chips
        document.querySelectorAll('.regular1, .number1').forEach(el => {
          // Clear any existing chips
          el.innerHTML = '';

          // Add the chip to this element
          const chipClass = getChipClass(amount);
          el.innerHTML = `<div id="${amount}" class="betting-chip betting-chip-shadow ${chipClass}">${amount}</div>`;
        });

        this.updateDisplay();
        return;
      }
    }

    // Check if bet already exists
    const existingBetIndex = this.bets.findIndex(bet => bet.id === betId);

    if (existingBetIndex !== -1) {
      // Update existing bet
      this.bets[existingBetIndex].amount = amount;
      this.bets[existingBetIndex].potentialReturn = potentialReturn;
    } else {
      // Add new bet
      this.bets.push({
        id: betId,
        element: element,
        elementSelector: elementSelector,
        type: betType,
        description: betInfo,
        amount: amount,
        multiplier: multiplier,
        potentialReturn: potentialReturn
      });

      // Special handling for number 0 after adding a new bet
      if (betInfo === "Straight Up on 0") {
        console.log("Post-add handling for number 0");

        // First, check if there are any existing bets on number 1 that might have been added incorrectly
        const existingNumber1Bets = this.bets.filter(bet =>
          bet.description === "Straight Up on 1"
        );

        // Remove any existing bets on number 1 that might have been added incorrectly
        if (existingNumber1Bets.length > 0) {
          console.log("Removing incorrectly added number 1 bets in post-add handling");
          this.bets = this.bets.filter(bet =>
            bet.description !== "Straight Up on 1"
          );

          // Clear any chips from number 1 elements
          document.querySelectorAll('.regular1, .number1').forEach(el => {
            el.innerHTML = '';
          });
        }

        // Ensure all number 0 elements have chips
        document.querySelectorAll('.regular0, .number0').forEach(el => {
          if (el !== element && !el.querySelector('.betting-chip')) {
            const chipClass = getChipClass(amount);
            el.innerHTML = `<div id="${amount}" class="betting-chip betting-chip-shadow ${chipClass}">${amount}</div>`;
          }
        });

        // Update the display to reflect the changes
        this.updateDisplay();
      }

      // Special handling for number 1 after adding a new bet
      else if (betInfo === "Straight Up on 1") {
        console.log("Post-add handling for number 1");

        // Ensure all number 1 elements have chips
        document.querySelectorAll('.regular1, .number1').forEach(el => {
          if (el !== element && !el.querySelector('.betting-chip')) {
            const chipClass = getChipClass(amount);
            el.innerHTML = `<div id="${amount}" class="betting-chip betting-chip-shadow ${chipClass}">${amount}</div>`;
          }
        });
      }
    }

    this.updateDisplay();
  },

  // Get a CSS selector that uniquely identifies the element
  getElementSelector: function(element) {
    const classes = Array.from(element.classList);
    return '.' + classes.join('.');
  },

  // Remove a bet by its ID
  removeBet: function(betId) {
    const betIndex = this.bets.findIndex(bet => bet.id === betId);

    if (betIndex !== -1) {
      const bet = this.bets[betIndex];

      // Special handling for number 0 straight up bet
      if (bet.description === "Straight Up on 0") {
        console.log("Removing bet on number 0");

        // Check if there are any other bets on number 0
        const otherNumber0Bets = this.bets.filter((b, i) =>
          i !== betIndex && b.description === "Straight Up on 0"
        );

        if (otherNumber0Bets.length > 0) {
          console.log(`Found ${otherNumber0Bets.length} other bets on number 0, removing all of them`);

          // Calculate total amount to refund for all number 0 bets
          let totalAmount = bet.amount;
          otherNumber0Bets.forEach(otherBet => {
            totalAmount += otherBet.amount;
          });

          // Remove all chips from all elements that could represent number 0
          document.querySelectorAll('.regular0, .number0').forEach(element => {
            element.innerHTML = '';
          });

          // Update cash and bet totals
          const betTotal = parseFloat($(".bet-total").text());
          const newBetTotal = betTotal - totalAmount;
          $(".bet-total").html(`${newBetTotal.toFixed(2)}`);

          // Update global bet sum
          betSum = newBetTotal;

          // Immediately update the UI to show the refunded amount
          cashSum = cashSum + totalAmount;
          bankSum = cashSum; // Update bankSum to match cashSum
          $(".cash-total").html(`${cashSum.toFixed(2)}`);

          // Remove all number 0 bets from our array
          this.bets = this.bets.filter(b => b.description !== "Straight Up on 0");

          // Update display
          this.updateDisplay();

          return true;
        } else {
          // Just one bet on number 0, remove all chips
          document.querySelectorAll('.regular0, .number0').forEach(element => {
            element.innerHTML = '';
          });
        }
      }
      // Special handling for number 1 straight up bet
      else if (bet.description === "Straight Up on 1") {
        console.log("Removing bet on number 1");

        // Check if there are any other bets on number 1
        const otherNumber1Bets = this.bets.filter((b, i) =>
          i !== betIndex && b.description === "Straight Up on 1"
        );

        if (otherNumber1Bets.length > 0) {
          console.log(`Found ${otherNumber1Bets.length} other bets on number 1, removing all of them`);

          // Calculate total amount to refund for all number 1 bets
          let totalAmount = bet.amount;
          otherNumber1Bets.forEach(otherBet => {
            totalAmount += otherBet.amount;
          });

          // Remove all chips from all elements that could represent number 1
          document.querySelectorAll('.regular1, .number1').forEach(element => {
            element.innerHTML = '';
          });

          // Update cash and bet totals
          const betTotal = parseFloat($(".bet-total").text());
          const newBetTotal = betTotal - totalAmount;
          $(".bet-total").html(`${newBetTotal.toFixed(2)}`);

          // Update global bet sum
          betSum = newBetTotal;

          // Immediately update the UI to show the refunded amount
          cashSum = cashSum + totalAmount;
          bankSum = cashSum; // Update bankSum to match cashSum
          $(".cash-total").html(`${cashSum.toFixed(2)}`);

          // Remove all number 1 bets from our array
          this.bets = this.bets.filter(b => b.description !== "Straight Up on 1");

          // Update display
          this.updateDisplay();

          return true;
        } else {
          // Just one bet on number 1, remove all chips
          document.querySelectorAll('.regular1, .number1').forEach(element => {
            element.innerHTML = '';
          });
        }
      } else {
        // For all other bets, just remove the chip from the specific element
        const elements = document.querySelectorAll(bet.elementSelector);
        elements.forEach(element => {
          // Clear the element's HTML to remove the chip
          element.innerHTML = '';
        });
      }

      // Update cash and bet totals
      const betTotal = parseFloat($(".bet-total").text());
      const newBetTotal = betTotal - bet.amount;
      $(".bet-total").html(`${newBetTotal.toFixed(2)}`);

      // Update global bet sum
      betSum = newBetTotal;

      // Immediately update the UI to show the refunded amount
      cashSum = cashSum + bet.amount;
      bankSum = cashSum; // Update bankSum to match cashSum
      $(".cash-total").html(`${cashSum.toFixed(2)}`);

      // Remove the bet from our array
      this.bets.splice(betIndex, 1);

      // Update display
      this.updateDisplay();

      // If all bets are removed, restore the original cash amount
      if (this.bets.length === 0) {
        const originalCashAmount = localStorage.getItem('originalCashAmount');
        if (originalCashAmount) {
          // Restore the original cash amount
          cashSum = parseFloat(originalCashAmount);
          bankSum = cashSum;
          $(".cash-total").html(`${cashSum.toFixed(2)}`);

          // Clear the stored original cash amount
          localStorage.removeItem('originalCashAmount');

          console.log('All bets removed, cash restored to original amount:', cashSum);
        }
      }

      return true;
    }

    return false;
  },

  // Remove a bet by its ID without updating UI (for internal use)
  removeBetById: function(betId) {
    const betIndex = this.bets.findIndex(bet => bet.id === betId);

    if (betIndex !== -1) {
      const bet = this.bets[betIndex];

      // Special handling for number 0 straight up bet
      if (bet.description === "Straight Up on 0") {
        console.log("Internal removal of bet on number 0");

        // Check if there are any other bets on number 0 still in the tracker
        const otherNumber0Bets = this.bets.filter((b, i) =>
          i !== betIndex && b.description === "Straight Up on 0"
        );

        if (otherNumber0Bets.length > 0) {
          console.log(`Found ${otherNumber0Bets.length} other bets on number 0`);

          // Remove all number 0 bets from our array
          this.bets = this.bets.filter(b => b.description !== "Straight Up on 0");

          // Clear all number 0 chips from the board
          $(".regular0, .number0").html("");

          // Update display
          this.updateDisplay();

          return true;
        } else {
          // Just one bet on number 0, make sure all chips are removed
          $(".regular0, .number0").html("");
        }
      }
      // Special handling for number 1 straight up bet
      else if (bet.description === "Straight Up on 1") {
        console.log("Internal removal of bet on number 1");

        // Check if there are any other bets on number 1 still in the tracker
        const otherNumber1Bets = this.bets.filter((b, i) =>
          i !== betIndex && b.description === "Straight Up on 1"
        );

        if (otherNumber1Bets.length > 0) {
          console.log(`Found ${otherNumber1Bets.length} other bets on number 1`);

          // Remove all number 1 bets from our array
          this.bets = this.bets.filter(b => b.description !== "Straight Up on 1");

          // Clear all number 1 chips from the board
          $(".regular1, .number1").html("");

          // Update display
          this.updateDisplay();

          return true;
        } else {
          // Just one bet on number 1, make sure all chips are removed
          $(".regular1, .number1").html("");
        }
      }

      // Remove the bet from our array
      this.bets.splice(betIndex, 1);

      // Update display
      this.updateDisplay();

      // If all bets are removed, restore the original cash amount
      if (this.bets.length === 0) {
        const originalCashAmount = localStorage.getItem('originalCashAmount');
        if (originalCashAmount) {
          // Clear the stored original cash amount
          localStorage.removeItem('originalCashAmount');
          console.log('All bets removed, original cash amount cleared');
        }
      }

      return true;
    }

    return false;
  },

  // Update the stake amount for a specific bet
  updateBetStake: function(betId, newAmount) {
    const betIndex = this.bets.findIndex(bet => bet.id === betId);

    if (betIndex !== -1) {
      const bet = this.bets[betIndex];
      const originalAmount = bet.amount;

      // Calculate difference for cash adjustment
      const difference = newAmount - originalAmount;

      // Check if we have enough cash
      if (difference > 0 && cashSum < difference) {
        return false; // Not enough cash
      }

      // Update the bet amount and potential return
      bet.amount = newAmount;
      bet.potentialReturn = newAmount + (newAmount * bet.multiplier);

      // Update betting display
      const betTotal = parseFloat($(".bet-total").text());
      const newBetTotal = betTotal + difference;
      $(".bet-total").html(`${newBetTotal.toFixed(2)}`);

      // Update cash
      cashSum = cashSum - difference;
      $(".cash-total").html(`${cashSum.toFixed(2)}`);

      // Update global bet sum
      betSum = newBetTotal;

      // Update chip on the board
      try {
        // First update the main element
        const elements = document.querySelectorAll(bet.elementSelector);
        elements.forEach(element => {
          // Remove existing chip
          element.innerHTML = '';

          // Add new chip with updated amount
          const chipClass = getChipClass(newAmount);
          element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
        });

        // Special handling for even money bets (1to18, 19to36, even, odd, red, black)
        if (bet.type === 'even-money') {
          let bottomSelector = '';

          if (bet.description.includes('Low Numbers')) {
            bottomSelector = '.column-1to18';
          } else if (bet.description.includes('High Numbers')) {
            bottomSelector = '.column-19to36';
          } else if (bet.description.includes('Even Numbers')) {
            bottomSelector = '.column-even';
          } else if (bet.description.includes('Odd Numbers')) {
            bottomSelector = '.column-odd';
          } else if (bet.description.includes('Red Numbers')) {
            bottomSelector = '.column-red';
          } else if (bet.description.includes('Black Numbers')) {
            bottomSelector = '.column-black';
          }

          if (bottomSelector) {
            console.log(`Updating bottom area chip for ${bet.description} using selector ${bottomSelector}`);

            // Get the bottom area element
            const bottomElements = document.querySelectorAll(bottomSelector);
            bottomElements.forEach(element => {
              // Clear the element and add a new chip
              element.innerHTML = '';
              const chipClass = getChipClass(newAmount);
              element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
            });
            console.log(`Successfully updated bottom area chip to ${newAmount}`);
          }
        }

        // Special handling for dozen bets (1st12, 2nd12, 3rd12)
        if (bet.type === 'dozen') {
          let dozenSelector = '';

          if (bet.description.includes('1st Dozen')) {
            dozenSelector = '.column-1st12';
          } else if (bet.description.includes('2nd Dozen')) {
            dozenSelector = '.column-2nd12';
          } else if (bet.description.includes('3rd Dozen')) {
            dozenSelector = '.column-3rd12';
          }

          if (dozenSelector) {
            console.log(`Updating dozen area chip for ${bet.description} using selector ${dozenSelector}`);

            // Get the dozen area elements
            const dozenElements = document.querySelectorAll(dozenSelector);
            dozenElements.forEach(element => {
              // Clear the element and add a new chip
              element.innerHTML = '';
              const chipClass = getChipClass(newAmount);
              element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
            });
            console.log(`Successfully updated dozen area chip to ${newAmount}`);
          }
        }

        // Special handling for column bets (bet2to1-1, bet2to1-2, bet2to1-3)
        if (bet.type === 'column') {
          let columnSelector = '';

          if (bet.description.includes('Column (3,6,9')) {
            columnSelector = '.bet2to1-1';
          } else if (bet.description.includes('Column (2,5,8')) {
            columnSelector = '.bet2to1-2';
          } else if (bet.description.includes('Column (1,4,7')) {
            columnSelector = '.bet2to1-3';
          }

          if (columnSelector) {
            console.log(`Updating column area chip for ${bet.description} using selector ${columnSelector}`);

            // Get the column area elements
            const columnElements = document.querySelectorAll(columnSelector);
            columnElements.forEach(element => {
              // Clear the element and add a new chip
              element.innerHTML = '';
              const chipClass = getChipClass(newAmount);
              element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
            });
            console.log(`Successfully updated column area chip to ${newAmount}`);
          }
        }

        // We no longer update all chips with the same amount
        // This ensures that only the specific bet being edited is updated
        console.log(`Updated only the specific bet chip to ${newAmount}`);
      } catch (error) {
        console.error('Error updating chips on board:', error);
      }

      // Update display
      this.updateDisplay();

      return true;
    }

    return false;
  },

  // Clear all bets
  clearAllBets: function() {
    this.bets = [];
    this.updateDisplay();
  },

  // Clear the board for new bets WITHOUT refunding money (used after printing a betting slip)
  clearBoardForNewBets: function() {
    // Clear chips from the board based on tracked bets
    this.bets.forEach(bet => {
      const elements = document.querySelectorAll(bet.elementSelector);
      elements.forEach(element => {
        element.innerHTML = '';
      });
    });

    // Additionally, clear ALL parts of the board to ensure nothing is left behind
    // This includes the bottom area (1st12, 2nd12, 3rd12, 1to18, EVEN, ODD, 19to36)
    document.querySelectorAll('.part').forEach(element => {
      element.innerHTML = '';
    });

    // Also clear any chips on number elements
    document.querySelectorAll('.number').forEach(element => {
      if (element.querySelector('.betting-chip')) {
        element.innerHTML = '';
      }
    });

    // Reset bet total
    betSum = 0;
    $(".bet-total").html(`${betSum.toFixed(2)}`);

    // Clear all bets from tracker
    this.bets = [];
    this.updateDisplay();

    console.log('Board completely cleared for new bets');
  },

  // Get the current draw number for betting slip assignment
  getCurrentDrawNumber: function() {
    console.log('🎯 betTracker.getCurrentDrawNumber: Getting draw number for betting slip...');
    console.log('🎯 DEBUG: Starting priority-based draw number detection...');

    // Priority 1: Check if there's a manually selected draw number (with validation)
    console.log('🎯 Priority 1: Checking manual selection...');
    if (window.selectedDrawNumber) {
      const manualDraw = parseInt(window.selectedDrawNumber, 10);
      console.log('🎯 Manual selection found:', manualDraw);

      // Validate that the manually selected draw is not in the past
      // First get the current draw from database to validate
      try {
        const timestamp = new Date().getTime();
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `php/get_next_draw_number.php?t=${timestamp}`, false);
        xhr.send();

        if (xhr.status === 200) {
          const response = JSON.parse(xhr.responseText);
          if (response.status === 'success' && response.current_draw_number) {
            const currentDraw = parseInt(response.current_draw_number, 10);

            // If manually selected draw is less than or equal to current draw, it's in the past
            if (manualDraw <= currentDraw) {
              console.log('🎯 ⚠️ Manual selection is for a past/completed draw:', manualDraw, 'vs current:', currentDraw);
              console.log('🎯 ⚠️ Clearing invalid manual selection and using upcoming draw instead');

              // Clear the invalid selection
              window.selectedDrawNumber = null;

              // Also clear from localStorage to prevent it from being reloaded
              this.clearInvalidDrawSelections(manualDraw, currentDraw);

              // Continue to next priority (database API for upcoming draw)
            } else {
              console.log('🎯 ✅ Manual selection is valid (future draw):', manualDraw);
              return manualDraw;
            }
          }
        }
      } catch (error) {
        console.log('🎯 ⚠️ Could not validate manual selection, using it anyway:', manualDraw);
        return manualDraw;
      }
    }
    console.log('🎯 ❌ No valid manual selection found');

    // Priority 2: ALWAYS fetch latest from database first (for real-time sync)
    console.log('🎯 Priority 2: Checking database for LATEST NEXT draw number...');
    try {
      // Add cache-busting parameter to ensure fresh data
      const timestamp = new Date().getTime();
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `php/get_next_draw_number.php?t=${timestamp}`, false); // Synchronous with cache-busting
      console.log('🎯 Sending request to get_next_draw_number.php with cache-busting...');
      xhr.send();

      console.log('🎯 Database response status:', xhr.status);
      console.log('🎯 Database response text:', xhr.responseText);

      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        console.log('🎯 Parsed database response:', response);
        if (response.status === 'success' && response.next_draw_number) {
          const dbNextDraw = parseInt(response.next_draw_number, 10);
          const dbCurrentDraw = parseInt(response.current_draw_number, 10);
          console.log('🎯 ✅ Database state - Current:', dbCurrentDraw, 'Next:', dbNextDraw);
          console.log('🎯 ✅ Using NEXT draw number for new betting slips:', dbNextDraw);

          // Update UI elements with the latest draw number (if method exists)
          if (typeof this.updateUIElementsWithLatestDraw === 'function') {
            this.updateUIElementsWithLatestDraw(dbNextDraw, response.current_draw_number);
          }

          // ALWAYS return the NEXT draw number for new betting slips
          return dbNextDraw;
        } else {
          console.log('🎯 ❌ Database response invalid or missing next_draw_number');
        }
      } else {
        console.log('🎯 ❌ Database request failed with status:', xhr.status);
      }
    } catch (error) {
      console.error('🎯 ❌ Error getting next draw from database:', error);
    }

    // Priority 3: DOM-based real-time detection (ENHANCED FALLBACK for NEXT draw)
    console.log('🎯 Priority 3: DOM-based real-time detection for NEXT draw...');
    const domDetectedDraw = this.detectNextDrawNumberFromDOM();
    if (domDetectedDraw && domDetectedDraw > 0) {
      console.log('🎯 ✅ Using NEXT draw number from DOM detection:', domDetectedDraw);
      return domDetectedDraw;
    }
    console.log('🎯 ❌ No valid NEXT draw found via DOM detection');

    // Priority 4: Try to get from cashier draw display
    console.log('🎯 Priority 4: Checking CashierDrawDisplay...');
    console.log('🎯 window.CashierDrawDisplay exists:', !!window.CashierDrawDisplay);
    if (window.CashierDrawDisplay) {
      console.log('🎯 Calling CashierDrawDisplay.getDrawNumbers()...');
      const drawNumbers = window.CashierDrawDisplay.getDrawNumbers();
      console.log('🎯 CashierDrawDisplay.getDrawNumbers() result:', drawNumbers);
      if (drawNumbers && drawNumbers.upcomingDraw) {
        console.log('🎯 ✅ Using upcoming draw from CashierDrawDisplay:', drawNumbers.upcomingDraw);
        return drawNumbers.upcomingDraw;
      }
    }
    console.log('🎯 ❌ No valid draw found in CashierDrawDisplay');

    // Priority 5: Try to get from global getCurrentDrawNumber function
    console.log('🎯 Priority 5: Checking global getCurrentDrawNumber...');
    console.log('🎯 window.getCurrentDrawNumber exists:', typeof window.getCurrentDrawNumber === 'function');
    if (typeof window.getCurrentDrawNumber === 'function') {
      const globalDraw = window.getCurrentDrawNumber();
      console.log('🎯 ✅ Using global getCurrentDrawNumber:', globalDraw);
      return globalDraw;
    }
    console.log('🎯 ❌ No global getCurrentDrawNumber function found');

    // Final fallback: Use draw #1 but warn user
    console.log('🎯 ⚠️ FALLBACK: Using default draw number 1');
    console.log('🎯 ⚠️ This indicates a system configuration issue - please check:');
    console.log('🎯 ⚠️ 1. Database connectivity (php/get_next_draw_number.php)');
    console.log('🎯 ⚠️ 2. UI elements (#next-draw-number)');
    console.log('🎯 ⚠️ 3. Draw display systems (CashierDrawDisplay)');

    // Show notification about draw assignment
    this.showDrawAssignmentNotification(1);

    return 1;
  },

  // Enhanced DOM detection specifically for NEXT draw numbers (for betting slips)
  detectNextDrawNumberFromDOM: function() {
    console.log('🎯 Next Draw Detection: Starting NEXT draw number detection for betting slips...');

    const detectedNumbers = [];
    const detectionSources = [];

    // Define selectors prioritizing NEXT/UPCOMING draw elements
    const nextDrawSelectors = [
      // Highest priority: Next/Upcoming draw elements
      '#next-draw-number',
      '#upcoming-draw-number',
      '[data-draw-display="next"]',
      '[data-draw-display="upcoming"]',
      '[data-draw-type="upcoming"]',
      '[data-draw-type="next"]',
      '.next-draw',
      '.upcoming-draw',
      '.cashier-draw-number',

      // Medium priority: TV display elements that might show next draw
      '.tv-draw-number-item.next',
      '.tv-draw-number-item.upcoming',
      '.draw-container .next-draw',
      '.draw-container .upcoming-draw',

      // Lower priority: Generic elements (might be current draw)
      '.tv-draw-number-item.current',
      '.current-draw',
      '#current-draw-number',
      '.draw-number',
      '.tv-draw-number',

      // Lowest priority: Last resort elements
      '#last-draw-number',
      '.draw',
      '.draw-info'
    ];

    // Regex patterns prioritizing "next" and "upcoming" keywords
    const nextDrawPatterns = [
      /Next\s*:?\s*#?(\d+)/i,        // "Next: #35", "Next 35", "NEXT: 35"
      /Upcoming\s*:?\s*#?(\d+)/i,    // "Upcoming: #35", "Upcoming 35"
      /#(\d+)/,                      // "#35"
      /Draw\s*#?(\d+)/i,            // "Draw #35", "Draw 35"
      /Current\s*:?\s*#?(\d+)/i,    // "Current: #35" (might be next in some contexts)
      /(\d+)/                       // Just numbers as last resort
    ];

    console.log('🎯 Next Draw Detection: Scanning', nextDrawSelectors.length, 'selector types for NEXT draw...');

    // Scan all potential next draw elements
    nextDrawSelectors.forEach((selector, index) => {
      try {
        const elements = document.querySelectorAll(selector);
        console.log(`🎯 Next Draw Detection: Selector "${selector}" found ${elements.length} elements`);

        elements.forEach((element, elementIndex) => {
          if (!element) return;

          // Get text content from element
          let textContent = element.textContent || element.innerText || '';

          // Also check data attributes
          const dataDrawNumber = element.getAttribute('data-draw-number');
          const dataDrawDisplay = element.getAttribute('data-draw-display');

          if (dataDrawNumber) {
            textContent += ` ${dataDrawNumber}`;
          }

          textContent = textContent.trim();

          if (textContent) {
            console.log(`🎯 Next Draw Detection: Element [${selector}][${elementIndex}] text: "${textContent}"`);

            // Try each regex pattern
            nextDrawPatterns.forEach((pattern, patternIndex) => {
              const match = textContent.match(pattern);
              if (match && match[1]) {
                const drawNumber = parseInt(match[1], 10);
                if (drawNumber > 0 && drawNumber < 10000) { // Reasonable range
                  detectedNumbers.push(drawNumber);
                  detectionSources.push({
                    selector: selector,
                    elementIndex: elementIndex,
                    pattern: pattern.toString(),
                    text: textContent,
                    drawNumber: drawNumber,
                    priority: this.getNextDrawSelectorPriority(selector),
                    dataAttributes: {
                      'data-draw-number': dataDrawNumber,
                      'data-draw-display': dataDrawDisplay
                    }
                  });
                  console.log(`🎯 Next Draw Detection: ✅ Found draw number ${drawNumber} using pattern ${pattern} in "${textContent}"`);
                }
              }
            });
          }
        });
      } catch (error) {
        console.log(`🎯 Next Draw Detection: ❌ Error with selector "${selector}":`, error.message);
      }
    });

    console.log('🎯 Next Draw Detection: Total detected numbers:', detectedNumbers);
    console.log('🎯 Next Draw Detection: Detection sources:', detectionSources);

    if (detectedNumbers.length === 0) {
      console.log('🎯 Next Draw Detection: ❌ No next draw numbers found in DOM');
      return null;
    }

    // Analyze detected numbers to find the most likely NEXT draw
    const analysis = this.analyzeNextDrawNumbers(detectedNumbers, detectionSources);
    console.log('🎯 Next Draw Detection: Analysis result:', analysis);

    return analysis.recommendedNextDraw;
  },

  // Get priority score for next draw selectors
  getNextDrawSelectorPriority: function(selector) {
    const priorities = {
      '#next-draw-number': 100,
      '#upcoming-draw-number': 100,
      '[data-draw-display="next"]': 95,
      '[data-draw-display="upcoming"]': 95,
      '[data-draw-type="upcoming"]': 90,
      '[data-draw-type="next"]': 90,
      '.next-draw': 85,
      '.upcoming-draw': 85,
      '.cashier-draw-number': 80,
      '.tv-draw-number-item.next': 75,
      '.tv-draw-number-item.upcoming': 75,
      '.draw-container .next-draw': 70,
      '.draw-container .upcoming-draw': 70,
      '.tv-draw-number-item.current': 50,
      '.current-draw': 45,
      '#current-draw-number': 40,
      '.draw-number': 30,
      '.tv-draw-number': 25,
      '#last-draw-number': 10,
      '.draw': 5,
      '.draw-info': 5
    };

    return priorities[selector] || 1;
  },

  // Analyze detected numbers to determine the most likely NEXT draw
  analyzeNextDrawNumbers: function(detectedNumbers, detectionSources) {
    console.log('🎯 Next Draw Analysis: Analyzing', detectedNumbers.length, 'detected numbers for NEXT draw...');

    // Count frequency of each number
    const frequency = {};
    detectedNumbers.forEach(num => {
      frequency[num] = (frequency[num] || 0) + 1;
    });

    console.log('🎯 Next Draw Analysis: Number frequency:', frequency);

    // Calculate weighted scores for each number based on selector priority
    const scores = {};
    detectionSources.forEach(source => {
      const priority = source.priority || 1;
      const number = source.drawNumber;
      scores[number] = (scores[number] || 0) + priority;

      console.log(`🎯 Next Draw Analysis: Number ${number} from "${source.selector}" gets ${priority} points`);
    });

    console.log('🎯 Next Draw Analysis: Weighted scores:', scores);

    // Find the highest scoring number
    let recommendedNextDraw = null;
    let highestScore = 0;

    Object.keys(scores).forEach(num => {
      const score = scores[num];
      if (score > highestScore) {
        highestScore = score;
        recommendedNextDraw = parseInt(num, 10);
      }
    });

    // Additional logic: prefer higher numbers among similar scores (next draws are typically higher)
    if (recommendedNextDraw) {
      const similarScores = Object.keys(scores).filter(num => scores[num] >= highestScore * 0.8);
      if (similarScores.length > 1) {
        // Among similar scores, prefer the higher number
        recommendedNextDraw = Math.max(...similarScores.map(n => parseInt(n, 10)));
        console.log('🎯 Next Draw Analysis: Multiple similar scores, choosing higher number:', recommendedNextDraw);
      }
    }

    // Cross-validate with database if possible
    try {
      const timestamp = new Date().getTime();
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `php/get_next_draw_number.php?t=${timestamp}`, false);
      xhr.send();

      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        if (response.status === 'success') {
          const dbCurrentDraw = parseInt(response.current_draw_number, 10);
          const dbNextDraw = parseInt(response.next_draw_number, 10);

          console.log('🎯 Next Draw Analysis: Database validation - Current:', dbCurrentDraw, 'Next:', dbNextDraw);

          // If detected draw is current or past, use database next draw instead
          if (recommendedNextDraw <= dbCurrentDraw) {
            console.log('🎯 Next Draw Analysis: ⚠️ Detected draw is current/past, using database next draw:', dbNextDraw);
            recommendedNextDraw = dbNextDraw;
          }
        }
      }
    } catch (error) {
      console.log('🎯 Next Draw Analysis: Could not validate with database:', error.message);
    }

    return {
      detectedNumbers: detectedNumbers,
      frequency: frequency,
      scores: scores,
      recommendedNextDraw: recommendedNextDraw,
      confidence: highestScore,
      totalSources: detectionSources.length
    };
  },

  // DOM-based real-time draw number detection system (legacy method)
  detectDrawNumberFromDOM: function() {
    console.log('🎯 DOM Detection: Starting comprehensive DOM-based draw number detection...');

    const detectedNumbers = [];
    const detectionSources = [];

    // Define comprehensive selectors for draw number elements
    const drawNumberSelectors = [
      // Primary draw number elements
      '#next-draw-number',
      '#upcoming-draw-number',
      '#last-draw-number',
      '#current-draw-number',

      // Data attribute selectors
      '[data-draw-display="next"]',
      '[data-draw-display="upcoming"]',
      '[data-draw-display="current"]',
      '[data-draw-number]',
      '[data-draw-type="upcoming"]',
      '[data-draw-type="next"]',

      // Class-based selectors
      '.next-draw',
      '.upcoming-draw',
      '.current-draw',
      '.draw-number',
      '.tv-draw-number-item.current',
      '.cashier-draw-number',

      // TV display selectors
      '.tv-draw-number',
      '.tv-draw-display .draw-number',
      '.draw-container .draw-number',

      // Draw header selectors
      '.draw-header-number',
      '.draw-numbers-row .draw-number',
      '.drawNumbersRow .draw-number',

      // Generic selectors that might contain draw numbers
      '.draw',
      '.draw-info',
      '.game-draw',
      '.roulette-draw'
    ];

    // Regex patterns for extracting draw numbers from text
    const drawNumberPatterns = [
      /#(\d+)/,                    // "#2", "#15"
      /Draw\s*#?(\d+)/i,          // "Draw #2", "Draw 15", "DRAW #3"
      /Next\s*:?\s*#?(\d+)/i,     // "Next: #2", "Next 15", "NEXT: 3"
      /Upcoming\s*:?\s*#?(\d+)/i, // "Upcoming: #2", "Upcoming 15"
      /Current\s*:?\s*#?(\d+)/i,  // "Current: #2", "Current 15"
      /(\d+)/                     // Just numbers as last resort
    ];

    console.log('🎯 DOM Detection: Scanning', drawNumberSelectors.length, 'selector types...');

    // Scan all potential draw number elements
    drawNumberSelectors.forEach((selector, index) => {
      try {
        const elements = document.querySelectorAll(selector);
        console.log(`🎯 DOM Detection: Selector "${selector}" found ${elements.length} elements`);

        elements.forEach((element, elementIndex) => {
          if (!element) return;

          // Get text content from element
          let textContent = element.textContent || element.innerText || '';

          // Also check data attributes
          const dataDrawNumber = element.getAttribute('data-draw-number');
          const dataDrawDisplay = element.getAttribute('data-draw-display');

          if (dataDrawNumber) {
            textContent += ` ${dataDrawNumber}`;
          }

          textContent = textContent.trim();

          if (textContent) {
            console.log(`🎯 DOM Detection: Element [${selector}][${elementIndex}] text: "${textContent}"`);

            // Try each regex pattern
            drawNumberPatterns.forEach((pattern, patternIndex) => {
              const match = textContent.match(pattern);
              if (match && match[1]) {
                const drawNumber = parseInt(match[1], 10);
                if (drawNumber > 0 && drawNumber < 10000) { // Reasonable range
                  detectedNumbers.push(drawNumber);
                  detectionSources.push({
                    selector: selector,
                    elementIndex: elementIndex,
                    pattern: pattern.toString(),
                    text: textContent,
                    drawNumber: drawNumber,
                    dataAttributes: {
                      'data-draw-number': dataDrawNumber,
                      'data-draw-display': dataDrawDisplay
                    }
                  });
                  console.log(`🎯 DOM Detection: ✅ Found draw number ${drawNumber} using pattern ${pattern} in "${textContent}"`);
                }
              }
            });
          }
        });
      } catch (error) {
        console.log(`🎯 DOM Detection: ❌ Error with selector "${selector}":`, error.message);
      }
    });

    console.log('🎯 DOM Detection: Total detected numbers:', detectedNumbers);
    console.log('🎯 DOM Detection: Detection sources:', detectionSources);

    if (detectedNumbers.length === 0) {
      console.log('🎯 DOM Detection: ❌ No draw numbers found in DOM');
      return null;
    }

    // Analyze detected numbers to find the most likely upcoming draw
    const analysis = this.analyzeDetectedDrawNumbers(detectedNumbers, detectionSources);
    console.log('🎯 DOM Detection: Analysis result:', analysis);

    return analysis.recommendedDraw;
  },

  // Analyze detected draw numbers to determine the most likely upcoming draw
  analyzeDetectedDrawNumbers: function(detectedNumbers, detectionSources) {
    console.log('🎯 DOM Analysis: Analyzing', detectedNumbers.length, 'detected numbers...');

    // Count frequency of each number
    const frequency = {};
    detectedNumbers.forEach(num => {
      frequency[num] = (frequency[num] || 0) + 1;
    });

    console.log('🎯 DOM Analysis: Number frequency:', frequency);

    // Prioritize based on source reliability
    const sourceReliability = {
      '#next-draw-number': 10,
      '#upcoming-draw-number': 10,
      '[data-draw-display="next"]': 9,
      '[data-draw-display="upcoming"]': 9,
      '.next-draw': 8,
      '.upcoming-draw': 8,
      '.tv-draw-number-item.current': 7,
      '.current-draw': 6,
      '#current-draw-number': 5,
      '#last-draw-number': 4
    };

    // Calculate weighted scores for each number
    const scores = {};
    detectionSources.forEach(source => {
      const reliability = sourceReliability[source.selector] || 1;
      const number = source.drawNumber;
      scores[number] = (scores[number] || 0) + reliability;

      console.log(`🎯 DOM Analysis: Number ${number} from "${source.selector}" gets ${reliability} points`);
    });

    console.log('🎯 DOM Analysis: Weighted scores:', scores);

    // Find the highest scoring number
    let recommendedDraw = null;
    let highestScore = 0;

    Object.keys(scores).forEach(num => {
      const score = scores[num];
      if (score > highestScore) {
        highestScore = score;
        recommendedDraw = parseInt(num, 10);
      }
    });

    // Additional logic: prefer higher numbers (upcoming draws are typically higher)
    if (recommendedDraw) {
      const similarScores = Object.keys(scores).filter(num => scores[num] >= highestScore * 0.8);
      if (similarScores.length > 1) {
        // Among similar scores, prefer the higher number
        recommendedDraw = Math.max(...similarScores.map(n => parseInt(n, 10)));
        console.log('🎯 DOM Analysis: Multiple similar scores, choosing higher number:', recommendedDraw);
      }
    }

    return {
      detectedNumbers: detectedNumbers,
      frequency: frequency,
      scores: scores,
      recommendedDraw: recommendedDraw,
      confidence: highestScore,
      totalSources: detectionSources.length
    };
  },

  // Initialize real-time DOM monitoring for draw number changes
  initializeDOMMonitoring: function() {
    console.log('🎯 DOM Monitor: Initializing real-time draw number monitoring...');

    // Store the last detected draw number
    if (!window.lastDetectedDrawNumber) {
      window.lastDetectedDrawNumber = null;
    }

    // Create MutationObserver to watch for DOM changes
    const observer = new MutationObserver((mutations) => {
      let shouldCheckDrawNumbers = false;

      mutations.forEach((mutation) => {
        // Check if any text content changed
        if (mutation.type === 'childList' || mutation.type === 'characterData') {
          shouldCheckDrawNumbers = true;
        }

        // Check if any attributes changed that might affect draw numbers
        if (mutation.type === 'attributes') {
          const attributeName = mutation.attributeName;
          if (attributeName === 'data-draw-number' ||
              attributeName === 'data-draw-display' ||
              attributeName === 'data-draw-type') {
            shouldCheckDrawNumbers = true;
          }
        }
      });

      if (shouldCheckDrawNumbers) {
        // Debounce the check to avoid excessive calls
        clearTimeout(this.domCheckTimeout);
        this.domCheckTimeout = setTimeout(() => {
          this.checkForDrawNumberChanges();
        }, 100);
      }
    });

    // Start observing the document for changes
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
      attributes: true,
      attributeFilter: ['data-draw-number', 'data-draw-display', 'data-draw-type']
    });

    console.log('🎯 DOM Monitor: MutationObserver started');

    // Store observer reference for cleanup
    this.domObserver = observer;

    // Initial check
    this.checkForDrawNumberChanges();
  },

  // Check for draw number changes and trigger events
  checkForDrawNumberChanges: function() {
    const currentDetectedDraw = this.detectDrawNumberFromDOM();

    if (currentDetectedDraw && currentDetectedDraw !== window.lastDetectedDrawNumber) {
      console.log('🎯 DOM Monitor: Draw number changed from', window.lastDetectedDrawNumber, 'to', currentDetectedDraw);

      // Update the stored value
      window.lastDetectedDrawNumber = currentDetectedDraw;

      // Trigger custom event for draw number change
      const event = new CustomEvent('drawNumberChanged', {
        detail: {
          newDrawNumber: currentDetectedDraw,
          previousDrawNumber: window.lastDetectedDrawNumber,
          source: 'DOM_MONITOR',
          timestamp: new Date().toISOString()
        }
      });

      document.dispatchEvent(event);
      console.log('🎯 DOM Monitor: Dispatched drawNumberChanged event');

      // Update global variables if they exist
      if (typeof window.selectedDrawNumber === 'undefined' || !window.selectedDrawNumber) {
        window.detectedDrawNumber = currentDetectedDraw;
        console.log('🎯 DOM Monitor: Updated window.detectedDrawNumber to', currentDetectedDraw);
      }

      // Show notification about the change
      this.showDrawChangeNotification(currentDetectedDraw);
    }
  },

  // Show notification when draw number changes
  showDrawChangeNotification: function(newDrawNumber) {
    console.log('🎯 DOM Monitor: Showing draw change notification for draw', newDrawNumber);

    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #2196F3, #1976D2);
      color: white;
      padding: 15px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      z-index: 10001;
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: bold;
      border-left: 4px solid #0D47A1;
      max-width: 300px;
      animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="font-size: 18px;">🔄</div>
        <div>
          <div style="font-size: 16px; margin-bottom: 4px;">Draw Number Updated</div>
          <div style="font-size: 13px; opacity: 0.9;">Now showing Draw #${newDrawNumber}</div>
        </div>
      </div>
    `;

    // Add animation styles if not already present
    if (!document.getElementById('draw-change-animations')) {
      const style = document.createElement('style');
      style.id = 'draw-change-animations';
      style.textContent = `
        @keyframes slideInRight {
          from { transform: translateX(100%); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
          from { transform: translateX(0); opacity: 1; }
          to { transform: translateX(100%); opacity: 0; }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(notification);

    // Auto-remove after 3 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 3000);
  },

  // Clear invalid draw selections from localStorage and global variables
  clearInvalidDrawSelections: function(invalidDraw, currentDraw) {
    console.log('🎯 Clearing invalid draw selections:', invalidDraw, 'current:', currentDraw);

    // List of possible localStorage keys that might store draw selections
    const drawSelectionKeys = [
      'selectedDraw',
      'selectedDrawNumber',
      'currentDraw',
      'nextDraw',
      'upcomingDraw',
      'draw_selection',
      'cashier_selected_draw',
      'future_draw_selected',
      'upcoming_draws_selected',
      'draw_number_selection',
      'roulette_selected_draw',
      'betting_draw_number'
    ];

    // Clear localStorage keys that contain the invalid draw number
    drawSelectionKeys.forEach(key => {
      try {
        const storedValue = localStorage.getItem(key);
        if (storedValue) {
          const storedDraw = parseInt(storedValue, 10);
          if (storedDraw === invalidDraw || storedDraw <= currentDraw) {
            console.log('🎯 Removing invalid localStorage key:', key, '=', storedValue);
            localStorage.removeItem(key);
          }
        }
      } catch (error) {
        console.log('🎯 Error checking localStorage key:', key, error);
      }
    });

    // Clear any global variables that might contain invalid selections
    if (window.currentDrawNumber && window.currentDrawNumber <= currentDraw) {
      console.log('🎯 Clearing invalid window.currentDrawNumber:', window.currentDrawNumber);
      window.currentDrawNumber = null;
    }

    // Dispatch event to notify other components about the cleanup
    const event = new CustomEvent('invalidDrawSelectionCleared', {
      detail: {
        invalidDraw: invalidDraw,
        currentDraw: currentDraw,
        timestamp: new Date().toISOString()
      }
    });
    document.dispatchEvent(event);

    console.log('🎯 Invalid draw selection cleanup completed');
  },

  // Proactively clean up stale draw selections on page load
  cleanupStaleDrawSelections: function() {
    console.log('🎯 Proactive cleanup: Checking for stale draw selections...');

    try {
      // Get current draw from database
      const timestamp = new Date().getTime();
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `php/get_next_draw_number.php?t=${timestamp}`, false);
      xhr.send();

      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        if (response.status === 'success' && response.current_draw_number) {
          const currentDraw = parseInt(response.current_draw_number, 10);
          const nextDraw = parseInt(response.next_draw_number, 10);

          console.log('🎯 Proactive cleanup: Current draw:', currentDraw, 'Next draw:', nextDraw);

          // Check if there's a manually selected draw that's invalid
          if (window.selectedDrawNumber) {
            const selectedDraw = parseInt(window.selectedDrawNumber, 10);
            if (selectedDraw <= currentDraw) {
              console.log('🎯 Proactive cleanup: Found stale manual selection:', selectedDraw);
              this.clearInvalidDrawSelections(selectedDraw, currentDraw);
              window.selectedDrawNumber = null;
            }
          }

          // Check localStorage for stale selections
          const drawSelectionKeys = [
            'selectedDraw',
            'selectedDrawNumber',
            'currentDraw',
            'nextDraw',
            'upcomingDraw',
            'draw_selection',
            'cashier_selected_draw',
            'future_draw_selected',
            'upcoming_draws_selected',
            'draw_number_selection',
            'roulette_selected_draw',
            'betting_draw_number'
          ];

          let cleanedCount = 0;
          drawSelectionKeys.forEach(key => {
            try {
              const storedValue = localStorage.getItem(key);
              if (storedValue) {
                const storedDraw = parseInt(storedValue, 10);
                if (!isNaN(storedDraw) && storedDraw <= currentDraw) {
                  console.log('🎯 Proactive cleanup: Removing stale localStorage:', key, '=', storedValue);
                  localStorage.removeItem(key);
                  cleanedCount++;
                }
              }
            } catch (error) {
              console.log('🎯 Proactive cleanup: Error checking key:', key, error);
            }
          });

          if (cleanedCount > 0) {
            console.log('🎯 Proactive cleanup: Cleaned', cleanedCount, 'stale localStorage entries');

            // Show notification about cleanup
            this.showCleanupNotification(cleanedCount, nextDraw);
          } else {
            console.log('🎯 Proactive cleanup: No stale selections found');
          }
        }
      }
    } catch (error) {
      console.log('🎯 Proactive cleanup: Error during cleanup:', error);
    }
  },

  // Show notification about cleanup
  showCleanupNotification: function(cleanedCount, nextDraw) {
    console.log('🎯 Showing cleanup notification');

    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #FF9800, #F57C00);
      color: white;
      padding: 15px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      z-index: 10001;
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: bold;
      border-left: 4px solid #E65100;
      max-width: 350px;
      animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 10px;">
        <div style="font-size: 18px;">🧹</div>
        <div>
          <div style="font-size: 16px; margin-bottom: 4px;">Draw Selection Cleaned</div>
          <div style="font-size: 13px; opacity: 0.9;">Removed ${cleanedCount} stale selection(s). Now using Draw #${nextDraw}</div>
        </div>
      </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 4 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 4000);
  },

  // Stop DOM monitoring
  stopDOMMonitoring: function() {
    if (this.domObserver) {
      this.domObserver.disconnect();
      console.log('🎯 DOM Monitor: Stopped monitoring');
    }

    if (this.domCheckTimeout) {
      clearTimeout(this.domCheckTimeout);
    }
  },

  // Generate a unique ID for a bet based on its element
  generateBetId: function(element) {
    // Get all classes of the element
    const classes = Array.from(element.classList);
    return classes.join('-');
  },

  // Get the type of bet (straight, corner, etc.)
  getBetType: function(element) {
    // Check element classes to determine bet type
    if (element.classList.contains('regular')) {
      return 'straight';
    } else if (element.classList.contains('corner')) {
      // Check specifically for six line bets that use corner intersection points
      if (element.classList.contains('corner4') ||
          element.classList.contains('corner7') ||
          element.classList.contains('corner10') ||
          element.classList.contains('corner13') ||
          element.classList.contains('corner16') ||
          element.classList.contains('corner19') ||
          element.classList.contains('corner22') ||
          element.classList.contains('corner25') ||
          element.classList.contains('corner28') ||
          element.classList.contains('corner31') ||
          element.classList.contains('corner34')) {
        return 'sixline'; // Six line bet (double street)
      }
      return 'corner';
    } else if (element.classList.contains('line')) {
      if (element.classList.contains('line1') ||
          element.classList.contains('line2') ||
          element.classList.contains('line3')) {
        return 'split'; // Split bets between 0 and other numbers
      }
      return 'split';
    } else if (element.classList.contains('between')) {
      if (element.classList.contains('with0')) {
        return 'split';
      } else if ((element.classList.contains('between1') ||
                 element.classList.contains('between4') ||
                 element.classList.contains('between7') ||
                 element.classList.contains('between10') ||
                 element.classList.contains('between13') ||
                 element.classList.contains('between16') ||
                 element.classList.contains('between19') ||
                 element.classList.contains('between22') ||
                 element.classList.contains('between25') ||
                 element.classList.contains('between28') ||
                 element.classList.contains('between31') ||
                 element.classList.contains('between34'))) {
        return 'street';
      } else if (element.classList.contains('corner') &&
                (element.classList.contains('between3') ||
                 element.classList.contains('between6') ||
                 element.classList.contains('between9') ||
                 element.classList.contains('between12') ||
                 element.classList.contains('between15') ||
                 element.classList.contains('between18') ||
                 element.classList.contains('between21') ||
                 element.classList.contains('between24') ||
                 element.classList.contains('between27') ||
                 element.classList.contains('between30') ||
                 element.classList.contains('between33') ||
                 element.classList.contains('between36'))) {
        return 'sixline'; // Six line bet (double street)
      }
      return 'split';
    } else if (element.classList.contains('bet2to1-1') ||
               element.classList.contains('bet2to1-2') ||
               element.classList.contains('bet2to1-3')) {
      return 'column';
    } else if (element.classList.contains('column-1st12') ||
               element.classList.contains('column-2nd12') ||
               element.classList.contains('column-3rd12')) {
      return 'dozen';
    } else if (element.classList.contains('column-1to18') ||
               element.classList.contains('column-19to36') ||
               element.classList.contains('column-even') ||
               element.classList.contains('column-odd') ||
               element.classList.contains('column-red') ||
               element.classList.contains('column-black')) {
      return 'even-money';
    }

    return 'unknown';
  },

  // Get human-readable information about the bet
  getBetInfo: function(element, betType) {
    // For regular numbers (straight up bets)
    for (let i = 0; i <= 36; i++) {
      if (element.classList.contains(`regular${i}`)) {
        return `Straight Up on ${i}`;
      }
    }

    // Column bets
    if (element.classList.contains('bet2to1-1')) {
      return 'Column (3,6,9,12,15,18,21,24,27,30,33,36)';
    } else if (element.classList.contains('bet2to1-2')) {
      return 'Column (2,5,8,11,14,17,20,23,26,29,32,35)';
    } else if (element.classList.contains('bet2to1-3')) {
      return 'Column (1,4,7,10,13,16,19,22,25,28,31,34)';
    }

    // Dozen bets
    if (element.classList.contains('column-1st12')) {
      return '1st Dozen (1-12)';
    } else if (element.classList.contains('column-2nd12')) {
      return '2nd Dozen (13-24)';
    } else if (element.classList.contains('column-3rd12')) {
      return '3rd Dozen (25-36)';
    }

    // Even money bets
    if (element.classList.contains('column-1to18')) {
      return 'Low Numbers (1-18)';
    } else if (element.classList.contains('column-19to36')) {
      return 'High Numbers (19-36)';
    } else if (element.classList.contains('column-even')) {
      return 'Even Numbers (2,4,6,8,10,12,14,16,18,20,22,24,26,28,30,32,34,36)';
    } else if (element.classList.contains('column-odd')) {
      return 'Odd Numbers (1,3,5,7,9,11,13,15,17,19,21,23,25,27,29,31,33,35)';
    } else if (element.classList.contains('column-red')) {
      return 'Red Numbers (1,3,5,7,9,12,14,16,18,19,21,23,25,27,30,32,34,36)';
    } else if (element.classList.contains('column-black')) {
      return 'Black Numbers (2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35)';
    }

    // For corner bets
    if (betType === 'corner') {
      for (let i = 1; i < 37; i++) {
        if (element.classList.contains(`corner${i}`)) {
          if (i === 1) {
            return 'First Four (0,1,2,3)';
          } else if (i === 2) {
            return 'Corner (0,1,2)';
          } else if (i === 3) {
            return 'Corner (0,2,3)';
          } else if (i >= 4 && i <= 33) {
            // Get the actual corner numbers
            const cornerNumbers = this.getCornerNumbers(i);
            return `Corner (${cornerNumbers})`;
          }
        }
      }
      return 'Corner Bet';
    }

    // For six line bets (6 numbers in two rows)
    else if (betType === 'sixline') {
      // Six line bets at corner intersections (where two streets meet)
      if (element.classList.contains('corner4')) {
        return 'Six Line (1,2,3,4,5,6)';
      } else if (element.classList.contains('corner7')) {
        return 'Six Line (4,5,6,7,8,9)';
      } else if (element.classList.contains('corner10')) {
        return 'Six Line (7,8,9,10,11,12)';
      } else if (element.classList.contains('corner13')) {
        return 'Six Line (10,11,12,13,14,15)';
      } else if (element.classList.contains('corner16')) {
        return 'Six Line (13,14,15,16,17,18)';
      } else if (element.classList.contains('corner19')) {
        return 'Six Line (16,17,18,19,20,21)';
      } else if (element.classList.contains('corner22')) {
        return 'Six Line (19,20,21,22,23,24)';
      } else if (element.classList.contains('corner25')) {
        return 'Six Line (22,23,24,25,26,27)';
      } else if (element.classList.contains('corner28')) {
        return 'Six Line (25,26,27,28,29,30)';
      } else if (element.classList.contains('corner31')) {
        return 'Six Line (28,29,30,31,32,33)';
      } else if (element.classList.contains('corner34')) {
        return 'Six Line (31,32,33,34,35,36)';
      }

      // Six line bets using the between+corner elements at the end of rows
      for (let i = 3; i <= 36; i += 3) {
        if (element.classList.contains(`between${i}`)) {
          return `Six Line (${i-2},${i-1},${i},${i+1},${i+2},${i+3})`;
        }
      }
      return 'Six Line Bet';
    }

    // For split bets
    else if (betType === 'split') {
      // Handle special splits between 0 and another number
      if (element.classList.contains('line1')) {
        return 'Split (0,1)';
      } else if (element.classList.contains('line2')) {
        return 'Split (0,2)';
      } else if (element.classList.contains('line3')) {
        return 'Split (0,3)';
      } else if (element.classList.contains('with0')) {
        // This is for the between2 element that's marked as with0
        return 'Split (0,2)';
      }

      // For standard horizontal splits
      for (let i = 1; i < 36; i++) {
        if (element.classList.contains(`between${i}`) && (i % 3 !== 1)) {
          return `Split (${i-1},${i})`;
        }
      }

      // For vertical splits (line elements)
      for (let i = 4; i <= 36; i++) {
        if (element.classList.contains(`line${i}`)) {
          return `Split (${i-3},${i})`;
        }
      }

      return 'Split Bet';
    }

    // For street bets (3 numbers in a row)
    else if (betType === 'street') {
      for (let i = 1; i <= 34; i += 3) {
        if (element.classList.contains(`between${i}`)) {
          return `Street (${i},${i+1},${i+2}) - Pays 11:1`;
        }
      }
      return 'Street Bet - Pays 11:1';
    }

    return 'Unknown Bet Type';
  },

  // Helper function to get corner numbers
  getCornerNumbers: function(i) {
    if (i === 1) {
      return "0,1,2,3";
    } else if (i === 2 || i === 3) {
      return `0,${i-1},${i}`;
    } else if (i > 3) {
      // Standard corner bet calculation
      const topLeft = i - 4; // Top left number in the corner
      const topRight = i - 3; // Top right number in the corner
      const bottomLeft = i - 1; // Bottom left number in the corner
      const bottomRight = i; // Bottom right number in the corner
      return `${topLeft},${topRight},${bottomLeft},${bottomRight}`;
    }
    return "";
  },

  // Get multiplier for a bet type
  getMultiplier: function(betType) {
    switch (betType) {
      case 'straight': return 35;
      case 'split': return 17;
      case 'street': return 11;
      case 'corner': return 8;
      case 'basket': return 6;
      case 'sixline': return 5;
      case 'dozen':
      case 'column':
        return 2;
      case 'even-money':
      case 'red':
      case 'black':
      case 'even':
      case 'odd':
      case 'high':
      case 'low':
        return 1;
      default: return 0;
    }
  },

  // Update the display with current bets
  updateDisplay: function() {
    // Get references to necessary elements
    const betsList = document.querySelector('.bet-display-list');

    if (!betsList) return; // Exit if elements don't exist

    // Check for incorrectly added number 1 bets before displaying
    const hasNumber1Chip = $(".regular1, .number1").has(".betting-chip").length > 0;
    if (!hasNumber1Chip) {
      // If there's no chip on number 1, remove any number 1 bets
      const existingNumber1Bets = this.bets.filter(bet =>
        bet.description === "Straight Up on 1"
      );

      if (existingNumber1Bets.length > 0) {
        console.log("Removing incorrectly added number 1 bets in updateDisplay");
        this.bets = this.bets.filter(bet =>
          bet.description !== "Straight Up on 1"
        );
      }
    }

    // Special handling for number 0 bets
    // If we have a number 0 bet, make sure we don't have any incorrect number 1 bets
    const hasNumber0Bet = this.bets.some(bet => bet.description === "Straight Up on 0");
    if (hasNumber0Bet) {
      // Check if there are any number 1 bets without chips
      const hasNumber1Chip = $(".regular1, .number1").has(".betting-chip").length > 0;
      if (!hasNumber1Chip) {
        // Remove any number 1 bets that might have been added incorrectly
        const existingNumber1Bets = this.bets.filter(bet =>
          bet.description === "Straight Up on 1"
        );

        if (existingNumber1Bets.length > 0) {
          console.log("Removing incorrectly added number 1 bets when displaying number 0 bet");
          this.bets = this.bets.filter(bet =>
            bet.description !== "Straight Up on 1"
          );
        }
      }
    }

    // Clear the list
    betsList.innerHTML = '';

    // If no bets, show message and zero total stakes
    if (this.bets.length === 0) {
      betsList.innerHTML = '<div class="no-bets-message">No bets placed yet</div>';

      // Update the summary section with zero total stakes and bet count (no buttons, no global stake)
      document.querySelector('.bet-display-summary').innerHTML = `
        <div class="bet-count">
          <span>Number of Bets:</span>
          <div class="count-value">0</div>
        </div>
        <div class="total-stakes">
          <span>Total Stakes:</span>
          <div class="stakes-value">$0.00</div>
        </div>
      `;

      // Trigger change calculation if the calculator exists
      if (document.getElementById('received-amount')) {
        const event = new Event('input');
        document.getElementById('received-amount').dispatchEvent(event);
      }

      return;
    }

    // Add each bet to the list
    let totalStakes = 0;

    this.bets.forEach(bet => {
      const betItem = document.createElement('div');
      betItem.className = 'bet-item';
      betItem.dataset.betId = bet.id;

      // Calculate total stakes
      totalStakes += bet.amount;

      // Get the appropriate badge class based on bet type
      let badgeClass = '';
      switch(bet.type) {
        case 'straight': badgeClass = 'badge-straight'; break;
        case 'split': badgeClass = 'badge-split'; break;
        case 'street': badgeClass = 'badge-street'; break;
        case 'sixline': badgeClass = 'badge-sixline'; break;
        case 'corner': badgeClass = 'badge-corner'; break;
        case 'column': badgeClass = 'badge-column'; break;
        case 'dozen': badgeClass = 'badge-dozen'; break;
        case 'even-money': badgeClass = 'badge-even-money'; break;
        default: badgeClass = '';
      }

      // Format the description with highlighted numbers
      let description = bet.description;

      // Highlight the specific numbers in the description
      description = this.highlightNumbers(description);

      // Create the bet type display with badge
      let betTypeDisplay = `<span class="bet-type-badge ${badgeClass}">${bet.type}</span>${description}`;

      // Create bet item content with edit and delete buttons
      betItem.innerHTML = `
        <div class="bet-info">
          <div class="bet-item-type">${betTypeDisplay}</div>
          <div class="bet-details">
            <div class="bet-item-amount">$${bet.amount.toFixed(2)}</div>
            <div class="bet-item-return">Return: $${bet.potentialReturn.toFixed(2)}</div>
          </div>
        </div>
        <div class="bet-item-actions">
          <button class="bet-action-btn edit-bet-btn" data-bet-id="${bet.id}">
            <i class="fas fa-edit"></i> Edit
          </button>
          <button class="bet-action-btn delete-bet-btn" data-bet-id="${bet.id}">
            <i class="fas fa-trash-alt"></i> Remove
          </button>
        </div>
      `;

      betsList.appendChild(betItem);
    });

    // Update summary with bet count and total stakes only (no global stake input)
    document.querySelector('.bet-display-summary').innerHTML = `
      <div class="bet-count">
        <span>Number of Bets:</span>
        <div class="count-value">${this.bets.length}</div>
      </div>
      <div class="total-stakes">
        <span>Total Stakes:</span>
        <div class="stakes-value">$${totalStakes.toFixed(2)}</div>
      </div>
    `;

    // Trigger change calculation if the calculator exists
    if (document.getElementById('received-amount')) {
      const event = new Event('input');
      document.getElementById('received-amount').dispatchEvent(event);
    }

    // Print button event listener removed as we no longer have the button in the summary

    // Add event listeners for edit and delete buttons
    document.querySelectorAll('.edit-bet-btn').forEach(button => {
      button.addEventListener('click', event => {
        const betId = event.currentTarget.dataset.betId;
        this.openUpdateStakeModal(betId);
      });
    });

    document.querySelectorAll('.delete-bet-btn').forEach(button => {
      button.addEventListener('click', event => {
        const betId = event.currentTarget.dataset.betId;
        this.removeBet(betId);

        if (playAudio) {
          selectSound.play();
        }
      });
    });

    // Global stake input and button event listeners removed
  },

  // Update the stake amount for all bets
  updateAllBetStakes: function(newAmount) {
    if (this.bets.length === 0) return false;

    // Calculate the total difference in stake amount
    let totalDifference = 0;
    this.bets.forEach(bet => {
      totalDifference += (newAmount - bet.amount);
    });

    // Check if we have enough cash for all updates
    if (totalDifference > 0 && cashSum < totalDifference) {
      alert("Not enough cash to update all bets to this stake amount.");
      return false;
    }

    // Special handling for number 1 bets - consolidate them first
    const number1Bets = this.bets.filter(bet => bet.description === "Straight Up on 1");
    if (number1Bets.length > 1) {
      console.log(`Found ${number1Bets.length} bets on number 1, consolidating them`);

      // Keep only the first bet
      const keepBet = number1Bets[0];

      // Remove all other number 1 bets
      this.bets = this.bets.filter(bet =>
        bet.description !== "Straight Up on 1" || bet === keepBet
      );

      console.log("Consolidated number 1 bets");
    }

    // Update each bet
    this.bets.forEach(bet => {
      // Update the bet amount and potential return
      bet.amount = newAmount;
      bet.potentialReturn = newAmount + (newAmount * bet.multiplier);

      // Special handling for number 1 straight up bet
      if (bet.description === "Straight Up on 1") {
        // Update all number 1 elements
        document.querySelectorAll('.regular1, .number1').forEach(element => {
          // Remove existing chip
          element.innerHTML = '';

          // Add new chip with updated amount
          const chipClass = getChipClass(newAmount);
          element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
        });

        // Skip the rest of the processing for this bet
        return;
      }

      // Update chip on the board for all other bets
      try {
        // First try using the element selector
        const elements = document.querySelectorAll(bet.elementSelector);
        if (elements && elements.length > 0) {
          elements.forEach(element => {
            // Remove existing chip
            element.innerHTML = '';

            // Add new chip with updated amount
            const chipClass = getChipClass(newAmount);
            element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
          });
        } else {
          // If no elements found, try using the class names from the bet ID
          const classNames = bet.id.split('-');
          classNames.forEach(className => {
            if (className) {
              const selector = '.' + className;
              const elements = document.querySelectorAll(selector);
              elements.forEach(element => {
                if (element.querySelector('.betting-chip')) {
                  // Remove existing chip
                  element.innerHTML = '';

                  // Add new chip with updated amount
                  const chipClass = getChipClass(newAmount);
                  element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
                }
              });
            }
          });
        }

        // Special handling for dozen bets (1st12, 2nd12, 3rd12)
        if (bet.type === 'dozen') {
          let dozenSelector = '';
          if (bet.description.includes('1st Dozen')) {
            dozenSelector = '.column-1st12';
          } else if (bet.description.includes('2nd Dozen')) {
            dozenSelector = '.column-2nd12';
          } else if (bet.description.includes('3rd Dozen')) {
            dozenSelector = '.column-3rd12';
          }

          if (dozenSelector) {
            const dozenElements = document.querySelectorAll(dozenSelector);
            dozenElements.forEach(element => {
              // Remove existing chip
              element.innerHTML = '';

              // Add new chip with updated amount
              const chipClass = getChipClass(newAmount);
              element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
            });
          }
        }

        // Special handling for even money bets (1to18, 19to36, even, odd, red, black)
        if (bet.type === 'even-money') {
          let evenMoneySelector = '';
          if (bet.description.includes('1 to 18')) {
            evenMoneySelector = '.column-1to18';
          } else if (bet.description.includes('19 to 36')) {
            evenMoneySelector = '.column-19to36';
          } else if (bet.description.includes('Even')) {
            evenMoneySelector = '.column-even';
          } else if (bet.description.includes('Odd')) {
            evenMoneySelector = '.column-odd';
          } else if (bet.description.includes('Red')) {
            evenMoneySelector = '.column-red';
          } else if (bet.description.includes('Black')) {
            evenMoneySelector = '.column-black';
          }

          if (evenMoneySelector) {
            const evenMoneyElements = document.querySelectorAll(evenMoneySelector);
            evenMoneyElements.forEach(element => {
              // Remove existing chip
              element.innerHTML = '';

              // Add new chip with updated amount
              const chipClass = getChipClass(newAmount);
              element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
            });
          }
        }

        // Special handling for column bets (bet2to1-1, bet2to1-2, bet2to1-3)
        if (bet.type === 'column') {
          let columnSelector = '';
          if (bet.description.includes('Column (3,6,9')) {
            columnSelector = '.bet2to1-1';
          } else if (bet.description.includes('Column (2,5,8')) {
            columnSelector = '.bet2to1-2';
          } else if (bet.description.includes('Column (1,4,7')) {
            columnSelector = '.bet2to1-3';
          }

          if (columnSelector) {
            const columnElements = document.querySelectorAll(columnSelector);
            columnElements.forEach(element => {
              // Remove existing chip
              element.innerHTML = '';

              // Add new chip with updated amount
              const chipClass = getChipClass(newAmount);
              element.innerHTML = `<div id="${newAmount}" class="betting-chip betting-chip-shadow ${chipClass}">${newAmount}</div>`;
            });
          }
        }
      } catch (error) {
        console.error('Error updating chip on board:', error);
      }
    });

    // Additionally, update all chips on the board with the class "betting-chip"
    try {
      document.querySelectorAll('.part .betting-chip').forEach(chip => {
        // Update the chip ID and text content
        chip.id = newAmount;
        chip.textContent = newAmount;

        // Update the chip class
        const chipClass = getChipClass(newAmount);
        chip.className = `betting-chip betting-chip-shadow ${chipClass}`;
      });
    } catch (error) {
      console.error('Error updating all chips on board:', error);
    }

    // Update betting totals
    let newBetTotal = this.bets.length * newAmount;
    $(".bet-total").html(`${newBetTotal.toFixed(2)}`);

    // Update cash
    cashSum = cashSum - totalDifference;
    $(".cash-total").html(`${cashSum.toFixed(2)}`);

    // Update global bet sum
    betSum = newBetTotal;

    // Update display
    this.updateDisplay();

    // Log success message
    console.log(`Updated all bets to $${newAmount}. Total bets: ${this.bets.length}, Total stake: $${newBetTotal}`);

    return true;
  },

  // Open the update stake modal for a specific bet
  openUpdateStakeModal: function(betId) {
    const bet = this.bets.find(b => b.id === betId);

    if (bet) {
      // Update modal with bet info
      document.querySelector('.update-bet-type').textContent = bet.description;
      document.querySelector('.current-stake-amount').textContent = bet.amount.toFixed(2);

      // Set input value to current amount
      const updateStakeInput = document.getElementById('update-stake-input');
      updateStakeInput.value = bet.amount;
      updateStakeInput.dataset.betId = betId;

      // Show the modal
      document.querySelector('.update-stake-modal').classList.add('visible');
      updateStakeInput.focus();

      // Validate amount
      validateUpdateStakeAmount(bet.amount);
    }
  },

  // Get validated draw number for printing with comprehensive validation
  getValidatedDrawNumberForPrint: function() {
    console.log('🎰 Print Validation: Starting draw number validation for print...');

    try {
      // Use the enhanced getCurrentDrawNumber method with all validations
      const detectedDraw = this.getCurrentDrawNumber();
      console.log('🎰 Print Validation: Detected draw number:', detectedDraw);

      // Get current database state for validation
      const timestamp = new Date().getTime();
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `php/get_next_draw_number.php?t=${timestamp}`, false);
      xhr.send();

      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        if (response.status === 'success') {
          const currentDraw = parseInt(response.current_draw_number, 10);
          const nextDraw = parseInt(response.next_draw_number, 10);

          console.log('🎰 Print Validation: Database state - Current:', currentDraw, 'Next:', nextDraw);

          // Validate the detected draw number - must be FUTURE draw for new betting slips
          if (detectedDraw <= currentDraw) {
            console.log('🎰 Print Validation: ❌ Detected draw is current/past - forcing next draw');
            console.log('🎰 Print Validation: Detected:', detectedDraw, 'Current:', currentDraw, 'Next:', nextDraw);

            // Force use of next draw number for new betting slips
            const correctedDraw = nextDraw;
            console.log('🎰 Print Validation: ✅ Using corrected next draw number:', correctedDraw);

            return {
              isValid: true,
              drawNumber: correctedDraw,
              source: 'DATABASE_API_CORRECTED',
              details: {
                originalDetectedDraw: detectedDraw,
                correctedDraw: correctedDraw,
                currentDraw: currentDraw,
                nextDraw: nextDraw,
                isUpcoming: true,
                isCorrected: true,
                message: `Corrected from draw #${detectedDraw} to next draw #${correctedDraw} for new betting slip.`
              }
            };
          }

          // Warn if detected draw is far in the future (more than 10 draws ahead)
          if (detectedDraw > nextDraw + 10) {
            console.log('🎰 Print Validation: ⚠️ Warning - detected draw is far in future:', detectedDraw, 'vs expected:', nextDraw);
          }

          // Valid draw number
          return {
            isValid: true,
            drawNumber: detectedDraw,
            source: this.getDrawNumberSource(detectedDraw, response),
            details: {
              detectedDraw: detectedDraw,
              currentDraw: currentDraw,
              nextDraw: nextDraw,
              isUpcoming: detectedDraw === nextDraw,
              isFuture: detectedDraw > nextDraw
            }
          };
        }
      }

      // Database validation failed, but we have a detected draw
      if (detectedDraw && detectedDraw > 0) {
        console.log('🎰 Print Validation: ⚠️ Database validation failed, using detected draw with warning');
        return {
          isValid: true,
          drawNumber: detectedDraw,
          source: 'FALLBACK_DETECTION',
          details: {
            detectedDraw: detectedDraw,
            warning: 'Could not validate against database, using detected draw number'
          }
        };
      }

      // No valid draw number detected
      return {
        isValid: false,
        error: 'NO_DRAW_DETECTED',
        drawNumber: null,
        details: {
          message: 'Could not detect a valid draw number for printing'
        }
      };

    } catch (error) {
      console.error('🎰 Print Validation: Error during validation:', error);
      return {
        isValid: false,
        error: 'VALIDATION_ERROR',
        drawNumber: null,
        details: {
          error: error.message,
          message: 'Error occurred while validating draw number'
        }
      };
    }
  },

  // Determine the source of the draw number for logging
  getDrawNumberSource: function(drawNumber, databaseResponse) {
    if (window.selectedDrawNumber && window.selectedDrawNumber === drawNumber) {
      return 'MANUAL_SELECTION';
    } else if (databaseResponse.next_draw_number && drawNumber === parseInt(databaseResponse.next_draw_number, 10)) {
      return 'DATABASE_API';
    } else {
      return 'DOM_DETECTION';
    }
  },

  // Show print error notification
  showPrintErrorNotification: function(errorType, details) {
    console.log('🎰 Print Error: Showing error notification:', errorType, details);

    let title = 'Print Error';
    let message = 'Cannot print betting slip';
    let icon = '❌';

    switch (errorType) {
      case 'PAST_DRAW_ERROR':
        title = 'Cannot Print Past Draw';
        message = `Cannot print betting slip for completed draw #${details.detectedDraw}. Please select an upcoming draw.`;
        icon = '⏰';
        break;
      case 'NO_DRAW_DETECTED':
        title = 'No Draw Number';
        message = 'Could not determine which draw to print the betting slip for. Please select a draw manually.';
        icon = '❓';
        break;
      case 'VALIDATION_ERROR':
        title = 'Validation Error';
        message = 'Error occurred while validating draw number. Please try again.';
        icon = '⚠️';
        break;
    }

    // Create error notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #f44336, #d32f2f);
      color: white;
      padding: 20px 25px;
      border-radius: 10px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.4);
      z-index: 10002;
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: bold;
      border-left: 5px solid #b71c1c;
      max-width: 400px;
      animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 15px;">
        <div style="font-size: 24px;">${icon}</div>
        <div>
          <div style="font-size: 18px; margin-bottom: 8px;">${title}</div>
          <div style="font-size: 14px; opacity: 0.9; line-height: 1.4;">${message}</div>
        </div>
      </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 6 seconds (longer for error messages)
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 6000);
  },

  // Show print success notification with draw number
  showPrintSuccessNotification: function(drawNumber, source, details) {
    console.log('🎰 Print Success: Showing success notification for draw:', drawNumber);

    let sourceText = '';
    switch (source) {
      case 'MANUAL_SELECTION':
        sourceText = 'manually selected';
        break;
      case 'DATABASE_API':
        sourceText = 'from database';
        break;
      case 'DOM_DETECTION':
        sourceText = 'auto-detected';
        break;
      default:
        sourceText = 'detected';
    }

    const isUpcoming = details && details.isUpcoming;
    const statusText = isUpcoming ? '(Next Draw)' : '(Future Draw)';

    // Create success notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #4CAF50, #45a049);
      color: white;
      padding: 18px 22px;
      border-radius: 10px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
      z-index: 10002;
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: bold;
      border-left: 5px solid #2E7D32;
      max-width: 380px;
      animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 15px;">
        <div style="font-size: 24px;">🎰</div>
        <div>
          <div style="font-size: 18px; margin-bottom: 6px;">Betting Slip Printed</div>
          <div style="font-size: 14px; opacity: 0.9;">Draw #${drawNumber} ${statusText}</div>
          <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">Source: ${sourceText}</div>
        </div>
      </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 4 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 4000);
  },

  // Enhanced database save with draw number validation and logging
  saveBettingSlipToDatabaseEnhanced: function(barcodeNumber, bets, totalStakes, totalPotentialReturn, drawNumber, drawSource) {
    console.log('🎰 Enhanced Save: Saving betting slip to database with draw validation...');
    console.log('🎰 Enhanced Save: Draw number:', drawNumber, 'Source:', drawSource);

    try {
      // Use the existing saveBettingSlipToDatabase function if available
      if (typeof saveBettingSlipToDatabase === 'function') {
        // Call the existing function - it should handle the draw number
        saveBettingSlipToDatabase(barcodeNumber, bets, totalStakes, totalPotentialReturn);
        console.log('🎰 Enhanced Save: Used existing saveBettingSlipToDatabase function');
      } else {
        // Fallback: Create the formData directly for more control
        console.log('🎰 Enhanced Save: Using direct API call fallback');

        const formData = new FormData();
        formData.append('action', 'save_slip');
        formData.append('barcode', barcodeNumber);
        formData.append('bets', JSON.stringify(bets));
        formData.append('total_stakes', totalStakes);
        formData.append('potential_return', totalPotentialReturn);
        formData.append('date', new Date().toISOString());
        formData.append('draw_number', drawNumber);
        formData.append('draw_source', drawSource);

        // Make the AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'php/slip_api.php', true);
        xhr.onload = function() {
          if (xhr.status === 200) {
            console.log('🎰 Enhanced Save: Betting slip saved successfully with draw #' + drawNumber);
          } else {
            console.error('🎰 Enhanced Save: Error saving betting slip:', xhr.responseText);
          }
        };
        xhr.onerror = function() {
          console.error('🎰 Enhanced Save: Network error while saving betting slip');
        };
        xhr.send(formData);
      }
    } catch (error) {
      console.error('🎰 Enhanced Save: Error during database save:', error);
    }
  },

  // Enhanced Print betting slip function with DOM-based detection integration
  printBettingSlip: function() {
    console.log('🎰 Enhanced Print Betting Slip: Starting print process...');

    // Generate a simple random barcode number (for display purposes)
    const barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();

    // Format current date and time for the receipt
    const now = new Date();
    const dateTimeStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();

    // Get the validated draw number using enhanced detection system
    const drawNumberResult = this.getValidatedDrawNumberForPrint();

    if (!drawNumberResult.isValid) {
      // Show error notification and abort print
      this.showPrintErrorNotification(drawNumberResult.error, drawNumberResult.details);
      return;
    }

    const drawNumber = drawNumberResult.drawNumber;
    const drawSource = drawNumberResult.source;
    const drawDetails = drawNumberResult.details;
    console.log('🎰 Enhanced Print: Using validated draw number:', drawNumber, 'from source:', drawSource);

    // Calculate total stakes and potential return
    let totalStakes = 0;
    let totalPotentialReturn = 0;
    this.bets.forEach(bet => {
      totalStakes += bet.amount;
      totalPotentialReturn += bet.potentialReturn;
    });

    // Register the ticket with the ticket manager (if ticketManager is defined)
    if (typeof ticketManager !== 'undefined') {
      ticketManager.addTicket(barcodeNumber, this.bets, totalStakes, totalPotentialReturn);
    }

    // Enhanced database save with draw number validation
    console.log('🎰 MAIN SCRIPTS: Saving betting slip to database immediately!');
    console.log('🎰 MAIN SCRIPTS: Data being saved:', { barcodeNumber, totalStakes, totalPotentialReturn, drawNumber, drawSource });
    this.saveBettingSlipToDatabaseEnhanced(barcodeNumber, this.bets, totalStakes, totalPotentialReturn, drawNumber, drawSource);

    // Check if print modal already exists, if not, create it
    if (!document.querySelector('.print-slip-modal')) {
      // Create the print modal structure
      const printModal = document.createElement('div');
      printModal.className = 'print-slip-modal';
      printModal.innerHTML = `
        <div class="print-slip-container">
          <div class="print-slip-header">
            <h2>Betting Slip Preview</h2>
            <div class="print-slip-close"><i class="fas fa-times"></i></div>
          </div>
          <div class="print-slip-body">
            <div class="print-slip-content"></div>
            <div class="print-slip-actions">
              <button class="print-action-button"><i class="fas fa-print"></i> Print Slip</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(printModal);

      // Add event listeners for the new modal
      document.querySelector('.print-slip-close').addEventListener('click', function() {
        document.querySelector('.print-slip-modal').classList.remove('visible');
      });

      document.querySelector('.print-action-button').addEventListener('click', () => {
        // Create a hidden iframe for printing just the receipt
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);

        const slipContent = document.querySelector('.print-slip-content').innerHTML;

        // Write the content to the iframe
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.write(`
          <html>
          <head>
            <title>Roulette Betting Slip</title>
            <style>
              body {
                font-family: 'Courier New', monospace;
                padding: 20px;
                max-width: 350px;
                margin: 0 auto;
                background-color: white;
                color: black;
              }
              .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px dotted #000;
                padding-bottom: 10px;
              }
              .header h1 {
                margin: 0;
                font-size: 24px;
              }
              .header p {
                margin: 5px 0;
                font-size: 14px;
              }
              .bet-item {
                margin-bottom: 15px;
                padding: 10px;
                border-bottom: 1px dotted #ccc;
              }
              .bet-type {
                font-weight: bold;
                margin-bottom: 5px;
              }
              .bet-details {
                display: flex;
                justify-content: space-between;
              }
              .summary {
                margin-top: 20px;
                padding-top: 10px;
                border-top: 2px dotted #000;
                font-weight: bold;
              }
              .summary-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 5px;
              }
              .barcode-container {
                text-align: center;
                margin: 20px 0;
              }
              .barcode-number {
                font-size: 12px;
                margin-top: 5px;
              }
              .footer {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                border-top: 2px dotted #000;
                padding-top: 10px;
              }
              .css-barcode {
                display: flex;
                justify-content: center;
                height: 40px;
                width: 95%;
                margin: 10px auto;
              }
              .bar {
                height: 100%;
                width: 2px;
                display: inline-block;
                background: black;
                margin-right: 1px;
              }
              .bar.thin {
                width: 1px;
              }
              .bar.medium {
                width: 2px;
              }
              .bar.thick {
                width: 3px;
              }
            </style>
          </head>
          <body>
            ${slipContent}
          </body>
          </html>
        `);
        doc.close();

        // Print the iframe content
        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        // Remove the iframe after printing
        setTimeout(() => {
          document.body.removeChild(iframe);
          // Close the modal after printing
          document.querySelector('.print-slip-modal').classList.remove('visible');

          // Clear the board for new bets WITHOUT refunding money
          this.clearBoardForNewBets();

          // Show enhanced success notification with draw number details
          this.showPrintSuccessNotification(drawNumber, drawSource, drawDetails);
        }, 500);
      });
    }

    // Generate the receipt HTML
    let receiptHTML = `
      <div class="header">
        <h1>ROULETTE BETTING SLIP</h1>
        <p>${dateTimeStr}</p>
        <p>Player ID: GUEST</p>
        <p>Draw #: ${drawNumber}</p>
      </div>

      <div class="bets-list">
    `;

    // Add each bet to the slip
    this.bets.forEach((bet, index) => {
      receiptHTML += `
        <div class="bet-item">
          <div class="bet-type">${index + 1}. ${bet.type.toUpperCase()}: ${bet.description}</div>
          <div class="bet-details">
            <div>Stake: $${bet.amount.toFixed(2)}</div>
            <div>Pays: ${this.getMultiplier(bet.type)}:1</div>
          </div>
          <div class="bet-details">
            <div></div>
            <div>Return: $${bet.potentialReturn.toFixed(2)}</div>
          </div>
        </div>
      `;
    });

    // Add summary and barcode
    receiptHTML += `
      </div>

      <div class="summary">
        <div class="summary-row">
          <div>Total Stakes:</div>
          <div>$${totalStakes.toFixed(2)}</div>
        </div>
        <div class="summary-row">
          <div>Draw Number:</div>
          <div>#${drawNumber}</div>
        </div>
      </div>

      <div class="barcode-container">
        <!-- CSS-based barcode as fallback -->
        <div class="css-barcode">
          ${this.generateCSSBarcode(barcodeNumber)}
        </div>
        <div class="barcode-number">${barcodeNumber}</div>
      </div>

      <div class="footer">
        <p>Good luck!</p>
        <p>This betting slip is for entertainment purposes only.</p>
        <p>Not redeemable for real money.</p>
      </div>
    `;

    // Update the modal content
    document.querySelector('.print-slip-content').innerHTML = receiptHTML;

    // Show the modal
    document.querySelector('.print-slip-modal').classList.add('visible');
  },

  // Helper function to generate a CSS-based barcode
  generateCSSBarcode: function(number) {
    let barcodeHTML = '';
    for (let i = 0; i < number.length; i++) {
      const digit = parseInt(number[i]);
      // Create different bar widths based on the digit
      for (let j = 0; j < 3; j++) {
        const thickness = (digit + j) % 3 === 0 ? 'thick' : ((digit + j) % 2 === 0 ? 'medium' : 'thin');
        barcodeHTML += `<div class="bar ${thickness}"></div>`;
      }
    }
    return barcodeHTML;
  },

  // Helper function to highlight numbers in the description
  highlightNumbers: function(description) {
    // Pattern to match numbers and number ranges in the description
    const regex = /\b(\d+(?:-\d+)?)\b|\(([^)]+)\)/g;

    // Replace numbers with highlighted version
    description = description.replace(regex, (match, p1, p2) => {
      // If it's a parenthesized group of numbers
      if (p2) {
        // Highlight each number in the parenthesized group
        return '(' + p2.split(',').map(num => {
          // Check if it's a range like 1-12
          if (num.includes('-')) {
            let [start, end] = num.split('-');
            return `<span class="number-highlight">${start}</span>-<span class="number-highlight">${end}</span>`;
          }
          // Otherwise it's a single number
          return `<span class="number-highlight">${num.trim()}</span>`;
        }).join(',') + ')';
      }
      // If it's a standalone number or range
      else if (p1) {
        if (p1.includes('-')) {
          let [start, end] = p1.split('-');
          return `<span class="number-highlight">${start}</span>-<span class="number-highlight">${end}</span>`;
        }
        return `<span class="number-highlight">${p1}</span>`;
      }
      // Return the original match if nothing matched
      return match;
    });

    return description;
  },

  // Add a new method to betTracker to clear the board visually without refunding money
  clearBoardForNewBets: function() {
    // Clear chips from the board visually
    this.bets.forEach(bet => {
      const elements = document.querySelectorAll(bet.elementSelector);
      elements.forEach(element => {
        element.innerHTML = '';
      });
    });

    // Additionally, clear ALL parts of the board to ensure nothing is left behind
    // This includes the bottom area (1st12, 2nd12, 3rd12, 1to18, EVEN, ODD, 19to36)
    document.querySelectorAll('.part').forEach(element => {
      element.innerHTML = '';
    });

    // Also clear any chips on number elements
    document.querySelectorAll('.number').forEach(element => {
      if (element.querySelector('.betting-chip')) {
        element.innerHTML = '';
      }
    });

    // Keep track of the total sold bets amount (don't refund money)
    // This ensures the financial transaction is kept valid

    // Reset bet total display
    betSum = 0;
    $(".bet-total").html(`${betSum.toFixed(2)}`);

    // Clear bets from tracker for new bets
    this.bets = [];
    this.updateDisplay();

    console.log('Board completely cleared for new bets');
  },

  // Legacy method - now redirects to enhanced getCurrentDrawNumber
  // This ensures compatibility with any old code that might call getNextDrawNumber
  getNextDrawNumber: function() {
    console.log('🎯 Legacy getNextDrawNumber called - redirecting to enhanced getCurrentDrawNumber');
    return this.getCurrentDrawNumber();
  },

  // Get next draw number from database
  getNextDrawFromDatabase: function() {
    try {
      // Synchronous request to get current draw number
      const xhr = new XMLHttpRequest();
      xhr.open('GET', 'php/get_next_draw_number.php', false); // Synchronous
      xhr.send();

      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        if (response.status === 'success' && response.next_draw_number) {
          console.log('Using next draw number from database:', response.next_draw_number);
          return parseInt(response.next_draw_number, 10);
        }
      }
    } catch (error) {
      console.error('Error getting next draw from database:', error);
    }

    // Fallback: try to get current draw and add 1
    try {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', 'load_analytics.php', false); // Synchronous
      xhr.send();

      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        if (response.status === 'success' && response.current_draw_number) {
          const nextDraw = parseInt(response.current_draw_number, 10) + 1;
          console.log('Calculated next draw from current draw:', nextDraw);
          return nextDraw;
        }
      }
    } catch (error) {
      console.error('Error getting current draw from analytics:', error);
    }

    // Final fallback
    console.warn('Could not determine next draw number, using fallback value 1');
    return 1;
  },

  // Handle draw number change events for real-time synchronization
  handleDrawNumberChange: function(eventDetails) {
    console.log('🎰 Print System: Handling draw number change event:', eventDetails);

    const newDrawNumber = eventDetails.newDrawNumber;
    const previousDrawNumber = eventDetails.previousDrawNumber;
    const source = eventDetails.source;

    // Show notification about draw number change affecting print system
    if (this.bets && this.bets.length > 0) {
      // There are pending bets, show notification about draw change
      this.showDrawChangeNotificationForPrint(newDrawNumber, previousDrawNumber, source);
    }

    // Update any cached draw number information
    if (window.lastValidatedDrawNumber) {
      window.lastValidatedDrawNumber = newDrawNumber;
    }

    console.log('🎰 Print System: Updated for new draw number:', newDrawNumber);
  },

  // Handle invalid draw selection cleared events
  handleInvalidDrawCleared: function(eventDetails) {
    console.log('🎰 Print System: Handling invalid draw cleared event:', eventDetails);

    const invalidDraw = eventDetails.invalidDraw;
    const currentDraw = eventDetails.currentDraw;

    // Show notification if there are pending bets
    if (this.bets && this.bets.length > 0) {
      this.showInvalidDrawClearedNotificationForPrint(invalidDraw, currentDraw);
    }

    console.log('🎰 Print System: Processed invalid draw cleanup');
  },

  // Show notification about draw number change affecting pending bets
  showDrawChangeNotificationForPrint: function(newDrawNumber, previousDrawNumber, source) {
    console.log('🎰 Print System: Showing draw change notification for pending bets');

    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 80px;
      right: 20px;
      background: linear-gradient(135deg, #2196F3, #1976D2);
      color: white;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      z-index: 10001;
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: bold;
      border-left: 4px solid #0D47A1;
      max-width: 350px;
      animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="font-size: 20px;">🔄</div>
        <div>
          <div style="font-size: 16px; margin-bottom: 4px;">Draw Number Updated</div>
          <div style="font-size: 13px; opacity: 0.9;">Pending bets will use Draw #${newDrawNumber}</div>
          <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">Previous: #${previousDrawNumber}</div>
        </div>
      </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 4 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 4000);
  },

  // Show notification about invalid draw cleanup affecting pending bets
  showInvalidDrawClearedNotificationForPrint: function(invalidDraw, currentDraw) {
    console.log('🎰 Print System: Showing invalid draw cleared notification');

    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
      position: fixed;
      top: 80px;
      right: 20px;
      background: linear-gradient(135deg, #FF9800, #F57C00);
      color: white;
      padding: 16px 20px;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      z-index: 10001;
      font-family: Arial, sans-serif;
      font-size: 14px;
      font-weight: bold;
      border-left: 4px solid #E65100;
      max-width: 350px;
      animation: slideInRight 0.3s ease-out;
    `;

    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="font-size: 20px;">🧹</div>
        <div>
          <div style="font-size: 16px; margin-bottom: 4px;">Draw Selection Updated</div>
          <div style="font-size: 13px; opacity: 0.9;">Cleared past draw #${invalidDraw}</div>
          <div style="font-size: 12px; opacity: 0.8; margin-top: 2px;">Pending bets will use upcoming draw</div>
        </div>
      </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 4 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => {
        if (notification.parentNode) {
          notification.parentNode.removeChild(notification);
        }
      }, 300);
    }, 4000);
  },
};

// Toggle bet display
document.addEventListener('DOMContentLoaded', function() {
  // Bet display toggle functionality
  const betDisplayToggle = document.querySelector('.bet-display-toggle');
  const betDisplayContainer = document.querySelector('.bet-display-container');

  if (betDisplayToggle && betDisplayContainer) {
      // Check localStorage for saved state
      const isCollapsed = localStorage.getItem('betDisplayCollapsed') === 'true';

      // Set initial state based on localStorage
      if (isCollapsed) {
          betDisplayContainer.classList.add('bet-display-collapsed');
      } else {
          betDisplayContainer.classList.remove('bet-display-collapsed');
      }

      // Toggle functionality
      betDisplayToggle.addEventListener('click', function(e) {
          betDisplayContainer.classList.toggle('bet-display-collapsed');

          // Save state to localStorage
          const newCollapsedState = betDisplayContainer.classList.contains('bet-display-collapsed');
          localStorage.setItem('betDisplayCollapsed', newCollapsedState);

          // Prevent clicks from initiating drag
          e.preventDefault();
          e.stopPropagation();
      });

      // Make the bet display draggable (optional)
      betDisplayContainer.addEventListener('mousedown', function(e) {
          // Don't initiate drag if clicking on the toggle button
          if (e.target === betDisplayToggle || betDisplayToggle.contains(e.target)) {
              return;
          }

          // Handle dragging functionality here if needed
          // This is a placeholder for potential dragging implementation
      });
  }

  // Initialize bet display
  betTracker.updateDisplay();

  // Add CSS to make the bet display taller
  const styleElement = document.createElement('style');
  styleElement.textContent = `
    .bet-display-container {
      height: auto;
      max-height: 500px;
    }
    .bet-display-body {
      max-height: 380px;
    }
    .bet-display-list {
      max-height: 300px;
    }
    .bet-display-summary {
      padding: 15px;
      background: rgba(0, 0, 0, 0.2);
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    .total-stakes {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 14px;
      margin-bottom: 8px;
    }
    .total-stakes span {
      color: rgba(255, 255, 255, 0.7);
    }
    .stakes-value {
      font-size: 18px;
      font-weight: bold;
      color: #f8d348;
      text-shadow: 0 0 5px rgba(248, 211, 72, 0.5);
    }
    .print-bets-button {
      display: block;
      width: 100%;
      margin-top: 12px;
      padding: 8px;
      background: linear-gradient(to right, #4deeea, #4dcd91);
      color: #000;
      border: none;
      border-radius: 5px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .print-bets-button:hover {
      background: linear-gradient(to right, #4dcd91, #4deeea);
      transform: translateY(-2px);
    }
    .badge-sixline {
      background-color: #ff9800;
    }
  `;
  document.head.appendChild(styleElement);
});

// Complete bet button click handler
$(".button-complete").click(function() {
  if (isCompleteBetMode) {
    // Turn off complete bet mode
    isCompleteBetMode = false;
    $(this).removeClass("active-button");
  } else {
    // Turn on complete bet mode
    isCompleteBetMode = true;
    $(this).addClass("active-button");

    // Show a message to indicate complete bet mode is active
    const tooltip = document.querySelector(".bet-type-tooltip");
    if (tooltip) {
      tooltip.textContent = "Click on a number to place a complete bet";
      tooltip.classList.add('visible');
      setTimeout(() => {
        if (isCompleteBetMode) {
          tooltip.classList.remove('visible');
        }
      }, 2000);
    }
  }

  if (playAudio) {
    selectSound.play();
  }
});

// Add click handler for number elements to support complete bets
$(".number").click(function() {
  if (isCompleteBetMode) {
    // Only process for the numbered elements (0-36)
    for (let i = 0; i <= 36; i++) {
      if ($(this).hasClass(`number${i}`)) {
        placeCompleteBet(i);
      return;
    }
    }
  }
});

// Initialize update stake modal functionality
function initializeUpdateStakeModal() {
  const updateStakeModal = document.querySelector('.update-stake-modal');
  const updateStakeInput = document.getElementById('update-stake-input');
  const updateStakeError = document.querySelector('.update-stake-error');
  const updateStakeButton = document.querySelector('.update-stake-button');
  const updateStakeClose = document.querySelector('.update-stake-close');

  // Close modal when close button is clicked
  updateStakeClose.addEventListener('click', function() {
    updateStakeModal.classList.remove('visible');
  });

  // Close modal when clicking outside the container
  updateStakeModal.addEventListener('click', function(e) {
    if (e.target === updateStakeModal) {
      updateStakeModal.classList.remove('visible');
    }
  });

  // Function to validate update stake amount
  function validateUpdateStakeAmount(amount) {
    if (amount < 100 || amount > 50000 || isNaN(amount)) {
      updateStakeError.style.display = 'block';
      updateStakeButton.style.opacity = '0.5';
      updateStakeButton.style.pointerEvents = 'none';
      return false;
    } else {
      updateStakeError.style.display = 'none';
      updateStakeButton.style.opacity = '1';
      updateStakeButton.style.pointerEvents = 'auto';
      return true;
    }
  }

  // Make validateUpdateStakeAmount globally available
  window.validateUpdateStakeAmount = validateUpdateStakeAmount;

  // Handle input validation
  updateStakeInput.addEventListener('input', function() {
    // Remove non-numeric characters
    this.value = this.value.replace(/[^0-9]/g, '');

    // Validate amount range
    const amount = parseInt(this.value);
    validateUpdateStakeAmount(amount);
  });

  // Handle confirm button click
  updateStakeButton.addEventListener('click', function() {
    const amount = parseInt(updateStakeInput.value);
    const betId = updateStakeInput.dataset.betId;

    if (validateUpdateStakeAmount(amount)) {
      // Update the bet stake
      const success = betTracker.updateBetStake(betId, amount);

      if (!success) {
        // Show error (not enough cash)
        updateStakeError.textContent = "Not enough cash available";
        updateStakeError.style.display = 'block';
        return;
      }

      // Close the modal
      updateStakeModal.classList.remove('visible');

      if (playAudio) {
        selectSound.play();
      }
    }
  });

  // Handle Enter key press
  updateStakeInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      const amount = parseInt(this.value);
      if (validateUpdateStakeAmount(amount)) {
        updateStakeButton.click();
      }
    }
  });
}

// Initialize the stake update modal when the document is ready
$(document).ready(function() {
  initializeUpdateStakeModal();
  initializeCancelSlipButton();
  initializePayoutModal();
  initializeCancelTicketModal();

  // Setup the UI toggle handlers
  document.querySelector('.bet-display-toggle').addEventListener('click', function(e) {
    const betDisplay = document.querySelector('.bet-display-container');
    betDisplay.classList.toggle('bet-display-collapsed');

    // Save the collapsed state to localStorage
    const isCollapsed = betDisplay.classList.contains('bet-display-collapsed');
    localStorage.setItem('betDisplayCollapsed', isCollapsed);

    // Prevent this click from being treated as the start of a drag operation
    e.stopPropagation();
  });

  // Global stake styles removed
});

// Function to handle Cancel Slip button functionality
function initializeCancelSlipButton() {
  // We no longer need the event handler for the Cancel Slip button in the bet display summary
  // as we're using the fixed buttons at the bottom of the sidebar instead

  // Close button for the Cancel Slip modal
  document.querySelector('.cancel-slip-close').addEventListener('click', function() {
    document.querySelector('.cancel-slip-modal').classList.remove('visible');
  });

  // "No, Keep Bets" button
  document.querySelector('.cancel-cancel-button').addEventListener('click', function() {
    document.querySelector('.cancel-slip-modal').classList.remove('visible');
  });

  // "Yes, Cancel All" button
  document.querySelector('.confirm-cancel-button').addEventListener('click', function() {
    // Reset all bets using the bet tracker's clearAllBets function
    betTracker.cancelAllBets();

    // Hide the confirmation modal
    document.querySelector('.cancel-slip-modal').classList.remove('visible');

    // Play sound if enabled
    if (playAudio) {
      selectSound.play();
    }
  });
}

// Add cancelAllBets method to betTracker
betTracker.cancelAllBets = function() {
  // Clear chips from the board based on tracked bets
  this.bets.forEach(bet => {
    const elements = document.querySelectorAll(bet.elementSelector);
    elements.forEach(element => {
      element.innerHTML = '';
    });
  });

  // Additionally, clear ALL parts of the board to ensure nothing is left behind
  // This includes the bottom area (1st12, 2nd12, 3rd12, 1to18, EVEN, ODD, 19to36)
  document.querySelectorAll('.part').forEach(element => {
    element.innerHTML = '';
  });

  // Also clear any chips on number elements
  document.querySelectorAll('.number').forEach(element => {
    if (element.querySelector('.betting-chip')) {
      element.innerHTML = '';
    }
  });

  // Reset bet total
  betSum = 0;
  $(".bet-total").html(`${betSum.toFixed(2)}`);

  // Clear all bets from tracker
  this.bets = [];
  this.updateDisplay();

  console.log('All bets cancelled and board completely cleared');

  // Restore the original cash amount from the database
  const originalCashAmount = localStorage.getItem('originalCashAmount');
  if (originalCashAmount) {
    // Restore the original cash amount
    cashSum = parseFloat(originalCashAmount);
    bankSum = cashSum;
    $(".cash-total").html(`${cashSum.toFixed(2)}`);

    // Clear the stored original cash amount
    localStorage.removeItem('originalCashAmount');

    console.log('Cash restored to original amount:', cashSum);
  } else if (typeof CashManager !== 'undefined') {
    // If no original amount is stored, refresh from the database
    CashManager.refreshBalance()
      .then(newBalance => {
        console.log('Cash refreshed from database:', newBalance);
        cashSum = newBalance;
        bankSum = newBalance;
        $(".cash-total").html(`${CashManager.formatCash(cashSum)}`);
      })
      .catch(error => {
        console.error('Error refreshing cash balance:', error);
      });
  }
};

// Function to handle Payout verification modal
function initializePayoutModal() {
  // Add the payout button to the menu if it doesn't exist
  const menuContainer = document.querySelector('.menu-container');
  if (!menuContainer.querySelector('.button-payout')) {
    const payoutButton = document.createElement('div');
    payoutButton.className = 'button button-payout';
    payoutButton.innerHTML = `
      <div class="circle">
        <i class="fas fa-search-dollar icon"></i>
      </div>
      <div class="circle-overlay"></div>
      <div class="button-text">PAYOUT</div>
    `;
    menuContainer.appendChild(payoutButton);
  }

  // Add event handler for the Payout button
  document.querySelector('.button-payout').addEventListener('click', function() {
    document.querySelector('.payout-modal').classList.add('visible');
    // Reset the verification result visibility
    document.querySelector('.verification-result').classList.remove('visible');
    // Reset tab selection
    document.querySelector('.barcode-tab').classList.add('active-tab');
    document.querySelector('.manual-tab').classList.remove('active-tab');
    document.querySelector('.barcode-content').classList.add('active-content');
    document.querySelector('.manual-content').classList.remove('active-content');
  });

  // Close button for the Payout modal
  document.querySelector('.payout-close').addEventListener('click', function() {
    document.querySelector('.payout-modal').classList.remove('visible');
  });

  // Tab switching in the Payout modal
  document.querySelector('.barcode-tab').addEventListener('click', function() {
    document.querySelector('.barcode-tab').classList.add('active-tab');
    document.querySelector('.manual-tab').classList.remove('active-tab');
    document.querySelector('.barcode-content').classList.add('active-content');
    document.querySelector('.manual-content').classList.remove('active-content');
  });

  document.querySelector('.manual-tab').addEventListener('click', function() {
    document.querySelector('.manual-tab').classList.add('active-tab');
    document.querySelector('.barcode-tab').classList.remove('active-tab');
    document.querySelector('.manual-content').classList.add('active-content');
    document.querySelector('.barcode-content').classList.remove('active-content');
  });

  // Scan button in the barcode tab
  document.querySelector('.scan-button').addEventListener('click', function() {
    simulateBarcodeScanning();
  });

  // Verify button in the manual entry tab
  document.querySelector('.verify-button').addEventListener('click', function() {
    verifySlipManually();
  });

  // Collect winnings button
  document.querySelector('.collect-button').addEventListener('click', function() {
    collectWinnings();
  });

  // Input validation for slip number
  document.getElementById('slip-number-input').addEventListener('input', function() {
    const slipNumber = this.value;
    const errorElement = document.querySelector('.manual-entry-error');

    // Ensure only digits are entered
    if (!/^\d*$/.test(slipNumber)) {
      this.value = slipNumber.replace(/\D/g, '');
    }

    // Show error if length is not correct
    if (slipNumber.length > 0 && slipNumber.length !== 8) {
      errorElement.style.display = 'block';
      errorElement.textContent = 'Please enter a valid 8-digit slip number';
    } else {
      errorElement.style.display = 'none';
    }
  });
}

// Function to simulate barcode scanning
function simulateBarcodeScanning() {
  // Simulate a scanning animation
  const barcodeArea = document.querySelector('.barcode-area');
  barcodeArea.innerHTML = '<i class="fas fa-spinner fa-spin"></i><p>Scanning...</p>';

  // Simulate a delay for scanning process
  setTimeout(function() {
    // Generate a random barcode number
    const barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();

    // Display verification result
    showVerificationResult(barcodeNumber);
  }, 2000);
}

// Function to verify slip manually
function verifySlipManually() {
  const slipNumberInput = document.getElementById('slip-number-input');
  const slipNumber = slipNumberInput.value;
  const errorElement = document.querySelector('.manual-entry-error');

  // Validate input
  if (slipNumber.length !== 8 || !/^\d{8}$/.test(slipNumber)) {
    errorElement.style.display = 'block';
    errorElement.textContent = 'Please enter a valid 8-digit slip number';
    return;
  }

  // Clear error
  errorElement.style.display = 'none';

  // Show verification result
  showVerificationResult(slipNumber);
}

// Function to show verification result
function showVerificationResult(slipNumber) {
  // Get verification result element
  const resultElement = document.querySelector('.verification-result');

  // Simulate random win or loss result
  const isWin = Math.random() > 0.5;
  const winningNumber = Math.floor(Math.random() * 37); // 0-36
  const payoutAmount = isWin ? Math.floor(Math.random() * 1000) + 50 : 0;

  // Display results
  document.querySelector('.result-status').textContent = isWin ? 'WIN' : '';
  document.querySelector('.result-status').className = isWin ? 'result-status win' : 'result-status loss';

  document.querySelector('.result-slip-number').textContent = slipNumber;
  document.querySelector('.result-date').textContent = new Date().toLocaleDateString() + ' ' + new Date().toLocaleTimeString();
  document.querySelector('.result-number').textContent = winningNumber;
  document.querySelector('.result-win-status').textContent = isWin ? 'Win' : 'Loss';

  // Show payout amount only if win
  const payoutElement = document.querySelector('.result-payout-amount');
  if (isWin) {
    payoutElement.textContent = `Payout Amount: $${payoutAmount.toFixed(2)}`;
    payoutElement.style.display = 'block';
    document.querySelector('.collect-button').style.display = 'block';
  } else {
    payoutElement.style.display = 'none';
    document.querySelector('.collect-button').style.display = 'none';
  }

  // Show the verification result
  resultElement.classList.add('visible');

  // Reset barcode area if in scan mode
  document.querySelector('.barcode-area').innerHTML = '<i class="fas fa-barcode"></i><p>Position barcode in this area</p>';
}

// Function to collect winnings
function collectWinnings() {
  // Get the payout amount
  const payoutText = document.querySelector('.result-payout-amount').textContent;
  const payout = parseFloat(payoutText.replace(/[^\d.]/g, ''));

  // Add payout to player's cash
  cashSum += payout;
  $(".cash-total").html(`${cashSum.toFixed(2)}`);

  // Hide the verification result and payout modal
  document.querySelector('.payout-modal').classList.remove('visible');

  // Show a success message
  alert(`Congratulations! $${payout.toFixed(2)} has been added to your balance.`);
}

// Global object to track printed tickets
const ticketManager = {
  tickets: [],
  drawCalled: false,  // Track if a draw has been called

  // Add a new ticket
  addTicket: function(barcodeNumber, bets, totalStakes, potentialReturn) {
    const ticket = {
      barcodeNumber: barcodeNumber,
      date: new Date(),
      bets: [...bets],  // Copy the bets array
      totalStakes: totalStakes,
      potentialReturn: potentialReturn,
      drawCalled: this.drawCalled,
      winningNumber: null
    };

    this.tickets.push(ticket);
    return ticket;
  },

  // Find a ticket by barcode number
  findTicket: function(barcodeNumber) {
    return this.tickets.find(ticket => ticket.barcodeNumber === barcodeNumber);
  },

  // Set draw called status
  setDrawCalled: function(winningNumber) {
    this.drawCalled = true;
    const currentDate = new Date();

    // Update all existing tickets that don't have a draw called
    this.tickets.forEach(ticket => {
      if (!ticket.drawCalled) {
        ticket.drawCalled = true;
        ticket.winningNumber = winningNumber;
      }
    });
  },

  // Reset draw called status (happens when a new betting round starts)
  resetDrawCalled: function() {
    this.drawCalled = false;
  },

  // Cancel a ticket and refund the player
  cancelTicket: function(barcodeNumber) {
    const ticketIndex = this.tickets.findIndex(ticket => ticket.barcodeNumber === barcodeNumber);

    if (ticketIndex === -1) {
      return { success: false, message: "Ticket not found" };
    }

    const ticket = this.tickets[ticketIndex];

    // Check if the ticket is eligible for cancellation
    if (ticket.drawCalled) {
      return { success: false, message: "Ticket cannot be cancelled because the draw has already occurred" };
    }

    // Remove the ticket from the array
    this.tickets.splice(ticketIndex, 1);

    // Return the refund amount
    return {
      success: true,
      message: "Ticket cancelled successfully",
      refundAmount: ticket.totalStakes
    };
  }
};

// Modify the spin button click handler to set draw called status
$('.button-spin').on('click', function() {
  // The existing code does the spinning

  // After spinning, we'll need to set the draw called status
  // This happens in the rouletteWheelAnimation function
});

// Modify the printBettingSlip function in betTracker to only show preview (no saving)
betTracker.printBettingSlip = function() {
  // Generate a simple random barcode number (for display purposes)
  const barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();

  // Format current date and time for the receipt
  const now = new Date();
  const dateTimeStr = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();

  // Calculate total stakes and potential return
  let totalStakes = 0;
  let totalPotentialReturn = 0;
  this.bets.forEach(bet => {
    totalStakes += bet.amount;
    totalPotentialReturn += bet.potentialReturn;
  });

  // Store the betting slip data for later saving when "Print Slip" is clicked
  this.pendingSlipData = {
    barcodeNumber: barcodeNumber,
    bets: this.bets,
    totalStakes: totalStakes,
    totalPotentialReturn: totalPotentialReturn,
    dateTimeStr: dateTimeStr
  };

  // Check if print modal already exists, if not, create it
  if (!document.querySelector('.print-slip-modal')) {
    // Create the print modal structure
    const printModal = document.createElement('div');
    printModal.className = 'print-slip-modal';
    printModal.innerHTML = `
      <div class="print-slip-container">
        <div class="print-slip-header">
          <h2>Betting Slip Preview</h2>
          <div class="print-slip-close"><i class="fas fa-times"></i></div>
        </div>
        <div class="print-slip-body">
          <div class="print-slip-content"></div>
          <div class="print-slip-actions">
            <button class="print-action-button"><i class="fas fa-print"></i> Print Slip</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(printModal);

    // Add event listeners for the new modal
    document.querySelector('.print-slip-close').addEventListener('click', function() {
      document.querySelector('.print-slip-modal').classList.remove('visible');
    });

    document.querySelector('.print-action-button').addEventListener('click', () => {
      // First, save the betting slip to the database
      const slipData = betTracker.pendingSlipData;
      if (!slipData) {
        console.error('No pending slip data found');
        alert('Error: No betting slip data found. Please try again.');
        return;
      }

      // Register the ticket with the ticket manager
      ticketManager.addTicket(slipData.barcodeNumber, slipData.bets, slipData.totalStakes, slipData.totalPotentialReturn);

      // Create a promise to track the saving process
      const savingPromise = new Promise((resolve, reject) => {
        try {
          // Always use guest player ID 1 to ensure compatibility with database constraints
          const playerId = 1;

          // Get the current draw number
          const drawNumber = betTracker.getCurrentDrawNumber ? betTracker.getCurrentDrawNumber() : 1;

          // Prepare data for the API call
          const data = {
              slip_number: slipData.barcodeNumber,
              player_id: playerId,
              bets: slipData.bets,
              total_stake: slipData.totalStakes,
              potential_return: slipData.totalPotentialReturn,
              draw_number: drawNumber
          };

          console.log('Saving betting slip data:', data);

          // Send the data to the server
          fetch('php/save_betting_slip.php', {
              method: 'POST',
              headers: {
                  'Content-Type': 'application/json'
              },
              body: JSON.stringify(data)
          })
          .then(response => {
              if (!response.ok) {
                  throw new Error('Network response was not ok: ' + response.status);
              }
              return response.json();
          })
          .then(result => {
              if (result.status === 'success') {
                  console.log('Betting slip saved successfully:', result);
                  resolve(result);
              } else {
                  console.error('Error saving betting slip:', result.message);
                  reject(new Error(result.message || 'Failed to save betting slip'));
              }
          })
          .catch(error => {
              console.error('Error in API call:', error);
              reject(error);
          });
        } catch (error) {
          console.error('Exception while saving betting slip:', error);
          reject(error);
        }
      });

      // After saving the betting slip, proceed with printing and cash updates
      savingPromise.then(result => {
        // Update cash balance and commission
        if (typeof CashManager !== 'undefined' && slipData.totalStakes > 0) {
          console.log('Betting slip sold with total stakes:', slipData.totalStakes);

          // Check if the server returned a new balance
          if (result.new_balance !== undefined) {
            console.log('Server returned new balance:', result.new_balance);

            // Update the UI and CashManager with the new balance
            const newBalance = parseFloat(result.new_balance);
            cashSum = newBalance;
            bankSum = newBalance;
            $(".cash-total").html(`${CashManager.formatCash(newBalance)}`);

            // Also update the CashManager's internal balance
            CashManager.setBalance(newBalance);

            // Update commission (4% of bet amount) with slip number for reference
            updateCommission(slipData.totalStakes, slipData.barcodeNumber);
          } else {
            console.log('Server did not return new balance, using direct update');

            // Make a direct call to update the cash from bets
            fetch('update_cash_from_bets.php?user_id=1')
              .then(response => response.json())
              .then(data => {
                console.log('Direct cash update result:', data);

                if (data.updated) {
                  // Update the UI with the new balance
                  const newBalance = parseFloat(data.new_balance);
                  cashSum = newBalance;
                  bankSum = newBalance;
                  $(".cash-total").html(`${CashManager.formatCash(newBalance)}`);

                  // Also update the CashManager's internal balance
                  CashManager.setBalance(newBalance);
                } else {
                  // Refresh the balance from the server anyway
                  return CashManager.refreshBalance();
                }
              })
              .then(newBalance => {
                if (newBalance) {
                  console.log('Cash balance refreshed. New balance:', newBalance);
                  // Update the UI with the new balance
                  cashSum = newBalance;
                  bankSum = newBalance;
                  $(".cash-total").html(`${CashManager.formatCash(newBalance)}`);
                }

                // Update commission (4% of bet amount) with slip number for reference
                updateCommission(slipData.totalStakes, slipData.barcodeNumber);
              })
              .catch(error => {
                console.error('Error in direct cash update:', error);
                // Try the original method as last resort
                CashManager.removeCash(slipData.totalStakes, 'bet_sold', slipData.barcodeNumber, 'Betting slip sold #' + slipData.barcodeNumber)
                  .then(newBalance => {
                    console.log('Last resort cash update successful. New balance:', newBalance);
                    cashSum = newBalance;
                    bankSum = newBalance;
                    $(".cash-total").html(`${CashManager.formatCash(newBalance)}`);
                  });
              });
          }
        }

        // Clear the original cash amount since we've now committed the transaction
        localStorage.removeItem('originalCashAmount');

        // Now proceed with printing
        // Create a hidden iframe for printing just the receipt
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        document.body.appendChild(iframe);

        const slipContent = document.querySelector('.print-slip-content').innerHTML;

        // Write the content to the iframe
        const doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.write(`
        <html>
        <head>
          <title>Roulette Betting Slip</title>
          <style>
            body {
              font-family: 'Courier New', monospace;
              padding: 20px;
              max-width: 350px;
              margin: 0 auto;
              background-color: white;
              color: black;
            }
            .header {
              text-align: center;
              margin-bottom: 20px;
              border-bottom: 2px dotted #000;
              padding-bottom: 10px;
            }
            .header h1 {
              margin: 0;
              font-size: 24px;
            }
            .header p {
              margin: 5px 0;
              font-size: 14px;
            }
            .bet-item {
              margin-bottom: 15px;
              padding: 10px;
              border-bottom: 1px dotted #ccc;
            }
            .bet-type {
              font-weight: bold;
              margin-bottom: 5px;
            }
            .bet-details {
              display: flex;
              justify-content: space-between;
            }
            .summary {
              margin-top: 20px;
              padding-top: 10px;
              border-top: 2px dotted #000;
              font-weight: bold;
            }
            .summary-row {
              display: flex;
              justify-content: space-between;
              margin-bottom: 5px;
            }
            .barcode-container {
              text-align: center;
              margin: 20px 0;
            }
            .barcode-number {
              font-size: 12px;
              margin-top: 5px;
            }
            .footer {
              text-align: center;
              margin-top: 20px;
              font-size: 12px;
              border-top: 2px dotted #000;
              padding-top: 10px;
            }
            .css-barcode {
              display: flex;
              justify-content: center;
              height: 40px;
              width: 95%;
              margin: 10px auto;
            }
            .bar {
              height: 100%;
              width: 2px;
              display: inline-block;
              background: black;
              margin-right: 1px;
            }
            .bar.thin {
              width: 1px;
            }
            .bar.medium {
              width: 2px;
            }
            .bar.thick {
              width: 3px;
            }
          </style>
        </head>
        <body>
          ${slipContent}
        </body>
        </html>
      `);
      doc.close();

      // Print the iframe content
      iframe.contentWindow.focus();
      iframe.contentWindow.print();

      // Remove the iframe after printing
      setTimeout(() => {
        document.body.removeChild(iframe);
        // Close the modal after printing
        document.querySelector('.print-slip-modal').classList.remove('visible');

        // Clear the board for new bets WITHOUT refunding money
        this.clearBoardForNewBets();

        // Show confirmation message
        const message = document.createElement('div');
        message.style.position = 'fixed';
        message.style.top = '20px';
        message.style.left = '50%';
        message.style.transform = 'translateX(-50%)';
        message.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
        message.style.color = '#fff';
        message.style.padding = '10px 20px';
        message.style.borderRadius = '5px';
        message.style.zIndex = '10000';
        message.style.fontFamily = 'Arial, sans-serif';
        message.style.fontSize = '14px';
        message.textContent = 'Betting slip printed and saved. Board cleared for new bets!';
        document.body.appendChild(message);

        // Remove the message after 3 seconds
        setTimeout(() => {
          document.body.removeChild(message);
        }, 3000);
      }, 500);
      }).catch(error => {
        console.error('Failed to complete betting slip sale:', error);
        // Show a more detailed error message
        const errorMsg = error.message || 'There was an error saving the betting slip. Please try again.';
        alert(errorMsg);

        // Add error message to the UI
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.style.color = 'white';
        errorElement.style.backgroundColor = 'red';
        errorElement.style.padding = '10px';
        errorElement.style.margin = '10px 0';
        errorElement.style.borderRadius = '5px';
        errorElement.style.textAlign = 'center';
        errorElement.textContent = 'Error processing server response: ' + errorMsg;

        // Insert at the top of the bets container
        const betsContainer = document.querySelector('.your-bets-container');
        if (betsContainer) {
          betsContainer.insertBefore(errorElement, betsContainer.firstChild);

          // Remove after 5 seconds
          setTimeout(() => {
            if (errorElement.parentNode) {
              errorElement.parentNode.removeChild(errorElement);
            }
          }, 5000);
        }
      });
    });
  }

  // Generate the receipt HTML using pending slip data
  let receiptHTML = `
    <div class="header">
      <h1>ROULETTE BETTING SLIP</h1>
      <p>${this.pendingSlipData.dateTimeStr}</p>
      <p>Player ID: GUEST</p>
      <p>Draw #: ${this.getCurrentDrawNumber()}</p>
    </div>

    <div class="bets-list">
  `;

  // Add each bet to the slip
  this.pendingSlipData.bets.forEach((bet, index) => {
    receiptHTML += `
      <div class="bet-item">
        <div class="bet-type">${index + 1}. ${bet.type.toUpperCase()}: ${bet.description}</div>
        <div class="bet-details">
          <div>Stake: $${bet.amount.toFixed(2)}</div>
          <div>Pays: ${this.getMultiplier(bet.type)}:1</div>
        </div>
        <div class="bet-details">
          <div></div>
          <div>Return: $${bet.potentialReturn.toFixed(2)}</div>
        </div>
      </div>
    `;
  });

  // Add summary and barcode
  receiptHTML += `
    </div>

    <div class="summary">
      <div class="summary-row">
        <div>Total Stakes:</div>
        <div>$${this.pendingSlipData.totalStakes.toFixed(2)}</div>
      </div>
      <div class="summary-row">
        <div>Draw Number:</div>
        <div>#${this.getCurrentDrawNumber()}</div>
      </div>
    </div>

    <div class="barcode-container">
      <!-- CSS-based barcode as fallback -->
      <div class="css-barcode">
        ${this.generateCSSBarcode(this.pendingSlipData.barcodeNumber)}
      </div>
      <div class="barcode-number">${this.pendingSlipData.barcodeNumber}</div>
    </div>

    <div class="footer">
      <p>Good luck!</p>
      <p>This betting slip is for entertainment purposes only.</p>
      <p>Not redeemable for real money.</p>
    </div>
  `;

  // Update the modal content
  document.querySelector('.print-slip-content').innerHTML = receiptHTML;

  // Show the modal
  document.querySelector('.print-slip-modal').classList.add('visible');
};

// Modify the rouletteWheelAnimation function to set draw called status
let rouletteWheelAnimation = () => {
  // Existing code

  // After the animation completes and we have the winning number, set draw called
  // This code would normally be at the end of the animation
  setTimeout(function() {
    // This is where the winning number is determined
    const winningNumber = parseInt($(".roll-number").text());

    // Mark the draw as called
    ticketManager.setDrawCalled(winningNumber);
  }, 3000); // Adjust this time to match when the winning number is determined
};
// Reset the draw called status when a new game starts
$('.button-reset').on('click', function() {
  ticketManager.resetDrawCalled();
});

// Function to initialize the Cancel Ticket modal
function initializeCancelTicketModal() {
  // Add event handler for the Cancel Ticket button
  document.querySelector('.button-cancel-ticket').addEventListener('click', function() {
    document.querySelector('.cancel-ticket-modal').classList.add('visible');

    // Reset the verification result visibility
    document.querySelector('.cancel-verification-result').classList.remove('visible');

    // Reset tab selection
    document.querySelector('.cancel-ticket-tab.barcode-tab').classList.add('active-tab');
    document.querySelector('.cancel-ticket-tab.manual-tab').classList.remove('active-tab');
    document.querySelector('.cancel-ticket-tab-content.barcode-content').classList.add('active-content');
    document.querySelector('.cancel-ticket-tab-content.manual-content').classList.remove('active-content');

    // Hide error message
    document.querySelector('.cancel-error-message').classList.remove('visible');
  });

  // Close button for the Cancel Ticket modal
  document.querySelector('.cancel-ticket-close').addEventListener('click', function() {
    document.querySelector('.cancel-ticket-modal').classList.remove('visible');
  });

  // Tab switching in the Cancel Ticket modal
  document.querySelector('.cancel-ticket-tab.barcode-tab').addEventListener('click', function() {
    document.querySelector('.cancel-ticket-tab.barcode-tab').classList.add('active-tab');
    document.querySelector('.cancel-ticket-tab.manual-tab').classList.remove('active-tab');
    document.querySelector('.cancel-ticket-tab-content.barcode-content').classList.add('active-content');
    document.querySelector('.cancel-ticket-tab-content.manual-content').classList.remove('active-content');
  });

  document.querySelector('.cancel-ticket-tab.manual-tab').addEventListener('click', function() {
    document.querySelector('.cancel-ticket-tab.manual-tab').classList.add('active-tab');
    document.querySelector('.cancel-ticket-tab.barcode-tab').classList.remove('active-tab');
    document.querySelector('.cancel-ticket-tab-content.manual-content').classList.add('active-content');
    document.querySelector('.cancel-ticket-tab-content.barcode-content').classList.remove('active-content');
  });

  // Scan button in the barcode tab
  document.querySelector('.scan-cancel-button').addEventListener('click', function() {
    simulateCancelBarcodeScanning();
  });

  // Verify button in the manual entry tab
  document.querySelector('.verify-cancel-button').addEventListener('click', function() {
    verifyCancelTicketManually();
  });

  // Confirm cancellation button
  document.querySelector('.confirm-cancel-ticket-button').addEventListener('click', function() {
    confirmTicketCancellation();
  });

  // Input validation for slip number
  document.getElementById('cancel-slip-number-input').addEventListener('input', function() {
    const slipNumber = this.value;
    const errorElement = document.querySelector('.cancel-ticket-modal .manual-entry-error');

    // Ensure only digits are entered
    if (!/^\d*$/.test(slipNumber)) {
      this.value = slipNumber.replace(/\D/g, '');
    }

    // Show error if length is not correct
    if (slipNumber.length > 0 && slipNumber.length !== 8) {
      errorElement.style.display = 'block';
      errorElement.textContent = 'Please enter a valid 8-digit slip number';
    } else {
      errorElement.style.display = 'none';
    }
  });
}

// Function to simulate barcode scanning for cancellation
function simulateCancelBarcodeScanning() {
  // Simulate a scanning animation
  const barcodeArea = document.querySelector('.cancel-ticket-modal .barcode-area');
  barcodeArea.innerHTML = '<i class="fas fa-spinner fa-spin"></i><p>Scanning...</p>';

  // Simulate a delay for scanning process
  setTimeout(function() {
    // In a real app, this would scan an actual barcode
    // Here we'll simulate by generating a random barcode or using one from our tickets

    // Get a random ticket from our tickets array if available, or generate a new one
    let barcodeNumber;
    if (ticketManager.tickets.length > 0) {
      const randomIndex = Math.floor(Math.random() * ticketManager.tickets.length);
      barcodeNumber = ticketManager.tickets[randomIndex].barcodeNumber;
    } else {
      barcodeNumber = Math.floor(10000000 + Math.random() * 90000000).toString();
    }

    // Display verification result
    showCancelVerificationResult(barcodeNumber);

    // Reset barcode area
    barcodeArea.innerHTML = '<i class="fas fa-barcode"></i><p>Position barcode in this area</p>';
  }, 2000);
}

// Function to verify ticket manually for cancellation
function verifyCancelTicketManually() {
  const slipNumberInput = document.getElementById('cancel-slip-number-input');
  const slipNumber = slipNumberInput.value;
  const errorElement = document.querySelector('.cancel-ticket-modal .manual-entry-error');

  // Validate input
  if (slipNumber.length !== 8 || !/^\d{8}$/.test(slipNumber)) {
    errorElement.style.display = 'block';
    errorElement.textContent = 'Please enter a valid 8-digit slip number';
    return;
  }

  // Clear error
  errorElement.style.display = 'none';

  // Show verification result
  showCancelVerificationResult(slipNumber);
}

// Function to show verification result for ticket cancellation
function showCancelVerificationResult(barcodeNumber) {
  // Find the ticket
  const ticket = ticketManager.findTicket(barcodeNumber);

  // Get verification result element
  const resultElement = document.querySelector('.cancel-verification-result');
  const statusElement = document.querySelector('.cancel-result-status');
  const cancelButton = document.querySelector('.confirm-cancel-ticket-button');
  const errorMessage = document.querySelector('.cancel-error-message');

  // Reset elements
  errorMessage.classList.remove('visible');
  cancelButton.classList.remove('disabled');

  // If the ticket exists, check if it's eligible for cancellation
  if (ticket) {
    // Update result elements with ticket information
    document.querySelector('.cancel-result-slip-number').textContent = barcodeNumber;
    document.querySelector('.cancel-result-date').textContent = ticket.date.toLocaleDateString() + ' ' + ticket.date.toLocaleTimeString();
    document.querySelector('.cancel-result-stakes').textContent = '$' + ticket.totalStakes.toFixed(2);

    if (!ticket.drawCalled) {
      // Ticket is eligible for cancellation
      statusElement.textContent = 'ELIGIBLE FOR CANCELLATION';
      statusElement.className = 'cancel-result-status eligible';
      document.querySelector('.cancel-result-status-text').textContent = 'Eligible for cancellation';
    } else {
      // Ticket is not eligible for cancellation
      statusElement.textContent = 'NOT ELIGIBLE';
      statusElement.className = 'cancel-result-status ineligible';
      document.querySelector('.cancel-result-status-text').textContent = 'Draw has occurred';
      cancelButton.classList.add('disabled');
      errorMessage.classList.add('visible');
    }
  } else {
    // Ticket not found
    document.querySelector('.cancel-result-slip-number').textContent = barcodeNumber;
    document.querySelector('.cancel-result-date').textContent = 'N/A';
    document.querySelector('.cancel-result-stakes').textContent = 'N/A';
    statusElement.textContent = 'TICKET NOT FOUND';
    statusElement.className = 'cancel-result-status ineligible';
    document.querySelector('.cancel-result-status-text').textContent = 'Unknown ticket';
    cancelButton.classList.add('disabled');
  }

  // Show the result
  resultElement.classList.add('visible');

  // Store the current barcode number for use in confirmation
  cancelButton.dataset.barcodeNumber = barcodeNumber;
}

// Function to confirm ticket cancellation
function confirmTicketCancellation() {
  const cancelButton = document.querySelector('.confirm-cancel-ticket-button');
  const barcodeNumber = cancelButton.dataset.barcodeNumber;

  // Process the cancellation
  const result = ticketManager.cancelTicket(barcodeNumber);

  if (result.success) {
    // Refund the player
    cashSum += result.refundAmount;
    $(".cash-total").html(`${cashSum.toFixed(2)}`);

    // Show success message
    alert(`Ticket cancelled successfully. $${result.refundAmount.toFixed(2)} has been refunded.`);

    // Close the modal
    document.querySelector('.cancel-ticket-modal').classList.remove('visible');
  } else {
    // Show error message
    alert(`Error: ${result.message}`);
  }
}

// Make bet display draggable
function initializeDraggableBetDisplay() {
  const betDisplay = document.querySelector('.bet-display-container');
  const betDisplayHeader = document.querySelector('.bet-display-header');

  if (!betDisplay || !betDisplayHeader) return;

  let isDragging = false;
  let offsetX, offsetY;
  let initialX, initialY;

  // Functions to handle mouse events
  function startDrag(e) {
    // Only allow dragging from the header area
    if (e.target.closest('.bet-display-toggle')) return;

    isDragging = true;
    betDisplay.classList.add('dragging');

    // Calculate the offset of the mouse relative to the panel
    const rect = betDisplay.getBoundingClientRect();

    if (e.type === 'mousedown') {
      offsetX = e.clientX - rect.left;
      offsetY = e.clientY - rect.top;
      initialX = e.clientX;
      initialY = e.clientY;
    } else if (e.type === 'touchstart') {
      offsetX = e.touches[0].clientX - rect.left;
      offsetY = e.touches[0].clientY - rect.top;
      initialX = e.touches[0].clientX;
      initialY = e.touches[0].clientY;
    }

    // Prevent text selection during drag
    e.preventDefault();
  }

  function doDrag(e) {
    if (!isDragging) return;

    let clientX, clientY;

    if (e.type === 'mousemove') {
      clientX = e.clientX;
      clientY = e.clientY;
    } else if (e.type === 'touchmove') {
      clientX = e.touches[0].clientX;
      clientY = e.touches[0].clientY;
    }

    // Calculate the new position
    let newX = clientX - offsetX;
    let newY = clientY - offsetY;

    // Get window dimensions
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;

    // Get panel dimensions
    const panelWidth = betDisplay.offsetWidth;
    const panelHeight = betDisplay.offsetHeight;

    // Constrain to window boundaries
    newX = Math.max(0, Math.min(windowWidth - panelWidth, newX));
    newY = Math.max(0, Math.min(windowHeight - panelHeight, newY));

    // Update position
    betDisplay.style.left = newX + 'px';
    betDisplay.style.top = newY + 'px';

    // Override any transition for smoother dragging
    betDisplay.style.transition = 'none';

    e.preventDefault();
  }

  function endDrag(e) {
    if (!isDragging) return;

    isDragging = false;
    betDisplay.classList.remove('dragging');

    // Restore the transition
    betDisplay.style.transition = 'all 0.3s ease';

    // If it was just a click (not a drag), don't trigger collapse
    if (e.type === 'mouseup') {
      const dragDistance = Math.sqrt(
        Math.pow(e.clientX - initialX, 2) +
        Math.pow(e.clientY - initialY, 2)
      );

      if (dragDistance < 5) {
        // It was just a click, not a drag
        return;
      }
    }

    // Save the position in localStorage for persistence
    const rect = betDisplay.getBoundingClientRect();
    localStorage.setItem('betDisplayPosition', JSON.stringify({
      left: rect.left,
      top: rect.top
    }));
  }

  // Add event listeners for mouse events
  betDisplayHeader.addEventListener('mousedown', startDrag);
  document.addEventListener('mousemove', doDrag);
  document.addEventListener('mouseup', endDrag);

  // Add event listeners for touch events (mobile)
  betDisplayHeader.addEventListener('touchstart', startDrag, { passive: false });
  document.addEventListener('touchmove', doDrag, { passive: false });
  document.addEventListener('touchend', endDrag);

  // Restore saved position (if any)
  const savedPosition = localStorage.getItem('betDisplayPosition');
  if (savedPosition) {
    try {
      const position = JSON.parse(savedPosition);
      betDisplay.style.left = position.left + 'px';
      betDisplay.style.top = position.top + 'px';
    } catch (error) {
      console.error('Error restoring bet display position:', error);
    }
  }

  // Restore collapsed state
  const isCollapsed = localStorage.getItem('betDisplayCollapsed') === 'true';
  if (isCollapsed) {
    betDisplay.classList.add('bet-display-collapsed');
  } else {
    betDisplay.classList.remove('bet-display-collapsed');
  }
}

// Initialize draggable bet display when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
  // ... existing DOMContentLoaded code ...

  // Initialize draggable bet display
  initializeDraggableBetDisplay();
});

// Initialize bet display panel
function initializeBetDisplay() {
  const betDisplay = document.querySelector('.bet-display-container');
  const betDisplayToggle = document.querySelector('.bet-display-toggle');

  if (!betDisplay || !betDisplayToggle) return;

  // Check local storage for saved state
  const isExpanded = localStorage.getItem('betDisplayExpanded') === 'true';
  if (isExpanded) {
    betDisplay.classList.add('expanded');
  }

  // Toggle the expanded state when the toggle button is clicked
  betDisplayToggle.addEventListener('click', function(e) {
    betDisplay.classList.toggle('expanded');

    // Save the expanded state to localStorage
    const isCurrentlyExpanded = betDisplay.classList.contains('expanded');
    localStorage.setItem('betDisplayExpanded', isCurrentlyExpanded);

    // Prevent this click from being treated as the start of a drag
    e.stopPropagation();
  });

  // Double click on header to toggle as well
  betDisplay.querySelector('.bet-display-header').addEventListener('dblclick', function(e) {
    // Don't toggle if we clicked on the toggle button itself
    if (!e.target.closest('.bet-display-toggle')) {
      betDisplay.classList.toggle('expanded');

      // Save the expanded state to localStorage
      const isCurrentlyExpanded = betDisplay.classList.contains('expanded');
      localStorage.setItem('betDisplayExpanded', isCurrentlyExpanded);
    }
  });
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
  // ... existing code ...

  // Initialize bet display with new implementation
  initializeBetDisplay();

  // No longer needed:
  // initializeDraggableBetDisplay();
});

// Enhance the bet display to be fully resizable and freely positionable
function initializeResizableDraggableBetDisplay() {
  const betDisplay = document.getElementById('bet-display-container');
  const betDisplayHeader = document.querySelector('.bet-display-header');

  if (!betDisplay || !betDisplayHeader) return;

  let isResizing = false;
  let resizeHandle = null;
  let isDragging = false;
  let startX, startY, startWidth, startHeight, startLeft, startTop;

  // Initialize dragging functionality
  betDisplayHeader.addEventListener('mousedown', startDrag);
  document.addEventListener('mousemove', doDrag);
  document.addEventListener('mouseup', endDrag);

  // Initialize resizing functionality
  document.querySelectorAll('.resize-handle').forEach(handle => {
    handle.addEventListener('mousedown', startResize);
  });

  // Function to start dragging
  function startDrag(e) {
    // Only drag with left mouse button
    if (e.button !== 0) return;

    // Prevent if clicking on toggle button
    if (e.target.closest('.bet-display-toggle')) return;

    isDragging = true;
    startX = e.clientX;
    startY = e.clientY;

    const rect = betDisplay.getBoundingClientRect();
    startLeft = rect.left;
    startTop = rect.top;

    // Add a class to indicate dragging state
    betDisplay.classList.add('dragging');

    // Prevent text selection during drag
    e.preventDefault();
  }

  // Function to handle drag movement
  function doDrag(e) {
    if (!isDragging) return;

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    // Calculate new position with boundary checking
    const newLeft = Math.max(0, Math.min(window.innerWidth - betDisplay.offsetWidth, startLeft + dx));
    const newTop = Math.max(0, Math.min(window.innerHeight - betDisplay.offsetHeight, startTop + dy));

    // Update position
    betDisplay.style.left = newLeft + 'px';
    betDisplay.style.top = newTop + 'px';

    // Since we're allowing free positioning, remove the collapsed state if dragging
    if (betDisplay.classList.contains('bet-display-collapsed')) {
      betDisplay.classList.remove('bet-display-collapsed');
    }
  }

  // Function to end dragging
  function endDrag(e) {
    if (!isDragging) return;

    isDragging = false;
    betDisplay.classList.remove('dragging');
  }

  // Function to start resizing
  function startResize(e) {
    // Only resize with left mouse button
    if (e.button !== 0) return;

    isResizing = true;
    resizeHandle = e.target.getAttribute('data-resize');

    startX = e.clientX;
    startY = e.clientY;

    const rect = betDisplay.getBoundingClientRect();
    startWidth = rect.width;
    startHeight = rect.height;
    startLeft = rect.left;

    // Add a class to indicate resizing state
    betDisplay.classList.add('resizing');

    // Prevent text selection during resize
    e.preventDefault();

    // Add event listeners for resize movement and end
    document.addEventListener('mousemove', doResize);
    document.addEventListener('mouseup', endResize);
  }

  // Function to handle resize movement
  function doResize(e) {
    if (!isResizing) return;

    // Since we're resizing, remove the collapsed state
    if (betDisplay.classList.contains('bet-display-collapsed')) {
      betDisplay.classList.remove('bet-display-collapsed');
    }

    // Remove minimum width and height constraints to allow unlimited resizing

    let newWidth = startWidth;
    let newHeight = startHeight;
    let newLeft = startLeft;

    // Calculate new dimensions based on which handle is being dragged
    switch (resizeHandle) {
      case 'e': // East (right)
        newWidth = startWidth + (e.clientX - startX);
        break;

      case 'w': // West (left)
        let dxW = e.clientX - startX;
        newWidth = startWidth - dxW;
        newLeft = startLeft + dxW;
        break;

      case 's': // South (bottom)
        newHeight = startHeight + (e.clientY - startY);
        break;

      case 'se': // Southeast (bottom-right corner)
        newWidth = startWidth + (e.clientX - startX);
        newHeight = startHeight + (e.clientY - startY);
        break;

      case 'sw': // Southwest (bottom-left corner)
        let dxSW = e.clientX - startX;
        newWidth = startWidth - dxSW;
        newLeft = startLeft + dxSW;
        newHeight = startHeight + (e.clientY - startY);
        break;
    }

    // Apply the new dimensions
    betDisplay.style.width = newWidth + 'px';
    betDisplay.style.height = newHeight + 'px';
    betDisplay.style.left = newLeft + 'px';

    // Update max-height of the body to match the new container height
    const betDisplayBody = betDisplay.querySelector('.bet-display-body');
    if (betDisplayBody) {
      const headerHeight = betDisplay.querySelector('.bet-display-header').offsetHeight;
      const stakeControlHeight = betDisplay.querySelector('.stake-control').offsetHeight;
      betDisplayBody.style.maxHeight = (newHeight - headerHeight - stakeControlHeight) + 'px';
    }
  }

  // Function to end resizing
  function endResize(e) {
    if (!isResizing) return;

    isResizing = false;
    betDisplay.classList.remove('resizing');

    // Remove event listeners for resize
    document.removeEventListener('mousemove', doResize);
    document.removeEventListener('mouseup', endResize);
  }

  // Function to initialize the initial position
  function initializePosition() {
    // Set initial max-height for the body based on container height
    const betDisplayBody = betDisplay.querySelector('.bet-display-body');
    if (betDisplayBody) {
      const headerHeight = betDisplay.querySelector('.bet-display-header').offsetHeight;
      const stakeControlHeight = betDisplay.querySelector('.stake-control').offsetHeight;
      const containerHeight = betDisplay.offsetHeight;
      betDisplayBody.style.maxHeight = (containerHeight - headerHeight - stakeControlHeight) + 'px';
    }
  }

  // Initialize position after a short delay to ensure DOM is ready
  setTimeout(initializePosition, 100);
}

// Call this function during initialization
$(document).ready(function() {
  // ... existing initialization code ...

  // Initialize the resizable and draggable bet display
  initializeResizableDraggableBetDisplay();

  // Set custom height for bet display
  setBetDisplayHeight();

  // ... rest of your code ...
});

// Function to set the bet display height
function setBetDisplayHeight() {
  // Set a timeout to ensure DOM is fully loaded
  setTimeout(function() {
    // Get the bet display container
    const betDisplay = document.getElementById('bet-display-container');
    if (betDisplay) {
      // Set new height
      betDisplay.style.height = '500px';

      // Also update the max-height of the bet display body
      const betDisplayBody = betDisplay.querySelector('.bet-display-body');
      if (betDisplayBody) {
        const headerHeight = betDisplay.querySelector('.bet-display-header').offsetHeight;
        const stakeControlHeight = betDisplay.querySelector('.stake-control').offsetHeight;
        betDisplayBody.style.maxHeight = (500 - headerHeight - stakeControlHeight) + 'px';
      }

      // Update the bet display list
      const betDisplayList = betDisplay.querySelector('.bet-display-list');
      if (betDisplayList) {
        betDisplayList.style.maxHeight = '600px';
      }

      console.log('Bet display height updated to 500px');
    }
  }, 300);
}

// Add a function to save betting slip to database
function saveBettingSlipToDatabase(barcodeNumber, bets, totalStakes, potentialReturn) {
    try {
        // Prepare data for the API call - no need to specify user_id, the server will handle it
        const data = {
            slip_number: barcodeNumber,
            bets: bets,
            total_stake: totalStakes,
            potential_return: potentialReturn,
            draw_number: getCurrentDrawNumber()
        };

        console.log('Saving betting slip data:', data);

        // Send the data to the server
        fetch('php/save_betting_slip.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                console.log('Betting slip saved successfully:', result);

                // Show success notification if needed
                if (typeof showNotification === 'function') {
                    showNotification('Betting slip saved successfully', 'success');
                }

                // Save the slip number to localStorage for later reference
                localStorage.setItem('lastSlipNumber', barcodeNumber);

                return true;
            } else {
                console.error('Error saving betting slip:', result.message);
                showErrorMessage(result.message || 'Failed to save betting slip');
                return false;
            }
        })
        .catch(error => {
            console.error('Error in API call:', error);
            showErrorMessage('An error occurred while saving the betting slip');
            return false;
        });
    } catch (error) {
        console.error('Exception while saving betting slip:', error);
        showErrorMessage('An unexpected error occurred');
        return false;
    }

    // Helper function to show error messages
    function showErrorMessage(message) {
        console.error(message);

        // Show error notification if the function exists
        if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        } else {
            // Fallback to alert if notification function doesn't exist
            alert('Error: ' + message);
        }
    }
}

/* Countdown Timer Functionality */
// Add this at the end of the file

// Timer variables
let countdownEndTime = 0;
let countdownInterval = null;
const countdownDisplay = document.getElementById('countdown-timer');

// Function to start the countdown
function startCountdown() {
  // Clear any existing intervals
  if (countdownInterval) {
    clearInterval(countdownInterval);
  }

  // Get countdown time from server or use default (120 seconds)
  fetch('load_state.php')
    .then(response => response.json())
    .then(data => {
      console.log('Loaded state:', data);
      let countdownTime = 120; // Default 2 minutes

      if (data && data.countdown_time) {
        countdownTime = data.countdown_time;
      }

      // Calculate end time
      countdownEndTime = Date.now() + (countdownTime * 1000);

      // Store in localStorage for persistence
      localStorage.setItem('countdownEndTime', countdownEndTime);

      // Start interval
      countdownInterval = setInterval(updateCountdownDisplay, 1000);

      // Immediately update display
      updateCountdownDisplay();
    })
    .catch(error => {
      console.error('Error loading state:', error);

      // Use default 2 minutes if we can't load from server
      countdownEndTime = Date.now() + (120 * 1000);
      localStorage.setItem('countdownEndTime', countdownEndTime);

      // Start interval
      countdownInterval = setInterval(updateCountdownDisplay, 1000);

      // Immediately update display
      updateCountdownDisplay();
    });
}

// Function to update the countdown display
function updateCountdownDisplay() {
  // Calculate remaining time
  const now = Date.now();
  const remainingTime = Math.max(0, countdownEndTime - now);

  // Convert to minutes and seconds
  const minutes = Math.floor(remainingTime / 60000);
  const seconds = Math.floor((remainingTime % 60000) / 1000);

  // Update display
  countdownDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

  // Add warning class if less than 10 seconds
  if (remainingTime < 10000) {
    countdownDisplay.classList.add('timer-warning');
  } else {
    countdownDisplay.classList.remove('timer-warning');
  }

  // If time is up, clear interval and refresh the page
  if (remainingTime <= 0) {
    clearInterval(countdownInterval);
    refreshPageOnTimerEnd();
    return; // Return early since we're refreshing the page
  }
}

// Function to handle page refresh when timer ends
function refreshPageOnTimerEnd() {
  console.log('Countdown reached zero - updating draw and refreshing page');

  // Create a visual indicator that refresh is happening
  const refreshIndicator = document.createElement('div');
  refreshIndicator.style.position = 'fixed';
  refreshIndicator.style.top = '50%';
  refreshIndicator.style.left = '50%';
  refreshIndicator.style.transform = 'translate(-50%, -50%)';
  refreshIndicator.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
  refreshIndicator.style.color = '#fff';
  refreshIndicator.style.padding = '20px';
  refreshIndicator.style.borderRadius = '5px';
  refreshIndicator.style.zIndex = '10000';
  refreshIndicator.style.fontFamily = 'Arial, sans-serif';
  refreshIndicator.style.fontSize = '16px';
  refreshIndicator.textContent = 'New game starting...';
  document.body.appendChild(refreshIndicator);

  // First, advance the draw number using our DrawSync module if it's available
  if (window.DrawSync && typeof window.DrawSync.advanceToNextDraw === 'function') {
    // First, update the draw number
    window.DrawSync.advanceToNextDraw();

    // Then, after a brief delay to allow the database update to complete, refresh the page
    setTimeout(() => {
      // Refresh the page
      window.location.reload();
    }, 800); // Longer delay (800ms) to ensure database updates complete
  } else {
    // If DrawSync isn't available, just refresh the page after a short delay
    setTimeout(() => {
      window.location.reload();
    }, 500);
  }
}

// Function to update the countdown display
function updateCountdownDisplay() {
  // Calculate remaining time
  const now = Date.now();
  const remainingTime = Math.max(0, countdownEndTime - now);

  // Convert to minutes and seconds
  const minutes = Math.floor(remainingTime / 60000);
  const seconds = Math.floor((remainingTime % 60000) / 1000);

  // Update display
  countdownDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

  // Add warning class if less than 10 seconds
  if (remainingTime < 10000) {
    countdownDisplay.classList.add('timer-warning');
  } else {
    countdownDisplay.classList.remove('timer-warning');
  }

  // If time is up, clear interval and refresh the page
  if (remainingTime <= 0) {
    clearInterval(countdownInterval);
    refreshPageOnTimerEnd();
    return; // Return early since we're refreshing the page
  }
}

// Handle page visibility changes
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible') {
    // Page is visible again, check if we need to recalculate timer
    const storedEndTime = localStorage.getItem('countdownEndTime');
    if (storedEndTime) {
      countdownEndTime = parseInt(storedEndTime);

      // If end time has passed, refresh the page
      if (Date.now() >= countdownEndTime) {
        refreshPageOnTimerEnd();
        return; // Return early since we're refreshing
      } else {
        // Otherwise, just restart the interval with the existing end time
        if (countdownInterval) {
          clearInterval(countdownInterval);
        }
        countdownInterval = setInterval(updateCountdownDisplay, 1000);
        updateCountdownDisplay();
      }
    } else {
      // No stored end time, start a new countdown
      startCountdown();
    }
  } else {
    // Page is hidden, clear interval to save resources
    if (countdownInterval) {
      clearInterval(countdownInterval);
    }
  }
});

// Start the countdown when the page loads
document.addEventListener('DOMContentLoaded', function() {
  startCountdown();
});

// Initialize the countdown timer when the page loads
startCountdown();

/**
 * Get the next draw number (upcoming draw for new betting slips)
 * This function ensures betting slips are always assigned to future draws
 */
function getCurrentDrawNumber() {
  return getNextDrawNumber();
}

/**
 * Get the next draw number (upcoming draw for new bets)
 */
function getNextDrawNumber() {
  // First try to get the next draw number from the UI
  const nextDrawElement = document.getElementById('next-draw-number');
  if (nextDrawElement) {
    const nextDrawText = nextDrawElement.textContent;
    const match = nextDrawText.match(/#(\d+)/);
    if (match && match[1]) {
      console.log('Using next draw number from UI:', match[1]);
      return parseInt(match[1], 10);
    }
  }

  // Try to get from database
  return getNextDrawFromDatabase();
}

/**
 * Get next draw number from database
 */
function getNextDrawFromDatabase() {
  try {
    // Synchronous request to get next draw number
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'php/get_next_draw_number.php', false); // Synchronous
    xhr.send();

    if (xhr.status === 200) {
      const response = JSON.parse(xhr.responseText);
      if (response.status === 'success' && response.next_draw_number) {
        console.log('Using next draw number from database:', response.next_draw_number);
        return parseInt(response.next_draw_number, 10);
      }
    }
  } catch (error) {
    console.error('Error getting next draw from database:', error);
  }

  // Fallback: try to get current draw and add 1
  try {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'load_analytics.php', false); // Synchronous
    xhr.send();

    if (xhr.status === 200) {
      const response = JSON.parse(xhr.responseText);
      if (response.status === 'success' && response.current_draw_number) {
        const nextDraw = parseInt(response.current_draw_number, 10) + 1;
        console.log('Calculated next draw from current draw:', nextDraw);
        return nextDraw;
      }
    }
  } catch (error) {
    console.error('Error getting current draw from analytics:', error);
  }

  // Final fallback
  console.warn('Could not determine next draw number, using fallback value 1');
  return 1;
}

/**
 * Update commission when a betting slip is sold
 * @param {number} betAmount - The amount of the bet
 * @param {string} slipNumber - Optional slip number for reference
 */
function updateCommission(betAmount, slipNumber = null) {
  // Skip if bet amount is 0 or negative
  if (betAmount <= 0) return;

  console.log(`Updating commission for bet amount: ${betAmount}, slip: ${slipNumber || 'N/A'}`);

  // Send bet amount to server to update commission
  fetch('update_commission.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      bet_amount: betAmount,
      slip_number: slipNumber
    })
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    if (data.status === 'success') {
      console.log('Commission updated successfully:', data);
    } else {
      console.error('Error updating commission:', data.message);
    }
  })
  .catch(error => {
    console.error('Error in commission API call:', error);
    // Try again with a simplified request if there was an error
    if (slipNumber) {
      console.log('Retrying commission update without slip number...');
      setTimeout(() => updateCommission(betAmount), 1000);
    }
  });
}

// Function to save roll history to the server
function saveRollHistoryToServer() {
  try {
    console.log('Saving roll history to server:', rolledNumbersArray, rolledNumbersColorArray);

    // Get current draw numbers from the DOM
    const lastDraw = $('#last-draw-number').text() || '#0';
    const nextDraw = $('#next-draw-number').text() || '#1';

    // Extract draw numbers (remove # if present)
    const currentDrawNumber = parseInt(lastDraw.replace('#', '')) || 0;
    const nextDrawNumber = parseInt(nextDraw.replace('#', '')) || 1;

    // Current countdown time - get from timer if available
    let countdownTime = 120;
    const timerEl = document.querySelector('.timer-display');
    if (timerEl) {
      const timerText = timerEl.textContent;
      const timeParts = timerText.split(':');
      if (timeParts.length === 2) {
        countdownTime = parseInt(timeParts[0]) * 60 + parseInt(timeParts[1]);
      }
    }

    // Prepare data for saving
    const gameState = {
      numbers: rolledNumbersArray.toString(),
      colors: rolledNumbersColorArray.toString(),
      lastDraw: lastDraw,
      nextDraw: nextDraw,
      timer: countdownTime
    };

    console.log('Saving game state to server:', gameState);

    // Save to Firebase if available
    if (window.FirebaseService && window.FirebaseDrawManager) {
      try {
        // Get the latest winning number (first in the array)
        const latestWinningNumber = rolledNumbersArray.length > 0 ? rolledNumbersArray[0] : null;
        const latestWinningColor = rolledNumbersColorArray.length > 0 ? rolledNumbersColorArray[0] : null;

        if (latestWinningNumber !== null && currentDrawNumber > 0) {
          console.log('🔥 Saving draw result to Firebase:', {
            drawNumber: currentDrawNumber,
            winningNumber: latestWinningNumber,
            winningColor: latestWinningColor
          });

          // Save draw result to Firebase
          FirebaseDrawManager.saveDrawResult({
            drawNumber: currentDrawNumber,
            winningNumber: latestWinningNumber,
            winningColor: latestWinningColor,
            isForced: false,
            source: 'index.php'
          }).then(result => {
            console.log('✅ Draw result saved to Firebase:', result);
          }).catch(error => {
            console.error('❌ Error saving to Firebase:', error);
          });

          // Also update draw numbers
          FirebaseDrawManager.updateDrawNumbers(currentDrawNumber, nextDrawNumber).catch(error => {
            console.error('❌ Error updating draw numbers in Firebase:', error);
          });
        }

        // Update game state in Firebase
        const firebaseGameState = {
          rollHistory: rolledNumbersArray.slice(0, 5),
          rollColors: rolledNumbersColorArray.slice(0, 5),
          drawNumber: currentDrawNumber,
          nextDrawNumber: nextDrawNumber,
          lastDrawFormatted: lastDraw,
          nextDrawFormatted: nextDraw,
          updatedAt: new Date().toISOString()
        };

        FirebaseService.GameState.updateCurrent(firebaseGameState).catch(error => {
          console.error('❌ Error updating game state in Firebase:', error);
        });
      } catch (error) {
        console.error('❌ Error in Firebase save:', error);
      }
    }

    // Send data to server (keep for backward compatibility)
    fetch('/slipp/save_state.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(gameState)
    })
    .then(response => response.json())
    .then(data => {
      console.log('Game state saved to server:', data);
    })
    .catch(error => {
      console.error('Error saving roll history to server:', error);
    });
  } catch (error) {
    console.error('Error in saveRollHistoryToServer:', error);
  }
}

/**
 * Save draw result to Firebase when a spin completes
 * This ensures draw data is saved even if tvdisplay is closed
 */
async function saveDrawResultToFirebase(winningNumber, winningColor) {
  if (!window.FirebaseDrawManager || !window.FirebaseService) {
    console.warn('Firebase not available, skipping Firebase save');
    return;
  }

  try {
    // Get current draw number from various sources
    const lastDrawElement = document.getElementById('last-draw-number');
    const nextDrawElement = document.getElementById('next-draw-number');
    
    let currentDrawNumber = null;
    let nextDrawNumber = null;

    // Try to get from DOM elements
    if (lastDrawElement) {
      const lastDrawText = lastDrawElement.textContent || '';
      currentDrawNumber = parseInt(lastDrawText.replace('#', '')) || null;
    }

    if (nextDrawElement) {
      const nextDrawText = nextDrawElement.textContent || '';
      nextDrawNumber = parseInt(nextDrawText.replace('#', '')) || null;
    }

    // If we don't have draw numbers from DOM, try to get from Firebase or DrawSync
    if (!currentDrawNumber && window.DrawSync) {
      currentDrawNumber = DrawSync.getCurrentDraw();
      nextDrawNumber = DrawSync.getNextDraw();
    }

    // If still no draw number, use the latest from rolledNumbersArray context
    // The winning number just completed the "next" draw, so that becomes the current
    if (currentDrawNumber && nextDrawNumber) {
      // The spin just completed, so the "next" draw is now the completed one
      const completedDrawNumber = nextDrawNumber - 1 > 0 ? nextDrawNumber - 1 : currentDrawNumber;
      
      console.log('🔥 Saving completed draw to Firebase:', {
        drawNumber: completedDrawNumber,
        winningNumber: winningNumber,
        winningColor: winningColor,
        currentDraw: currentDrawNumber,
        nextDraw: nextDrawNumber
      });

      // Save the completed draw result
      const result = await FirebaseDrawManager.saveDrawResult({
        drawNumber: completedDrawNumber,
        winningNumber: winningNumber,
        winningColor: winningColor,
        isForced: false,
        source: 'index.php-spin'
      });

      if (result.status === 'success') {
        console.log('✅ Draw result saved to Firebase successfully');
        
        // Update draw numbers - the completed draw becomes current, next becomes completed+1
        const newCurrent = completedDrawNumber;
        const newNext = completedDrawNumber + 1;
        await FirebaseDrawManager.updateDrawNumbers(newCurrent, newNext);
        console.log('✅ Draw numbers updated in Firebase:', { current: newCurrent, next: newNext });
      } else {
        console.error('❌ Failed to save draw result:', result.message);
      }
    } else {
      console.warn('⚠️ Cannot save to Firebase: Draw numbers not available yet');
    }
  } catch (error) {
    console.error('❌ Error saving draw result to Firebase:', error);
  }
}

/**
 * Check if a player exists in the database
 * Called before saving a betting slip to ensure the player ID is valid
 */
function checkPlayerExists(playerId) {
    // Default to Guest player if no ID provided
    if (!playerId) {
        playerId = 1;
    }

    return new Promise((resolve, reject) => {
        fetch('php/check_player.php?player_id=' + playerId)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Player check successful:', data);
                    resolve(data.player_id);
                } else {
                    console.warn('Player check failed:', data.message);
                    // Resolve with the default player ID (1 for Guest)
                    resolve(1);
                }
            })
            .catch(error => {
                console.error('Error checking player:', error);
                // Resolve with default in case of error
                resolve(1);
            });
    });
}

/**
 * Check if a player exists in the database and return a valid player ID
 * If the player doesn't exist, it will return the default Guest player ID
 *
 * @param {number|string} playerId - The player ID to check
 * @returns {Promise<number>} - A promise that resolves to a valid player ID
 */
function checkPlayerExists(playerId = null) {
    console.log("Checking player existence:", playerId);

    // Default to Guest player if no ID is provided
    if (!playerId) {
        console.log("No player ID provided, using default Guest");
        return Promise.resolve(1); // Default Guest ID
    }

    // Make a request to check if the player exists
    return fetch(`php/check_player.php?player_id=${playerId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Player check response:", data);

            if (data.status === 'success') {
                return data.player_id;
            } else {
                console.warn("Player check failed, using default Guest:", data.message);
                return 1; // Default to Guest ID on failure
            }
        })
        .catch(error => {
            console.error("Error checking player:", error);
            return 1; // Default to Guest ID on error
        });
}

// Initialize the betting system when the page loads
$(document).ready(function() {
  betTracker.init();

  // Proactively clean up any stale draw selections on page load
  setTimeout(() => {
    betTracker.cleanupStaleDrawSelections();
  }, 500); // Clean up after 500ms

  // Initialize DOM-based real-time draw number monitoring
  setTimeout(() => {
    betTracker.initializeDOMMonitoring();
    console.log('🎯 DOM Monitor: Initialized after page load');
  }, 1000); // Wait 1 second for page to fully load
});

// Listen for draw number change events
document.addEventListener('drawNumberChanged', function(event) {
  console.log('🎯 Draw Number Change Event:', event.detail);

  // Update print system with new draw number information
  if (betTracker && betTracker.handleDrawNumberChange) {
    betTracker.handleDrawNumberChange(event.detail);
  }
});

// Listen for invalid draw selection cleared events
document.addEventListener('invalidDrawSelectionCleared', function(event) {
  console.log('🎯 Invalid Draw Selection Cleared Event:', event.detail);

  // Update print system after cleanup
  if (betTracker && betTracker.handleInvalidDrawCleared) {
    betTracker.handleInvalidDrawCleared(event.detail);
  }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
  if (betTracker && betTracker.stopDOMMonitoring) {
    betTracker.stopDOMMonitoring();
  }
});

