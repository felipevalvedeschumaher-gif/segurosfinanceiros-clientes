<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$ativo = deposito_limite_ativo($cliente);
echo sf_layout_inicio('Área da empresa');
echo sf_nav('painel');
?>
<main class="wrap main">
  <section class="card">
    <h1><?= e($cliente['razao_social']) ?></h1>
    <p>CNPJ <?= e(cnpj_format($cliente['cnpj'])) ?></p>
    <p><strong>Status do limite:</strong> <?= e($cliente['status_limite'] ?: 'Em análise') ?></p>
    <?php if (limite_status_tem_relatorio($cliente['status_limite'] ?? '')): ?>
      <p><a class="btn-gold" href="/relatorio.php">Baixar relatório de limite</a></p>
    <?php endif; ?>
    <p><a href="/limite.php">Solicitar ou acompanhar limite</a></p>
    <?php if ($ativo): ?>
      <p><a href="/deposito.php">Solicitar depósito recursal</a></p>
    <?php else: ?>
      <p>O depósito recursal não está habilitado para esta empresa.</p>
    <?php endif; ?>
  </section>
</main>
<?= sf_layout_fim() ?>
