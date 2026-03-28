-- Dados de teste para Translators101
USE translators101_db;

-- Limpar dados existentes
DELETE FROM certificates;
DELETE FROM access_logs;
DELETE FROM glossaries;
DELETE FROM lectures;
DELETE FROM users;

-- Inserir usuário administrador
INSERT INTO users (id, email, name, password_hash, role, created_at) VALUES 
(UUID(), 'wrbl.traduz@gmail.com', 'Administrador', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW());
-- Senha: admin123

-- Inserir palestras de teste
INSERT INTO lectures (id, title, speaker, description, duration_minutes, embed_code, thumbnail_url, category, tags, is_featured, is_live, created_at) VALUES 
(UUID(), 'Método para cálculo de valor - EMPREENDE LETRAS 2021', 'William Cassemiro', 'Palestra de William Cassemiro sobre um método para cálculo de valor a ser cobrado no EMPREENDE LETRAS 2021. Aprenda técnicas eficazes para precificar seus serviços de tradução de forma competitiva e justa.', 90, '<div style="position:relative;padding-top:56.25%;"><iframe id="panda-8cb423b9-5023-4764-82d1-d3c103c28e19" src="https://player-vz-9256cd6f-703.tv.pandavideo.com.br/embed/?v=vision-php-upgrade" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen=true width="100%" height="100%" fetchpriority="high"></iframe></div>', 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80', 'Empreendedorismo', '["precificação", "negócios", "freelancer"]', TRUE, FALSE, NOW()),

(UUID(), 'Tradução Técnica: Melhores Práticas', 'Maria Silva', 'Explore as técnicas fundamentais para tradução técnica eficiente, incluindo terminologia especializada e ferramentas CAT.', 75, '<div style="position:relative;padding-top:56.25%;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen=true width="100%" height="100%"></iframe></div>', 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80', 'Tradução Técnica', '["CAT", "terminologia", "técnica"]', TRUE, FALSE, NOW()),

(UUID(), 'Interpretação Simultânea: Técnicas Avançadas', 'João Santos', 'Desenvolva suas habilidades de interpretação simultânea com técnicas profissionais utilizadas em conferências internacionais.', 120, '<div style="position:relative;padding-top:56.25%;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen=true width="100%" height="100%"></iframe></div>', 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80', 'Interpretação', '["simultânea", "conferências", "técnicas"]', FALSE, FALSE, NOW()),

(UUID(), 'Revisão de Textos: Controle de Qualidade', 'Ana Oliveira', 'Aprenda métodos sistemáticos para revisão de textos traduzidos, garantindo alta qualidade e consistência.', 60, '<div style="position:relative;padding-top:56.25%;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen=true width="100%" height="100%"></iframe></div>', 'https://images.unsplash.com/photo-1455390582262-044cdead277a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80', 'Revisão', '["qualidade", "controle", "metodologia"]', FALSE, FALSE, NOW()),

(UUID(), 'Tradução Jurídica: Particularidades e Desafios', 'Roberto Lima', 'Navegue pelas complexidades da tradução jurídica, incluindo terminologia específica e aspectos culturais do direito.', 105, '<div style="position:relative;padding-top:56.25%;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen=true width="100%" height="100%"></iframe></div>', 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80', 'Tradução Jurídica', '["jurídico", "direito", "terminologia"]', TRUE, FALSE, NOW()),

(UUID(), 'Tecnologia na Tradução: IA e o Futuro', 'Carla Fernandes', 'Explore como a inteligência artificial está transformando o mercado de tradução e como se adaptar às novas tecnologias.', 85, '<div style="position:relative;padding-top:56.25%;"><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" allowfullscreen=true width="100%" height="100%"></iframe></div>', 'https://images.unsplash.com/photo-1677442136019-21780ecad995?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80', 'Tecnologia', '["IA", "futuro", "tecnologia"]', FALSE, FALSE, NOW());

-- Inserir glossários de teste
INSERT INTO glossaries (id, term, definition, category, language_pair, created_at) VALUES 
(UUID(), 'CAT Tools', 'Computer-Assisted Translation Tools - Ferramentas de tradução assistida por computador que ajudam tradutores a trabalhar de forma mais eficiente.', 'Tecnologia', 'EN-PT', NOW()),
(UUID(), 'Memória de Tradução', 'Base de dados que armazena pares de frases ou segmentos em dois idiomas, permitindo reutilização em traduções futuras.', 'Tecnologia', 'PT-EN', NOW()),
(UUID(), 'Localização', 'Processo de adaptação de um produto ou conteúdo para uma localidade ou mercado específico, incluindo aspectos culturais e linguísticos.', 'Tradução', 'EN-PT', NOW()),
(UUID(), 'Transcriação', 'Processo criativo de tradução que visa manter a intenção, o estilo e o tom do texto original, especialmente em marketing.', 'Marketing', 'EN-PT', NOW()),
(UUID(), 'Booth', 'Cabine insonorizada utilizada por intérpretes durante eventos de interpretação simultânea.', 'Interpretação', 'EN-PT', NOW()),
(UUID(), 'Sight Translation', 'Tradução oral de um texto escrito, realizada à primeira vista pelo intérprete.', 'Interpretação', 'EN-PT', NOW()),
(UUID(), 'TM Leverage', 'Percentual de aproveitamento da memória de tradução em um projeto, indicando economia de tempo e custo.', 'Tecnologia', 'EN-PT', NOW()),
(UUID(), 'Fuzzy Match', 'Correspondência parcial encontrada na memória de tradução, que requer edição antes de ser utilizada.', 'Tecnologia', 'EN-PT', NOW());
