<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($page_title) ? $page_title . ' - Translators101' : 'Translators101 - Educação Continuada para Tradutores'; ?></title>
  <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Plataforma de streaming educacional para profissionais de tradução, interpretação e revisão. Quase 400 palestras especializadas.'; ?>">

  <?php
  // Detectar a profundidade do diretório atual para calcular path relativo
  $script_path = $_SERVER['SCRIPT_NAME'];
  $path_parts = explode('/', trim($script_path, '/'));
  $depth = count($path_parts) - 1; // -1 porque o último é o arquivo, não diretório
  $base_path = str_repeat('../', $depth);
  ?>

  <!-- CSS principal Vision -->
  <link rel="stylesheet" href="<?php echo $base_path; ?>vision/assets/css/style.css?v=15">

  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- Google Fonts (Inter para tipografia clean) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">

  <!-- JS principal Vision -->
  <script src="<?php echo $base_path; ?>vision/assets/js/main.js?v=3" defer></script>
</head>
<body class="with-sidebar">