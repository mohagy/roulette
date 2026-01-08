/**
 * Movable Sidebar Functionality
 * This script makes the user info sidebar draggable and adds numeric keypad
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the movable sidebar
    initMovableSidebar();

    // Initialize the numeric keypad
    initNumericKeypad();
});

function initMovableSidebar() {
    // Get the user info element
    const userInfo = document.getElementById('user-info');

    // If the user info element doesn't exist yet, wait for it to be created
    if (!userInfo) {
        // Check if we're on a page that uses auth-check.js which creates the user-info element
        const authCheckScript = document.querySelector('script[src*="auth-check.js"]');
        if (authCheckScript) {
            // Wait a bit for auth-check.js to create the user-info element
            setTimeout(initMovableSidebar, 500);
        }
        return;
    }

    // Add the drag handle to the user info element if it doesn't already have one
    if (!userInfo.querySelector('.user-info-drag-handle')) {
        const dragHandle = document.createElement('div');
        dragHandle.className = 'user-info-drag-handle';
        dragHandle.innerHTML = '<i class="fas fa-grip-lines"></i><span>Cashier Info</span><i class="fas fa-arrows-alt"></i>';

        // Insert the drag handle at the beginning of the user info element
        userInfo.insertBefore(dragHandle, userInfo.firstChild);
    }

    // Variables to track dragging
    let isDragging = false;
    let offsetX, offsetY;

    // Get the drag handle
    const dragHandle = userInfo.querySelector('.user-info-drag-handle');

    // Add event listeners for dragging
    dragHandle.addEventListener('mousedown', startDrag);
    dragHandle.addEventListener('touchstart', startDrag, { passive: false });

    // Function to start dragging
    function startDrag(e) {
        // Prevent default behavior for touch events
        if (e.type === 'touchstart') {
            e.preventDefault();
        }

        // Get the current position of the user info element
        const rect = userInfo.getBoundingClientRect();

        // Calculate the offset from the mouse/touch position to the top-left corner of the element
        if (e.type === 'touchstart') {
            offsetX = e.touches[0].clientX - rect.left;
            offsetY = e.touches[0].clientY - rect.top;
        } else {
            offsetX = e.clientX - rect.left;
            offsetY = e.clientY - rect.top;
        }

        // Set dragging to true
        isDragging = true;

        // Add the dragging class to the user info element
        userInfo.classList.add('dragging');

        // Add event listeners for dragging and stopping
        document.addEventListener('mousemove', drag);
        document.addEventListener('touchmove', drag, { passive: false });
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchend', stopDrag);
    }

    // Function to handle dragging
    function drag(e) {
        if (!isDragging) return;

        // Prevent default behavior for touch events
        if (e.type === 'touchmove') {
            e.preventDefault();
        }

        // Calculate the new position
        let clientX, clientY;
        if (e.type === 'touchmove') {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            clientX = e.clientX;
            clientY = e.clientY;
        }

        // Calculate the new position
        let newLeft = clientX - offsetX;
        let newTop = clientY - offsetY;

        // Get the dimensions of the viewport and the element
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const elementWidth = userInfo.offsetWidth;
        const elementHeight = userInfo.offsetHeight;

        // Ensure the element stays within the viewport bounds
        newLeft = Math.max(0, Math.min(newLeft, viewportWidth - elementWidth));
        newTop = Math.max(0, Math.min(newTop, viewportHeight - elementHeight));

        // Set the new position
        userInfo.style.left = newLeft + 'px';
        userInfo.style.top = newTop + 'px';

        // Save the position to localStorage
        savePosition(newLeft, newTop);
    }

    // Function to stop dragging
    function stopDrag() {
        if (!isDragging) return;

        // Set dragging to false
        isDragging = false;

        // Remove the dragging class from the user info element
        userInfo.classList.remove('dragging');
        userInfo.classList.add('moved');

        // Remove the moved class after the animation completes
        setTimeout(() => {
            userInfo.classList.remove('moved');
        }, 300);

        // Remove event listeners for dragging and stopping
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('touchmove', drag);
        document.removeEventListener('mouseup', stopDrag);
        document.removeEventListener('touchend', stopDrag);
    }

    // Function to save the position to localStorage
    function savePosition(left, top) {
        localStorage.setItem('userInfoPosition', JSON.stringify({ left, top }));
    }

    // Function to load the position from localStorage
    function loadPosition() {
        const position = localStorage.getItem('userInfoPosition');
        if (position) {
            const { left, top } = JSON.parse(position);

            // Get the dimensions of the viewport and the element
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const elementWidth = userInfo.offsetWidth;
            const elementHeight = userInfo.offsetHeight;

            // Ensure the element stays within the viewport bounds
            const newLeft = Math.max(0, Math.min(left, viewportWidth - elementWidth));
            const newTop = Math.max(0, Math.min(top, viewportHeight - elementHeight));

            // Set the position
            userInfo.style.left = newLeft + 'px';
            userInfo.style.top = newTop + 'px';
        }
    }

    // Load the position from localStorage
    loadPosition();

    // Add event listener for window resize to ensure the element stays within the viewport
    window.addEventListener('resize', function() {
        // Get the current position
        const rect = userInfo.getBoundingClientRect();

        // Get the dimensions of the viewport and the element
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const elementWidth = userInfo.offsetWidth;
        const elementHeight = userInfo.offsetHeight;

        // Ensure the element stays within the viewport bounds
        const newLeft = Math.max(0, Math.min(rect.left, viewportWidth - elementWidth));
        const newTop = Math.max(0, Math.min(rect.top, viewportHeight - elementHeight));

        // Set the position
        userInfo.style.left = newLeft + 'px';
        userInfo.style.top = newTop + 'px';

        // Save the position to localStorage
        savePosition(newLeft, newTop);
    });
}

/**
 * Initialize the numeric keypad for stake input
 * Note: Stake control has been removed from sidebar
 */
