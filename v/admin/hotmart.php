<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$page_title = 'Integração Hotmart - Admin';
$message = '';
$error = '';
$sync_details = [];

// --- CREDENCIAIS ---
define('HOTMART_BASIC_AUTH', 'Basic N2UzZDM0MmQtYWY0Zi00MTkwLTk1OWMtNmE5NzU0NmYxNDM3OjZmNjI1NzZmLTQzMzUtNDBkMC04N2FhLThhNThmMDlkZjdmZA==');
define('HOTMART_ACCESS_TOKEN_URL', 'https://api-sec-vlc.hotmart.com/security/oauth/token');
define('HOTMART_SALES_HISTORY_URL', 'https://developers.hotmart.com/payments/api/v1/sales/history');

function gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

if (isset($_POST['sync_hotmart'])) {
    try {
        $days = isset($_POST['days_to_sync']) ? intval($_POST['days_to_sync']) : 30;
        $sync_result = syncWithHotmart($days);
        if ($sync_result['success']) {
            $message = 'Sincronização realizada! ' . $sync_result['message'];
            $sync_details = $sync_result['details'] ?? [];
        } else {
            $error = 'Erro: ' . $sync_result['message'];
        }
    } catch (Exception $e) {
        $error = 'Erro crítico: ' . $e->getMessage();
    }
}

function syncWithHotmart($days_to_sync) {
    global $pdo;
    $processed_users = [];

    // 1. Autenticação
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, HOTMART_ACCESS_TOKEN_URL . '?grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: ' . HOTMART_BASIC_AUTH]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) return ['success' => false, 'message' => "Falha na autenticação (HTTP $http_code)."];
    $auth_data = json_decode($response, true);
    if (!isset($auth_data['access_token'])) return ['success' => false, 'message' => 'Token não recebido.'];
    $access_token = $auth_data['access_token'];

    // 2. Buscar Vendas
    $start_date = strtotime("-{$days_to_sync} days") * 1000;
    $end_date = time() * 1000;
    $url = HOTMART_SALES_HISTORY_URL . "?start_date=$start_date&end_date=$end_date&max_results=100";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $sales_res = curl_exec($ch);
    $sales_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($sales_code !== 200) return ['success' => false, 'message' => "Erro ao buscar vendas (HTTP $sales_code)."];
    $sales_data = json_decode($sales_res, true);
    if (!isset($sales_data['items'])) return ['success' => true, 'message' => "Nenhuma venda encontrada nos últimos $days_to_sync dias.", 'details' => []];

    // 3. Processar
    $count_new = 0;
    $count_updated = 0;

    foreach ($sales_data['items'] as $sale) {
        if (!isset($sale['buyer']['email'])) continue;

        $email = $sale['buyer']['email'];
        $name = $sale['buyer']['name'] ?? 'Desconhecido';
        
        // Dados do Produto para identificar Plano e Tipo
        $product_name = $sale['product']['name'] ?? '';
        
        // --- LÓGICA DE IDENTIFICAÇÃO DE PRODUTO ---
        $periodo = 'Outro';
        if (stripos($product_name, 'anual') !== false) $periodo = 'Anual';
        elseif (stripos($product_name, 'semestral') !== false) $periodo = 'Semestral';
        elseif (stripos($product_name, 'trimestral') !== false) $periodo = 'Trimestral';
        elseif (stripos($product_name, 'mensal') !== false) $periodo = 'Mensal';
        elseif (stripos($product_name, 'vip') !== false) $periodo = 'VIP';

        $tipo = 'Padrão';
        if (stripos($product_name, 'premium') !== false) $tipo = 'Premium';
        elseif (stripos($product_name, 'básico') !== false || stripos($product_name, 'basic') !== false) $tipo = 'Básico';

        // Dados da Compra
        $purchase_info = $sale['purchase'] ?? [];
        $raw_status = $purchase_info['status'] ?? $sale['status'] ?? 'UNKNOWN';
        $purchase_date_ms = $purchase_info['approved_date'] ?? $purchase_info['order_date'] ?? $sale['purchase_date'] ?? (time() * 1000);
        $purchase_time = $purchase_date_ms / 1000; 

        // LÓGICA DE STATUS
        $is_vigente = in_array($raw_status, ['APPROVED', 'COMPLETE']);
        $status_hotmart_label = '';
        $status_hotmart_color = '';
        $should_block = false;

        if ($is_vigente) {
            $status_hotmart_label = 'Vigente';
            $status_hotmart_color = '#2ecc71';
            $should_block = false;
        } else {
            $status_hotmart_label = 'Atrasado';
            $days_diff = floor((time() - $purchase_time) / (60 * 60 * 24));
            
            if ($days_diff <= 5) {
                $status_hotmart_color = '#f1c40f'; // Amarelo
                $should_block = false;
                $status_hotmart_label .= " ({$days_diff}d)";
            } else {
                $status_hotmart_color = '#e74c3c'; // Vermelho
                $should_block = true;
                $status_hotmart_label .= " (> 5d)";
            }
        }

        $system_action_log = '';
        $user_id_db = null;
        $is_registered = false;

        try {
            $stmt = $pdo->prepare("SELECT id, password_hash, is_active FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $user_id_db = $existing['id'];
                $is_registered = !empty($existing['password_hash']);
                
                if ($should_block) {
                    if ($existing['is_active'] == 1) {
                        $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$user_id_db]);
                        $count_updated++;
                    }
                    $system_action_log = '<span style="color:#e74c3c; font-weight:bold;">Inativo</span>';
                } else {
                    if ($existing['is_active'] == 0) {
                        $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$user_id_db]);
                        $count_updated++;
                    }
                    $system_action_log = $is_registered ? 
                        '<span style="color:#2ecc71; font-weight:bold;">Ativo</span>' : 
                        '<span style="color:#f1c40f; font-weight:bold;">Pendente</span>';
                }

            } else {
                // Novo Usuário
                if (!$should_block) {
                    $new_uuid = gen_uuid();
                    $stmtInsert = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, created_at, is_active) VALUES (?, ?, ?, NULL, 'subscriber', NOW(), 1)");
                    $stmtInsert->execute([$new_uuid, $name, $email]);
                    
                    $user_id_db = $new_uuid;
                    $count_new++;
                    $system_action_log = '<span style="color:#f1c40f; font-weight:bold;">Novo (Pendente)</span>';
                    $is_registered = false;
                } else {
                    $system_action_log = '<span style="color:#777;">Ignorado</span>';
                }
            }

        } catch (Exception $e) {
            $system_action_log = 'Erro DB';
        }

        if ($user_id_db || $should_block) {
            $processed_users[] = [
                'name' => $name,
                'email' => $email,
                'periodo' => $periodo,
                'tipo' => $tipo,
                'status_hotmart_label' => $status_hotmart_label,
                'status_hotmart_color' => $status_hotmart_color,
                'system_action' => $system_action_log,
                'id' => $user_id_db,
                'is_registered' => $is_registered
            ];
        }
    }

    return [
        'success' => true, 
        'message' => "Novos: $count_new. Alterações: $count_updated.",
        'details' => $processed_users
    ];
}

