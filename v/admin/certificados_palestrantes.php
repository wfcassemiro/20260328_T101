<?php
session_start();

// Incluir conexão PDO
require_once __DIR__ . '/../config/database.php';

// Verificação de autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php'); 
    exit;
}

// Verificação defensiva da conexão PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Falha ao obter conexão PDO a partir de config/database.php. Verifique o arquivo.');
}

// Variáveis para mensagens
$success_message = '';
$error_message = '';
$certificate_id = '';
$download_link = '';
$verify_link = '';

// =================================================================================
// 1. LÓGICA PARA EXCLUIR CERTIFICADO
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_certificate'])) {
    $cert_id_to_delete = filter_input(INPUT_POST, 'delete_id', FILTER_SANITIZE_STRING);
    
    if ($cert_id_to_delete) {
        try {
            // Buscar caminho do arquivo antes de deletar
            $stmt = $pdo->prepare("SELECT file_path FROM certificates WHERE id = :id");
            $stmt->execute([':id' => $cert_id_to_delete]);
            $cert_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cert_data) {
                // Tentar remover o arquivo físico
                // O file_path no banco é algo como '/certificates/nome.pdf'
                // O script está em /v/admin, então a pasta é ../certificates
                
                // Remove a barra inicial se houver para concatenar corretamente
                $clean_path = ltrim($cert_data['file_path'], '/'); 
                $full_file_path = __DIR__ . '/../' . $clean_path;
                
                // Se o caminho no banco já for absoluto ou diferente, ajustamos:
                // Vamos tentar garantir que estamos apontando para v/certificates/
                if (strpos($cert_data['file_path'], '/certificates/') !== false) {
                     $full_file_path = __DIR__ . '/../certificates/' . basename($cert_data['file_path']);
                }

                if (file_exists($full_file_path)) {
                    unlink($full_file_path);
                }

                // Remover do banco de dados
                $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = :id");
                $stmt->execute([':id' => $cert_id_to_delete]);
                
                $success_message = "Certificado excluído com sucesso.";
            }
        } catch (Exception $e) {
            $error_message = "Erro ao excluir: " . $e->getMessage();
        }
    }
}

// Função para arredondar a carga horária
function roundToNearestHalfHour($minutes) {
    if ($minutes <= 0) return 0.0;
    $hours = $minutes / 60.0;
    return round($hours * 2) / 2; 
}

// Função auxiliar para limpar nomes de arquivos (NOVO)
function sanitizeFileName($string) {
    // Transliterar para ASCII (remove acentos)
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    // Remove caracteres que não sejam letras, números, espaços, hífens ou underlines
    $string = preg_replace('/[^A-Za-z0-9 _-]/', '', $string);
    // Substitui espaços por underlines
    $string = str_replace(' ', '_', $string);
    // Remove underlines duplicados
    $string = preg_replace('/_+/', '_', $string);
    return $string;
}

