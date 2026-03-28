-- Script SQL para adicionar colunas necessárias na tabela access_logs
-- Execute este script no seu banco de dados antes de usar o sistema

-- Adicionar colunas para controle de tempo assistido
ALTER TABLE access_logs 
ADD COLUMN IF NOT EXISTS accumulated_watch_time DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Tempo total assistido em segundos',
ADD COLUMN IF NOT EXISTS watch_sessions JSON COMMENT 'Sessões de visualização do usuário',
ADD COLUMN IF NOT EXISTS last_skip_detected_at TIMESTAMP NULL COMMENT 'Último skip detectado',
ADD COLUMN IF NOT EXISTS skip_count INT DEFAULT 0 COMMENT 'Número de skips detectados',
ADD COLUMN IF NOT EXISTS skip_details JSON COMMENT 'Detalhes dos skips realizados',
ADD COLUMN IF NOT EXISTS current_position DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Posição atual do vídeo em segundos',
ADD COLUMN IF NOT EXISTS last_access TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Último acesso registrado';

-- Criar índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_access_logs_user_video ON access_logs(user_id, video_id);
CREATE INDEX IF NOT EXISTS idx_access_logs_last_access ON access_logs(last_access);

-- Verificar se as colunas foram criadas corretamente
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'access_logs' 
AND COLUMN_NAME IN (
    'accumulated_watch_time',
    'watch_sessions',
    'last_skip_detected_at',
    'skip_count',
    'skip_details',
    'current_position',
    'last_access'
)
ORDER BY ORDINAL_POSITION;