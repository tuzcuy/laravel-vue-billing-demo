# Stage 1 — build frontend assets
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
RUN npm ci
COPY resources resources
COPY public public
RUN npm run build

# Stage 2 — install PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction \
    --no-scripts --ignore-platform-reqs

# Stage 3 — application
FROM php:8.4-cli-alpine
RUN docker-php-ext-install pdo_mysql bcmath > /dev/null

WORKDIR /app
COPY . .
COPY --from=vendor /app/vendor vendor
COPY --from=assets /app/public/build public/build

RUN cp .env.example .env \
    && php artisan key:generate --force

EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate:fresh --seed --force && php artisan serve --host=0.0.0.0 --port=8000"]
