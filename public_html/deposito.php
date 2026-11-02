<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$empresa = sf_empresa();
$campos = ['cpf_parte','nome_parte','cep','rua','numero','complemento','bairro','cidade','uf','tipo_recurso','numero_processo','vara','cidade_processo','uf_processo','email_advogado','email_financeiro'];
$f = array_fill_keys($campos, '');   // endereço começa em branco: preenchido pelo CEP
$erros = [];
$habilitado = deposito_limite_ativo($cliente);

$postExcedido = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0);
if ($postExcedido) {
    $erros[] = 'O tamanho total dos arquivos excedeu o limite permitido. Envie menos arquivos ou arquivos menores.';
} elseif ($habilitado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $k) $f[$k] = trim((string)($_POST[$k] ?? ''));
    try {
        ensure_sheet_structures();
        if (!sf_csrf_ok()) $erros[] = 'Sua sessão expirou. Revise os dados e envie novamente.';
        else {
            $res = deposito_criar($cliente, $f, 'empresa', '', $_FILES['documentos'] ?? [], ['nome' => $empresa['nome'] ?? '', 'email' => $empresa['email'] ?? '']);
            if ($res['ok']) { header('Location: /deposito.php?ok=' . rawurlencode($res['id']), true, 303); exit; }
            $erros = $res['erros'];
        }
    } catch (Exception $ex) {
        app_log('SF depósito: ' . $ex->getMessage());
        $erros[] = 'Não foi possível enviar agora. Tente novamente em instantes.';
    }
}

$lista = [];
try { $lista = deposito_listar(cnpj_digits($cliente['cnpj'])); } catch (Exception $ex) { app_log($ex->getMessage()); }
$okId = preg_replace('/[^A-Z0-9-]/', '', (string)($_GET['ok'] ?? ''));

function sf_dep_links(array $d)
{
    $out = [];
    foreach (['emissoes', 'documentos'] as $campo) {
        foreach (deposito_docs($d[$campo] ?? '[]') as $doc) {
            $rot = $campo === 'emissoes' ? (($doc['tipo'] ?? '') !== '' ? $doc['tipo'] : 'Documento') . ': ' : 'Enviado: ';
            $out[] = '<a href="/deposito_download.php?id=' . rawurlencode($d['id']) . '&amp;campo=' . $campo . '&amp;arquivo=' . rawurlencode((string)($doc['arquivo'] ?? '')) . '">' . e($rot . ($doc['nome'] ?? 'arquivo')) . '</a>';
        }
    }
    return $out ? implode('<br>', $out) : '<span class="hint">—</span>';
}

echo sf_layout_inicio('Depósito recursal');
echo sf_nav('deposito');
?>
<main class="wrap main">
  <section class="card" style="margin-bottom:18px">
    <h1>Depósito recursal</h1>
    <?php if (!$habilitado): ?>
      <p>O depósito recursal não está habilitado para esta empresa.</p>
    <?php else: ?>
      <p class="hint">Informe o CEP e clique em Buscar CEP para preencher o endereço da outra parte. O valor é calculado sozinho.</p>
      <?php if ($okId !== ''): ?><div class="alert-ok">Demanda <?= e($okId) ?> recebida. Você será avisado quando o documento estiver disponível.</div><?php endif; ?>
      <?php if ($erros): ?><div class="alert alert-error"><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" data-envio-overlay="Enviando sua demanda, não feche esta página...">
        <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
        <?= deposito_formulario($f, $cliente['cnpj'], $cliente['razao_social'] ?? '') ?>
        <div class="actions"><button class="btn-gold" type="submit">Solicitar</button></div>
      </form>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Suas solicitações</h2>
    <table class="sf-tbl">
      <thead><tr><th>Demanda</th><th>Processo</th><th>Valor</th><th>Status</th><th>Arquivos</th></tr></thead>
      <tbody>
      <?php foreach ($lista as $d): ?>
        <tr>
          <td><strong><?= e($d['id']) ?></strong><br><span class="hint"><?= e($d['criado_em'] ?? '') ?></span></td>
          <td><?= e($d['numero_processo'] ?? '') ?></td>
          <td>R$ <?= e($d['valor_total']) ?></td>
          <td><?= e(deposito_status_empresa($d['status'] ?? '')) ?></td>
          <td class="sf-arquivos"><?= sf_dep_links($d) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?><tr><td colspan="5">Nenhuma solicitação ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</main>
<?= sf_overlay() ?>
<?= sf_layout_fim() ?>
