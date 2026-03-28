<?php
session_start();
require_once 'config/database.php';

$page_title = 'Perguntas Frequentes';
$page_description = 'Encontre respostas para as principais dúvidas sobre o Translators101, planos, certificados e muito mais.';

include 'includes/header.php';
?>

<div class="min-h-screen px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-16">
            <h1 class="text-4xl font-bold mb-6">Perguntas Frequentes</h1>
            <p class="text-xl text-gray-400">
                Encontre respostas para as principais dúvidas sobre nossa plataforma.
            </p>
        </div>
        
        <!-- FAQ Items -->
        <div class="space-y-6">
            <?php
            $faqs = [
                [
                    'question' => 'Como posso me inscrever no Translators101?',
                    'answer' => 'É muito simples! Clique em "Registrar" no menu superior, preencha seus dados e escolha um de nossos planos. Você terá acesso imediato a todo o conteúdo após a confirmação do pagamento.'
                ],
                [
                    'question' => 'Quantas palestras estão disponíveis na plataforma?',
                    'answer' => 'Temos quase 400 palestras especializadas em tradução, interpretação e revisão. Nosso catálogo é atualizado semanalmente com novos conteúdos ministrados por especialistas renomados.'
                ],
                [
                    'question' => 'Como funciona o sistema de certificados?',
                    'answer' => 'Os certificados são gerados automaticamente em PDF após você assistir completamente a uma palestra. O certificado inclui seu nome, título da palestra, palestrante e duração em horas (sempre arredondada para a próxima meia hora).'
                ],
                [
                    'question' => 'Posso cancelar minha assinatura a qualquer momento?',
                    'answer' => 'Sim! O cancelamento é muito fácil e pode ser feito a qualquer momento através da sua conta ou entrando em contato conosco. Não há multas ou taxas de cancelamento.'
                ],
                [
                    'question' => 'Os glossários são gratuitos?',
                    'answer' => 'Sim! Nossos glossários especializados têm acesso gratuito. Basta fazer um cadastro simples na plataforma para acessar centenas de termos técnicos organizados por categoria e par de idiomas.'
                ],
                [
                    'question' => 'Posso assistir às palestras offline?',
                    'answer' => 'No momento, nossas palestras são disponibilizadas apenas online através de streaming. Isso nos permite manter o conteúdo sempre atualizado e oferecer a melhor qualidade de vídeo.'
                ],
                [
                    'question' => 'Qual a diferença entre os planos?',
                    'answer' => 'Todos os planos oferecem acesso completo ao conteúdo. A diferença está na duração e economia: quanto maior o período, maior o desconto. O plano anual oferece a melhor economia, economizando R$ 86.'
                ],
                [
                    'question' => 'Os certificados são reconhecidos oficialmente?',
                    'answer' => 'Nossos certificados comprovam sua participação nas palestras e podem ser utilizados para demonstrar educação continuada. Para reconhecimento oficial específico, recomendamos verificar com órgãos reguladores da sua área.'
                ],
                [
                    'question' => 'Posso acessar a plataforma de qualquer dispositivo?',
                    'answer' => 'Sim! Nossa plataforma é totalmente responsiva e funciona perfeitamente em computadores, tablets e smartphones. Você pode estudar a qualquer hora e lugar.'
                ],
                [
                    'question' => 'Como posso sugerir temas para futuras palestras?',
                    'answer' => 'Adoramos receber sugestões! Entre em contato conosco pelo email contato@translators101.com.br ou WhatsApp. Suas sugestões são fundamentais para mantermos o conteúdo relevante e atualizado.'
                ],
                [
                    'question' => 'Existe suporte técnico disponível?',
                    'answer' => 'Sim! Oferecemos suporte especializado por email e WhatsApp durante horário comercial. Nossa equipe está sempre pronta para ajudar com qualquer dúvida técnica ou de conteúdo.'
                ],
                [
                    'question' => 'As palestras têm legendas ou transcrições?',
                    'answer' => 'Estamos trabalhando para implementar legendas e transcrições em português em todas as nossas palestras. Algumas já possuem esse recurso, e estamos expandindo gradualmente.'
                ],
                [
                    'question' => 'Posso compartilhar minha conta com outras pessoas?',
                    'answer' => 'Cada assinatura é individual e intransferível. Para uso empresarial ou educacional, oferecemos planos especiais. Entre em contato para conhecer nossas soluções corporativas.'
                ],
                [
                    'question' => 'Como funciona o pagamento?',
                    'answer' => 'Os pagamentos são processados com segurança através da Hotmart. Aceitamos cartão de crédito, PIX e boleto bancário. Você receberá acesso imediato após a confirmação do pagamento.'
                ],
                [
                    'question' => 'Existe app mobile do Translators101?',
                    'answer' => 'Atualmente não temos um app nativo, mas nossa plataforma web é totalmente otimizada para dispositivos móveis, oferecendo uma experiência excelente em smartphones e tablets.'
                ]
            ];
            
            foreach($faqs as $index => $faq):
            ?>
                <div class="bg-gray-900 rounded-lg overflow-hidden">
                    <button 
                        class="w-full p-6 text-left flex justify-between items-center hover:bg-gray-800 transition-colors faq-toggle"
                        data-target="faq-<?php echo $index; ?>"
                    >
                        <h3 class="text-lg font-semibold text-purple-400 pr-4">
                            <?php echo htmlspecialchars($faq['question']); ?>
                        </h3>
                        <i class="fas fa-chevron-down text-gray-400 transition-transform"></i>
                    </button>
                    <div id="faq-<?php echo $index; ?>" class="faq-answer hidden">
                        <div class="px-6 pb-6">
                            <p class="text-gray-300 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Contato para outras dúvidas -->
        <div class="mt-16 bg-gradient-to-r from-purple-900 to-purple-600 rounded-lg p-8 text-center">
            <h2 class="text-2xl font-bold mb-4">
                Não encontrou sua resposta?
            </h2>
            <p class="text-lg mb-6 opacity-90">
                Nossa equipe está pronta para ajudar com qualquer dúvida específica.
            </p>
            <div class="space-x-4">
                <a href="/contato.php" class="bg-white text-purple-600 hover:bg-gray-100 px-6 py-3 rounded-lg font-semibold transition-colors">
                    Entre em Contato
                </a>
                <a href="https://wa.me/5519982600771" target="_blank" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // FAQ Toggle functionality
    const faqToggles = document.querySelectorAll('.faq-toggle');
    
    faqToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const answer = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            // Close all other answers
            document.querySelectorAll('.faq-answer').forEach(otherAnswer => {
                if (otherAnswer.id !== targetId && !otherAnswer.classList.contains('hidden')) {
                    otherAnswer.classList.add('hidden');
                    otherAnswer.previousElementSibling.querySelector('i').style.transform = 'rotate(0deg)';
                }
            });
            
            // Toggle current answer
            answer.classList.toggle('hidden');
            
            if (answer.classList.contains('hidden')) {
                icon.style.transform = 'rotate(0deg)';
            } else {
                icon.style.transform = 'rotate(180deg)';
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
