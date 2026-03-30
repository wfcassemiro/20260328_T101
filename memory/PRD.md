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
- Ordenação por colunas

### 5. Lista de Faturas (invoices_list.php)
- Botões Editar e Excluir adicionados
- Ordenação por colunas

### 6. Relatório de Despesas de Interpretação (interpretation_expenses_report.php)
- Filtros: período, cliente, tipo, responsável
- Cards de resumo com totais
- Tabela detalhada com 7 colunas ordenáveis
- Exportação CSV e Excel (com filtros preservados, BOM UTF-8)

### 7. Central de Relatórios (reports.php)
- Link "Despesas de interpretação" adicionado ao card Relatórios Operacionais

## Banco de Dados
- `dash_freelancer_rates`: lang_from/lang_to NULLable
- `dash_freelancers`: phone_country_code, is_whatsapp
- `dash_interpretation_expenses`: nova tabela

## Arquivos Gerados (9 arquivos)
- `/app/Abril/v/dash-t101/freelancers.php`
- `/app/Abril/v/dash-t101/freelancers_list.php`
- `/app/Abril/v/dash-t101/projects.php`
- `/app/Abril/v/dash-t101/projects_list.php`
- `/app/Abril/v/dash-t101/invoices_list.php`
- `/app/Abril/v/dash-t101/interpretation_expenses_report.php`
- `/app/Abril/v/dash-t101/reports.php`
- `/app/Abril/database_update_abril_2026.sql`
- `/app/Abril/README.md`

## Backlog
- P2: Dashboard com gráficos de despesas de interpretação
