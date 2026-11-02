<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$empresa = sf_empresa();
$cnpj = cnpj_digits($cliente['cnpj']);

/** Modalidade detalhada => modalidade da planilha de oportunidades. */
$modalidades = [
    'judicial_trabalhista' => ['Judicial trabalhista', 'Judicial'],
    'judicial_civel' => ['Judicial cível', 'Judicial'],
    'judicial_fiscal' => ['Judicial fiscal', 'Judicial'],
    'recursal' => ['Judicial recursal (depósito recursal)', 'Judicial'],
    'licitacao' => ['Licitação (garantia de proposta)', 'Licitação'],
    'execucao_contrato' => ['Execução de contrato (performance)', 'Contratual'],
    'adiantamento' => ['Adiantamento de pagamentos', 'Contratual'],
    'retencao' => ['Retenção de pagamentos', 'Contratual'],
    'aduaneira' => ['Aduaneira', 'Aduaneira'],
    'financeira' => ['Financeira', 'Financeira'],
    'outra' => ['Outra', 'Outra'],
];
const SF_COTACAO_MARCA = '[Cotação via portal]';

$f = ['modalidade' => '', 'valor' => '', 'processo' => '', 'prazo' => '', 'observacoes' => ''];
$erros = [];

$postExcedido = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0);
if ($postExcedido) {
    $erros[] = 'O tamanho total dos arquivos excedeu o limite permitido. Envie menos arquivos ou arquivos menores.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $valorFmt = normalize_money_input($f['valor']);
    $prazo = (int)preg_replace('/\D/', '', $f['prazo']);
    if (!sf_csrf_ok()) $erros[] = 'Sua sessão expirou. Revise os dados e envie novamente.';
    if (!isset($modalidades[$f['modalidade']])) $erros[] = 'Escolha a modalidade.';
    if ($valorFmt === '') $erros[] = 'Informe o valor da garantia.';
    if ($prazo < 1 || $prazo > 240) $erros[] = 'Informe o prazo em meses (de 1 a 240).';
    if (mb_strlen($f['processo']) > 120) $erros[] = 'O número do processo/edital está muito longo.';
    if (mb_strlen($f['observacoes']) > 3000) $erros[] = 'As observações estão muito longas.';
    $qtd = 0;
    $errosArq = sf_validar_arquivos($_FILES['documentos'] ?? [], $qtd);
    $erros = array_merge($erros, $errosArq);
    if ($qtd > SF_MAX_ARQUIVOS) $erros[] = 'Envie no máximo ' . SF_MAX_ARQUIVOS . ' arquivos.';

    if (!$erros) {
        try {
            ensure_sheet_structures();
            [$modNome, $modPlanilha] = $modalidades[$f['modalidade']];
            $descricao = SF_COTACAO_MARCA . ' Modalidade: ' . $modNome
                . ' | Valor da garantia: R$ ' . $valorFmt
                . ' | Prazo: ' . $prazo . ' meses'
                . ($f['processo'] !== '' ? ' | Processo/edital: ' . $f['processo'] : '')
                . ($f['observacoes'] !== '' ? ' | Observações: ' . $f['observacoes'] : '')
                . ' | Solicitante: ' . ($empresa['nome'] ?? '') . ' (' . ($empresa['email'] ?? '') . ')';
            $dono = strtolower(trim((string)($cliente['email_dono'] ?? '')));
            $autor = ['email' => $dono !== '' ? $dono : DEPOSITO_DONO_PORTAL, 'nome' => 'Empresa'];
            $warnings = [];
            $saved = save_new_opportunity($autor, $cliente, $cnpj, $descricao, $qtd > 0 ? ($_FILES['documentos'] ?? []) : [], 'Demanda', 'OP', $warnings, [
                'valor_estimado' => $valorFmt,
                'modalidade' => $modPlanilha,
                'prazo_meses' => (string)$prazo,
                'max_arquivos' => SF_MAX_ARQUIVOS,
            ]);
            $opId = $saved['id'];

            $nomesDocs = array_map(function ($x) { return (string)$x['nome']; }, $saved['docs']);
            $linhas = [
                'ID' => $opId,
                'Origem' => 'Portal da empresa (cliente.segurosfinanceiros.com.br) — cotação de seguro garantia',
                'Empresa' => ($cliente['razao_social'] ?? '') . ' (' . cnpj_format($cnpj) . ')',
                'Solicitante' => ($empresa['nome'] ?? '') . ' (' . ($empresa['email'] ?? '') . ')',
                'Responsável pelo cliente' => $autor['email'],
                'Modalidade' => $modNome . ' (' . $modPlanilha . ')',
                'Valor da garantia' => 'R$ ' . $valorFmt,
                'Prazo' => $prazo . ' meses',
                'Processo/edital' => $f['processo'] !== '' ? $f['processo'] : 'Não informado',
                'Observações' => $f['observacoes'] !== '' ? $f['observacoes'] : '—',
                'Documentos' => $nomesDocs ? implode(', ', $nomesDocs) : 'Nenhum',
            ];
            $htmlEquipe = '<h2>Nova demanda cadastrada</h2><table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#d0d7de;font-family:Arial,sans-serif;font-size:14px">';
            foreach ($linhas as $rot => $val) {
                $htmlEquipe .= '<tr><th align="left" style="background:#f3f6f9;white-space:nowrap">' . e($rot) . '</th><td>' . nl2br(e($val)) . '</td></tr>';
            }
            $htmlEquipe .= '</table>';
            $anexos = [];
            foreach ($saved['docs'] as $doc) {
                $bytes = @file_get_contents($doc['path']);
                if (is_string($bytes) && $bytes !== '') $anexos[] = ['nome' => $doc['nome'], 'bytes' => $bytes];
            }
            if (!rg_enviar_email_equipe('Nova oportunidade da Rede Garantia (' . $opId . ')', $htmlEquipe, $anexos)) {
                app_log('SF cotação ' . $opId . ': falha ao avisar a equipe.');
            }
            if ($warnings) app_log('SF cotação ' . $opId . ' avisos: ' . implode(' | ', $warnings));
            header('Location: /cotacao.php?ok=' . rawurlencode($opId), true, 303);
            exit;
        } catch (Exception $ex) {
            app_log('SF cotação: ' . $ex->getMessage());
            $erros[] = 'Não foi possível registrar a cotação agora. Tente novamente em instantes.';
        }
    }
}

