<?php
// ============================================================
// Segredo compartilhado das sincronizações (stock_sync.php e
// catalog_sync.php). Troque TROCAR_AQUI por um valor longo e único
// (40+ letras e números) e use EXATAMENTE o mesmo valor no programa
// do PC da loja (C:\mms\sync-config.ps1) e/ou no n8n.
// Este arquivo NUNCA deve ir para o GitHub com o valor real.
// ============================================================
define('STOCK_SYNC_SECRET', 'TROCAR_AQUI');
