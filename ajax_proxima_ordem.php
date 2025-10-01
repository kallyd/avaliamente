<?php
require_once 'config/database.php';

header('Content-Type: application/json');

$categoria_id = $_GET['categoria_id'] ?? 0;

if (!$categoria_id) {
    echo json_encode(['erro' => 'Categoria não especificada']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT MAX(ordem) as max_ordem FROM perguntas WHERE categoria_id = ? AND ativo = 1");
    $stmt->execute([$categoria_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $proxima_ordem = ($resultado['max_ordem'] ?? 0) + 1;
    
    echo json_encode(['proxima_ordem' => $proxima_ordem]);
    
} catch (Exception $e) {
    echo json_encode(['erro' => 'Erro ao buscar próxima ordem: ' . $e->getMessage()]);
}
?>
