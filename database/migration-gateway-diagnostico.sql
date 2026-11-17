-- Guarda o último erro ao abrir o pagamento (mostrado em Configurações > Testar conexão).
-- Rodar UMA vez no phpMyAdmin (banco u340554535_mms_boutique).
ALTER TABLE store_settings ADD COLUMN gateway_last_error TEXT NULL;
