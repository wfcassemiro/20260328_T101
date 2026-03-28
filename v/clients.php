<?php
session_start();
require_once __DIR__ . '/config/database.php';

$page_title = 'Clientes - Translators101';
$page_description = 'Gestão de clientes e contatos comerciais';

include __DIR__ . '/vision/includes/head.php';
include __DIR__ . '/vision/includes/header.php';
include __DIR__ . '/vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-users"></i> Gestão de Clientes</h1>
            <p>Gerencie seus clientes e oportunidades comerciais</p>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-plus-circle"></i> Adicionar Novo Cliente</h2>
        
        <form class="vision-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="company_name">
                        <i class="fas fa-building"></i> Nome da Empresa
                    </label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>

                <div class="form-group">
                    <label for="contact_name">
                        <i class="fas fa-user"></i> Nome do Contato
                    </label>
                    <input type="text" id="contact_name" name="contact_name" required>
                </div>

                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">
                        <i class="fas fa-phone"></i> Telefone
                    </label>
                    <input type="tel" id="phone" name="phone">
                </div>

                <div class="form-group form-group-wide">
                    <label for="address">
                        <i class="fas fa-map-marker-alt"></i> Endereço
                    </label>
                    <textarea id="address" name="address" rows="3"></textarea>
                </div>

                <div class="form-group form-group-wide">
                    <label for="notes">
                        <i class="fas fa-sticky-note"></i> Observações
                    </label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="cta-btn">
                    <i class="fas fa-save"></i> Salvar Cliente
                </button>
            </div>
        </form>
    </div>

    <div class="video-card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Lista de Clientes</h2>
            
            <div class="search-filters">
                <form class="search-form">
                    <div class="search-group">
                        <input type="text" name="search" placeholder="Buscar clientes...">
                        <button type="submit" class="page-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><i class="fas fa-building"></i> Empresa</th>
                        <th><i class="fas fa-user"></i> Contato</th>
                        <th><i class="fas fa-envelope"></i> Email</th>
                        <th><i class="fas fa-phone"></i> Telefone</th>
                        <th><i class="fas fa-cogs"></i> Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="text-primary">TechCorp Brasil</span>
                        </td>
                        <td>João Silva</td>
                        <td>joao@techcorp.com.br</td>
                        <td>(11) 99999-9999</td>
                        <td>
                            <div class="action-buttons">
                                <button class="page-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="page-btn" title="Ver Projetos">
                                    <i class="fas fa-project-diagram"></i>
                                </button>
                                <button class="page-btn btn-danger" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td>
                            <span class="text-primary">Legal Solutions</span>
                        </td>
                        <td>Maria Santos</td>
                        <td>maria@legalsolutions.com</td>
                        <td>(21) 88888-8888</td>
                        <td>
                            <div class="action-buttons">
                                <button class="page-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="page-btn" title="Ver Projetos">
                                    <i class="fas fa-project-diagram"></i>
                                </button>
                                <button class="page-btn btn-danger" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>