/**
 * Preset Schedule Module
 * Handles generating, loading, saving, and displaying preset schedules
 */

class PresetSchedule {
    constructor() {
        this.schedule = [];
        this.scheduleDate = null;
        this.isGenerating = false;
    }

    /**
     * Initialize the module
     */
    init() {
        console.log('📅 PresetSchedule module initialized');
        this.checkAndAutoGenerate();
    }

    /**
     * Check and auto-generate schedule if needed
     */
    async checkAndAutoGenerate() {
        try {
            const today = new Date().toISOString().split('T')[0];
            const response = await apiClient.checkPresetSchedule(today);

            if (response.exists) {
                console.log('✅ Preset schedule exists for today');
                await this.loadSchedule(today);
            } else {
                console.log('⚠️ No preset schedule found for today, will generate on demand');
            }
        } catch (error) {
            console.error('❌ Error checking preset schedule:', error);
        }
    }

    /**
     * Generate preset schedule (480 draws)
     */
    async generateSchedule(timePreset = 'auto', patternType = 'smart') {
        if (this.isGenerating) {
            console.warn('⚠️ Schedule generation already in progress');
            return;
        }

        this.isGenerating = true;
        const statusEl = Utils.$('#presetScheduleStatus');
        if (statusEl) {
            statusEl.textContent = 'Generating schedule...';
            statusEl.style.color = '#858796';
        }

        try {
            console.log('🔄 Generating 480-draw preset schedule with mathematical patterns...');

            const schedule = [];
            const today = new Date();
            // Always generate draws #1-480 for the current day (not starting from current draw)
            const startDraw = 1;

            // Track recent numbers for pattern generation (last 8 draws)
            const recentNumbers = [];

            // Generate 480 numbers (24 hours * 20 draws per hour)
            // Draw #1 = 00:00, Draw #2 = 00:03, ..., Draw #480 = 23:57
            for (let i = 0; i < 480; i++) {
                const drawNumber = startDraw + i; // Always 1-480

                // Generate number with pattern based on recent draws
                const result = this.generateSmartNumber(i, patternType, recentNumbers);
                let number = result.number;
                let pattern = result.pattern;
                const color = result.color;

                // Calculate time based on draw number (draw #1 = 00:00, draw #2 = 00:03, etc.)
                const time = this.calculateDrawTimeFromNumber(drawNumber);

                schedule.push({
                    draw_number: drawNumber,
                    number: number,
                    color: color,
                    time: time,
                    pattern: pattern  // Store the pattern/puzzle explanation
                });

                // ⚠️ CRITICAL: Validate multiple constraints to prevent abnormal patterns
                // 1. No more than 2 consecutive identical numbers
                // 2. No more than 5 same numbers per hour (20 draws per hour = 3-minute intervals)
                // 3. Daily frequency limit per number (max 25 times per day)

                let needsChange = false;
                let changeReason = '';
                const rouletteNumbers = Array.from({ length: 37 }, (_, i) => i);
                const maxDailyFrequency = 25; // Max times a number can appear in a day
                const maxHourlyFrequency = 5; // Max times a number can appear per hour (20 draws per hour)

                // Constraint 1: Check for 3+ consecutive identical numbers
                if (schedule.length >= 2) {
                    const lastTwo = schedule.slice(-2);
                    if (lastTwo[0].number === lastTwo[1].number && lastTwo[0].number === number) {
                        needsChange = true;
                        changeReason = `3+ consecutive ${number}`;
                    }
                }

                // Constraint 2: Check hourly frequency (max 5 same number per hour = last 20 draws)
                if (!needsChange && schedule.length >= 20) {
                    const hourWindow = schedule.slice(-20);
                    const countInHour = hourWindow.filter(item => item.number === number).length;
                    if (countInHour >= maxHourlyFrequency) {
                        needsChange = true;
                        changeReason = `${number} already appeared ${countInHour} times in this hour (max ${maxHourlyFrequency})`;
                    }
                }

                // Constraint 3: Check daily frequency (max 25 times per day per number)
                if (!needsChange) {
                    const countInDay = schedule.filter(item => item.number === number).length;
                    if (countInDay >= maxDailyFrequency) {
                        needsChange = true;
                        changeReason = `${number} already appeared ${countInDay} times today (max ${maxDailyFrequency})`;
                    }
                }

                if (needsChange) {
                    console.log(`⚠️ ${changeReason} at draw #${drawNumber} - changing to different number`);

                    // Find alternative numbers that meet all constraints
                    let alternativeNumbers = rouletteNumbers.filter(n => {
                        // Not the same as current number
                        if (n === number) return false;

                        // Not violating consecutive constraint
                        if (schedule.length >= 2) {
                            const lastTwo = schedule.slice(-2);
                            if (lastTwo[0].number === lastTwo[1].number && lastTwo[0].number === n) return false;
                        }

                        // Not violating hourly frequency (max 5 per hour)
                        if (schedule.length >= 20) {
                            const hourWindow = schedule.slice(-20);
                            if (hourWindow.filter(item => item.number === n).length >= maxHourlyFrequency) return false;
                        }

                        // Not violating daily frequency (max 25 per day)
                        if (schedule.filter(item => item.number === n).length >= maxDailyFrequency) return false;

                        return true;
                    });

                    // If no alternatives found, relax constraints (avoid consecutive at minimum)
                    if (alternativeNumbers.length === 0) {
                        alternativeNumbers = rouletteNumbers.filter(n => {
                            if (n === number) return false;
                            if (schedule.length >= 2) {
                                const lastTwo = schedule.slice(-2);
                                if (lastTwo[0].number === lastTwo[1].number && lastTwo[0].number === n) return false;
                            }
                            return true;
                        });
                    }

                    if (alternativeNumbers.length > 0) {
                        // Try to maintain color if possible
                        const lastColor = schedule.length > 0 ? schedule[schedule.length - 1].color : 'black';
                        const sameColorNumbers = lastColor === 'red' ?
                            [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36] :
                            (lastColor === 'black' ?
                                [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35] :
                                [0]);

                        const sameColorAlternatives = alternativeNumbers.filter(n => sameColorNumbers.includes(n));
                        if (sameColorAlternatives.length > 0) {
                            number = sameColorAlternatives[(i * 3) % sameColorAlternatives.length];
                            pattern = `Changed to avoid constraint violation (${lastColor})`;
                        } else {
                            number = alternativeNumbers[(i * 7) % alternativeNumbers.length];
                            pattern = `Changed to avoid constraint violation (${Utils.getNumberColor(number)})`;
                        }

                        console.log(`✅ Changed to ${number} (${Utils.getNumberColor(number)}) - Reason: ${changeReason}`);
                    } else {
                        console.warn(`⚠️ Could not find alternative number that meets all constraints at draw #${drawNumber}`);
                    }
                }

                // Update recent numbers (keep last 8)
                recentNumbers.push(number);
                if (recentNumbers.length > 8) {
                    recentNumbers.shift();
                }

                // Log progress every 100 draws
                if ((i + 1) % 100 === 0) {
                    console.log(`📊 Generated ${i + 1}/480 draws...`);
                }
            }

            this.schedule = schedule;
            this.scheduleDate = today.toISOString().split('T')[0];

            console.log(`💾 Saving ${schedule.length} draws to database...`);

            // Save to database (always starts at draw #1)
            await this.saveSchedule(timePreset, patternType, startDraw);

            // Display schedule
            this.displaySchedule();

            if (statusEl) {
                statusEl.textContent = `✅ Schedule generated successfully (${schedule.length} draws)`;
                statusEl.style.color = '#1cc88a';
            }

            console.log('✅ Preset schedule generated successfully');
            return schedule;
        } catch (error) {
            console.error('❌ Error generating schedule:', error);
            if (statusEl) {
                statusEl.textContent = `❌ Error: ${error.message}`;
                statusEl.style.color = '#e74a3b';
            }
            alert(`Failed to generate schedule: ${error.message}`);
            throw error;
        } finally {
            this.isGenerating = false;
        }
    }

