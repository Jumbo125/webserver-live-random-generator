<?php
/**
 * Erwartet $viewerToken (string) im Scope. Wird von live.php und control.php
 * eingebunden, damit beide dieselbe Zahlen-/Statusanzeige nutzen.
 */
?>
<section id="draw-display" class="card shadow-sm">
  <div class="card-body text-center py-5">
    <div id="session-title" class="fs-5 fw-semibold mb-1"></div>
    <div id="session-range" class="text-muted small mb-4"></div>

    <div id="state-invalid" class="d-none">
      <p class="fs-4 mb-0">🔒 Link ungültig oder abgelaufen.</p>
    </div>

    <div id="state-live" class="d-none">
      <div class="matrix-stage">
        <canvas id="matrix-canvas"></canvas>
        <div class="matrix-stage-content">
          <div id="big-number" class="display-1 fw-bold mb-3">–</div>
          <div id="status-line" class="fs-5">Warten auf Start …</div>
        </div>
      </div>
    </div>

    <div id="connection-banner" class="alert alert-warning mt-4 mb-0 d-none" role="status">
      Verbindung wird wiederhergestellt …
    </div>
  </div>
</section>

<script>
  window.addEventListener('DOMContentLoaded', function () {
    var matrixCanvas = document.getElementById('matrix-canvas');
    if (matrixCanvas && window.MatrixRain) {
      window.MatrixRain.create(matrixCanvas);
    }

    window.drawViewInstance = window.DrawView.create({
      token: <?= json_encode($viewerToken) ?>,
      apiUrl: 'api/session.php',
      elements: {
        title: document.getElementById('session-title'),
        range: document.getElementById('session-range'),
        stateInvalid: document.getElementById('state-invalid'),
        stateLive: document.getElementById('state-live'),
        bigNumber: document.getElementById('big-number'),
        statusLine: document.getElementById('status-line'),
        connectionBanner: document.getElementById('connection-banner')
      },
      onActiveChange: typeof window.onDrawActiveChange === 'function' ? window.onDrawActiveChange : undefined
    });
  });
</script>
