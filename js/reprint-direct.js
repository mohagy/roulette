/**
 * Direct Reprint Functionality
 *
 * This script provides a direct reprint functionality for betting slips.
 */

// Add CSS for the processing state of the print button
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .print-action-button.processing-reprint {
            opacity: 0.7;
            cursor: not-allowed;
            position: relative;
        }

        .print-action-button.processing-reprint::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin-top: -10px;
            margin-left: -10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
})();

class DirectReprintHandler {
    constructor() {
        this.init();
    }

    /**
     * Initialize the reprint handler
     */
    init() {
        console.log('Initializing DirectReprintHandler');
        this.setupEventListeners();
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Flag to track if we're currently processing a reprint
        this.isProcessingReprint = false;

        // Listen for clicks on the print button in the betting slip preview modal
        document.addEventListener('click', (event) => {
            const printButton = event.target.closest('.print-action-button');
            if (printButton) {
                // Check if we should prevent this print dialog
                if (window.preventNextPrintDialog) {
                    console.log('Preventing duplicate print dialog');
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }

                const modal = document.querySelector('.print-slip-modal');
                if (modal && modal.classList.contains('visible')) {
                    // Check if this is a reprint by looking for the reprint watermark
                    // or checking if the modal content contains "Original Draw #"
                    const modalContent = modal.querySelector('.print-slip-content');
                    const isReprint = modal.querySelector('.reprint-watermark') !== null ||
                                     (modalContent && modalContent.innerHTML.includes('Original Draw #'));

                    if (isReprint && !this.isProcessingReprint) {
                        console.log('Reprint button clicked');

                        // Set the flag to prevent duplicate processing
                        this.isProcessingReprint = true;

                        // Prevent the default print action
                        event.preventDefault();
                        event.stopPropagation();

                        // Handle the reprint
                        this.handleReprintButtonClick(event);

                        // Add a class to the button to indicate it's being processed
                        printButton.classList.add('processing-reprint');

                        // Disable the button temporarily to prevent double-clicks
                        printButton.disabled = true;

                        // Reset after a delay
                        setTimeout(() => {
                            this.isProcessingReprint = false;
                            printButton.classList.remove('processing-reprint');
                            printButton.disabled = false;
                        }, 3000);

                        return false;
                    }
                }
            }
        });
    }