    /**
     * Comprehensive Mathematical Pattern Generator
     * Returns: { number: int, pattern: string, color: string }
     */
    generateSmartNumber(index, patternType = 'smart', recentNumbers = []) {
        const last8 = recentNumbers.slice(-8);
        const last4 = recentNumbers.slice(-4);
        const last3 = recentNumbers.slice(-3);
        const last2 = recentNumbers.slice(-2);
        const last1 = recentNumbers[recentNumbers.length - 1];

        const patterns = [];
        const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        const blackNumbers = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

        // Helper function to get color
        const getColor = (num) => Utils.getNumberColor(num);

        // Define isRed and isBlack directly to avoid scope issues
        const redNumbersList = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        const isRed = (num) => {
            if (num === 0) return false;
            return redNumbersList.includes(num);
        };
        const isBlack = (num) => {
            if (num === 0) return false;
            return !redNumbersList.includes(num);
        };

        // ============================================
        // COLOR-BASED PATTERNS (Red/Black/Green)
        // ============================================

        if (last1 !== undefined) {
            const lastColor = getColor(last1);

            // Pattern 1: Alternate Red/Black
            if (lastColor === 'red') {
                const nextBlack = blackNumbers[index % blackNumbers.length];
                patterns.push({
                    number: nextBlack,
                    pattern: `Color alternate: Last was Red (${last1}), next is Black (${nextBlack})`
                });
            } else if (lastColor === 'black') {
                const nextRed = redNumbers[index % redNumbers.length];
                patterns.push({
                    number: nextRed,
                    pattern: `Color alternate: Last was Black (${last1}), next is Red (${nextRed})`
                });
            }

            // Pattern 2: Same color as last
            if (lastColor === 'red') {
                const sameRed = redNumbers[(redNumbers.indexOf(last1) + 1) % redNumbers.length];
                patterns.push({
                    number: sameRed,
                    pattern: `Same color (Red): ${sameRed}`
                });
            } else if (lastColor === 'black') {
                const sameBlack = blackNumbers[(blackNumbers.indexOf(last1) + 1) % blackNumbers.length];
                patterns.push({
                    number: sameBlack,
                    pattern: `Same color (Black): ${sameBlack}`
                });
            }

            // Pattern 3: Opposite color
            if (lastColor === 'red') {
                const oppBlack = blackNumbers[Math.floor((last1 + index) / 2) % blackNumbers.length];
                patterns.push({
                    number: oppBlack,
                    pattern: `Opposite color: Red → Black (${oppBlack})`
                });
            } else if (lastColor === 'black') {
                const oppRed = redNumbers[Math.floor((last1 + index) / 2) % redNumbers.length];
                patterns.push({
                    number: oppRed,
                    pattern: `Opposite color: Black → Red (${oppRed})`
                });
            }
        }

        // Pattern 4: Count red vs black in last 4
        if (last4.length >= 4) {
            const redCount = last4.filter(n => isRed(n)).length;
            const blackCount = last4.filter(n => isBlack(n)).length;

            if (redCount > blackCount) {
                const nextBlack = blackNumbers[index % blackNumbers.length];
                patterns.push({
                    number: nextBlack,
                    pattern: `Color balance: ${redCount} Red vs ${blackCount} Black → Black (${nextBlack})`
                });
            } else if (blackCount > redCount) {
                const nextRed = redNumbers[index % redNumbers.length];
                patterns.push({
                    number: nextRed,
                    pattern: `Color balance: ${blackCount} Black vs ${redCount} Red → Red (${nextRed})`
                });
            }
        }

        // Pattern 5: Sum of red numbers in last 3
        if (last3.length >= 3) {
            const redNums = last3.filter(n => isRed(n));
            if (redNums.length > 0) {
                const redSum = redNums.reduce((a, b) => a + b, 0) % 37;
                patterns.push({
                    number: redSum,
                    pattern: `Sum of Red numbers [${redNums.join(',')}] = ${redSum}`
                });
            }
        }

        // Pattern 6: Sum of black numbers in last 3
        if (last3.length >= 3) {
            const blackNums = last3.filter(n => isBlack(n));
            if (blackNums.length > 0) {
                const blackSum = blackNums.reduce((a, b) => a + b, 0) % 37;
                patterns.push({
                    number: blackSum,
                    pattern: `Sum of Black numbers [${blackNums.join(',')}] = ${blackSum}`
                });
            }
        }

        // Pattern 7: Color streak breaker (if 3 same colors in a row)
        if (last3.length >= 3) {
            const colors = last3.map(n => getColor(n));
            if (colors[0] === colors[1] && colors[1] === colors[2] && colors[0] !== 'green') {
                const oppColor = colors[0] === 'red' ? 'black' : 'red';
                const oppNumbers = oppColor === 'red' ? redNumbers : blackNumbers;
                const streakBreaker = oppNumbers[index % oppNumbers.length];
                patterns.push({
                    number: streakBreaker,
                    pattern: `Color streak breaker: 3 ${colors[0]} → ${oppColor} (${streakBreaker})`
                });
            }
        }

        // Pattern 8: Green (0) after 4 same color
        if (last4.length >= 4) {
            const allSameColor = last4.every(n => getColor(n) === getColor(last4[0])) && getColor(last4[0]) !== 'green';
            if (allSameColor) {
                patterns.push({
                    number: 0,
                    pattern: 'Green (0) after 4 same color'
                });
            }
        }

        // Pattern 9: Red/Black alternation pattern
        if (last4.length >= 4) {
            const colors = last4.map(n => getColor(n));
            const isAlternating = colors[0] !== colors[1] &&
                colors[1] !== colors[2] &&
                colors[2] !== colors[3] &&
                colors[0] !== 'green' && colors[1] !== 'green';
            if (isAlternating) {
                const nextColor = colors[3] === 'red' ? 'black' : 'red';
                const nextNumbers = nextColor === 'red' ? redNumbers : blackNumbers;
                const altNum = nextNumbers[index % nextNumbers.length];
                patterns.push({
                    number: altNum,
                    pattern: `Color alternation: ${nextColor} (${altNum})`
                });
            }
        }

        // Pattern 10: Red/Black pairs (1-2, 3-4, etc.)
        if (last2.length >= 2 && last1 !== undefined) {
            if (isRed(last1)) {
                const redIndex = redNumbers.indexOf(last1);
                if (redIndex !== -1 && redIndex < blackNumbers.length) {
                    const pairBlack = blackNumbers[redIndex];
                    patterns.push({
                        number: pairBlack,
                        pattern: `Red/Black pair: ${last1} (Red) → ${pairBlack} (Black)`
                    });
                }
            } else if (isBlack(last1)) {
                const blackIndex = blackNumbers.indexOf(last1);
                if (blackIndex !== -1 && blackIndex < redNumbers.length) {
                    const pairRed = redNumbers[blackIndex];
                    patterns.push({
                        number: pairRed,
                        pattern: `Black/Red pair: ${last1} (Black) → ${pairRed} (Red)`
                    });
                }
            }
        }

        // ============================================
        // BASIC ARITHMETIC PATTERNS (Last 2 numbers)
        // ============================================

        if (last2.length >= 2) {
            // Pattern 11: Simple Addition
            const sum = (last2[0] + last2[1]) % 37;
            patterns.push({
                number: sum,
                pattern: `${last2[0]} + ${last2[1]} = ${sum} (${getColor(sum)})`
            });

            // Pattern 12: Subtraction
            const diff = Math.abs(last2[0] - last2[1]) % 37;
            patterns.push({
                number: diff,
                pattern: `|${last2[0]} - ${last2[1]}| = ${diff} (${getColor(diff)})`
            });

            // Pattern 13: Multiplication
            const mult = (last2[0] * last2[1]) % 37;
            patterns.push({
                number: mult,
                pattern: `(${last2[0]} × ${last2[1]}) mod 37 = ${mult} (${getColor(mult)})`
            });

            // Pattern 14: Division (rounded down)
            if (last2[1] !== 0) {
                const div = Math.floor(last2[0] / last2[1]) % 37;
                patterns.push({
                    number: div,
                    pattern: `⌊${last2[0]} ÷ ${last2[1]}⌋ = ${div} (${getColor(div)})`
                });
            }

            // Pattern 15: Average
            const avg = Math.round((last2[0] + last2[1]) / 2) % 37;
            patterns.push({
                number: avg,
                pattern: `Avg(${last2[0]}, ${last2[1]}) = ${avg} (${getColor(avg)})`
            });

            // Pattern 16: Sum with color preference
            const sumColor = (last2[0] + last2[1]) % 37;
            const lastColor = getColor(last2[1]);
            if (getColor(sumColor) !== lastColor && lastColor !== 'green') {
                const sameColorNums = lastColor === 'red' ? redNumbers : blackNumbers;
                const closest = sameColorNums.reduce((a, b) =>
                    Math.abs(a - sumColor) < Math.abs(b - sumColor) ? a : b
                );
                patterns.push({
                    number: closest,
                    pattern: `Sum ${sumColor} → Nearest ${lastColor}: ${closest}`
                });
            }
        }

        // ============================================
        // THREE NUMBER PATTERNS
        // ============================================

        if (last3.length >= 3) {
            // Pattern 17: Sum of last 3
            const sum3 = (last3[0] + last3[1] + last3[2]) % 37;
            patterns.push({
                number: sum3,
                pattern: `${last3[0]} + ${last3[1]} + ${last3[2]} = ${sum3} (${getColor(sum3)})`
            });

            // Pattern 18: Average of last 3
            const avg3 = Math.round((last3[0] + last3[1] + last3[2]) / 3) % 37;
            patterns.push({
                number: avg3,
                pattern: `Avg(${last3[0]}, ${last3[1]}, ${last3[2]}) = ${avg3} (${getColor(avg3)})`
            });

            // Pattern 19: Fibonacci-like
            const fib = (last3[1] + last3[2]) % 37;
            patterns.push({
                number: fib,
                pattern: `Fibonacci: ${last3[1]} + ${last3[2]} = ${fib} (${getColor(fib)})`
            });

            // Pattern 20: Color-based sum (sum of numbers with same color as last)
            const lastColor = getColor(last3[2]);
            const sameColorNums = last3.filter(n => getColor(n) === lastColor);
            if (sameColorNums.length >= 2) {
                const colorSum = sameColorNums.reduce((a, b) => a + b, 0) % 37;
                patterns.push({
                    number: colorSum,
                    pattern: `Sum of ${lastColor} numbers = ${colorSum}`
                });
            }

            // Pattern 21: First + Last
            const firstLast = (last3[0] + last3[2]) % 37;
            patterns.push({
                number: firstLast,
                pattern: `First + Last: ${last3[0]} + ${last3[2]} = ${firstLast}`
            });

            // Pattern 22: Middle number
            const middle = last3[1];
            patterns.push({
                number: middle,
                pattern: `Middle of last 3: ${middle}`
            });
        }

        // ============================================
        // FOUR NUMBER PATTERNS
        // ============================================

        if (last4.length >= 4) {
            // Pattern 23: Sum of last 4
            const sum4 = (last4[0] + last4[1] + last4[2] + last4[3]) % 37;
            patterns.push({
                number: sum4,
                pattern: `Sum of last 4: ${last4[0]}+${last4[1]}+${last4[2]}+${last4[3]} = ${sum4}`
            });

            // Pattern 24: Average of last 4
            const avg4 = Math.round((last4[0] + last4[1] + last4[2] + last4[3]) / 4) % 37;
            patterns.push({
                number: avg4,
                pattern: `Avg of last 4 = ${avg4}`
            });

            // Pattern 25: First + Last of 4
            const fl4 = (last4[0] + last4[3]) % 37;
            patterns.push({
                number: fl4,
                pattern: `First + Last (4): ${last4[0]} + ${last4[3]} = ${fl4}`
            });

            // Pattern 26: Middle two sum
            const mid2 = (last4[1] + last4[2]) % 37;
            patterns.push({
                number: mid2,
                pattern: `Middle two: ${last4[1]} + ${last4[2]} = ${mid2}`
            });

            // Pattern 27: Min of last 4
            const min4 = Math.min(...last4);
            patterns.push({
                number: min4,
                pattern: `Min of last 4 = ${min4}`
            });

            // Pattern 28: Max of last 4
            const max4 = Math.max(...last4);
            patterns.push({
                number: max4,
                pattern: `Max of last 4 = ${max4}`
            });

            // Pattern 29: Range (max - min)
            const range = (Math.max(...last4) - Math.min(...last4)) % 37;
            patterns.push({
                number: range,
                pattern: `Range of last 4 = ${range}`
            });
        }

        // ============================================
        // SPECIAL NUMBER PATTERNS
        // ============================================

        if (last8.length >= 3) {
            // Pattern 30: Zero after 3 non-zero
            const last3NonZero = last8.slice(-3).filter(n => n !== 0);
            if (last3NonZero.length === 3 && last1 !== 0) {
                patterns.push({
                    number: 0,
                    pattern: 'Green (0) after 3 non-zero numbers'
                });
            }
        }

        // Pattern 31: Multiples of 10 (10, 20, 30) - all red!
        if (last8.length >= 2) {
            const tens = [10, 20, 30];
            const lastTens = last8.filter(n => tens.includes(n));
            if (lastTens.length > 0) {
                const lastTen = lastTens[lastTens.length - 1];
                const tenIndex = tens.indexOf(lastTen);
                const nextTen = tens[(tenIndex + 1) % tens.length];
                patterns.push({
                    number: nextTen,
                    pattern: `10/20/30 pattern (Red): ${nextTen}`
                });
            }
        }

        // Pattern 32: Multiples of 5
        if (last4.length >= 2) {
            const fives = [5, 10, 15, 20, 25, 30, 35];
            const lastFives = last4.filter(n => fives.includes(n));
            if (lastFives.length > 0) {
                const lastFive = lastFives[lastFives.length - 1];
                const fiveIndex = fives.indexOf(lastFive);
                const nextFive = fives[(fiveIndex + 1) % fives.length];
                patterns.push({
                    number: nextFive,
                    pattern: `Multiples of 5: ${nextFive}`
                });
            }
        }

        // ============================================
        // DIGIT-BASED PATTERNS
        // ============================================

        if (last1 !== undefined && last1 > 0) {
            // Pattern 33: Sum of digits
            const digits = last1.toString().split('').map(Number);
            const sumDigits = digits.reduce((a, b) => a + b, 0) % 37;
            patterns.push({
                number: sumDigits,
                pattern: `Sum of digits(${last1}) = ${sumDigits} (${getColor(sumDigits)})`
            });

            // Pattern 34: Product of digits
            if (last1 > 9) {
                const prodDigits = digits.reduce((a, b) => a * b, 1) % 37;
                patterns.push({
                    number: prodDigits,
                    pattern: `Product of digits(${last1}) = ${prodDigits}`
                });
            }

            // Pattern 35: Last digit
            const lastDigit = last1 % 10;
            if (lastDigit > 0) {
                patterns.push({
                    number: lastDigit,
                    pattern: `Last digit of ${last1} = ${lastDigit} (${getColor(lastDigit)})`
                });
            }

            // Pattern 36: First digit (if > 9)
            if (last1 > 9) {
                const firstDigit = Math.floor(last1 / 10);
                patterns.push({
                    number: firstDigit,
                    pattern: `First digit of ${last1} = ${firstDigit}`
                });
            }
        }

        // ============================================
        // SEQUENCE PATTERNS
        // ============================================

        if (last3.length >= 3) {
            // Pattern 37: Arithmetic progression
            const diff1 = last3[1] - last3[0];
            const diff2 = last3[2] - last3[1];
            if (diff1 === diff2) {
                const next = (last3[2] + diff1 + 37) % 37;
                patterns.push({
                    number: next,
                    pattern: `Arithmetic: ${last3[2]} + ${diff1} = ${next}`
                });
            }
        }

        // ============================================
        // MODULO PATTERNS
        // ============================================

        if (last2.length >= 2) {
            // Pattern 38: Sum mod 10
            const mod10 = ((last2[0] + last2[1]) % 10) % 37;
            patterns.push({
                number: mod10,
                pattern: `(${last2[0]} + ${last2[1]}) mod 10 = ${mod10}`
            });
        }

        // ============================================
        // COMBINATION PATTERNS
        // ============================================

        if (last4.length >= 4) {
            // Pattern 39: Weighted average
            const weighted = Math.round((last4[0] * 1 + last4[1] * 2 + last4[2] * 3 + last4[3] * 4) / 10) % 37;
            patterns.push({
                number: weighted,
                pattern: `Weighted avg (1:2:3:4) = ${weighted}`
            });
        }

        // Pattern 40: Complementary number (36 - last)
        if (last1 !== undefined) {
            const complement = (36 - last1) % 37;
            patterns.push({
                number: complement,
                pattern: `Complement of ${last1}: 36 - ${last1} = ${complement}`
            });
        }

        // ============================================
        // FREQUENCY PATTERNS
        // ============================================

        if (last8.length >= 5) {
            // Pattern 41: Most frequent in last 5
            const freq = {};
            last8.slice(-5).forEach(n => freq[n] = (freq[n] || 0) + 1);
            const mostFreq = Object.keys(freq).reduce((a, b) => freq[a] > freq[b] ? a : b);
            patterns.push({
                number: parseInt(mostFreq),
                pattern: `Most frequent in last 5 = ${mostFreq}`
            });
        }

        // ============================================
        // FALLBACK PATTERNS (if no history)
        // ============================================

        if (patterns.length === 0 || index < 8) {
            // Pattern 42: Base mathematical pattern
            const basePattern = (index * 7 + 13) % 37;
            patterns.push({
                number: basePattern,
                pattern: `Base: (${index} × 7 + 13) mod 37 = ${basePattern} (${getColor(basePattern)})`
            });

            // Pattern 43: Prime-based
            const primeBase = (index * 11 + 17) % 37;
            patterns.push({
                number: primeBase,
                pattern: `Prime base: (${index} × 11 + 17) mod 37 = ${primeBase} (${getColor(primeBase)})`
            });
        }

        // ============================================
        // SELECT PATTERN (rotate through available)
        // ============================================

        const patternIndex = index % patterns.length;
        const selectedPattern = patterns[patternIndex];
        const finalNumber = Math.abs(selectedPattern.number) % 37;
        const finalColor = getColor(finalNumber);

        return {
            number: finalNumber,
            pattern: selectedPattern.pattern,
            color: finalColor
        };
    }

