<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php'; // Inclui dash_functions.php

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$page_title = 'Visualizar Fatura - Dash-T101';
$user_id = $_SESSION['user_id'];
$invoice_id = $_GET['id'] ?? null;

if (!$invoice_id) {
    header('Location: invoices.php'); // Redireciona se não houver ID da fatura
    exit;
}

// Obter detalhes da fatura
$stmt = $pdo->prepare("
    SELECT 
        i.*, 
        i.issue_date AS invoice_date, 
        c.company AS company_name, 
        c.name AS contact_name,
        c.email AS client_email,
        c.vat_number AS client_vat_number,         
        c.address_line1 AS client_address_line1,    
        c.address_line2 AS client_address_line2,    
        c.address_line3 AS client_address_line3,    
        c.phone AS client_phone,
        ds.user_name AS user_full_name,             
        ds.cnpj_cpf AS user_cnpj_cpf,               
        ds.user_country AS user_country,            
        ds.user_state AS user_state,                
        ds.company_name AS user_company_name,
        ds.company_address AS user_company_address,
        ds.company_phone AS user_company_phone,
        ds.company_email AS user_company_email,
        ds.company_website AS user_company_website, 
        ds.bank_details AS user_bank_details,       
        ds.invoice_terms AS user_invoice_terms,     
        ds.invoice_footer AS user_invoice_footer    
    FROM 
        dash_invoices i
    LEFT JOIN 
        dash_clients c ON i.client_id = c.id
    LEFT JOIN
        dash_user_settings ds ON i.user_id = ds.user_id
    WHERE 
        i.id = ? AND i.user_id = ?
");
$stmt->execute([$invoice_id, $user_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    $_SESSION['error_message'] = 'Fatura não encontrada ou você não tem permissão para visualizá-la.';
    header('Location: invoices.php');
    exit;
}

// Determinar o idioma da fatura
// Se address_line3 não for 'Brasil' (case-insensitive), o idioma será inglês.
$is_brazilian_client = (isset($invoice['client_address_line3']) && strtolower(trim($invoice['client_address_line3'])) === 'brasil');

// Definir labels com base no idioma
$labels = [
    'invoice' => $is_brazilian_client ? 'FATURA' : 'INVOICE',
    'billed_to' => $is_brazilian_client ? 'FATURADO PARA:' : 'BILLED TO:',
    'issue_date' => $is_brazilian_client ? 'Data de Emissão:' : 'Issue Date:',
    'due_date' => $is_brazilian_client ? 'Data de Vencimento:' : 'Due Date:',
    'status' => $is_brazilian_client ? 'Status:' : 'Status:',
    'paid_date' => $is_brazilian_client ? 'Data Pagamento:' : 'Payment Date:',
    'method' => $is_brazilian_client ? 'Método:' : 'Method:',
    'description' => $is_brazilian_client ? 'Descrição' : 'Description',
    'quantity' => $is_brazilian_client ? 'Quantidade' : 'Quantity',
    'unit_price' => $is_brazilian_client ? 'Preço Unitário' : 'Unit Price',
    'total' => $is_brazilian_client ? 'Total' : 'Total',
    'subtotal' => $is_brazilian_client ? 'Subtotal:' : 'Subtotal:',
    'tax' => $is_brazilian_client ? 'Imposto' : 'Tax',
    'notes' => $is_brazilian_client ? 'Observações:' : 'Notes:',
    'bank_details' => $is_brazilian_client ? 'Dados Bancários para Pagamento:' : 'Bank Details for Payment:',
    'payment_terms' => $is_brazilian_client ? 'Termos de Pagamento:' : 'Payment Terms:',
    'print_save_pdf' => $is_brazilian_client ? 'Imprimir / Salvar PDF' : 'Print / Save PDF',
    'back' => $is_brazilian_client ? 'Voltar' : 'Back',
    'ref_project' => $is_brazilian_client ? 'Ref. Projeto:' : 'Ref. Project:',
    'words' => $is_brazilian_client ? 'palavras' : 'words',
    'characters' => $is_brazilian_client ? 'caracteres' : 'characters',
    'per_word' => $is_brazilian_client ? '/palavra' : '/word',
    'per_character' => $is_brazilian_client ? '/caractere' : '/character',
    'no_items' => $is_brazilian_client ? 'Nenhum item para esta fatura.' : 'No items for this invoice.',
    'company_name_default' => $is_brazilian_client ? 'Sua Empresa' : 'Your Company',
    'vat_label' => $is_brazilian_client ? 'VAT/CNPJ/CPF:' : 'VAT/Tax ID:',
    'phone_label' => $is_brazilian_client ? 'Tel:' : 'Phone:',
    'email_label' => $is_brazilian_client ? 'Email:' : 'Email:',
    'web_label' => $is_brazilian_client ? 'Web:' : 'Web:',
];


// Obter itens da fatura
$stmt = $pdo->prepare("
    SELECT 
        ii.*,
        p.title AS project_name,
        p.po_number,               
        p.word_count,              
        p.rate_per_word,           
        p.character_count,         
        p.rate_per_character,      
        p.source_language,
        p.target_language,
        p.service_type
    FROM 
        dash_invoice_items ii
    LEFT JOIN
        dash_projects p ON ii.project_id = p.id
    WHERE 
        ii.invoice_id = ?
");
$stmt->execute([$invoice_id]);
$invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obter configurações do usuário (necessário para 'dash_config')
$user_settings = getUserSettings($user_id);

// Gerar nome de arquivo único para PDF
// Remove caracteres especiais e espaços do nome da empresa do cliente, substitui espaços por _
$client_name_for_pdf = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $invoice['company_name']));
$invoice_month_year = date('m_Y', strtotime($invoice['invoice_date']));
// Formato sugerido: INVOICE_NomeDoCliente_Mes_Ano_NumeroDaFatura.pdf
$pdf_filename = ($is_brazilian_client ? 'Fatura' : 'Invoice') . "_{$client_name_for_pdf}_{$invoice_month_year}_{$invoice['invoice_number']}.pdf";


// Define o título da página, que será o nome do arquivo PDF sugerido
$page_title = ($is_brazilian_client ? 'Fatura' : 'Invoice') . " #{$invoice['invoice_number']} - {$invoice['company_name']}";
?>

<!DOCTYPE html>
<html lang="<?php echo $is_brazilian_client ? 'pt-BR' : 'en-US'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .print\:hidden {
                display: none;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bg-gray-800 { background-color: #1f2937 !important; }
            .bg-gray-700 { background-color: #374151 !important; }
            .text-gray-100 { color: #f3f4f6 !important; }
            .text-gray-300 { color: #d1d5db !important; }
            .text-gray-400 { color: #9ca3af !important; }
            .text-gray-500 { color: #6b7280 !important; }
            .text-white { color: #ffffff !important; }
            .border-gray-700 { border-color: #4b5563 !important; }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 font-sans">

<div class="container mx-auto px-4 py-8 bg-gray-900 text-gray-100 min-h-screen">

    <div class="max-w-4xl mx-auto bg-gray-800 p-8 rounded-lg shadow-lg">

        <div class="flex justify-between items-start mb-8 border-b border-gray-700 pb-6">
            <div>
                <h1 class="text-4xl font-bold text-white mb-2"><?php echo $labels['invoice']; ?></h1>
                <p class="text-xl text-purple-400">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
            </div>
            <div class="text-right">
                <?php if (!empty($invoice['user_company_name'])): ?>
                    <h2 class="text-2xl font-semibold text-white"><?php echo htmlspecialchars($invoice['user_company_name']); ?></h2>
                <?php else: ?>
                    <h2 class="text-2xl font-semibold text-white"><?php echo $labels['company_name_default']; ?></h2>
                <?php endif; ?>
                <?php if (!empty($invoice['user_full_name'])): ?>
                    <p class="text-md text-gray-300"><?php echo htmlspecialchars($invoice['user_full_name']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['user_cnpj_cpf'])): ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['vat_label']; ?> <?php echo htmlspecialchars($invoice['user_cnpj_cpf']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['user_company_address'])): ?>
                    <p class="text-sm text-gray-400"><?php echo nl2br(htmlspecialchars($invoice['user_company_address'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['user_state']) || !empty($invoice['user_country'])): ?>
                     <p class="text-sm text-gray-400">
                        <?php echo htmlspecialchars($invoice['user_state'] ?? ''); ?>
                        <?php echo !empty($invoice['user_state']) && !empty($invoice['user_country']) ? ',' : ''; ?>
                        <?php echo htmlspecialchars($invoice['user_country'] ?? ''); ?>
                    </p>
                <?php endif; ?>
                <?php if (!empty($invoice['user_company_phone'])): ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['phone_label']; ?> <?php echo htmlspecialchars($invoice['user_company_phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['user_company_email'])): ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['email_label']; ?> <?php echo htmlspecialchars($invoice['user_company_email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['user_company_website'])): ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['web_label']; ?> <?php echo htmlspecialchars($invoice['user_company_website']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-lg font-semibold text-white mb-2"><?php echo $labels['billed_to']; ?></h3>
                <p class="font-medium text-white"><?php echo htmlspecialchars($invoice['company_name'] ?? 'N/A'); ?></p>
                <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($invoice['contact_name'] ?? 'N/A'); ?></p>
                <?php if (!empty($invoice['client_vat_number'])): ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['vat_label']; ?> <?php echo htmlspecialchars($invoice['client_vat_number']); ?></p>
                <?php endif; ?>
                <?php 
                $client_address_lines = [];
                if (!empty($invoice['client_address_line1'])) $client_address_lines[] = htmlspecialchars($invoice['client_address_line1']);
                if (!empty($invoice['client_address_line2'])) $client_address_lines[] = htmlspecialchars($invoice['client_address_line2']);
                if (!empty($invoice['client_address_line3'])) $client_address_lines[] = htmlspecialchars($invoice['client_address_line3']);
                
                foreach($client_address_lines as $line):
                    if (!empty($line)): ?>
                        <p class="text-sm text-gray-400"><?php echo $line; ?></p>
                    <?php endif;
                endforeach;
                ?>

                <?php if (!empty($invoice['client_phone'])): ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['phone_label']; ?> <?php echo htmlspecialchars($invoice['client_phone']); ?></p>
                <?php endif; ?>
                <p class="text-gray-400 text-sm"><?php echo $labels['email_label']; ?> <?php echo htmlspecialchars($invoice['client_email'] ?? 'N/A'); ?></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-400"><?php echo $labels['issue_date']; ?> <span class="text-white"><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></span></p>
                <p class="text-sm text-gray-400"><?php echo $labels['due_date']; ?> <span class="text-white"><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></span></p>
                <p class="text-sm text-gray-400"><?php echo $labels['status']; ?> <span class="text-white <?php echo getStatusColor($invoice['status'], 'invoice'); ?> px-2 py-0.5 rounded-full text-xs"><?php echo getStatusLabel($invoice['status'], 'invoice'); ?></span></p>
                <?php if ($invoice['status'] == 'paid' && !empty($invoice['paid_date'])): // Changed 'payment_date' to 'paid_date' based on SQL dump ?>
                    <p class="text-sm text-gray-400"><?php echo $labels['paid_date']; ?> <span class="text-white"><?php echo date('d/m/Y', strtotime($invoice['paid_date'])); ?></span></p>
                    <p class="text-sm text-gray-400"><?php echo $labels['method']; ?> <span class="text-white"><?php echo htmlspecialchars($invoice['payment_method'] ?? 'N/A'); ?></span></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-8">
            <table class="w-full text-left table-auto">
                <thead>
                    <tr class="bg-gray-700">
                        <th class="p-3 text-gray-300"><?php echo $labels['description']; ?></th>
                        <th class="p-3 text-gray-300 text-right"><?php echo $labels['quantity']; ?></th>
                        <th class="p-3 text-gray-300 text-right"><?php echo $labels['unit_price']; ?></th>
                        <th class="p-3 text-gray-300 text-right"><?php echo $labels['total']; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoice_items)): ?>
                        <tr><td colspan="4" class="p-3 text-center text-gray-400"><?php echo $labels['no_items']; ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($invoice_items as $item): ?>
                            <tr class="border-b border-gray-700">
                                <td class="p-3">
                                    <p class="text-white font-medium">
                                        <?php 
                                            $item_desc = htmlspecialchars($item['description']);
                                            if (!empty($item['po_number'])) {
                                                $item_desc = "PO: " . htmlspecialchars($item['po_number']) . " - " . $item_desc;
                                            }
                                            echo $item_desc;
                                        ?>
                                    </p>
                                    <?php if (!empty($item['project_id']) && !empty($item['project_name'])): ?>
                                        <p class="text-xs text-gray-400"><?php echo $labels['ref_project']; ?> <?php echo htmlspecialchars($item['project_name']); ?></p>
                                        <p class="text-xs text-gray-500">
                                            (<?php echo $item['source_language']; ?> &rarr; <?php echo $item['target_language']; ?>, <?php echo $item['service_type']; ?>)
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right text-white">
                                    <?php 
                                        if (!empty($item['word_count']) && $item['word_count'] > 0) {
                                            echo number_format($item['word_count'], 0, ',', '.') . ' ' . $labels['words'];
                                        } elseif (!empty($item['character_count']) && $item['character_count'] > 0) {
                                            echo number_format($item['character_count'], 0, ',', '.') . ' ' . $labels['characters'];
                                        } else {
                                            echo number_format($item['quantity'], 2, ',', '.'); 
                                        }
                                    ?>
                                </td>
                                <td class="p-3 text-right text-white">
                                    <?php 
                                        if (!empty($item['rate_per_word']) && $item['rate_per_word'] > 0) {
                                            echo formatCurrency($item['rate_per_word'], $invoice['currency']);
                                            echo $labels['per_word'];
                                        } elseif (!empty($item['rate_per_character']) && $item['rate_per_character'] > 0) {
                                            echo formatCurrency($item['rate_per_character'], $invoice['currency']);
                                            echo $labels['per_character'];
                                        } else {
                                            echo formatCurrency($item['unit_price'], $invoice['currency']);
                                        }
                                    ?>
                                </td>
                                <td class="p-3 text-right text-white"><?php echo formatCurrency($item['total_price'], $invoice['currency']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end mb-8">
            <div class="w-1/2 md:w-1/3">
                <div class="flex justify-between items-center py-2 border-b border-gray-700">
                    <span class="text-gray-300"><?php echo $labels['subtotal']; ?></span>
                    <span class="text-white font-semibold"><?php echo formatCurrency($invoice['subtotal'], $invoice['currency']); ?></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-700">
                    <span class="text-gray-300"><?php echo $labels['tax']; ?> (<?php echo number_format($invoice['tax_rate'], 2, ',', '.'); ?>%):</span>
                    <span class="text-white font-semibold"><?php echo formatCurrency($invoice['tax_amount'], $invoice['currency']); ?></span>
                </div>
                <div class="flex justify-between items-center py-2 font-bold text-xl text-purple-400">
                    <span><?php echo $labels['total']; ?>:</span>
                    <span><?php echo formatCurrency($invoice['total_amount'], $invoice['currency']); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($invoice['notes'])): ?>
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-white mb-2"><?php echo $labels['notes']; ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($invoice['user_bank_details'])): ?>
            <div class="mb-8 p-4 bg-gray-700 rounded-lg">
                <h3 class="text-lg font-semibold text-white mb-2"><?php echo $labels['bank_details']; ?></h3>
                <p class="text-gray-300 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($invoice['user_bank_details'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($invoice['user_invoice_terms'])): ?>
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-white mb-2"><?php echo $labels['payment_terms']; ?></h3>
                <p class="text-gray-400 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($invoice['user_invoice_terms'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($invoice['user_invoice_footer'])): ?>
            <div class="text-center text-gray-500 text-sm">
                <p><?php echo htmlspecialchars($invoice['user_invoice_footer']); ?></p>
            </div>
        <?php endif; ?>

        <div class="mt-8 flex justify-center space-x-4 print:hidden">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center">
                <i class="fas fa-print mr-2"></i> <?php echo $labels['print_save_pdf']; ?>
            </button>
            <a href="invoices.php" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> <?php echo $labels['back']; ?>
            </a>
        </div>

    </div>
</div>

<script>
// Define o título da página, que será usado como nome do arquivo PDF sugerido
document.title = "<?php echo $pdf_filename; ?>";
</script>

</body>
</html>