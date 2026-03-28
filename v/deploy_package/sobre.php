<?php
session_start();
require_once 'config/database.php';

$page_title = 'Sobre o Translators101';
$page_description = 'Conheça a história e missão do Translators101, plataforma de educação continuada para profissionais de tradução.';

include 'includes/header.php';
?>

<div class="min-h-screen px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Hero Section -->
        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-bold mb-6">Sobre o Translators101</h1>
            <p class="text-xl text-gray-400 leading-relaxed">
                Uma empresa com DNA USP, dedicada à educação continuada para 
                profissionais de tradução, interpretação e revisão.
            </p>
        </div>
        
        <!-- Nossa História -->
        <section class="mb-16">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold mb-6">Nossa História</h2>
                    <div class="prose prose-invert max-w-none space-y-4">
                        <p class="text-lg text-gray-300 leading-relaxed">
                            O Translators101 nasceu da necessidade de oferecer educação continuada 
                            de qualidade para profissionais da área de linguística aplicada, 
                            especialmente tradutores, intérpretes e revisores.
                        </p>
                        <p class="text-lg text-gray-300 leading-relaxed">
                            Com raízes na Universidade de São Paulo (USP), nossa plataforma 
                            combina excelência acadêmica com praticidade profissional, 
                            oferecendo conteúdo atualizado e relevante para o mercado atual.
                        </p>
                        <p class="text-lg text-gray-300 leading-relaxed">
                            Desde nossa fundação, já capacitamos mais de 1.000 profissionais, 
                            contribuindo para o desenvolvimento e profissionalização do 
                            setor de tradução no Brasil.
                        </p>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="bg-gradient-to-br from-purple-600 to-purple-800 rounded-lg p-8">
                        <div class="text-6xl mb-4">🎓</div>
                        <h3 class="text-2xl font-bold mb-4">DNA USP</h3>
                        <p class="text-purple-200">
                            Qualidade acadêmica e rigor científico em cada conteúdo oferecido.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Nossa Missão -->
        <section class="mb-16">
            <h2 class="text-3xl font-bold text-center mb-12">Nossa Missão, Visão e Valores</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gray-900 rounded-lg p-8 text-center">
                    <div class="text-4xl mb-4">🎯</div>
                    <h3 class="text-xl font-bold mb-4 text-purple-400">Missão</h3>
                    <p class="text-gray-300">
                        Democratizar o acesso à educação continuada de qualidade para 
                        profissionais de tradução, interpretação e revisão, promovendo 
                        o desenvolvimento da categoria no Brasil.
                    </p>
                </div>
                
                <div class="bg-gray-900 rounded-lg p-8 text-center">
                    <div class="text-4xl mb-4">🔭</div>
                    <h3 class="text-xl font-bold mb-4 text-purple-400">Visão</h3>
                    <p class="text-gray-300">
                        Ser a principal referência em educação continuada para 
                        profissionais da área de linguística aplicada na América Latina 
                        até 2030.
                    </p>
                </div>
                
                <div class="bg-gray-900 rounded-lg p-8 text-center">
                    <div class="text-4xl mb-4">💎</div>
                    <h3 class="text-xl font-bold mb-4 text-purple-400">Valores</h3>
                    <p class="text-gray-300">
                        Excelência acadêmica, acessibilidade, inovação tecnológica, 
                        ética profissional e compromisso com o desenvolvimento contínuo.
                    </p>
                </div>
            </div>
        </section>
        
        <!-- Números que Impressionam -->
        <section class="mb-16">
            <h2 class="text-3xl font-bold text-center mb-12">Números que Impressionam</h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-4xl font-bold text-purple-400 mb-2">400+</div>
                    <div class="text-gray-300">Palestras Especializadas</div>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl font-bold text-purple-400 mb-2">50+</div>
                    <div class="text-gray-300">Especialistas Renomados</div>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl font-bold text-purple-400 mb-2">1000+</div>
                    <div class="text-gray-300">Profissionais Capacitados</div>
                </div>
                
                <div class="text-center">
                    <div class="text-4xl font-bold text-purple-400 mb-2">95%</div>
                    <div class="text-gray-300">Satisfação dos Alunos</div>
                </div>
            </div>
        </section>
        
        <!-- Nossos Diferenciais -->
        <section class="mb-16">
            <h2 class="text-3xl font-bold text-center mb-12">Nossos Diferenciais</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="flex items-start space-x-4">
                    <div class="bg-purple-600 rounded-full p-3 flex-shrink-0">
                        <i class="fas fa-graduation-cap text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold mb-2">Excelência Acadêmica</h4>
                        <p class="text-gray-400">
                            Conteúdo desenvolvido com rigor acadêmico e ministrado por 
                            especialistas reconhecidos no mercado.
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="bg-purple-600 rounded-full p-3 flex-shrink-0">
                        <i class="fas fa-clock text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold mb-2">Atualizações Semanais</h4>
                        <p class="text-gray-400">
                            Novos conteúdos toda semana para manter você sempre 
                            atualizado com as tendências do mercado.
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="bg-purple-600 rounded-full p-3 flex-shrink-0">
                        <i class="fas fa-certificate text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold mb-2">Certificados Automáticos</h4>
                        <p class="text-gray-400">
                            Receba certificados em PDF automaticamente após assistir 
                            às palestras, validando seu desenvolvimento profissional.
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="bg-purple-600 rounded-full p-3 flex-shrink-0">
                        <i class="fas fa-dollar-sign text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold mb-2">Preços Acessíveis</h4>
                        <p class="text-gray-400">
                            Planos com preços justos e cancelamento fácil, 
                            democratizando o acesso à educação de qualidade.
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="bg-purple-600 rounded-full p-3 flex-shrink-0">
                        <i class="fas fa-mobile-alt text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold mb-2">Acesso Multiplataforma</h4>
                        <p class="text-gray-400">
                            Assista em qualquer dispositivo, a qualquer hora e lugar, 
                            adaptando-se à sua rotina profissional.
                        </p>
                    </div>
                </div>
                
                <div class="flex items-start space-x-4">
                    <div class="bg-purple-600 rounded-full p-3 flex-shrink-0">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-xl font-semibold mb-2">Comunidade Ativa</h4>
                        <p class="text-gray-400">
                            Faça parte de uma comunidade engajada de profissionais 
                            que compartilham conhecimentos e experiências.
                        </p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Compromisso com a Qualidade -->
        <section class="mb-16">
            <div class="bg-gradient-to-r from-purple-900 to-purple-600 rounded-lg p-8">
                <h2 class="text-3xl font-bold text-center mb-6">
                    Nosso Compromisso com a Qualidade
                </h2>
                <p class="text-xl text-center opacity-90 leading-relaxed">
                    No Translators101, acreditamos que a educação continuada é fundamental 
                    para o sucesso profissional. Por isso, nos comprometemos a oferecer 
                    sempre o melhor conteúdo, com palestrantes renomados e metodologias 
                    inovadoras que realmente fazem a diferença na carreira de nossos alunos.
                </p>
            </div>
        </section>
        
        <!-- Call to Action -->
        <section class="text-center">
            <h2 class="text-3xl font-bold mb-6">
                Faça parte da nossa história
            </h2>
            <p class="text-xl text-gray-400 mb-8">
                Junte-se a milhares de profissionais que já transformaram suas carreiras conosco.
            </p>
            <div class="space-x-4">
                <a href="/planos.php" class="bg-purple-600 hover:bg-purple-700 px-8 py-4 rounded-lg text-lg font-semibold transition-colors">
                    Ver Planos
                </a>
                <a href="/contato.php" class="bg-gray-600 hover:bg-gray-700 px-8 py-4 rounded-lg text-lg font-semibold transition-colors">
                    Entre em Contato
                </a>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
