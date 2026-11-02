<?php
/** Sessão da empresa no portal Seguros Financeiros. Não usa a sessão da Rede Garantia. */

function sf_empresa()
{
    return $_SESSION['sf_empresa'] ?? null;
}

function sf_entrar_empresa(array $acesso)
{
    session_regenerate_id(true);
    $_SESSION['sf_empresa'] = [
        'cnpj' => cnpj_digits($acesso['cnpj'] ?? ''),
        'email' => strtolower((string)($acesso['email'] ?? '')),
        'nome' => (string)($acesso['nome'] ?? ''),
    ];
    unset($_SESSION['sf_csrf']);
}

function sf_exigir_empresa()
{
    $e = sf_empresa();
    if (!$e) {
        header('Location: /entrar.php', true, 303);
        exit;
    }
    // A cada página: o acesso precisa continuar ativo (pendente/recusado/bloqueado derruba a sessão).
    $acesso = empresa_por_email($e['email'] ?? '');
    if (!$acesso || empresa_status($acesso) !== 'ativo' || cnpj_digits($acesso['cnpj'] ?? '') !== ($e['cnpj'] ?? '')) {
        unset($_SESSION['sf_empresa']);
        header('Location: /entrar.php', true, 303);
        exit;
    }
    $cliente = find_client_by_cnpj($e['cnpj']);
    if (!$cliente) {
        unset($_SESSION['sf_empresa']);
        header('Location: /entrar.php', true, 303);
        exit;
    }
    return $cliente;
}

/** Menu da empresa logada: barra visível logo abaixo do cabeçalho. */
function sf_nav($ativo)
{
    $itens = [
        'painel' => ['/painel.php', 'Painel'],
        'limite' => ['/limite.php', 'Pedir limite'],
        'cotacao' => ['/cotacao.php', 'Cotação seguro garantia'],
        'deposito' => ['/deposito.php', 'Depósito recursal'],
    ];
    $emp = sf_empresa();
    $html = sf_menu_css() . '<nav class="sf-menu" aria-label="Menu da empresa"><div class="wrap sf-menu-in">';
    foreach ($itens as $k => $it) {
        $html .= '<a href="' . $it[0] . '"' . ($ativo === $k ? ' class="on" aria-current="page"' : '') . '>' . e($it[1]) . '</a>';
    }
    $html .= '<span class="sf-menu-user">' . e($emp['email'] ?? '') . '</span>'
        . '<a class="sf-menu-sair" href="/sair.php">Sair</a></div></nav>';
    return $html;
}

/** Menu das páginas públicas (sem login). */
function sf_nav_publico($ativo = '')
{
    if (sf_empresa()) return sf_nav($ativo);
    $html = sf_menu_css() . '<nav class="sf-menu" aria-label="Menu"><div class="wrap sf-menu-in">'
        . '<a href="/"' . ($ativo === 'inicio' ? ' class="on" aria-current="page"' : '') . '>Início</a>'
        . '<a href="/entrar.php"' . ($ativo === 'entrar' ? ' class="on" aria-current="page"' : '') . '>Entrar</a>'
        . '<a class="sf-menu-cta' . ($ativo === 'cadastrar' ? ' on' : '') . '" href="/cadastrar.php">Criar cadastro</a>'
        . '</div></nav>';
    return $html;
}

function sf_menu_css()
{
    return '<link rel="stylesheet" href="/assets/portal.css?v=1">';
}

/** Mesma tela "Enviando…" usada nos formulários da plataforma. */
function sf_overlay()
{
    return deposito_overlay_html();
}