function initNumericKeypad() {
    console.log('Stake control has been removed from sidebar - skipping numeric keypad initialization');
    // Stake control functionality removed - no longer needed
}

/**
 * Create the numeric keypad for the stake input
 */
function createNumericKeypad(inputElement) {
    // Create the keypad container
    const keypad = document.createElement('div');
    keypad.className = 'numeric-keypad';
    keypad.id = 'numeric-keypad';

    // Create the keys
    const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '⏎'];

    // Add the keys to the keypad
    keys.forEach(key => {
        const keyElement = document.createElement('div');
        keyElement.className = 'numeric-key';

        // Add special classes for special keys
        if (key === 'C') {
            keyElement.className += ' key-clear';
            keyElement.textContent = 'Clear';
        } else if (key === '⏎') {
            keyElement.className += ' key-enter';
            keyElement.textContent = 'Enter';
        } else {
            keyElement.textContent = key;
        }

        // Add event listener for the key
        keyElement.addEventListener('click', function() {
            handleNumericKeyPress(key, inputElement);
        });

        // Add the key to the keypad
        keypad.appendChild(keyElement);
    });

    // Add the keypad to the input's parent
    inputElement.parentNode.appendChild(keypad);

    // Add event listener to close the keypad when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.stake-input-wrapper') && !e.target.closest('.numeric-keypad')) {
            hideNumericKeypad();
        }
    });
}

/**
 * Show the numeric keypad for the given input
 */
function showNumericKeypad(inputElement) {
    // Hide any visible keypads
    hideNumericKeypad();

    // Show the keypad for this input
    const keypad = inputElement.parentNode.querySelector('.numeric-keypad');
    if (keypad) {
        keypad.classList.add('visible');
    }
}

/**
 * Hide all numeric keypads
 */
function hideNumericKeypad() {
    document.querySelectorAll('.numeric-keypad').forEach(keypad => {
        keypad.classList.remove('visible');
    });
}

/**
 * Handle a numeric key press
 */
function handleNumericKeyPress(key, inputElement) {
    // Get the current value
    let currentValue = inputElement.value;

    // Handle the key press
    if (key === 'C') {
        // Clear the input
        inputElement.value = '';
    } else if (key === '⏎') {
        // Hide the keypad and trigger the enter key
        hideNumericKeypad();

        // Trigger the enter key event
        const event = new KeyboardEvent('keydown', {
            key: 'Enter',
            code: 'Enter',
            keyCode: 13,
            which: 13,
            bubbles: true
        });
        inputElement.dispatchEvent(event);

        // Trigger change event to update other inputs
        inputElement.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
        // Add the number to the input
        inputElement.value = currentValue + key;

        // Trigger input event to update other inputs
        inputElement.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

/**
 * Sync the sidebar stake input with the main stake input
 * Note: Stake control has been removed from sidebar
 */
function syncStakeInputs(sidebarInput) {
    console.log('Stake control has been removed from sidebar - no sync needed');
    // Stake control functionality removed - no longer needed
}
