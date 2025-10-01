# 🧠 AvaliaMente - Sistema de Avaliação Infantil

> Sistema web voltado para ONGs e profissionais de psicopedagogia e desenvolvimento infantil.
> Permite o cadastro de crianças e aplicação de formulários avaliativos por **avaliadores**, com acompanhamento da evolução em gráficos por categoria.

## 🚀 Funcionalidades

### 👑 **Administrador**
- ✅ Gera e gerencia contas de avaliadores
- ✅ Cadastra novas crianças
- ✅ Visualiza relatórios gerais da ONG
- ✅ Acompanha o progresso das avaliações em todas as categorias
- ✅ Dashboard com estatísticas completas
- ✅ Relatórios detalhados com filtros
- ✅ **Gerenciamento completo de perguntas** (adicionar, editar, excluir)
- ✅ **Visualização de todas as crianças** com filtros avançados

### 🧑‍⚕️ **Avaliador**
- ✅ Faz login no sistema
- ✅ Acessa a lista de crianças que ele acompanha
- ✅ Aplica formulários de avaliação (divididos por categorias)
- ✅ Visualiza histórico de avaliações anteriores
- ✅ Vê gráficos comparativos mostrando evolução da criança ao longo do tempo
- ✅ **Gerenciamento de perguntas** (adicionar, editar, excluir)
- ✅ **Visualização de todas as crianças** com filtros avançados

## 🧱 **Categorias de Avaliação**

1. **Socialização** - Interações sociais e convivência
2. **Linguagem** - Comunicação e expressão verbal
3. **Cognição** - Desenvolvimento cognitivo e aprendizado
4. **Autocuidados** - Independência e cuidados pessoais
5. **Desenvolvimento Motor** - Coordenação e habilidades motoras

## 💻 **Tecnologias Utilizadas**

| Área | Tecnologia |
|------|------------|
| Front-end | HTML5, CSS3, JavaScript, TailwindCSS |
| Back-end | PHP 8+ |
| Banco de Dados | MySQL 8+ |
| Gráficos | Chart.js |
| Autenticação | PHP Sessions |
| Ícones | Font Awesome |

## 📋 **Pré-requisitos**

- PHP 8.0 ou superior
- MySQL 8.0 ou superior
- Servidor web (Apache/Nginx)
- Extensões PHP: PDO, PDO_MySQL, JSON

## 🛠️ **Instalação**

### 1. Clone ou baixe o projeto
```bash
git clone [url-do-repositorio]
# ou baixe o ZIP e extraia
```

### 2. Configure o banco de dados
1. Crie um banco de dados MySQL chamado `avaliamente`
2. Importe o arquivo `database.sql`:
```sql
mysql -u root -p avaliamente < database.sql
```

### 3. Configure a conexão
Edite o arquivo `config/database.php` com suas credenciais:
```php
private $host = 'localhost';
private $db_name = 'avaliamente';
private $username = 'seu_usuario';
private $password = 'sua_senha';
```

### 4. Configure permissões
```bash
chmod 755 logs/
chmod 644 config/*.php
```

### 5. Acesse o sistema
- URL: `http://localhost/avaliamentev1/`
- **Admin padrão:**
  - Email: `admin@avaliamente.com`
  - Senha: `password`

## 🔒 **Segurança Implementada**

- ✅ Senhas criptografadas com `password_hash()`
- ✅ Proteção contra SQL Injection (PDO Prepared Statements)
- ✅ Validação e sanitização de dados de entrada
- ✅ Headers de segurança HTTP
- ✅ Rate limiting para tentativas de login
- ✅ Logs de auditoria e tentativas de acesso
- ✅ Sessões seguras com timeout
- ✅ Validação de permissões por tipo de usuário

## 📊 **Estrutura do Banco de Dados**

### Tabelas principais:
- `usuarios` - Administradores e avaliadores
- `criancas` - Dados das crianças
- `formularios` - Formulários de avaliação
- `categorias` - Categorias de desenvolvimento
- `perguntas` - Perguntas por categoria
- `avaliacoes` - Registros de avaliações
- `respostas` - Respostas das avaliações
- `avaliador_crianca` - Relacionamento avaliador-criança

## 🎨 **Design e UX**

- **Visual:** Clean e acolhedor, inspirado no Google Forms + Notion
- **Cores:** Azul (#3B82F6), Verde (#10B981), Vermelho (#EF4444)
- **Responsivo:** Funciona em desktop, tablet e mobile
- **Acessibilidade:** Contraste adequado e navegação por teclado

## 📈 **Gráficos e Relatórios**

- **Chart.js** para visualizações interativas
- Gráficos de evolução por categoria
- Comparativo primeira vs última avaliação
- Relatórios administrativos com filtros
- Estatísticas de uso do sistema
- **Sistema completo de gerenciamento de perguntas**

## 🔧 **Configurações Avançadas**

### Logs
- `logs/audit.log` - Log de auditoria
- `logs/login_attempts.log` - Tentativas de login
- `logs/rate_limit.json` - Controle de rate limiting

### Personalização
- Edite `config/database.php` para configurações do banco
- Modifique `config/security.php` para configurações de segurança
- Ajuste perguntas em `database.sql` conforme necessário

## 🚀 **Deploy em Produção**

### Recomendações:
1. Use HTTPS (certificado SSL)
2. Configure `session.cookie_secure = 1`
3. Configure backup automático do banco
4. Monitore logs regularmente
5. Mantenha PHP e MySQL atualizados

### Hospedagem sugerida:
- **Gratuita:** InfinityFree (PHP + MySQL)
- **Paga:** Hostinger, Locaweb, DigitalOcean
- **Cloud:** Vercel, Deta Space (com adaptações)

## 📞 **Suporte**

Para dúvidas ou problemas:
1. Verifique os logs em `logs/`
2. Confirme as configurações do banco
3. Verifique permissões de arquivo
4. Consulte a documentação do PHP/MySQL

## 🔄 **Atualizações Futuras**

- [ ] Exportação de relatórios em PDF
- [ ] Notificações por email
- [ ] API REST para integrações
- [ ] App mobile (React Native/Flutter)
- [ ] Backup automático na nuvem
- [ ] Múltiplos formulários personalizáveis

## 📄 **Licença**

Este projeto é de código aberto e pode ser usado livremente para fins educacionais e comerciais.

---

**Desenvolvido com ❤️ para o desenvolvimento infantil**
