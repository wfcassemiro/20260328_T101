-- Migração para integração Hotmart
-- Adicionar campos na tabela users para dados da Hotmart

ALTER TABLE users ADD COLUMN IF NOT EXISTS hotmart_subscription_id VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS hotmart_status VARCHAR(50) DEFAULT 'NONE';
ALTER TABLE users ADD COLUMN IF NOT EXISTS hotmart_synced_at TIMESTAMP NULL;

-- Campos para sistema de senha
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_expires TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS first_login BOOLEAN DEFAULT TRUE;

-- Tabela para logs de webhook da Hotmart
CREATE TABLE IF NOT EXISTS hotmart_webhooks (
    id VARCHAR(36) PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT NULL
);

-- Tabela para histórico de sincronizações
CREATE TABLE IF NOT EXISTS hotmart_sync_logs (
    id VARCHAR(36) PRIMARY KEY,
    sync_type ENUM('MANUAL', 'WEBHOOK', 'SCHEDULED') NOT NULL,
    users_synced INT DEFAULT 0,
    errors_count INT DEFAULT 0,
    status ENUM('SUCCESS', 'PARTIAL', 'FAILED') NOT NULL,
    message TEXT,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL
);

-- Índices para performance (executar depois das tabelas)
CREATE INDEX IF NOT EXISTS idx_users_hotmart_subscription ON users(hotmart_subscription_id);
CREATE INDEX IF NOT EXISTS idx_users_hotmart_status ON users(hotmart_status);
CREATE INDEX IF NOT EXISTS idx_users_password_reset_token ON users(password_reset_token);
CREATE INDEX IF NOT EXISTS idx_hotmart_webhooks_event ON hotmart_webhooks(event_type);
CREATE INDEX IF NOT EXISTS idx_hotmart_webhooks_processed ON hotmart_webhooks(processed);
CREATE INDEX IF NOT EXISTS idx_hotmart_webhooks_created ON hotmart_webhooks(created_at);
CREATE INDEX IF NOT EXISTS idx_sync_logs_created ON hotmart_sync_logs(started_at);