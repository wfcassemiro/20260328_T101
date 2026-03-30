# DASH-T101 - Atualização de Abril 2026

## Resumo das Alterações

### 1. Correção do Bug de Salvamento de Serviços
**Problema:** Ao adicionar tarifas/serviços a um fornecedor, serviços como DTP não estavam sendo salvos porque os campos de idioma estavam vazios.

**Causa:** O código PHP exigia que pelo menos o campo "idioma destino" (`lang_to`) fosse preenchido para salvar uma tarifa.

**Solução:** 
- Idiomas agora são **completamente opcionais** para qualquer tipo de serviço
- Serviços como DTP, Diagramação, etc. podem ser salvos sem especificar idioma algum
- A única validação é: nome do serviço preenchido + valor >= 0

### 2. Botão "Adicionar novo fornecedor"
- Adicionado botão verde "Adicionar novo fornecedor" na barra de navegação de ambas as páginas
- O botão aparece ao lado dos botões "Voltar" e "Ver lista"
- Visível em `freelancers.php` (quando editando) e `freelancers_list.php`

### 3. Campo WhatsApp
**Novas funcionalidades no campo Telefone:**
- Seletor de código de país com bandeiras emoji (Brasil, EUA, Portugal, Espanha, etc.)
- Checkbox "É WhatsApp" com ícone verde do WhatsApp
- Quando marcado, exibe um botão WhatsApp na lista de fornecedores

### 4. Ícone WhatsApp na Lista
- Na coluna de contato, quando o telefone está marcado como WhatsApp, aparece um botão verde que abre diretamente o `https://wa.me/` com o número correto
- O link já inclui o código do país automaticamente

### 5. Botão Copiar E-mail
- Ao lado de cada e-mail na lista de fornecedores, há um botão para copiar o e-mail
- Ao clicar, o e-mail é copiado para a área de transferência
- Uma notificação toast aparece confirmando "E-mail copiado!"

### 6. Busca em Observações
- A busca de fornecedores agora inclui o campo `notes` (observações)
- Permite encontrar fornecedores por informações adicionais registradas nas observações

---

## Arquivos Alterados

### `/v/dash-t101/freelancers.php`
- Correção do bug de salvamento de tarifas
- Adição do seletor de código de país
- Adição do checkbox "É WhatsApp"
- Adição do botão "Adicionar novo fornecedor"

### `/v/dash-t101/freelancers_list.php`
- Botão WhatsApp na coluna de contato
- Botão copiar e-mail
- Busca incluindo campo de observações
- Botão "Adicionar novo fornecedor"

---

## Alterações no Banco de Dados

**IMPORTANTE:** Execute o script `database_update_abril_2026.sql` antes de utilizar os novos arquivos PHP.

```sql
-- Adicionar campo de código de país do telefone
ALTER TABLE `dash_freelancers` 
ADD COLUMN `phone_country_code` VARCHAR(10) DEFAULT '+55' AFTER `phone`;

-- Adicionar campo para indicar se o telefone é WhatsApp
ALTER TABLE `dash_freelancers` 
ADD COLUMN `is_whatsapp` TINYINT(1) DEFAULT 0 AFTER `phone_country_code`;
```

---

## Instruções de Instalação

1. **Backup do banco de dados** (recomendado)

2. **Execute o script SQL:**
   - Acesse o phpMyAdmin
   - Selecione o banco `u335416710_t101_db`
   - Clique em "SQL"
   - Cole e execute o conteúdo de `database_update_abril_2026.sql`

3. **Substitua os arquivos PHP:**
   - Faça upload de `v/dash-t101/freelancers.php`
   - Faça upload de `v/dash-t101/freelancers_list.php`

4. **Teste:**
   - Acesse a lista de fornecedores
   - Edite um fornecedor existente
   - Adicione uma nova tarifa (teste com serviço monolíngue como DTP)
   - Salve e verifique se a tarifa foi preservada

---

## Códigos de País Suportados

| Código | País |
|--------|------|
| +55 | Brasil |
| +1 | EUA/Canadá |
| +44 | Reino Unido |
| +351 | Portugal |
| +34 | Espanha |
| +33 | França |
| +49 | Alemanha |
| +39 | Itália |
| +81 | Japão |
| +86 | China |
| +52 | México |
| +54 | Argentina |
| +56 | Chile |
| +57 | Colômbia |
| +58 | Venezuela |
| +591 | Bolívia |
| +595 | Paraguai |
| +598 | Uruguai |

---

### 7. Modal de Despesas de Interpretação (NOVO)
**Funcionalidade:** Quando o serviço "Interpretação" é selecionado em uma tarefa do projeto, um botão "Despesas" aparece. Ao clicar, abre-se um modal para gerenciar custos adicionais.

**Tipos de despesa:**
- Viagem (travel)
- Hospedagem (accommodation)
- Alimentação (food)
- Equipamento (equipment)

**Responsável pelo pagamento:**
- **Cliente:** O valor é somado ao total do projeto (faturamento)
- **Intérprete:** O valor é salvo apenas para controle interno (não afeta o total)

**Campos por despesa:** Tipo, Descrição, Valor, Responsável (Cliente/Intérprete)

### `/v/dash-t101/projects.php`
- Modal de despesas de interpretação
- Botão "Despesas" visível quando serviço = Interpretação
- Toggle Cliente/Intérprete para cada despesa
- Resumo com totais por responsável
- Despesas do Cliente são somadas ao total do projeto
- Suporte a edição (carrega despesas existentes)
- Hidden fields sincronizados antes do submit

### Alteração no Banco de Dados (Parte 3)
```sql
CREATE TABLE IF NOT EXISTS `dash_interpretation_expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `expense_type` ENUM('travel', 'accommodation', 'food', 'equipment') NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paid_by` ENUM('client', 'interpreter') NOT NULL DEFAULT 'client',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_user_id` (`user_id`)
);
```

### Instruções Adicionais

4. **Substitua/Adicione o arquivo PHP:**
   - Faça upload de `v/dash-t101/projects.php`

5. **Teste a funcionalidade de Interpretação:**
   - Crie um novo projeto
   - Adicione uma tarefa e selecione "Interpretação" como serviço
   - Clique no botão "Despesas" que aparece
   - Adicione despesas de viagem, hospedagem, alimentação e/ou equipamento
   - Alterne entre Cliente e Intérprete para cada despesa
   - Verifique que as despesas do Cliente são somadas ao total
   - Salve o projeto e verifique os registros na tabela `dash_interpretation_expenses`

---

**Data:** Abril 2026
**Desenvolvido por:** Emergent Agent
