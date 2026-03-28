<?php
session_start(); // CRÍTICO: Iniciar sessão para funções de autenticação funcionarem

// Inclui o arquivo de funções de autenticação e conexão com o banco de dados
require_once __DIR__ . '/config/database.php';

// --- Funções de Acesso (Devem ser garantidas no ambiente de produção) ---
// ATENÇÃO: Presumindo que estas funções estão definidas no ambiente (ex: database.php)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
    }
}

// Verifica se o usuário está logado
$is_logged_in = isLoggedIn();
$is_admin = isAdmin();

// Se estiver logado, redireciona para a videoteca
if ($is_logged_in && !$is_admin) {
    header('Location: /videoteca.php');
    exit();
}

// Configurações da página
$page_title = 'Translators101 - Palestras com profissionais experientes';
$page_description = 'Palestras sobre tradução e interpretação com dicas e informações importantes para a evolução de sua carreira.';

// --- 2. BUSCAR PRÓXIMAS PALESTRAS (upcoming_announcements) ---
$upcomingLectures = [];
try {
    // Ordenação: data mais próxima para a mais distante
    $stmt = $pdo->query("
        SELECT id, title, speaker, description, image_path, announcement_date, lecture_time
        FROM upcoming_announcements
        WHERE is_active = 1
        AND announcement_date >= CURDATE()
        ORDER BY announcement_date ASC, display_order ASC
        LIMIT 3
    ");
    $upcomingLectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Em caso de erro, a lista fica vazia.
    $upcomingLectures = [];
}

// Fallback de dados (apenas para garantir o layout em caso de lista vazia)
if (empty($upcomingLectures)) {
    $upcomingLectures = [
        [
            'id' => 'default-1',
            'title' => 'Técnicas Avançadas de Interpretação Simultânea',
            'speaker' => 'Dra. Maria Silva',
            'description' => 'Aprenda as técnicas mais modernas de interpretação simultânea utilizadas em eventos internacionais.',
            'image_path' => '/images/palestra-placeholder.jpg',
            'announcement_date' => date('Y-m-d', strtotime('+15 days')),
            'lecture_time' => '19:00:00'
        ],
        [
            'id' => 'default-2',
            'title' => 'Tradução Jurídica: Contratos Internacionais',
            'speaker' => 'Dr. Carlos Santos',
            'description' => 'Domine a terminologia e as nuances da tradução de documentos jurídicos.',
            'image_path' => '/images/palestra-placeholder.jpg',
            'announcement_date' => date('Y-m-d', strtotime('+22 days')),
            'lecture_time' => '19:00:00'
        ],
        [
            'id' => 'default-3',
            'title' => 'IA na Tradução: Como Usar sem Perder Qualidade',
            'speaker' => 'Prof. Ana Costa',
            'description' => 'Descubra como integrating ferramentas de IA no seu workflow mantendo a qualidade.',
            'image_path' => '/images/palestra-placeholder.jpg',
            'announcement_date' => date('Y-m-d', strtotime('+29 days')),
            'lecture_time' => '19:00:00'
        ]
    ];
}

include __DIR__ . '/vision/includes/head.php';
?>

<?php include __DIR__ . '/vision/includes/header.php'; ?>

<?php include __DIR__ . '/vision/includes/sidebar.php'; ?>

<main class="main-content">
    <section class="glass-hero fade-item" id="home">
        <div class="hero-logo">
            <div class="translators-logo">
              <img src="/images/Logo-t101.webp" alt="Translators 101" class="main-logo" width="280" height="100" loading="lazy">
            </div>
        </div>

        <div class="hero-content-conversion">
            <h1 class="hero-headline">Transforme sua carreira em tradução e interpretação com mais de 400 palestras especializadas</h1>
            <p class="hero-subheadline">Acesse conteúdo exclusivo dos melhores profissionais do mercado e acelere seu crescimento profissional hoje mesmo!</p>

            <div class="social-proof-hero">
                <div class="proof-item">
                    <i class="fas fa-users"></i>
                    <span><strong>+1.500</strong> tradutores já confiam em nós</span>
                </div>
                <div class="proof-item">
                    <i class="fas fa-video"></i>
                    <span><strong>+400</strong> palestras disponíveis</span>
                </div>
                <div class="proof-item">
                    <i class="fas fa-calendar-week"></i>
                    <span><strong>Nova palestra</strong> toda semana</span>
                </div>
            </div>

            <div class="hero-cta-section">
                <a href="#planos" class="cta-btn cta-primary pulse-animation">
                    <i class="fas fa-rocket"></i> Quero começar agora
                </a>
                <p class="hero-guarantee" style="margin-top: 30px;">
                    ✅ Acesso imediato • ✅ Cancele quando quiser • ✅ Garantia total
                </p>
            </div>
        </div>
    </section>

    <section class="value-problem-combined fade-item">
        <div class="main-content-grid">
            <div class="value-grid-compact">
                <div class="value-card fade-item">
                    <div class="value-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Aprenda com os melhores</h3>
                    <p>Palestras ministradas por <strong>profissionais reconhecidos</strong> no mercado, com experiência real e casos práticos.</p>
                </div>

                <div class="value-card fade-item">
                    <div class="value-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>No seu ritmo</h3>
                    <p><strong>Acesso 24/7</strong> a todo conteúdo. Assista quando e onde quiser, quantas vezes precisar.</p>
                </div>

                <div class="value-card fade-item">
                    <div class="value-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Carreira acelerada</h3>
                    <p>Conteúdo <strong>prático e aplicável</strong> que você usa imediatamente para aumentar seus ganhos.</p>
                </div>

                <div class="value-card fade-item">
                    <div class="value-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>Certificados reconhecidos</h3>
                    <p><strong>Certificados automáticos</strong> para comprovar sua educação continuada no mercado.</p>
                </div>
            </div>

            <div class="problem-solution-grid">
                <div class="challenge-solution-section">
                    <h2 class="challenge-title">Você está enfrentando estes desafios?</h2>
                    <div class="challenge-cards-grid">
                        <div class="challenge-card fade-item">
                            <div class="challenge-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h4>Dificuldade para se atualizar</h4>
                            <p>Mercado em constante mudança e falta de conteúdo atualizado para se manter competitivo</p>
                        </div>

                        <div class="challenge-card fade-item">
                            <div class="challenge-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h4>Falta de networking</h4>
                            <p>Isolamento profissional e dificuldade para conectar com outros profissionais da área</p>
                        </div>

                        <div class="challenge-card fade-item">
                            <div class="challenge-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h4>Cursos caros sem valor</h4>
                            <p>Investimentos altos em cursos que não entregam conhecimento prático aplicável</p>
                        </div>

                        <div class="challenge-card fade-item">
                            <div class="challenge-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h4>Conteúdo desatualizado</h4>
                            <p>Material genérico que não reflete as demandas atuais do mercado de tradução</p>
                        </div>
                    </div>
                </div>

                <div class="solution-section">
                    <h2 class="solution-title">A Translators101 resolve todos eles:</h2>
                    <div class="solution-cards-grid">
                        <div class="solution-card fade-item">
                            <div class="solution-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Conteúdo sempre atualizado</h4>
                            <p>Palestras semanais com as últimas tendências e demandas do mercado de tradução</p>
                        </div>

                        <div class="solution-card fade-item">
                            <div class="solution-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Comunidade ativa</h4>
                            <p>Rede de +1.500 profissionais conectados para networking e troca de experiências</p>
                        </div>

                        <div class="solution-card fade-item">
                            <div class="solution-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Preço justo e acessível</h4>
                            <p>Acesso completo a +400 palestras por menos de R$ 2,00 por dia</p>
                        </div>

                        <div class="solution-card fade-item">
                            <div class="solution-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h4>Especialização por área</h4>
                            <p>Conteúdo específico e aprofundado para cada nicho de atuação profissional</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="expertise-areas fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Domine todas as áreas da tradução e interpretação</h2>
            <p class="section-subtitle">Conteúdo especializado para cada nicho do mercado</p>

            <div class="expertise-grid">
                <div class="expertise-card fade-item">
                    <i class="fas fa-gamepad"></i>
                    <h4>Tradução de jogos</h4>
                    <p>Localização, adaptação cultural e técnicas específicas para games</p>
                </div>
                <div class="expertise-card fade-item">
                    <i class="fas fa-film"></i>
                    <h4>Dublagem & legendagem</h4>
                    <p>Técnicas profissionais para audiovisual e streaming</p>
                </div>
                <div class="expertise-card fade-item">
                    <i class="fas fa-microphone"></i>
                    <h4>Interpretação</h4>
                    <p>Simultânea, consecutiva e técnicas avançadas</p>
                </div>
                <div class="expertise-card fade-item">
                    <i class="fas fa-cogs"></i>
                    <h4>Tradução técnica</h4>
                    <p>Manuais, documentação e textos especializados</p>
                </div>
                <div class="expertise-card fade-item">
                    <i class="fas fa-heartbeat"></i>
                    <h4>Área da saúde</h4>
                    <p>Terminologia médica e farmacêutica</p>
                </div>
                <div class="expertise-card fade-item">
                    <i class="fas fa-book"></i>
                    <h4>Tradução literária</h4>
                    <p>Quadrinhos, romances e adaptação criativa</p>
                </div>
            </div>
        </div>
    </section>

    <section class="upcoming-lectures fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Próximas palestras</h2>
            <p class="section-subtitle">Não perca os próximos eventos exclusivos da nossa comunidade</p>

            <?php if ($is_admin): ?>
            <div class="admin-controls">
                <button class="btn-admin-add" onclick="openAddLectureModal()">
                    <i class="fas fa-plus"></i> Adicionar Nova Palestra
                </button>
            </div>
            <?php endif; ?>

            <div class="lectures-grid" id="lecturesContainer">
                <?php
                foreach ($upcomingLectures as $lecture):
                    // Processar data e horário
                    $announcementDate = $lecture['announcement_date'] ?? '';
                    $lectureTime = $lecture['lecture_time'] ?? '19:00:00';
                    $defaultDuration = 90;

                    $dateTimeStart = new DateTime($announcementDate . ' ' . $lectureTime);
                    $dateTimeEnd = clone $dateTimeStart;
                    $dateTimeEnd->modify("+{$defaultDuration} minutes");

                    $formattedDate = $dateTimeStart->format('d \d\e F, Y');
                    $formattedTime = $dateTimeStart->format('H:i');
                    $monthNames = [
                        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
                        'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
                        'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
                        'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
                    ];
                    $formattedDate = str_replace(array_keys($monthNames), array_values($monthNames), $formattedDate);
                ?>
                <div class="lecture-card fade-item" id="lecture-<?php echo htmlspecialchars($lecture['id']); ?>">
                    <?php if ($is_admin): ?>
                    <button class="edit-lecture-btn" onclick="editLecture('<?php echo htmlspecialchars($lecture['id']); ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="delete-lecture-btn"
                            onclick="deleteAnnouncement('<?php echo htmlspecialchars($lecture['id']); ?>')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <?php endif; ?>

                    <div class="lecture-image-container">
                        <img src="<?php echo htmlspecialchars($lecture["image_path"] ?? "/images/palestra-placeholder.jpg"); ?>" alt="Palestra" class="lecture-image" width="300" height="200">
                    </div>
                    <div class="lecture-info">
                        <div class="lecture-datetime">
                            <div class="lecture-date"><?php echo htmlspecialchars($formattedDate); ?></div>
                            <div class="lecture-time"><?php echo htmlspecialchars($formattedTime); ?>h</div>
                        </div>
                        <h4 class="lecture-title"><?php echo htmlspecialchars($lecture['title']); ?></h4>
                        <div class="lecture-speaker">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($lecture['speaker']); ?></span>
                        </div>
                        <p class="lecture-summary">
                            <?php echo htmlspecialchars($lecture['description']); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="expertise-cta">
                <a href="vetor.php" class="cta-btn cta-secondary">
                    <i class="fas fa-eye"></i> Ver todas as palestras
                </a>
            </div>
        </div>
    </section>

    <section class="testimonials-enhanced fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Veja o que nossos assinantes estão dizendo</h2>
            <p class="section-subtitle">(Depoimentos reais de profissionais que transformaram suas carreiras)</p>

            <div class="testimonials-grid-narrow">
                <div class="testimonial-video fade-item">
                    <div class="video-wrapper">
                        <div class="youtube-player" data-id="7Rp3-rb4fcs"></div>
                    </div>
                </div>

                <div class="testimonial-video fade-item">
                    <div class="video-wrapper">
                        <div class="youtube-player" data-id="dlf6fIX4nAc"></div>
                    </div>
                </div>

                <div class="testimonial-video fade-item">
                    <div class="video-wrapper">
                        <div class="youtube-player" data-id="LuOWNKPEN3A"></div>
                    </div>
                </div>

                <div class="testimonial-video fade-item">
                    <div class="video-wrapper">
                        <div class="youtube-player" data-id="IZAL_0j7ep8"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pricing-conversion fade-item" id="planos">
        <div class="glass-section">
            <div class="pricing-header">
                <h2 class="section-title">Escolha seu plano e comece hoje mesmo</h2>
                <p class="pricing-subtitle">Acesso imediato a todas as palestras • Sem período mínimo • Cancele quando quiser</p>

                <div class="urgency-banner">
                    <i class="fas fa-fire"></i>
                    <span><strong>Oferta especial:</strong> Acesso completo por menos de R$ 2,00 por dia!</span>
                </div>
            </div>

            <div class="pricing-grid-conversion">
                <div class="price-card popular fade-item">
                    <div class="badge-container">
                        <div class="badge popular-badge"> Mais popular</div>
                    </div>
                    <h4>Mensal</h4>
                    <div class="price-section">
                        <div class="price">R$ 53</div>
                        <div class="price-per-day">R$ 1,77/dia</div>
                    </div>
                    <div class="price-benefits">
                        <div class="benefit">✅ Acesso a todas as palestras</div>
                        <div class="benefit">✅ Certificados automáticos</div>
                        <div class="benefit">✅ Suporte prioritário</div>
                        <div class="benefit">✅ Descontos em eventos</div>
                        <div class="benefit">✅ Sorteios mensais de livros</div>
                    </div>
                    <a href="https://pay.hotmart.com/V94273047M?off=i1hvrpr2&checkoutMode=10" class="cta-btn cta-plan cta-popular" target="_blank">
                        <i class="fas fa-fire"></i> Assinar agora
                    </a>
                    <p class="plan-guarantee"> Acesso imediato após pagamento</p>
                </div>

                <div class="price-card fade-item">
                    <div class="badge-container">
                        <div class="badge invisible">Placeholder</div>
                    </div>
                    <h4>Trimestral</h4>
                    <div class="price-section">
                        <div class="price">R$ 150</div>
                        <div class="price-per-day">R$ 1,67/dia</div>
                        <div class="savings">Economize R$ 9</div>
                    </div>
                    <div class="price-benefits">
                        <div class="benefit">✅ Acesso a todas as palestras</div>
                        <div class="benefit">✅ Certificados automáticos</div>
                        <div class="benefit">✅ Suporte prioritário</div>
                        <div class="benefit">✅ Descontos em eventos</div>
                        <div class="benefit">✅ Sorteios mensais de livros</div>
                    </div>
                    <a href="https://pay.hotmart.com/V94273047M?off=whfa869v&checkoutMode=10" class="cta-btn cta-plan" target="_blank">
                        <i class="fas fa-credit-card"></i> Assinar agora
                    </a>
                    <p class="plan-guarantee"> Economize com o plano trimestral</p>
                </div>

                <div class="price-card fade-item">
                    <div class="badge-container">
                        <div class="badge invisible">Placeholder</div>
                    </div>
                    <h4>Semestral</h4>
                    <div class="price-section">
                        <div class="price">R$ 285</div>
                        <div class="price-per-day">R$ 1,58/dia</div>
                        <div class="savings">Economize R$ 33</div>
                    </div>
                    <div class="price-benefits">
                        <div class="benefit">✅ Acesso a todas as palestras</div>
                        <div class="benefit">✅ Certificados automáticos</div>
                        <div class="benefit">✅ Suporte prioritário</div>
                        <div class="benefit">✅ Descontos em eventos</div>
                        <div class="benefit">✅ Sorteios mensais de livros</div>
                    </div>
                    <a href="https://pay.hotmart.com/V94273047M?off=qh0m3cuy&checkoutMode=10" class="cta-btn cta-plan" target="_blank">
                        <i class="fas fa-gift"></i> Assinar agora
                    </a>
                    <p class="plan-guarantee"> Mais economia no plano semestral</p>
                </div>

                <div class="price-card best-value fade-item">
                    <div class="badge-container">
                        <div class="badge best-value-badge"> Melhor custo-benefício</div>
                    </div>
                    <h4>Anual</h4>
                    <div class="price-section">
                        <div class="price">R$ 550</div>
                        <div class="price-per-day">R$ 1,51/dia</div>
                        <div class="savings">Economize R$ 86</div>
                    </div>
                    <div class="price-benefits">
                        <div class="benefit">✅ Acesso a todas as palestras</div>
                        <div class="benefit">✅ Certificados automáticos</div>
                        <div class="benefit">✅ Suporte prioritário</div>
                        <div class="benefit">✅ Descontos em eventos</div>
                        <div class="benefit">✅ Sorteios mensais de livros</div>
                    </div>
                    <a href="https://pay.hotmart.com/V94273047M?off=cor1dwtx&checkoutMode=10" class="cta-btn cta-plan cta-best" target="_blank">
                        <i class="fas fa-crown"></i> Assinar agora
                    </a>
                    <p class="plan-guarantee"> Maior desconto disponível</p>
                </div>
            </div>

            <div class="pricing-footer">
                <div class="payment-options">
                    <h4> Formas de pagamento aceitas:</h4>
                    <div class="payment-icons">
                        <span>💳 Cartão de crédito</span>
                        <span>🏦 PIX</span>
                        <span>📄 Boleto</span>
                        <span>💸 PayPal</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="guarantee-enhanced fade-item">
      <div class="glass-section">
        <div class="guarantee-content">
          <div class="guarantee-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h2>Garantia de satisfação</h2>
          <div class="guarantee-points expertise-grid">
            <div class="expertise-card fade-item">
              <i class="fas fa-calendar-times"></i>
              <h4>Cancele quando quiser</h4>
              <p>Sem multas, sem burocracia. Um clique e pronto.</p>
            </div>
            <div class="expertise-card fade-item">
              <i class="fas fa-mobile-alt"></i>
              <h4>Acesso total imediato</h4>
              <p>Computador, tablet, celular - onde você estiver.</p>
            </div>
            <div class="expertise-card fade-item">
              <i class="fas fa-headset"></i>
              <h4>Suporte dedicado</h4>
              <p>Equipe especializada pronta para ajudar.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="creator-authority fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Criada por William Cassemiro</h2>
            <p class="section-subtitle">Ex-presidente da ABRATES e referência nacional em tradução</p>

            <div class="creator-info-enhanced-centered">
                <div class="creator-image">
                    <img src="/images/william.png" alt="William Cassemiro" loading="lazy" width="300" height="300">
                </div>
                <div class="creator-credentials expertise-grid">
                  <div class="expertise-card fade-item credential-item">
                    <i class="fas fa-university" style="color: #f39c12;"></i>
                    <h4>Formação sólida</h4>
                    <p>Bacharel em Letras pela USP</p>
                  </div>
                  <div class="expertise-card fade-item credential-item">
                    <i class="fas fa-medal" style="color: #f39c12;"></i>
                    <h4>Experiência comprovada</h4>
                    <p>Ex-diretor e ex-presidente da ABRATES (2014-2018)</p>
                  </div>
                  <div class="expertise-card fade-item credential-item">
                    <i class="fas fa-globe" style="color: #f39c12;"></i>
                    <h4>Reconhecimento internacional</h4>
                    <p>Palestrante em eventos no Brasil e exterior</p>
                  </div>
                  <div class="expertise-card fade-item credential-item">
                    <i class="fas fa-chart-line" style="color: #f39c12;"></i>
                    <h4>Carreira de sucesso</h4>
                    <p>Mais de 20 anos transformando carreiras</p>
                  </div>
                </div>
            </div>
        </div>
    </section>

    <section class="faq-conversion fade-item" id="faq">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Perguntas frequentes</h2>
            <p class="section-subtitle">Tire suas dúvidas antes de começar sua transformação</p>

            <div class="faq-grid">
                <div class="faq-column">
                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Como funciona o acesso? É realmente imediato?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Sim, é imediato!</strong> Após confirmar o pagamento, você recebe login e senha na hora. Em menos de 2 minutos já está assistindo às palestras.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Posso cancelar realmente a qualquer momento?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Absolutamente!</strong> Não há multa, burocracia ou período mínimo. Você cancela quando quiser direto na plataforma ou entrando em contato conosco.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Vale a pena para quem está começando na área?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Com certeza!</strong> Temos palestras desde o nível iniciante até o avançado. Muitos de nossos assinantes começaram do zero e hoje são profissionais estabelecidos no mercado.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Os certificados são aceitos pelo mercado?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Sim!</strong> Nossos certificados são reconhecidos e você pode incluí-los em seu currículo e LinkedIn para comprovar sua educação continuada.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Como vocês conseguem manter o preço tão baixo?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Modelo de assinatura!</strong> Ao invés de cobrar mais por evento individual, oferecemos acesso completo por uma mensalidade acessível. Todos ganham.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>A plataforma funciona no celular?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Sim!</strong> Nossa plataforma é totalmente responsiva e funciona perfeitamente em dispositivos móveis, tablets e desktops.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-column">
                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Quanto tempo leva para ver resultados?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Varia de pessoa para pessoa,</strong> mas muitos assinantes relatam melhorias em 30-60 dias. O conhecimento é imediatamente aplicável a seus projetos.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Como obter os certificados?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Os certificados são gerados automaticamente</strong> após assistir uma palestra completa. Você pode baixá-los diretamente da plataforma, em formato PDF.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Posso baixar os glossários?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Sim!</strong> Na seção Glossários você encontra materiais especializados para download gratuito. Todos os arquivos estão disponíveis em formato PDF.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Os pagamentos são seguros?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Sim!</strong> Utilizamos sistemas de pagamento criptografados e seguros. Todos os dados são protegidos conforme as melhores práticas de segurança.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Esqueci minha senha, como recuperar?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Fácil!</strong> Na página de login, clique em "Esqueci minha senha" e siga as instruções enviadas para seu email cadastrado.</p>
                        </div>
                    </div>

                    <div class="faq-item fade-item" onclick="toggleFaq(this)">
                        <div class="faq-question">
                            <h4>Como atualizar meus dados pessoais?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p><strong>Simples!</strong> Acesse sua área de Perfil para atualizar informações pessoais, alterar senha e gerenciar suas preferências.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="final-cta fade-item">
        <div class="glass-section">
            <h2 class="section-title section-title-centered">Não deixe sua carreira parada</h2>
            <p class="final-message">Enquanto você está pensando, outros profissionais estão se capacitando e avançando no mercado.</p>

            <div class="urgency-stats">
                <div class="stat-item">
                    <div class="stat-number">+1.500</div>
                    <div class="stat-label">Profissionais já transformaram suas carreiras</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">+400</div>
                    <div class="stat-label">Palestras esperando por você</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">R$ 1,51</div>
                    <div class="stat-label">Por dia no plano anual</div>
                </div>
            </div>

            <div class="final-cta-action">
                <a href="#planos" class="cta-btn cta-final mega-btn">
                    <i class="fas fa-rocket"></i> Transformar minha carreira agora
                </a>
                <p class="final-guarantee">⚡ Acesso em 2 minutos • 🛡️ Garantia total • ❌ Cancele quando quiser</p>
            </div>
        </div>
    </section>

    <?php if (!$is_logged_in): ?>
    <section class="glass-section fade-item login-highlight">
        <div class="login-content">
            <h2 class="section-title">Já é assinante?</h2>

            <div class="login-actions">
                <a href="/login.php" class="cta-btn login-btn">
                    <i class="fa-solid fa-key"></i> Fazer login
                </a>
                <a href="/registro.php" class="cta-btn register-btn">
                    <i class="fa-solid fa-user-plus"></i> Criar conta
                </a>
            </div>
            <p class="free-account-info">
                Com uma conta gratuita, você terá acesso aos nossos glossários em pdf.
            </p>
        </div>
    </section>
    <?php endif; ?>
</main>

<div class="modal-overlay-vision" id="lectureModal" style="display: none;">
    <div class="modal-vision">
        <div class="modal-header-vision">
            <div class="modal-title-container">
                <div class="modal-icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h3 id="modalTitle" class="modal-title-vision">Editar Palestra</h3>
            </div>
            <button class="close-btn-vision" onclick="closeLectureModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body-vision">
            <form class="form-vision" id="lectureForm" enctype="multipart/form-data">
                <input type="hidden" id="lectureId" name="lectureId">

                <div class="form-group-vision">
                    <label for="lectureImage" class="form-label-vision">
                        <i class="fas fa-image"></i>
                        Imagem da Palestra
                    </label>
                    <div class="file-upload-vision">
                        <input type="file" id="lectureImage" name="lectureImage" accept="image/*" class="file-input-vision">
                        <div class="file-upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Arraste uma imagem ou clique para selecionar</span>
                            <small>Proporção recomendada: 16:9 (JPG, PNG, WEBP)</small>
                        </div>
                    </div>
                </div>

                <div class="form-grid-vision">
                    <div class="form-group-vision">
                        <label for="lectureSpeaker" class="form-label-vision">
                            <i class="fas fa-user"></i>
                            Palestrante
                        </label>
                        <input type="text" id="lectureSpeaker" name="lectureSpeaker" class="form-input-vision" required placeholder="Nome do palestrante">
                    </div>

                    <div class="form-group-vision">
                        <label for="lectureTitle" class="form-label-vision">
                            <i class="fas fa-heading"></i>
                            Título da Palestra
                        </label>
                        <input type="text" id="lectureTitle" name="lectureTitle" class="form-input-vision" required placeholder="Título da palestra">
                    </div>
                </div>

                <div class="form-grid-vision form-grid-datetime">
                    <div class="form-group-vision">
                        <label for="lectureDate" class="form-label-vision">
                            <i class="fas fa-calendar-alt"></i>
                            Data da Palestra
                        </label>
                        <input type="date" id="lectureDate" name="lectureDate" class="form-input-vision" required>
                    </div>

                    <div class="form-group-vision">
                        <label for="lectureTime" class="form-label-vision">
                            <i class="fas fa-clock"></i>
                            Horário
                        </label>
                        <input type="time" id="lectureTime" name="lectureTime" class="form-input-vision" value="19:00" required>
                    </div>
                </div>

                <div class="form-group-vision">
                    <label for="lectureSummary" class="form-label-vision">
                        <i class="fas fa-align-left"></i>
                        Descrição da Palestra
                    </label>
                    <textarea id="lectureSummary" name="lectureSummary" class="form-textarea-vision" rows="4" required placeholder="Descreva o conteúdo e objetivos da palestra..."></textarea>
                </div>

                <div class="form-actions-vision">
                    <button type="button" class="btn-vision btn-cancel-vision" onclick="closeLectureModal()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn-vision btn-save-vision">
                        <i class="fas fa-save"></i>
                        Salvar Palestra
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/vision/includes/footer.php'; ?>

<style>
/* Enhanced Conversion-Focused Styles */
:root {
    --brand-purple: #8e44ad;
    --brand-purple-dark: #5e3370;
    --brand-purple-light: #a569bd;
    --accent-gold: #f39c12;
    --accent-green: #27ae60;
    --accent-red: #e74c3c;
    --text-primary: #ffffff;
    --text-secondary: #f0f0f0;
    --text-muted: #d4d4d4;
    --glass-bg: rgba(255, 255, 255, 0.05);
    --glass-border: rgba(255, 255, 255, 0.15);
    
    /* Apple Vision Pro Cores */
    --vision-glass: rgba(255, 255, 255, 0.08);
    --vision-glass-border: rgba(255, 255, 255, 0.12);
    --vision-blur: blur(40px);
    --vision-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    --vision-shadow-light: 0 4px 20px rgba(142, 68, 173, 0.2);
}

/* Base CTA Button style */
.cta-btn {
    display: inline-flex;
    align-items: center !important;
    text-align: center !important;
    gap: 50px;
    padding: 14px 28px;
    font-size: 1.1rem;
    font-weight: bold;
    border-radius: 30px;
    background: var(--brand-purple);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(142, 68, 173, 0.6);
    transition: transform 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    border: none;
    cursor: pointer;
}
.cta-btn:hover {
    background: var(--brand-purple-dark);
}

/* MELHORIAS IMPLEMENTADAS */

/* 1. Espaçamento melhorado após botão "Quero começar agora" */
.hero-cta-section {
    margin-top: 50px; 
    margin-bottom: 80px; 
}

/* 2. Títulos centralizados */
.section-title-centered {
    text-align: center;
}

/* 3. Seção William Cassemiro centralizada com mais espaçamento */
.creator-authority {
    margin-top: 120px; 
}

.creator-info-enhanced-centered {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 40px;
    margin: 50px 0;
}

.creator-info-enhanced-centered .creator-image {
    margin-bottom: 20px;
}

.creator-info-enhanced-centered .creator-credentials {
    display: grid;
    gap: 25px;
    max-width: 800px;
}


/* ==============================================
   FORÇANDO A CENTRALIZAÇÃO IDÊNTICA AOS DEPOIMENTOS
   ============================================== */

/* Resetando o container principal para evitar conflitos de grid/flex herdados */
.value-problem-combined {
    margin: 40px 0;
    width: 100%;
    display: block;
}

.main-content-grid {
    width: 100%;
    margin: auto;
    padding: 0 20px;
    display: block;
    align-content: center;
}

/* Aplicando exatamente a mesma regra que mantém os vídeos perfeitamente no centro */
.value-grid-compact,
.challenge-cards-grid,
.solution-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    margin: 50px auto; /* Margin auto é a chave do depoimento */
    max-width: 900px;  /* Largura travada em 900px, como no depoimento */
}

