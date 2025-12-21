# Dockerfile para Exclusiva SaaS

FROM php:8.1-fpm

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    supervisor \
    nginx \
    redis-tools \
    mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensões PHP
RUN docker-php-ext-install \
    pdo_mysql \
    zip \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml \
    curl

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instalar Node.js
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && \
    apt-get install -y nodejs

# Definir diretório de trabalho
WORKDIR /var/www/exclusiva

# Copiar código da aplicação (backend agora está na raiz do repositório)
COPY backend /var/www/exclusiva

# Instalar dependências PHP
# RUN composer install --optimize-autoloader --no-dev

# Instalar dependências Node (opcional - desabilitado para instalação rápida)
# RUN npm install

# Build de assets (opcional - desabilitado para instalação rápida)
# RUN npm run build

# Criar diretórios necessários
RUN mkdir -p /var/www/exclusiva/storage/logs \
    && mkdir -p /var/www/exclusiva/bootstrap/cache \
    && chown -R www-data:www-data /var/www/exclusiva \
    && chmod -R 755 /var/www/exclusiva \
    && chmod -R 775 /var/www/exclusiva/storage \
    && chmod -R 775 /var/www/exclusiva/bootstrap/cache

# Copiar configuração do Nginx
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copiar configuração do PHP-FPM
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copiar script de inicialização
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expor portas
EXPOSE 80 443 9000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Executar entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