// =================================================================================
// 2. LÓGICA PARA GERAR CERTIFICADO
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_certificate'])) {
    
    $speaker_name_selected = filter_input(INPUT_POST, 'speaker_name', FILTER_SANITIZE_STRING);
    $palestra_id = filter_input(INPUT_POST, 'palestra_id', FILTER_SANITIZE_STRING);
    $lecture_date_manual = filter_input(INPUT_POST, 'lecture_date', FILTER_SANITIZE_STRING); 
    $admin_user_id = $_SESSION['user_id']; 
    
    if (empty($speaker_name_selected) || empty($palestra_id) || empty($admin_user_id) || empty($lecture_date_manual)) {
        $error_message = 'Por favor, preencha todos os campos, incluindo a data de realização.';
    } else {
        $certificate_uuid = ''; 
        try {
            // 1. Buscar dados da palestra
            $stmt = $pdo->prepare("SELECT title, duration_minutes FROM lectures WHERE id = :id AND speaker = :speaker_name");
            $stmt->execute([':id' => $palestra_id, ':speaker_name' => $speaker_name_selected]);
            $palestra = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$palestra) {
                throw new Exception('Palestra não encontrada ou não pertence ao palestrante selecionado.');
            }

            $palestrante_nome = $speaker_name_selected;

            // 2. Verificar se já existe certificado
            $stmt = $pdo->prepare("
                SELECT id FROM certificates 
                WHERE lecture_id = :lecture_id 
                AND speaker_name = :speaker_name_param
                AND user_name = :user_name_param 
            ");
            $stmt->execute([
                ':lecture_id' => $palestra_id,
                ':speaker_name_param' => $palestrante_nome,
                ':user_name_param' => $palestrante_nome
            ]);
            
            if ($stmt->fetch()) {
                throw new Exception('Já existe um certificado emitido para este palestrante nesta palestra.');
            }
            
            // 3. Gerar UUID
            $certificate_uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // 4. Formatar dados
            $lecture_date_for_pdf = date('d/m/Y', strtotime($lecture_date_manual));
            $hours_from_db = roundToNearestHalfHour($palestra['duration_minutes']);
            
            if ($hours_from_db <= 0) {
                throw new Exception('A palestra selecionada não possui uma duração válida ('.$palestra['duration_minutes'].' min).');
            }
            
            // Lógica de corte de título
            $raw_title = $palestra['title'];
            $clean_title = $raw_title; 
            $dash_count = mb_substr_count($raw_title, '—', 'UTF-8');
            
            if ($dash_count > 1) {
                $last_dash_pos = mb_strrpos($raw_title, '—', 0, 'UTF-8');
                if ($last_dash_pos !== false) { 
                    $clean_title = mb_substr($raw_title, 0, $last_dash_pos, 'UTF-8');
                    $clean_title = trim($clean_title);
                }
            }

            // 5. Definir caminhos
            $pdf_dir = __DIR__ . '/../certificates/'; 
            if (!is_dir($pdf_dir)) {
                if (!mkdir($pdf_dir, 0755, true) && !is_dir($pdf_dir)) {
                    throw new Exception('Falha ao criar o diretório de certificados: ' . $pdf_dir);
                }
            }
            
            // --- ALTERAÇÃO DO NOME DO ARQUIVO ---
            // Sanitizar os nomes para evitar caracteres inválidos no sistema de arquivos
            $safe_speaker_name = sanitizeFileName($palestrante_nome);
            $safe_lecture_title = sanitizeFileName($clean_title);
            // Limitar o tamanho do título no nome do arquivo para não ficar gigante
            $safe_lecture_title = substr($safe_lecture_title, 0, 50);

            // Formato: Certificado_Nome_do_palestrante_Nome_da_palestra.pdf
            $pdf_filename = 'Certificado_' . $safe_speaker_name . '_' . $safe_lecture_title . '.pdf';
            
            // Se quiser garantir que nunca sobrescreva (embora o banco proteja), poderia adicionar o UUID no final,
            // mas como solicitado o padrão específico, mantemos assim:
            $pdf_full_path = $pdf_dir . $pdf_filename; 
            $pdf_relative_path = '/certificates/' . $pdf_filename; 
            
            // 6. Inserir registro no banco
            $stmt = $pdo->prepare("
                INSERT INTO certificates (
                    id, user_id, lecture_id, user_name, lecture_title, speaker_name, duration_hours, 
                    issued_at, file_path, file_type, generated_at, certificate_code, pdf_path, lecture_date 
                ) VALUES (
                    :id, :user_id, :lecture_id, :user_name, :lecture_title, :speaker_name, :duration_hours, 
                    NOW(), :file_path, 'pdf', NOW(), :certificate_code, :pdf_path, :lecture_date 
                )
            ");
            
            $stmt->execute([
                ':id' => $certificate_uuid,
                ':user_id' => $admin_user_id, 
                ':lecture_id' => $palestra_id,
                ':user_name' => $palestrante_nome, 
                ':lecture_title' => $palestra['title'], 
                ':speaker_name' => $palestrante_nome,
                ':duration_hours' => $hours_from_db,
                ':file_path' => $pdf_relative_path, 
                ':certificate_code' => $certificate_uuid,
                ':pdf_path' => $pdf_full_path,
                ':lecture_date' => $lecture_date_manual 
            ]);
            
            // 7. Gerar PDF do certificado
            
            // --- Carregamento Manual do TCPDF ---
            if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
                require_once __DIR__ . '/../../vendor/autoload.php';
            } elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }

            if (!class_exists('TCPDF')) {
                $paths_to_try = [
                    __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php',
                    __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
                    $_SERVER['DOCUMENT_ROOT'] . '/vendor/tecnickcom/tcpdf/tcpdf.php'
                ];
                foreach ($paths_to_try as $path) {
                    if (file_exists($path)) {
                        require_once $path;
                        break;
                    }
                }
            }
            
            if (!class_exists('TCPDF')) {
                 throw new Exception('Classe TCPDF não encontrada. Verifique a instalação.');
            }
            // -------------------------------------

            $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            
            $pdf->SetCreator('Translators101');
            $pdf->SetAuthor('Translators101');
            $pdf->SetTitle('Certificado de Palestrante');
            $pdf->SetSubject('Certificado');
            
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false, 0); 
            
            $pdf->AddPage();
            
            $template_path = $_SERVER['DOCUMENT_ROOT'] . '/images/template_palestrante.png';
            
            if (!file_exists($template_path)) {
                throw new Exception('Arquivo de template não encontrado. Caminho tentado: ' . $template_path);
            }
            
            $pdf->Image($template_path, 0, 0, 297, 210, 'PNG', '', '', false, 300, '', false, false, 0);
            
            // Nome do palestrante
            $pdf->SetFont('helvetica', 'B', 32); 
            $pdf->SetTextColor(105, 0, 134); 
            $pdf->SetXY(0, 90); 
            $pdf->Cell(297, 15, mb_strtoupper($palestrante_nome, 'UTF-8'), 0, 1, 'C', false); 
            
            // Frase da palestra
            $pdf->SetFont('helvetica', '', 16); 
            $pdf->SetTextColor(105, 0, 134); 
            $pdf->SetXY(0, 108); 
            
            $parte_inteira_horas = (int)$hours_from_db;
            $unidade_hora = ($parte_inteira_horas == 1) ? 'hora' : 'horas';

            $line1 = sprintf('Ministrou a palestra "%s",', $clean_title);
            $line2 = sprintf('realizada em %s, com carga horária de %.1f %s.', $lecture_date_for_pdf, $hours_from_db, $unidade_hora);

            $pdf->SetX(10); 
            $pdf->MultiCell(277, 8, $line1, 0, 'C', false, 1);
            $pdf->SetX(10); 
            $pdf->MultiCell(277, 8, $line2, 0, 'C', false, 1);            
            
            // URL de verificação
            $verify_url = 'https://v.translators101.com/verificar_certificado_palestrante.php?id=' . $certificate_uuid;
            
            $qr_style = [
                'border' => 0,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            ];
            
            $pdf->write2DBarcode($verify_url, 'QRCODE,H', 250, 180, 30, 30, $qr_style, 'N');
            
            // ID de verificação
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(255, 0, 0); 
            $pdf->SetXY(20, 202); 
            $pdf->Cell(50, 5, 'ID: ' . $certificate_uuid, 0, 0, 'L'); 
            
            $pdf->Output($pdf_full_path, 'F');
            
            $success_message = 'Certificado gerado com sucesso!';
            $certificate_id = $certificate_uuid;
            $download_link = $pdf_relative_path; 
            $verify_link = $verify_url;
            
        } catch (Exception $e) {
            $error_message = 'Erro ao gerar certificado: ' . $e->getMessage();
            if (!empty($certificate_uuid)) {
                try {
                    $pdo->prepare("DELETE FROM certificates WHERE id = :id")->execute([':id' => $certificate_uuid]);
                } catch (Exception $db_e) {
                    $error_message .= ' | Erro adicional ao tentar reverter: ' . $db_e->getMessage();
                }
            }
        }
    }
}

