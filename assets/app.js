(function () {
  document.querySelectorAll('.stars').forEach(function (box) {
    for (var i = 1; i <= 5; i++) {
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = '★'; b.dataset.v = i; b.setAttribute('aria-label', i + ' из 5');
      box.appendChild(b);
    }
    box.addEventListener('click', function (e) {
      if (e.target.tagName !== 'BUTTON') return;
      var v = +e.target.dataset.v;
      box.dataset.rating = (box.dataset.rating == v) ? 0 : v; // повторный клик сбрасывает
      paint(box);
    });
  });
  function paint(box) {
    var v = +box.dataset.rating;
    box.querySelectorAll('button').forEach(function (b) { b.classList.toggle('on', +b.dataset.v <= v); });
  }
  var err = document.getElementById('err');
  function showErr(m) { err.textContent = m; err.hidden = false; }

  document.getElementById('send').addEventListener('click', function () {
    err.hidden = true;
    var items = [];
    var bad = false;
    document.querySelectorAll('.block').forEach(function (blk) {
      var r = +blk.querySelector('.stars').dataset.rating;
      if (!r) return;
      var it = { category: +blk.dataset.cat, rating: r, comment: blk.querySelector('textarea').value };
      if (it.category === 1) {
        it.dish_id = +blk.querySelector('select').value;
        if (!it.dish_id) { bad = true; }
      }
      items.push(it);
    });
    if (bad) return showErr('Выберите блюдо, которое оцениваете.');
    if (!items.length) return showErr('Поставьте оценку хотя бы в одном пункте.');
    var btn = this; btn.disabled = true;
    fetch('api/submit.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: document.getElementById('code').value, items: items })
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.ok) {
        document.getElementById('formBox').hidden = true;
        document.getElementById('done').hidden = false;
      } else { showErr(d.error || 'Ошибка'); btn.disabled = false; }
    }).catch(function () { showErr('Нет связи с сервером'); btn.disabled = false; });
  });
})();
