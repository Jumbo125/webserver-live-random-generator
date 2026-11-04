(function () {
  'use strict';

  const form = document.getElementById('create-form');
  const errorBox = document.getElementById('create-error');
  const submitBtn = document.getElementById('create-submit');

  function showError(message) {
    errorBox.textContent = message;
    errorBox.classList.remove('d-none');
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    errorBox.classList.add('d-none');

    const turnstileWidget = document.querySelector('.cf-turnstile');
    const turnstileInput = document.querySelector('[name="cf-turnstile-response"]');
    let turnstileToken = '';
    if (turnstileInput) {
      turnstileToken = turnstileInput.value;
    }

    if (turnstileWidget && !turnstileToken) {
      showError('Bitte zuerst die Sicherheitsabfrage abschließen.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Wird erstellt …';

    const payload = {
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
