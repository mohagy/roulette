// Store the last updated timestamp
let lastUpdated = Math.floor(Date.now() / 1000);

// Function to update betting slip statuses
function updateBettingSlipStatuses() {
    $.ajax({
        url: 'get_betting_slip_status.php',
        type: 'GET',
        data: {
            last_updated: lastUpdated
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update the last updated timestamp
                lastUpdated = response.timestamp;

                // Process updated betting slips
                if (response.betting_slips.length > 0) {
                    response.betting_slips.forEach(function(slip) {
                        // Find the row for this slip
                        const slipRow = $(`tr[data-slip-number="${slip.slip_number}"]`);

                        if (slipRow.length) {
                            // Update draw number if needed
                            const drawNumberCell = slipRow.find('td:nth-child(3)');
                            if (slip.draw_number && drawNumberCell.text().trim() !== slip.draw_number.toString()) {
                                drawNumberCell.text(slip.draw_number);
                            }

                            // Update draw time if available
                            const drawTimeCell = slipRow.find('td:nth-child(4)');
                            if (slip.draw_time) {
                                // Format the draw time from MySQL datetime format
                                drawTimeCell.html(slip.draw_time);
                            } else {
                                drawTimeCell.html('<span class="text-muted">Pending</span>');
                            }

                            // Update winning number
                            const winningNumberCell = slipRow.find('td:nth-child(7)');
                            if (slip.actual_winning_number !== null) {
                                const colorClass = slip.winning_color === 'red' ? 'danger' :
                                                  (slip.winning_color === 'black' ? 'dark' : 'success');
                                winningNumberCell.html(`<span class="badge badge-${colorClass}">${slip.actual_winning_number}</span>`);
                            }

                            // Update result
                            const resultCell = slipRow.find('td:nth-child(8)');
                            let newResultHtml = '';

                            if (slip.actual_winning_number === null) {
                                newResultHtml = '<span class="badge badge-secondary">Pending</span>';
                            } else if (slip.is_winner) {
                                newResultHtml = '<span class="badge badge-success">WIN</span>';
                            } else {
                                newResultHtml = '<span class="badge badge-danger">LOSS</span>';
                            }

                            // Only update if the content has changed
                            if (resultCell.html().trim() !== newResultHtml) {
                                // Store the old result for notification
                                const oldResult = resultCell.find('.badge').text().trim();
                                const newResult = slip.actual_winning_number === null ? 'Pending' :
                                                 (slip.is_winner ? 'WIN' : 'LOSS');

                                // Update the content
                                resultCell.html(newResultHtml);

                                // Add badge pulse animation
                                resultCell.find('.badge').addClass('pulse');
                                setTimeout(function() {
                                    resultCell.find('.badge').removeClass('pulse');
                                }, 500);

                                // Add row highlight animation
                                slipRow.addClass('highlight-update');
                                setTimeout(function() {
                                    slipRow.removeClass('highlight-update');
                                }, 3000);

                                // POS System: Browser notifications disabled for cashier interface
                            }

                            // Update actual win
                            const actualWinCell = slipRow.find('td:nth-child(9)');
                            if (slip.is_winner) {
                                actualWinCell.html(`<span class="text-success font-weight-bold">$${parseFloat(slip.winning_amount).toFixed(2)}</span>`);
                            } else {
                                actualWinCell.html('<span class="text-muted">$0.00</span>');
                            }

                            // Update bet details if expanded
                            const detailsRow = $(`#slip-${slip.slip_id}`);
                            if (detailsRow.hasClass('show') && slip.actual_winning_number !== null) {
                                // Update the bet results in the expanded details
                                const betRows = detailsRow.find('tbody tr');

                                slip.bets.forEach((bet, index) => {
                                    if (index < betRows.length) {
                                        const resultCell = $(betRows[index]).find('td:last-child');

                                        // Determine if this bet is a winner
                                        let isWinningBet = false;
                                        const winningNumber = slip.actual_winning_number;
                                        const winningNum = parseInt(winningNumber);

                                        switch (bet.bet_type.toLowerCase()) {
                                            case 'straight':
                                                const straightMatch = bet.bet_description.match(/(\d+)/);
                                                if (straightMatch && parseInt(straightMatch[1]) === winningNum) {
                                                    isWinningBet = true;
                                                    console.log(`Straight bet won: ${bet.bet_description} matches ${winningNum}`);
                                                }
                                                break;

                                            case 'split':
                                                const splitMatch = bet.bet_description.match(/\((\d+),(\d+)\)/);
                                                if (splitMatch && (parseInt(splitMatch[1]) === winningNum || parseInt(splitMatch[2]) === winningNum)) {
                                                    isWinningBet = true;
                                                    console.log(`Split bet won: ${bet.bet_description} contains ${winningNum}`);
                                                }
                                                break;

                                            case 'street':
                                                const streetMatch = bet.bet_description.match(/\((\d+),(\d+),(\d+)\)/);
                                                if (streetMatch) {
                                                    for (let i = 1; i <= 3; i++) {
                                                        if (parseInt(streetMatch[i]) === winningNum) {
                                                            isWinningBet = true;
                                                            console.log(`Street bet won: ${bet.bet_description} contains ${winningNum}`);
                                                            break;
                                                        }
                                                    }
                                                }
                                                break;

                                            case 'corner':
                                                const cornerMatch = bet.bet_description.match(/\((\d+),(\d+),(\d+),(\d+)\)/);
                                                if (cornerMatch) {
                                                    for (let i = 1; i <= 4; i++) {
                                                        if (parseInt(cornerMatch[i]) === winningNum) {
                                                            isWinningBet = true;
                                                            console.log(`Corner bet won: ${bet.bet_description} contains ${winningNum}`);
                                                            break;
                                                        }
                                                    }
                                                }
                                                break;

                                            case 'line':
                                                const lineMatch = bet.bet_description.match(/\(([\d,]+)\)/);
                                                if (lineMatch) {
                                                    const numbers = lineMatch[1].split(',').map(n => parseInt(n.trim()));
                                                    if (numbers.includes(winningNum)) {
                                                        isWinningBet = true;
                                                        console.log(`Line bet won: ${bet.bet_description} contains ${winningNum}`);
                                                    }
                                                }
                                                break;

                                            case 'dozen':
                                                if (bet.bet_description.includes('1st Dozen') && winningNum >= 1 && winningNum <= 12) {
                                                    isWinningBet = true;
                                                    console.log(`1st Dozen bet won: ${winningNum} is between 1-12`);
                                                } else if (bet.bet_description.includes('2nd Dozen') && winningNum >= 13 && winningNum <= 24) {
                                                    isWinningBet = true;
                                                    console.log(`2nd Dozen bet won: ${winningNum} is between 13-24`);
                                                } else if (bet.bet_description.includes('3rd Dozen') && winningNum >= 25 && winningNum <= 36) {
                                                    isWinningBet = true;
                                                    console.log(`3rd Dozen bet won: ${winningNum} is between 25-36`);
                                                }
                                                break;

                                            case 'column':
                                                if (bet.bet_description.includes('1st Column') && winningNum % 3 === 1) {
                                                    isWinningBet = true;
                                                    console.log(`1st Column bet won: ${winningNum} is in the 1st column`);
                                                } else if (bet.bet_description.includes('2nd Column') && winningNum % 3 === 2) {
                                                    isWinningBet = true;
                                                    console.log(`2nd Column bet won: ${winningNum} is in the 2nd column`);
                                                } else if (bet.bet_description.includes('3rd Column') && winningNum % 3 === 0 && winningNum > 0) {
                                                    isWinningBet = true;
                                                    console.log(`3rd Column bet won: ${winningNum} is in the 3rd column`);
                                                }
                                                break;

                                            case 'even-money':
                                                if (bet.bet_description.includes('Red Numbers')) {
                                                    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                                                    if (redNumbers.includes(winningNum)) {
                                                        isWinningBet = true;
                                                        console.log(`Red Numbers bet won: ${winningNum} is a red number`);
                                                    }
                                                } else if (bet.bet_description.includes('Black Numbers')) {
                                                    const blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];
                                                    if (blackNumbers.includes(winningNum)) {
                                                        isWinningBet = true;
                                                        console.log(`Black Numbers bet won: ${winningNum} is a black number`);
                                                    }
                                                } else if (bet.bet_description.includes('Even') && winningNum > 0 && winningNum % 2 === 0) {
                                                    isWinningBet = true;
                                                    console.log(`Even Numbers bet won: ${winningNum} is an even number`);
                                                } else if (bet.bet_description.includes('Odd') && winningNum > 0 && winningNum % 2 === 1) {
                                                    isWinningBet = true;
                                                    console.log(`Odd Numbers bet won: ${winningNum} is an odd number`);
                                                } else if (bet.bet_description.includes('1-18') && winningNum >= 1 && winningNum <= 18) {
                                                    isWinningBet = true;
                                                    console.log(`Low Numbers bet won: ${winningNum} is between 1-18`);
                                                } else if (bet.bet_description.includes('19-36') && winningNum >= 19 && winningNum <= 36) {
                                                    isWinningBet = true;
                                                    console.log(`High Numbers bet won: ${winningNum} is between 19-36`);
                                                } else {
                                                    const numberListMatch = bet.bet_description.match(/\(([\d,]+)\)/);
                                                    if (numberListMatch) {
                                                        const numberList = numberListMatch[1].split(',').map(num => parseInt(num.trim()));
                                                        if (numberList.includes(winningNum)) {
                                                            isWinningBet = true;
                                                            console.log(`Even-money bet won: ${bet.bet_description} contains winning number ${winningNum}`);
                                                        }
                                                    }
                                                }
                                                break;

                                            case 'basket':
                                                if (winningNum >= 0 && winningNum <= 3) {
                                                    isWinningBet = true;
                                                    console.log(`Basket bet won: ${winningNum} is between 0-3`);
                                                }
                                                break;

                                            case 'snake':
                                                const snakeNumbers = [1, 5, 9, 12, 14, 16, 19, 23, 27, 30, 32, 34];
                                                if (snakeNumbers.includes(winningNum)) {
                                                    isWinningBet = true;
                                                    console.log(`Snake bet won: ${winningNum} is a snake number`);
                                                }
                                                break;

                                            default:
                                                const defaultMatch = bet.bet_description.match(/\(([\d,]+)\)/);
                                                if (defaultMatch) {
                                                    const numberList = defaultMatch[1].split(',').map(num => parseInt(num.trim()));
                                                    if (numberList.includes(winningNum)) {
                                                        isWinningBet = true;
                                                        console.log(`Default case: Bet type '${bet.bet_type}' with description '${bet.bet_description}' contains winning number ${winningNum}`);
                                                    }
                                                }
                                                break;
                                        }

                                        // Update the result badge
                                        let resultBadge = '';
                                        if (isWinningBet) {
                                            resultBadge = '<span class="badge badge-success">WIN</span>';
                                        } else {
                                            resultBadge = '<span class="badge badge-danger">LOSS</span>';
                                        }

                                        // Only update if changed
                                        if (resultCell.html().trim() !== resultBadge) {
                                            resultCell.html(resultBadge);
                                            $(betRows[index]).addClass('highlight-update');
                                            setTimeout(() => {
                                                $(betRows[index]).removeClass('highlight-update');
                                            }, 3000);
                                        }
                                    }
                                });
                            }
                        }
                    });
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching betting slip statuses:', error);
        },
        complete: function() {
            // Schedule the next update
            setTimeout(updateBettingSlipStatuses, 7000); // Poll every 7 seconds
        }
    });
}

// Start the polling when the document is ready
$(document).ready(function() {
    // POS System: Browser notification permission requests disabled for cashier interface

    // Add a status update indicator to the page
    const statusIndicator = $('<div id="status-indicator" style="position: fixed; bottom: 20px; right: 20px; background-color: #28a745; color: white; padding: 8px 15px; border-radius: 20px; font-size: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); display: none; z-index: 1000;">Updating status...</div>');
    $('body').append(statusIndicator);

    // Show/hide the status indicator during updates
    $(document).ajaxStart(function() {
        $('#status-indicator').fadeIn(300);
    });

    $(document).ajaxComplete(function() {
        $('#status-indicator').fadeOut(300);
    });

    // Initial delay before starting polling
    setTimeout(updateBettingSlipStatuses, 3000);
});
