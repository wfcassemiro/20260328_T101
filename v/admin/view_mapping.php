<?php
session_start();
// Assumindo que seu arquivo de autenticação é 'index (6).php'
require_once 'index.php'; 

// Verificação de autenticação (reforçando a segurança)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$page_title = "Status de Mapeamento de Aulas (Local vs. Hotmart)";

try {
    // 1. Obter todas as palestras com seus IDs Hotmart
    $stmt = $pdo->query("
        SELECT 
            id, 
            title, 
            hotmart_page_id, 
            hotmart_module_id 
        FROM 
            lectures 
        ORDER BY 
            title ASC
    ");
    $lectures = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro ao consultar o banco de dados: " . htmlspecialchars($e->getMessage()));
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #004d40; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .unmapped { background-color: #ffcccc; }
        .mapped { background-color: #ccffcc; }
        .hotmart-id { font-weight: bold; color: #d35400; }
        .local-id { font-size: 0.8em; color: #7f8c8d; }
    </style>
</head>
<body>

<div class="container">
    <h1><?php echo htmlspecialchars($page_title); ?></h1>

    <p>Esta tabela mostra todas as suas palestras e se elas foram mapeadas para um ID de página (conteúdo) no Hotmart Club.</p>
    <p>
        <span class="unmapped" style="padding: 2px 5px; border-radius: 3px;">Vermelho</span>: Não mapeado (Pronto para ser associado). | 
        <span class="mapped" style="padding: 2px 5px; border-radius: 3px;">Verde</span>: Mapeado (Pronto para sincronização).
    </p>
    
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Título Local da Palestra</th>
                    <th>ID Local (BD)</th>
                    <th>ID da Página Hotmart</th>
                    <th>ID do Módulo Hotmart</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $mapped_count = 0;
                $unmapped_count = 0;
                foreach ($lectures as $lecture): 
                    $is_mapped = !empty($lecture['hotmart_page_id']);
                    if ($is_mapped) {
                        $mapped_count++;
                        $row_class = 'mapped';
                        $status_text = 'Mapeado';
                    } else {
                        $unmapped_count++;
                        $row_class = 'unmapped';
                        $status_text = 'PENDENTE';
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td><?php echo htmlspecialchars($lecture['title']); ?></td>
                    <td class="local-id"><?php echo htmlspecialchars($lecture['id']); ?></td>
                    <td class="hotmart-id"><?php echo htmlspecialchars($lecture['hotmart_page_id'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($lecture['hotmart_module_id'] ?? '-'); ?></td>
                    <td><?php echo $status_text; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <h3 style="margin-top: 20px;">Resumo:</h3>
    <ul>
        <li>Total de Palestras: <strong><?php echo count($lectures); ?></strong></li>
        <li>Palestras Mapeadas (Sincronizáveis): <strong><?php echo $mapped_count; ?></strong></li>
        <li>Palestras Pendentes de Mapeamento: <strong><?php echo $unmapped_count; ?></strong></li>
    </ul>
</div>

</body>
</html>