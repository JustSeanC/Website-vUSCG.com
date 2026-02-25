(function () {
  function formatValue(key, value) {
    if (value === null || value === undefined || value === '') return '—';

    if (key === 'hours') return Number(value).toLocaleString(undefined, { maximumFractionDigits: 1 });
    if (key === 'miles' || key === 'pireps') return Number(value).toLocaleString();
    return String(value);
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function loadStats() {
    fetch('api/home-stats.php', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.stats) return;

        var stats = data.stats;
        Object.keys(stats).forEach(function (key) {
          var node = document.querySelector('[data-stat="' + key + '"]');
          if (node) node.textContent = formatValue(key, stats[key]);
        });

        if (data.updated_at) {
          var updated = new Date(data.updated_at);
          if (!isNaN(updated.getTime())) {
            setText('homeStatsUpdatedAt', 'Updated ' + updated.toLocaleString());
          }
        }
      })
      .catch(function () {
        setText('homeStatsUpdatedAt', 'Live stats temporarily unavailable');
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadStats);
  } else {
    loadStats();
  }
})();
