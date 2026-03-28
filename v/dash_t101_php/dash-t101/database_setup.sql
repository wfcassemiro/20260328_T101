-- Script SQL para criação das tabelas do Dash-T101
-- Compatível com MySQL/MariaDB da Hostinger

-- Tabela de clientes
CREATE TABLE IF NOT EXISTS dash_clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120),
    phone VARCHAR(20),
    company VARCHAR(100),
    address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_email (email)
);

-- Tabela de projetos
CREATE TABLE IF NOT EXISTS dash_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    source_language VARCHAR(10),
    target_language VARCHAR(10),
    word_count INT,
    rate_per_word DECIMAL(10,4),
    total_amount DECIMAL(10,2),
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    start_date DATE,
    deadline DATE,
    completed_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES dash_clients(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_status (status),
    INDEX idx_deadline (deadline)
);

-- Tabela de faturas
CREATE TABLE IF NOT EXISTS dash_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES dash_projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- Inserir alguns dados de exemplo (opcional)
INSERT INTO dash_clients (name, email, company, notes) VALUES 
('João Silva', 'joao@empresa.com', 'Empresa ABC', 'Cliente regular'),
('Maria Santos', 'maria@consultoria.com', 'Consultoria XYZ', 'Projetos técnicos'),
('Pedro Costa', 'pedro@startup.com', 'Startup Tech', 'Documentação de software');

INSERT INTO dash_projects (client_id, title, source_language, target_language, word_count, rate_per_word, total_amount, status, deadline) VALUES 
(1, 'Manual Técnico', 'EN', 'PT', 5000, 0.08, 400.00, 'in_progress', '2025-07-15'),
(2, 'Relatório Financeiro', 'PT', 'EN', 3000, 0.10, 300.00, 'pending', '2025-07-10'),
(3, 'Documentação API', 'EN', 'PT', 8000, 0.07, 560.00, 'completed', '2025-06-30');

