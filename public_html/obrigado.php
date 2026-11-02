<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
$protocolo = (string)($_SESSION['sf_protocolo'] ?? '');
echo sf_layout_inicio('Informações recebidas');
?>
<section class="hero"><div class="wrap">
  <span class="eyebrow">Envio concluído</span>
  <h1>Recebemos suas <span class="gold-text">informações</span></h1>
</div></section>
<main class="wrap main">
  <div class="card card-center">
    <div class="ok-icon" aria-hidden="true">&#10003;</div>
    <h2>Obrigado!</h2>
    <p>Seus dados e documentos foram recebidos com sucesso. Nossa equipe de especialistas fará a análise e entrará em contato em breve pelo e-mail ou telefone informados.</p>
    <?php if ($protocolo !== ''): ?><p class="protocolo">Protocolo: <strong><?= e($protocolo) ?></strong></p><?php endif; ?>
    <p><a class="btn-gold" href="<?= e(SF_SITE_URL) ?>" rel="noreferrer">Conhecer a Seguros Financeiros</a></p>
  </div>
</main>
<?= sf_layout_fim() ?>
