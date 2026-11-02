<?php
require_once dirname(__DIR__) . '/private/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function sf_json($arr, $code = 200) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if ((empty($_SESSION['sf_link_ok']) && empty($_SESSION['sf_cadastro_form'])) || !sf_csrf_ok()) sf_json(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.'], 403);
$n = (int)($_SESSION['sf_cnpj_consultas'] ?? 0);
if ($n >= 20) sf_json(['ok' => false, 'erro' => 'Limite de consultas atingido. Preencha a razão social manualmente.'], 429);
$_SESSION['sf_cnpj_consultas'] = $n + 1;

$cnpj = cnpj_digits($_GET['cnpj'] ?? '');
if (!cnpj_valid($cnpj)) sf_json(['ok' => false, 'erro' => 'CNPJ inválido. Verifique os dígitos.']);
session_write_close();
$r = cnpj_lookup($cnpj);
if (empty($r['ok'])) sf_json(['ok' => false, 'erro' => (string)($r['erro'] ?? 'Não foi possível consultar agora. Preencha manualmente.')]);
$d = $r['dados'] ?? [];
sf_json(['ok' => true, 'razao_social' => (string)($d['razao_social'] ?? ''), 'situacao' => (string)($d['situacao'] ?? '')]);
