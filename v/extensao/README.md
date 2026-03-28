# Translators 101 Dashboard - Extensão Chrome

Dashboard para tradutores e intérpretes que substitui a página de nova aba do Chrome.

## Funcionalidades

- **Relógio e Data** - Exibe hora e data atual
- **Lista de Tarefas** - Gerencia suas tarefas diárias com opção de excluir
- **Pomodoro Timer** - Técnica de produtividade 25/5 minutos
- **Pipeline de Conteúdo** - Gerencie seus projetos (Ideia → Escrevendo → Revisão → Concluído)
- **Próximos Eventos** - Eventos do setor de tradução
- **Notícias** - Agregador de notícias de fontes como ProZ, Slator, Nimdzi
- **Palestras T101** - Próximas palestras do Translators101
- **Acesso Rápido** - Links personalizáveis para ferramentas essenciais
- **Citação do Dia** - Motivação diária

## Personalização

- **Temas**: Claro e Escuro
- **Idiomas**: Português (BR), English, Español
- **Widgets**: Ativar/desativar conforme preferência
- **Links**: Editar links de acesso rápido
- **Backup/Restore**: Exportar e importar configurações

## Barra de Pesquisa

Pesquisa no Google com `udm=14` para evitar respostas geradas por IA.

## Instalação (Desenvolvedor)

1. Abra `chrome://extensions/` no Chrome
2. Ative "Modo do desenvolvedor" no canto superior direito
3. Clique em "Carregar sem compactação"
4. Selecione a pasta `Extensao`

## Estrutura

```
Extensao/
├── icons/           # Ícones da extensão
├── static/          # Arquivos compilados (JS/CSS)
├── _locales/        # Traduções
├── api/             # Scripts PHP para dados
├── manifest.json    # Configuração da extensão
└── index.html       # Página principal
```

## Versão

1.0.0
