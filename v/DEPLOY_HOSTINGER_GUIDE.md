# 🚀 Guia Completo de Deploy - v.translators101.com (Hostinger)

## 📦 Arquivo Pronto para Deploy

**Arquivo**: `translators101_vision_ui_final.tar.gz` (146MB)
**SHA256**: `29cd165d8336854a3559e737d7dad547aa5482d63c6d5e07f78d9545da29f325`

## 🎯 PASSO A PASSO - DEPLOY NA HOSTINGER

### 1️⃣ **Preparação no Painel da Hostinger**

1. **Acesse o hPanel da Hostinger**
2. **Vá para "Gerenciador de Arquivos"**
3. **Navegue até o diretório do subdomínio**: `domains/v.translators101.com/public_html/`

### 2️⃣ **Upload e Extração dos Arquivos**

1. **Faça upload do arquivo** `translators101_vision_ui_final.tar.gz`
2. **Extrair o arquivo**:
   ```bash
   # Via terminal da Hostinger (se disponível)
   cd domains/v.translators101.com/public_html/
   tar -xzf translators101_vision_ui_final.tar.gz
   mv public_html/* ./
   rm -rf public_html/
   rm translators101_vision_ui_final.tar.gz
   ```

   **OU via Gerenciador de Arquivos:**
   - Clique com botão direito no arquivo `.tar.gz`
   - Selecione "Extrair"
   - Mova todos os arquivos da pasta `public_html/` para a raiz
   - Delete a pasta `public_html/` vazia e o arquivo `.tar.gz`

### 3️⃣ **Configuração de Banco de Dados**

1. **Criar Banco MySQL na Hostinger**:
   - Vá para "Bancos de Dados MySQL"
   - Crie um novo banco: `t101_vision_db`
   - Anote: nome do banco, usuário e senha

2. **Configurar Credenciais do Banco**:
   - Edite `/config/database.php`
   - Substitua as credenciais:
   ```php
   $host = 'localhost';
   $db   = 'seuusuario_t101_vision_db';  // Nome completo do banco
   $user = 'seuusuario_dbuser';          // Usuário do banco
   $pass = 'sua_senha_segura';           // Senha do banco
   ```

3. **Importar Estrutura do Banco** (se necessário):
   - Use phpMyAdmin na Hostinger
   - Importe arquivo SQL de estrutura (se existir)

### 4️⃣ **Configuração de Permissões**

```bash
# Via terminal (se disponível)
chmod -R 755 .
chmod -R 777 uploads/     # Se existir pasta de uploads
chmod -R 777 certificates/ # Se existir pasta de certificados
```

**Via Gerenciador de Arquivos:**
- Clique com botão direito nas pastas
- "Permissões" → 755 para arquivos PHP
- "Permissões" → 777 para pastas de upload

### 5️⃣ **Configurações do Sistema**

1. **Configurar Email** (arquivo `/config/email.php`):
   ```php
   // Configurações SMTP da Hostinger
   define('SMTP_HOST', 'smtp.hostinger.com');
   define('SMTP_PORT', 587);
   define('SMTP_USERNAME', 'noreply@translators101.com');
   define('SMTP_PASSWORD', 'sua_senha_email');
   ```

2. **Configurar URLs Base**:
   - Edite arquivos que contenham URLs hardcoded
   - Certifique-se que apontam para `https://v.translators101.com`

### 6️⃣ **Teste de Funcionamento**

1. **Acesse** `https://v.translators101.com`
2. **Verifique**:
   - ✅ Homepage carrega com Vision UI
   - ✅ CSS e JS carregam corretamente (sem 404)
   - ✅ Login funciona
   - ✅ Dashboard acessível
   - ✅ Efeitos glass funcionando

### 7️⃣ **Configurações de Produção**

1. **SSL/HTTPS**:
   - Ative SSL gratuito na Hostinger
   - Force redirecionamento HTTPS

2. **Cache e Performance**:
   - Ative cache do lado servidor
   - Configure compressão GZIP

3. **Backup**:
   - Configure backup automático
   - Teste restauração

## 🔧 **ESTRUTURA DE ARQUIVOS NO SERVIDOR**

```
domains/v.translators101.com/public_html/
├── admin/                    # Painel administrativo
├── config/                   # Configurações do sistema
│   ├── database.php         # ← EDITAR: credenciais DB
│   ├── email.php           # ← EDITAR: configurações SMTP
│   └── ...
├── dash-t101/              # Dashboard principal
├── vision/                 # Componentes Vision UI
│   ├── assets/
│   │   ├── css/style.css   # CSS principal
│   │   ├── js/main.js      # JavaScript
│   │   └── img/            # Imagens
│   └── includes/           # Componentes PHP
├── index.php              # Homepage
├── login.php              # Sistema de login
└── ...
```

## 🚨 **CHECKLIST FINAL**

### Antes de Fazer Público:
- [ ] Banco de dados configurado e funcionando
- [ ] Credenciais de email configuradas
- [ ] SSL ativo (HTTPS)
- [ ] Backup configurado
- [ ] Teste de todas as funcionalidades principais
- [ ] Verificação de logs de erro

### Funcionalidades a Testar:
- [ ] **Homepage**: Carregamento e efeitos glass
- [ ] **Login/Registro**: Autenticação funcionando
- [ ] **Dashboard**: Acesso aos dados
- [ ] **Admin**: Painel administrativo
- [ ] **Responsivo**: Mobile e desktop
- [ ] **Performance**: Tempo de carregamento
- [ ] **Email**: Sistema de notificações

## 📞 **Suporte Técnico**

### Problemas Comuns:

1. **Erro 500**:
   - Verificar permissões de arquivos
   - Checar logs de erro do servidor
   - Validar configurações de banco

2. **CSS/JS não carrega**:
   - Verificar cache-busting (`?v=15`)
   - Confirmar caminhos relativos
   - Testar em modo privado do navegador

3. **Banco não conecta**:
   - Confirmar credenciais em `/config/database.php`
   - Testar conexão via phpMyAdmin
   - Verificar nome completo do banco (usuário_banco)

### Logs Importantes:
- `/var/log/apache2/error.log` (erros do servidor)
- Logs do PHP (via painel Hostinger)
- Logs de email (configurações SMTP)

---

## ✅ **DEPLOY CONCLUÍDO!**

Após seguir todos os passos, seu site estará rodando em:
**https://v.translators101.com**

Com todas as funcionalidades Apple Vision UI funcionando perfeitamente! 🎉

---

**Data**: Setembro 2024  
**Versão**: Vision UI Final  
**Status**: Produção Ready ✅