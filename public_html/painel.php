<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$ativo = deposito_limite_ativo($cliente);
echo sf_layout_inicio('Painel');
echo sf_nav('painel');
?>
<main class="wrap main">
  <section class="card" style="margin-bottom:18px">
    <h1><?= e($cliente['razao_social']) ?></h1>
    <p>CNPJ <?= e(cnpj_format($cliente['cnpj'])) ?></p>
    <p><strong>Status do limite:</strong> <?= e($cliente['status_limite'] ?: 'Em análise') ?></p>
    <?php if (limite_status_tem_relatorio($cliente['status_limite'] ?? '')): ?>
      <p><a class="btn-gold" href="/relatorio.php">Baixar relatório de limite</a></p>
    <?php endif; ?>
  </section>
  <div class="sf-cards">
    <section class="card">
      <h2>Pedir limite</h2>
      <p>Solicite a análise ou revisão do limite e acompanhe os pedidos.</p>
      <a class="btn-gold" href="/limite.php">Pedir limite</a>
    </section>
    <section class="card">
      <h2>Cotação de seguro garantia</h2>
      <p>Judicial, recursal, licitação, execução de contrato e outras modalidades.</p>
      <a class="btn-gold" href="/cotacao.php">Pedir cotação</a>
    </section>
    <section class="card">
      <h2>Depósito recursal</h2>
      <?php if ($ativo): ?>
        <p>Envie a demanda e baixe a apólice ou guia quando estiver pronta.</p>
        <a class="btn-gold" href="/deposito.php">Depósito recursal</a>
      <?php else: ?>
        <p>O depósito recursal ainda não está habilitado para esta empresa.</p>
        <a class="btn-gold" href="/deposito.php">Ver solicitações</a>
      <?php endif; ?>
    </section>
  </div>
</main>
<?= sf_layout_fim() ?>
