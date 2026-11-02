<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();

$id = trim((string)($_GET['id'] ?? ''));
$campo = (string)($_GET['campo'] ?? '');
$arquivo = (string)($_GET['arquivo'] ?? '');
try {
    // A empresa baixa só o resultado (apólice/guia/comprovante) e o que ela mesma enviou, do próprio CNPJ.
    if (!in_array($campo, ['emissoes', 'documentos'], true)) throw new Exception('Campo não permitido.');
    $d = $id !== '' ? deposito_por_id($id) : null;
    if (!$d || cnpj_digits($d['cnpj'] ?? '') !== cnpj_digits($cliente['cnpj'] ?? '')) throw new Exception('Sem permissão.');
    $arq = deposito_arquivo($d, $campo, $arquivo);
    if (!$arq) throw new Exception('Arquivo não encontrado.');
    deposito_enviar_arquivo($arq);
} catch (Exception $ex) {
    app_log('SF download depósito: ' . $ex->getMessage());
    sf_pagina_neutra('Arquivo indisponível', 'Arquivo não encontrado ou acesso não autorizado.', 404);
}
