<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();

$database = new Database();
$pdo = $database->getConnection();

$sucesso = '';
$erro = '';

if ($_POST) {
    $nome = trim($_POST['nome'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $responsavel = trim($_POST['responsavel'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');
    
    if (empty($nome) || empty($data_nascimento) || empty($responsavel)) {
        $erro = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO criancas (nome, data_nascimento, responsavel, observacoes, criado_por) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $data_nascimento, $responsavel, $observacoes, $_SESSION['user_id']]);
            
            $crianca_id = $pdo->lastInsertId();
            
            // Se for admin, associar a todos os avaliadores
            if ($_SESSION['user_type'] === 'admin') {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE tipo = 'avaliador' AND ativo = 1");
                $stmt->execute();
                $avaliadores = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($avaliadores as $avaliador_id) {
                    $stmt = $pdo->prepare("INSERT INTO avaliador_crianca (avaliador_id, crianca_id) VALUES (?, ?)");
                    $stmt->execute([$avaliador_id, $crianca_id]);
                }
            } else {
                // Se for avaliador, associar apenas a ele
                $stmt = $pdo->prepare("INSERT INTO avaliador_crianca (avaliador_id, crianca_id) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $crianca_id]);
            }
            
            $sucesso = 'Criança cadastrada com sucesso!';
            
            // Limpar formulário
            $_POST = [];
            
        } catch (Exception $e) {
            $erro = 'Erro ao cadastrar criança: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Cadastrar Criança</title>
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

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                    <i class="fas fa-child text-3xl text-blue-600"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Cadastrar Nova Criança</h2>
                <p class="text-gray-600">Preencha os dados da criança para começar o acompanhamento</p>
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
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Nome Completo *
                    </label>
                    <input type="text" id="nome" name="nome" required
                           value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                           placeholder="Digite o nome completo da criança">
                </div>

                <div>
                    <label for="data_nascimento" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-calendar mr-2"></i>Data de Nascimento *
                    </label>
                    <input type="date" id="data_nascimento" name="data_nascimento" required
                           value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200">
                </div>

                <div>
                    <label for="responsavel" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-friends mr-2"></i>Responsável *
                    </label>
                    <input type="text" id="responsavel" name="responsavel" required
                           value="<?php echo htmlspecialchars($_POST['responsavel'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                           placeholder="Nome do responsável legal">
                </div>

                <div>
                    <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-sticky-note mr-2"></i>Observações Iniciais
                    </label>
                    <textarea id="observacoes" name="observacoes" rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                              placeholder="Observações importantes sobre a criança (opcional)"><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" 
                            class="flex-1 bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200 font-medium">
                        <i class="fas fa-save mr-2"></i>Cadastrar Criança
                    </button>
                    <a href="dashboard.php" 
                       class="flex-1 bg-gray-500 text-white py-3 px-4 rounded-lg hover:bg-gray-600 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-200 font-medium text-center">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
