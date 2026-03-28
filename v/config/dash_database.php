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

// Mock functions for testing environment
function checkDashTables() {
    return true; // Always return true for testing
}

function createDashTables() {
    return true; // Always return true for testing
}

// Skip table creation in testing environment
?>