/* Área que envelopa Desafios e Soluções */
.problem-solution-grid {
    display: block; /* Garante que os blocos desçam um após o outro, sem empurrar para o lado */
    width: 100%;
    margin-top: 60px;
}

/* Alinhando as seções individualmente com 900px */
.challenge-solution-section,
.solution-section {
    max-width: 900px;
    margin: 0 auto 60px auto; 
}

.challenge-title {
    color: var(--accent-red);
    font-size: 2.2rem;
    text-align: center;
    margin-bottom: 20px;
    font-weight: 600;
}

.solution-title {
    color: var(--accent-green);
    font-size: 2.2rem;
    text-align: center;
    margin-bottom: 20px;
    font-weight: 600;
}

/* ============================================== */


.challenge-card,
.solution-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s ease;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.challenge-card:hover,
.solution-card:hover {
    border-color: var(--brand-purple);
    box-shadow: 0 20px 40px rgba(142, 68, 173, 0.3);
}

.challenge-icon,
.solution-icon {
    margin-bottom: 20px;
}

.challenge-icon i {
    font-size: 2.5rem;
    color: var(--accent-red);
}

.solution-icon i {
    font-size: 2.5rem;
    color: var(--accent-green);
}

.challenge-card h4,
.solution-card h4 {
    font-size: 1.3rem;
    margin-bottom: 15px;
    color: var(--text-primary);
    font-weight: 600;
}

