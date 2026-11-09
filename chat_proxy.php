<?php
// ============================================================
// Proxy do chat de IA da home. O navegador do visitante chama ESTE
// arquivo (não a Anthropic direto), então a chave de API nunca fica
// exposta no código-fonte da página.
//
// ANTES DE IR PRA PRODUÇÃO: preencha ANTHROPIC_API_KEY com uma chave
// real, gerada em https://console.anthropic.com
// ============================================================

define('ANTHROPIC_API_KEY', 'COLE_AQUI_A_CHAVE_REAL_DA_ANTHROPIC');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

if (ANTHROPIC_API_KEY === 'COLE_AQUI_A_CHAVE_REAL_DA_ANTHROPIC') {
    http_response_code(503);
    echo json_encode(['error' => 'Chat ainda não configurado. Fale com a gente pelo WhatsApp!']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$history = $input['messages'] ?? [];

// Limite simples pra evitar abuso: no máximo 20 mensagens de histórico por requisição
if (!is_array($history) || count($history) > 20) {
    http_response_code(400);
    echo json_encode(['error' => 'Histórico de conversa inválido.']);
    exit;
}

$systemPrompt = "Você é a assistente virtual da MM'S Boutique, uma loja de roupas infantil, adulto, masculina e plus size em Natal/RN (desde 2022). Lojas: Loja 01 - R. Santa Luzia, 1550, Igapó (seg a sáb, 9h-18h); Loja 02 - SuperFácil Atacado, SGA (seg a sáb, 9h-20h). WhatsApp Igapó: (84) 98801-3059. WhatsApp SuperFácil: (84) 99900-1179. Instagram: @usemmsboutique. Seja simpática, breve e objetiva, em português. Se não souber uma informação específica sobre estoque/preço, sugira falar no WhatsApp da loja.";

$payload = json_encode([
    'model' => 'claude-sonnet-4-6',
    'max_tokens' => 300,
    'system' => $systemPrompt,
    'messages' => $history,
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode >= 400) {
    error_log('Erro no proxy do chat: ' . $curlError . ' | HTTP ' . $httpCode . ' | ' . $response);
    http_response_code(502);
    echo json_encode(['error' => 'Não consegui responder agora. Tenta de novo ou chama no WhatsApp!']);
    exit;
}

echo $response;
