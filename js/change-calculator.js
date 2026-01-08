/**
 * Change Calculator
 * Calculates change based on received amount and total stakes
 */
document.addEventListener('DOMContentLoaded', function() {
  // Create the change calculator HTML
  function createChangeCalculator() {
    const changeCalculator = document.createElement('div');
    changeCalculator.className = 'change-calculator';
    changeCalculator.innerHTML = `
      <div class="change-calculator-toggle">
        <span>Change Calculator</span>
        <i class="fas fa-chevron-up"></i>
      </div>
      <div class="change-calculator-content">
        <div class="change-calculator-title">Calculate Change</div>
        <div class="change-calculator-row">
          <div class="change-calculator-label">Received Amount:</div>
          <input type="number" class="change-calculator-input" id="received-amount" min="0" placeholder="0.00">
        </div>
        <div class="change-calculator-divider"></div>
        <div class="change-calculator-row">
          <div class="change-calculator-label">Total Stakes:</div>
          <div class="change-calculator-result" id="total-stakes-display">$0.00</div>
        </div>
        <div class="change-calculator-divider"></div>
        <div class="change-calculator-row">
          <div class="change-calculator-label">Change:</div>
          <div class="change-calculator-result" id="change-result">$0.00</div>
        </div>
      </div>
    `;

    // Insert the change calculator into the bet display container
    const betDisplayContainer = document.querySelector('.bet-display-container');
    if (betDisplayContainer) {
      betDisplayContainer.appendChild(changeCalculator);
    }

    // Add event listeners
    setupChangeCalculatorEvents();
  }

  // Set up event listeners for the change calculator
  function setupChangeCalculatorEvents() {
    // Toggle calculator visibility
    const toggleButton = document.querySelector('.change-calculator-toggle');
    if (toggleButton) {
      toggleButton.addEventListener('click', function() {
        const calculator = document.querySelector('.change-calculator');
        calculator.classList.toggle('collapsed');

        // Save state to localStorage
        localStorage.setItem('changeCalculatorCollapsed', calculator.classList.contains('collapsed'));
      });

      // Set initial state based on localStorage
      const isCollapsed = localStorage.getItem('changeCalculatorCollapsed') === 'true';
      if (isCollapsed) {
        document.querySelector('.change-calculator').classList.add('collapsed');
      }
    }

    // Calculate change when received amount changes
    const receivedAmountInput = document.getElementById('received-amount');
    if (receivedAmountInput) {
      receivedAmountInput.addEventListener('input', calculateChange);

      // Also recalculate when total stakes change
      const observer = new MutationObserver(calculateChange);
      const stakesValue = document.querySelector('.stakes-value');
      if (stakesValue) {
        observer.observe(stakesValue, { childList: true, characterData: true, subtree: true });
      }
    }
  }

  // Calculate change based on received amount and total stakes
  function calculateChange() {
    const receivedAmount = parseFloat(document.getElementById('received-amount').value) || 0;
    const stakesValueText = document.querySelector('.stakes-value').textContent;
    const totalStakes = parseFloat(stakesValueText.replace(/[^0-9.]/g, '')) || 0;

    // Update the total stakes display in the calculator
    const totalStakesDisplay = document.getElementById('total-stakes-display');
    if (totalStakesDisplay) {
      totalStakesDisplay.textContent = '$' + totalStakes.toFixed(2);
    }

    const change = receivedAmount - totalStakes;
    const changeResult = document.getElementById('change-result');

    if (change >= 0) {
      changeResult.textContent = '$' + change.toFixed(2);
      changeResult.style.color = '#4CAF50'; // Green for positive change
    } else {
      changeResult.textContent = '-$' + Math.abs(change).toFixed(2);
      changeResult.style.color = '#f44336'; // Red for negative change (not enough money)
    }
  }

  // Initialize the change calculator
  createChangeCalculator();
});
