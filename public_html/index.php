<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';

$token = trim((string)($_GET['p'] ?? $_POST['p'] ?? ''));
if ($token === '') {
    // Sem link de parceiro: página inicial do portal da empresa (Entrar / Criar cadastro).
    require_once dirname(__DIR__) . '/private/empresa_portal.php';
    $logada = sf_empresa();
    echo sf_layout_inicio('Área da empresa');
    echo sf_nav_publico('inicio');
    echo '<section class="hero"><div class="wrap"><h1>Área da empresa <span class="gold-text">Seguros Financeiros</span></h1>'
        . '<p>Acompanhe o limite da sua empresa, peça cotações de seguro garantia e solicite depósito recursal em um só lugar.</p>'
        . '<div class="sf-landing-acoes">'
        . ($logada
            ? '<a class="btn-gold" href="/painel.php">Ir para o painel</a>'
            : '<a class="btn-gold" href="/entrar.php">Entrar</a><a class="btn-outline" href="/cadastrar.php">Criar cadastro</a>')
        . '</div></div></section>'
        . '<main class="wrap main"><div class="sf-cards">'
        . '<section class="card"><h2>Limite de crédito</h2><p>Peça a análise ou revisão do limite da empresa e acompanhe o status.</p></section>'
        . '<section class="card"><h2>Cotação de seguro garantia</h2><p>Judicial, recursal, licitação, execução de contrato e outras modalidades.</p></section>'
        . '<section class="card"><h2>Depósito recursal</h2><p>Envie a demanda com os dados do processo e receba a apólice ou guia por aqui.</p></section>'
        . '</div></main>';
    echo sf_layout_fim();
    exit;
}
$parceiro = sf_parceiro_por_token($token);
if (!$parceiro) {
    http_response_code(404);
echo sf_layout_inicio('Link inválido');
echo '<section class="hero"><div class="wrap"><h1>Link inválido</h1></div></section>'
  . '<main class="wrap main"><div class="card card-center"><p>Este link não é válido ou não está mais disponível. Por favor, solicite um novo link a quem o enviou.</p>'
  . '<p><a class="btn-gold" href="/entrar.php">Área da empresa</a></p></div></main>';
echo sf_layout_fim();
exit;
}
$_SESSION['sf_link_ok'] = true;

$produtos = sf_produtos();
$erros = [];
$f = [
    'cnpj' => '', 'razao' => '', 'contato' => '', 'email' => '', 'telefone' => '', 'produto' => '', 'descricao' => '',
];

$postExcedido = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0);
if ($postExcedido) {
    $erros[] = 'O tamanho total dos arquivos excedeu o limite permitido. Envie menos arquivos ou arquivos menores.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$postExcedido) {
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $cnpjDig = cnpj_digits($f['cnpj']);
    $telDig = preg_replace('/\D/', '', $f['telefone']);

    if (!sf_csrf_ok()) {
        $erros[] = 'Sua sessão expirou. Revise os dados e envie novamente.';
    }
    if (trim((string)($_POST['website'] ?? '')) !== '') {   // honeypot anti-robô
        $erros[] = 'Não foi possível enviar. Tente novamente.';
    }
    if (!cnpj_valid($cnpjDig)) $erros[] = 'Informe um CNPJ válido.';
    if (mb_strlen($f['razao']) < 2) $erros[] = 'Informe a razão social da empresa.';
    if (mb_strlen($f['contato']) < 2) $erros[] = 'Informe o nome do responsável pelo contato.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
    if (strlen($telDig) < 10 || strlen($telDig) > 13) $erros[] = 'Informe um telefone válido com DDD.';
    if (!isset($produtos[$f['produto']])) $erros[] = 'Selecione o produto desejado.';
    if (mb_strlen($f['descricao']) < 10) $erros[] = 'Descreva a operação com pelo menos 10 caracteres.';

    $qtdArquivos = 0;
    $errosArq = sf_validar_arquivos($_FILES['documentos'] ?? [], $qtdArquivos);
    if ($qtdArquivos === 0) $erros[] = 'Anexe ao menos um documento.';
    elseif ($qtdArquivos > SF_MAX_ARQUIVOS) $erros[] = 'Você pode anexar no máximo ' . SF_MAX_ARQUIVOS . ' documentos.';
    $erros = array_merge($erros, $errosArq);

    if (!$erros) {
        try {
            ensure_sheet_structures();
            $id = generate_indicacao_id();
            $warnings = [];
            ensure_indicacoes_storage_dir();
            $uploads = save_uploads_generic(indicacoes_base_dir(), $id, $_FILES['documentos'] ?? [], $warnings, SF_MAX_ARQUIVOS);
            if (!$uploads) throw new Exception('Nenhum arquivo salvo: ' . implode(' | ', $warnings));
            if ($warnings) app_log('SF portal ' . $id . ' avisos upload: ' . implode(' | ', $warnings));
            $docs = array_map(function ($u) {
                return ['nome' => $u['nome'], 'arquivo' => $u['arquivo'], 'drive_id' => $u['drive_id'] ?? ''];
            }, $uploads);

            $emailParceiro = strtolower(trim((string)($parceiro['email'] ?? '')));
            $produtoLabel = $produtos[$f['produto']];
            $descricaoPlanilha = clean_cell('[' . $produtoLabel . '] ' . $f['descricao']);

            sheets()->appendRow(SHEET_INDICACOES, sf_linha_indicacao([
                'id' => $id,
                'token_parceiro' => $token,
                'email_parceiro' => $emailParceiro,
                'razao' => clean_cell($f['razao']),
                'cnpj' => cnpj_format($cnpjDig),
                'contato' => clean_cell($f['contato']),
                'telefone' => clean_cell($f['telefone']),
                'descricao' => $descricaoPlanilha,
                'documentos' => json_encode($docs, JSON_UNESCAPED_UNICODE),
                'status' => 'Nova',
                'data_cadastro' => date('d/m/Y H:i:s'),
                'produto' => $f['produto'],
                'email_cliente' => strtolower(clean_cell($f['email'])),
                'origem' => SF_ORIGEM,
            ]));

            $_SESSION['sf_protocolo'] = substr($id, 3);
            unset($_SESSION['sf_csrf']);
            header('Location: /obrigado.php', true, 303);
            header('Content-Length: 0');
            if (function_exists('fastcgi_finish_request')) {
                session_write_close();
                fastcgi_finish_request();
            }
            ignore_user_abort(true);
            set_time_limit(180);

            try {
                sf_notificar_envio($id, $parceiro, $f, $cnpjDig, $produtoLabel, $uploads);
            } catch (Throwable $t) {
                app_log('SF portal avisos ' . $id . ': ' . $t->getMessage());
            }
            exit;
        } catch (Exception $ex) {
            app_log('SF portal salvar: ' . $ex->getMessage());
            $erros = ['Não foi possível enviar suas informações agora. Tente novamente em instantes.'];
        }
    }
}

