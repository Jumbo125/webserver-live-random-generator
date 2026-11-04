(function () {
  'use strict';

  var form = document.getElementById('create-form');
  var errorBox = document.getElementById('create-error');
  var submitBtn = document.getElementById('create-submit');

  function showError(message) {
    errorBox.textContent = message;
    errorBox.classList.remove('d-none');
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.classList.add('d-none');

    var turnstileWidget = document.querySelector('.cf-turnstile');
    var turnstileInput = document.querySelector('[name="cf-turnstile-response"]');
    var turnstileToken = turnstileInput ? turnstileInput.value : '';

    if (turnstileWidget && !turnstileToken) {
      showError('Bitte zuerst die Sicherheitsabfrage abschließen.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird erstellt …';

    var payload = {
      title: document.getElementById('title').value,
      min: parseInt(document.getElementById('min').value, 10),
      max: parseInt(document.getElementById('max').value, 10),
      duration_s: parseFloat(document.getElementById('duration').value),
      expires_in_hours: parseInt(document.getElementById('expires').value, 10),
      turnstile_token: turnstileToken
    };

    fetch('api/create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error(data.error || 'Erstellung fehlgeschlagen.');
          }
          return data;
        });
      })
      .then(function (data) {
        window.location.href = 'control.php?token=' + encodeURIComponent(data.creator_token);
      })
      .catch(function (err) {
        showError(err.message || 'Erstellung fehlgeschlagen.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Link & QR-Code erstellen';
      });
  });
})();
