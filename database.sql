-- Sistema AvaliaMente - Estrutura do Banco de Dados
-- Criado para ONGs e profissionais de psicopedagogia

CREATE DATABASE IF NOT EXISTS avaliamente;
USE avaliamente;

-- Tabela de usuários (admin e avaliadores)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'avaliador') NOT NULL DEFAULT 'avaliador',
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de crianças
CREATE TABLE criancas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    data_nascimento DATE NOT NULL,
    responsavel VARCHAR(255) NOT NULL,
    observacoes TEXT,
    criado_por INT NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Tabela de formulários
CREATE TABLE formularios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    criado_por INT NOT NULL,
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (criado_por) REFERENCES usuarios(id)
);

-- Tabela de categorias (fixas: Socialização, Linguagem, Cognição, Autocuidados, Desenvolvimento Motor)
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    formulario_id INT NOT NULL,
    nome_categoria VARCHAR(100) NOT NULL,
    ordem INT DEFAULT 0,
    FOREIGN KEY (formulario_id) REFERENCES formularios(id)
);

-- Tabela de perguntas
CREATE TABLE perguntas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    texto_pergunta TEXT NOT NULL,
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
);

-- Tabela de avaliações
CREATE TABLE avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crianca_id INT NOT NULL,
    avaliador_id INT NOT NULL,
    formulario_id INT NOT NULL,
    data_avaliacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT,
    FOREIGN KEY (crianca_id) REFERENCES criancas(id),
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id),
    FOREIGN KEY (formulario_id) REFERENCES formularios(id)
);

-- Tabela de respostas
CREATE TABLE respostas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliacao_id INT NOT NULL,
    pergunta_id INT NOT NULL,
    valor DECIMAL(2,1) NOT NULL CHECK (valor IN (0, 0.5, 1)),
    FOREIGN KEY (avaliacao_id) REFERENCES avaliacoes(id),
    FOREIGN KEY (pergunta_id) REFERENCES perguntas(id),
    UNIQUE KEY unique_avaliacao_pergunta (avaliacao_id, pergunta_id)
);

-- Tabela de relacionamento avaliador-criança
CREATE TABLE avaliador_crianca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    avaliador_id INT NOT NULL,
    crianca_id INT NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (avaliador_id) REFERENCES usuarios(id),
    FOREIGN KEY (crianca_id) REFERENCES criancas(id),
    UNIQUE KEY unique_avaliador_crianca (avaliador_id, crianca_id)
);

-- Inserir usuário administrador padrão
INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
('Administrador', 'admin@avaliamente.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Inserir formulário padrão
INSERT INTO formularios (titulo, descricao, criado_por) VALUES 
('Avaliação de Desenvolvimento Infantil', 'Formulário completo para avaliação do desenvolvimento infantil em 5 categorias principais', 1);

-- Inserir categorias fixas
INSERT INTO categorias (formulario_id, nome_categoria, ordem) VALUES 
(1, 'Socialização', 1),
(1, 'Linguagem', 2),
(1, 'Cognição', 3),
(1, 'Autocuidados', 4),
(1, 'Desenvolvimento Motor', 5);

-- Inserir perguntas para cada categoria
-- Socialização
INSERT INTO perguntas (categoria_id, texto_pergunta, ordem) VALUES 
(1, 'A criança mantém contato visual durante interações sociais?', 1),
(1, 'A criança responde adequadamente a cumprimentos e despedidas?', 2),
(1, 'A criança demonstra interesse em brincar com outras crianças?', 3),
(1, 'A criança compartilha brinquedos e materiais com outras crianças?', 4),
(1, 'A criança segue regras básicas de convivência em grupo?', 5);

-- Linguagem
INSERT INTO perguntas (categoria_id, texto_pergunta, ordem) VALUES 
(2, 'A criança compreende instruções simples de 1-2 passos?', 1),
(2, 'A criança expressa suas necessidades verbalmente?', 2),
(2, 'A criança usa vocabulário apropriado para sua idade?', 3),
(2, 'A criança mantém conversas simples com adultos?', 4),
(2, 'A criança demonstra interesse por histórias e livros?', 5);

-- Cognição
INSERT INTO perguntas (categoria_id, texto_pergunta, ordem) VALUES 
(3, 'A criança demonstra curiosidade e interesse em aprender?', 1),
(3, 'A criança resolve problemas simples de forma independente?', 2),
(3, 'A criança reconhece cores, formas e números básicos?', 3),
(3, 'A criança demonstra memória adequada para sua idade?', 4),
(3, 'A criança consegue focar em atividades por períodos apropriados?', 5);

-- Autocuidados
INSERT INTO perguntas (categoria_id, texto_pergunta, ordem) VALUES 
(4, 'A criança demonstra independência na alimentação?', 1),
(4, 'A criança cuida de sua higiene pessoal adequadamente?', 2),
(4, 'A criança se veste e despe com autonomia?', 3),
(4, 'A criança demonstra consciência sobre segurança pessoal?', 4),
(4, 'A criança organiza seus pertences e brinquedos?', 5);

-- Desenvolvimento Motor
INSERT INTO perguntas (categoria_id, texto_pergunta, ordem) VALUES 
(5, 'A criança demonstra coordenação motora grossa adequada?', 1),
(5, 'A criança demonstra coordenação motora fina adequada?', 2),
(5, 'A criança mantém equilíbrio e postura adequados?', 3),
(5, 'A criança demonstra força muscular apropriada?', 4),
(5, 'A criança demonstra lateralidade definida?', 5);
