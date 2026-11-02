<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
if (sf_empresa()) { header('Location: /painel.php'); exit; }
$_SESSION['sf_cadastro_form'] = true;

$erro = '';
$sucesso = '';
$f = ['cnpj'=>'','razao'=>'','nome'=>'','email'=>'','telefone'=>''];
if (isset($_GET['ok'])) {
    $sucesso = $_GET['ok'] === 'base'
        ? 'Este CNPJ já está cadastrado. Nossa equipe vai entrar em contato pelo e-mail ou telefone informado no cadastro.'
        : 'Cadastro recebido. Avisaremos por e-mail quando seu acesso for liberado.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $senha = (string)($_POST['senha'] ?? '');
    $cnpj = cnpj_digits($f['cnpj']);
    $telDig = preg_replace('/\D/', '', $f['telefone']);
    try {
        ensure_sheet_structures();
        if (!sf_csrf_ok()) $erro = 'Sessão expirada. Tente novamente.';
        elseif (!cnpj_valid($cnpj)) $erro = 'Informe um CNPJ válido.';
        elseif (mb_strlen($f['razao']) < 2) $erro = 'Informe a razão social.';
        elseif (mb_strlen($f['nome']) < 2) $erro = 'Informe o nome do responsável.';
        elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
        elseif (strlen($telDig) < 10 || strlen($telDig) > 13) $erro = 'Informe um telefone com DDD.';
        elseif (strlen($senha) < 8) $erro = 'A senha precisa ter pelo menos 8 caracteres.';
        else {
            // Nada é liberado aqui: o pedido fica pendente até a aprovação do admin.
            // CNPJ novo: cria o cliente (dono felipe.schumaher@redegarantia.com.br, origem portal). CNPJ existente: não muda o cliente nem o dono.
            $r = empresa_solicitar_acesso($cnpj, [
                'razao_social' => $f['razao'],
                'nome' => $f['nome'],
                'email' => strtolower($f['email']),
                'telefone' => $f['telefone'],
            ], $senha);
            if ($r['erro'] !== '') $erro = $r['erro'];
            else {
                unset($_SESSION['sf_cadastro_form']);
                header('Location: /cadastrar.php?ok=' . ($r['cnpj_existente'] ? 'base' : 'novo'), true, 303);
                exit;
            }
        }
    } catch (Exception $ex) {
        app_log('SF cadastro: ' . $ex->getMessage());
        $erro = 'Não foi possível cadastrar agora.';
    }
}
echo sf_layout_inicio('Cadastro da empresa');
echo sf_nav_publico('cadastrar');
?>
<section class="hero"><div class="wrap"><h1>Cadastre a empresa</h1><p>Depois do cadastro, nossa equipe confere os dados e libera o acesso. Você recebe um aviso por e-mail.</p></div></section>
<main class="wrap main">
<?php if ($sucesso): ?>
<section class="card"><div class="alert-ok"><?= e($sucesso) ?></div><p class="hint"><a href="/">Voltar ao início</a></p></section>
<?php else: ?>
<form class="card" method="post" id="formCad" data-envio-overlay="Enviando seu cadastro, não feche esta página...">
  <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
  <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
  <div class="grid2">
    <label>CNPJ *<input name="cnpj" id="cnpj" required maxlength="18" value="<?= e($f['cnpj']) ?>"></label>
    <label>Razão social *<input name="razao" id="razao" required maxlength="200" value="<?= e($f['razao']) ?>"></label>
  </div>
  <div class="grid2">
    <label>Responsável *<input name="nome" required maxlength="120" value="<?= e($f['nome']) ?>"></label>
    <label>Telefone *<input type="tel" name="telefone" required maxlength="20" placeholder="(11) 99999-9999" value="<?= e($f['telefone']) ?>"></label>
  </div>
  <div class="grid2">
    <label>E-mail *<input type="email" name="email" required value="<?= e($f['email']) ?>"></label>
    <label>Senha *<input type="password" name="senha" required minlength="8"></label>
  </div>
  <div class="actions"><button class="btn-gold" type="submit">Enviar cadastro</button></div>
  <p class="hint">Já tem acesso? <a href="/entrar.php">Entrar</a></p>
</form>
<?php endif; ?>
</main>
<script>
(function () {
  var form = document.getElementById('formCad');
  if (!form) return;
  var csrf = form.querySelector('input[name=csrf]').value;
  var cnpj = document.getElementById('cnpj');
  var razao = document.getElementById('razao');
  function digits(v) { return (v || '').replace(/\D/g, ''); }
  cnpj.addEventListener('blur', function () {
    var d = digits(cnpj.value);
    if (d.length !== 14) return;
    fetch('/cnpj.php?cnpj=' + d, { headers: { 'X-CSRF-Token': csrf }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.ok && j.razao_social && !razao.value.trim()) razao.value = j.razao_social; })
      .catch(function () {});
  });
})();
</script>
<?= sf_overlay() ?>
<?= sf_layout_fim() ?>
