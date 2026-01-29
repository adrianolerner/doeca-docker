Este é um repositório ainda em testes, contém a imagem pronta para implantação do sistema DOECA, disponível em: <https://github.com/adrianolerner/doeca-docker>

_______________________________________________________________________________________________

# 🏛️ DOECA - Diário Oficial Eletrônico de Código Aberto

> Sistema simples, leve e eficiente para gerenciamento e publicação de Diários Oficiais municipais.

O **DOECA** foi desenvolvido para oferecer uma solução gratuita e de fácil manutenção para prefeituras e câmaras municipais que precisam dar transparência aos seus atos oficiais. O sistema conta com uma área pública de fácil leitura com busca textual avançada e um painel administrativo seguro para gestão de edições, usuários e métricas de acesso.

---

## 🐳 Instalação Rápida (Docker) - Recomendado

A maneira mais simples de rodar o DOECA é utilizando containers. Isso garante que todas as dependências (PHP, Apache, MySQL, PDF Parser) funcionem sem necessidade de configuração manual no servidor.

### Pré-requisitos

* [Docker](https://www.docker.com/) e Docker Compose instalados.

### Passo a Passo

1. **Crie uma pasta chamada doeca em seu servidor:**

2. **Dentro da pasta crie o arquivo docker-compose.yml e cole o conteudo abaixo modificando os dados de acesso ao banco de dados:**
   OBS.: Caso queira fazer a build do container localmente, você pode clonar este respositório localmente com o comando `git clone https://github.com/adrianolerner/doeca.git` e então, editar o arquivo `docker-compose.yml` padrão do repositório modificando o usuário e senha do banco de dados dentro da raiz do repositório ir para o passo 4 e 5.

    ```bash
    nano docker-compose.yml
    ```

    ```yaml
    services:
        app:
            # Altere o usuário e senha do banco de dados conforme sua necessidade. TAmbém é possível trocar a porta padrão 8080 por uma de sua necessidade.
            image: albiesek/doeca:latest
            container_name: doeca_app
            restart: always
            ports:
                - "8080:80"
            environment:
                - DB_HOST=db_doeca
                - DB_NAME=doeca_db
                - DB_USER=admin
                - DB_PASS=admin
                - CF_SITE_KEY=SUA_SITE_KEY_AQUI
                - CF_SECRET_KEY=SUA_SECRET_KEY_AQUI
            depends_on:
                - db_doeca
            networks:
                - doeca_net
        # Volumes apenas para persistência de dados (uploads). Caso queira, também é possível montar em pasta ao invés de volume. 
        # Neste caso troque o volume pelo caminho local que desejar e remova da seção de volumes no fim do arquivo e necessário dar as devidas permissções (sudo chmod 775 -R)
            volumes:
                - doeca_uploads:/var/www/html/uploads
                # A montagem importação é necessária para poder incluir lotes de arquivos mais facilmente para processamento inicial, caso não queira a função pode ser removido ou montado em volume.
                # Necessário dar privilégios usando "sudo chmod 775 -R importacao/" após a criação do container.
                - ./importacao:/var/www/html/importacao

        db_doeca:
            image: mysql:8.0
            container_name: doeca_db
            restart: always
            environment:
                # Alterar para o mesmo usuário e senha usados no bloco evironment do APP acima.
                MYSQL_DATABASE: doeca_db
                MYSQL_USER: admin
                MYSQL_PASSWORD: admin
                MYSQL_ROOT_PASSWORD: admin123
            volumes:
                - db_data:/var/lib/mysql
            networks:
                - doeca_net

    networks:
        doeca_net:
            driver: bridge

    # Caso montado caminho local para as pastas de uploads, remover deste bloco o volume correspondente.
    volumes:
        db_data:
        doeca_uploads:
    ```

4. **Ajuste de permissão das pastas montadas:**

    Execute os comandos abaixo para garantir que o container consiga gravar os arquivos nas pastas mapeadas:
    Caso não montar a pasta uploads localmente não é necessário dar permissão na pasta uploads.

    ```bash
    sudo chmod 775 uploads/
    sudo chmod 775 importacao/
    ```

5. **Suba o ambiente:**

    Execute o comando abaixo na raiz do projeto. O Docker irá baixar as imagens, instalar o Composer e configurar o banco de dados automaticamente. OBS.: Pode ou não ser necessário rodar os comandos abaixo com o uso do SUDO, verifique a configuração do seu ambiente.

    ```bash
    docker-compose up -d
    ```

    *Nota: Dependendo da sua distribuição Linux e versão do Docker, o comando pode ser sem o hífen:*

    ```bash
    docker compose up -d
    ```

6. **Acesse o sistema:**
    ***Área Pública:** `http://localhost:8080`
    * **Painel Admin:** `http://localhost:8080/admin`
    * **Login Padrão:** `admin@municipio.gov.br` / `admin`

---

## 🆕 O que há de novo

Esta versão traz ferramentas essenciais para a implantação do sistema em órgãos que já possuem um histórico de publicações:

* **📦 Central de Migração (Importação em Lote):** Três novas ferramentas para carregar acervos antigos (legado):
  * **Via CSV:** Importação estruturada usando planilha de dados.
  * **Automática:** Reconhecimento baseado no nome do arquivo (ex: `AAAA-MM-DD__EDICAO.pdf`).
  * **Inteligente (OCR):** O sistema lê o cabeçalho dos PDFs para identificar a Data e o Número da Edição automaticamente, mesmo em arquivos com nomes aleatórios.
* **🔄 Backup e Portabilidade:** Módulo de exportação que gera um arquivo `.ZIP` com todo o acervo. O sistema renomeia os arquivos para um padrão legível e gera um índice CSV automaticamente, facilitando migrações futuras.
* **🔍 Busca Full-Text (OCR/Extração):** O sistema lê automaticamente o texto dos PDFs no upload, permitindo buscas precisas dentro do conteúdo.

---

## 🛠️ Instalação Manual (Legado / cPanel)

Se você não pode usar Docker e precisa instalar em um servidor tradicional (XAMPP, Apache, cPanel), siga os passos no repositório original:
[https://github.com/adrianolerner/doeca/](https://github.com/adrianolerner/doeca/)

---

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
