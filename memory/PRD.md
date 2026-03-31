# DASH-T101 - PRD (Product Requirements Document)

## O Que Foi Implementado (Abril 2026)

### 1. Correção do Bug de Salvamento (DTP)
- Idiomas opcionais (lang_from/lang_to NULLable)

### 2. Melhorias em Fornecedores
- Botão "Adicionar fornecedor", WhatsApp, Copiar E-mail, Busca em observações, Ordenação

### 3. Modal de Despesas de Interpretação (projects.php)
- Botão "Incluir despesas da interpretação", 4 tipos + customizados via "+"
- Toggle Cliente/Intérprete, Diária pré-selecionada, Monolíngue no topo

### 4. Orçamento com Interpretação (budget.php)
- "Interpretação" no dropdown de serviços por padrão
- Ao selecionar Interpretação: pula Configuração de Pesos e Análise, vai direto para Custos
- Campo "Quantidade" renomeado para "Quantidade de diárias ou horas"
- Unidades: Diária (padrão), Hora, Projeto
- Despesas de interpretação integradas ao cálculo (cliente = faturamento)
- Tipos customizados de despesa via botão "+"

### 5. Tipos Customizados de Despesa
- Botão "+" em projects.php e budget.php
- Coluna expense_type: VARCHAR(100) para suportar tipos customizados

### 6. Listas com Ordenação
- projects_list.php, invoices_list.php, freelancers_list.php

### 7. Faturas (invoices_list.php)
- Botões Editar e Excluir

### 8. Relatório de Despesas
- Filtros, cards, exportação CSV/Excel, tipos customizados

### 9. Central de Relatórios (reports.php)
- Link "Despesas de interpretação"

## Arquivos (10)
freelancers.php, freelancers_list.php, projects.php, projects_list.php, invoices_list.php,
interpretation_expenses_report.php, reports.php, budget.php, database_update_abril_2026.sql, README.md

## Backlog
- P2: Incluir despesas de interpretação no PDF do orçamento
- P2: Dashboard com gráficos de despesas
