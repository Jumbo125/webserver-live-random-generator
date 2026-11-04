/**
 * Gemeinsame Logik für Zuschauer- und Ersteller-Ansicht: Zeitsynchronisation,
 * Polling mit Backoff und eine deterministische Zahlen-Animation, die auf
 * allen Browsern aus start_at_ms/duration_ms/animation_seed denselben Ablauf
 * berechnet (kein unabhängiges Math.random() pro Client).
 */
window.DrawView = (function () {
  'use strict';

  var FAST_POLL_MS = 300;
  var SLOW_POLL_MS = 1500;
  var MAX_BACKOFF_MS = 10000;

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  // mulberry32: kleiner, deterministischer PRNG (kein Math.random()), damit
  // alle Browser bei gleichem Seed/Tick exakt dieselbe Zwischenzahl zeigen.
  function mulberry32(seed) {
    var t = seed >>> 0;
    return function () {
      t += 0x6d2b79f5;
      var x = t;
      x = Math.imul(x ^ (x >>> 15), x | 1);
      x ^= x + Math.imul(x ^ (x >>> 7), x | 61);
      return ((x ^ (x >>> 14)) >>> 0) / 4294967296;
    };
  }

  function seedToInt(seedHex) {
    return parseInt(seedHex.slice(0, 8), 16) || 1;
  }

  function pseudoNumberForTick(seedInt, tick, min, max) {
    var rnd = mulberry32(seedInt + tick * 2654435761)();
    return min + Math.floor(rnd * (max - min + 1));
  }

  function create(options) {
    var token = options.token;
    var apiUrl = options.apiUrl;
    var el = options.elements;
    var prefersReducedMotion = window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var offsetSamples = [];
    var bestOffsetMs = 0;
    var requestSeq = 0;
    var latestAppliedSeq = -1;
    var pollTimer = null;
    var backoffMs = FAST_POLL_MS;
    var consecutiveErrors = 0;

    var lastSession = null;
    var lastRound = null;
    var animationFrameId = null;
    var lastTickIndex = -1;
    var lastActiveState = null;

    function notifyActive(active) {
      if (active !== lastActiveState) {
        lastActiveState = active;
        if (typeof options.onActiveChange === 'function') {
          options.onActiveChange(active);
        }
      }
    }

    function estimatedServerNowMs() {
      return Date.now() + bestOffsetMs;
    }

    function recordOffsetSample(sendMs, recvMs, serverNowMs) {
      var rtt = recvMs - sendMs;
      var midpoint = sendMs + rtt / 2;
      var offset = serverNowMs - midpoint;
      offsetSamples.push({ rtt: rtt, offset: offset });
      if (offsetSamples.length > 5) {
        offsetSamples.shift();
      }
      var best = offsetSamples[0];
      for (var i = 1; i < offsetSamples.length; i++) {
        if (offsetSamples[i].rtt < best.rtt) {
          best = offsetSamples[i];
        }
      }
      bestOffsetMs = best.offset;
    }

    function setBanner(visible) {
      if (el.connectionBanner) {
        el.connectionBanner.classList.toggle('d-none', !visible);
      }
    }

    function showOnly(stateName) {
      if (el.stateInvalid) {
        el.stateInvalid.classList.toggle('d-none', stateName !== 'invalid');
      }
      if (el.stateLive) {
        el.stateLive.classList.toggle('d-none', stateName !== 'live');
      }
    }

    function renderInvalid() {
      showOnly('invalid');
      stopAnimationLoop();
    }

    function renderSessionMeta(session) {
      if (el.title) {
        el.title.textContent = session.title || '';
      }
      if (el.range) {
        el.range.textContent = 'Zahlenbereich: ' + session.min + ' – ' + session.max;
      }
    }

    function renderBigNumber(text, resultClass, isText) {
      if (!el.bigNumber) {
        return;
      }
      el.bigNumber.textContent = text;
      el.bigNumber.classList.toggle('number-result', !!resultClass);
      el.bigNumber.classList.toggle('big-number-text', !!isText);
    }

    function stopAnimationLoop() {
      if (animationFrameId !== null) {
        window.cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
      }
    }

    function startAnimationLoop(session, round) {
      stopAnimationLoop();
      lastTickIndex = -1;
      var seedInt = seedToInt(round.animation_seed);
      var totalTicks = 36;

      notifyActive(estimatedServerNowMs() < round.end_at_ms);

      function frame() {
        var now = estimatedServerNowMs();
        var progress = clamp((now - round.start_at_ms) / round.duration_ms, 0, 1);

        if (now < round.start_at_ms) {
          var secondsLeft = Math.ceil((round.start_at_ms - now) / 1000);
          if (el.statusLine) {
            el.statusLine.textContent = 'Start in ' + secondsLeft + ' s …';
          }
          renderBigNumber('Bitte warten …', false, true);
          animationFrameId = window.requestAnimationFrame(frame);
          return;
        }

        if (progress >= 1) {
          if (el.statusLine) {
            el.statusLine.textContent = 'Ergebnis';
          }
          renderBigNumber(String(round.result), true);
          stopAnimationLoop();
          notifyActive(false);
          return;
        }

        if (el.statusLine) {
          el.statusLine.textContent = 'Ziehung läuft …';
        }

        if (prefersReducedMotion) {
          renderBigNumber('…', false);
        } else {
          var eased = 1 - Math.pow(1 - progress, 3);
          var tickIndex = Math.floor(eased * totalTicks);
          if (tickIndex !== lastTickIndex) {
            lastTickIndex = tickIndex;
            var num = pseudoNumberForTick(seedInt, tickIndex, session.min, session.max);
            renderBigNumber(String(num), false);
          }
        }

        animationFrameId = window.requestAnimationFrame(frame);
      }

      animationFrameId = window.requestAnimationFrame(frame);
    }

    function applyState(data) {
      var session = data.session;
      lastSession = session;

      if (session.state === 'invalid') {
        lastRound = null;
        renderInvalid();
        notifyActive(false);
        return;
      }

      showOnly('live');
      renderSessionMeta(session);

      var round = data.round;
      var roundChanged = (round === null && lastRound !== null) ||
        (round !== null && (lastRound === null || round.id !== lastRound.id));

      lastRound = round;

      if (round === null) {
        stopAnimationLoop();
        notifyActive(false);
        if (el.statusLine) {
          el.statusLine.textContent = 'Warten auf Start …';
        }
        renderBigNumber('–', false);
        return;
      }

      if (roundChanged || animationFrameId === null) {
        startAnimationLoop(session, round);
      }
    }

    function nextPollDelay() {
      if (lastRound && lastSession && lastSession.state === 'ok') {
        var now = estimatedServerNowMs();
        if (now < lastRound.end_at_ms) {
          return FAST_POLL_MS;
        }
      }
      return SLOW_POLL_MS;
    }

    function poll() {
      var seq = ++requestSeq;
      var sendMs = Date.now();

      fetch(apiUrl + '?token=' + encodeURIComponent(token), { cache: 'no-store' })
        .then(function (response) {
          return response.json().then(function (data) {
            if (!response.ok) {
              throw new Error('http_' + response.status);
            }
            return data;
          });
        })
        .then(function (data) {
          var recvMs = Date.now();
          if (seq < latestAppliedSeq) {
            // Ältere, verzögerte Antwort nach einer bereits angewendeten neueren verwerfen.
            return;
          }
          latestAppliedSeq = seq;

          recordOffsetSample(sendMs, recvMs, data.server_now_ms);
          applyState(data);

          consecutiveErrors = 0;
          backoffMs = FAST_POLL_MS;
          setBanner(false);
          schedulePoll();
        })
        .catch(function () {
          consecutiveErrors++;
          setBanner(consecutiveErrors >= 2);
          backoffMs = Math.min(MAX_BACKOFF_MS, backoffMs * 2);
          schedulePoll();
        });
    }

    function schedulePoll() {
      if (pollTimer !== null) {
        window.clearTimeout(pollTimer);
      }
      var delay = consecutiveErrors > 0 ? backoffMs : nextPollDelay();
      pollTimer = window.setTimeout(poll, delay);
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') {
        poll();
      }
    });

    poll();

    return {
      refresh: poll
    };
  }

  return { create: create };
})();
