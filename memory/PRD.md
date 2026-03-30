# DASH-T101 - PRD (Product Requirements Document)

## Problema Original
O usuário relatou que ao tentar incluir um serviço no Dash-T101, ele não estava sendo salvo. Especificamente, serviços como DTP (Desktop Publishing) sem idiomas especificados não eram persistidos no banco de dados.

## Causa Raiz Identificada
O bug estava no arquivo `freelancers.php`, na lógica de validação para salvar tarifas. O código exigia que pelo menos o campo `lang_to` (idioma destino) estivesse preenchido, mas serviços como DTP não precisam de idioma.

## O Que Foi Implementado

### Data: Abril 2026

### 1. Correção do Bug de Salvamento
- Idiomas agora são completamente OPCIONAIS para qualquer serviço
- Regra simplificada: salvar se tem nome do serviço + valor >= 0
- Serviços como DTP, Diagramação, etc. podem ser salvos sem idioma

### 2. Botão "Adicionar novo fornecedor"
- Adicionado ao lado de "Voltar" e "Ver lista"
- Estilo verde (`vision-btn-success`)

### 3. Campo WhatsApp
- Seletor de código de país com 18 países suportados
- Checkbox "É WhatsApp" com ícone verde

### 4. Ícone WhatsApp na Lista
- Botão verde que abre `https://wa.me/` diretamente

### 5. Botão Copiar E-mail
- Botão ao lado do e-mail na lista
- Toast de confirmação "E-mail copiado!"

### 6. Busca em Observações
- Campo `notes` incluído na busca de fornecedores

### 7. Modal de Despesas de Interpretação (NOVO)
- Ao selecionar "Interpretação" como serviço numa tarefa, aparece botão "Despesas"
- Modal com tabela para adicionar despesas: Viagem, Hospedagem, Alimentação, Equipamento
- Toggle Cliente/Intérprete para cada despesa
- Despesas do Cliente somadas ao total do projeto (faturamento)
- Despesas do Intérprete salvas apenas para controle interno
- Suporte completo a edição (carrega despesas existentes)
- Hidden fields sincronizados antes do submit do formulário
- Badge com contagem de despesas no botão da tarefa

## Alterações de Banco de Dados
```sql
-- Parte 1: Idiomas opcionais
ALTER TABLE `dash_freelancer_rates` MODIFY COLUMN `lang_from` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `dash_freelancer_rates` MODIFY COLUMN `lang_to` VARCHAR(100) NULL DEFAULT NULL;

-- Parte 2: WhatsApp
ALTER TABLE `dash_freelancers` ADD COLUMN `phone_country_code` VARCHAR(10) DEFAULT '+55';
ALTER TABLE `dash_freelancers` ADD COLUMN `is_whatsapp` TINYINT(1) DEFAULT 0;

-- Parte 3: Despesas de Interpretação
CREATE TABLE IF NOT EXISTS `dash_interpretation_expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `expense_type` ENUM('travel', 'accommodation', 'food', 'equipment') NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_by` ENUM('client', 'interpreter') NOT NULL DEFAULT 'client',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Arquivos Gerados
- `/app/Abril/v/dash-t101/freelancers.php`
- `/app/Abril/v/dash-t101/freelancers_list.php`
- `/app/Abril/v/dash-t101/projects.php` (NOVO)
- `/app/Abril/database_update_abril_2026.sql`
- `/app/Abril/README.md`

## Backlog / Próximas Tarefas
- P0: Nenhuma pendência crítica
- P1: Testes de integração após aplicação em produção
- P1: Visualizar despesas de interpretação na lista de projetos (projects_list.php)
- P2: Possível adição de mais códigos de país conforme necessidade
- P2: Relatório de despesas de interpretação por período

## Personas de Usuário
- **Tradutor Freelancer**: Usa o sistema para gerenciar seus fornecedores/parceiros de tradução
- **Agência de Tradução**: Gerencia múltiplos fornecedores com diferentes serviços e tarifas
- **Gestor de Projetos**: Cria projetos com tarefas de interpretação e controla despesas