$pedidos = [];
try {
    foreach (sheets()->getRecords(SHEET_OPPORTUNITIES, OPPORTUNITY_HEADER) as $op) {
        if (cnpj_digits($op['cnpj'] ?? '') !== $cnpj) continue;
        if (strpos((string)($op['descricao'] ?? ''), SF_COTACAO_MARCA) !== 0) continue;
        $pedidos[] = $op;
    }
    $pedidos = array_reverse($pedidos);
} catch (Exception $ex) {
    app_log('SF cotação lista: ' . $ex->getMessage());
}
$okId = preg_replace('/[^A-Za-z0-9-]/', '', (string)($_GET['ok'] ?? ''));

echo sf_layout_inicio('Cotação de seguro garantia');
echo sf_nav('cotacao');
?>
<main class="wrap main">
  <section class="card" style="margin-bottom:18px">
    <h1>Cotação de seguro garantia</h1>
    <p class="hint">Preencha os dados e anexe o que tiver (processo, edital, contrato). Nossa equipe retorna com a cotação.</p>
    <?php if ($okId !== ''): ?><div class="alert-ok">Pedido de cotação <?= e($okId) ?> recebido. Em breve entraremos em contato.</div><?php endif; ?>
    <?php if ($erros): ?><div class="alert alert-error"><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" data-envio-overlay="Enviando sua demanda, não feche esta página...">
      <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
      <p><strong>Empresa</strong><br><?= e($cliente['razao_social'] ?? '') ?> · CNPJ <?= e(cnpj_format($cnpj)) ?></p>
      <div class="grid2">
        <label>Modalidade *
          <select name="modalidade" required>
            <option value="">Selecione</option>
            <?php foreach ($modalidades as $k => $m): ?>
              <option value="<?= e($k) ?>" <?= $f['modalidade'] === $k ? 'selected' : '' ?>><?= e($m[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Valor da garantia (R$) *<input name="valor" required inputmode="decimal" placeholder="Ex.: 150.000,00" maxlength="20" value="<?= e($f['valor']) ?>"></label>
      </div>
      <div class="grid2">
        <label>Número do processo ou edital<input name="processo" maxlength="120" value="<?= e($f['processo']) ?>"></label>
        <label>Prazo (meses) *<input name="prazo" required type="number" min="1" max="240" step="1" inputmode="numeric" value="<?= e($f['prazo']) ?>"></label>
      </div>
      <label>Observações<textarea name="observacoes" rows="4" maxlength="3000"><?= e($f['observacoes']) ?></textarea></label>
      <label>Anexos<input type="file" name="documentos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
        <span class="hint">PDF, DOC, DOCX, XLS, XLSX, JPG ou PNG. Até <?= (int)SF_MAX_ARQUIVOS ?> arquivos de 10 MB. Se houver erro no formulário, selecione os arquivos de novo.</span></label>
      <div class="actions"><button class="btn-gold" type="submit">Pedir cotação</button></div>
    </form>
  </section>
  <section class="card">
    <h2>Suas cotações</h2>
    <table class="sf-tbl">
      <thead><tr><th>Pedido</th><th>Modalidade</th><th>Valor</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($pedidos as $p): ?>
        <?php preg_match('/Modalidade: ([^|]+)/', (string)($p['descricao'] ?? ''), $mm); ?>
        <tr>
          <td><strong><?= e($p['id'] ?? '') ?></strong><br><span class="hint"><?= e($p['data_cadastro'] ?? '') ?></span></td>
          <td><?= e(trim($mm[1] ?? ($p['modalidade'] ?? ''))) ?></td>
          <td><?= ($p['valor_estimado'] ?? '') !== '' ? 'R$ ' . e($p['valor_estimado']) : '—' ?></td>
          <td><?= e($p['status'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pedidos): ?><tr><td colspan="4">Nenhuma cotação ainda.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</main>
<?= sf_overlay() ?>
<?= sf_layout_fim() ?>
