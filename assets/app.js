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
      var cur = +box.dataset.rating;
      box.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', +x.dataset.v <= cur); });
    });
  });
  function rating(key) {
    return +document.querySelector('.stars[data-key="' + key + '"]').dataset.rating;
  }
  var err = document.getElementById('err');
  function showErr(m) { err.textContent = m; err.hidden = false; }

  document.getElementById('send').addEventListener('click', function () {
    err.hidden = true;
    var overall = rating('overall');
    if (!overall) return showErr('Поставьте общую оценку.');
    var dishIds = [];
    document.querySelectorAll('.dish:checked').forEach(function (c) { dishIds.push(+c.value); });
    var btn = this; btn.disabled = true;
    fetch('api/submit.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        code: document.getElementById('code').value,
        dish_ids: dishIds,
        overall: overall,
        cleanliness: rating('cleanliness'),
        service: rating('service'),
        comment: document.getElementById('comment').value
      })
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d.ok) {
        document.getElementById('formBox').hidden = true;
        document.getElementById('done').hidden = false;
      } else { showErr(d.error || 'Ошибка'); btn.disabled = false; }
    }).catch(function () { showErr('Нет связи с сервером'); btn.disabled = false; });
  });
})();
