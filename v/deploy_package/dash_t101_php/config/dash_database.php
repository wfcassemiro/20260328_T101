<?php
// config/dash_database.php
// Configuração do banco de dados para o Dash-T101

// Incluir funções auxiliares
require_once __DIR__ . '/dash_functions.php';

// Configurações específicas do Dash-T101
$dash_config = [
    'version' => '1.0.0',
    'app_name' => 'Dash-T101',
    'currencies' => ['BRL', 'USD', 'EUR'],
    'languages' => [
        'pt-BR' => 'Português (Brasil)',
        'en-US' => 'Inglês (EUA)',
        'es-ES' => 'Espanhol (Espanha)',
        'fr-FR' => 'Francês',
        'de-DE' => 'Alemão',
        'it-IT' => 'Italiano'
    ],
    'service_types' => [
        'translation' => 'Tradução',
        'revision' => 'Revisão',
        'proofreading' => 'Revisão de Texto',
        'localization' => 'Localização',
        'transcription' => 'Transcrição',
        'other' => 'Outro'
    ],
    'project_statuses' => [
        'pending' => 'Pendente',
        'in_progress' => 'Em Andamento',
        'completed' => 'Concluído',
        'on_hold' => 'Pausado',
        'cancelled' => 'Cancelado'
    ],
    'invoice_statuses' => [
        'draft' => 'Rascunho',
        'sent' => 'Enviada',
        'paid' => 'Paga',
        'overdue' => 'Vencida',
        'cancelled' => 'Cancelada'
    ],
    'priorities' => [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'urgent' => 'Urgente'
    ]
];

// Verificar se as tabelas do Dash-T101 existem
function checkDashTables() {
    global $pdo;
    
    $tables = [
        'dash_clients',
        'dash_projects', 
        'dash_invoices',
        'dash_invoice_items',
        'dash_user_settings'
    ];
    
    foreach ($tables as $table) {
        // CORREÇÃO: Usando query() em vez de prepare() para SHOW TABLES LIKE
        $stmt = $pdo->query("SHOW TABLES LIKE '" . $table . "'"); // Linha 64 corrigida
        
        if (!$stmt->fetch()) {
            return false;
        }
    }
    
    return true;
}

// Criar tabelas se não existirem
function createDashTables() {
    global $pdo;
    
    $sql = file_get_contents(__DIR__ . '/../database_setup_protemos.sql');
    
    if ($sql) {
        try {
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao criar tabelas do Dash-T101: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

// Verificar e criar tabelas se necessário
if (!checkDashTables()) {
    createDashTables();
}
?>