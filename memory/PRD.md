# DASH-T101 - PRD (Product Requirements Document)

## Problema Original
Correções e melhorias no sistema Dash-T101 (PHP + MySQL) para gestão de tradução e interpretação.

## O Que Foi Implementado (Abril 2026)

### 1. Correção do Bug de Salvamento (DTP)
- Idiomas opcionais para qualquer serviço (lang_from/lang_to NULLable)

### 2. Melhorias em Fornecedores (freelancers.php / freelancers_list.php)
- Botão "Adicionar novo fornecedor", WhatsApp, Copiar E-mail, Busca em observações, Ordenação

### 3. Modal de Despesas de Interpretação (projects.php)
- Botão "Incluir despesas da interpretação", 4 tipos + customizados via "+"
- Toggle Cliente/Intérprete, Diária pré-selecionada, Monolíngue no topo do card

### 4. Modal de Despesas de Interpretação (budget.php) - NOVO
- Mesma funcionalidade do projects.php adaptada ao fluxo de orçamento
- Botão aparece quando serviço contém "Interpret"
- Despesas do Cliente somam ao total final do orçamento
- Despesas salvas via AJAX na sessão
- Tipos customizados via botão "+" com modal dedicado

### 5. Tipos Customizados de Despesa (projects.php + budget.php)
- Botão "+" ao lado do select de tipo de despesa
- Modal para digitar nome do novo tipo
- Coluna `expense_type` alterada de ENUM para VARCHAR(100) no SQL

### 6. Listas com Ordenação
- projects_list.php, invoices_list.php, freelancers_list.php: ordenação por colunas

### 7. Lista de Faturas (invoices_list.php)
- Botões Editar e Excluir

### 8. Relatório de Despesas (interpretation_expenses_report.php)
- Filtros, cards de resumo, exportação CSV/Excel, suporte a tipos customizados

### 9. Central de Relatórios (reports.php)
- Link "Despesas de interpretação" no card Operacionais

## Banco de Dados
- `dash_freelancer_rates`: lang_from/lang_to NULLable
- `dash_freelancers`: phone_country_code, is_whatsapp
- `dash_interpretation_expenses`: expense_type agora VARCHAR(100) para suportar tipos customizados

## Arquivos Gerados (10 arquivos)
- `/app/Abril/v/dash-t101/freelancers.php`
- `/app/Abril/v/dash-t101/freelancers_list.php`
- `/app/Abril/v/dash-t101/projects.php`
- `/app/Abril/v/dash-t101/projects_list.php`
- `/app/Abril/v/dash-t101/invoices_list.php`
- `/app/Abril/v/dash-t101/interpretation_expenses_report.php`
- `/app/Abril/v/dash-t101/reports.php`
- `/app/Abril/v/dash-t101/budget.php`
- `/app/Abril/database_update_abril_2026.sql`
- `/app/Abril/README.md`

## Backlog
- P2: Dashboard com gráficos de despesas de interpretação
- P2: Incluir despesas de interpretação no PDF do orçamento
