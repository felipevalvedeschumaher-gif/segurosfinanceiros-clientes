<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$erro = '';
$descricao = '';
$cnpj = cnpj_digits($cliente['cnpj']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    try {
        if (!sf_csrf_ok()) $erro = 'Sessão expirada.';
        elseif (mb_strlen($descricao) < 10) $erro = 'Descreva o pedido com pelo menos 10 caracteres.';
        else {
            $qtd = 0;
            $errosArq = sf_validar_arquivos($_FILES['documentos'] ?? [], $qtd);
            if ($qtd < 1) $erro = 'Anexe ao menos um documento.';
            elseif ($errosArq) $erro = $errosArq[0];
            else {
                $dono = strtolower(trim((string)($cliente['email_dono'] ?? '')));
                $autor = ['email' => $dono !== '' ? $dono : 'direto@segurosfinanceiros.com.br', 'nome' => 'Empresa'];
                $warnings = [];
                save_new_opportunity($autor, $cliente, $cnpj, $descricao, $_FILES['documentos'] ?? [], 'Revisao de Limite', 'SL', $warnings, [
                    'max_arquivos' => SF_MAX_ARQUIVOS,
                ]);
                header('Location: /limite.php?ok=1');
                exit;
            }
        }
    } catch (Exception $ex) {
        app_log('SF limite: ' . $ex->getMessage());
        $erro = 'Não foi possível registrar a solicitação agora.';
    }
}

$pedidos = [];
try {
    foreach (sheets()->getRecords(SHEET_OPPORTUNITIES, OPPORTUNITY_HEADER) as $op) {
        if (cnpj_digits($op['cnpj'] ?? '') !== $cnpj) continue;
        if (opportunity_tipo_normalize($op['tipo'] ?? '') !== 'Revisao de Limite') continue;
        $pedidos[] = $op;
    }
} catch (Exception $ex) {
    app_log('SF limite lista: ' . $ex->getMessage());
}

echo sf_layout_inicio('Pedir limite');
echo sf_nav('limite');
?>
<main class="wrap main">
  <section class="card">
    <h1>Pedir limite</h1>
    <p>Status atual: <strong><?= e($cliente['status_limite'] ?: 'Em análise') ?></strong></p>
    <?php if (isset($_GET['ok'])): ?><div class="alert-ok">Solicitação enviada.</div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-error"><?= e($erro) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" data-envio-overlay="Enviando sua demanda, não feche esta página...">
      <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
      <label>O que você precisa *<textarea name="descricao" rows="5" required><?= e($descricao) ?></textarea></label>
      <label>Documentos *<input type="file" name="documentos[]" multiple required></label>
      <div class="actions"><button class="btn-gold" type="submit">Enviar</button></div>
    </form>
    <h2>Acompanhamento</h2>
    <ul class="emp-list">
      <?php foreach ($pedidos as $p): ?>
        <li><strong><?= e($p['id'] ?? '') ?></strong> · <?= e($p['status'] ?? '') ?><br><?= e($p['descricao'] ?? '') ?></li>
      <?php endforeach; ?>
      <?php if (!$pedidos): ?><li>Nenhuma solicitação ainda.</li><?php endif; ?>
    </ul>
  </section>
</main>
<?= sf_overlay() ?>
<?= sf_layout_fim() ?>