.challenge-card p,
.solution-card p {
    line-height: 1.6;
    color: var(--text-secondary);
    font-size: 1rem;
}

/* 5. Área de vídeos mais estreita (Referência) */
.testimonials-grid-narrow {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    margin: 50px auto;
    max-width: 900px; 
}

/* 6. Nova seção de palestras com horário */
.upcoming-lectures {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.1), rgba(255, 255, 255, 0.05));
    margin: 40px 0;
}

.admin-controls {
    text-align: center;
    margin-bottom: 40px;
}

.btn-admin-add {
    background: linear-gradient(135deg, var(--brand-purple), var(--brand-purple-dark));
    color: var(--text-primary);
    padding: 15px 30px;
    border: none;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.btn-admin-add:hover {
    box-shadow: 0 10px 25px rgba(142, 68, 173, 0.5);
}

/* Grid das palestras (3 colunas) */
.lectures-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin: 50px 0;
}


.edit-lecture-btn, .delete-lecture-btn {
    position: absolute;
    top: 15px;
    
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    
    /* Para alinhar os dois botões no topo */
    font-size: 1rem;
    padding: 0;
}

.edit-lecture-btn {
    background: rgba(142, 68, 173, 0.9);
    right: 15px; 
}

.edit-lecture-btn:hover {
    background: var(--brand-purple);
}