include __DIR__ . '/../vision/includes/head.php';
include __DIR__ . '/../vision/includes/header.php';
include __DIR__ . '/../vision/includes/sidebar.php';
?>

<div class="main-content">
    <div class="glass-hero">
        <div class="hero-content">
            <h1><i class="fas fa-sync-alt"></i> Integração Hotmart</h1>
            <p>Sincronize usuários e controle acessos via API</p>
        </div>
    </div>

    <div class="container-fluid" style="padding: 20px;">
        
        <?php if ($message): ?>
            <div class="alert success" style="background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error" style="background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #e74c3c; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="video-card" style="text-align: center; padding: 40px;">
            <i class="fas fa-cloud-download-alt" style="font-size: 3rem; color: #8e44ad; margin-bottom: 20px;"></i>
            <h3>Sincronização Manual</h3>
            <p style="color: #aaa; margin-bottom: 30px;">
                Atualiza base e bloqueia acessos atrasados há mais de 5 dias.
            </p>
            
            <form method="post" style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label style="color: #fff;">Período:</label>
                    <select name="days_to_sync" style="padding: 8px; border-radius: 5px; background: #333; color: #fff; border: 1px solid #555;">
                        <option value="7">Últimos 7 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                        <option value="180">Últimos 180 dias</option>
                        <option value="365">Últimos 365 dias</option>
                    </select>
                </div>

                <button type="submit" name="sync_hotmart" class="cta-btn" style="font-size: 1.1rem; padding: 12px 30px;">
                    <i class="fas fa-sync"></i> Sincronizar Agora
                </button>
            </form>
        </div>

        <?php if (!empty($sync_details)): ?>
        <div class="video-card" style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3><i class="fas fa-list"></i> Resultado da Sincronização</h3>
                <button onclick="downloadCSV('relatorio_hotmart.csv')" class="cta-btn" style="padding: 8px 15px; font-size: 0.9rem; background: #2ecc71;">
                    <i class="fas fa-file-csv"></i> Exportar CSV
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="syncTable" style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <thead>
                        <tr style="background: rgba(255,255,255,0.05); color: #fff;">
                            <th style="padding: 12px; text-align: center; width: 50px;">#</th>
                            <th style="padding: 12px; text-align: left;">Nome</th>
                            <th style="padding: 12px; text-align: left;">Email</th>
                            <th style="padding: 12px; text-align: center;">Período</th>
                            <th style="padding: 12px; text-align: center;">Tipo</th>
                            <th style="padding: 12px; text-align: center;">Status Hotmart</th>
                            <th style="padding: 12px; text-align: center;">Ação no Sistema</th>
                            <th style="padding: 12px; text-align: center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($sync_details as $user): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <td style="padding: 12px; text-align: center; color: #777;"><?php echo $counter++; ?></td>
                            <td style="padding: 12px; color: #fff;"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td style="padding: 12px; color: #ccc;"><?php echo htmlspecialchars($user['email']); ?></td>
                            
                            <td style="padding: 12px; text-align: center; color: #bbb;"><?php echo $user['periodo']; ?></td>
                            <td style="padding: 12px; text-align: center; color: #bbb;"><?php echo $user['tipo']; ?></td>
                            
                            <td style="padding: 12px; text-align: center;">
                                <span style="color:<?php echo $user['status_hotmart_color']; ?>; font-weight:bold;">
                                    <?php echo $user['status_hotmart_label']; ?>
                                </span>
                            </td>

                            <td id="status-sys-<?php echo $user['id']; ?>" style="padding: 12px; text-align: center;">
                                <?php echo $user['system_action']; ?>
                            </td>
                            
                            <td style="padding: 12px; text-align: center;">
                                <?php if ($user['id']): ?>
                                    <div style="display: flex; gap: 15px; justify-content: center; align-items: center;">
                                        <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="action-btn edit" title="Editar" style="color: #3498db; font-size: 1.1rem; text-decoration: none;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if (!$user['is_registered']): ?>
                                            <button onclick="openSendModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['name']); ?>')" 
                                                    class="action-btn key" title="Enviar Link de Senha" style="color: #f39c12; background:none; border:none; font-size: 1.1rem; cursor:pointer;">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        <?php else: ?>
                                            <span style="visibility: hidden; width: 18px;"><i class="fas fa-key"></i></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#666;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="emailModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3 style="color: #f39c12;"><i class="fas fa-envelope"></i> Enviar Link de Senha</h3>
        <p style="color: #ddd; margin: 15px 0;">
            Deseja enviar um e-mail para <strong id="modalUserName" style="color: #fff;"></strong>?
        </p>
        <p style="font-size: 0.9rem; color: #aaa;">Email: <span id="modalUserEmail"></span></p>
        <div style="margin-top: 25px; text-align: right;">
            <button onclick="closeModal()" class="cta-btn" style="background: #555; margin-right: 10px;">Cancelar</button>
            <button onclick="confirmSendEmail()" class="cta-btn" id="btnConfirmSend">Enviar E-mail</button>
        </div>
    </div>
