<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

$crianca_id = $_GET['id'] ?? 0;
$erro = '';
$sucesso = '';

// Buscar dados da criança
$stmt = $pdo->prepare("SELECT * FROM criancas WHERE id = ? AND ativo = 1");
$stmt->execute([$crianca_id]);
$crianca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$crianca) {
    $erro = 'Criança não encontrada.';
} else {
    // Verificar se o usuário tem permissão para avaliar esta criança
    if ($_SESSION['user_type'] === 'avaliador') {
        $stmt = $pdo->prepare("SELECT id FROM avaliador_crianca WHERE avaliador_id = ? AND crianca_id = ?");
        $stmt->execute([$_SESSION['user_id'], $crianca_id]);
        if (!$stmt->fetch()) {
            $erro = 'Você não tem permissão para avaliar esta criança.';
        }
    }
}

// Buscar formulário e categorias
$stmt = $pdo->prepare("SELECT * FROM formularios WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$formulario = $stmt->fetch(PDO::FETCH_ASSOC);

$categorias = [];
if ($formulario) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM perguntas WHERE categoria_id = c.id AND ativo = 1) as total_perguntas
        FROM categorias c 
        WHERE c.formulario_id = ? 
        ORDER BY c.ordem
    ");
    $stmt->execute([$formulario['id']]);
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Processar envio do formulário
if ($_POST && !$erro) {
    try {
        $pdo->beginTransaction();
        
        // Criar avaliação
        $stmt = $pdo->prepare("
            INSERT INTO avaliacoes (crianca_id, avaliador_id, formulario_id, observacoes) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $crianca_id, 
            $_SESSION['user_id'], 
            $formulario['id'], 
            $_POST['observacoes'] ?? ''
        ]);
        
        $avaliacao_id = $pdo->lastInsertId();
        
        // Salvar respostas
        foreach ($_POST['respostas'] as $pergunta_id => $valor) {
            if ($valor !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO respostas (avaliacao_id, pergunta_id, valor) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$avaliacao_id, $pergunta_id, $valor]);
            }
        }
        
        $pdo->commit();
        $sucesso = 'Avaliação realizada com sucesso!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $erro = 'Erro ao salvar avaliação: ' . $e->getMessage();
    }
}

// Buscar perguntas para cada categoria
$perguntas_por_categoria = [];
foreach ($categorias as $categoria) {
    $stmt = $pdo->prepare("
        SELECT * FROM perguntas 
        WHERE categoria_id = ? AND ativo = 1 
        ORDER BY ordem
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
    <title>AvaliaMente - Avaliar Criança</title>
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

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($erro): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
            <div class="text-center">
                <a href="dashboard.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
            </div>
        <?php elseif ($sucesso): ?>
            <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-check text-3xl text-green-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Avaliação Concluída!</h2>
                <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($sucesso); ?></p>
                <div class="flex space-x-4 justify-center">
                    <a href="crianca.php?id=<?php echo $crianca_id; ?>" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-eye mr-2"></i>Ver Perfil da Criança
                    </a>
                    <a href="dashboard.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                        <i class="fas fa-home mr-2"></i>Dashboard
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">Avaliação de Desenvolvimento</h2>
                    <p class="text-gray-600">Criança: <strong><?php echo htmlspecialchars($crianca['nome']); ?></strong></p>
                    <p class="text-sm text-gray-500">Nascida em: <?php echo date('d/m/Y', strtotime($crianca['data_nascimento'])); ?></p>
                </div>

                <form method="POST" id="avaliacaoForm">
                    <div class="space-y-8">
                        <?php foreach ($categorias as $categoria): ?>
                            <div class="border border-gray-200 rounded-lg p-6">
                                <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-<?php 
                                        $icons = ['users', 'comments', 'brain', 'user-check', 'running'];
                                        echo $icons[$categoria['ordem'] - 1] ?? 'question';
                                    ?> mr-3 text-blue-600"></i>
                                    <?php echo htmlspecialchars($categoria['nome_categoria']); ?>
                                </h3>
                                
                                <div class="space-y-4">
                                    <?php foreach ($perguntas_por_categoria[$categoria['id']] as $pergunta): ?>
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <p class="text-gray-800 mb-3 font-medium"><?php echo htmlspecialchars($pergunta['texto_pergunta']); ?></p>
                                            <div class="flex space-x-6">
                                                <label class="flex items-center">
                                                    <input type="radio" name="respostas[<?php echo $pergunta['id']; ?>]" value="0" required
                                                           class="mr-2 text-red-600 focus:ring-red-500">
                                                    <span class="text-red-600 font-medium">❌ Não (0)</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="radio" name="respostas[<?php echo $pergunta['id']; ?>]" value="0.5" required
                                                           class="mr-2 text-yellow-600 focus:ring-yellow-500">
                                                    <span class="text-yellow-600 font-medium">❔ Talvez (0.5)</span>
                                                </label>
                                                <label class="flex items-center">
                                                    <input type="radio" name="respostas[<?php echo $pergunta['id']; ?>]" value="1" required
                                                           class="mr-2 text-green-600 focus:ring-green-500">
                                                    <span class="text-green-600 font-medium">✅ Sim (1)</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-8">
                        <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-sticky-note mr-2"></i>Observações da Avaliação
                        </label>
                        <textarea id="observacoes" name="observacoes" rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                                  placeholder="Observações importantes sobre esta avaliação (opcional)"></textarea>
                    </div>

                    <div class="mt-8 flex space-x-4">
                        <button type="submit" 
                                class="flex-1 bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-200 font-medium">
                            <i class="fas fa-check mr-2"></i>Finalizar Avaliação
                        </button>
                        <a href="crianca.php?id=<?php echo $crianca_id; ?>" 
                           class="flex-1 bg-gray-500 text-white py-3 px-4 rounded-lg hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 font-medium text-center">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Validação do formulário
        document.getElementById('avaliacaoForm').addEventListener('submit', function(e) {
            const categorias = document.querySelectorAll('[class*="border-gray-200"]');
            let todasRespondidas = true;
            
            categorias.forEach(categoria => {
                const perguntas = categoria.querySelectorAll('input[type="radio"]');
                const grupos = new Set();
                
                perguntas.forEach(radio => {
                    grupos.add(radio.name);
                });
                
                grupos.forEach(nomeGrupo => {
                    const grupoRadios = categoria.querySelectorAll(`input[name="${nomeGrupo}"]`);
                    const algumSelecionado = Array.from(grupoRadios).some(radio => radio.checked);
                    
                    if (!algumSelecionado) {
                        todasRespondidas = false;
                    }
                });
            });
            
            if (!todasRespondidas) {
                e.preventDefault();
                alert('Por favor, responda todas as perguntas antes de finalizar a avaliação.');
            }
        });
    </script>
</body>
</html>
