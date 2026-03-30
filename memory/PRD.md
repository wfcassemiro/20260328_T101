# DASH-T101 - PRD (Product Requirements Document)

## Problema Original
O usuário relatou que ao tentar incluir um serviço no Dash-T101, ele não estava sendo salvo. Especificamente, serviços como DTP (Desktop Publishing) sem idiomas especificados não eram persistidos no banco de dados.

## O Que Foi Implementado

### Data: Abril 2026

### 1. Correção do Bug de Salvamento
- Idiomas agora são completamente OPCIONAIS para qualquer serviço
- Serviços como DTP, Diagramação, etc. podem ser salvos sem idioma

### 2. Botão "Adicionar novo fornecedor"
- Adicionado ao lado de "Voltar" e "Ver lista" em freelancers.php e freelancers_list.php

### 3. Campo WhatsApp
- Seletor de código de país com 18 países suportados
- Checkbox "É WhatsApp" com ícone verde

### 4. Ícone WhatsApp na Lista
- Botão verde que abre `https://wa.me/` diretamente

### 5. Botão Copiar E-mail
- Botão ao lado do e-mail na lista com toast "E-mail copiado!"

### 6. Busca em Observações
- Campo `notes` incluído na busca de fornecedores

### 7. Modal de Despesas de Interpretação
- Botão "Incluir despesas da interpretação" abaixo dos campos da tarefa (largura total)
- Aparece apenas quando serviço = Interpretação
- Modal com tabela: Viagem, Hospedagem, Alimentação, Equipamento
- Toggle Cliente (verde = faturamento) / Intérprete (laranja = controle interno)
- Despesas do Cliente somadas ao total do projeto
- Suporte completo a edição de projetos existentes
- Unidade "Diária" pré-selecionada ao escolher Interpretação

### 8. Coluna "Despesas" na Lista de Projetos
- Nova coluna na tabela de projects_list.php
- Mostra total de despesas do Cliente (verde) e do Intérprete (laranja) separadamente
- Query otimizada com GROUP BY para buscar totais por projeto
- Exclusão de despesas ao deletar projeto

## Alterações de Banco de Dados
```sql
-- Parte 1: Idiomas opcionais
ALTER TABLE `dash_freelancer_rates` MODIFY COLUMN `lang_from` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `dash_freelancer_rates` MODIFY COLUMN `lang_to` VARCHAR(100) NULL DEFAULT NULL;

-- Parte 2: WhatsApp
ALTER TABLE `dash_freelancers` ADD COLUMN `phone_country_code` VARCHAR(10) DEFAULT '+55';
ALTER TABLE `dash_freelancers` ADD COLUMN `is_whatsapp` TINYINT(1) DEFAULT 0;

-- Parte 3: Despesas de Interpretação
CREATE TABLE IF NOT EXISTS `dash_interpretation_expenses` (...);
```

## Arquivos Gerados
- `/app/Abril/v/dash-t101/freelancers.php`
- `/app/Abril/v/dash-t101/freelancers_list.php`
- `/app/Abril/v/dash-t101/projects.php`
- `/app/Abril/v/dash-t101/projects_list.php`
- `/app/Abril/database_update_abril_2026.sql`
- `/app/Abril/README.md`

## Backlog / Próximas Tarefas
- P0: Nenhuma pendência crítica
- P1: Testes de integração após aplicação em produção
- P2: Relatório de despesas de interpretação por período
- P2: Mais códigos de país para WhatsApp

## Personas de Usuário
- **Tradutor Freelancer**: Gerencia fornecedores/parceiros de tradução
- **Agência de Tradução**: Gerencia múltiplos fornecedores com diferentes serviços e tarifas
- **Gestor de Projetos**: Cria projetos com tarefas de interpretação e controla despesas