echo sf_layout_inicio('Envio de informações para análise');
?>
<section class="hero"><div class="wrap">
  <span class="eyebrow">Análise de seguros para empresas</span>
  <h1>Envie as informações da sua <span class="gold-text">operação</span></h1>
  <p>Preencha os dados abaixo e anexe os documentos. Nossa equipe de especialistas fará a análise e entrará em contato.</p>
</div></section>

<main class="wrap main">
  <form class="card" method="post" enctype="multipart/form-data" id="formEnvio" novalidate>
    <input type="hidden" name="csrf" value="<?= e(sf_csrf_token()) ?>">
    <input type="hidden" name="p" value="<?= e($token) ?>">
    <div class="hp" aria-hidden="true"><label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <?php if ($erros): ?>
      <div class="alert alert-error" role="alert"><strong>Revise os itens abaixo:</strong><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <h2 class="sec-title"><span>1</span> Dados da empresa</h2>
    <div class="grid2">
      <label>CNPJ *
        <input name="cnpj" id="cnpj" inputmode="numeric" autocomplete="off" required maxlength="18" placeholder="00.000.000/0000-00" value="<?= e($f['cnpj']) ?>">
        <small class="hint" id="cnpjHint">Ao informar o CNPJ, buscamos a razão social automaticamente.</small>
      </label>
      <label>Razão social *
        <input name="razao" id="razao" required maxlength="200" value="<?= e($f['razao']) ?>">
      </label>
    </div>

    <h2 class="sec-title"><span>2</span> Contato</h2>
    <div class="grid3">
      <label>Nome do responsável *
        <input name="contato" required maxlength="120" autocomplete="name" value="<?= e($f['contato']) ?>">
      </label>
      <label>E-mail *
        <input type="email" name="email" required maxlength="160" autocomplete="email" value="<?= e($f['email']) ?>">
      </label>
      <label>Telefone / WhatsApp *
        <input name="telefone" id="telefone" required inputmode="tel" maxlength="20" autocomplete="tel" placeholder="(11) 90000-0000" value="<?= e($f['telefone']) ?>">
      </label>
    </div>

    <h2 class="sec-title"><span>3</span> Operação</h2>
    <label>Produto *
      <select name="produto" required>
        <option value="">Selecione</option>
        <?php foreach ($produtos as $val => $lab): ?>
          <option value="<?= e($val) ?>" <?= $f['produto'] === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Descreva a operação *
      <textarea name="descricao" rows="6" required maxlength="1900" placeholder="Ex.: objeto do contrato ou processo, valor a ser garantido, prazo, órgão ou parte envolvida e qualquer informação útil."><?= e($f['descricao']) ?></textarea>
    </label>

    <h2 class="sec-title"><span>4</span> Documentos</h2>
    <div class="upload" id="upload" data-max-files="<?= SF_MAX_ARQUIVOS ?>" data-max-bytes="10485760" data-allowed-ext="pdf,doc,docx,xls,xlsx,jpg,jpeg,png">
      <p class="hint">Até <?= SF_MAX_ARQUIVOS ?> arquivos · 10 MB por arquivo · PDF, DOC, DOCX, XLS, XLSX, JPG ou PNG. Sugestões: contrato social, balanços e DRE recentes, edital/contrato ou peça do processo.</p>
      <label class="upload-drop" for="documentos">
        <span class="upload-cta">+ Adicionar documentos</span>
        <span class="hint" id="uploadStatus">Nenhum documento adicionado.</span>
        <input type="file" id="documentos" name="documentos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
      </label>
      <ul class="upload-list" id="uploadList"></ul>
      <p class="alert alert-error" id="uploadErro" hidden></p>
    </div>

    <div class="actions">
      <p class="hint">Ao enviar, você concorda que os dados sejam utilizados exclusivamente para a análise desta solicitação.</p>
      <button class="btn-gold" type="submit" id="btnEnviar">Enviar para análise</button>
    </div>
  </form>
</main>
<?= sf_layout_fim(true) ?>
