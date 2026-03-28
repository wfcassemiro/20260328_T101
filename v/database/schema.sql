-- Schema do banco de dados Translators101
-- Execute este script no seu MySQL da Hostinger

CREATE DATABASE IF NOT EXISTS translators101_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE translators101_db;

-- Tabela de usuários
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'subscriber', 'free') DEFAULT 'free',
    subscription_type ENUM('monthly', 'quarterly', 'biannual', 'annual') NULL,
    subscription_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Tabela de palestras
CREATE TABLE lectures (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    speaker VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    duration_minutes INT NOT NULL,
    embed_code TEXT NOT NULL,
    thumbnail_url VARCHAR(1000) NULL,
    category VARCHAR(100) NOT NULL,
    tags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_featured BOOLEAN DEFAULT FALSE,
    is_live BOOLEAN DEFAULT FALSE
);

-- Tabela de glossários
CREATE TABLE glossaries (
    id VARCHAR(36) PRIMARY KEY,
    term VARCHAR(255) NOT NULL,
    definition TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    language_pair VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de certificados
CREATE TABLE certificates (
    id VARCHAR(36) PRIMARY KEY,
    user_id VARCHAR(36) NOT NULL,
    lecture_id VARCHAR(36) NOT NULL,
    user_name VARCHAR(255) NOT NULL,
    lecture_title VARCHAR(500) NOT NULL,
    speaker_name VARCHAR(255) NOT NULL,
    duration_hours DECIMAL(3,1) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
);

-- Tabela de logs de acesso (para auditoria)
CREATE TABLE access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(36) NULL,
    action VARCHAR(100) NOT NULL,
    resource VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Índices para performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_lectures_category ON lectures(category);
CREATE INDEX idx_lectures_featured ON lectures(is_featured);
CREATE INDEX idx_glossaries_category ON glossaries(category);
CREATE INDEX idx_certificates_user ON certificates(user_id);
CREATE INDEX idx_access_logs_user ON access_logs(user_id);
CREATE INDEX idx_access_logs_created ON access_logs(created_at);
