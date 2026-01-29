# 🏛️ DOECA - Diário Oficial Eletrônico de Código Aberto

> Sistema simples, leve e eficiente para gerenciamento e publicação de Diários Oficiais municipais.

O **DOECA** foi desenvolvido para oferecer uma solução gratuita e de fácil manutenção para prefeituras e câmaras municipais que precisam dar transparência aos seus atos oficiais. O sistema conta com uma área pública de fácil leitura com busca textual avançada e um painel administrativo seguro para gestão de edições, usuários e métricas de acesso.

---

## 🆕 O que há de novo!

Esta versão traz ferramentas essenciais para a implantação do sistema em órgãos que já possuem um histórico de publicações:

* **📦 Central de Migração (Importação em Lote):** Três novas ferramentas para carregar acervos antigos (legado):
    * **Via CSV:** Importação estruturada usando planilha de dados.
    * **Automática:** Reconhecimento baseado no nome do arquivo (`AAAA-MM-DD__EDICAO.pdf`).
    * **Inteligente (OCR):** O sistema lê o cabeçalho dos PDFs para identificar a Data e o Número da Edição automaticamente, mesmo em arquivos com nomes aleatórios.
* **🔄 Backup e Portabilidade:** Módulo de exportação que gera um arquivo `.ZIP` com todo o acervo. O sistema renomeia os arquivos para um padrão legível e gera um índice CSV automaticamente, facilitando migrações futuras.
* **📊 Dashboard Gerencial:** Acompanhamento visual de visitas, downloads e termos mais pesquisados com geração de relatório em PDF.
* **🔍 Busca Full-Text (OCR/Extração):** O sistema lê automaticamente o texto dos PDFs no upload, permitindo buscas precisas dentro do conteúdo.
* **📂 Armazenamento Inteligente:** Arquivos salvos em subpastas (`uploads/ANO/MES`), garantindo performance.

---

## 🚀 Funcionalidades

### 🌍 Área Pública
* **Busca Inteligente:** Barra de pesquisa estilo "Google" que encontra termos dentro dos PDFs e nos metadados.
* **Listagem Otimizada:** Exibição clara das edições recentes.
* **Visualizador Integrado:** Leitura do PDF sem sair do site (layout responsivo).
* **Download Seguro:** Botão de download protegido via proxy.

### 🔒 Painel Administrativo
* Autenticação segura com criptografia (Bcrypt).
* **Ferramentas:** Hub central para importação de legado e exportação de backups.
* **Dashboard:** Gráficos de acessos, downloads e ranking de pesquisas.
* **Gestão de Edições:** Upload, exclusão e visualização.
* **Gestão de Usuários:** Cadastro com níveis (Admin/Editor).
* **Auditoria:** Histórico visual (timeline) de todas as alterações.

---

## 📸 Telas do Sistema

### Área Pública
<img width="100%" alt="Pagina de Consulta Publica" src="https://github.com/user-attachments/assets/53f9fcba-2600-426b-a23b-52475118d88b" />

### Dashboard Gerencial
<img width="100%" alt="Dashboard" src="https://github.com/user-attachments/assets/80ed9d3d-934e-41f7-9d57-f5d5f1ef4eae" />

### Login e Painel
<img width="100%" alt="Tela de Login" src="https://github.com/user-attachments/assets/0e55d556-055c-4085-9373-badd9ddd8c03" />
<img width="100%" alt="Painel Admin" src="https://github.com/user-attachments/assets/d7405e84-d101-4836-a673-fc1577fecaa2" />

### Auditoria e Gestão
<img width="100%" alt="Histórico de Alterações" src="https://github.com/user-attachments/assets/5d28f428-54aa-42d2-8201-14919360fc58" />
<img width="100%" alt="Gerenciar Usuários" src="https://github.com/user-attachments/assets/c6812d45-3949-4c02-af8a-a1630d9fe29c" />

---

## 🛠️ Requisitos do Servidor

Para rodar o DOECA, você precisará de um servidor web básico com suporte a PHP.

