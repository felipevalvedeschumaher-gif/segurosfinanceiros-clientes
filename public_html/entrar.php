<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
if (sf_empresa()) { header('Location: /painel.php'); exit; }

$erro = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    try {
        ensure_sheet_structures();
        if (!sf_csrf_ok()) $erro = 'Sessão expirada. Tente novamente.';
        else {
            $acesso = empresa_autenticar($email, $senha);
            if (!$acesso) $erro = 'E-mail ou senha inválidos.';
            else { sf_entrar_empresa($acesso); header('Location: /painel.php'); exit; }
        }
    } catch (Exception $ex) {
        app_log('SF entrar: ' . $ex->getMessage());
        $erro = 'Não foi possível entrar agora.';
    }
}
echo sf_layout_inicio('Área da empresa');
?>
<section class="hero"><div class="wrap"><h1>Área da empresa</h1><p>Acompanhe seu limite e solicite depósito recursal.</p></div></section>
<main class="wrap main"><form class="card" method="post">
  <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
  <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
  <label>E-mail *<input type="email" name="email" required value="<?= e($email) ?>"></label>
  <label>Senha *<input type="password" name="senha" required></label>
  <div class="actions"><button class="btn-gold" type="submit">Entrar</button></div>
  <p class="hint">Ainda não tem acesso? <a href="/cadastrar.php">Cadastre a empresa</a></p>
</form></main>
<?= sf_layout_fim() ?>
