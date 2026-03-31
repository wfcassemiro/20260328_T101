-- =====================================================
-- DASH-T101 - Abril 2026 Update
-- Script de alteração do banco de dados
-- =====================================================
-- ATENÇÃO: Execute este script no phpMyAdmin ou similar
-- antes de utilizar os novos arquivos PHP
-- =====================================================

-- =====================================================
-- PARTE 1: Permitir idiomas NULL na tabela de tarifas
-- (Necessário para serviços como DTP que não têm idioma)
-- =====================================================

-- Alterar coluna lang_from para permitir NULL
ALTER TABLE `dash_freelancer_rates` 
MODIFY COLUMN `lang_from` VARCHAR(100) NULL DEFAULT NULL;

-- Alterar coluna lang_to para permitir NULL
ALTER TABLE `dash_freelancer_rates` 
MODIFY COLUMN `lang_to` VARCHAR(100) NULL DEFAULT NULL;

-- =====================================================
-- PARTE 2: Adicionar campos de WhatsApp
-- =====================================================

-- Adicionar campo de código de país do telefone
ALTER TABLE `dash_freelancers` 
ADD COLUMN IF NOT EXISTS `phone_country_code` VARCHAR(10) DEFAULT '+55' AFTER `phone`;

-- Adicionar campo para indicar se o telefone é WhatsApp
ALTER TABLE `dash_freelancers` 
ADD COLUMN IF NOT EXISTS `is_whatsapp` TINYINT(1) DEFAULT 0 AFTER `phone_country_code`;

-- =====================================================
-- PARTE 3: Tabela de Despesas de Interpretação
-- (Viagem, Hospedagem, Alimentação, Equipamento)
-- =====================================================

CREATE TABLE IF NOT EXISTS `dash_interpretation_expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `expense_type` VARCHAR(100) NOT NULL DEFAULT 'travel',
    `description` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_by` ENUM('client', 'interpreter') NOT NULL DEFAULT 'client',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Se a tabela já existir com ENUM, alterar para VARCHAR:
-- ALTER TABLE `dash_interpretation_expenses` MODIFY COLUMN `expense_type` VARCHAR(100) NOT NULL DEFAULT 'travel';

-- =====================================================
-- VERIFICAÇÃO (Opcional - Execute para confirmar)
-- =====================================================
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'dash_freelancer_rates' 
-- AND COLUMN_NAME IN ('lang_from', 'lang_to');
--
-- SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'dash_freelancers' 
-- AND COLUMN_NAME IN ('phone_country_code', 'is_whatsapp');
--
-- SELECT * FROM dash_interpretation_expenses LIMIT 5;
