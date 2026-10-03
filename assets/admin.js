(function () {
  var COLORS = { o: '#e67e22', c: '#2980b9', s: '#1f9d62' };
  var NAMES = { o: 'Общая', c: 'Чистота', s: 'Обслуживание' };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
  var selCanteen = document.getElementById('canteen'), selDays = document.getElementById('days');

  function load() {
    fetch('api_stats.php?days=' + selDays.value + '&canteen=' + selCanteen.value)
      .then(function (r) { return r.json(); }).then(render);
  }

  function render(d) {
    // список столовых с оценкой (сохраняем выбранную)
    var prev = selCanteen.value;
    var html = '<option value="0">Все столовые</option>';
    d.canteens.forEach(function (c) {
      html += '<option value="' + c.id + '">' + esc(c.address) + (c.active == 1 ? '' : ' (архив)') +
        (c.a ? ' — ★ ' + c.a : '') + '</option>';
    });
    selCanteen.innerHTML = html; selCanteen.value = prev;

    var s = d.summary, n = +s.n;
    document.getElementById('avgBadge').textContent = n ? '★ ' + s.o + ' · ' + n + ' отзывов' : 'нет оценок';

    var used = +d.tokens.used || 0, issued = +d.tokens.issued || 0;
    var pend = d.comments.filter(function (c) { return c.s === 'pending'; }).length;
    document.getElementById('kpis').innerHTML =
      kpi('Общая оценка', s.o || '—') + kpi('Отзывов', n) + kpi('Чистота', s.c || '—') + kpi('Обслуживание', s.s || '—') +
      kpi('Коды: использовано', used + ' / ' + issued) + kpi('На модерации', pend);

    lineChart(d.daily); barChart(s); distChart(d.dist);

    document.querySelector('#dishes tbody').innerHTML = d.dishes.map(function (x) {
      return '<tr><td>' + esc(x.name) + '</td><td>' + x.a + '</td><td>' + x.n + '</td></tr>';
    }).join('') || '<tr><td colspan=3>Нет данных</td></tr>';

    document.getElementById('comments').innerHTML = d.comments.map(function (c) {
      var btns = '';
      if (c.s !== 'approved') btns += '<button class="btn sm" data-id="' + c.id + '" data-s="approved">Одобрить</button> ';
      if (c.s !== 'hidden') btns += '<button class="btn sm red" data-id="' + c.id + '" data-s="hidden">Скрыть</button>';
      return '<div class="block"><span class="tag ' + c.s + '">' + c.s + '</span> <b>' + esc(c.canteen) +
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
    g.strokeStyle = '#e1e8e3'; g.fillStyle = '#66766d';
    for (var v = 1; v <= 5; v++) {
      var y = T + (o.h - T - B) * (1 - (v - 1) / 4);
      g.beginPath(); g.moveTo(L, y); g.lineTo(o.w - R, y); g.stroke(); g.fillText(v, 15, y + 4);
    }
    if (!rows.length) { g.fillText('Нет данных за период', o.w / 2 - 60, o.h / 2); return; }
    var step = (o.w - L - R) / Math.max(1, rows.length - 1);
    rows.forEach(function (r, i) {
      if (rows.length < 15 || i % Math.ceil(rows.length / 12) === 0) g.fillText(r.d.slice(5), L + i * step - 14, o.h - 22);
    });
    ['o', 'c', 's'].forEach(function (k, idx) {
      g.strokeStyle = g.fillStyle = COLORS[k]; g.lineWidth = 2; g.beginPath(); var started = false;
      function pt(i) { return [L + i * step, T + (o.h - T - B) * (1 - (+rows[i][k] - 1) / 4)]; }
      rows.forEach(function (r, i) {
        if (r[k] == null) return;
        var p = pt(i); started ? g.lineTo(p[0], p[1]) : g.moveTo(p[0], p[1]); started = true;
      });
      g.stroke();
      rows.forEach(function (r, i) {
        if (r[k] == null) return;
        var p = pt(i); g.beginPath(); g.arc(p[0], p[1], 3, 0, 7); g.fill();
      });
      g.fillStyle = COLORS[k]; g.fillRect(L + idx * 130, o.h - 12, 10, 10); g.fillStyle = '#333'; g.fillText(NAMES[k], L + idx * 130 + 15, o.h - 3);
    });
  }
  function barChart(s) {
    var o = ctx('bars'), g = o.g, bw = 70, gap = 40, base = o.h - 40, maxH = o.h - 70;
    ['o', 'c', 's'].forEach(function (k, i) {
      var v = +s[k] || 0, hgt = maxH * v / 5, x = 40 + i * (bw + gap);
      g.fillStyle = COLORS[k]; g.fillRect(x, base - hgt, bw, hgt);
      g.fillStyle = '#333'; g.fillText(v ? v.toFixed(2) : '—', x + 18, base - hgt - 6);
      g.fillText(NAMES[k], x - 2, base + 18);
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

  selDays.addEventListener('change', load);
  selCanteen.addEventListener('change', load);
  document.getElementById('comments').addEventListener('click', function (e) {
    var b = e.target.closest('button[data-id]'); if (!b) return;
    fetch('moderate.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: +b.dataset.id, status: b.dataset.s, csrf: CSRF }) }).then(load);
  });
  load();
})();
