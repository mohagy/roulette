/**
 * Login Page JavaScript
 * Handles animations, form validation, and AJAX login
 */

$(document).ready(function() {
    // DOM Elements
    const loginForm = $('#loginForm');
    const usernameInput = $('#username');
    const passwordInput = $('#password');
    const togglePasswordBtn = $('#togglePassword');
    const errorMessage = $('#errorMessage');
    const loginButton = $('#loginButton');
    const loadingOverlay = $('#loadingOverlay');


    let activeInput = null;
    let particlesInterval;

    // Initialize animations
    initAnimations();

    // Toggle password visibility
    togglePasswordBtn.on('click', function() {
        const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
        passwordInput.attr('type', type);

        // Toggle icon
        const icon = $(this).find('i');
        icon.toggleClass('fa-eye fa-eye-slash');
    });

    // Form validation
    usernameInput.on('input', function() {
        validateInput($(this), 12);
    });

    passwordInput.on('input', function() {
        validateInput($(this), 6);
    });

    // Handle form submission
    loginForm.on('submit', function(e) {
        e.preventDefault();

        // Validate form
        if (!validateForm()) {
            return false;
        }

        // Show loading overlay
        loadingOverlay.addClass('active');

        // Submit form via AJAX
        $.ajax({
            type: 'POST',
            url: 'ajax_login.php', // Use the dedicated AJAX endpoint
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Add success animation
                    loginButton.addClass('success');

                    // Redirect after a short delay
                    setTimeout(function() {
                        window.location.href = response.redirect || 'index.php';
                    }, 1000);
                } else {
                    // Hide loading overlay
                    loadingOverlay.removeClass('active');

                    // Show error message
                    showError(response.message || 'Login failed. Please try again.');

                    // Add error animation to button
                    loginButton.addClass('error');
                    setTimeout(function() {
                        loginButton.removeClass('error');
                    }, 1000);

                    console.error('Login error:', response);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading overlay
                loadingOverlay.removeClass('active');

                // Try to parse the response if it's JSON
                let errorMessage = 'Network error. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch (e) {
                    // If the response is not JSON, check if it contains HTML error messages
                    if (xhr.responseText && xhr.responseText.includes('Fatal error')) {
                        errorMessage = 'PHP Error: ' + xhr.responseText.split('<b>')[1].split('</b>')[0];
                    }
                }

                // Show error message
                showError(errorMessage);
                console.error('AJAX Error:', status, error, xhr.responseText);

                // Add error animation to button
                loginButton.addClass('error');
                setTimeout(function() {
                    loginButton.removeClass('error');
                }, 1000);
            }
        });
    });



    // Helper functions
    function validateInput(input, requiredLength) {
        const value = input.val();
        const isValid = value.length === requiredLength && /^\d+$/.test(value);

        if (value.length > 0) {
            if (isValid) {
                input.removeClass('invalid').addClass('valid');
                return true;
            } else {
                input.removeClass('valid').addClass('invalid');
                return false;
            }
        } else {
            input.removeClass('valid invalid');
            return false;
        }
    }

    function validateForm() {
        const isUsernameValid = validateInput(usernameInput, 12);
        const isPasswordValid = validateInput(passwordInput, 6);

        if (!isUsernameValid) {
            showError('Username must be exactly 12 digits');
            return false;
        }

        if (!isPasswordValid) {
            showError('Password must be exactly 6 digits');
            return false;
        }

        // Clear any previous error
        errorMessage.text('').hide();
        return true;
    }

    function showError(message) {
        errorMessage.text(message).show();
        errorMessage.css('animation', 'none');
        setTimeout(function() {
            errorMessage.css('animation', 'errorShake 0.5s ease-out');
        }, 10);
    }

    function initAnimations() {
        // Create floating particles
        createParticles();

        // Add animation classes to form elements with delay
        $('.form-group').each(function(index) {
            const delay = 100 + (index * 100);
            setTimeout(() => {
                $(this).addClass('animated');
            }, delay);
        });
    }

    function createParticles() {
        const particlesContainer = $('.particles');

        // Clear any existing particles
        particlesContainer.empty();

        // Create new particles
        for (let i = 0; i < 50; i++) {
            const particle = $('<div class="particle"></div>');
            const size = Math.random() * 5 + 1;
            const posX = Math.random() * 100;
            const posY = Math.random() * 100;
            const opacity = Math.random() * 0.5 + 0.1;
            const animationDuration = Math.random() * 20 + 10;
            const animationDelay = Math.random() * 10;

            particle.css({
                position: 'absolute',
                width: size + 'px',
                height: size + 'px',
                borderRadius: '50%',
                backgroundColor: 'rgba(255, 255, 255, ' + opacity + ')',
                left: posX + '%',
                top: posY + '%',
                animation: 'float ' + animationDuration + 's infinite ease-in-out ' + animationDelay + 's'
            });

            particlesContainer.append(particle);
        }
    }

    // Add some dynamic background effects
    $(window).on('mousemove', function(e) {
        const mouseX = e.pageX / $(window).width();
        const mouseY = e.pageY / $(window).height();

        $('.shape1').css('transform', 'translate(' + (mouseX * 20) + 'px, ' + (mouseY * 20) + 'px)');
        $('.shape2').css('transform', 'translate(' + (mouseX * -20) + 'px, ' + (mouseY * -20) + 'px)');
        $('.shape3').css('transform', 'translate(' + (mouseX * 10) + 'px, ' + (mouseY * 10) + 'px)');
        $('.shape4').css('transform', 'translate(' + (mouseX * -10) + 'px, ' + (mouseY * -10) + 'px)');
    });
});