    /**
     * Handle reprint button click
     */
    handleReprintButtonClick(event) {
        console.log('Handling reprint button click');

        // Prevent any default actions
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        // Get the slip ID and draw number from the modal content
        const modal = document.querySelector('.print-slip-modal');
        const slipContent = modal.querySelector('.print-slip-content');

        // Store a reference to the original print button
        this.originalPrintButton = event.target.closest('.print-action-button');

        // Temporarily disable the default print functionality
        if (this.originalPrintButton) {
            this.originalPrintButton.style.pointerEvents = 'none';
        }

        // Extract slip ID from the content
        const originalDrawMatch = slipContent.innerHTML.match(/Original Draw #: (\d+)/);
        const slipNumberMatch = slipContent.innerHTML.match(/Slip Number: (\d+)/);
        const drawNumberMatch = slipContent.innerHTML.match(/Draw #: (\d+)/);

        if (!originalDrawMatch || !slipNumberMatch || !drawNumberMatch) {
            console.error('Could not extract slip information from modal content');
            this.showError('Could not extract slip information from the preview');

            // Re-enable the print button
            if (this.originalPrintButton) {
                this.originalPrintButton.style.pointerEvents = '';
            }

            return;
        }

        const originalDraw = originalDrawMatch[1];
        const slipNumber = slipNumberMatch[1];
        const drawNumber = drawNumberMatch[1];

        console.log('Extracted information:', {
            originalDraw,
            slipNumber,
            drawNumber
        });

        // First, get the slip ID from the slip number
        this.getSlipIdFromNumber(slipNumber)
            .then(slipId => {
                if (!slipId) {
                    throw new Error('Could not find slip ID for slip number: ' + slipNumber);
                }

                console.log('Found slip ID:', slipId);

                // Show loading indicator
                this.showLoadingIndicator('Processing reprint...');

                // Now reprint the slip
                return this.reprintSlip(slipId, drawNumber);
            })
            .then(data => {
                console.log('Reprint successful:', data);

                // Hide loading indicator
                this.hideLoadingIndicator();

                // Close the modal with a slight delay to ensure the print dialog appears first
                setTimeout(() => {
                    // Check if the modal is still visible (user might have closed it manually)
                    if (modal && modal.classList.contains('visible')) {
                        console.log('Closing modal after successful reprint');
                        modal.classList.remove('visible');
                    }
                }, 500);

                // Update the cash balance if provided
                if (data.new_balance !== undefined && typeof CashManager !== 'undefined') {
                    console.log('Updating cash balance to:', data.new_balance);

                    // Force a refresh of the cash balance from the server
                    CashManager.refreshBalance()
                        .then(newBalance => {
                            console.log('Cash balance refreshed:', newBalance);

                            // Update the UI display of cash balance
                            const cashTotalElement = document.querySelector('.cash-total');
                            if (cashTotalElement) {
                                cashTotalElement.textContent = CashManager.formatCash(newBalance);
                            }
                        })
                        .catch(error => {
                            console.error('Failed to refresh cash balance:', error);
                        });
                }

                // Show success message
                this.showSuccess(`Slip #${data.new_slip_number} reprinted successfully for draw #${data.draw_number}`);

                // Print the slip directly if content is provided
                if (data.slip_content) {
                    console.log('Printing slip directly from modal');

                    // Verify the transaction was successful by checking the new slip ID
                    fetch(`check_slip_exists.php?slip_id=${data.new_slip_id}`)
                        .then(response => response.json())
                        .then(checkResult => {
                            if (checkResult.exists) {
                                console.log('Slip confirmed to exist in database, printing directly');

                                // Print the slip directly
                                this.printSlipContent(data.slip_content, data.new_slip_number);
                            } else {
                                console.error('Slip does not exist in database!');
                                this.showError('Error: The reprinted slip was not found in the database.');

                                // Fallback to opening in a new tab if available
                                if (data.print_url) {
                                    console.log('Falling back to print URL:', data.print_url);
                                    window.open(data.print_url, '_blank');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error checking slip existence:', error);

                            // Try direct printing anyway
                            this.printSlipContent(data.slip_content, data.new_slip_number);
                        });
                }
                // Fallback to opening a new tab if no content is provided
                else if (data.print_url) {
                    console.log('No slip content provided, falling back to print URL:', data.print_url);
                    window.open(data.print_url, '_blank');
                }
            })
            .catch(error => {
                console.error('Error reprinting slip:', error);
                this.hideLoadingIndicator();
                this.showError(error.message || 'Error reprinting slip');

                // Re-enable the print button
                if (this.originalPrintButton) {
                    this.originalPrintButton.style.pointerEvents = '';
                }

                // Reset the processing flag
                this.isProcessingReprint = false;
            })
            .finally(() => {
                // Ensure the processing flag is reset after a timeout
                setTimeout(() => {
                    this.isProcessingReprint = false;

                    // Re-enable the print button if it's still disabled
                    if (this.originalPrintButton) {
                        this.originalPrintButton.style.pointerEvents = '';
                    }
                }, 5000);
            });
    }

    /**
     * Get slip ID from slip number
     */
    getSlipIdFromNumber(slipNumber) {
        return fetch('php/get_slip_id.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `slip_number=${slipNumber}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to get slip ID');
            }

            return data.slip_id;
        });
    }

    /**
     * Reprint a slip
     */
    reprintSlip(slipId, drawNumber) {
        console.log(`Reprinting slip ID: ${slipId} for draw number: ${drawNumber}`);

        return fetch('php/reprint_slip_direct.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `slip_id=${slipId}&draw_number=${drawNumber}`
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text().then(text => {
                try {
                    console.log('Raw response:', text);
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    console.error('Raw response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to reprint slip');
            }

            return data;
        });
    }

    /**
     * Show a loading indicator
     */
    showLoadingIndicator(message = 'Loading...') {
        // Create a loading overlay if it doesn't exist
        let loadingOverlay = document.getElementById('reprint-loading-overlay');
        if (!loadingOverlay) {
            loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'reprint-loading-overlay';
            loadingOverlay.style.position = 'fixed';
            loadingOverlay.style.top = '0';
            loadingOverlay.style.left = '0';
            loadingOverlay.style.width = '100%';
            loadingOverlay.style.height = '100%';
            loadingOverlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.justifyContent = 'center';
            loadingOverlay.style.alignItems = 'center';
            loadingOverlay.style.zIndex = '10001';

            const loadingContent = document.createElement('div');
            loadingContent.style.backgroundColor = 'white';
            loadingContent.style.padding = '20px';
            loadingContent.style.borderRadius = '5px';
            loadingContent.style.textAlign = 'center';

            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.style.border = '4px solid #f3f3f3';
            spinner.style.borderTop = '4px solid #3498db';
            spinner.style.borderRadius = '50%';
            spinner.style.width = '30px';
            spinner.style.height = '30px';
            spinner.style.animation = 'spin 2s linear infinite';
            spinner.style.margin = '0 auto 10px';

            const messageElement = document.createElement('div');
            messageElement.id = 'reprint-loading-message';
            messageElement.textContent = message;

            loadingContent.appendChild(spinner);
            loadingContent.appendChild(messageElement);
            loadingOverlay.appendChild(loadingContent);

            // Add the spinner animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);

            document.body.appendChild(loadingOverlay);
        } else {
            document.getElementById('reprint-loading-message').textContent = message;
            loadingOverlay.style.display = 'flex';
        }
    }

    /**
     * Hide the loading indicator
     */
    hideLoadingIndicator() {
        const loadingOverlay = document.getElementById('reprint-loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = 'none';
        }
    }

    /**
     * Show an error message
     */
    showError(message) {
        // Create a floating notification if it doesn't exist
        let notification = document.getElementById('reprint-error-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'reprint-error-notification';
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.padding = '15px 20px';
            notification.style.backgroundColor = 'rgba(220, 53, 69, 0.9)';
            notification.style.color = 'white';
            notification.style.borderRadius = '5px';
            notification.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
            notification.style.zIndex = '10000';
            notification.style.transition = 'opacity 0.5s ease-in-out';
            notification.style.opacity = '0';
            document.body.appendChild(notification);
        }

        // Set the message and show the notification
        notification.textContent = message;
        notification.style.opacity = '1';

        // Hide the notification after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 500);
        }, 5000);
    }