.delete-lecture-btn {
    background: rgba(231, 76, 60, 0.9);
    right: 65px; /* Deixa 50px de espaço entre os botões */
}

.delete-lecture-btn:hover {
    background: var(--accent-red);
}

.lecture-image-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%; /* 16:9 aspect ratio */
    overflow: hidden;
    border-radius: 12px;
}

.lecture-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.lecture-info {
    padding-top: 16px;
}

.lecture-datetime {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.lecture-date {
    background: var(--brand-purple);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
}

.lecture-time {
    background: var(--accent-gold);
    color: white;
    padding: 6px 12px;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
}

.lecture-title {
    font-size: 1.2rem;
    color: var(--text-primary);
    margin: 8px 0 10px;
    font-weight: 700;
    line-height: 1.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: calc(1.25rem * 3); 
}

.lecture-speaker {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
    font-size: 1.15rem;            
    color: var(--brand-purple-dark); 
    margin-bottom: 10px;
}

.lecture-speaker i {
    color: var(--accent-gold);
}

.lecture-summary {
    color: var(--text-secondary);
    font-size: 0.95rem;
    line-height: 1.4rem;
    display: -webkit-box;
    -webkit-line-clamp: 5;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    max-height: calc(1.4rem * 5);
    margin: 0;
}

.lecture-card {
    position: relative;
    border: 2px solid transparent;
    border-radius: 20px;
    padding: 20px;
    background: rgba(255, 255, 255, 0.08);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.lecture-card:hover {
    border-color: var(--accent-green);
    box-shadow: 0 20px 40px rgba(39, 174, 96, 0.3);
}

.schedule-actions-bottom {
    margin-top: 20px;
    text-align: center;
}

.cta-secondary {
    background: linear-gradient(135deg, var(--accent-gold), #e67e22);
    color: var(--text-primary);
}

/* ==============================================
   MODAL APPLE VISION PRO STYLE
   ============================================== */

.modal-overlay-vision {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    animation: modalFadeIn 0.4s ease-out forwards;
}

@keyframes modalFadeIn {
    to { opacity: 1; }
}

.modal-vision {
    background: var(--vision-glass);
    backdrop-filter: var(--vision-blur);
    border: 1px solid var(--vision-glass-border);
    border-radius: 24px;
    box-shadow: var(--vision-shadow);
    max-width: 680px;
    width: 95%;
    max-height: 85vh;
    overflow: hidden;
    transform: scale(0.9) translateY(20px);
    animation: modalSlideIn 0.4s ease-out 0.1s forwards;
}

@keyframes modalSlideIn {
    to {
        transform: scale(1) translateY(0);
    }
}

.modal-header-vision {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 28px;
    border-bottom: 1px solid var(--vision-glass-border);
    background: linear-gradient(135deg, 
        rgba(142, 68, 173, 0.1), 
        rgba(255, 255, 255, 0.05)
    );
}

.modal-title-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--brand-purple), var(--brand-purple-dark));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
    box-shadow: var(--vision-shadow-light);
}

