<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$campos = ['cpf_parte','nome_parte','cep','rua','numero','complemento','bairro','cidade','uf','tipo_recurso','numero_processo','vara','cidade_processo','uf_processo','email_advogado','email_financeiro'];
$f = array_fill_keys($campos, '');
$f['cep'] = (string)($cliente['cep'] ?? '');
$f['rua'] = (string)($cliente['logradouro'] ?? '');
$f['numero'] = (string)($cliente['numero'] ?? '');
$f['complemento'] = (string)($cliente['complemento'] ?? '');
$f['bairro'] = (string)($cliente['bairro'] ?? '');
$f['cidade'] = (string)($cliente['municipio'] ?? '');
$f['uf'] = (string)($cliente['uf'] ?? '');
$erros = [];
$habilitado = deposito_limite_ativo($cliente);

if ($habilitado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $k) $f[$k] = trim((string)($_POST[$k] ?? ''));
    try {
        ensure_sheet_structures();
        if (!sf_csrf_ok()) $erros[] = 'Sessão expirada.';
        else {
            $res = deposito_criar($cliente, $f, 'empresa', '');
            if ($res['ok']) { header('Location: /deposito.php?ok=' . rawurlencode($res['id'])); exit; }
            $erros = $res['erros'];
        }
    } catch (Exception $ex) {
        app_log('SF depósito: ' . $ex->getMessage());
        $erros[] = 'Não foi possível enviar agora.';
    }
}

$lista = [];
try { $lista = deposito_listar(cnpj_digits($cliente['cnpj'])); } catch (Exception $ex) { app_log($ex->getMessage()); }

echo sf_layout_inicio('Depósito recursal');
echo sf_nav('deposito');
?>
<main class="wrap main">
  <section class="card">
    <h1>Depósito recursal</h1>
    <?php if (!$habilitado): ?>
      <p>O depósito recursal não está habilitado para esta empresa.</p>
    <?php else: ?>
      <?php if (isset($_GET['ok'])): ?><p>Demanda <?= e($_GET['ok']) ?> recebida.</p><?php endif; ?>
      <?php if ($erros): ?><div class="alert alert-error"><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
        <?= deposito_formulario($f, $cliente['cnpj'], $cliente['razao_social'] ?? '') ?>
        <div class="actions"><button class="btn-gold" type="submit">Solicitar</button></div>
      </form>
    <?php endif; ?>
    <h2>Suas solicitações</h2>
    <ul class="emp-list">
      <?php foreach ($lista as $d): ?>
        <li><strong><?= e($d['id']) ?></strong> · <?= e(deposito_status_empresa($d['status'] ?? '')) ?> · R$ <?= e($d['valor_total']) ?></li>
      <?php endforeach; ?>
      <?php if (!$lista): ?><li>Nenhuma solicitação ainda.</li><?php endif; ?>
    </ul>
  </section>
</main>
<?= sf_layout_fim() ?>
