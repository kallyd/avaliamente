<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

$sucesso = '';
$erro = '';

// Processar ações
$acao = $_GET['acao'] ?? '';
$pergunta_id = $_GET['id'] ?? 0;

if ($acao === 'excluir' && $pergunta_id) {
    try {
        // Verificar se a pergunta tem respostas
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM respostas WHERE pergunta_id = ?");
        $stmt->execute([$pergunta_id]);
        $tem_respostas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        if ($tem_respostas) {
            $erro = 'Não é possível excluir esta pergunta pois ela já possui respostas associadas.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM perguntas WHERE id = ?");
            $stmt->execute([$pergunta_id]);
            $sucesso = 'Pergunta excluída com sucesso!';
        }
    } catch (Exception $e) {
        $erro = 'Erro ao excluir pergunta: ' . $e->getMessage();
    }
}

// Buscar categorias e perguntas
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM perguntas WHERE categoria_id = c.id AND ativo = 1) as total_perguntas
    FROM categorias c 
    ORDER BY c.ordem
");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$perguntas_por_categoria = [];
foreach ($categorias as $categoria) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome_categoria 
        FROM perguntas p
        JOIN categorias c ON p.categoria_id = c.id
        WHERE p.categoria_id = ? AND p.ativo = 1
        ORDER BY p.ordem
    ");
    $stmt->execute([$categoria['id']]);
    $perguntas_por_categoria[$categoria['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Gerenciar Perguntas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <i class="fas fa-brain text-2xl text-blue-600 mr-3"></i>
                    <h1 class="text-2xl font-bold text-gray-800">AvaliaMente</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                    <span class="text-gray-600"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Cabeçalho -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Gerenciar Perguntas</h2>
                    <p class="text-gray-600">Adicione, edite ou remova perguntas das categorias de avaliação</p>
                </div>
                <a href="adicionar_pergunta.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Nova Pergunta
                </a>
            </div>
        </div>

        <?php if ($sucesso): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <!-- Lista de Categorias e Perguntas -->
        <div class="space-y-6">
            <?php foreach ($categorias as $categoria): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-<?php 
                                    $icons = ['users', 'comments', 'brain', 'user-check', 'running'];
                                    echo $icons[$categoria['ordem'] - 1] ?? 'question';
                                ?> mr-3 text-blue-600"></i>
                                <?php echo htmlspecialchars($categoria['nome_categoria']); ?>
                            </h3>
                            <div class="flex items-center space-x-4">
                                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                    <?php echo $categoria['total_perguntas']; ?> perguntas
                                </span>
                                <a href="adicionar_pergunta.php?categoria_id=<?php echo $categoria['id']; ?>" 
                                   class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition duration-200">
                                    <i class="fas fa-plus mr-1"></i>Adicionar
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($perguntas_por_categoria[$categoria['id']])): ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-question-circle text-4xl mb-4"></i>
                                <p>Nenhuma pergunta cadastrada nesta categoria</p>
                                <a href="adicionar_pergunta.php?categoria_id=<?php echo $categoria['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
                                    Adicionar primeira pergunta
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($perguntas_por_categoria[$categoria['id']] as $pergunta): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                        <div class="flex-1">
                                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($pergunta['texto_pergunta']); ?></p>
                                            <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                                <span>Ordem: <?php echo $pergunta['ordem']; ?></span>
                                                <span>ID: <?php echo $pergunta['id']; ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 ml-4">
                                            <a href="editar_pergunta.php?id=<?php echo $pergunta['id']; ?>" 
                                               class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600 transition duration-200">
                                                <i class="fas fa-edit mr-1"></i>Editar
                                            </a>
                                            <a href="gerenciar_perguntas.php?acao=excluir&id=<?php echo $pergunta['id']; ?>" 
                                               onclick="return confirm('Tem certeza que deseja excluir esta pergunta?')"
                                               class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 transition duration-200">
                                                <i class="fas fa-trash mr-1"></i>Excluir
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