    /**
     * Calculate draw time based on index
     */
    /**
     * Calculate draw time from draw number (draw #1 = 00:00, draw #2 = 00:03, etc.)
     */
    calculateDrawTimeFromNumber(drawNumber) {
        // Draw #1 = 00:00, Draw #2 = 00:03, ..., Draw #480 = 23:57
        // Each draw is 3 minutes apart, starting from midnight
        const totalMinutes = (drawNumber - 1) * 3;
        const hours = Math.floor(totalMinutes / 60) % 24;
        const minutes = totalMinutes % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    }

    /**
     * Calculate draw time from index (for backward compatibility)
     */
    calculateDrawTime(index) {
        // Convert index to draw number (index 0 = draw #1)
        const drawNumber = index + 1;
        return this.calculateDrawTimeFromNumber(drawNumber);
    }

    /**
     * Get current draw number
     */
    async getCurrentDrawNumber() {
        try {
            const drawInfo = await apiClient.getDrawInfo();
            // API returns 'current_draw' not 'current_draw_number'
            const currentDraw = drawInfo?.data?.current_draw ||
                drawInfo?.data?.current_draw_number ||
                1;
            console.log('📊 Current draw number:', currentDraw);
            return currentDraw;
        } catch (error) {
            console.error('❌ Error getting current draw number:', error);
            return 1; // Default to 1 if error
        }
    }

