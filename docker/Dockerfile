# Escolha a versão do PHP compatível com seu código (ex: 7.4, 8.0, 8.2)
FROM php:8.1-apache

# 1. Instalar dependências do sistema necessárias para o Composer e extensões PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mysqli zip gd mbstring

# 2. Habilitar o mod_rewrite do Apache (geralmente útil para sistemas PHP)
RUN a2enmod rewrite
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# 3. Instalar o Composer dentro do container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Definir diretório de trabalho
WORKDIR /var/www/html

# 5. Copiar o código fonte para dentro do container
# Nota: Copie primeiro o composer.json para aproveitar o cache do Docker
COPY src/composer.json src/composer.lock ./

# 6. Rodar o install das dependências (pdf-parser)
# --no-dev e --optimize-autoloader são boas práticas para produção
RUN composer install --no-dev --optimize-autoloader

# 7. Copiar o restante da aplicação
COPY src/ .

# 8. Ajustar permissões (o Apache roda como www-data)
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 775 /var/www/html/uploads
RUN chmod -R 775 /var/www/html/importacao