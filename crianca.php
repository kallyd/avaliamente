<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

$crianca_id = $_GET['id'] ?? 0;

// Buscar dados da criança
$stmt = $pdo->prepare("SELECT * FROM criancas WHERE id = ? AND ativo = 1");
$stmt->execute([$crianca_id]);
$crianca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$crianca) {
    header('Location: dashboard.php');
    exit();
}

// Verificar permissão
if ($_SESSION['user_type'] === 'avaliador') {
    $stmt = $pdo->prepare("SELECT id FROM avaliador_crianca WHERE avaliador_id = ? AND crianca_id = ?");
    $stmt->execute([$_SESSION['user_id'], $crianca_id]);
    if (!$stmt->fetch()) {
        header('Location: dashboard.php');
        exit();
    }
}

// Buscar histórico de avaliações
$stmt = $pdo->prepare("
    SELECT a.*, u.nome as avaliador_nome,
           (SELECT COUNT(*) FROM respostas WHERE avaliacao_id = a.id) as total_respostas
    FROM avaliacoes a
    JOIN usuarios u ON a.avaliador_id = u.id
    WHERE a.crianca_id = ?
    ORDER BY a.data_avaliacao DESC
");
$stmt->execute([$crianca_id]);
$avaliacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular médias por categoria para cada avaliação
$medias_por_avaliacao = [];
foreach ($avaliacoes as $avaliacao) {
    $stmt = $pdo->prepare("
        SELECT 
            cat.nome_categoria,
            cat.ordem,
            AVG(r.valor) as media,
            COUNT(r.valor) as total_perguntas
        FROM respostas r
        JOIN perguntas p ON r.pergunta_id = p.id
        JOIN categorias cat ON p.categoria_id = cat.id
        WHERE r.avaliacao_id = ?
        GROUP BY cat.id, cat.nome_categoria, cat.ordem
        ORDER BY cat.ordem
    ");
    $stmt->execute([$avaliacao['id']]);
    $medias_por_avaliacao[$avaliacao['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calcular evolução (primeira vs última avaliação)
$evolucao = [];
if (count($avaliacoes) >= 2) {
    $primeira_avaliacao = end($avaliacoes);
    $ultima_avaliacao = $avaliacoes[0];
    
    $primeira_medias = $medias_por_avaliacao[$primeira_avaliacao['id']] ?? [];
    $ultima_medias = $medias_por_avaliacao[$ultima_avaliacao['id']] ?? [];
    
    foreach ($ultima_medias as $categoria) {
        $primeira_media = 0;
        foreach ($primeira_medias as $p) {
            if ($p['nome_categoria'] === $categoria['nome_categoria']) {
                $primeira_media = $p['media'];
                break;
            }
        }
        
        $evolucao[] = [
            'categoria' => $categoria['nome_categoria'],
            'primeira' => $primeira_media,
            'ultima' => $categoria['media'],
            'diferenca' => $categoria['media'] - $primeira_media
        ];
    }
}

// Calcular idade da criança
$data_nascimento = new DateTime($crianca['data_nascimento']);
$hoje = new DateTime();
$idade = $hoje->diff($data_nascimento);
$idade_texto = $idade->y . ' anos, ' . $idade->m . ' meses';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Perfil da Criança</title>
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
        <!-- Informações da Criança -->
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 mb-8">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-child text-3xl text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($crianca['nome']); ?></h2>
                        <p class="text-gray-600"><?php echo $idade_texto; ?> • Responsável: <?php echo htmlspecialchars($crianca['responsavel']); ?></p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <a href="avaliar.php?id=<?php echo $crianca_id; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Nova Avaliação
                    </a>
                </div>
            </div>
            
            <?php if ($crianca['observacoes']): ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-2">Observações Iniciais:</h4>
                    <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($crianca['observacoes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($avaliacoes)): ?>
            <!-- Gráfico de Evolução -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-chart-line mr-2"></i>Evolução por Categoria
                    </h3>
                    <canvas id="evolucaoChart" width="400" height="300"></canvas>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-trending-up mr-2"></i>Comparativo de Evolução
                    </h3>
                    <div class="space-y-4">
                        <?php foreach ($evolucao as $cat): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($cat['categoria']); ?></span>
                                <div class="flex items-center space-x-4">
                                    <span class="text-sm text-gray-600">
                                        <?php echo number_format($cat['primeira'], 2); ?> → <?php echo number_format($cat['ultima'], 2); ?>
                                    </span>
                                    <span class="px-2 py-1 rounded text-sm font-medium <?php 
                                        echo $cat['diferenca'] > 0 ? 'bg-green-100 text-green-800' : 
                                            ($cat['diferenca'] < 0 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800');
                                    ?>">
                                        <?php 
                                        echo $cat['diferenca'] > 0 ? '+' . number_format($cat['diferenca'], 2) : 
                                            number_format($cat['diferenca'], 2);
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Histórico de Avaliações -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-history mr-2"></i>Histórico de Avaliações
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avaliador</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Socialização</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Linguagem</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cognição</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Autocuidados</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($avaliacoes as $avaliacao): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y H:i', strtotime($avaliacao['data_avaliacao'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($avaliacao['avaliador_nome']); ?>
                                    </td>
                                    <?php 
                                    $medias = $medias_por_avaliacao[$avaliacao['id']] ?? [];
                                    $categorias_ordem = ['Socialização', 'Linguagem', 'Cognição', 'Autocuidados', 'Desenvolvimento Motor'];
                                    foreach ($categorias_ordem as $cat_nome):
                                        $media = 0;
                                        foreach ($medias as $m) {
                                            if ($m['nome_categoria'] === $cat_nome) {
                                                $media = $m['media'];
                                                break;
                                            }
                                        }
                                    ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                                echo $media >= 0.7 ? 'bg-green-100 text-green-800' : 
                                                    ($media >= 0.4 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                            ?>">
                                                <?php echo number_format($media, 2); ?>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <a href="detalhes_avaliacao.php?id=<?php echo $avaliacao['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye mr-1"></i>Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Sem avaliações -->
            <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <i class="fas fa-clipboard-list text-3xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Nenhuma Avaliação Realizada</h3>
                <p class="text-gray-600 mb-6">Esta criança ainda não possui avaliações registradas.</p>
                <a href="avaliar.php?id=<?php echo $crianca_id; ?>" 
                   class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Realizar Primeira Avaliação
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Gráfico de evolução
        const ctx = document.getElementById('evolucaoChart').getContext('2d');
        const avaliacoes = <?php echo json_encode($avaliacoes); ?>;
        const mediasPorAvaliacao = <?php echo json_encode($medias_por_avaliacao); ?>;
        
        if (avaliacoes.length > 0) {
            const categorias = ['Socialização', 'Linguagem', 'Cognição', 'Autocuidados', 'Desenvolvimento Motor'];
            const cores = ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444'];
            
            const datasets = [];
            
            // Primeira avaliação
            if (avaliacoes.length > 0) {
                const primeira = avaliacoes[avaliacoes.length - 1];
                const medias = mediasPorAvaliacao[primeira.id] || [];
                const data = categorias.map(cat => {
                    const media = medias.find(m => m.nome_categoria === cat);
                    return media ? parseFloat(media.media) : 0;
                });
                
                datasets.push({
                    label: 'Primeira Avaliação',
                    data: data,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                });
            }
            
            // Última avaliação
            if (avaliacoes.length > 1) {
                const ultima = avaliacoes[0];
                const medias = mediasPorAvaliacao[ultima.id] || [];
                const data = categorias.map(cat => {
                    const media = medias.find(m => m.nome_categoria === cat);
                    return media ? parseFloat(media.media) : 0;
                });
                
                datasets.push({
                    label: 'Última Avaliação',
                    data: data,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.1
                });
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: categorias,
                    datasets: datasets
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
                            display: true
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
