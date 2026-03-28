# Dash-T101 - Dashboard do Freelancer

Este é o Dash-T101, um dashboard simplificado para gerenciar clientes, projetos e faturas, desenvolvido em PHP para integração com o site translators101.com e hospedagem na Hostinger.

## Estrutura de Arquivos

Certifique-se de que a estrutura de arquivos no seu servidor Hostinger esteja da seguinte forma:

```
/home/u335416710/domains/translators101.com/public_html/
├── index.php             (do Dash-T101)
├── clients.php           (do Dash-T101)
├── projects.php          (do Dash-T101)
├── invoices.php          (do Dash-T101)
├── config/               (pasta existente do translators101.com)
│   ├── database.php      (do translators101.com)
│   └── dash_database.php (do Dash-T101)
├── includes/             (pasta existente do translators101.com)
│   ├── header.php        (do translators101.com)
│   └── footer.php        (do translators101.com)
└── ... (outros arquivos do translators101.com)
```

**Importante:** Os arquivos `index.php`, `clients.php`, `projects.php` e `invoices.php` do Dash-T101 devem ser colocados diretamente na pasta `public_html`. O arquivo `dash_database.php` deve ser colocado dentro da pasta `config/` existente.

## Passos para Instalação

Siga estes passos para instalar e configurar o Dash-T101 no seu ambiente Hostinger:

### 1. Upload dos Arquivos

1.  **Baixe o arquivo `dash_t101_complete_final.zip`** que você receberá.
2.  **Descompacte-o** no seu computador.
3.  **Faça o upload dos arquivos PHP** (`index.php`, `clients.php`, `projects.php`, `invoices.php`) diretamente para o diretório `public_html` do seu domínio `translators101.com`.
4.  **Faça o upload do arquivo `config/dash_database.php`** para o diretório `public_html/config/`.

### 2. Configuração do Banco de Dados

O Dash-T101 utiliza o mesmo banco de dados do seu site `translators101.com`.

1.  **Acesse o phpMyAdmin** ou outra ferramenta de gerenciamento de banco de dados na sua Hostinger.
2.  **Selecione o banco de dados** `u335416710_t101_db`.
3.  **Importe o arquivo `database_setup.sql`**. Este script criará as tabelas necessárias (`dash_clients`, `dash_projects`, `dash_invoices`) para o Dash-T101.

    *   **Verifique as credenciais no `config/dash_database.php`:**
        ```php
        $host = 'localhost'; 
        $db   = 'u335416710_t101_db'; // Seu nome de banco de dados
        $user = 'u335416710_t101';    // Seu usuário do banco de dados
        $pass = 'Pa392ap!';          // Sua senha do banco de dados
        ```
        **Atenção:** Se suas credenciais de banco de dados forem diferentes, você precisará atualizar o arquivo `dash_database.php` com as informações corretas.

### 3. Teste o Acesso

Após o upload dos arquivos e a configuração do banco de dados, você poderá acessar o Dash-T101 através da URL principal do seu site, por exemplo:

`https://translators101.com/index.php` (ou apenas `https://translators101.com/` se o servidor estiver configurado para `index.php` como padrão)

O Dash-T101 está configurado para verificar se o usuário está logado no sistema `translators101.com` (`isLoggedIn()` na `dash_database.php`). Se não estiver logado, ele redirecionará para a página de login.

## Funcionalidades Implementadas

*   **Dashboard Principal:** Visão geral com estatísticas de clientes, projetos e faturas.
*   **Gerenciamento de Clientes:** Adicionar, editar e visualizar informações de clientes.
*   **Gerenciamento de Projetos:** Criar, atualizar e acompanhar projetos, com cálculo automático de valores com base em palavras e taxa por palavra.
*   **Gerenciamento de Faturas:** Gerar, editar e controlar faturas, incluindo status de pagamento e datas de vencimento.
*   **Integração de Login:** Utiliza o sistema de login existente do `translators101.com`.
*   **Design Responsivo:** Utiliza Tailwind CSS para uma interface moderna e adaptável.

## Próximos Passos e Melhorias Futuras

*   **Relatórios Detalhados:** Implementar relatórios mais avançados sobre receita, projetos por cliente, etc.
*   **Notificações:** Adicionar sistema de notificações para prazos de projetos e faturas vencidas.
*   **Integração com Pagamentos:** Possibilidade de gerar links de pagamento diretamente das faturas.
*   **Personalização:** Opções para personalizar a interface ou campos de dados.
*   **Controle de Acesso:** Implementar níveis de permissão mais granulares para diferentes tipos de usuários (se aplicável).

---

Qualquer dúvida ou problema durante a instalação, por favor, entre em contato!

