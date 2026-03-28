<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/dash_database.php';
require_once __DIR__ . '/../config/dash_functions.php';

if (!isLoggedIn()) {
    die("Acesso negado.");
}

$user_id = $_SESSION['user_id'];
$today = new DateTime();
$currency = 'BRL';

// Inicializar categorias de "aging"
$aging_data = [
    'a_vencer' => [],
    'vencido_0_30' => [],
    'vencido_31_60' => [],
    'vencido_61_mais' => [],
];
$totals = [
    'a_vencer' => 0,
    'vencido_0_30' => 0,
    'vencido_31_60' => 0,
    'vencido_61_mais' => 0,
    'total_geral' => 0
];

// Lógica de busca de dados (Exatamente a mesma)
try {
    $stmt = $pdo->prepare(
        "SELECT c.company AS client_name, i.invoice_number, i.due_date, i.total_amount, i.currency
         FROM dash_invoices i
         JOIN dash_clients c ON i.client_id = c.id
         WHERE i.user_id = ? 
           AND i.status IN ('sent', 'overdue')
         ORDER BY i.due_date ASC"
    );
    $stmt->execute([$user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invoices as $invoice) {
        $due_date = new DateTime($invoice['due_date']);
        if ($due_date >= $today) {
            $aging_data['a_vencer'][] = $invoice;
            $totals['a_vencer'] += $invoice['total_amount'];
        } else {
            $days_overdue = $today->diff($due_date)->days;
            if ($days_overdue <= 30) {
                $aging_data['vencido_0_30'][] = $invoice;
                $totals['vencido_0_30'] += $invoice['total_amount'];
            } elseif ($days_overdue <= 60) {
                $aging_data['vencido_31_60'][] = $invoice;
                $totals['vencido_31_60'] += $invoice['total_amount'];
            } else {
                $aging_data['vencido_61_mais'][] = $invoice;
                $totals['vencido_61_mais'] += $invoice['total_amount'];
            }
        }
        $totals['total_geral'] += $invoice['total_amount'];
    }

} catch (Exception $e) {
    die("Erro ao gerar relatório: " . $e->getMessage());
}

// Gerar CSV
$filename = "contas_a_receber_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // BOM do UTF-8

// Cabeçalhos do CSV
fputcsv($output, ['Categoria', 'Cliente', 'Fatura', 'Vencimento', 'Moeda', 'Valor']);

// Função helper para escrever linhas
$write_rows = function($category_name, $data) use ($output) {
    foreach ($data as $row) {
        fputcsv($output, [
            $category_name,
            $row['client_name'],
            $row['invoice_number'],
            date('d/m/Y', strtotime($row['due_date'])),
            $row['currency'],
            number_format($row['total_amount'], 2, ',', '.')
        ]);
    }
};

// Escrever dados agrupados
$write_rows('A Vencer', $aging_data['a_vencer']);
$write_rows('Vencido (0-30 dias)', $aging_data['vencido_0_30']);
$write_rows('Vencido (31-60 dias)', $aging_data['vencido_31_60']);
$write_rows('Vencido (61+ dias)', $aging_data['vencido_61_mais']);

// Linhas de Total
fputcsv($output, []); // Linha em branco
fputcsv($output, ['Resumo dos Totais (BRL)', 'Valor']);
fputcsv($output, ['A Vencer', number_format($totals['a_vencer'], 2, ',', '.')]);
fputcsv($output, ['Vencido (0-30 dias)', number_format($totals['vencido_0_30'], 2, ',', '.')]);
fputcsv($output, ['Vencido (31-60 dias)', number_format($totals['vencido_31_60'], 2, ',', '.')]);
fputcsv($output, ['Vencido (61+ dias)', number_format($totals['vencido_61_mais'], 2, ',', '.')]);
fputcsv($output, ['TOTAL GERAL', number_format($totals['total_geral'], 2, ',', '.')]);

fclose($output);
exit;
?>