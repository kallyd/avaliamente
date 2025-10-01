<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarAdmin();

$database = new Database();
$pdo = $database->getConnection();

$sucesso = '';
$erro = '';

if ($_POST) {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erro = 'Este email já está cadastrado.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nome, email, senha, tipo) 
                    VALUES (?, ?, ?, 'avaliador')
                ");
                $stmt->execute([$nome, $email, hashSenha($senha)]);
                
                $avaliador_id = $pdo->lastInsertId();
                
                // Associar todas as crianças existentes ao novo avaliador
                $stmt = $pdo->prepare("SELECT id FROM criancas WHERE ativo = 1");
                $stmt->execute();
                $criancas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($criancas as $crianca_id) {
                    $stmt = $pdo->prepare("INSERT INTO avaliador_crianca (avaliador_id, crianca_id) VALUES (?, ?)");
                    $stmt->execute([$avaliador_id, $crianca_id]);
                }
                
                $sucesso = 'Avaliador cadastrado com sucesso!';
                
                // Limpar formulário
                $_POST = [];
            }
        } catch (Exception $e) {
            $erro = 'Erro ao cadastrar avaliador: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Cadastrar Avaliador</title>
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
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <i class="fas fa-user-plus text-3xl text-green-600"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Cadastrar Novo Avaliador</h2>
                <p class="text-gray-600">Crie uma conta para um novo profissional avaliador</p>
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
                           placeholder="Digite o nome completo do avaliador">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-2"></i>Email *
                    </label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                           placeholder="email@exemplo.com">
                </div>

                <div>
                    <label for="senha" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Senha *
                    </label>
                    <input type="password" id="senha" name="senha" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                           placeholder="Mínimo 6 caracteres">
                </div>

                <div>
                    <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Confirmar Senha *
                    </label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                           placeholder="Digite a senha novamente">
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex">
                        <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                        <div>
                            <h4 class="text-sm font-medium text-blue-800">Informação Importante</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                O novo avaliador será automaticamente associado a todas as crianças cadastradas no sistema.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-4">
                    <button type="submit" 
                            class="flex-1 bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-200 font-medium">
                        <i class="fas fa-user-plus mr-2"></i>Cadastrar Avaliador
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
