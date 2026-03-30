# DASH-T101 - PRD (Product Requirements Document)

## Problema Original
Correções e melhorias no sistema Dash-T101 (PHP + MySQL) para gestão de tradução e interpretação.

## O Que Foi Implementado (Abril 2026)

### 1. Correção do Bug de Salvamento (DTP)
- Idiomas opcionais para qualquer serviço (lang_from/lang_to NULLable)

### 2. Melhorias em Fornecedores (freelancers.php / freelancers_list.php)
- Botão "Adicionar novo fornecedor"
- Campo WhatsApp (código de país + checkbox)
- Ícone WhatsApp na lista (abre wa.me)
- Botão "Copiar E-mail" com toast
- Busca em observações
- Ordenação por colunas (Nome, País, Moeda)

### 3. Modal de Despesas de Interpretação (projects.php)
- Botão "Incluir despesas da interpretação" (largura total, abaixo dos campos)
- Modal com 4 tipos: Viagem, Hospedagem, Alimentação, Equipamento
- Toggle Cliente (faturamento) / Intérprete (controle interno)
- Unidade "Diária" pré-selecionada ao escolher Interpretação
- Checkbox "Monolíngue" reposicionada no topo do card

### 4. Lista de Projetos (projects_list.php)
- Coluna "Despesas" com totais Cliente/Intérprete
- Ordenação por colunas (Projeto, Cliente, Status, Prazo, Valor, Despesas)

### 5. Lista de Faturas (invoices_list.php)
- Botões Editar e Excluir adicionados
- Exclusão com remoção de itens e vínculos
- Ordenação por colunas (Número, Cliente, Data, Vencimento, Status, Valor)

### 6. Relatório de Despesas de Interpretação (interpretation_expenses_report.php) - NOVO
- Filtros: período (de/até), cliente, tipo de despesa, responsável
- Cards de resumo: total geral, total cliente, total intérprete, por tipo de despesa
- Tabela detalhada com link para o projeto
- Rodapé com totais consolidados
- Ordenação por colunas (7 colunas)
- Estado vazio quando não há resultados

## Banco de Dados
- `dash_freelancer_rates`: lang_from/lang_to NULLable
- `dash_freelancers`: phone_country_code, is_whatsapp
- `dash_interpretation_expenses`: nova tabela (expense_type, description, amount, paid_by)

## Arquivos Gerados
- `/app/Abril/v/dash-t101/freelancers.php`
- `/app/Abril/v/dash-t101/freelancers_list.php`
- `/app/Abril/v/dash-t101/projects.php`
- `/app/Abril/v/dash-t101/projects_list.php`
- `/app/Abril/v/dash-t101/invoices_list.php`
- `/app/Abril/v/dash-t101/interpretation_expenses_report.php`
- `/app/Abril/database_update_abril_2026.sql`

## Backlog
- P1: Adicionar link para o relatório de despesas no menu/sidebar/index
- P2: Exportar relatório de despesas para CSV/Excel
- P2: Favicon no head.php (tag link rel="icon" faltando)