    /**
     * Save schedule to database
     */
    async saveSchedule(timePreset, patternType, startDrawNumber) {
        try {
            const endDrawNumber = startDrawNumber + this.schedule.length - 1;

            // Extract numbers as a simple array (API expects JSON array of numbers)
            const numbersArray = this.schedule.map(s => s.number);

            // Extract patterns array (pattern explanations for each draw)
            const patternsArray = this.schedule.map(s => s.pattern || 'Base pattern');

            console.log('💾 Saving schedule with patterns:', {
                schedule_date: this.scheduleDate,
                start_draw_number: startDrawNumber,
                end_draw_number: endDrawNumber,
                total_draws: this.schedule.length,
                numbers_count: numbersArray.length,
                patterns_count: patternsArray.length
            });

            const scheduleData = {
                schedule_date: this.scheduleDate,
                start_draw_number: startDrawNumber,
                end_draw_number: endDrawNumber,
                time_preset: timePreset,
                pattern_type: patternType,
                schedule_data: JSON.stringify(numbersArray), // JSON string of numbers array
                pattern_data: JSON.stringify(patternsArray), // JSON string of pattern explanations
                total_draws: this.schedule.length
            };

            const response = await apiClient.savePresetSchedule(scheduleData);

            console.log('💾 Save response:', response);

            if (response.status === 'success') {
                console.log('✅ Schedule saved to database with patterns');
                return response;
            } else {
                throw new Error(response.message || 'Failed to save schedule');
            }
        } catch (error) {
            console.error('❌ Error saving schedule:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            throw error;
        }
    }

    /**
     * Load schedule from database
     */
    async loadSchedule(date = null) {
        try {
            const scheduleDate = date || new Date().toISOString().split('T')[0];
            console.log(`📥 Loading preset schedule for ${scheduleDate}...`);

            const response = await apiClient.loadPresetSchedule(scheduleDate);

            if (response.status === 'success' && response.data) {
                const scheduleData = JSON.parse(response.data.schedule_data || '[]');

                // Parse pattern_data if available (handle NULL for old schedules)
                let patternsData = [];
                if (response.data.pattern_data) {
                    try {
                        patternsData = JSON.parse(response.data.pattern_data);
                    } catch (e) {
                        console.warn('⚠️ Failed to parse pattern_data:', e);
                        patternsData = [];
                    }
                }

                const startDraw = response.data.start_draw_number || 1;

                // ALWAYS remap schedule to start at draw #1, regardless of what startDraw is
                // Take exactly 480 items (or all if less than 480) and remap to #1-480
                const maxItems = Math.min(480, scheduleData.length);

                this.schedule = scheduleData.slice(0, maxItems).map((number, index) => {
                    const drawNumber = index + 1; // Always start at #1
                    return {
                        draw_number: drawNumber,
                        number: number,
                        color: Utils.getNumberColor(number),
                        time: this.calculateDrawTimeFromNumber(drawNumber), // Calculate time based on draw #1-480
                        pattern: patternsData[index] || 'Base pattern'
                    };
                });

                // Warn if schedule was remapped from a different starting number
                if (startDraw !== 1) {
                    console.warn(`⚠️ Schedule was remapped from starting at draw #${startDraw} to starting at draw #1`);
                }

                // Warn if schedule has fewer than 480 draws
                if (this.schedule.length < 480) {
                    console.warn(`⚠️ Schedule only has ${this.schedule.length} draws (expected 480)`);
                }

                // Ensure all draws are in valid range (1-480)
                const invalidDraws = this.schedule.filter(item => item.draw_number < 1 || item.draw_number > 480);
                if (invalidDraws.length > 0) {
                    console.error(`❌ Schedule contains ${invalidDraws.length} invalid draws (outside 1-480 range)`);
                    // Filter out invalid draws
                    this.schedule = this.schedule.filter(item => item.draw_number >= 1 && item.draw_number <= 480);
                }

                this.scheduleDate = scheduleDate;

                // Log warning if schedule was remapped
                if (startDraw !== 1 && this.schedule.length > 0) {
                    console.warn(`⚠️ Schedule was remapped from starting at draw #${startDraw} to starting at draw #1`);
                    const statusEl = Utils.$('#presetScheduleStatus');
                    if (statusEl) {
                        statusEl.innerHTML = `⚠️ Schedule remapped (was starting at #${startDraw}, now #1-${this.schedule.length}). <strong>Please regenerate for accurate times.</strong>`;
                        statusEl.style.color = '#f39c12';
                    }
                } else if (this.schedule.length > 0) {
                    // Update status to show correct count
                    const statusEl = Utils.$('#presetScheduleStatus');
                    if (statusEl) {
                        statusEl.innerHTML = `✅ Schedule loaded (${this.schedule.length} draws, #1-${this.schedule.length})`;
                        statusEl.style.color = '#1cc88a';
                    }
                }

                // Log the first and last draw numbers for debugging
                if (this.schedule.length > 0) {
                    console.log(`📊 Schedule loaded: First draw = #${this.schedule[0].draw_number}, Last draw = #${this.schedule[this.schedule.length - 1].draw_number}`);
                }

                this.displaySchedule();

                console.log(`✅ Schedule loaded successfully (${this.schedule.length} draws, ${patternsData.length} patterns)`);
                return this.schedule;
            } else {
                throw new Error(response.message || 'Failed to load schedule');
            }
        } catch (error) {
            console.error('❌ Error loading schedule:', error);
            throw error;
        }
    }

    /**
     * Display schedule in table (filtered to show only relevant draws #1-480)
     */
    async displaySchedule() {
        const tableBody = Utils.$('#presetScheduleTable tbody');
        if (!tableBody) return;

        tableBody.innerHTML = '';

        if (!this.schedule || this.schedule.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No schedule generated</td></tr>';
            return;
        }

        // Filter schedule to only show draws #1-480 (valid daily draws)
        const validSchedule = this.schedule.filter(item => {
            return item.draw_number >= 1 && item.draw_number <= 480;
        });

        if (validSchedule.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-warning">⚠️ Schedule contains invalid draw numbers. Please regenerate the schedule.</td></tr>';
            return;
        }

        // Get current draw number to filter the display
        let currentDraw = 1;
        try {
            const drawInfo = await apiClient.getDrawInfo();
            currentDraw = drawInfo?.data?.current_draw ||
                drawInfo?.data?.current_draw_number ||
                1;

            // Ensure current draw is within valid range (1-480)
            if (currentDraw < 1 || currentDraw > 480) {
                console.warn('⚠️ Current draw number out of range, resetting to 1');
                currentDraw = 1;
            }
        } catch (error) {
            console.warn('⚠️ Could not get current draw number, showing all draws:', error);
        }

        // Filter: Show last 5 completed draws + next 20 upcoming draws
        const filteredSchedule = validSchedule.filter(item => {
            // Show draws within range: (currentDraw - 5) to (currentDraw + 20)
            const minDraw = Math.max(1, currentDraw - 5);
            const maxDraw = Math.min(480, currentDraw + 20);
            return item.draw_number >= minDraw && item.draw_number <= maxDraw;
        });

        // If no filtered results, show first 25 valid draws
        const displaySchedule = filteredSchedule.length > 0 ? filteredSchedule : validSchedule.slice(0, 25);

        displaySchedule.forEach((item) => {
            const row = Utils.createElement('tr');
            const isCurrent = item.draw_number === currentDraw;
            const isPast = item.draw_number < currentDraw;
            const rowClass = isCurrent ? 'table-warning' : isPast ? 'table-secondary' : '';

            row.className = rowClass;
            row.innerHTML = `
                <td>${item.draw_number}${isCurrent ? ' <span class="badge bg-warning text-dark">Current</span>' : ''}</td>
                <td>${item.time}</td>
                <td>
                    <span class="number-badge ${item.color}">${item.number}</span>
                </td>
                <td class="text-muted small" style="font-size: 0.85rem;">
                    ${item.pattern || 'Base pattern'}
                </td>
            `;
            tableBody.appendChild(row);
        });

        // Show summary if filtered or if schedule has invalid draws
        const hasInvalidDraws = this.schedule.some(item => item.draw_number < 1 || item.draw_number > 480);
        if (hasInvalidDraws) {
            const warningRow = Utils.createElement('tr');
            warningRow.className = 'table-warning';
            warningRow.innerHTML = `
                <td colspan="4" class="text-center text-warning">
                    ⚠️ Schedule contains draws outside valid range (1-480). Showing only valid draws. Please regenerate the schedule.
                </td>
            `;
            tableBody.appendChild(warningRow);
        } else if (filteredSchedule.length < validSchedule.length) {
            const summaryRow = Utils.createElement('tr');
            summaryRow.className = 'table-info';
            summaryRow.innerHTML = `
                <td colspan="4" class="text-center text-muted small">
                    Showing ${filteredSchedule.length} of ${validSchedule.length} draws (around current draw #${currentDraw})
                </td>
            `;
            tableBody.appendChild(summaryRow);
        }
    }

    /**
     * Get preset number for a draw
     */
    getPresetNumber(drawNumber) {
        const item = this.schedule.find(s => s.draw_number === drawNumber);
        return item ? item.number : null;
    }

    /**
     * Get schedule
     */
    getSchedule() {
        return this.schedule;
    }
}

// Create global instance
const presetSchedule = new PresetSchedule();