* **PHP:** Versão 7.4 ou superior (Recomendado 8.0+).
* **Banco de Dados:** MySQL ou MariaDB.
* **Servidor Web:** Apache (Recomendado) ou Nginx.
* **Gerenciador de Dependências:** Composer (para instalar o leitor de PDF).
* **Extensões PHP:** `pdo_mysql`, `mbstring`.

---

## 📦 Instalação

Siga os passos abaixo para colocar o sistema no ar:

### 1. Clonar ou Baixar
Faça o download dos arquivos e coloque na pasta pública do seu servidor (ex: `htdocs` ou `www`).

```bash
git clone https://seu-repositorio/doeca.git
cd doeca

```

### 2. Instalar Dependências

O sistema utiliza a biblioteca `smalot/pdfparser`. Instale via Composer na raiz do projeto:

```bash
composer install

```

### 3. Configurar Conexão

1. Renomeie `config.example.php` para `config.php`.
2. Configure suas credenciais:

```php
$host = 'localhost';
$db   = 'doeca_db';
$user = 'root';
$pass = 'suasenha';

```

### 4. Criar o Banco de Dados

Rode o script SQL completo no seu gerenciador de banco de dados:

```sql
CREATE DATABASE IF NOT EXISTS doeca_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE doeca_db;

-- Tabela de Edições
CREATE TABLE edicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_edicao VARCHAR(50) NOT NULL,
    data_publicacao DATE NOT NULL,
    arquivo_path VARCHAR(255) NOT NULL,
    conteudo_indexado LONGTEXT,
    visualizacoes INT DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE edicoes ADD FULLTEXT(conteudo_indexado);

-- Tabela de Usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    nivel ENUM('admin', 'editor') DEFAULT 'editor',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabela de Logs
CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_nome VARCHAR(100),
    acao VARCHAR(50),
    alvo VARCHAR(255),
    detalhes TEXT,
    ip VARCHAR(45),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabelas Dashboard
CREATE TABLE visitas_diarias (
    data_visita DATE PRIMARY KEY,
    quantidade INT DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE termos_pesquisados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    termo VARCHAR(255) UNIQUE,
    quantidade INT DEFAULT 1,
    ultima_busca TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Usuário Padrão (Senha: admin)
INSERT INTO usuarios (nome, email, senha, nivel) VALUES 
('Administrador', 'admin@municipio.gov.br', '$2y$10$OSzVz6E6vdRVzhZW3jzS7u9DIJgt/s9MxoW6pBILcGu7JatFcCZJm', 'admin');

```

### 5. Permissões

Dê permissão de escrita nas pastas:

* `uploads/`
* `importacao/` (Criar manualmente se for usar a ferramenta de importação em lote)

---

## 📂 Estrutura de Arquivos

```text
/doeca
├── admin/
│   ├── index.php                 # Lista de Edições
│   ├── dashboard.php             # Gráficos e Relatórios
│   ├── ferramentas.php           # (Novo) Hub de Importação/Exportação
│   ├── importar.php              # Script Importação Automática
│   ├── importar_csv.php          # Script Importação via CSV
│   ├── importar_inteligente.php  # Script Importação via OCR (Cabeçalho)
│   ├── exportar.php              # Script Backup ZIP
│   ├── usuarios.php              # Gestão de Usuários
│   ├── historico.php             # Auditoria
│   ├── ...                       # Outros arquivos do admin
├── assets/                       # CSS/JS
├── importacao/                   # Pasta temporária para carga de arquivos
├── uploads/                      # Armazenamento oficial (Protegido)
├── vendor/                       # Dependências (Composer)
├── arquivo.php                   # Proxy de download
├── config.php                    # Conexão DB
├── index.php                     # Área Pública
└── README.md                     # Documentação

```

---

## 🤝 Contribuição

1. Faça um Fork.
2. Crie uma Branch (`git checkout -b feature/NovaFeature`).
3. Commit (`git commit -m 'Nova feature'`).
4. Push (`git push origin feature/NovaFeature`).
5. Pull Request.

---

## 📄 Licença

Licença [MIT](https://opensource.org/licenses/MIT). Livre para uso em órgãos públicos.