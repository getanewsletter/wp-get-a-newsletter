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
});