</div>

<style>
    .cta-btn {
        background: #8e44ad; color: #fff; border: none; padding: 10px 20px; border-radius: 30px; cursor: pointer; font-weight: bold; text-decoration: none; transition: 0.3s;
    }
    .cta-btn:hover { background: #9b59b6; transform: translateY(-2px); }
    .action-btn:hover { transform: scale(1.2); }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
    .modal-content { background: #1e1e1e; margin: 15% auto; padding: 30px; border: 1px solid #444; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
</style>

<script>
    // CSV EXPORT FUNCTION
    function downloadCSV(filename) {
        let csv = [];
        const rows = document.querySelectorAll("#syncTable tr");
        
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll("td, th");
            
            // Pega apenas até a penúltima coluna (ignora Ações)
            for (let j = 0; j < cols.length - 1; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/;/g, ",");
                row.push('"' + data + '"');
            }
            csv.push(row.join(";"));
        }

        // Adiciona BOM para UTF-8 no Excel
        const csvContent = "\uFEFF" + csv.join("\n");
        const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        
        link.setAttribute("href", url);
        link.setAttribute("download", filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    let currentUserId = null;
    let currentUserEmail = null;

    function openSendModal(id, email, name) {
        currentUserId = id;
        currentUserEmail = email;
        document.getElementById('modalUserName').innerText = name;
        document.getElementById('modalUserEmail').innerText = email;
        document.getElementById('emailModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('emailModal').style.display = 'none';
    }

    function confirmSendEmail() {
        const btn = document.getElementById('btnConfirmSend');
        const originalText = btn.innerText;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

        const formData = new FormData();
        formData.append('action', 'batch_process');
        formData.append('user_ids[]', currentUserId);
        formData.append('generate_only', 0);

        fetch('gerenciar_senhas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success_count > 0) {
                closeModal();
                const statusCell = document.getElementById('status-sys-' + currentUserId);
                if (statusCell) statusCell.innerHTML = '<span style="color:#3498db; font-weight:bold;">Link enviado</span>';
                alert('✅ E-mail enviado com sucesso!');
            } else {
                alert('❌ Erro ao enviar: ' + (data.details[0]?.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('❌ Erro de conexão.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = originalText;
        });
    }

    window.onclick = function(event) {
        if (event.target == document.getElementById('emailModal')) closeModal();
    }
</script>

<?php include __DIR__ . '/../vision/includes/footer.php'; ?>