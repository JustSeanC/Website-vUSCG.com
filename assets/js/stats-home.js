(function () {
  function formatNumber(value, options) {
    var n = Number(value);
    if (!isFinite(n)) return '—';
    return n.toLocaleString(undefined, options || {});
  }

  function formatValue(key, value) {
    if (value === null || value === undefined || value === '') return '—';

    if (key === 'hours') return formatNumber(value, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    if (key === 'miles') return formatNumber(value, { maximumFractionDigits: 1 });
    if (key === 'pireps' || key === 'currently_flying') return formatNumber(value, { maximumFractionDigits: 0 });
    return String(value);
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function applyStats(payload) {
    var stats = payload && payload.stats ? payload.stats : payload;
    if (!stats || typeof stats !== 'object') return false;

    Object.keys(stats).forEach(function (key) {
      var node = document.querySelector('[data-stat="' + key + '"]');
      if (node) node.textContent = formatValue(key, stats[key]);
    });

    if (payload && payload.updated_at) {
      var updated = new Date(payload.updated_at);
      if (!isNaN(updated.getTime())) {
        setText('homeStatsUpdatedAt', 'Updated ' + updated.toLocaleString());
        return true;
      }
    }

    setText('homeStatsUpdatedAt', 'Updated just now');
    return true;
  }

  function fetchJson(url) {
    return fetch(url, { cache: 'no-store' }).then(function (response) {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status + ' at ' + url);
      }

      return response.text().then(function (text) {
        try {
          return JSON.parse(text);
        } catch (e) {
          throw new Error('Invalid JSON from ' + url + ' (received non-JSON response)');
        }
      });
    });
  }

  function loadStats() {
    // Canonical endpoint(s) first; legacy alias is a last-resort fallback.
    var endpoints = ['api/stats-home.php', '/api/stats-home.php', 'api/home-stats.php', '/api/home-stats.php'];
    var errors = [];

    function summarizePrimaryFailure() {
      for (var i = 0; i < errors.length; i++) {
        if (errors[i].url.indexOf('stats-home.php') !== -1) {
          return errors[i].message;
        }
      }
      return errors.length ? errors[errors.length - 1].message : 'Unknown fetch error';
    }

    function tryNext(index) {
      if (index >= endpoints.length) {
        var detail = summarizePrimaryFailure();
        setText('homeStatsUpdatedAt', 'Live stats temporarily unavailable: ' + detail);

        if (typeof console !== 'undefined' && console.error) {
          console.error('[home-stats] All endpoints failed:', errors);
          console.error('[home-stats] Canonical endpoint is api/stats-home.php.');
        }
        return;
      }

      fetchJson(endpoints[index])
        .then(function (data) {
          if (!applyStats(data)) {
            throw new Error('Missing stats payload at ' + endpoints[index]);
          }
        })
        .catch(function (err) {
          errors.push({
            url: endpoints[index],
            message: (err && err.message) ? err.message : 'Unknown error at ' + endpoints[index],
          });
          tryNext(index + 1);
        });
    }

    tryNext(0);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadStats);
  } else {
    loadStats();
  }
})();
