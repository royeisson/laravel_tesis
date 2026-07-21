# Producción: Laravel con PHP 8.4 + Nginx + Supervisor
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    git \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo_pgsql pgsql mbstring exif pcntl bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock artisan ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY public ./public
COPY resources ./resources
COPY routes ./routes
COPY storage ./storage
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --ignore-platform-req=php --no-scripts \
    && composer dump-autoload --optimize

# Si public/build ya existe (build pre-generado), se usa. Si no, se intenta construir.
COPY package.json package-lock.json* pnpm-lock.yaml* vite.config.js tailwind.config.js postcss.config.js ./
RUN if [ ! -d "public/build" ]; then \
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash - && apt-get install -y nodejs \
        && npm ci --ignore-scripts \
        && npm run build \
        && rm -rf node_modules /root/.npm; \
    fi

COPY . .

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && php -r "file_exists('public/storage') || symlink('/var/www/html/storage/app/public', '/var/www/html/public/storage');"

COPY docker/koyeb/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/koyeb/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
