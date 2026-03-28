<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: palestras.php');
    exit;
}

$id = trim($_POST['id'] ?? '');
if (empty($id)) {
    header('Location: palestras.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT thumbnail_url FROM lectures WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) throw new Exception("Palestra não encontrada");

    $title = trim($_POST['title'] ?? '');
    $speaker = trim($_POST['speaker'] ?? '');
    $speaker_minibio = trim($_POST['speaker_minibio'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $embed_code = trim($_POST['embed_code'] ?? '');
    $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_live = isset($_POST['is_live']) ? 1 : 0;
    $language = trim($_POST['language'] ?? '');
    $level = trim($_POST['level'] ?? '');

    if (empty($title)) {
    throw new Exception("Título é obrigatório");
    }

    // Converter tags para JSON
    if (!empty($tags)) {
    $tagsArray = array_map('trim', explode(',', $tags));
    $tags = json_encode($tagsArray, JSON_UNESCAPED_UNICODE);
    }

    // Upload se enviado
    if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg','jpeg','png','webp'];
    $ext = strtolower(pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) throw new Exception("Formato inválido da thumbnail.");
    
    $dir = $_SERVER['DOCUMENT_ROOT'] . "/v/images/thumbnails/S08-HR/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $newName = uniqid("thumb_") . "." . $ext;
    $path = $dir . $newName;
    if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $path)) {
    // Apagar antiga se for local
    if (!empty($old['thumbnail_url']) && str_starts_with($old['thumbnail_url'], "/v/images/thumbnails/S08-HR/")) {
    $oldFile = $_SERVER['DOCUMENT_ROOT'] . ltrim($old['thumbnail_url'], '/');
    if (file_exists($oldFile)) unlink($oldFile);
    }
    $thumbnail_url = "/v/images/thumbnails/S08-HR/" . $newName;
    }
    }

    $stmt = $pdo->prepare("
    UPDATE lectures SET 
    title=?, speaker=?, speaker_minibio=?, description=?, duration_minutes=?, 
    embed_code=?, thumbnail_url=?, category=?, tags=?, is_featured=?, is_live=?, 
    language=?, level=?, updated_at=NOW()
    WHERE id=?
    ");
    $stmt->execute([
    $title,$speaker,$speaker_minibio,$description,$duration_minutes,
    $embed_code,$thumbnail_url,$category,$tags,$is_featured,$is_live,
    $language,$level,$id
    ]);

    $_SESSION['admin_message'] = "Palestra atualizada com sucesso!";
    header("Location: palestras.php");
    exit;
} catch (Exception $e) {
    $_SESSION['admin_error'] = "Erro: ".$e->getMessage();
    header("Location: editar_palestra.php?id=".$id);
    exit;
}