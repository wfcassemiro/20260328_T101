# DASH-T101 - Atualização de Abril 2026

## Resumo das Alterações

### 1. Correção do Bug de Salvamento de Serviços
**Problema:** Ao adicionar tarifas/serviços a um fornecedor, os serviços "Monolíngues" (como DTP) não estavam sendo salvos corretamente.

**Causa:** O código PHP não processava corretamente os índices dos campos `rates_is_monolingual[]` quando eram enviados via POST, causando falha na validação.

**Solução:** 
- Corrigida a lógica de processamento do array `rates_is_monolingual` no `freelancers.php`
- Adicionada verificação para serviços monolíngues que não necessitam de idioma de destino obrigatório
- O campo `lang_to` agora recebe o nome do serviço quando é monolíngue e o idioma não é especificado

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

**Data:** Abril 2026
**Desenvolvido por:** Emergent Agent
