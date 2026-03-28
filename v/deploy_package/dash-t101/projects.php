<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Projetos - Dash-T101';
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_project':
                try {
                    $word_count = intval($_POST['word_count'] ?? 0);
                    // Removido character_count
                    $rate_per_word = floatval($_POST['rate_per_word'] ?? 0);
                    // Removido rate_per_character
                    $daily_word_target = intval($_POST['daily_word_target'] ?? 0);

                    $negotiated_amount = floatval(str_replace(',', '.', $_POST['negotiated_amount'] ?? '0'));
                    // A função calculateProjectTotal deve ser ajustada para não usar character_count se for removido globalmente
                    $calculated_amount = calculateProjectTotal($word_count, 0, $rate_per_word, 0); // Passando 0 para character_count e rate_per_character
                    $total_amount = ($negotiated_amount > 0) ? $negotiated_amount : $calculated_amount;

                    // Removido character_count e rate_per_character do INSERT
                    $stmt = $pdo->prepare("INSERT INTO dash_projects (user_id, client_id, title, po_number, description, source_language, target_language, service_type, word_count, rate_per_word, total_amount, currency, status, priority, start_date, deadline, notes, daily_word_target) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $result = $stmt->execute([
                        $user_id,
                        $_POST['client_id'],
                        $_POST['project_name'],
                        $_POST['po_number'] ?? null,
                        $_POST['project_description'] ?? '',
                        $_POST['source_language'],
                        $_POST['target_language'],
                        $_POST['service_type'],
                        $word_count,
                        $rate_per_word,
                        $total_amount,
                        $_POST['currency'],
                        $_POST['status'],
                        $_POST['priority'],
                        $_POST['start_date'] ?: null,
                        $_POST['deadline'] ?: null,
                        $_POST['notes'] ?? '',
                        $daily_word_target
                    ]);

                    if ($result) {
                        $message = 'Projeto adicionado com sucesso!';
                    } else {
                        $error = 'Erro ao adicionar projeto.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;

            case 'edit_project':
                try {
                    $word_count = intval($_POST['word_count'] ?? 0);
                    // Removido character_count
                    $rate_per_word = floatval($_POST['rate_per_word'] ?? 0);
                    // Removido rate_per_character
                    $daily_word_target = intval($_POST['daily_word_target'] ?? 0);

                    $negotiated_amount = floatval(str_replace(',', '.', $_POST['negotiated_amount'] ?? '0'));
                    // A função calculateProjectTotal deve ser ajustada para não usar character_count se for removido globalmente
                    $calculated_amount = calculateProjectTotal($word_count, 0, $rate_per_word, 0); // Passando 0 para character_count e rate_per_character
                    $total_amount = ($negotiated_amount > 0) ? $negotiated_amount : $calculated_amount;

                    // Removido character_count e rate_per_character do UPDATE
                    $stmt = $pdo->prepare("UPDATE dash_projects SET client_id = ?, title = ?, po_number = ?, description = ?, source_language = ?, target_language = ?, service_type = ?, word_count = ?, rate_per_word = ?, total_amount = ?, currency = ?, status = ?, priority = ?, start_date = ?, deadline = ?, notes = ?, daily_word_target = ? WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([
                        $_POST['client_id'],
                        $_POST['project_name'],
                        $_POST['po_number'] ?? null,
                        $_POST['project_description'] ?? '',
                        $_POST['source_language'],
                        $_POST['target_language'],
                        $_POST['service_type'],
                        $word_count,
                        $rate_per_word,
                        $total_amount,
                        $_POST['currency'],
                        $_POST['status'],
                        $_POST['priority'],
                        $_POST['start_date'] ?: null,
                        $_POST['deadline'] ?: null,
                        $_POST['notes'] ?? '',
                        $daily_word_target, // Adicionado o novo campo
                        $_POST['project_id'],
                        $user_id
                    ]);

                    if ($result) {
                        $message = 'Projeto atualizado com sucesso!';
                    } else {
                        $error = 'Erro ao atualizar projeto.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;

            case 'delete_project':
                try {
                    $stmt = $pdo->prepare("DELETE FROM dash_projects WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([$_POST['project_id'], $user_id]);

                    if ($result) {
                        $message = 'Projeto excluído com sucesso!';
                    } else {
                        $error = 'Erro ao excluir projeto.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;

            case 'complete_project':
                try {
                    $project_id = $_POST['project_id'];
                    $stmt = $pdo->prepare("UPDATE dash_projects SET status = 'completed', completed_date = CURDATE() WHERE id = ? AND user_id = ?");
                    $result = $stmt->execute([$project_id, $user_id]);

                    if ($result) {
                        $message = 'Projeto marcado como concluído!';
                    } else {
                        $error = 'Erro ao marcar projeto como concluído.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;

            case 'generate_invoice':
                try {
                    $project_id = $_POST['project_id'];
                    $invoice_number = generateInvoiceFromProject($user_id, $project_id);

                    if ($invoice_number) {
                        $message = 'Fatura gerada com sucesso! Número: ' . $invoice_number;
                    } else {
                        $error = 'Erro ao gerar fatura a partir do projeto. Verifique o log de erros para mais detalhes.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erro: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Obter lista de clientes para o dropdown (incluindo a moeda padrão)
$stmt = $pdo->prepare("SELECT id, company AS company_name, default_currency FROM dash_clients WHERE user_id = ? ORDER BY company ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter lista de projetos
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clause = "WHERE p.user_id = ?";
$params = [$user_id];

if ($search) {
    $where_clause .= " AND (p.title LIKE ? OR c.company LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter) {
    $where_clause .= " AND p.status = ?";
    $params[] = $status_filter;
}

// Selecionando po_number e daily_word_target
$stmt = $pdo->prepare("SELECT p.*, p.title AS project_name, p.description AS project_description, p.po_number, p.daily_word_target, c.company AS company_name, c.name AS contact_name FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id $where_clause ORDER BY p.created_at DESC");
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter projeto para edição se solicitado
$edit_project = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    // Selecionando po_number e daily_word_target para edição
    $stmt = $pdo->prepare("SELECT p.*, p.title AS project_name, p.description AS project_description, p.po_number, p.daily_word_target, c.company AS company_name, c.name AS contact_name FROM dash_projects p LEFT JOIN dash_clients c ON p.client_id = c.id WHERE p.id = ? AND p.user_id = ?");
    $stmt->execute([$_GET['edit'], $user_id]);
    $edit_project = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- Lógica para a Linha do Tempo ---
$display_range_days = 20; // Período fixo de 20 dias
$today_display_offset_days = 5; // "HOJE" no 5º dia da exibição

// Garantir que a data atual seja baseada no fuso horário correto (se aplicável ao ambiente do servidor)
date_default_timezone_set('America/Sao_Paulo'); // Defina para o fuso horário de seu servidor/usuário, se diferente do padrão
$today_ts = strtotime(date('Y-m-d')); // Data atual à meia-noite (00:00:00)

// Calcular a data de início da exibição (5 dias antes de hoje)
$min_display_date = strtotime(date('Y-m-d', strtotime("-$today_display_offset_days days", $today_ts)));
// Calcular a data de fim da exibição (14 dias depois de hoje, totalizando 20 dias de range)
$max_display_date = strtotime(date('Y-m-d', strtotime("+" . ($display_range_days - $today_display_offset_days - 1) . " days", $today_ts)));

// A posição percentual do "HOJE" na linha do tempo geral de 20 dias (fixa)
$today_pos_on_global_line_percent = ($today_display_offset_days / $display_range_days) * 100;

include __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white mb-2">Gerenciar Projetos</h1>
            <p class="text-gray-400">Crie e acompanhe seus projetos de tradução</p>
        </div>
        <a href="index.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
        </a>
    </div>

    <?php if ($message): ?>
        <div class="bg-green-600 text-white p-4 rounded-lg mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-600 text-white p-4 rounded-lg mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">
            <?php echo $edit_project ? 'Editar Projeto' : 'Adicionar Novo Projeto'; ?>
        </h2>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <input type="hidden" name="action" value="<?php echo $edit_project ? 'edit_project' : 'add_project'; ?>">
            <?php if ($edit_project): ?>
                <input type="hidden" name="project_id" value="<?php echo $edit_project['id']; ?>">
            <?php endif; ?>

            <div class="lg:col-span-2">
                <label for="project_name" class="block text-sm font-medium text-gray-300 mb-2">Nome do Projeto *</label>
                <input type="text" name="project_name" id="project_name" required
                       value="<?php echo htmlspecialchars($edit_project['project_name'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-300 mb-2">Cliente *</label>
                <select name="client_id" id="client_id" required
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>"
                                data-currency="<?php echo htmlspecialchars($client['default_currency']); ?>"
                            <?php echo ($edit_project && $edit_project['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="source_language" class="block text-sm font-medium text-gray-300 mb-2">Idioma de Origem *</label>
                <select name="source_language" id="source_language" required
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <option value="">Selecione</option>
                    <?php foreach ($dash_config['languages'] as $langCode => $langName): ?>
                        <option value="<?php echo $langCode; ?>" <?php echo ($edit_project && $edit_project['source_language'] == $langCode) ? 'selected' : ''; ?>>
                            <?php echo $langName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="target_language" class="block text-sm font-medium text-gray-300 mb-2">Idioma de Destino *</label>
                <select name="target_language" id="target_language" required
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <option value="">Selecione</option>
                    <?php foreach ($dash_config['languages'] as $langCode => $langName): ?>
                        <option value="<?php echo $langCode; ?>" <?php echo ($edit_project && $edit_project['target_language'] == $langCode) ? 'selected' : ''; ?>>
                            <?php echo $langName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="service_type" class="block text-sm font-medium text-gray-300 mb-2">Tipo de Serviço *</label>
                <select name="service_type" id="service_type" required
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <?php foreach ($dash_config['service_types'] as $typeCode => $typeName): ?>
                        <option value="<?php echo $typeCode; ?>" <?php echo ($edit_project && $edit_project['service_type'] == $typeCode) ? 'selected' : ''; ?>>
                            <?php echo $typeName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="po_number" class="block text-sm font-medium text-gray-300 mb-2">Número PO (Purchase Order)</label>
                <input type="text" name="po_number" id="po_number"
                       value="<?php echo htmlspecialchars($edit_project['po_number'] ?? ''); ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div>
                <label for="word_count" class="block text-sm font-medium text-gray-300 mb-2">Contagem de Palavras</label>
                <input type="number" name="word_count" id="word_count" min="0"
                       value="<?php echo $edit_project['word_count'] ?? ''; ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                       oninput="calculateTotal()"> </div>

            <div>
                <label for="rate_per_word" class="block text-sm font-medium text-gray-300 mb-2">Taxa por Palavra</label>
                <div class="flex items-center">
                    <select name="currency_word_rate" id="currency_word_rate" class="p-3 bg-gray-700 border border-gray-600 rounded-l-lg focus:border-purple-500 focus:outline-none text-white" style="width: 25%;">
                        <?php foreach ($dash_config['currencies'] as $currencyCode): ?>
                            <option value="<?php echo $currencyCode; ?>" <?php echo ($edit_project && $edit_project['currency'] == $currencyCode) ? 'selected' : ''; ?>>
                                <?php echo $currencyCode; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="rate_per_word" id="rate_per_word" min="0" step="0.0001"
                           value="<?php echo $edit_project['rate_per_word'] ?? ''; ?>"
                           class="flex-1 p-3 bg-gray-700 border border-gray-600 rounded-r-lg focus:border-purple-500 focus:outline-none text-white"
                           oninput="calculateTotal()"> </div>
            </div>

            <input type="hidden" name="character_count" id="character_count" value="0">
            <input type="hidden" name="rate_per_character" id="rate_per_character" value="0">


            <div class="flex items-center gap-2">
                <div>
                    <label for="total_calculated" class="block text-sm font-medium text-gray-300 mb-2">Total Calculado</label>
                    <input type="text" id="total_calculated" readonly
                           class="w-full p-3 bg-gray-600 border border-gray-600 rounded-lg text-white">
                </div>
                <div class="flex-1">
                    <label for="negotiated_amount" class="block text-sm font-medium text-gray-300 mb-2">Valor Negociado</label>
                    <input type="number" name="negotiated_amount" id="negotiated_amount" min="0" step="0.01"
                           value="<?php echo $edit_project['total_amount'] ?? ''; ?>"
                           class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"
                           oninput="updateCurrencyForNegotiated()">
                </div>
                <div>
                    <label for="currency_negotiated" class="block text-sm font-medium text-gray-300 mb-2 invisible">Moeda</label>
                    <select name="currency" id="currency_negotiated" class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                        <?php foreach ($dash_config['currencies'] as $currencyCode): ?>
                            <option value="<?php echo $currencyCode; ?>" <?php echo ($edit_project && $edit_project['currency'] == $currencyCode) ? 'selected' : ''; ?>>
                                <?php echo $currencyCode; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="daily_word_target" class="block text-sm font-medium text-gray-300 mb-2">Meta de Palavras/Dia (opcional)</label>
                <input type="number" name="daily_word_target" id="daily_word_target" min="0"
                       value="<?php echo $edit_project['daily_word_target'] ?? ''; ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                <select name="status" id="status"
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <?php foreach ($dash_config['project_statuses'] as $statusCode => $statusName): ?>
                        <option value="<?php echo $statusCode; ?>" <?php echo ($edit_project && $edit_project['status'] == $statusCode) ? 'selected' : ''; ?>>
                            <?php echo $statusName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="priority" class="block text-sm font-medium text-gray-300 mb-2">Prioridade</label>
                <select name="priority" id="priority"
                        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <?php foreach ($dash_config['priorities'] as $priorityCode => $priorityName): ?>
                        <option value="<?php echo $priorityCode; ?>" <?php echo ($edit_project && $edit_project['priority'] == $priorityCode) ? 'selected' : ''; ?>>
                            <?php echo $priorityName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-300 mb-2">Data de Início</label>
                <input type="date" name="start_date" id="start_date"
                       value="<?php echo $edit_project['start_date'] ?? ''; ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div>
                <label for="deadline" class="block text-sm font-medium text-gray-300 mb-2">Prazo de Entrega</label>
                <input type="date" name="deadline" id="deadline"
                       value="<?php echo $edit_project['deadline'] ?? ''; ?>"
                       class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
            </div>

            <div class="lg:col-span-3">
                <label for="project_description" class="block text-sm font-medium text-gray-300 mb-2">Descrição do Projeto</label>
                <textarea name="project_description" id="project_description" rows="3"
                          class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"><?php echo htmlspecialchars($edit_project['project_description'] ?? ''); ?></textarea>
            </div>

            <div class="lg:col-span-3">
                <label for="notes" class="block text-sm font-medium text-gray-300 mb-2">Observações</label>
                <textarea name="notes" id="notes" rows="3"
                          class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white"><?php echo htmlspecialchars($edit_project['notes'] ?? ''); ?></textarea>
            </div>

            <div class="lg:col-span-3 flex gap-4">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg transition-colors text-white">
                    <?php echo $edit_project ? 'Atualizar Projeto' : 'Adicionar Projeto'; ?>
                </button>
                <?php if ($edit_project): ?>
                    <a href="projects.php" class="bg-gray-600 hover:bg-gray-700 px-6 py-3 rounded-lg transition-colors text-white">
                        Cancelar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Linha do Tempo dos Projetos</h2>
        <div class="space-y-4">
            <div class="flex items-center text-sm mb-4">
                <div class="w-1/4 pr-4 text-gray-300 truncate">
                    <span class="font-semibold text-white">Projetos</span>
                </div>
                <div class="w-3/4 relative h-4 bg-white rounded-full flex items-center">
                    <?php
                    $start_date_label = date('d/m/Y', $min_display_date);
                    $end_date_label = date('d/m/Y', $max_display_date);
                    $today_label = date('d/m/Y', $today_ts);
                    ?>
                    <span class="absolute left-2 text-black text-xs select-none" style="top: 50%; transform: translateY(-50%);">
                        <?php echo $start_date_label; ?>
                    </span>

                    <span class="absolute right-2 text-black text-xs select-none" style="top: 50%; transform: translateY(-50%);">
                        <?php echo $end_date_label; ?>
                    </span>

                    <div class="absolute top-0 bottom-0 bg-black" style="left: <?php echo $today_pos_on_global_line_percent; ?>%; transform: translateX(-50%); width: 2px; z-index: 10;"></div>

                    <span class="absolute text-black text-xs font-semibold select-none" style="left: <?php echo $today_pos_on_global_line_percent; ?>%; transform: translateX(5px); white-space: nowrap;">
                        HOJE (<?php echo $today_label; ?>)
                    </span>
                </div>
            </div>
            
            <?php if (empty($projects)): ?>
                <p class="text-gray-400 text-center py-4">Nenhum projeto para exibir na linha do tempo.</p>
            <?php else: ?>
                <?php
                // Função para interpolar cor entre verde e vermelho
                function interpolateColor($ratio) {
                    $r = (int)(34 + ($ratio * (239 - 34))); // 34 (verde) -> 239 (vermelho)
                    $g = (int)(197 - ($ratio * (197 - 55))); // 197 (verde) -> 55 (vermelho)
                    $b = (int)(94 - ($ratio * 94)); // 94 (verde) -> 0 (vermelho)
                    return "rgb($r,$g,$b)";
                }

                foreach ($projects as $project):
                    $start_ts = $project['start_date'] ? strtotime($project['start_date']) : null;
                    $deadline_ts = $project['deadline'] ? strtotime($project['deadline']) : null;

                    // Somente exibe projetos que têm data de início e prazo definidos e que se sobrepõem ao período de exibição
                    if ($start_ts === null || $deadline_ts === null || $deadline_ts < $min_display_date || $start_ts > $max_display_date) {
                        continue;
                    }

                    // Calcula o início e o fim visíveis do projeto dentro do range de 20 dias
                    $visible_start_ts = max($start_ts, $min_display_date);
                    $visible_end_ts = min($deadline_ts, $max_display_date);

                    // Calcular offsets em dias relativos ao período de exibição (min_display_date a max_display_date)
                    $offset_start_project_days = ($visible_start_ts - $min_display_date) / (60 * 60 * 24);
                    $offset_end_project_days = ($visible_end_ts - $min_display_date) / (60 * 60 * 24);

                    // Posição percentual de início e fim do projeto dentro da barra de 100% (20 dias)
                    $project_display_start_percent = ($offset_start_project_days / $display_range_days) * 100;
                    $project_display_end_percent = ($offset_end_project_days / $display_range_days) * 100;

                    // Posição do "HOJE" na barra (fixa no 5º dia, mas relativa ao período total de 20 dias)
                    $today_pos_percent_on_bar = ($today_display_offset_days / $display_range_days) * 100;

                    // Calcular o progresso real do projeto para o degradê
                    $progress_ratio = 0;
                    if ($deadline_ts > $start_ts) {
                        $progress_ratio = ($today_ts - $start_ts) / ($deadline_ts - $start_ts);
                        $progress_ratio = max(0, min(1, $progress_ratio));
                    }
                    
                    $color_gradient_css = '';
                    $color_width = 0;
                    $gray_width = 0;

                    if ($today_ts >= $deadline_ts) {
                        // Se o projeto já passou da data limite, a barra é toda colorida (verde ao vermelho total)
                        $color_width = $project_display_end_percent - $project_display_start_percent;
                        $gray_width = 0;
                        $color_gradient_css = "linear-gradient(to right, #22c55e, " . interpolateColor(1) . ")";
                    } elseif ($today_ts < $start_ts) {
                         // Se o projeto ainda não começou, a barra é toda cinza
                        $color_width = 0;
                        $gray_width = $project_display_end_percent - $project_display_start_percent;
                    } else {
                        // Se o projeto está em andamento, o degradê vai até a data de hoje, e o restante é cinza
                        // Calcula a cor no ponto "hoje"
                        $current_progress_ratio_for_color = ($today_ts - $start_ts) / ($deadline_ts - $start_ts);
                        $current_progress_ratio_for_color = max(0, min(1, $current_progress_ratio_for_color));
                        $color_to_today = interpolateColor($current_progress_ratio_for_color);

                        $color_gradient_css = "linear-gradient(to right, #22c55e, $color_to_today)";
                        
                        // Largura da parte colorida: do início visível até a posição do "HOJE" (limitado ao fim visível do projeto)
                        $color_segment_end_percent = min($today_pos_percent_on_bar, $project_display_end_percent);
                        $color_width = max(0, $color_segment_end_percent - $project_display_start_percent);

                        // Largura da parte cinza: da posição do "HOJE" (ou início visível, se HOJE for antes) até o fim visível
                        $gray_segment_start_percent = max($today_pos_percent_on_bar, $project_display_start_percent);
                        $gray_width = max(0, $project_display_end_percent - $gray_segment_start_percent);

                        // Ajuste se a cor e o cinza se sobrepõem na borda do projeto
                        if ($project_display_start_percent + $color_width > $project_display_end_percent) {
                             $color_width = $project_display_end_percent - $project_display_start_percent;
                             $gray_width = 0;
                        } else if ($project_display_start_percent + $color_width + $gray_width > $project_display_end_percent + 0.1) { // Pequeno offset para evitar problemas de float
                            $gray_width = $project_display_end_percent - ($project_display_start_percent + $color_width);
                        }
                    }


                    // Formatar datas para exibição
                    $display_deadline_date_label = date('d/m', $deadline_ts);
                    
                    // Calcular a posição da data final dentro da barra do projeto
                    $label_pos_on_global_line = (($deadline_ts - $min_display_date) / (60 * 60 * 24 * $display_range_days)) * 100;
                    $label_transform_x = '-50%'; // Centraliza o texto na posição

                    // Estimativa de largura do texto "DD/MM" (aprox. 30px em uma tela grande, o que em 75% da largura da tela total, ou ~1000px, seria uns 3%)
                    $estimated_label_width_percent = (strlen($display_deadline_date_label) * 0.7); // Ajuste este valor conforme necessário

                    // Regra 1: Se o deadline estiver após o final da linha do tempo de 20 dias, fixe o label na extrema direita.
                    if ($deadline_ts > $max_display_date) {
                         $label_left_pos = 100;
                         $label_transform_x = '-100%'; // Alinha o final do texto à direita da barra
                    }
                    // Regra 2: Se a data final do projeto está dentro do período visível do projeto
                    else {
                        // Posiciona o label na porcentagem correspondente ao deadline dentro dos 20 dias
                        $label_left_pos = (($deadline_ts - $min_display_date) / (60 * 60 * 24)) / ($display_range_days / 100);
                        
                        // Garante que o label não saia para a direita da *barra visível do projeto*
                        // Verifica se a parte direita do label (metade da largura do label) excederia o fim da barra visível do projeto
                        if (($label_left_pos + ($estimated_label_width_percent / 2)) > $project_display_end_percent) { 
                            $label_left_pos = $project_display_end_percent - ($estimated_label_width_percent / 2) - 0.5; // move o centro do label para dentro
                            $label_transform_x = '-50%'; 
                        }
                        // Garante que o label não saia para a esquerda da *barra visível do projeto*
                        // Verifica se a parte esquerda do label (metade da largura do label) excederia o início da barra visível do projeto
                        else if (($label_left_pos - ($estimated_label_width_percent / 2)) < $project_display_start_percent) {
                            $label_left_pos = $project_display_start_percent + ($estimated_label_width_percent / 2) + 0.5; // move o centro do label para dentro
                            $label_transform_x = '-50%';
                        }
                        else {
                            $label_transform_x = '-50%'; // Centraliza normalmente
                        }
                    }
                    
                    // Garante que o label_left_pos final esteja entre 0 e 100 (dentro da linha do tempo global)
                    $label_left_pos = max(0, min(100, $label_left_pos));

                    ?>
                    <div class="flex items-center text-sm">
                        <div class="w-1/4 pr-4 text-gray-300 truncate">
                            <span class="font-semibold text-white"><?php echo htmlspecialchars($project['project_name']); ?></span> - <?php echo htmlspecialchars($project['company_name']); ?>
                        </div>
                        <div class="w-3/4 relative h-4 bg-gray-700 rounded-full flex items-center overflow-hidden">
                            <?php if ($color_width > 0): ?>
                                <div class="absolute h-full rounded-l-full"
                                     style="left: <?php echo $project_display_start_percent; ?>%;
                                            width: <?php echo $color_width; ?>%;
                                            background-image: <?php echo $color_gradient_css; ?>;">
                                </div>
                            <?php endif; ?>
                            <?php if ($gray_width > 0): ?>
                                <div class="absolute h-full rounded-r-full"
                                     style="left: <?php echo $project_display_start_percent + $color_width; ?>%;
                                            width: <?php echo $gray_width; ?>%;
                                            background-color: #a0a0a0;">
                                </div>
                            <?php endif; ?>

                            <?php if ($today_pos_percent_on_bar >= $project_display_start_percent && $today_pos_percent_on_bar <= $project_display_end_percent): ?>
                            <div class="absolute top-0 bottom-0 bg-purple-400"
                                 style="left: <?php echo $today_pos_percent_on_bar; ?>%; transform: translateX(-50%); width: 2px; z-index: 10;">
                            </div>
                            <?php endif; ?>

                            <span class="absolute text-xs font-bold text-white"
                                  style="left: <?php echo $label_left_pos; ?>%; top: 50%; transform: translate(<?php echo $label_transform_x; ?>, -50%); white-space: nowrap;">
                                <?php echo $display_deadline_date_label; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-gray-800 rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-white mb-4">Estimativa de Produtividade</h2>

        <?php
        // Bloco de depuração para verificação dos projetos
        /*
        echo '<div class="bg-blue-600 text-white p-4 rounded-lg mb-6">';
        echo '<h3>DEBUG: Projetos para Estimativa</h3>';
        echo '<p>Data de hoje (timestamp): ' . $today_ts . ' (' . date('Y-m-d', $today_ts) . ')</p>';
        if (empty($projects)) {
            echo '<p>Nenhum projeto carregado do banco de dados.</p>';
        } else {
            foreach ($projects as $proj) {
                $proj_deadline_ts = $proj['deadline'] ? strtotime($proj['deadline']) : null;
                $is_active_status = ($proj['status'] !== 'completed' && $proj['status'] !== 'cancelled');
                $has_future_deadline = ($proj_deadline_ts !== null && $proj_deadline_ts >= $today_ts);
                $has_word_count = ($proj['word_count'] > 0);
                $meets_criteria = $is_active_status && $has_future_deadline && $has_word_count;

                echo '<p>--- Projeto ID: ' . $proj['id'] . ' | Título: ' . htmlspecialchars($proj['project_name']) . ' ---</p>';
                echo '<ul class="list-disc ml-5">';
                echo '<li>Status: ' . htmlspecialchars($proj['status']) . ' (Pendente/Em andamento? ' . ($is_active_status ? 'Sim' : 'Não') . ')</li>';
                echo '<li>Deadline: ' . htmlspecialchars($proj['deadline']) . ' (Timestamp: ' . ($proj_deadline_ts ?: 'N/A') . ') ( >= Hoje? ' . ($has_future_deadline ? 'Sim' : 'Não') . ')</li>';
                echo '<li>Word Count: ' . htmlspecialchars($proj['word_count']) . ' (>0? ' . ($has_word_count ? 'Sim' : 'Não') . ')</li>';
                echo '<li>Atende aos critérios para estimativa? ' . ($meets_criteria ? 'SIM' : 'NÃO') . '</li>';
                echo '</ul>';
            }
        }
        echo '</div>';
        */
        ?>

        <?php
        $pending_projects_for_estimation = array_filter($projects, function($p) use ($today_ts) {
            // Verifica se o status não é "completed" nem "cancelled"
            $is_active_status = ($p['status'] !== 'completed' && $p['status'] !== 'cancelled');
            
            // Verifica se o prazo de entrega não é nulo e é maior ou igual à data de hoje
            $has_future_deadline = false;
            if ($p['deadline']) {
                $deadline_ts_project = strtotime($p['deadline']);
                // Use >= para incluir o dia do deadline como um dia útil
                $has_future_deadline = ($deadline_ts_project >= $today_ts);
            }

            // Verifica se a contagem de palavras é maior que zero
            $has_word_count = ($p['word_count'] > 0);

            return $is_active_status && $has_future_deadline && $has_word_count;
        });

        if (empty($pending_projects_for_estimation)): ?>
            <p class="text-gray-400 text-center py-4">Nenhum projeto pendente com prazo e contagem de palavras para estimativa de produtividade.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="pb-3 text-gray-300">Projeto</th>
                            <th class="pb-3 text-gray-300">Prazo</th>
                            <th class="pb-3 text-gray-300">Contagem de Palavras</th>
                            <th class="pb-3 text-gray-300">Dias Restantes</th>
                            <th class="pb-3 text-gray-300">Meta Diária (Projeto)</th>
                            <th class="pb-3 text-gray-300">Sugestão Diária para Entregar no Prazo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_projects_for_estimation as $project):
                            $deadline_ts = strtotime($project['deadline']);
                            // Calcula dias restantes, incluindo o dia de hoje e o dia do deadline
                            $days_remaining_calc = ($deadline_ts - $today_ts) / (60 * 60 * 24);
                            $days_remaining = max(1, floor($days_remaining_calc) + 1); // Garante no mínimo 1 dia, adiciona o dia do deadline

                            $words_to_translate = $project['word_count'];
                            
                            $suggested_daily_words = ($days_remaining > 0) ? ceil($words_to_translate / $days_remaining) : $words_to_translate;
                            ?>
                            <tr class="border-b border-gray-700 hover:bg-gray-700">
                                <td class="py-4 text-white font-medium"><?php echo htmlspecialchars($project['project_name']); ?></td>
                                <td class="py-4 text-gray-300"><?php echo date('d/m/Y', $deadline_ts); ?></td>
                                <td class="py-4 text-gray-300"><?php echo number_format($words_to_translate, 0, ',', '.'); ?></td>
                                <td class="py-4 text-gray-300"><?php echo $days_remaining; ?></td>
                                <td class="py-4 text-gray-300"><?php echo $project['daily_word_target'] > 0 ? number_format($project['daily_word_target'], 0, ',', '.') : '-'; ?></td>
                                <td class="py-4 text-gray-300">
                                    <?php
                                    $daily_target_set = $project['daily_word_target'];
                                    if ($daily_target_set > 0) {
                                        if ($daily_target_set < $suggested_daily_words) {
                                            echo '<span class="text-orange-400 font-semibold">' . number_format($suggested_daily_words, 0, ',', '.') . ' (Acima da meta!)</span>';
                                        } elseif ($daily_target_set >= $suggested_daily_words) {
                                            echo '<span class="text-green-400 font-semibold">' . number_format($suggested_daily_words, 0, ',', '.') . ' (Meta atingida!)</span>';
                                        }
                                    } else {
                                        echo number_format($suggested_daily_words, 0, ',', '.');
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-gray-800 rounded-lg p-6">
        <div class="flex flex-col md:flex-row items-center justify-between mb-6 gap-4">
            <h2 class="text-xl font-semibold text-white">Lista de Projetos</h2>

            <form method="GET" class="flex gap-2">
                <input type="text" name="search" placeholder="Buscar projetos..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                <select name="status" class="px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg focus:border-purple-500 focus:outline-none text-white">
                    <option value="">Todos os status</option>
                    <?php foreach ($dash_config['project_statuses'] as $statusCode => $statusName): ?>
                        <option value="<?php echo $statusCode; ?>" <?php echo ($status_filter == $statusCode) ? 'selected' : ''; ?>>
                            <?php echo $statusName; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-search"></i>
                </button>
                <?php if ($search || $status_filter): ?>
                    <a href="projects.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($projects)): ?>
            <p class="text-gray-400 text-center py-8">
                <?php echo ($search || $status_filter) ? 'Nenhum projeto encontrado com os critérios de busca.' : 'Nenhum projeto cadastrado ainda.'; ?>
            </p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                    <tr class="border-b border-gray-700">
                        <th class="pb-3 text-gray-300">Projeto</th>
                        <th class="pb-3 text-gray-300">PO</th>
                        <th class="pb-3 text-gray-300">Cliente</th>
                        <th class="pb-3 text-gray-300">Idiomas</th>
                        <th class="pb-3 text-gray-300">Status</th>
                        <th class="pb-3 text-gray-300">Valor</th>
                        <th class="pb-3 text-gray-300">Prazo</th>
                        <th class="pb-3 text-gray-300">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700">
                            <td class="py-4">
                                <div>
                                    <p class="text-white font-medium"><?php echo htmlspecialchars($project['project_name']); ?></p>
                                    <p class="text-gray-400 text-sm"><?php echo ucfirst($project['service_type']); ?></p>
                                </div>
                            </td>
                            <td class="py-4 text-gray-300"><?php echo htmlspecialchars($project['po_number'] ?: '-'); ?></td>
                            <td class="py-4 text-gray-300"><?php echo htmlspecialchars($project['company_name']); ?></td>
                            <td class="py-4 text-gray-300 text-sm">
                                <?php echo $project['source_language']; ?> → <?php echo $project['target_language']; ?>
                            </td>
                            <td class="py-4">
                                <span class="inline-block px-2 py-1 text-xs rounded-full
                                <?php
                                switch ($project['status']) {
                                    case 'completed':
                                        echo 'bg-green-600 text-white';
                                        break;
                                    case 'in_progress':
                                        echo 'bg-blue-600 text-white';
                                        break;
                                    case 'pending':
                                        echo 'bg-yellow-600 text-white';
                                        break;
                                    case 'on_hold':
                                        echo 'bg-orange-600 text-white';
                                        break;
                                    case 'cancelled':
                                        echo 'bg-red-600 text-white';
                                        break;
                                    default:
                                        echo 'bg-gray-600 text-white';
                                }
                                ?>">
                                    <?php
                                    $status_labels = [
                                        'pending' => 'Pendente',
                                        'in_progress' => 'Em Andamento',
                                        'completed' => 'Concluído',
                                        'cancelled' => 'Cancelado',
                                        'on_hold' => 'Pausado'
                                    ];
                                    echo $status_labels[$project['status']] ?? $project['status'];
                                    ?>
                                </span>
                            </td>
                            <td class="py-4 text-gray-300">
                                <?php echo formatCurrency($project['total_amount'], $project['currency'] ?? 'BRL'); ?>
                            </td>
                            <td class="py-4 text-gray-300">
                                <?php if ($project['deadline']): ?>
                                    <?php echo date('d/m/Y', strtotime($project['deadline'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="py-4">
                                <div class="flex gap-2">
                                    <a href="?edit=<?php echo $project['id']; ?>"
                                       class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($project['status'] != 'completed'): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja marcar este projeto como concluído?')">
                                            <input type="hidden" name="action" value="complete_project">
                                            <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-sm transition-colors">
                                                <i class="fas fa-check-circle"></i> Concluir
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja gerar uma fatura para este projeto? Isso criará uma nova fatura.')">
                                        <input type="hidden" name="action" value="generate_invoice">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" class="bg-purple-600 hover:bg-purple-700 px-3 py-1 rounded text-sm transition-colors">
                                            <i class="fas fa-file-invoice"></i> Gerar Fatura
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir este projeto?')">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm transition-colors">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Referências aos campos do formulário
const wordCountInput = document.getElementById('word_count');
const ratePerWordInput = document.getElementById('rate_per_word');
// Removido charCountInput e ratePerCharInput
const totalCalculatedInput = document.getElementById('total_calculated');
const negotiatedAmountInput = document.getElementById('negotiated_amount');
const currencyWordRateSelect = document.getElementById('currency_word_rate');
// Removido currencyCharRateSelect
const currencyNegotiatedSelect = document.getElementById('currency_negotiated');
const clientIdSelect = document.getElementById('client_id');

function calculateTotal() {
    const wordCount = parseFloat(wordCountInput.value) || 0;
    const ratePerWord = parseFloat(ratePerWordInput.value) || 0;
    // characterCount e ratePerCharacter não são mais lidos do formulário
    const characterCount = 0; 
    const ratePerCharacter = 0;

    const wordTotal = wordCount * ratePerWord;
    const charTotal = characterCount * ratePerCharacter; // Será 0
    let total = wordTotal + charTotal;

    totalCalculatedInput.value = formatCurrencyForDisplay(total, currencyWordRateSelect.value);
}

// Helper para formatar moeda no JS (para exibir no total_display)
function formatCurrencyForDisplay(amount, currency) {
    if (isNaN(amount)) {
        amount = 0;
    }
    switch (currency) {
        case 'BRL':
            return 'R$ ' + amount.toFixed(2).replace('.', ',');
        case 'USD':
            return '$' + amount.toFixed(2);
        case 'EUR':
            return '€' + amount.toFixed(2).replace('.', ',');
        default:
            return amount.toFixed(2);
    }
}

// Função para desabilitar campos com base na entrada (Simplificada sem character_count)
function handleRateInput() {
    // A lógica de desabilitar/habilitar campos de palavras/caracteres não é mais necessária
    // se apenas um tipo de contagem (palavras) for usado.
    // Garante que o total é sempre recalculado.
    calculateTotal(); 
    // Sincroniza a moeda da taxa com a moeda negociada se a negociada estiver vazia
    if (negotiatedAmountInput.value === '') {
        currencyNegotiatedSelect.value = currencyWordRateSelect.value;
    }
}

// Sincronizar moeda do projeto com a moeda padrão do cliente
clientIdSelect.addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const defaultCurrency = selectedOption.dataset.currency;
    if (defaultCurrency) {
        currencyWordRateSelect.value = defaultCurrency;
        // currencyCharRateSelect removido
        if (negotiatedAmountInput.value === '') {
            currencyNegotiatedSelect.value = defaultCurrency;
        }
    }
    // Chame handleRateInput para garantir que os campos corretos sejam desabilitados/habilitados
    // com base no estado atual após a mudança do cliente.
    handleRateInput(); // Chamada simplificada
});

// Sincronizar a moeda negociada com a moeda das taxas se a negociada for alterada
function updateCurrencyForNegotiated() {
    if (negotiatedAmountInput.value !== '') {
        wordCountInput.disabled = true;
        ratePerWordInput.disabled = true;
        // Campos de caracteres não existem mais para desabilitar
    } else {
        wordCountInput.disabled = false;
        ratePerWordInput.disabled = false;
        calculateTotal();
        const selectedOption = clientIdSelect.options[clientIdSelect.selectedIndex];
        const defaultCurrency = selectedOption.dataset.currency;
        if (defaultCurrency) {
            currencyNegotiatedSelect.value = defaultCurrency;
        }
    }
}
negotiatedAmountInput.addEventListener('input', updateCurrencyForNegotiated);


// Chamar calculateTotal no carregamento da página e se estiver editando para preencher o campo
document.addEventListener('DOMContentLoaded', function () {
    calculateTotal();
    if (negotiatedAmountInput.value !== '') {
        wordCountInput.disabled = true;
        ratePerWordInput.disabled = true;
        // Campos de caracteres não existem mais para desabilitar
    } else {
        // Chamada simplificada
        handleRateInput(); 
    }

    if (clientIdSelect.value) {
        const selectedOption = clientIdSelect.options[clientIdSelect.selectedIndex];
        const defaultCurrency = selectedOption.dataset.currency;
        if (defaultCurrency) {
            currencyWordRateSelect.value = defaultCurrency;
            // currencyCharRateSelect removido
            if (!negotiatedAmountInput.value) {
                currencyNegotiatedSelect.value = defaultCurrency;
            }
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>