    /**
     * Show a success message
     */
    showSuccess(message) {
        // Create a floating notification if it doesn't exist
        let notification = document.getElementById('reprint-success-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.id = 'reprint-success-notification';
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.padding = '15px 20px';
            notification.style.backgroundColor = 'rgba(40, 167, 69, 0.9)';
            notification.style.color = 'white';
            notification.style.borderRadius = '5px';
            notification.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
            notification.style.zIndex = '10000';
            notification.style.transition = 'opacity 0.5s ease-in-out';
            notification.style.opacity = '0';
            document.body.appendChild(notification);
        }

        // Set the message and show the notification
        notification.textContent = message;
        notification.style.opacity = '1';

        // Hide the notification after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 500);
        }, 5000);
    }

    /**
     * Print slip content directly from the modal
     */
    printSlipContent(htmlContent, slipNumber) {
        console.log('Printing slip content directly');

        // Set a global flag to prevent the second print dialog
        window.preventNextPrintDialog = true;

        // Check if we already have a print iframe
        const existingIframe = document.getElementById('print-iframe-reprint');
        if (existingIframe) {
            console.log('Removing existing print iframe');
            existingIframe.parentNode.removeChild(existingIframe);
        }

        // Create a hidden iframe for printing
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.id = 'print-iframe-reprint';
        document.body.appendChild(iframe);

        // Add a flag to track if we've already printed
        this.hasPrinted = false;

        // Write the content to the iframe
        iframe.contentDocument.write(htmlContent);
        iframe.contentDocument.close();

        // Wait for the iframe content to load
        iframe.onload = () => {
            try {
                // Only print if we haven't already
                if (!this.hasPrinted) {
                    console.log('Iframe loaded, printing content');
                    this.hasPrinted = true;

                    // Add a print event listener to detect when printing is complete
                    const afterPrint = () => {
                        console.log('Print completed or cancelled');

                        // Remove the event listener
                        iframe.contentWindow.removeEventListener('afterprint', afterPrint);

                        // Remove the iframe after a delay
                        setTimeout(() => {
                            if (iframe.parentNode) {
                                iframe.parentNode.removeChild(iframe);
                                console.log('Print iframe removed');
                            }
                        }, 1000);

                        // Reset the global flag after a delay
                        setTimeout(() => {
                            window.preventNextPrintDialog = false;
                            console.log('Reset preventNextPrintDialog flag');
                        }, 2000);
                    };

                    // Add the afterprint event listener
                    iframe.contentWindow.addEventListener('afterprint', afterPrint);

                    // Print the iframe content
                    iframe.contentWindow.print();

                    // Set a timeout to remove the iframe even if afterprint doesn't fire
                    setTimeout(() => {
                        if (iframe.parentNode) {
                            iframe.parentNode.removeChild(iframe);
                            console.log('Print iframe removed (timeout)');
                        }

                        // Reset the global flag
                        window.preventNextPrintDialog = false;
                        console.log('Reset preventNextPrintDialog flag (timeout)');
                    }, 10000);
                } else {
                    console.log('Already printed, skipping duplicate print');
                }
            } catch (error) {
                console.error('Error printing slip content:', error);
                this.showError('Error printing slip: ' + error.message);

                // Remove the iframe
                if (iframe.parentNode) {
                    iframe.parentNode.removeChild(iframe);
                }

                // Reset the print flag
                this.hasPrinted = false;

                // Reset the global flag
                window.preventNextPrintDialog = false;
            }
        };

        // Handle errors
        iframe.onerror = (error) => {
            console.error('Error loading iframe:', error);
            this.showError('Error loading print content');

            // Remove the iframe
            if (iframe.parentNode) {
                iframe.parentNode.removeChild(iframe);
            }

            // Reset the print flag
            this.hasPrinted = false;

            // Reset the global flag
            window.preventNextPrintDialog = false;
        };
    }
}

// Initialize the direct reprint handler when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the handler
    window.directReprintHandler = new DirectReprintHandler();

    // Add a global beforeprint event listener to prevent duplicate print dialogs
    window.addEventListener('beforeprint', (event) => {
        if (window.preventNextPrintDialog) {
            console.log('Preventing duplicate print dialog from global event');
            event.preventDefault();
            event.stopPropagation();

            // Reset the flag after a short delay
            setTimeout(() => {
                window.preventNextPrintDialog = false;
                console.log('Reset preventNextPrintDialog flag from global event');
            }, 500);

            return false;
        }
    }, true);
});
