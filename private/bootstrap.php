<?php
/**
 * Bootstrap do portal de envio de documentos (cliente.segurosfinanceiros.com.br).
 * Reutiliza as funções, a planilha e o armazenamento do sistema principal (mesmo usuário Hestia).
 * Nada deste arquivo é exibido ao visitante.
 */
define('SF_APP_INCLUDES', '/home/sua_senha_admin/web/app.redegarantia.com.br/public_html/includes');
require_once SF_APP_INCLUDES . '/funcoes.php';
require_once __DIR__ . '/notificacoes.php';

const SF_MAX_ARQUIVOS = 10;               // mesmo limite da revisão de limite (limite_upload_max_count)
const SF_ORIGEM = 'link_cliente';
const SF_SITE_URL = 'https://segurosfinanceiros.com.br/';

header_remove('X-Powered-By');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_name('sfsess');
    session_start();
}

function sf_csrf_token()
{
    if (empty($_SESSION['sf_csrf'])) $_SESSION['sf_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['sf_csrf'];
}

function sf_csrf_ok()
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && !empty($_SESSION['sf_csrf']) && hash_equals($_SESSION['sf_csrf'], $t);
}

/** Lista de produtos = modalidades já usadas nas oportunidades do sistema. */
function sf_produtos()
{
    $labels = [
        'Judicial' => 'Seguro Garantia Judicial',
        'Licitação' => 'Seguro Garantia para Licitação',
        'Contratual' => 'Seguro Garantia Contratual',
        'Aduaneira' => 'Seguro Garantia Aduaneiro',
        'Financeira' => 'Seguro Garantia Financeiro',
        'Outra' => 'Outro / não sei informar',
    ];
    $out = [];
    foreach (OPORTUNIDADE_MODALIDADES as $m) $out[$m] = $labels[$m] ?? $m;
    return $out;
}

/** Retorna o parceiro dono do token (somente ativo) ou null. Nunca expõe o motivo. */
function sf_parceiro_por_token($token)
{
    $token = trim((string)$token);
    if (!preg_match('/^[a-f0-9]{16,64}$/i', $token)) return null;
    try {
        $u = find_user_by_token_indicacao($token);
    } catch (Exception $e) {
        app_log('SF portal token: ' . $e->getMessage());
        return null;
    }
    if (!$u) return null;
    if (user_status_normalize($u['status'] ?? '') !== 'Ativo') return null;
    return $u;
}

/** Valida os arquivos enviados com as mesmas regras do sistema (extensão, 10 MB, até 10 arquivos). */
function sf_validar_arquivos($files, &$qtd)
{
    $qtd = 0;
    $erros = [];
    $nomes = $files['name'] ?? [];
    if (!is_array($nomes)) return [];
    $allowed = opportunity_allowed_extensions();
    $max = opportunity_upload_max_bytes();
    foreach ($nomes as $i => $nome) {
        $nome = (string)$nome;
        $err = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || trim($nome) === '') continue;
        $qtd++;
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int)($files['size'][$i] ?? 0) > $max) {
            $erros[] = 'O arquivo "' . $nome . '" excede 10 MB.';
        } elseif ($err !== UPLOAD_ERR_OK) {
            $erros[] = 'Falha ao receber o arquivo "' . $nome . '". Tente novamente.';
        } elseif (!in_array($ext, $allowed, true)) {
            $erros[] = 'Formato não aceito: "' . $nome . '". Envie PDF, DOC, DOCX, XLS, XLSX, JPG ou PNG.';
        } elseif ((int)($files['size'][$i] ?? 0) <= 0) {
            $erros[] = 'O arquivo "' . $nome . '" está vazio.';
        }
    }
    return $erros;
}

/** Monta a linha da aba Indicacoes alinhada ao cabeçalho real da planilha. */
function sf_linha_indicacao(array $dados)
{
    $hdr = sheets()->getSheetValues(SHEET_INDICACOES . '!1:1');
    $hdr = $hdr[0] ?? INDICACOES_HEADER;
    $row = [];
    foreach ($hdr as $i => $h) {
        $row[$i] = (string)($dados[mb_strtolower(trim((string)$h))] ?? '');
    }
    return $row;
}

function sf_layout_inicio($titulo)
{
    $t = e($titulo) . ' | Seguros Financeiros';
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<meta name="description" content="Envio seguro de informações e documentos para análise - Seguros Financeiros.">'
        . '<title>' . $t . '</title>'
        . '<link rel="icon" type="image/svg+xml" href="/favicon.svg">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="/assets/estilo.css?v=1">'
        . '</head><body>'
        . '<div class="topbar"><div class="wrap topbar-in">'
        . '<span class="topbar-susep">&#10003; Autorizada e Regulamentada pela SUSEP</span>'
        . '<span class="topbar-contato"><a href="tel:+551135662664">(11) 3566-2664</a></span>'
        . '</div></div>'
        . '<header class="header"><div class="wrap header-in">'
        . '<a class="logo" href="' . e(SF_SITE_URL) . '" rel="noreferrer">'
        . '<span class="logo-mark"><img src="/assets/logo-mark.svg" alt="" width="24" height="24"></span>'
        . '<span><span class="logo-name">SEGUROS <span class="gold">FINANCEIROS</span></span>'
        . '<span class="logo-sub">Advisory &amp; Corporate Risk</span></span></a>'
        . '<span class="header-tag">Envio seguro de documentos</span>'
        . '</div></header>';
}

function sf_layout_fim($js = false)
{
    return '<footer class="footer"><div class="wrap">'
        . '<div class="footer-grid">'
        . '<div><span class="logo-name">SEGUROS <span class="gold">FINANCEIROS</span></span>'
        . '<p>Especialistas em Seguros de Garantia, Crédito, Prestamista Imobiliário (CNSP 447) e Responsabilidade para empresas em todo o Brasil.</p></div>'
        . '<div><h3>Canais de atendimento</h3><ul>'
        . '<li><a href="mailto:contato@segurosfinanceiros.com.br">contato@segurosfinanceiros.com.br</a></li>'
        . '<li><a href="tel:+551135662664">(11) 3566-2664</a></li>'
        . '<li class="muted">Segunda a sexta, 8h30 às 18h30</li></ul></div>'
        . '</div>'
        . '<div class="footer-copy">&copy; ' . date('Y') . ' Seguros Financeiros. Seus dados são tratados com sigilo e utilizados apenas para a análise da sua solicitação.</div>'
        . '</div></footer>'
        . ($js ? '<script src="/assets/envio.js?v=1"></script>' : '')
        . '</body></html>';
}

function sf_pagina_neutra($titulo, $mensagem, $status = 200)
{
    http_response_code($status);
    echo sf_layout_inicio($titulo);
    echo '<section class="hero"><div class="wrap"><h1>' . e($titulo) . '</h1></div></section>'
        . '<main class="wrap main"><div class="card card-center"><p>' . e($mensagem) . '</p>'
        . '<p><a class="btn-gold" href="' . e(SF_SITE_URL) . '" rel="noreferrer">Conhecer a Seguros Financeiros</a></p></div></main>';
    echo sf_layout_fim();
    exit;
}
