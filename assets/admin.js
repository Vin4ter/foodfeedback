(function () {
  var COLORS = { 1: '#e67e22', 2: '#2980b9', 3: '#1f9d62' };
  var NAMES = { 1: 'Блюдо', 2: 'Чистота', 3: 'Обслуживание' };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  function load() {
    fetch('api_stats.php?days=' + document.getElementById('days').value)
      .then(function (r) { return r.json(); }).then(render);
  }
  function render(d) {
    var total = 0, sum = 0;
    d.dist.forEach(function (x) { total += +x.n; sum += x.r * x.n; });
    var pend = d.comments.filter(function (c) { return c.s === 'pending'; }).length;
    var used = +d.tokens.used || 0, issued = +d.tokens.issued || 0;
    document.getElementById('kpis').innerHTML =
      kpi('Средняя оценка', total ? (sum / total).toFixed(2) : '—') + kpi('Оценок', total) +
      kpi('Коды: использовано', used + ' / ' + issued) + kpi('Комментарии на модерации', pend);

    lineChart(d.daily); barChart(d.cats); distChart(d.dist);

    document.querySelector('#dishes tbody').innerHTML = d.dishes.map(function (x) {
      return '<tr><td>' + esc(x.name) + '</td><td>' + x.a + '</td><td>' + x.n + '</td></tr>';
    }).join('') || '<tr><td colspan=3>Нет данных</td></tr>';

    document.getElementById('comments').innerHTML = d.comments.map(function (c) {
      var btns = '';
      if (c.s !== 'approved') btns += '<button class="btn sm" data-id="' + c.id + '" data-s="approved">Одобрить</button> ';
      if (c.s !== 'hidden') btns += '<button class="btn sm red" data-id="' + c.id + '" data-s="hidden">Скрыть</button>';
      return '<div class="block"><span class="tag ' + c.s + '">' + c.s + '</span> <b>' + esc(c.cat) + (c.dish ? ': ' + esc(c.dish) : '') +
        '</b> · ' + '★'.repeat(c.rating) + ' · <span class="muted small">' + esc(c.t.slice(0, 13)) + ':00</span><p>' + esc(c.comment) + '</p>' + btns + '</div>';
    }).join('') || '<p class="muted">Комментариев нет</p>';
  }
  function kpi(t, v) { return '<div class="kpi"><span class="muted">' + t + '</span><b>' + v + '</b></div>'; }

  function ctx(id) {
    var c = document.getElementById(id), g = c.getContext('2d');
    g.clearRect(0, 0, c.width, c.height); g.font = '13px sans-serif'; return { g: g, w: c.width, h: c.height };
  }
  function lineChart(rows) {
    var o = ctx('line'), g = o.g, L = 40, R = 15, T = 15, B = 45;
    var dates = [], series = { 1: {}, 2: {}, 3: {} };
    rows.forEach(function (r) { if (dates.indexOf(r.d) < 0) dates.push(r.d); series[r.c][r.d] = +r.a; });
    g.strokeStyle = '#e1e8e3'; g.fillStyle = '#66766d';
    for (var v = 1; v <= 5; v++) {
      var y = T + (o.h - T - B) * (1 - (v - 1) / 4);
      g.beginPath(); g.moveTo(L, y); g.lineTo(o.w - R, y); g.stroke(); g.fillText(v, 15, y + 4);
    }
    if (!dates.length) { g.fillText('Нет данных за период', o.w / 2 - 60, o.h / 2); return; }
    var step = (o.w - L - R) / Math.max(1, dates.length - 1);
    dates.forEach(function (dt, i) {
      if (dates.length < 15 || i % Math.ceil(dates.length / 12) === 0) g.fillText(dt.slice(5), L + i * step - 14, o.h - 22);
    });
    [1, 2, 3].forEach(function (c, k) {
      g.strokeStyle = g.fillStyle = COLORS[c]; g.lineWidth = 2; g.beginPath(); var started = false;
      dates.forEach(function (dt, i) {
        if (series[c][dt] == null) return;
        var x = L + i * step, y = T + (o.h - T - B) * (1 - (series[c][dt] - 1) / 4);
        started ? g.lineTo(x, y) : g.moveTo(x, y); started = true;
      });
      g.stroke();
      dates.forEach(function (dt, i) {
        if (series[c][dt] == null) return;
        g.beginPath(); g.arc(L + i * step, T + (o.h - T - B) * (1 - (series[c][dt] - 1) / 4), 3, 0, 7); g.fill();
      });
      g.fillStyle = COLORS[c]; g.fillRect(L + k * 130, o.h - 12, 10, 10); g.fillStyle = '#333'; g.fillText(NAMES[c], L + k * 130 + 15, o.h - 3);
    });
  }
  function barChart(cats) {
    var o = ctx('bars'), g = o.g, bw = 70, gap = 40, base = o.h - 40, maxH = o.h - 70;
    cats.forEach(function (c, i) {
      var v = +c.a || 0, hgt = maxH * v / 5, x = 40 + i * (bw + gap);
      g.fillStyle = COLORS[c.id]; g.fillRect(x, base - hgt, bw, hgt);
      g.fillStyle = '#333'; g.fillText(v ? v.toFixed(2) : '—', x + 18, base - hgt - 6);
      g.fillText(c.title, x - 2, base + 18); g.fillStyle = '#66766d'; g.fillText('n=' + c.n, x + 12, base + 34);
    });
  }
  function distChart(dist) {
    var o = ctx('dist'), g = o.g, m = {}, mx = 1; dist.forEach(function (x) { m[x.r] = +x.n; mx = Math.max(mx, +x.n); });
    for (var r = 1; r <= 5; r++) {
      var w = (o.w - 120) * (m[r] || 0) / mx, y = 20 + (r - 1) * 44;
      g.fillStyle = '#f5b301'; g.fillRect(50, y, w, 28);
      g.fillStyle = '#333'; g.fillText(r + ' ★', 10, y + 19); g.fillText(m[r] || 0, 56 + w, y + 19);
    }
  }

  document.getElementById('days').addEventListener('change', load);
  document.getElementById('comments').addEventListener('click', function (e) {
    var b = e.target.closest('button[data-id]'); if (!b) return;
    fetch('moderate.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: +b.dataset.id, status: b.dataset.s, csrf: CSRF }) }).then(load);
  });
  load();
})();
