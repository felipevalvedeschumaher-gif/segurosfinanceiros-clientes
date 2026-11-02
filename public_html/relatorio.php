<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
require_once dirname(__DIR__) . '/private/empresa_portal.php';
$cliente = sf_exigir_empresa();
$cnpj = cnpj_digits($cliente['cnpj']);

try {
    if (!limite_status_tem_relatorio($cliente['status_limite'] ?? '')) {
        throw new Exception('sem relatório');
    }
    $arquivo = limite_relatorio_find($cnpj, 'cliente');
    if (!$arquivo || empty($arquivo['id']) || ($arquivo['variante'] ?? '') !== 'cliente') {
        throw new Exception('pdf cliente ausente');
    }
    $bytes = gdrive_download_bytes($arquivo['id'], GDRIVE_SCOPE_READONLY);
    if (!is_string($bytes) || strpos(substr($bytes, 0, 1024), '%PDF-') === false) {
        throw new Exception('arquivo inválido');
    }
    $nome = 'relatorio-limite-' . $cnpj . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nome . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
} catch (Exception $ex) {
    app_log('SF relatório: ' . $ex->getMessage());
    sf_pagina_neutra('Relatório indisponível', 'O relatório de limite ainda não está disponível para esta empresa.', 404);
}
