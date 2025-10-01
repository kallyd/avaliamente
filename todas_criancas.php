<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

// Filtros
$busca = $_GET['busca'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';
$avaliador_filtro = $_GET['avaliador'] ?? '';

// Construir query com filtros
$where_conditions = ["c.ativo = 1"];
$params = [];

if ($busca) {
    $where_conditions[] = "(c.nome LIKE ? OR c.responsavel LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($categoria_filtro) {
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM avaliacoes a 
        JOIN respostas r ON a.id = r.avaliacao_id 
        JOIN perguntas p ON r.pergunta_id = p.id 
        JOIN categorias cat ON p.categoria_id = cat.id 
        WHERE a.crianca_id = c.id AND cat.id = ?
    )";
    $params[] = $categoria_filtro;
}

if ($avaliador_filtro) {
    $where_conditions[] = "EXISTS (
        SELECT 1 FROM avaliador_crianca ac 
        WHERE ac.crianca_id = c.id AND ac.avaliador_id = ?
    )";
    $params[] = $avaliador_filtro;
}

$where_clause = implode(' AND ', $where_conditions);

// Buscar crianças
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        (SELECT COUNT(*) FROM avaliacoes WHERE crianca_id = c.id) as total_avaliacoes,
        (SELECT MAX(data_avaliacao) FROM avaliacoes WHERE crianca_id = c.id) as ultima_avaliacao,
        (SELECT GROUP_CONCAT(u.nome SEPARATOR ', ') 
         FROM avaliador_crianca ac 
         JOIN usuarios u ON ac.avaliador_id = u.id 
         WHERE ac.crianca_id = c.id) as avaliadores
    FROM criancas c
    WHERE $where_clause
    ORDER BY c.nome
");
$stmt->execute($params);
$criancas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar categorias para filtro
$stmt = $pdo->prepare("SELECT * FROM categorias ORDER BY ordem");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar avaliadores para filtro
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE tipo = 'avaliador' AND ativo = 1 ORDER BY nome");
$stmt->execute();
$avaliadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular idade das crianças
foreach ($criancas as &$crianca) {
    $data_nascimento = new DateTime($crianca['data_nascimento']);
    $hoje = new DateTime();
    $idade = $hoje->diff($data_nascimento);
    $crianca['idade'] = $idade->y . ' anos, ' . $idade->m . ' meses';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Todas as Crianças</title>
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
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Todas as Crianças</h2>
                    <p class="text-gray-600">Visualize e gerencie todas as crianças cadastradas no sistema</p>
                </div>
                <div class="flex space-x-3">
                    <a href="cadastrar_crianca.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Nova Criança
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="busca" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input type="text" id="busca" name="busca" value="<?php echo htmlspecialchars($busca); ?>"
                           placeholder="Nome ou responsável..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label for="categoria" class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                    <select id="categoria" name="categoria" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo $categoria_filtro == $categoria['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nome_categoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="avaliador" class="block text-sm font-medium text-gray-700 mb-1">Avaliador</label>
                    <select id="avaliador" name="avaliador" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Todos os avaliadores</option>
                        <?php foreach ($avaliadores as $avaliador): ?>
                            <option value="<?php echo $avaliador['id']; ?>" 
                                    <?php echo $avaliador_filtro == $avaliador['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($avaliador['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="todas_criancas.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition duration-200">
                        <i class="fas fa-times mr-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de Crianças -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-list mr-2"></i>Lista de Crianças
                    </h3>
                    <span class="text-sm text-gray-500">
                        <?php echo count($criancas); ?> criança(s) encontrada(s)
                    </span>
                </div>
            </div>

            <?php if (empty($criancas)): ?>
                <div class="p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <i class="fas fa-child text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Nenhuma criança encontrada</h3>
                    <p class="text-gray-600 mb-4">
                        <?php if ($busca || $categoria_filtro || $avaliador_filtro): ?>
                            Tente ajustar os filtros de busca ou 
                            <a href="todas_criancas.php" class="text-blue-600 hover:text-blue-800">limpar os filtros</a>.
                        <?php else: ?>
                            Ainda não há crianças cadastradas no sistema.
                        <?php endif; ?>
                    </p>
                    <a href="cadastrar_crianca.php" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Cadastrar Primeira Criança
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Criança</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Idade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsável</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avaliadores</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avaliações</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Última Avaliação</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($criancas as $crianca): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <i class="fas fa-child text-blue-600"></i>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($crianca['nome']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    ID: <?php echo $crianca['id']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $crianca['idade']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($crianca['responsavel']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($crianca['avaliadores']): ?>
                                            <span class="text-blue-600"><?php echo htmlspecialchars($crianca['avaliadores']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                                            <?php echo $crianca['total_avaliacoes']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($crianca['ultima_avaliacao']): ?>
                                            <?php echo date('d/m/Y', strtotime($crianca['ultima_avaliacao'])); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">Nunca</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="crianca.php?id=<?php echo $crianca['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-eye mr-1"></i>Ver
                                            </a>
                                            <a href="avaliar.php?id=<?php echo $crianca['id']; ?>" 
                                               class="text-green-600 hover:text-green-800">
                                                <i class="fas fa-plus mr-1"></i>Avaliar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
