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
    $cliente = find_client_by_cnpj($e['cnpj']);
    if (!$cliente) {
        unset($_SESSION['sf_empresa']);
        header('Location: /entrar.php', true, 303);
        exit;
    }
    return $cliente;
}

function sf_nav($ativo)
{
    $itens = [
        'painel' => ['/painel.php', 'Início'],
        'limite' => ['/limite.php', 'Limite'],
        'deposito' => ['/deposito.php', 'Depósito recursal'],
    ];
    $html = '<nav class="emp-nav wrap">';
    foreach ($itens as $k => $it) {
        $html .= '<a href="' . $it[0] . '"' . ($ativo === $k ? ' class="on"' : '') . '>' . $it[1] . '</a>';
    }
    $html .= '<a href="/sair.php">Sair</a></nav>';
    return $html;
}
