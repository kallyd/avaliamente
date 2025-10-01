<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarAdmin();

$database = new Database();
$pdo = $database->getConnection();

// Filtros
$filtro_periodo = $_GET['periodo'] ?? '30';
$filtro_avaliador = $_GET['avaliador'] ?? '';

// Buscar avaliadores para filtro
$stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE tipo = 'avaliador' AND ativo = 1 ORDER BY nome");
$stmt->execute();
$avaliadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas gerais
$stats = [];

// Total de crianças
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM criancas WHERE ativo = 1");
$stmt->execute();
$stats['criancas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de avaliadores
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'avaliador' AND ativo = 1");
$stmt->execute();
$stats['avaliadores'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de avaliações
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM avaliacoes");
$stmt->execute();
$stats['avaliacoes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Avaliações no período
$where_periodo = "WHERE a.data_avaliacao >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params_periodo = [$filtro_periodo];

if ($filtro_avaliador) {
    $where_periodo .= " AND a.avaliador_id = ?";
    $params_periodo[] = $filtro_avaliador;
}

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM avaliacoes a $where_periodo");
$stmt->execute($params_periodo);
$stats['avaliacoes_periodo'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Médias por categoria no período
$stmt = $pdo->prepare("
    SELECT 
        cat.nome_categoria,
        cat.ordem,
        AVG(r.valor) as media,
        COUNT(DISTINCT a.id) as total_avaliacoes
    FROM avaliacoes a
    JOIN respostas r ON a.id = r.avaliacao_id
    JOIN perguntas p ON r.pergunta_id = p.id
    JOIN categorias cat ON p.categoria_id = cat.id
    $where_periodo
    GROUP BY cat.id, cat.nome_categoria, cat.ordem
    ORDER BY cat.ordem
");
$stmt->execute($params_periodo);
$medias_categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top 5 crianças com mais avaliações
$stmt = $pdo->prepare("
    SELECT 
        c.nome,
        c.data_nascimento,
        COUNT(a.id) as total_avaliacoes,
        MAX(a.data_avaliacao) as ultima_avaliacao
    FROM criancas c
    LEFT JOIN avaliacoes a ON c.id = a.crianca_id
    WHERE c.ativo = 1
    GROUP BY c.id, c.nome, c.data_nascimento
    ORDER BY total_avaliacoes DESC, ultima_avaliacao DESC
    LIMIT 5
");
$stmt->execute();
$top_criancas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Avaliações por avaliador
$stmt = $pdo->prepare("
    SELECT 
        u.nome as avaliador_nome,
        COUNT(a.id) as total_avaliacoes,
        MAX(a.data_avaliacao) as ultima_avaliacao
    FROM usuarios u
    LEFT JOIN avaliacoes a ON u.id = a.avaliador_id
    WHERE u.tipo = 'avaliador' AND u.ativo = 1
    GROUP BY u.id, u.nome
    ORDER BY total_avaliacoes DESC
");
$stmt->execute();
$avaliacoes_por_avaliador = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Evolução mensal (últimos 6 meses)
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(a.data_avaliacao, '%Y-%m') as mes,
        COUNT(a.id) as total_avaliacoes,
        AVG(r.valor) as media_geral
    FROM avaliacoes a
    JOIN respostas r ON a.id = r.avaliacao_id
    WHERE a.data_avaliacao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(a.data_avaliacao, '%Y-%m')
    ORDER BY mes
");
$stmt->execute();
$evolucao_mensal = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Relatórios</title>
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
        <!-- Filtros -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>Filtros
            </h3>
            <form method="GET" class="flex flex-wrap gap-4">
                <div>
                    <label for="periodo" class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                    <select name="periodo" id="periodo" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="7" <?php echo $filtro_periodo == '7' ? 'selected' : ''; ?>>Últimos 7 dias</option>
                        <option value="30" <?php echo $filtro_periodo == '30' ? 'selected' : ''; ?>>Últimos 30 dias</option>
                        <option value="90" <?php echo $filtro_periodo == '90' ? 'selected' : ''; ?>>Últimos 90 dias</option>
                        <option value="365" <?php echo $filtro_periodo == '365' ? 'selected' : ''; ?>>Último ano</option>
                    </select>
                </div>
                <div>
                    <label for="avaliador" class="block text-sm font-medium text-gray-700 mb-1">Avaliador</label>
                    <select name="avaliador" id="avaliador" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Todos os avaliadores</option>
                        <?php foreach ($avaliadores as $avaliador): ?>
                            <option value="<?php echo $avaliador['id']; ?>" <?php echo $filtro_avaliador == $avaliador['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($avaliador['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-child text-2xl text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total de Crianças</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['criancas']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-users text-2xl text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Avaliadores</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['avaliadores']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-clipboard-check text-2xl text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total de Avaliações</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['avaliacoes']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <div class="flex items-center">
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <i class="fas fa-calendar text-2xl text-orange-600"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">No Período</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $stats['avaliacoes_periodo']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Gráfico de Médias por Categoria -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-bar mr-2"></i>Médias por Categoria
                </h3>
                <canvas id="categoriasChart" width="400" height="300"></canvas>
            </div>

            <!-- Evolução Mensal -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-line mr-2"></i>Evolução Mensal
                </h3>
                <canvas id="evolucaoChart" width="400" height="300"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Top 5 Crianças -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-trophy mr-2"></i>Top 5 Crianças (Mais Avaliações)
                </h3>
                <div class="space-y-3">
                    <?php foreach ($top_criancas as $index => $crianca): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-sm font-bold mr-3">
                                    <?php echo $index + 1; ?>
                                </span>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($crianca['nome']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('d/m/Y', strtotime($crianca['data_nascimento'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-blue-600"><?php echo $crianca['total_avaliacoes']; ?> avaliações</p>
                                <?php if ($crianca['ultima_avaliacao']): ?>
                                    <p class="text-xs text-gray-500">
                                        Última: <?php echo date('d/m/Y', strtotime($crianca['ultima_avaliacao'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Avaliações por Avaliador -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user-check mr-2"></i>Avaliações por Avaliador
                </h3>
                <div class="space-y-3">
                    <?php foreach ($avaliacoes_por_avaliador as $avaliador): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($avaliador['avaliador_nome']); ?></p>
                                <?php if ($avaliador['ultima_avaliacao']): ?>
                                    <p class="text-sm text-gray-600">
                                        Última: <?php echo date('d/m/Y', strtotime($avaliador['ultima_avaliacao'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600"><?php echo $avaliador['total_avaliacoes']; ?> avaliações</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gráfico de categorias
        const ctx1 = document.getElementById('categoriasChart').getContext('2d');
        const categoriasData = <?php echo json_encode($medias_categorias); ?>;
        
        new Chart(ctx1, {
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

        // Gráfico de evolução mensal
        const ctx2 = document.getElementById('evolucaoChart').getContext('2d');
        const evolucaoData = <?php echo json_encode($evolucao_mensal); ?>;
        
        new Chart(ctx2, {
            type: 'line',
            data: {
                labels: evolucaoData.map(item => {
                    const [ano, mes] = item.mes.split('-');
                    const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                    return `${meses[parseInt(mes) - 1]}/${ano}`;
                }),
                datasets: [{
                    label: 'Avaliações',
                    data: evolucaoData.map(item => item.total_avaliacoes),
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y'
                }, {
                    label: 'Média Geral',
                    data: evolucaoData.map(item => parseFloat(item.media_geral).toFixed(2)),
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        max: 1,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html>
