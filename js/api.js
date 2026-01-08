/**
 * Roulette Game API Client
 * This file handles all AJAX communication with the PHP backend
 */

// API base URL
const API_BASE_URL = './php/';

// Player API functions
const PlayerAPI = {
    /**
     * Get player information
     * @param {number} playerId - The player ID (defaults to 1)
     * @returns {Promise} - Promise with player data
     */
    getPlayer: function(playerId = 1) {
        return $.ajax({
            url: `${API_BASE_URL}player_api.php?action=get_player&player_id=${playerId}`,
            type: 'GET',
            dataType: 'json'
        });
    },
    
    /**
     * Update player balance
     * @param {number} playerId - The player ID
     * @param {number} amount - Amount to add/remove from balance
     * @returns {Promise} - Promise with updated balance
     */
    updateBalance: function(playerId, amount) {
        return $.ajax({
            url: `${API_BASE_URL}player_api.php?action=update_balance`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                player_id: playerId,
                amount: amount
            }),
            dataType: 'json'
        });
    }
};

// Game API functions
const GameAPI = {
    /**
     * Place a bet
     * @param {number} playerId - The player ID
     * @param {string} betType - Type of bet (straight, split, etc.)
     * @param {string} betNumbers - Numbers or values related to the bet
     * @param {number} betAmount - Bet amount
     * @returns {Promise} - Promise with bet result
     */
    placeBet: function(playerId, betType, betNumbers, betAmount) {
        return $.ajax({
            url: `${API_BASE_URL}game_api.php?action=place_bet`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                player_id: playerId,
                bet_type: betType,
                bet_numbers: betNumbers,
                bet_amount: betAmount
            }),
            dataType: 'json'
        });
    },
    
    /**
     * Record a game result
     * @param {number} winningNumber - The winning number
     * @param {string} winningColor - The winning color (red, black, green)
     * @returns {Promise} - Promise with game result
     */
    recordResult: function(winningNumber, winningColor) {
        return $.ajax({
            url: `${API_BASE_URL}game_api.php?action=record_result`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                winning_number: winningNumber,
                winning_color: winningColor
            }),
            dataType: 'json'
        });
    },
    
    /**
     * Complete bets and generate a betting slip
     * @param {number} playerId - The player ID
     * @param {Array} bets - Array of bet objects
     * @param {number} totalStake - Total stake amount
     * @param {number} potentialPayout - Potential payout amount
     * @returns {Promise} - Promise with betting slip
     */
    completeBets: function(playerId, bets, totalStake, potentialPayout) {
        return $.ajax({
            url: `${API_BASE_URL}game_api.php?action=complete_bets`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                player_id: playerId,
                bets: bets,
                total_stake: totalStake,
                potential_payout: potentialPayout
            }),
            dataType: 'json'
        });
    },
    
    /**
     * Get game history
     * @param {number} limit - Number of records to retrieve
     * @returns {Promise} - Promise with game history
     */
    getHistory: function(limit = 10) {
        return $.ajax({
            url: `${API_BASE_URL}game_api.php?action=get_history&limit=${limit}`,
            type: 'GET',
            dataType: 'json'
        });
    }
};

// Slip API functions
const SlipAPI = {
    /**
     * Verify a betting slip
     * @param {string} slipNumber - The slip number to verify
     * @returns {Promise} - Promise with slip verification result
     */
    verifySlip: function(slipNumber) {
        return $.ajax({
            url: `${API_BASE_URL}slip_api.php?action=verify_slip&slip_number=${slipNumber}`,
            type: 'GET',
            dataType: 'json'
        });
    },
    
    /**
     * Cancel a betting slip
     * @param {string} slipNumber - The slip number to cancel
     * @returns {Promise} - Promise with cancellation result
     */
    cancelSlip: function(slipNumber) {
        return $.ajax({
            url: `${API_BASE_URL}slip_api.php?action=cancel_slip`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                slip_number: slipNumber
            }),
            dataType: 'json'
        });
    },
    
    /**
     * Collect winnings from a slip
     * @param {string} slipNumber - The slip number to collect winnings from
     * @returns {Promise} - Promise with collection result
     */
    collectWinnings: function(slipNumber) {
        return $.ajax({
            url: `${API_BASE_URL}slip_api.php?action=collect_winnings`,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                slip_number: slipNumber
            }),
            dataType: 'json'
        });
    }
}; 