// Carregar palestrantes
try {
    $stmt = $pdo->query("SELECT DISTINCT speaker FROM lectures WHERE speaker IS NOT NULL AND speaker != '' ORDER BY speaker");
    $palestrantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $palestrantes = [];
    $error_message = 'Erro ao carregar palestrantes: ' . $e->getMessage();
}

// =================================================================================
// 3. CARREGAR LISTA DE CERTIFICADOS JÁ GERADOS
// =================================================================================
try {
    // Listar os últimos 50 certificados
    $stmt = $pdo->query("
        SELECT id, user_name, lecture_title, issued_at, file_path 
        FROM certificates 
        ORDER BY issued_at DESC 
        LIMIT 50
    ");
    $lista_certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $lista_certificados = [];
}


// --- Inclusão dos cabeçalhos do Admin ---
$page_title = 'Emitir Certificados de Palestrantes';
$page_description = 'Geração e gerenciamento de certificados para palestrantes.';
include __DIR__ . '/../vision/includes/head.php';
?>
<style>
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #fff; font-size: 14px; }
    .form-group select, .form-group input { width: 100%; padding: 12px 15px; border: 2px solid rgba(255, 255, 255, 0.2); border-radius: 10px; font-size: 14px; font-family: inherit; transition: all 0.3s ease; background-color: rgba(255, 255, 255, 0.05); color: #fff; }
    .form-group input[type="date"] { color-scheme: dark; padding-top: 10px; padding-bottom: 10px; }
    .form-group select:focus, .form-group input:focus { outline: none; border-color: #667eea; background-color: rgba(255, 255, 255, 0.1); }
    .form-group select option { background-color: #333; color: #fff; }
    .form-group select:disabled { background: rgba(255, 255, 255, 0.05); opacity: 0.5; cursor: not-allowed; }
    
    .btn { display: inline-block; padding: 14px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; text-align: center; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); }
    .btn:disabled { background: #555; opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-secondary { background: #6c757d; }
    .btn-secondary:hover { background: #5a6268; box_shadow: 0 10px 25px rgba(90, 98, 104, 0.3); }
    .btn-danger { background: #dc3545; padding: 8px 15px; font-size: 13px; }
    .btn-danger:hover { background: #c82333; box_shadow: 0 5px 15px rgba(220, 53, 69, 0.3); }
    .btn-small { padding: 8px 15px; font-size: 13px; }
    
    .btn-group { display: flex; gap: 10px; margin-top: 30px; }
    .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-size: 14px; line-height: 1.5; border: 1px solid transparent; }
    .alert-success { background: linear-gradient(135deg,rgba(52,199,89,.2),rgba(52,199,89,.1)); color: #34C759; border-color: rgba(52,199,89,.3); }
    .alert-error { background: linear-gradient(135deg,rgba(255,59,48,.2),rgba(255,59,48,.1)); color: #FF3B30; border-color: rgba(255,59,48,.3); }
    .alert strong { display: block; margin-bottom: 8px; font-weight: 600; }
    .alert a { color: inherit; text-decoration: underline; font-weight: 500; }
    .certificate-info { background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 10px; margin-top: 20px; border: 1px solid rgba(255, 255, 255, 0.1); }
    
    /* Estilos para a Tabela de Certificados */
    .table-container {
        margin-top: 40px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .cert-table {
        width: 100%;
        border-collapse: collapse;
        color: #fff;
        font-size: 14px;
    }
    .cert-table th, .cert-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    .cert-table th {
        background: rgba(0, 0, 0, 0.2);
        font-weight: 600;
        color: #bbb;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 1px;
    }
    .cert-table tr:last-child td { border-bottom: none; }
    .cert-table tr:hover { background: rgba(255, 255, 255, 0.05); }
    .action-links { display: flex; gap: 10px; }
</style>

<?php
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-chalkboard-teacher"></i> Emitir Certificados para Palestrantes</h1>
            <p>Selecione o palestrante e a palestra para gerar o certificado</p>
        </div>
    </div>

    <div class="video-card">
        <h2><i class="fas fa-file-signature"></i> Gerador de Certificado</h2>
        
        <div class="content" style="padding: 20px;">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <strong>✓ <?php echo htmlspecialchars($success_message); ?></strong>
                    <?php if ($download_link): ?>
                    <div class="certificate-info">
                        <p><strong>ID do Certificado:</strong> <?php echo htmlspecialchars($certificate_id); ?></p>
                        <p><strong>Download:</strong> <a href="<?php echo htmlspecialchars($download_link); ?>" target="_blank">Baixar PDF</a></p>
                        <p><strong>Verificação:</strong> <a href="<?php echo htmlspecialchars($verify_link); ?>" target="_blank">Verificar Certificado</a></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <strong>✗ Erro</strong>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="certificateForm">
                <div class="form-group">
                    <label for="speaker_name">Palestrante *</label>
                    <select name="speaker_name" id="speaker_name" required>
                        <option value="">Selecione um palestrante</option>
                        <?php foreach ($palestrantes as $palestrante): ?>
                            <option value="<?php echo htmlspecialchars($palestrante['speaker']); ?>">
                                <?php echo htmlspecialchars($palestrante['speaker']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="palestra_id">Palestra *</label>
                    <select name="palestra_id" id="palestra_id" required disabled>
                        <option value="">Selecione um palestrante primeiro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="lecture_date">Data de Realização *</label>
                    <input type="date" name="lecture_date" id="lecture_date" required>
                </div>

                <div class="btn-group">
                    <button type="submit" name="generate_certificate" id="generate_button" class="btn" disabled>
                        Gerar Certificado
                    </button>
                    <a href="index.php" class="btn btn-secondary">Voltar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="video-card" style="margin-top: 30px;">
        <h2><i class="fas fa-list"></i> Certificados Gerados (Últimos 50)</h2>
        <div class="content" style="padding: 0;">
            <div class="table-container">
                <table class="cert-table">
                    <thead>
                        <tr>
                            <th>Data Emissão</th>
                            <th>Nome (Palestrante/Usuário)</th>
                            <th>Palestra</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lista_certificados) > 0): ?>
                            <?php foreach ($lista_certificados as $cert): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($cert['issued_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($cert['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars(mb_strimwidth($cert['lecture_title'], 0, 40, "...")); ?></td>
                                    <td>
                                        <div class="action-links">
                                            <a href="<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" class="btn btn-small">
                                                <i class="fas fa-download"></i> Baixar
                                            </a>
                                            
                                            <form method="POST" action="" onsubmit="return confirm('Tem certeza que deseja apagar este certificado? O arquivo será removido permanentemente.');" style="display:inline;">
                                                <input type="hidden" name="delete_id" value="<?php echo $cert['id']; ?>">
                                                <button type="submit" name="delete_certificate" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i> Apagar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 30px; color: #aaa;">Nenhum certificado encontrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const speakerSelect = document.getElementById('speaker_name');
    const palestraSelect = document.getElementById('palestra_id');
    const generateButton = document.getElementById('generate_button');
    const dateInput = document.getElementById('lecture_date'); 

    function checkFormReady() {
        const speakerReady = speakerSelect.value !== '';
        const palestraReady = palestraSelect.value !== '' && !palestraSelect.disabled;
        const dateReady = dateInput.value !== ''; 
        
        generateButton.disabled = !(speakerReady && palestraReady && dateReady);
    }

    speakerSelect.addEventListener('change', function() {
        const speakerName = this.value;
        palestraSelect.innerHTML = '<option value="">Carregando...</option>';
        palestraSelect.disabled = true;
        checkFormReady(); 

        if (speakerName) {
            const formData = new FormData();
            formData.append('speaker_name', speakerName);

            fetch('ajax_get_palestras.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Erro de rede: ' + response.statusText);
                return response.json();
            })
            .then(palestras => {
                palestraSelect.innerHTML = '<option value="">Selecione uma palestra</option>';
                if (palestras.length > 0) {
                    palestras.forEach(palestra => {
                        const date = new Date(palestra.created_at);
                        const formattedDate = date.toLocaleDateString('pt-BR', {
                            day: '2-digit', month: '2-digit', year: 'numeric'
                        });
                        const option = document.createElement('option');
                        option.value = palestra.id;
                        option.textContent = `${palestra.title} (${formattedDate})`;
                        palestraSelect.appendChild(option);
                    });
                    palestraSelect.disabled = false;
                } else {
                    palestraSelect.innerHTML = '<option value="">Nenhuma palestra encontrada</option>';
                }
                checkFormReady();
            })
            .catch(error => {
                console.error('Erro ao buscar palestras:', error);
                palestraSelect.innerHTML = '<option value="">Erro ao carregar palestras</option>';
                checkFormReady();
            });
        } else {
            palestraSelect.innerHTML = '<option value="">Selecione um palestrante primeiro</option>';
            checkFormReady();
        }
    });

    palestraSelect.addEventListener('change', checkFormReady);
    dateInput.addEventListener('change', checkFormReady);
});
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>