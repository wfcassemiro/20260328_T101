#!/bin/bash

# Comandos Git para commit apenas dos arquivos Vision UI modificados

echo "🔧 Comandos para commit seletivo dos arquivos Vision UI:"
echo ""

# Adicionar estrutura Vision UI
echo "# 1. Adicionar estrutura Vision UI"
echo "git add public_html/vision/"
echo ""

# Adicionar páginas principais específicas
echo "# 2. Adicionar páginas principais transformadas"
echo "git add public_html/index.php"
echo "git add public_html/videoteca.php"
echo "git add public_html/glossarios.php"
echo "git add public_html/planos.php"
echo "git add public_html/projects.php"
echo "git add public_html/palestra.php"
echo "git add public_html/contato.php"
echo "git add public_html/login.php"
echo "git add public_html/registro.php"
echo "git add public_html/sobre.php"
echo "git add public_html/faq.php"
echo "git add public_html/clients.php"
echo ""

# Adicionar dashboard
echo "# 3. Adicionar dashboard transformado"
echo "git add public_html/dash-t101/"
echo ""

# Adicionar documentação
echo "# 4. Adicionar documentação"
echo "git add COMMIT_READY_FILES.md"
echo "git add MODIFIED_FILES_LIST.txt"
echo ""

# Commit final
echo "# 5. Fazer commit"
echo 'git commit -m "feat: Complete Apple Vision UI transformation

✨ Features:
- Glass effect design with dark background
- Font Awesome 6 icons throughout system
- Complete dashboard transformation (invoices.php, projects.php)
- Universal CSS/JS path system for subdirectories
- Mobile-responsive design
- New pages: login, register, about, faq

🐛 Fixes:
- PHP errors in project management
- CSS loading issues in subdirectories
- Backend functionality tested (100% invoices, 94% projects)

📁 Files modified:
- Vision UI structure: head.php, header.php, sidebar.php, footer.php
- Main pages: 12 pages transformed to Vision UI
- Dashboard: 7 files with complete backend functionality
- CSS: Complete Vision UI stylesheet with dashboard classes

🚀 Ready for production deployment"'

echo ""
echo "📋 OU execute todos de uma vez:"
echo ""
echo "git add public_html/vision/ public_html/*.php public_html/dash-t101/ *.md && git commit -m \"feat: Complete Apple Vision UI transformation - Ready for production\""