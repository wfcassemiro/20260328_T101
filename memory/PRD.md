# DASH-T101 - PRD (Product Requirements Document)

## Problema Original
O usuário relatou que ao tentar incluir um serviço no Dash-T101, ele não estava sendo salvo. Especificamente, serviços como DTP (Desktop Publishing) com checkbox "Monolíngue" marcado não eram persistidos no banco de dados após salvar.

## Causa Raiz Identificada
O bug estava no arquivo `freelancers.php`, na lógica de processamento do formulário POST. O problema ocorria porque:

1. O campo `rates_is_monolingual[]` era enviado como array associativo com índices, mas o código tentava acessá-lo como array sequencial
2. Para serviços monolíngues (onde o campo "De (Origem)" fica oculto), a validação exigia `lang_to` preenchido, mas o select estava com valor "Selecione" (vazio)
3. Isso fazia com que a condição `if (!empty($service) && !empty($lang_to) && $rate >= 0)` falhasse para serviços monolíngues

## O Que Foi Implementado

### Data: Abril 2026

### 1. Correção do Bug de Salvamento ✅
- Corrigida a lógica de processamento do array `rates_is_monolingual`
- Serviços monolíngues agora podem ter `lang_to` vazio (usa o nome do serviço como identificador)

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
