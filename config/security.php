<?php
// Configurações de segurança do sistema AvaliaMente

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Mude para 1 em produção com HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Timeout de sessão (2 horas)
ini_set('session.gc_maxlifetime', 7200);

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Função para sanitizar dados de entrada
function sanitizarEntrada($dados) {
    if (is_array($dados)) {
        return array_map('sanitizarEntrada', $dados);
    }
    return htmlspecialchars(trim($dados), ENT_QUOTES, 'UTF-8');
}

// Função para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Função para validar data
function validarData($data) {
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

// Função para gerar token CSRF
function gerarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para verificar token CSRF
function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Função para verificar força da senha
function verificarForcaSenha($senha) {
    $erros = [];
    
    if (strlen($senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres';
    }
    
    if (!preg_match('/[A-Za-z]/', $senha)) {
        $erros[] = 'A senha deve conter pelo menos uma letra';
    }
    
    if (!preg_match('/[0-9]/', $senha)) {
        $erros[] = 'A senha deve conter pelo menos um número';
    }
    
    return $erros;
}

// Função para registrar tentativas de login
function registrarTentativaLogin($email, $sucesso, $ip) {
    $arquivo = 'logs/login_attempts.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $sucesso ? 'SUCCESS' : 'FAILED';
    $linha = "[$timestamp] $status - Email: $email - IP: $ip\n";
    file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
}

// Função para verificar rate limiting
function verificarRateLimit($ip, $max_tentativas = 5, $janela_tempo = 300) {
    $arquivo = 'logs/rate_limit.json';
    $agora = time();
    
    if (file_exists($arquivo)) {
        $dados = json_decode(file_get_contents($arquivo), true);
        
        // Limpar tentativas antigas
        $dados[$ip] = array_filter($dados[$ip] ?? [], function($timestamp) use ($agora, $janela_tempo) {
            return ($agora - $timestamp) < $janela_tempo;
        });
        
        // Verificar se excedeu o limite
        if (count($dados[$ip] ?? []) >= $max_tentativas) {
            return false;
        }
        
        // Adicionar nova tentativa
        $dados[$ip][] = $agora;
    } else {
        $dados = [$ip => [$agora]];
    }
    
    file_put_contents($arquivo, json_encode($dados), LOCK_EX);
    return true;
}

// Função para log de auditoria
function logAuditoria($acao, $detalhes = '') {
    $arquivo = 'logs/audit.log';
    $timestamp = date('Y-m-d H:i:s');
    $usuario = $_SESSION['user_name'] ?? 'Sistema';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
    $linha = "[$timestamp] $usuario ($ip) - $acao - $detalhes\n";
    file_put_contents($arquivo, $linha, FILE_APPEND | LOCK_EX);
}

// Função para limpar logs antigos
function limparLogsAntigos($dias = 30) {
    $diretorio = 'logs/';
    $arquivos = glob($diretorio . '*.log');
    
    foreach ($arquivos as $arquivo) {
        if (filemtime($arquivo) < (time() - ($dias * 24 * 60 * 60))) {
            unlink($arquivo);
        }
    }
}

// Criar diretório de logs se não existir
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

// Executar limpeza de logs (apenas uma vez por dia)
$ultima_limpeza = 'logs/last_cleanup.txt';
if (!file_exists($ultima_limpeza) || (time() - filemtime($ultima_limpeza)) > 86400) {
    limparLogsAntigos();
    file_put_contents($ultima_limpeza, time());
}
?>
