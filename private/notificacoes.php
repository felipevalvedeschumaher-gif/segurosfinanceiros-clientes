<?php
/** Avisos internos (equipe + parceiro) de um envio feito pela página do cliente. O cliente não recebe e-mail. */
function sf_notificar_envio($id, array $parceiro, array $f, $cnpjDig, $produtoLabel, array $uploads)
{
        $emailParceiro = strtolower(trim((string)($parceiro['email'] ?? '')));
        // Mesmo aviso interno usado nas indicações (indique.php), com a origem identificada
        $html = '<h2>Nova indicação recebida</h2>'
            . '<p><strong>ID:</strong> ' . e($id) . '</p>'
            . '<p><strong>Origem:</strong> link do parceiro para clientes (página Seguros Financeiros)</p>'
            . '<p><strong>Parceiro:</strong> ' . e($parceiro['nome'] ?? '') . ' (' . e($emailParceiro) . ')</p>'
            . '<p><strong>Empresa:</strong> ' . e($f['razao']) . ' · ' . e(cnpj_format($cnpjDig)) . '</p>'
            . '<p><strong>Produto:</strong> ' . e($produtoLabel) . '</p>'
            . '<p><strong>Contato:</strong> ' . e($f['contato']) . ' · ' . e($f['email']) . ' · ' . e($f['telefone']) . '</p>'
            . '<p><strong>Descrição:</strong><br>' . nl2br(e($f['descricao'])) . '</p>';
        $anexos = [];
        foreach ($uploads as $u) {
            $bytes = @file_get_contents($u['path']);
            if (is_string($bytes) && $bytes !== '') $anexos[] = ['nome' => $u['nome'], 'bytes' => $bytes];
        }
        $okEquipe = rg_enviar_email_equipe('Nova indicação recebida (' . $id . ')', $html, $anexos);

        $htmlParceiro = '<h2>Seu cliente enviou informações pelo seu link</h2>'
            . '<p>Recebemos um envio feito pelo seu link para clientes. A equipe vai analisar e você acompanha em Indicações na plataforma.</p>'
            . '<p><strong>ID:</strong> ' . e($id) . '<br>'
            . '<strong>Empresa:</strong> ' . e($f['razao']) . ' (' . e(cnpj_format($cnpjDig)) . ')<br>'
            . '<strong>Produto:</strong> ' . e($produtoLabel) . '<br>'
            . '<strong>Contato:</strong> ' . e($f['contato']) . '<br>'
            . '<strong>Documentos:</strong> ' . count($uploads) . ' arquivo(s)</p>';
        $okParceiro = $emailParceiro !== ''
            ? rg_enviar_email($emailParceiro, 'Rede Garantia - Novo envio de cliente pelo seu link (' . $id . ')', $htmlParceiro)
            : false;
        app_log('SF portal ' . $id . ' e-mails equipe=' . ($okEquipe ? 'ok' : 'fail') . ' parceiro=' . ($okParceiro ? 'ok' : 'fail'));
}