.modal-title-vision {
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.close-btn-vision {
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.close-btn-vision:hover {
    background: rgba(255, 255, 255, 0.15);
    color: var(--text-primary);
    transform: scale(1.05);
}

.modal-body-vision {
    padding: 28px;
    overflow-y: auto;
    max-height: calc(85vh - 100px);
}

.form-vision {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-group-vision {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label-vision {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.form-label-vision i {
    color: var(--accent-gold);
    font-size: 0.9rem;
}

.form-input-vision,
.form-textarea-vision {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 14px 16px;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.form-input-vision:focus,
.form-textarea-vision:focus {
    outline: none;
    border-color: var(--brand-purple);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 2px rgba(142, 68, 173, 0.2);
}

.form-input-vision::placeholder,
.form-textarea-vision::placeholder {
    color: var(--text-muted);
}

.form-textarea-vision {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
    line-height: 1.5;
}

.form-grid-vision {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-grid-datetime {
    grid-template-columns: 1.2fr 0.8fr;
}

/* File Upload com estilo Apple Vision */
.file-upload-vision {
    position: relative;
    overflow: hidden;
}

.file-input-vision {
    position: absolute;
    left: -9999px;
    opacity: 0;
}

.file-upload-placeholder {
    background: rgba(255, 255, 255, 0.04);
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 32px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.file-upload-placeholder:hover {
    border-color: var(--brand-purple);
    background: rgba(255, 255, 255, 0.06);
}

.file-upload-placeholder i {
    font-size: 2rem;
    color: var(--accent-gold);
    margin-bottom: 12px;
    display: block;
}

.file-upload-placeholder span {
    display: block;
    color: var(--text-primary);
    font-weight: 500;
    margin-bottom: 6px;
}

.file-upload-placeholder small {
    color: var(--text-muted);
    font-size: 0.85rem;
}

/* Botões de Ação */
.form-actions-vision {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid var(--vision-glass-border);
}

.btn-vision {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(20px);
    min-width: 140px;
    justify-content: center;
}

.btn-cancel-vision {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.btn-cancel-vision:hover {
    background: rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    transform: translateY(-1px);
}

.btn-save-vision {
    background: linear-gradient(135deg, var(--brand-purple), var(--brand-purple-dark));
    color: white;
    border: 1px solid var(--brand-purple);
    box-shadow: var(--vision-shadow-light);
}

.btn-save-vision:hover {
    background: linear-gradient(135deg, var(--brand-purple-dark), var(--brand-purple));
    transform: translateY(-1px);
    box-shadow: 0 6px 25px rgba(142, 68, 173, 0.4);
}

/* Enhanced Typography for Conversion */
.hero-headline {
    font-size: 3.5rem;
    font-weight: 800;
    color: var(--text-primary);
    text-align: center;
    margin-bottom: 30px;
    line-height: 1.2;
    text-shadow: 0 4px 20px rgba(142, 68, 173, 0.6);
}

.hero-subheadline {
    font-size: 1.4rem;
    color: var(--text-secondary);
    text-align: center;
    margin-bottom: 40px;
    line-height: 1.6;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.section-subtitle {
    font-size: 1.2rem;
    color: var(--text-secondary);
    text-align: center;
    margin-bottom: 40px;
    font-style: italic;
}

/* Hero Content Conversion */
.hero-content-conversion {
    max-width: 1000px;
    margin: 0 auto;
    text-align: center;
}

.social-proof-hero {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin: 40px auto;
    max-width: 700px;
}

.proof-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 12px;
    font-weight: 500;
}

.proof-item i {
    color: var(--accent-gold);
    font-size: 1.2rem;
}

.hero-guarantee {
    margin-top: 15px;
    font-size: 1.1rem;
    color: var(--accent-green);
    font-weight: 600;
}

/* Pulse Animation for CTA */
.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Expertise Grid */
.expertise-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin: 50px 0;
}

.expertise-card {
    background: rgba(255, 255, 255, 0.08);
    padding: 30px;
    border-radius: 18px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.expertise-card:hover {
    background: rgba(142, 68, 173, 0.15);
    border-color: var(--brand-purple);
}

.expertise-card i {
    font-size: 2.5rem;
    color: var(--accent-gold);
    margin-bottom: 20px;
}

.expertise-card h4 {
    font-size: 1.3rem;
    margin-bottom: 15px;
    color: var(--text-primary);
}

.expertise-cta {
    text-align: center;
    margin-top: 40px;
}

/* Video wrapper */
.video-wrapper {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 56.25%; /* 16:9 aspect ratio */
    border-radius: 16px;
    overflow: hidden;
}

.video-wrapper iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 16px;
}

.testimonial-video {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.testimonial-video:hover {
    box-shadow: 0 15px 40px rgba(142, 68, 173, 0.3);
}

/* Conversion-Optimized Pricing */
.pricing-header {
    text-align: center;
    margin-bottom: 50px;
}

.pricing-subtitle {
    font-size: 1.2rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.urgency-banner {
    background: linear-gradient(135deg, var(--accent-red), #c0392b);
    color: white;
    padding: 15px 30px;
    border-radius: 25px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    animation: pulse 2s infinite;
    margin-top: 20px;
}

.pricing-grid-conversion {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin: 50px 0;
}

.price-card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(20px);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    padding: 35px 25px;
    text-align: center;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    min-height: 400px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.price-card.popular {
    border-color: var(--accent-red);
    background: rgba(231, 76, 60, 0.1);
}

.price-card.best-value {
    border-color: var(--accent-green);
    background: rgba(39, 174, 96, 0.1);
}

.badge-container {
    height: 45px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    margin-bottom: 15px;
}

.badge {
    padding: 10px 20px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.popular-badge {
    background: linear-gradient(135deg, var(--accent-red), #c0392b);
    color: white;
    box-shadow: 0 6px 20px rgba(231, 76, 60, 0.5);
}

.best-value-badge {
    background: linear-gradient(135deg, var(--accent-green), #229954);
    color: white;
    box-shadow: 0 6px 20px rgba(39, 174, 96, 0.5);
}

.badge.invisible {
    visibility: hidden;
}

.price-card h4 {
    font-size: 1.6rem;
    color: var(--text-primary);
    margin: 20px 0;
    font-weight: 600;
}

.price-section {
    text-align: center;
    margin: 20px 0;
}

.price {
    font-size: 3rem;
    font-weight: 700;
    color: var(--accent-gold);
    margin: 25px 0;
    text-shadow: 0 2px 10px rgba(243, 156, 18, 0.5);
}

.price-per-day {
    font-size: 1.1rem;
    color: var(--accent-green);
    font-weight: 600;
    margin-top: 5px;
}

.savings {
    background: var(--accent-green);
    color: white;
    padding: 5px 15px;
    border-radius: 15px;
    font-size: 0.9rem;
    font-weight: bold;
    margin-top: 10px;
    display: inline-block;
}

.price-benefits {
    margin: 25px 0;
    text-align: left;
}

.benefit {
    padding: 8px 0;
    font-size: 0.95rem;
    color: var(--text-secondary);
}

.cta-plan {
    width: 100%;
    font-size: 1.1rem;
    text-align: center;
    align-items: center;
    text-align: center !important;
    padding: 16px;
    font-weight: 700;
    text-transform: uppercase;
}

.cta-popular {
    background: linear-gradient(135deg, var(--accent-red), #c0392b);
    animation: pulse 2s infinite;
}

.cta-best {
    background: linear-gradient(135deg, var(--accent-green), #229954);
}

.plan-guarantee {
    font-size: 0.9rem;
    color: var(--accent-green);
    text-align: center;
    margin-top: 10px;
    font-weight: 500;
}

.price-card:hover {
    box-shadow: 0 20px 50px rgba(142, 68, 173, 0.4);
}

.payment-options {
    text-align: center;
    margin-top: 40px;
    padding: 30px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
}

.payment-icons {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 15px;
    flex-wrap: wrap;
}

.payment-icons span {
    background: rgba(255, 255, 255, 0.1);
    padding: 10px 15px;
    border-radius: 10px;
    font-size: 0.9rem;
}

/* Enhanced Guarantee */
.guarantee-enhanced {
    background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(142, 68, 173, 0.1));
}

.guarantee-content {
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
}

.guarantee-icon i {
    font-size: 5rem;
    color: var(--accent-green);
    margin-bottom: 30px;
}

.guarantee-points {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 40px;
}

.guarantee-point {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    text-align: left;
}

.guarantee-point i {
    font-size: 2rem;
    color: var(--accent-green);
    margin-top: 5px;
}

.guarantee-point h4 {
    margin-bottom: 10px;
    color: var(--text-primary);
}

/* Creator Authority */
.creator-credentials {
    display: grid;
    grid-template-columns: repeat(2, 1fr); 
    gap: 30px; 
}

.credential-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    border: 2px solid var(--brand-purple); 
    transition: all 0.3s ease;
    cursor: pointer;
}

.credential-item:hover {
    border-color: var(--accent-gold); 
    box-shadow: 0 10px 30px rgba(142, 68, 173, 0.4); 
}
.credential-item i {
    font-size: 2rem;
    color: var(--accent-gold);
    margin-top: 5px;
}

.credential-item h4 {
    margin-bottom: 8px;
    color: var(--text-primary);
}

.creator-image img {
    border-radius: 50% !important; /* Garante um círculo perfeito */
    border: 4px solid var(--accent-gold);
    box-shadow: 0 20px 50px rgba(243, 156, 18, 0.4);
    object-fit: cover;
    object-position: center top; /* Centraliza a imagem no topo */
}

/* Final CTA */
.final-cta {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.2), rgba(243, 156, 18, 0.1));
}

.final-message {
    font-size: 1.3rem;
    text-align: center;
    margin-bottom: 40px;
    color: var(--text-secondary);
    font-style: italic;
}

.urgency-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin: 40px 0;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.stat-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 30px;
    border-radius: 20px;
}

.stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--accent-gold);
    margin-bottom: 10px;
}

.stat-label {
    font-size: 1.1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.final-cta-action {
    text-align: center;
    margin-top: 50px;
}

.mega-btn {
    font-size: 1.4rem;
    padding: 25px 50px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: linear-gradient(135deg, var(--brand-purple), var(--accent-gold));
    animation: pulse 3s infinite;
}

.final-guarantee {
    margin-top: 20px;
    font-size: 1.2rem;
    color: var(--accent-green);
    font-weight: 600;
}

/* Enhanced CTA Buttons */
.cta-primary {
    background: linear-gradient(135deg, var(--brand-purple), var(--brand-purple-dark));
    font-size: 1.3rem;
    padding: 20px 40px;
}

.cta-secondary {
    background: linear-gradient(135deg, var(--accent-gold), #e67e22);
    color: var(--text-primary);
}

.cta-final {
    background: linear-gradient(135deg, var(--brand-purple), var(--accent-gold));
}

.cta-btn:hover {
    box-shadow: 0 12px 35px rgba(142, 68, 173, 0.8);
}

/* Enhanced FAQ - Two Column Layout */
.faq-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    max-width: 1200px;
    margin: 0 auto;
}

.faq-column {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.faq-conversion .faq-item {
    background: rgba(255, 255, 255, 0.08);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.faq-conversion .faq-item:hover {
    background: rgba(142, 68, 173, 0.15);
    border-color: var(--brand-purple);
}

.faq-question {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30px;
}

.faq-question h4 {
    color: var(--text-primary);
    margin: 0;
    font-size: 1.2rem;
    font-weight: 500;
}

.faq-icon {
    color: var(--accent-gold);
    transition: transform 0.3s ease;
    font-size: 1.2rem;
}

.faq-item.active .faq-icon {
    transform: rotate(180deg);
}

.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.faq-item.active .faq-answer {
    max-height: 200px;
}

.faq-answer p {
    padding: 0 30px 30px;
    color: var(--text-secondary);
    line-height: 1.7;
    margin: 0;
    font-size: 1.1rem;
}

/* Login Section */
.login-highlight {
    background: linear-gradient(135deg, rgba(142, 68, 173, 0.15), rgba(255, 255, 255, 0.05));
    border: 2px solid rgba(142, 68, 173, 0.3);
}

.login-content {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}

.login-btn {
    background: linear-gradient(135deg, var(--brand-purple), var(--brand-purple-dark));
    font-size: 1.2rem;
    padding: 18px 36px;
    font-weight: 600;
}

.register-btn {
    background: linear-gradient(135deg, var(--accent-green), #229954);
    font-size: 1.2rem;
    padding: 18px 36px;
    font-weight: 600;
}

.main-logo {
    max-width: 280px;
    height: auto;
    filter: drop-shadow(0 8px 25px rgba(142, 68, 173, 0.6));
    animation: pulse 3s infinite;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .hero-headline {
        font-size: 2.8rem;
    }
    
    .social-proof-hero {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .value-grid-compact {
        grid-template-columns: 1fr;
    }
    
    .challenge-cards-grid,
    .solution-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .creator-info-enhanced-centered {
        text-align: center;
        gap: 40px;
    }
    
    .testimonials-grid-narrow {
        grid-template-columns: 1fr;
    }
    
    .expertise-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .lectures-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .guarantee-points {
        grid-template-columns: 1fr;
    }
    
    .faq-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .form-grid-vision {
        grid-template-columns: 1fr;
    }

    .modal-vision {
        width: 98%;
        margin: 10px;
    }

    .form-actions-vision {
        flex-direction: column;
    }

    .btn-vision {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .hero-headline {
        font-size: 2.2rem;
    }
    
    .expertise-grid {
        grid-template-columns: 1fr;
    }
    
    .lectures-grid {
        grid-template-columns: 1fr;
    }
    
    .pricing-grid-conversion {
        grid-template-columns: 1fr;
    }
    
    .urgency-stats {
        grid-template-columns: 1fr;
    }
    
    .payment-icons {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .glass-section {
        padding: 40px 20px;
        border-radius: 16px;
    }

    .modal-body-vision {
        padding: 20px;
    }

    .form-actions-vision {
        margin-top: 24px;
        padding-top: 16px;
    }
}

@media (max-width: 480px) {
    .hero-headline {
        font-size: 1.8rem;
    }
    
    .mega-btn {
        font-size: 1.1rem;
        padding: 18px 30px;
    }
    
    .glass-section {
        padding: 30px 16px;
        margin: 20px 0;
    }
    
    .login-actions {
        flex-direction: column;
        align-items: center;
    }

    .login-actions .cta-btn {
        width: 100%;
        max-width: 280px;
    }

    .modal-vision {
        max-height: 95vh;
    }

    .modal-body-vision {
        padding: 16px;
    }

    .modal-header-vision {
        padding: 16px 20px;
    }

    .file-upload-placeholder {
        padding: 20px 16px;
    }

    .form-input-vision,
    .form-textarea-vision {
        padding: 12px 14px;
    }
}

/* ==============================================
   CORREÇÕES SOLICITADAS
   ============================================== */

/* 1. Corrige a imagem do criador para ser circular e bem enquadrada */
.creator-image img {
    border-radius: 50% !important; /* Garante um círculo perfeito */
    border: 4px solid var(--accent-gold);
    box-shadow: 0 20px 50px rgba(243, 156, 18, 0.4);
    object-fit: cover;
    object-position: center top; /* Centraliza a imagem no topo */
}

/* 2. Corrige o deslocamento do conteúdo principal */
.main-content {
    /* Remove qualquer deslocamento estático e prepara para a animação */
    margin-left: 0; 
    transition: margin-left 0.4s ease; /* Adiciona uma transição suave */
}

/* Aplica o deslocamento SOMENTE quando o menu estiver aberto.
   É necessário que seu JavaScript adicione a classe 'sidebar-open' 
   ao <body> quando o menu for ativado.
*/
body.sidebar-open .main-content {
    /* Ajuste '280px' para a largura exata da sua sidebar */
    margin-left: 280px; 
}

/*
  Ajuste de tamanho da imagem
*/

.creator-image {
    /* ADICIONE ESTAS DUAS LINHAS PARA CONTROLAR O TAMANHO */
    width: 250px;
    height: 250px;

    /* Suas regras que já funcionam: */
    border-radius: 50% !important;
    overflow: hidden;
    border: 5px solid var(--brand-purple);
    box-shadow: 0 0 20px rgba(142, 68, 173, .5);
    margin-bottom: 20px;
}

.creator-image img {
    /* Estas duas linhas garantem que a imagem preencha o contêiner */
    width: 100%;
    height: 100%;
    
    /* Suas regras que já funcionam: */
    border-radius: 50% !important;
    border: 4px solid var(--accent-gold);
    box-shadow: 0 20px 50px rgba(243, 156, 18, 0.4);
    object-fit: cover !important;
    object-position: center top !important;
}
/* Container que envolve toda a área de valores e problemas */
.value-problem-combined {
    margin: 40px 0;
    width: 100%;
    display: flex;
    justify-content: center !important; /* Mantém tudo no centro em telas ultra-largas */
}

/* Grid principal travada em 900px (mesma largura dos vídeos) */
.main-content-grid {
    width: 100%;
    max-width: 900px !important; 
    margin: 0 auto !important;
    padding: 0 20px;
    display: flex !important;
    flex-direction: column;
    align-items: center !important;
}

/* Garante que as sub-grades ocupem no máximo os 900px disponíveis */
.value-grid-compact,
.challenge-cards-grid,
.solution-cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
    width: 100%;
    margin: 50px 0;
}

/* Estrutura flexível para a área de Desafios e Soluções */
.problem-solution-grid {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.challenge-solution-section,
.solution-section {
    width: 100%;
    margin-bottom: 60px;
}

.challenge-title,
.solution-title {
    text-align: center;
    margin-bottom: 20px;
}

body {
    background-image: none !important;
    background-color: #ae8fba !important; /* Tom de roxo escuro. Você pode alterar o código HEX para o tom exato que preferir */
}

</style>

<script>
function toggleFaq(element) {
    const isActive = element.classList.contains('active');

    // Close all FAQ items
    document.querySelectorAll('.faq-item').forEach(item => {
        item.classList.remove('active');
    });

    // If the clicked item wasn't active, open it
    if (!isActive) {
        element.classList.add('active');
    }
}

// Fade in animation on scroll
function handleScrollAnimation() {
    const elements = document.querySelectorAll('.fade-item');

    elements.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;
        const elementVisible = 150;

        if (elementTop < window.innerHeight - elementVisible) {
            element.classList.add('visible');
        }
    });
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Modal functions
function openAddLectureModal() {
    document.getElementById('modalTitle').textContent = 'Adicionar Nova Palestra';
    document.getElementById('lectureForm').reset();
    document.getElementById('lectureId').value = '';
    // Definir horário padrão
    document.getElementById('lectureTime').value = '19:00';
    document.getElementById('lectureModal').style.display = 'flex';
}

function editLecture(lectureId) {
    document.getElementById('modalTitle').textContent = 'Editar Palestra';
    document.getElementById('lectureId').value = lectureId;

    // Se for uma palestra padrão, usar dados de exemplo
    if (lectureId.startsWith('default-')) {
        const lectureData = getDefaultLectureData(lectureId);
        populateLectureForm(lectureData);
        document.getElementById('lectureModal').style.display = 'flex';
        return;
    }

    // Buscar dados da API de anúncios
    fetch(`manage_announcements.php?id=${lectureId}`)
        .then(response => {
            if (!response.ok) throw new Error('Falha ao carregar dados da palestra.');
            return response.json();
        })
        .then(data => {
            populateLectureForm(data);
            document.getElementById('lectureModal').style.display = 'flex';
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar dados da palestra. Verifique o console.');
        });
}

/**
 * Funçao para deletar anúncio de palestra
 */
function deleteAnnouncement(announcementId) {
    if (!confirm('Tem certeza de que deseja deletar esta palestra futura? Esta ação é irreversível.')) {
        return;
    }

    if (announcementId.startsWith('default-')) {
        alert('Não é possível deletar palestras de exemplo.');
        return;
    }

    const endpoint = 'manage_announcements.php?id=' + announcementId;

    fetch(endpoint, {
        method: 'DELETE'
    })
    .then(response => {
        if (!response.ok) throw new Error('Falha ao deletar anúncio.');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message || 'Palestra deletada com sucesso!');
            // Remover o card da interface
            const card = document.getElementById('lecture-' + announcementId);
            if (card) {
                card.classList.add('fade-out');
                setTimeout(() => card.remove(), 500);
            }
            location.reload(); // Recarregar para atualizar a lista
        } else {
            alert('Erro ao deletar: ' + (data.error || 'Erro desconhecido.'));
            console.error('Detalhes do erro:', data.error);
        }
    })
    .catch(error => {
        console.error('Erro de conexão:', error);
        alert('Erro de conexão com o servidor. Tente novamente.');
    });
}

function populateLectureForm(lectureData) {
    document.getElementById('lectureSpeaker').value = lectureData.speaker || '';
    document.getElementById('lectureTitle').value = lectureData.title || '';
    document.getElementById('lectureDate').value = lectureData.announcement_date || lectureData.lecture_date || '';
    // Tratar o horário (remover segundos se existir)
    let lectureTime = lectureData.lecture_time || '19:00';
    if (lectureTime.includes(':')) {
        lectureTime = lectureTime.substring(0, 5); // Pegar apenas HH:MM
    }
    document.getElementById('lectureTime').value = lectureTime;
    document.getElementById('lectureSummary').value = lectureData.description || '';
}

function getDefaultLectureData(lectureId) {
    const exampleData = {
        'default-1': {
            speaker: 'Dra. Maria Silva',
            title: 'Técnicas Avançadas de Interpretação Simultânea',
            lecture_date: new Date(Date.now() + (15 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0],
            lecture_time: '19:00',
            description: 'Aprenda as técnicas mais modernas de interpretação simultânea utilizadas em eventos internacionais e conferências de alto nível.'
        },
        'default-2': {
            speaker: 'Dr. Carlos Santos',
            title: 'Tradução Jurídica: Contratos Internacionais',
            lecture_date: new Date(Date.now() + (22 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0],
            lecture_time: '19:00',
            description: 'Domine a terminologia e as nuances da tradução de contratos e documentos jurídicos para o mercado internacional.'
        },
        'default-3': {
            speaker: 'Prof. Ana Costa',
            title: 'IA na Tradução: Como Usar sem Perder Qualidade',
            lecture_date: new Date(Date.now() + (29 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0],
            lecture_time: '19:00',
            description: 'Descubra como integrating ferramentas de IA no seu workflow mantendo a qualidade e o toque humano em suas traduções.'
        }
    };

    return exampleData[lectureId] || {};
}

// Handle form submission - VERSÃO PARA ANÚNCIOS
document.getElementById('lectureForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    // Enviar para API de anúncios
    fetch('manage_announcements.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeLectureModal();
            // Recarregar a página para mostrar as mudanças
            setTimeout(() => {
                location.reload();
            }, 500);
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro de conexão. Verifique se o arquivo manage_announcements.php está acessível.');
    });
});

function closeLectureModal() {
    document.getElementById('lectureModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('lectureModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLectureModal();
    }
});

// File upload interaction
document.addEventListener('DOMContentLoaded', function() {
    handleScrollAnimation();
    window.addEventListener('scroll', handleScrollAnimation);
    
    // File upload visual feedback
    const fileInput = document.getElementById('lectureImage');
    const placeholder = document.querySelector('.file-upload-placeholder');
    
    if (fileInput && placeholder) {
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const fileName = e.target.files[0].name;
                placeholder.querySelector('span').textContent = `Arquivo selecionado: ${fileName}`;
                placeholder.style.borderColor = 'var(--accent-green)';
                placeholder.style.background = 'rgba(39, 174, 96, 0.1)';
            }
        });
        
        placeholder.addEventListener('click', function() {
            fileInput.click();
        });
    }
});
</script>

<script src="js/fix-botoes-editar.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var youtubePlayers = document.querySelectorAll('.youtube-player');

        youtubePlayers.forEach(function(player) {
            var videoId = player.dataset.id;
            var img = new Image();
            img.src = 'https://img.youtube.com/vi/' + videoId + '/hqdefault.jpg';
            img.alt = 'YouTube Video Thumbnail';
            img.classList.add('youtube-thumbnail');

            var playButton = document.createElement('div');
            playButton.classList.add('youtube-play-button');

            player.appendChild(img);
            player.appendChild(playButton);

            player.addEventListener('click', function() {
                var iframe = document.createElement('iframe');
                iframe.setAttribute('src', 'https://www.youtube.com/embed/' + videoId + '?autoplay=1');
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allowfullscreen', '1');
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                iframe.classList.add('youtube-iframe');

                player.innerHTML = '';
                player.appendChild(iframe);
            });
        });
    });
</script>

<style>
    .youtube-player {
        position: relative;
        width: 100%;
        padding-bottom: 56.25%; /* 16:9 aspect ratio */
        background-color: #000;
        cursor: pointer;
        overflow: hidden;
    }

    .youtube-player .youtube-thumbnail {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        top: 0;
        left: 0;
        transition: filter 0.2s ease-in-out;
    }

    .youtube-player:hover .youtube-thumbnail {
        filter: brightness(75%);
    }

    .youtube-player .youtube-play-button {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 68px;
        height: 48px;
        background-color: #ff0000;
        border-radius: 12px;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: transform 0.2s ease-in-out;
    }

    .youtube-player .youtube-play-button::before {
        content: '';
        border-style: solid;
        border-width: 12px 0 12px 20px;
        border-color: transparent transparent transparent #fff;
        margin-left: 5px;
    }

    .youtube-player:hover .youtube-play-button {
        transform: translate(-50%, -50%) scale(1.1);
    }

    .youtube-player .youtube-iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
</style>