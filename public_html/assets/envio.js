(function () {
  'use strict';
  var form = document.getElementById('formEnvio');
  if (!form) return;
  var csrf = form.querySelector('input[name=csrf]').value;

  function digits(v) { return (v || '').replace(/\D/g, ''); }
  function cnpjValido(c) {
    c = digits(c);
    if (c.length !== 14 || /^(\d)\1{13}$/.test(c)) return false;
    var calc = function (len) {
      var w = len === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2], s = 0;
      for (var i = 0; i < len; i++) s += +c[i] * w[i];
      var r = s % 11; return r < 2 ? 0 : 11 - r;
    };
    return calc(12) === +c[12] && calc(13) === +c[13];
  }
  function maskCnpj(v) {
    var d = digits(v).slice(0, 14);
    return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
  }
  function maskTel(v) {
    var d = digits(v).slice(0, 11);
    if (d.length <= 10) return d.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d)/, '$1-$2');
    return d.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d)/, '$1-$2');
  }

  // CNPJ: máscara, validação e preenchimento da razão social
  var cnpj = document.getElementById('cnpj'), razao = document.getElementById('razao'), hint = document.getElementById('cnpjHint');
  var ultimo = '', razaoAuto = false;
  razao.addEventListener('input', function () { razaoAuto = false; });
  function setHint(t, cls) { hint.textContent = t; hint.className = 'hint' + (cls ? ' ' + cls : ''); }
  function checarCnpj() {
    var d = digits(cnpj.value);
    if (d.length < 14) { cnpj.classList.remove('invalid'); return; }
    if (!cnpjValido(d)) { cnpj.classList.add('invalid'); setHint('CNPJ inválido. Verifique os dígitos.', 'err'); return; }
    cnpj.classList.remove('invalid');
    if (d === ultimo) return;
    ultimo = d;
    setHint('Consultando CNPJ…');
    fetch('/cnpj.php?cnpj=' + d, { headers: { 'X-CSRF-Token': csrf }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok && j.razao_social) {
          if (!razao.value.trim() || razaoAuto) { razao.value = j.razao_social; razaoAuto = true; }
          setHint('CNPJ válido' + (j.situacao ? ' · Situação: ' + j.situacao : '') + '.', 'ok');
        } else {
          setHint((j && j.erro) ? j.erro : 'Não foi possível consultar agora. Preencha a razão social manualmente.', 'err');
        }
      })
      .catch(function () { setHint('Não foi possível consultar agora. Preencha a razão social manualmente.', 'err'); });
  }
  cnpj.addEventListener('input', function () { cnpj.value = maskCnpj(cnpj.value); checarCnpj(); });
  cnpj.addEventListener('blur', checarCnpj);
  if (cnpj.value) cnpj.value = maskCnpj(cnpj.value);

  var tel = document.getElementById('telefone');
  tel.addEventListener('input', function () { tel.value = maskTel(tel.value); });

  // Upload acumulativo: até N arquivos, 10 MB cada
  var box = document.getElementById('upload'), input = document.getElementById('documentos');
  var list = document.getElementById('uploadList'), status = document.getElementById('uploadStatus'), erroBox = document.getElementById('uploadErro');
  var maxFiles = parseInt(box.getAttribute('data-max-files'), 10) || 10;
  var maxBytes = parseInt(box.getAttribute('data-max-bytes'), 10) || 10485760;
  var allowed = box.getAttribute('data-allowed-ext').split(',');
  var podeAcumular = typeof DataTransfer !== 'undefined';
  try { new DataTransfer(); } catch (e) { podeAcumular = false; }
  var arquivos = [];

  function tamanho(b) { var kb = Math.ceil(b / 1024); return kb < 1024 ? kb + ' KB' : (kb / 1024).toFixed(1).replace('.', ',') + ' MB'; }
  function erro(msgs) { if (!msgs.length) { erroBox.hidden = true; return; } erroBox.innerHTML = ''; msgs.forEach(function (m) { var d = document.createElement('div'); d.textContent = m; erroBox.appendChild(d); }); erroBox.hidden = false; }
  function sincronizar() {
    if (podeAcumular) { var dt = new DataTransfer(); arquivos.forEach(function (f) { dt.items.add(f); }); input.files = dt.files; }
    list.innerHTML = '';
    arquivos.forEach(function (f, idx) {
      var li = document.createElement('li'), s = document.createElement('span'), b = document.createElement('button');
      s.textContent = f.name + ' (' + tamanho(f.size) + ')';
      b.type = 'button'; b.textContent = 'Remover';
      b.addEventListener('click', function () { arquivos.splice(idx, 1); erro([]); sincronizar(); });
      li.appendChild(s); if (podeAcumular) li.appendChild(b); list.appendChild(li);
    });
    status.textContent = arquivos.length ? arquivos.length + ' de ' + maxFiles + ' arquivo(s) selecionado(s).' : 'Nenhum documento adicionado.';
  }
  function adicionar(files) {
    var msgs = [];
    if (!podeAcumular) arquivos = [];
    Array.prototype.forEach.call(files, function (f) {
      var ext = (f.name.split('.').pop() || '').toLowerCase();
      if (allowed.indexOf(ext) < 0) { msgs.push('Formato não aceito: ' + f.name); return; }
      if (f.size > maxBytes) { msgs.push('Arquivo maior que 10 MB: ' + f.name); return; }
      if (f.size <= 0) { msgs.push('Arquivo vazio: ' + f.name); return; }
      if (arquivos.some(function (a) { return a.name === f.name && a.size === f.size; })) return;
      if (arquivos.length >= maxFiles) { msgs.push('Limite de ' + maxFiles + ' arquivos atingido: ' + f.name + ' não foi adicionado.'); return; }
      arquivos.push(f);
    });
    erro(msgs); sincronizar();
  }
  input.addEventListener('change', function () { adicionar(input.files); });
  var drop = box.querySelector('.upload-drop');
  ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('drag'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('drag'); }); });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) adicionar(e.dataTransfer.files); });

  form.addEventListener('submit', function (e) {
    var msgs = [];
    if (!cnpjValido(cnpj.value)) msgs.push('Informe um CNPJ válido.');
    if (!arquivos.length && !(input.files && input.files.length)) msgs.push('Anexe ao menos um documento.');
    var faltando = Array.prototype.some.call(form.querySelectorAll('[required]'), function (el) { return !el.value.trim(); });
    if (faltando) msgs.push('Preencha todos os campos obrigatórios.');
    if (msgs.length) { e.preventDefault(); erro(msgs); erroBox.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    var btn = document.getElementById('btnEnviar'); btn.disabled = true; btn.textContent = 'Enviando…';
  });
})();
