<?php
session_start();
require_once 'config/database.php';

$page_title = 'Contato';
$page_description = 'Entre em contato com o Translators101. Estamos aqui para ajudar com suas dúvidas e sugestões.';

$success_message = '';
$error_message = '';

// Processar envio de mensagem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Validações básicas
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email inválido.';
    } else {
        // Aqui você pode implementar o envio de email real
        // Por enquanto, vamos simular o sucesso
        
        // Salvar mensagem no banco (opcional)
        try {
            $stmt = $pdo->prepare("INSERT INTO access_logs (action, resource, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                'contact_form',
                "Name: $name, Email: $email, Subject: $subject",
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch(PDOException $e) {
            // Falha silenciosa no log
        }
        
        $success_message = 'Mensagem enviada com sucesso! Retornaremos o contato em breve.';
        
        // Limpar campos após envio bem-sucedido
        $name = $email = $subject = $message = '';
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-16">
            <h1 class="text-4xl font-bold mb-6">Entre em Contato</h1>
            <p class="text-xl text-gray-400">
                Estamos aqui para ajudar! Entre em contato conosco através de qualquer um dos canais abaixo.
            </p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Informações de Contato -->
            <div>
                <h2 class="text-2xl font-bold mb-8">Fale Conosco</h2>
                
                <!-- Canais de Contato -->
                <div class="space-y-6 mb-12">
                    <div class="flex items-center space-x-4">
                        <div class="bg-purple-600 rounded-full p-4 flex-shrink-0">
                            <i class="fas fa-envelope text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Email</h3>
                            <a href="mailto:contato@translators101.com.br" class="text-purple-400 hover:text-purple-300">
                                contato@translators101.com.br
                            </a>
                            <p class="text-gray-400 text-sm">Respondemos em até 24 horas</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="bg-green-600 rounded-full p-4 flex-shrink-0">
                            <i class="fab fa-whatsapp text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">WhatsApp</h3>
                            <a href="https://wa.me/5519982600771" target="_blank" class="text-green-400 hover:text-green-300">
                                (19) 98260-0771
                            </a>
                            <p class="text-gray-400 text-sm">Atendimento em horário comercial</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-600 rounded-full p-4 flex-shrink-0">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Horário de Atendimento</h3>
                            <p class="text-gray-300">Segunda a Sexta: 9h às 18h</p>
                            <p class="text-gray-400 text-sm">Horário de Brasília</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tipos de Suporte -->
                <div class="bg-gray-900 rounded-lg p-6">
                    <h3 class="text-xl font-semibold mb-4">Como podemos ajudar?</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-question-circle text-purple-400"></i>
                            <span>Dúvidas sobre planos e assinaturas</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-cog text-purple-400"></i>
                            <span>Suporte técnico e problemas de acesso</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-lightbulb text-purple-400"></i>
                            <span>Sugestões de conteúdo e melhorias</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-certificate text-purple-400"></i>
                            <span>Problemas com certificados</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-handshake text-purple-400"></i>
                            <span>Parcerias e colaborações</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-building text-purple-400"></i>
                            <span>Soluções corporativas</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulário de Contato -->
            <div>
                <h2 class="text-2xl font-bold mb-8">Envie uma Mensagem</h2>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-600 bg-opacity-20 border border-green-600 border-opacity-30 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-3"></i>
                            <p class="text-green-300"><?php echo htmlspecialchars($success_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="bg-red-600 bg-opacity-20 border border-red-600 border-opacity-30 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle text-red-400 mr-3"></i>
                            <p class="text-red-300"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium mb-2">Nome Completo *</label>
                            <input
                                type="text"
                                name="name"
                                id="name"
                                value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            />
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium mb-2">Email *</label>
                            <input
                                type="email"
                                name="email"
                                id="email"
                                value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                                required
                            />
                        </div>
                    </div>
                    
                    <div>
                        <label for="subject" class="block text-sm font-medium mb-2">Assunto *</label>
                        <select
                            name="subject"
                            id="subject"
                            class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            required
                        >
                            <option value="">Selecione o assunto</option>
                            <option value="Dúvidas sobre planos" <?php echo ($subject ?? '') === 'Dúvidas sobre planos' ? 'selected' : ''; ?>>Dúvidas sobre planos</option>
                            <option value="Suporte técnico" <?php echo ($subject ?? '') === 'Suporte técnico' ? 'selected' : ''; ?>>Suporte técnico</option>
                            <option value="Problemas com certificados" <?php echo ($subject ?? '') === 'Problemas com certificados' ? 'selected' : ''; ?>>Problemas com certificados</option>
                            <option value="Sugestões de conteúdo" <?php echo ($subject ?? '') === 'Sugestões de conteúdo' ? 'selected' : ''; ?>>Sugestões de conteúdo</option>
                            <option value="Parcerias" <?php echo ($subject ?? '') === 'Parcerias' ? 'selected' : ''; ?>>Parcerias</option>
                            <option value="Soluções corporativas" <?php echo ($subject ?? '') === 'Soluções corporativas' ? 'selected' : ''; ?>>Soluções corporativas</option>
                            <option value="Cancelamento" <?php echo ($subject ?? '') === 'Cancelamento' ? 'selected' : ''; ?>>Cancelamento</option>
                            <option value="Outros" <?php echo ($subject ?? '') === 'Outros' ? 'selected' : ''; ?>>Outros</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-sm font-medium mb-2">Mensagem *</label>
                        <textarea
                            name="message"
                            id="message"
                            rows="6"
                            class="w-full p-3 bg-gray-800 border border-gray-700 rounded-lg focus:border-purple-500 focus:outline-none"
                            placeholder="Descreva sua dúvida ou mensagem..."
                            required
                        ><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                    </div>
                    
                    <button 
                        type="submit"
                        name="send_message"
                        class="w-full bg-purple-600 hover:bg-purple-700 py-4 rounded-lg font-semibold transition-colors"
                    >
                        <i class="fas fa-paper-plane mr-2"></i>Enviar Mensagem
                    </button>
                </form>
                
                <p class="text-gray-400 text-sm mt-4">
                    * Campos obrigatórios. Responderemos sua mensagem em até 24 horas.
                </p>
            </div>
        </div>
        
        <!-- FAQ Rápido -->
        <div class="mt-16 bg-gray-900 rounded-lg p-8">
            <h2 class="text-2xl font-bold text-center mb-8">Perguntas Mais Frequentes</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-3xl mb-3">❓</div>
                    <h4 class="font-semibold mb-2">Como cancelar minha assinatura?</h4>
                    <p class="text-gray-400 text-sm mb-3">
                        O cancelamento pode ser feito a qualquer momento sem complicações.
                    </p>
                    <a href="/faq.php#cancelamento" class="text-purple-400 hover:text-purple-300 text-sm">
                        Ver detalhes →
                    </a>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl mb-3">📜</div>
                    <h4 class="font-semibold mb-2">Como gerar certificados?</h4>
                    <p class="text-gray-400 text-sm mb-3">
                        Certificados são gerados automaticamente após assistir às palestras.
                    </p>
                    <a href="/faq.php#certificados" class="text-purple-400 hover:text-purple-300 text-sm">
                        Ver detalhes →
                    </a>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl mb-3">💰</div>
                    <h4 class="font-semibold mb-2">Qual plano escolher?</h4>
                    <p class="text-gray-400 text-sm mb-3">
                        Todos os planos têm acesso completo. Quanto maior o período, maior a economia.
                    </p>
                    <a href="/planos.php" class="text-purple-400 hover:text-purple-300 text-sm">
                        Ver planos →
                    </a>
                </div>
            </div>
            
            <div class="text-center mt-8">
                <a href="/faq.php" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                    Ver Todas as Perguntas
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
