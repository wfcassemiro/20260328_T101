<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

// Função para buscar dados da fatura (será usada por ambos os arquivos)
if (!function_exists('fetchInvoiceData')) {
    function fetchInvoiceData($pdo, $invoice_id, $user_id) {
        $stmt = $pdo->prepare(
            "SELECT i.*, c.company AS client_name, c.address_line1, c.address_line2, c.address_line3, c.vat_number
             FROM dash_invoices i
             JOIN dash_clients c ON i.client_id = c.id
             WHERE i.id = ? AND i.user_id = ?"
        );
        $stmt->execute([$invoice_id, $user_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) { return false; }

        $stmt_items = $pdo->prepare("SELECT * FROM dash_invoice_items WHERE invoice_id = ?");
        $stmt_items->execute([$invoice_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        // Busca dados da sua empresa (do arquivo config.php)
        $company_info = [
            'name' => 'Translators101', // Substitua se necessário
            'address' => 'Seu Endereço, 123',
            'city_state_zip' => 'Sua Cidade, SP, 13500-000',
            'phone' => '+55 (19) 99999-9999',
            'email' => 'financeiro@translators101.com'
        ];

        return [
            'invoice' => $invoice,
            'items' => $items,
            'client' => $invoice,
            'company' => $company_info
        ];
    }
}

// Função para gerar o HTML da fatura (será usada por ambos os arquivos)
if (!function_exists('generateInvoiceHTML')) {
    function generateInvoiceHTML($data, $lang_code = 'pt') {
        if (!$data) {
            return "<p>Fatura não encontrada.</p>";
        }

        $invoice = $data['invoice'];
        $items = $data['items'];
        $client = $data['client'];
        $company = $data['company'];

        // Definições de idioma (do seu arquivo original)
        $lang_pt = [
            'invoice_title' => 'FATURA',
            'invoice_num' => 'Fatura #',
            'issue_date' => 'Emissão',
            'due_date' => 'Vencimento',
            'from' => 'De:',
            'to' => 'Para:',
            'service' => 'Descrição do Serviço',
            'value' => 'Valor',
            'subtotal' => 'Subtotal',
            'tax' => 'Imposto',
            'total' => 'Total',
            'notes' => 'Observações'
        ];
        $lang_en = [
            'invoice_title' => 'INVOICE',
            'invoice_num' => 'Invoice #',
            'issue_date' => 'Issue Date',
            'due_date' => 'Due Date',
            'from' => 'From:',
            'to' => 'To:',
            'service' => 'Service Description',
            'value' => 'Value',
            'subtotal' => 'Subtotal',
            'tax' => 'Tax',
            'total' => 'Total',
            'notes' => 'Notes'
        ];
        $lang_es = [
            'invoice_title' => 'FACTURA',
            'invoice_num' => 'Factura #',
            'issue_date' => 'Fecha de Emisión',
            'due_date' => 'Fecha de Vencimiento',
            'from' => 'De:',
            'to' => 'Para:',
            'service' => 'Descripción del Servicio',
            'value' => 'Valor',
            'subtotal' => 'Subtotal',
            'tax' => 'Impuesto',
            'total' => 'Total',
            'notes' => 'Notas'
        ];

        switch ($lang_code) {
            case 'en': $lang = $lang_en; break;
            case 'es': $lang = $lang_es; break;
            default:   $lang = $lang_pt; break;
        }

        $html = '<div class="invoice-box">';
        
        // Cabeçalho
        $html .= '<table cellpadding="0" cellspacing="0" class="invoice-header">
                    <tr class="top">
                        <td colspan="2">
                            <table>
                                <tr>
                                    <td class="title">
                                        </td>
                                    <td class="header-right">
                                        ' . $lang['invoice_num'] . ': <strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong><br>
                                        ' . $lang['issue_date'] . ': ' . date('d/m/Y', strtotime($invoice['issue_date'])) . '<br>
                                        ' . $lang['due_date'] . ': ' . date('d/m/Y', strtotime($invoice['due_date'])) . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';
        
        // Informações da Empresa e Cliente
        $html .= '<tr class="information">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td>
                                    <strong>' . $lang['from'] . '</strong><br>
                                    ' . htmlspecialchars($company['name']) . '<br>
                                    ' . htmlspecialchars($company['address']) . '<br>
                                    ' . htmlspecialchars($company['city_state_zip']) . '<br>
                                    ' . htmlspecialchars($company['email']) . '
                                </td>
                                <td class="client-info">
                                    <strong>' . $lang['to'] . '</strong><br>
                                    ' . htmlspecialchars($client['client_name']) . '<br>
                                    ' . htmlspecialchars($client['address_line1']) . '<br>
                                    ' . htmlspecialchars($client['address_line2']) . '<br>
                                    ' . htmlspecialchars($client['address_line3']) . '<br>
                                    VAT/CNPJ: ' . htmlspecialchars($client['vat_number']) . '
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>';

        // Itens da Fatura
        $html .= '<tr class="heading">
                    <td>' . $lang['service'] . '</td>
                    <td class="text-right">' . $lang['value'] . '</td>
                </tr>';
        
        foreach ($items as $item) {
            $html .= '<tr class="item">
                        <td>' . htmlspecialchars($item['description']) . ' (Qtd: ' . $item['quantity'] . ')</td>
                        <td class="text-right">' . formatCurrency($item['unit_price'] * $item['quantity'], $invoice['currency']) . '</td>
                      </tr>';
        }

        // Totais
        $html .= '<tr class="total">
                    <td class="text-right">' . $lang['subtotal'] . ':</td>
                    <td class="text-right">' . formatCurrency($invoice['subtotal'], $invoice['currency']) . '</td>
                </tr>';
        if ($invoice['tax_amount'] > 0) {
             $html .= '<tr class="total">
                        <td class="text-right">' . $lang['tax'] . ' (' . $invoice['tax_rate'] . '%):</td>
                        <td class="text-right">' . formatCurrency($invoice['tax_amount'], $invoice['currency']) . '</td>
                    </tr>';
        }
        $html .= '<tr class="total grand-total">
                    <td class="text-right"><strong>' . $lang['total'] . ':</strong></td>
                    <td class="text-right"><strong>' . formatCurrency($invoice['total_amount'], $invoice['currency']) . '</strong></td>
                </tr>';

        // Notas
        if (!empty($invoice['notes'])) {
            $html .= '<tr class="heading"><td colspan="2">' . $lang['notes'] . '</td></tr>
                      <tr class="item"><td colspan="2" class="notes">' . nl2br(htmlspecialchars($invoice['notes'])) . '</td></tr>';
        }
        
        $html .= '</table></div>';
        return $html;
    }
}

// =========================================================================
// || LÓGICA DESTA PÁGINA (IMPRESSÃO)                                     ||
// =========================================================================

// Esta parte só é executada se o invoice_print.php for chamado diretamente.
// Se ele for incluído pelo view_invoice.php, o script abaixo não será executado
// (pois o view_invoice.php fará seu próprio exit).

if (!defined('VIEW_INVOICE_PAGE')) {
    
    $user_id_print = $_SESSION['user_id'] ?? null;
    $invoice_id_print = $_GET['id'] ?? null;
    $lang_code_print = $_GET['lang'] ?? 'pt'; // Pega o idioma da URL

    if (!$user_id_print || !$invoice_id_print) {
        die("Acesso negado.");
    }

    $invoice_data = fetchInvoiceData($pdo, $invoice_id_print, $user_id_print);
    $invoice_html = generateInvoiceHTML($invoice_data, $lang_code_print);

?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code_print; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Fatura <?php echo htmlspecialchars($invoice_data['invoice']['invoice_number'] ?? ''); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }
        .invoice-box {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            border: 1px solid #eee;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
        }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; border-collapse: collapse; }
        .invoice-box table td { padding: 8px; vertical-align: top; }
        .invoice-header table td { padding: 0 8px; }
        .invoice-box .top table td { padding-bottom: 20px; }
        .invoice-box .title { font-size: 45px; line-height: 45px; color: #333; }
        .invoice-box .header-right { text-align: right; }
        .invoice-box .information table td { padding-bottom: 30px; }
        .invoice-box .client-info { text-align: right; }
        .invoice-box .heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            padding: 10px 8px;
        }
        .invoice-box .item td { border-bottom: 1px solid #eee; }
        .invoice-box .item.last td { border-bottom: none; }
        .invoice-box .total td { border-top: 1px solid #ddd; font-weight: 500; }
        .invoice-box .grand-total td { border-top: 2px solid #555; font-weight: bold; font-size: 1.1em; }
        .invoice-box .notes { font-size: 0.9em; color: #555; }
        .invoice-box .text-right { text-align: right; }
        
        @media print {
            body {
                background-color: #fff;
                margin: 0;
            }
            .invoice-box {
                max-width: 100%;
                margin: 0;
                padding: 10px;
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <?php echo $invoice_html; ?>
</body>
</html>
<?php
    exit; // Termina a execução
}
?>