(function () {
  'use strict';

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

  /* ---------- Menu no celular ---------- */
  var sidebar = document.querySelector('[data-sidebar]');
  var overlay = document.querySelector('[data-sidebar-overlay]');

  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('-translate-x-full', !open);
    sidebar.classList.toggle('translate-x-0', open);
    if (overlay) overlay.classList.toggle('hidden', !open);
  }

  document.querySelectorAll('[data-sidebar-open]').forEach(function (button) {
    button.addEventListener('click', function () { setSidebar(true); });
  });
  document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
    button.addEventListener('click', function () { setSidebar(false); });
  });
  if (overlay) overlay.addEventListener('click', function () { setSidebar(false); });

  /* ---------- Confirmação e "Salvando…" nos formulários ---------- */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      event.preventDefault();
      return;
    }
    var button = event.submitter;
    if (button && button.dataset.pending) {
      // Depois do envio começar, para não perder o valor do botão.
      setTimeout(function () {
        button.disabled = true;
        button.textContent = button.dataset.pending;
      }, 0);
    }
  });

  /* ---------- Copiar senha temporária ---------- */
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-copy]');
    if (!button || !navigator.clipboard) return;
    navigator.clipboard.writeText(button.dataset.copy).then(function () {
      var label = button.querySelector('span');
      if (label) label.textContent = 'Copiada';
    });
  });

  function post(url, data) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
      body: new URLSearchParams(data || {})
    }).then(function (response) { return response.json(); });
  }

  /* ---------- Simulador do bot ---------- */
  var simulator = document.querySelector('[data-simulator]');
  if (simulator) {
    var log = simulator.querySelector('[data-simulator-log]');
    var form = simulator.querySelector('[data-simulator-form]');
    var input = form.querySelector('input');
    var busy = false;

    var now = function () {
      return new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    };

    // *negrito* do WhatsApp, sem usar innerHTML.
    var appendFormatted = function (parent, text) {
      text.split(/(\*[^*\n]+\*)/g).forEach(function (part) {
        if (part.length > 2 && part.charAt(0) === '*' && part.charAt(part.length - 1) === '*') {
          var strong = document.createElement('strong');
          strong.className = 'font-bold';
          strong.textContent = part.slice(1, -1);
          parent.appendChild(strong);
        } else if (part) {
          parent.appendChild(document.createTextNode(part));
        }
      });
    };

    var bubble = function (from, text) {
      var empty = log.querySelector('[data-simulator-empty]');
      if (empty) empty.remove();
      var row = document.createElement('div');
      row.className = from === 'user' ? 'flex justify-end' : 'flex justify-start';
      var box = document.createElement('div');
      box.className = from === 'user'
        ? 'max-w-[80%] rounded-lg rounded-tr-sm bg-[#134536] px-3 py-2 text-sm'
        : 'max-w-[85%] rounded-lg rounded-tl-sm bg-panel-3 px-3 py-2 text-sm';
      if (from === 'bot') {
        var name = document.createElement('p');
        name.className = 'mb-0.5 text-xs font-bold text-accent';
        name.textContent = 'Controladoria';
        box.appendChild(name);
      }
      var body = document.createElement('p');
      body.className = 'whitespace-pre-wrap';
      if (from === 'bot') appendFormatted(body, text); else body.textContent = text;
      box.appendChild(body);
      var stamp = document.createElement('p');
      stamp.className = 'mt-1 text-right font-mono text-[10px] text-dim';
      stamp.textContent = now();
      box.appendChild(stamp);
      row.appendChild(box);
      log.appendChild(row);
      log.scrollTop = log.scrollHeight;
      return row;
    };

    var send = function (text) {
      text = (text || '').trim();
      if (!text || busy) return;
      busy = true;
      bubble('user', text);
      input.value = '';
      var typing = bubble('bot', 'digitando…');
      post('/bot/simular', { text: text })
        .then(function (data) {
          typing.remove();
          bubble('bot', data.reply || data.error || 'Sem resposta.');
        })
        .catch(function () {
          typing.remove();
          bubble('bot', 'Não foi possível falar com o painel. Recarregue a página.');
        })
        .then(function () { busy = false; });
    };

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      send(input.value);
    });
    simulator.querySelectorAll('[data-simulator-command]').forEach(function (button) {
      button.addEventListener('click', function () { send(button.dataset.simulatorCommand); });
    });
  }

  /* ---------- Verificação em 2 etapas ---------- */
  var totp = document.querySelector('[data-totp]');
  if (totp) {
    var start = totp.querySelector('[data-totp-start]');
    var error = totp.querySelector('[data-totp-error]');
    var setup = totp.querySelector('[data-totp-setup]');

    start.addEventListener('click', function () {
      start.disabled = true;
      error.hidden = true;
      post('/conta/2fa/iniciar')
        .then(function (data) {
          if (!data.uri || typeof window.qrcode !== 'function') {
            throw new Error(data.error || 'Não foi possível iniciar a configuração.');
          }
          var qr = window.qrcode(0, 'M');
          qr.addData(data.uri);
          qr.make();
          totp.querySelector('[data-totp-qr]').innerHTML = qr.createSvgTag(4, 2);
          totp.querySelector('[data-totp-secret]').textContent = data.secret;
          start.hidden = true;
          setup.hidden = false;
          setup.querySelector('input[name="code"]').focus();
        })
        .catch(function (problem) {
          error.textContent = problem.message || 'Não foi possível iniciar a configuração.';
          error.hidden = false;
          start.disabled = false;
        });
    });
  }
})();
