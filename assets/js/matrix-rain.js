/**
 * Matrix-artiger Zahlenregen als dekorativer Canvas-Hintergrund, z. B. hinter
 * der großen Zufallszahl. Füllt immer die Größe seines Elternelements (nicht
 * den ganzen Bildschirm) und respektiert prefers-reduced-motion.
 */
window.MatrixRain = (function () {
  'use strict';

  function create(canvas) {
    const ctx = canvas.getContext('2d');
    let streams = [];
    const size = 17;
    let columns = 0;
    let last = 0;
    let width = 0;
    let height = 0;
    let rafId = null;

    function resize() {
      const rect = canvas.parentElement.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      width = Math.max(1, Math.round(rect.width));
      height = Math.max(1, Math.round(rect.height));
      canvas.width = width * dpr;
      canvas.height = height * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      columns = Math.ceil(width / size);
      streams = [];
      for (let i = 0; i < columns; i++) {
        streams.push({
          y: Math.random() * -height / size,
          speed: 0.35 + Math.random() * 1.1,
          length: 8 + Math.random() * 21
        });
      }
      ctx.fillStyle = '#000';
      ctx.fillRect(0, 0, width, height);
    }

    function render(ts) {
      rafId = window.requestAnimationFrame(render);
      if (ts - last < 32) {
        return;
      }
      const dt = Math.min((ts - last) / 32, 2);
      last = ts;

      ctx.fillStyle = 'rgba(0,0,0,.095)';
      ctx.fillRect(0, 0, width, height);
      ctx.font = '16px monospace';
      ctx.textAlign = 'center';

      for (let i = 0; i < streams.length; i++) {
        const s = streams[i];
        s.y += s.speed * dt;
        const x = i * size + size / 2;
        const y = s.y * size;

        ctx.shadowBlur = 0;
        ctx.fillStyle = '#00c944';
        ctx.fillText(String(Math.floor(Math.random() * 10)), x, y);

        if (Math.random() < 0.12) {
          ctx.fillStyle = '#b6ffcb';
          ctx.shadowColor = '#6fff8e';
          ctx.shadowBlur = 15;
          ctx.fillText(String(Math.floor(Math.random() * 10)), x, y);
        }

        if (y > height + s.length * size) {
          s.y = -Math.random() * 40;
          s.speed = 0.35 + Math.random() * 1.1;
        }
      }
    }

    let resizeObserver = null;
    if (window.ResizeObserver) {
      resizeObserver = new ResizeObserver(resize);
      resizeObserver.observe(canvas.parentElement);
    } else {
      window.addEventListener('resize', resize);
    }

    resize();

    const prefersReducedMotion = window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReducedMotion) {
      rafId = window.requestAnimationFrame(render);
    }

    return {
      stop: function () {
        if (rafId !== null) {
          window.cancelAnimationFrame(rafId);
          rafId = null;
        }
        if (resizeObserver) {
          resizeObserver.disconnect();
        } else {
          window.removeEventListener('resize', resize);
        }
      }
    };
  }

  return { create: create };
})();
