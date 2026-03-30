-- =====================================================
-- DASH-T101 - Abril 2026 Update
-- Script de alteração do banco de dados
-- =====================================================
-- ATENÇÃO: Execute este script no phpMyAdmin ou similar
-- antes de utilizar os novos arquivos PHP
-- =====================================================

-- Adicionar campo de código de país do telefone
ALTER TABLE `dash_freelancers` 
ADD COLUMN `phone_country_code` VARCHAR(10) DEFAULT '+55' AFTER `phone`;

-- Adicionar campo para indicar se o telefone é WhatsApp
ALTER TABLE `dash_freelancers` 
ADD COLUMN `is_whatsapp` TINYINT(1) DEFAULT 0 AFTER `phone_country_code`;

-- Índice para melhorar performance de busca por fornecedores com WhatsApp
CREATE INDEX `idx_freelancer_whatsapp` ON `dash_freelancers` (`is_whatsapp`);

-- =====================================================
-- VERIFICAÇÃO (Opcional - Execute para confirmar)
-- =====================================================
-- SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'dash_freelancers' 
-- AND COLUMN_NAME IN ('phone_country_code', 'is_whatsapp');
