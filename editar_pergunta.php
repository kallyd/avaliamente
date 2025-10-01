<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

$sucesso = '';
$erro = '';

$pergunta_id = $_GET['id'] ?? 0;

// Buscar dados da pergunta
$stmt = $pdo->prepare("
    SELECT p.*, c.nome_categoria 
    FROM perguntas p
    JOIN categorias c ON p.categoria_id = c.id
    WHERE p.id = ? AND p.ativo = 1
");
$stmt->execute([$pergunta_id]);
$pergunta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pergunta) {
    header('Location: gerenciar_perguntas.php');
    exit();
}

// Buscar categorias
$stmt = $pdo->prepare("SELECT * FROM categorias ORDER BY ordem");
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_POST) {
    $categoria_id = $_POST['categoria_id'] ?? '';
    $texto_pergunta = trim($_POST['texto_pergunta'] ?? '');
    $ordem = $_POST['ordem'] ?? 1;
    
    if (empty($categoria_id) || empty($texto_pergunta)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
        try {
            // Verificar se já existe pergunta com a mesma ordem na categoria (exceto a atual)
            $stmt = $pdo->prepare("SELECT id FROM perguntas WHERE categoria_id = ? AND ordem = ? AND ativo = 1 AND id != ?");
            $stmt->execute([$categoria_id, $ordem, $pergunta_id]);
            if ($stmt->fetch()) {
                $erro = 'Já existe uma pergunta com esta ordem nesta categoria.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE perguntas 
                    SET categoria_id = ?, texto_pergunta = ?, ordem = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$categoria_id, $texto_pergunta, $ordem, $pergunta_id]);
                
                $sucesso = 'Pergunta atualizada com sucesso!';
                
                // Atualizar dados da pergunta
                $pergunta['categoria_id'] = $categoria_id;
                $pergunta['texto_pergunta'] = $texto_pergunta;
                $pergunta['ordem'] = $ordem;
            }
        } catch (Exception $e) {
            $erro = 'Erro ao atualizar pergunta: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Editar Pergunta</title>
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
                    <a href="gerenciar_perguntas.php" class="text-gray-600 hover:text-gray-800">
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

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 rounded-full mb-4">
                    <i class="fas fa-edit text-3xl text-yellow-600"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Editar Pergunta</h2>
                <p class="text-gray-600">Modifique os dados da pergunta selecionada</p>
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

            <form method="POST" class="space-y-6">
                <div>
                    <label for="categoria_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tags mr-2"></i>Categoria *
                    </label>
                    <select id="categoria_id" name="categoria_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                        <option value="">Selecione uma categoria</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo $categoria['id']; ?>" 
                                    <?php echo ($pergunta['categoria_id'] == $categoria['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nome_categoria']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="texto_pergunta" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-question-circle mr-2"></i>Texto da Pergunta *
                    </label>
                    <textarea id="texto_pergunta" name="texto_pergunta" required rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                              placeholder="Digite a pergunta que será apresentada durante a avaliação"><?php echo htmlspecialchars($pergunta['texto_pergunta']); ?></textarea>
                </div>

                <div>
                    <label for="ordem" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sort-numeric-up mr-2"></i>Ordem de Exibição *
                    </label>
                    <input type="number" id="ordem" name="ordem" required min="1"
                           value="<?php echo htmlspecialchars($pergunta['ordem']); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                    <p class="text-sm text-gray-500 mt-1">
                        A ordem determina a sequência de exibição das perguntas na categoria
                    </p>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-yellow-800">Atenção:</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                Alterar uma pergunta existente pode afetar avaliações já realizadas. 
                                Considere criar uma nova pergunta em vez de editar esta.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" 
                            class="flex-1 bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium">
                        <i class="fas fa-save mr-2"></i>Salvar Alterações
                    </button>
                    <a href="gerenciar_perguntas.php" 
                       class="flex-1 bg-gray-500 text-white py-3 px-4 rounded-lg hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 font-medium text-center">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
