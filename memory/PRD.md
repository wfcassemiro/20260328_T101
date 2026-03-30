# DASH-T101 - PRD (Product Requirements Document)

## Problema Original
O usuário relatou que ao tentar incluir um serviço no Dash-T101, ele não estava sendo salvo. Especificamente, serviços como DTP (Desktop Publishing) sem idiomas especificados não eram persistidos no banco de dados.

## Causa Raiz Identificada
O bug estava no arquivo `freelancers.php`, na lógica de validação para salvar tarifas. O código exigia que pelo menos o campo `lang_to` (idioma destino) estivesse preenchido, mas serviços como DTP não precisam de idioma.

## O Que Foi Implementado

### Data: Abril 2026

### 1. Correção do Bug de Salvamento ✅
- **Idiomas agora são completamente OPCIONAIS** para qualquer serviço
- Regra simplificada: salvar se tem nome do serviço + valor >= 0
- Serviços como DTP, Diagramação, etc. podem ser salvos sem idioma

### 2. Botão "Adicionar novo fornecedor" ✅
- Adicionado ao lado de "Voltar" e "Ver lista"
- Estilo verde (`vision-btn-success`)

### 3. Campo WhatsApp ✅
- Seletor de código de país com 18 países suportados
- Checkbox "É WhatsApp" com ícone verde

### 4. Ícone WhatsApp na Lista ✅
- Botão verde que abre `https://wa.me/` diretamente

### 5. Botão Copiar E-mail ✅
- Botão ao lado do e-mail na lista
- Toast de confirmação "E-mail copiado!"

### 6. Busca em Observações ✅
- Campo `notes` incluído na busca de fornecedores

## Alterações de Banco de Dados Necessárias
```sql
ALTER TABLE `dash_freelancers` ADD COLUMN `phone_country_code` VARCHAR(10) DEFAULT '+55';
ALTER TABLE `dash_freelancers` ADD COLUMN `is_whatsapp` TINYINT(1) DEFAULT 0;
```

## Arquivos Gerados
- `/app/Abril/v/dash-t101/freelancers.php`
- `/app/Abril/v/dash-t101/freelancers_list.php`
- `/app/Abril/database_update_abril_2026.sql`
- `/app/Abril/README.md`

## Backlog / Próximas Tarefas
- P0: Nenhuma pendência crítica
- P1: Testes de integração após aplicação em produção
- P2: Possível adição de mais códigos de país conforme necessidade

## Personas de Usuário
- **Tradutor Freelancer**: Usa o sistema para gerenciar seus fornecedores/parceiros de tradução
- **Agência de Tradução**: Gerencia múltiplos fornecedores com diferentes serviços e tarifas
