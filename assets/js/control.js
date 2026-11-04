(function () {
  'use strict';

  const data = window.CONTROL_DATA;
  const viewerUrl = new URL('live.php?token=' + encodeURIComponent(data.viewerToken), window.location.href).toString();

  const urlInput = document.getElementById('viewer-url');
  urlInput.value = viewerUrl;

  const qr = qrcode(0, 'M');
  qr.addData(viewerUrl);
  qr.make();
  document.getElementById('qr-container').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4 });

  document.getElementById('copy-link-btn').addEventListener('click', function () {
    const btn = this;
    navigator.clipboard.writeText(viewerUrl).then(function () {
      const original = btn.textContent;
      btn.textContent = 'Kopiert!';
      window.setTimeout(function () { btn.textContent = original; }, 1500);
    }).catch(function () {
      urlInput.select();
    });
  });

  const startBtn = document.getElementById('start-btn');
  const startError = document.getElementById('start-error');
  const excludedInput = document.getElementById('excluded-numbers');
  const excludedEnabled = document.getElementById('excluded-enabled');

  excludedInput.disabled = !excludedEnabled.checked;
  excludedEnabled.addEventListener('change', function () {
    excludedInput.disabled = !excludedEnabled.checked;
  });

  // Wird von draw-view.js aufgerufen, sobald sich erkennen lässt, ob gerade
  // eine Runde läuft (Countdown + Animation). Hält den Button gesperrt bis
  // zum Ergebnis, nicht nur während der kurzen Start-Anfrage.
  window.onDrawActiveChange = function (active) {
    startBtn.disabled = active;
  };

  startBtn.addEventListener('click', function () {
    startError.classList.add('d-none');
    startBtn.disabled = true;

    let excludedValue = '';
    if (excludedEnabled.checked) {
      excludedValue = excludedInput.value;
    }

    fetch('api/start.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        creator_token: data.creatorToken,
        excluded: excludedValue
      })
    })
      .then(function (response) {
        return response.json().then(function (body) {
          if (!response.ok) {
            throw new Error(body.error || 'Ziehung konnte nicht gestartet werden.');
          }
          return body;
        });
      })
      .then(function (body) {
        if (window.drawViewInstance) {
          window.drawViewInstance.refresh();
        }
        // Gezogene Zahl automatisch zur Ausschlussliste hinzufügen, damit sie
        // bei der nächsten Ziehung nicht mehr vorkommt (Liste bleibt editierbar).
        if (excludedEnabled.checked) {
          const result = String(body.round.result);
          const current = excludedInput.value.split(/[;,\s]+/).filter(Boolean);
          if (current.indexOf(result) === -1) {
            current.push(result);
            excludedInput.value = current.join(';');
          }
        }
      })
      .catch(function (err) {
        startError.textContent = err.message;
        startError.classList.remove('d-none');
        // Start ist fehlgeschlagen: Button wieder freigeben, sonst bleibt er
        // gesperrt, obwohl keine Runde läuft.
        startBtn.disabled = false;
      });
  });

  document.getElementById('delete-btn').addEventListener('click', function () {
    if (!window.confirm('Sitzung wirklich sofort löschen? Der Link wird danach ungültig.')) {
      return;
    }
    fetch('api/delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ creator_token: data.creatorToken })
    }).then(function () {
      window.location.href = 'index.php';
    });
  });
})();
