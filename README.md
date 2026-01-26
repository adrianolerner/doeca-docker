# 🏛️ DOECA - Diário Oficial Eletrônico de Código Aberto

> Sistema simples, leve e eficiente para gerenciamento e publicação de Diários Oficiais municipais.

O **DOECA** foi desenvolvido para oferecer uma solução gratuita e de fácil manutenção para prefeituras e câmaras municipais que precisam dar transparência aos seus atos oficiais. O sistema conta com uma área pública de fácil leitura com busca textual avançada e um painel administrativo seguro para gestão de edições, usuários e métricas de acesso.

---

## 🐳 Instalação Rápida (Docker) - Recomendado

A maneira mais simples de rodar o DOECA é utilizando containers. Isso garante que todas as dependências (PHP, Apache, MySQL, PDF Parser) funcionem sem necessidade de configuração manual no servidor.

### Pré-requisitos

* [Docker](https://www.docker.com/) e Docker Compose instalados.

### Passo a Passo

1. **Clone o repositório:**

    ```bash
    git clone https://github.com/adrianolerner/doeca-docker.git
    cd doeca-docker
    ```

2. **⚙️ Configuração (Docker):**

As configurações de banco de dados são gerenciadas diretamente no arquivo `docker-compose.yml` ou através de variáveis de ambiente. O sistema PHP detecta essas variáveis automaticamente.

Caso precise alterar senhas ou portas, edite a seção `environment` no `docker-compose.yml`:

```bash
nano docker-compose.yml
````

Altere as variáveis na seção do app e na seção do banco de dados com os mesmos dados.

```yaml
environment:
  - DB_HOST=db_doeca
  - DB_NAME=doeca_db
  - DB_USER=doeca_user
  - DB_PASS=sua_senha_segura

```

3. **Suba o ambiente:**
    Execute o comando abaixo na raiz do projeto. O Docker irá baixar as imagens, instalar o Composer e configurar o banco de dados automaticamente.

    ```bash
    docker-compose up -d --build
    ```

    ou (depdendo da distribuiçãoe versão, talvez precise rodar com sudo)

```bash
    docker compose up -d --build
```

4. **Acesse o sistema:**
    * **Área Pública:** `http://localhost:8080`
    * **Painel Admin:** `http://localhost:8080/admin`
    * **Login Padrão:** `admin@municipio.gov.br` / `admin`

---

## 🆕 O que há de novo

Esta versão traz ferramentas essenciais para a implantação do sistema em órgãos que já possuem um histórico de publicações:

* **📦 Central de Migração (Importação em Lote):** Três novas ferramentas para carregar acervos antigos (legado):
* **Via CSV:** Importação estruturada usando planilha de dados.
* **Automática:** Reconhecimento baseado no nome do arquivo (`AAAA-MM-DD__EDICAO.pdf`).
* **Inteligente (OCR):** O sistema lê o cabeçalho dos PDFs para identificar a Data e o Número da Edição automaticamente, mesmo em arquivos com nomes aleatórios.

* **🔄 Backup e Portabilidade:** Módulo de exportação que gera um arquivo `.ZIP` com todo o acervo. O sistema renomeia os arquivos para um padrão legível e gera um índice CSV automaticamente, facilitando migrações futuras.
* **🔍 Busca Full-Text (OCR/Extração):** O sistema lê automaticamente o texto dos PDFs no upload, permitindo buscas precisas dentro do conteúdo.

---

## 🛠️ Instalação Manual (Legado / cPanel)

Se você não pode usar Docker e precisa instalar em um servidor tradicional (XAMPP, Apache, cPanel), siga os passos em:
htps://github.com/adrianolerner/doeca/

## 📂 Estrutura de Arquivos

```text
/doeca
├── docker/                   # Configurações de Container
│   ├── Dockerfile            # Imagem do PHP/Apache
│   └── init_db/              # Script SQL de inicialização automática
├── src/                      # Código Fonte da Aplicação
│   ├── admin/                # Painel Administrativo
│   ├── assets/               # CSS/JS
│   ├── importacao/           # Pasta temporária para carga de arquivos
│   ├── uploads/              # Armazenamento oficial (Montado via Volume)
│   ├── vendor/               # Dependências (Composer - Gerado no build)
│   ├── config.php            # Conexão DB (Híbrido: Docker/Manual)
│   └── index.php             # Área Pública
├── docker-compose.yml        # Orquestração dos containers
└── README.md                 # Documentação

```

## 📄 Licença

Licença [MIT](https://opensource.org/licenses/MIT). Livre para uso em órgãos públicos.
