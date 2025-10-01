<?php
session_start();

// Verificar se o usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Verificar se é administrador
function verificarAdmin() {
    verificarLogin();
    if ($_SESSION['user_type'] !== 'admin') {
        header('Location: dashboard.php');
        exit();
    }
}

// Verificar se é avaliador
function verificarAvaliador() {
    verificarLogin();
    if ($_SESSION['user_type'] !== 'avaliador') {
        header('Location: dashboard.php');
        exit();
    }
}

// Fazer login
function fazerLogin($email, $senha, $pdo) {
    $stmt = $pdo->prepare("SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_type'] = $user['tipo'];
        return true;
    }
    return false;
}

// Fazer logout
function fazerLogout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Gerar hash da senha
function hashSenha($senha) {
    return password_hash($senha, PASSWORD_DEFAULT);
}

// Verificar se é admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Verificar se é avaliador
function isAvaliador() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'avaliador';
}
?>
