FROM php:8.2-apache

# Instalar extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar working directory
WORKDIR /var/www/html

# Copiar arquivos da aplicação
COPY . .

# Instalar dependências do Composer
RUN composer install --no-dev --optimize-autoloader

# Criar diretórios necessários
RUN mkdir -p /var/www/html/storage/logs
RUN mkdir -p /var/www/html/storage/framework/cache
RUN mkdir -p /var/www/html/storage/framework/views

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html/storage
RUN chmod -R 775 /var/www/html/storage

# Copiar e configurar entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Configurar Apache para usar porta 8080 ANTES de copiar o apache.conf
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf

# Copiar configuração do Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Expor porta 8080 (Render usa essa porta)
EXPOSE 8080

# Usar entrypoint customizado que roda migrations
ENTRYPOINT ["docker-entrypoint.sh"]
