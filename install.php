<?php
// Script de instalação do AvaliaMente
// Execute este arquivo uma única vez após configurar o banco de dados

require_once 'config/database.php';

$erro = '';
$sucesso = '';

if ($_POST) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Verificar se as tabelas já existem
        $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
        if ($stmt->rowCount() > 0) {
            $erro = 'O sistema já foi instalado. As tabelas já existem no banco de dados.';
        } else {
            // Ler e executar o arquivo SQL
            $sql = file_get_contents('database.sql');
            $pdo->exec($sql);
            
            // Criar diretório de logs
            if (!file_exists('logs')) {
                mkdir('logs', 0755, true);
            }
            
            $sucesso = 'Sistema instalado com sucesso! Você pode acessar o login agora.';
        }
    } catch (Exception $e) {
        $erro = 'Erro na instalação: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AvaliaMente - Instalação</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-2xl">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                <i class="fas fa-brain text-3xl text-blue-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">AvaliaMente</h1>
            <p class="text-gray-600">Sistema de Avaliação Infantil</p>
        </div>

        <?php if ($erro): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($sucesso); ?>
            </div>
            <div class="text-center">
                <a href="login.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>Ir para Login
                </a>
            </div>
        <?php else: ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="font-medium text-blue-800 mb-2">Pré-requisitos:</h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• PHP 8.0 ou superior</li>
                    <li>• MySQL 8.0 ou superior</li>
                    <li>• Banco de dados 'avaliamente' criado</li>
                    <li>• Configuração do banco em config/database.php</li>
                </ul>
            </div>

            <form method="POST">
                <div class="text-center">
                    <button type="submit" 
                            class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition duration-200 font-medium">
                        <i class="fas fa-download mr-2"></i>Instalar Sistema
                    </button>
                </div>
            </form>

            <div class="mt-8 bg-gray-50 rounded-lg p-4">
                <h3 class="font-medium text-gray-800 mb-2">Credenciais padrão após instalação:</h3>
                <p class="text-sm text-gray-600">
                    <strong>Admin:</strong> admin@avaliamente.com / password
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
