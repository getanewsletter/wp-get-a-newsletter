window.addEventListener('load', function() {
    if (document.querySelectorAll('.gan-shortcode-container').length > 0) {
        document.querySelectorAll('.gan-shortcode-container').forEach(element => {
            element.addEventListener('click', () => {
            navigator.clipboard.writeText(element.textContent);
        });
    });
}

    if (document.querySelector('.gan-advanced-settings-btn')) {
        document.querySelector('.gan-advanced-settings-btn').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('#gan-settings-confirmation').style.display = 'block';
            document.querySelector('#gan-settings-form-settings').style.display = 'block';
            this.style.display = 'none';
        });
    }

    if (document.querySelector('#gan-settings-form-name')) {
        const requiredFields = [
            {
                name: 'name',
                label: 'Form name'
            },
            {
                name: 'list',
                label: 'List'
            },
            {
                name: 'sender_id',
                label: 'Sender'
            },
            {
                name: 'confirmation_email_subject',
                label: 'Confirmation email subject'
            },
            {
                name: 'confirmation_email_message',
                label: 'Confirmation email message'
            },
            {
                name: 'button_text',
                label: 'Button text'
            }
        ];

        function createErrorMessage(message) {
            const error = document.createElement('div');
            error.className = 'notice notice-error inline';
            error.style.margin = '5px 0';
            error.innerHTML = `<p>${message}</p>`;
            return error;
        }

        function validateField(field) {
            const element = document.querySelector(`[name="${field.name}"]`);
            const existingError = element.parentElement.querySelector('.notice-error');
            
            if (existingError) {
                existingError.remove();
            }

            if (!element.value.trim()) {
                const error = createErrorMessage(`${field.label} is required.`);
                if (element.tagName.toLowerCase() === 'select') {
                    element.parentElement.appendChild(error);
                } else {
                    element.insertAdjacentElement('afterend', error);
                }
                return false;
            }
            return true;
        }

        requiredFields.forEach(field => {
            const element = document.querySelector(`[name="${field.name}"]`);
            if (element) {
                element.addEventListener('blur', () => validateField(field));
            }
        });

        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            let isValid = true;

            requiredFields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                e.preventDefault();
                const existingTopError = document.querySelector('.gan-settings-page > .notice');
                if (!existingTopError) {
                    const topError = createErrorMessage('Please fill in all required fields.');
                    topError.className = 'notice notice-error';
                    topError.style.margin = '20px 0';
                    form.parentElement.insertBefore(topError, form);
                    
                    topError.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    
                }
            }
        });
    }

    if (document.querySelector('.gan-onboarding-form')) {
        let checkmarkSVG = `<svg xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 50 50" width="50px" height="50px"><path d="M 41.9375 8.625 C 41.273438 8.648438 40.664063 9 40.3125 9.5625 L 21.5 38.34375 L 9.3125 27.8125 C 8.789063 27.269531 8.003906 27.066406 7.28125 27.292969 C 6.5625 27.515625 6.027344 28.125 5.902344 28.867188 C 5.777344 29.613281 6.078125 30.363281 6.6875 30.8125 L 20.625 42.875 C 21.0625 43.246094 21.640625 43.410156 22.207031 43.328125 C 22.777344 43.242188 23.28125 42.917969 23.59375 42.4375 L 43.6875 11.75 C 44.117188 11.121094 44.152344 10.308594 43.78125 9.644531 C 43.410156 8.984375 42.695313 8.589844 41.9375 8.625 Z"/></svg>`;

        document.querySelector('.gan-onboarding-form').addEventListener('submit', function(e) {
            e.preventDefault();
            document.querySelector('.gan-result-message').innerHTML = '';

            let token = document.querySelector('.gan-onboarding-form #token').value.trim();

            fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'gan_register_admin_api_key',
                    token: token
                })
            })
                .then(response => response.json())
                .then((data) => {
                    if (data.success === false) {
                        let errorMessage = data.message;

                        document.querySelector('.gan-result-message').innerHTML = `<div class="notice notice-error inline"><p></p></div>`;
                        document.querySelector('.gan-result-message .notice-error > p').textContent = errorMessage;
                        return;
                    }

                    Array.from(document.querySelectorAll('.gan-onboarding-step-counter')).forEach(el => {
                        el.classList.add('success');
                        el.innerHTML = checkmarkSVG;
                    });

                    document.querySelector('.gan-result-message').innerHTML = `<div class="gan-success-message"></div>`;
                    document.querySelector('.gan-result-message .gan-success-message').innerHTML = `<div class="gan-checkmark-container">` + checkmarkSVG + `</div>` + `<span>Your API token is active and working</span>`;
                    document.querySelector('.gan-result-message').innerHTML += `<a class="button button-primary" href="/wp-admin/admin.php?page=newsletter_subscription_forms">Continue to forms</a>`;

                    document.querySelector('.gan-onboarding-form').remove();
                })
                .catch(error => console.error('Error:', error))
        });
    }
});