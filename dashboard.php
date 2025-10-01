<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

// Buscar estatísticas
$stats = [];

// Total de avaliadores
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'avaliador' AND ativo = 1");
$stmt->execute();
$stats['avaliadores'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de crianças
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM criancas WHERE ativo = 1");
$stmt->execute();
$stats['criancas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de avaliações
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM avaliacoes");
$stmt->execute();
$stats['avaliacoes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Últimas avaliações
$stmt = $pdo->prepare("
    SELECT a.*, c.nome as crianca_nome, u.nome as avaliador_nome 
    FROM avaliacoes a 
    JOIN criancas c ON a.crianca_id = c.id 
    JOIN usuarios u ON a.avaliador_id = u.id 
    ORDER BY a.data_avaliacao DESC 
    LIMIT 5
");
$stmt->execute();
$ultimas_avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Médias por categoria (últimas avaliações)
$stmt = $pdo->prepare("
    SELECT 
        cat.nome_categoria,
        AVG(r.valor) as media
    FROM avaliacoes a
    JOIN respostas r ON a.id = r.avaliacao_id
    JOIN perguntas p ON r.pergunta_id = p.id
    JOIN categorias cat ON p.categoria_id = cat.id
    WHERE a.data_avaliacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY cat.id, cat.nome_categoria
    ORDER BY cat.ordem
");
$stmt->execute();
$medias_categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se for avaliador, buscar suas crianças
$minhas_criancas = [];
if ($_SESSION['user_type'] === 'avaliador') {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM avaliacoes WHERE crianca_id = c.id AND avaliador_id = ?) as total_avaliacoes
        FROM criancas c
        JOIN avaliador_crianca ac ON c.id = ac.crianca_id
        WHERE ac.avaliador_id = ? AND c.ativo = 1
        ORDER BY c.nome
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $minhas_criancas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Dashboard</title>
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
                    <a href="gerenciar_perguntas.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-question-circle mr-2"></i>Perguntas
                    </a>
                    <span class="text-gray-600">Olá, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-2"></i>Sair
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Avaliadores</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['avaliadores']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-child text-2xl text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Crianças</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['criancas']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-clipboard-check text-2xl text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Avaliações</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['avaliacoes']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Gráfico de Médias por Categoria -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2"></i>Médias por Categoria (30 dias)
                </h3>
                <canvas id="categoriasChart" width="400" height="200"></canvas>
            </div>

            <!-- Últimas Avaliações -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-history mr-2"></i>Últimas Avaliações
                </h3>
                <div class="space-y-3">
                    <?php if (empty($ultimas_avaliacoes)): ?>
                        <p class="text-gray-500 text-center py-4">Nenhuma avaliação encontrada</p>
                    <?php else: ?>
                        <?php foreach ($ultimas_avaliacoes as $avaliacao): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($avaliacao['crianca_nome']); ?></p>
                                    <p class="text-sm text-gray-600">por <?php echo htmlspecialchars($avaliacao['avaliador_nome']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($avaliacao['data_avaliacao'])); ?></p>
                                    <a href="crianca.php?id=<?php echo $avaliacao['crianca_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($_SESSION['user_type'] === 'admin'): ?>
            <!-- Ações do Admin -->
            <div class="mt-8 bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-cogs mr-2"></i>Ações Administrativas
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <a href="todas_criancas.php" class="bg-indigo-600 text-white p-4 rounded-lg hover:bg-indigo-700 transition duration-200 text-center">
                        <i class="fas fa-list text-2xl mb-2"></i>
                        <p class="font-medium">Todas as Crianças</p>
                    </a>
                    <a href="cadastrar_crianca.php" class="bg-blue-600 text-white p-4 rounded-lg hover:bg-blue-700 transition duration-200 text-center">
                        <i class="fas fa-plus text-2xl mb-2"></i>
                        <p class="font-medium">Cadastrar Criança</p>
                    </a>
                    <a href="cadastrar_avaliador.php" class="bg-green-600 text-white p-4 rounded-lg hover:bg-green-700 transition duration-200 text-center">
                        <i class="fas fa-user-plus text-2xl mb-2"></i>
                        <p class="font-medium">Cadastrar Avaliador</p>
                    </a>
                    <a href="gerenciar_perguntas.php" class="bg-orange-600 text-white p-4 rounded-lg hover:bg-orange-700 transition duration-200 text-center">
                        <i class="fas fa-question-circle text-2xl mb-2"></i>
                        <p class="font-medium">Gerenciar Perguntas</p>
                    </a>
                    <a href="relatorios.php" class="bg-purple-600 text-white p-4 rounded-lg hover:bg-purple-700 transition duration-200 text-center">
                        <i class="fas fa-chart-line text-2xl mb-2"></i>
                        <p class="font-medium">Relatórios</p>
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['user_type'] === 'avaliador'): ?>
            <!-- Ações do Avaliador -->
            <div class="mt-8 bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-tools mr-2"></i>Ferramentas
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="todas_criancas.php" class="bg-indigo-600 text-white p-4 rounded-lg hover:bg-indigo-700 transition duration-200 text-center">
                        <i class="fas fa-list text-2xl mb-2"></i>
                        <p class="font-medium">Todas as Crianças</p>
                    </a>
                    <a href="gerenciar_perguntas.php" class="bg-orange-600 text-white p-4 rounded-lg hover:bg-orange-700 transition duration-200 text-center">
                        <i class="fas fa-question-circle text-2xl mb-2"></i>
                        <p class="font-medium">Gerenciar Perguntas</p>
                    </a>
                    <a href="cadastrar_crianca.php" class="bg-blue-600 text-white p-4 rounded-lg hover:bg-blue-700 transition duration-200 text-center">
                        <i class="fas fa-plus text-2xl mb-2"></i>
                        <p class="font-medium">Cadastrar Criança</p>
                    </a>
                </div>
            </div>

            <?php if (!empty($minhas_criancas)): ?>
            <!-- Minhas Crianças -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-child mr-2"></i>Minhas Crianças
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($minhas_criancas as $crianca): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition duration-200">
                            <h4 class="font-medium text-gray-800 mb-2"><?php echo htmlspecialchars($crianca['nome']); ?></h4>
                            <p class="text-sm text-gray-600 mb-2">
                                <?php echo date('d/m/Y', strtotime($crianca['data_nascimento'])); ?>
                            </p>
                            <p class="text-sm text-gray-500 mb-3">
                                <?php echo $crianca['total_avaliacoes']; ?> avaliações
                            </p>
                            <div class="flex space-x-2">
                                <a href="crianca.php?id=<?php echo $crianca['id']; ?>" 
                                   class="flex-1 bg-blue-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-blue-700 transition duration-200">
                                    Ver Perfil
                                </a>
                                <a href="avaliar.php?id=<?php echo $crianca['id']; ?>" 
                                   class="flex-1 bg-green-600 text-white text-center py-2 px-3 rounded text-sm hover:bg-green-700 transition duration-200">
                                    Avaliar
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Gráfico de categorias
        const ctx = document.getElementById('categoriasChart').getContext('2d');
        const categoriasData = <?php echo json_encode($medias_categorias); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: categoriasData.map(item => item.nome_categoria),
                datasets: [{
                    label: 'Média (0-1)',
                    data: categoriasData.map(item => parseFloat(item.media).toFixed(2)),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(139, 92, 246, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
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
