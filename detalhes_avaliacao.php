<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

$avaliacao_id = $_GET['id'] ?? 0;

// Buscar dados da avaliação
$stmt = $pdo->prepare("
    SELECT a.*, c.nome as crianca_nome, c.data_nascimento, u.nome as avaliador_nome
    FROM avaliacoes a
    JOIN criancas c ON a.crianca_id = c.id
    JOIN usuarios u ON a.avaliador_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$avaliacao_id]);
$avaliacao = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$avaliacao) {
    header('Location: dashboard.php');
    exit();
}

// Verificar permissão
if ($_SESSION['user_type'] === 'avaliador') {
    $stmt = $pdo->prepare("SELECT id FROM avaliador_crianca WHERE avaliador_id = ? AND crianca_id = ?");
    $stmt->execute([$_SESSION['user_id'], $avaliacao['crianca_id']]);
    if (!$stmt->fetch()) {
        header('Location: dashboard.php');
        exit();
    }
}

// Buscar respostas detalhadas
$stmt = $pdo->prepare("
    SELECT 
        r.valor,
        p.texto_pergunta,
        cat.nome_categoria,
        cat.ordem
    FROM respostas r
    JOIN perguntas p ON r.pergunta_id = p.id
    JOIN categorias cat ON p.categoria_id = cat.id
    WHERE r.avaliacao_id = ?
    ORDER BY cat.ordem, p.ordem
");
$stmt->execute([$avaliacao_id]);
$respostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar respostas por categoria
$respostas_por_categoria = [];
foreach ($respostas as $resposta) {
    $categoria = $resposta['nome_categoria'];
    if (!isset($respostas_por_categoria[$categoria])) {
        $respostas_por_categoria[$categoria] = [];
    }
    $respostas_por_categoria[$categoria][] = $resposta;
}

// Calcular médias por categoria
$medias_categorias = [];
foreach ($respostas_por_categoria as $categoria => $respostas_cat) {
    $soma = array_sum(array_column($respostas_cat, 'valor'));
    $total = count($respostas_cat);
    $medias_categorias[$categoria] = $total > 0 ? $soma / $total : 0;
}

// Calcular média geral
$media_geral = count($medias_categorias) > 0 ? array_sum($medias_categorias) / count($medias_categorias) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Detalhes da Avaliação</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a href="crianca.php?id=<?php echo $avaliacao['crianca_id']; ?>" class="text-gray-600 hover:text-gray-800">
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
        <!-- Cabeçalho da Avaliação -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Detalhes da Avaliação</h2>
                    <p class="text-gray-600">Criança: <strong><?php echo htmlspecialchars($avaliacao['crianca_nome']); ?></strong></p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Realizada em:</p>
                    <p class="font-medium"><?php echo date('d/m/Y H:i', strtotime($avaliacao['data_avaliacao'])); ?></p>
                    <p class="text-sm text-gray-500">por <?php echo htmlspecialchars($avaliacao['avaliador_nome']); ?></p>
                </div>
            </div>
            
            <?php if ($avaliacao['observacoes']): ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-2">Observações:</h4>
                    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($avaliacao['observacoes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Gráfico de Médias por Categoria -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2"></i>Médias por Categoria
                </h3>
                <canvas id="categoriasChart" width="400" height="300"></canvas>
            </div>

            <!-- Resumo das Médias -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-list mr-2"></i>Resumo das Médias
                </h3>
                <div class="space-y-3">
                    <?php foreach ($medias_categorias as $categoria => $media): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($categoria); ?></span>
                            <div class="flex items-center space-x-3">
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $media * 100; ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700 w-12 text-right">
                                    <?php echo number_format($media, 2); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="border-t pt-3 mt-4">
                        <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                            <span class="font-bold text-gray-800">Média Geral</span>
                            <div class="flex items-center space-x-3">
                                <div class="w-24 bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $media_geral * 100; ?>%"></div>
                                </div>
                                <span class="text-lg font-bold text-blue-700">
                                    <?php echo number_format($media_geral, 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Respostas Detalhadas por Categoria -->
        <div class="space-y-6">
            <?php foreach ($respostas_por_categoria as $categoria => $respostas_cat): ?>
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-<?php 
                                $icons = ['users', 'comments', 'brain', 'user-check', 'running'];
                                $ordem = array_search($categoria, array_keys($respostas_por_categoria));
                                echo $icons[$ordem] ?? 'question';
                            ?> mr-3 text-blue-600"></i>
                            <?php echo htmlspecialchars($categoria); ?>
                        </h3>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            Média: <?php echo number_format($medias_categorias[$categoria], 2); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-3">
                        <?php foreach ($respostas_cat as $resposta): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <p class="text-gray-800 flex-1"><?php echo htmlspecialchars($resposta['texto_pergunta']); ?></p>
                                <div class="flex items-center space-x-2 ml-4">
                                    <?php 
                                    $valor = $resposta['valor'];
                                    $icone = $valor == 1 ? 'check-circle' : ($valor == 0.5 ? 'question-circle' : 'times-circle');
                                    $cor = $valor == 1 ? 'text-green-600' : ($valor == 0.5 ? 'text-yellow-600' : 'text-red-600');
                                    $texto = $valor == 1 ? 'Sim' : ($valor == 0.5 ? 'Talvez' : 'Não');
                                    ?>
                                    <i class="fas fa-<?php echo $icone; ?> <?php echo $cor; ?>"></i>
                                    <span class="font-medium <?php echo $cor; ?>"><?php echo $texto; ?></span>
                                    <span class="text-sm text-gray-500">(<?php echo $valor; ?>)</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Gráfico de categorias
        const ctx = document.getElementById('categoriasChart').getContext('2d');
        const medias = <?php echo json_encode($medias_categorias); ?>;
        
        const categorias = Object.keys(medias);
        const valores = Object.values(medias);
        const cores = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444'];
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: categorias,
                datasets: [{
                    label: 'Média (0-1)',
                    data: valores,
                    backgroundColor: cores.map(cor => cor + '80'),
                    borderColor: cores,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            stepSize: 0.2
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>
