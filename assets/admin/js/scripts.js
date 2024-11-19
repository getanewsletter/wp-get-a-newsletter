window.addEventListener('load', function() {
  document.querySelectorAll('.gan-shortcode-container').forEach(element => {
    element.addEventListener('click', () => {
        navigator.clipboard.writeText(element.textContent);
    });
  });

  document.querySelector('.gan-advanced-settings-btn').addEventListener('click', function(e) {
    e.preventDefault();
    document.querySelector('#gan-settings-confirmation').style.display = 'block';
    document.querySelector('#gan-settings-form-settings').style.display = 'block';
    this.style.display = 'none';
  });
});