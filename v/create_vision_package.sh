#!/bin/bash

# Script para criar pacote com apenas os arquivos Vision UI atualizados
echo "🚀 Criando pacote Vision UI..."

# Criar diretório temporário
mkdir -p /tmp/vision_ui_package/public_html

# Copiar estrutura Vision UI completa
echo "📁 Copiando estrutura Vision UI..."
cp -r public_html/vision /tmp/vision_ui_package/public_html/

# Copiar páginas principais transformadas
echo "🏠 Copiando páginas principais..."
cp public_html/index.php /tmp/vision_ui_package/public_html/
cp public_html/videoteca.php /tmp/vision_ui_package/public_html/
cp public_html/glossarios.php /tmp/vision_ui_package/public_html/
cp public_html/planos.php /tmp/vision_ui_package/public_html/
cp public_html/projects.php /tmp/vision_ui_package/public_html/
cp public_html/palestra.php /tmp/vision_ui_package/public_html/
cp public_html/contato.php /tmp/vision_ui_package/public_html/
cp public_html/login.php /tmp/vision_ui_package/public_html/
cp public_html/registro.php /tmp/vision_ui_package/public_html/
cp public_html/sobre.php /tmp/vision_ui_package/public_html/
cp public_html/faq.php /tmp/vision_ui_package/public_html/
cp public_html/clients.php /tmp/vision_ui_package/public_html/

# Copiar dashboard completo
echo "📊 Copiando dashboard..."
mkdir -p /tmp/vision_ui_package/public_html/dash-t101
cp public_html/dash-t101/*.php /tmp/vision_ui_package/public_html/dash-t101/

# Copiar arquivo de documentação
cp COMMIT_READY_FILES.md /tmp/vision_ui_package/

# Criar ZIP
echo "📦 Criando arquivo ZIP..."
cd /tmp
zip -r vision_ui_complete.zip vision_ui_package/

# Mover para diretório acessível
mv vision_ui_complete.zip /app/
echo "✅ Pacote criado: /app/vision_ui_complete.zip"

# Limpar temporários
rm -rf /tmp/vision_ui_package

echo "🎉 Pacote Vision UI criado com sucesso!"
echo "📁 Contém: Estrutura Vision + Páginas + Dashboard + Documentação"