FROM dunglas/frankenphp:1-php8.3

# The application uses Supabase PostgreSQL through PDO.
RUN install-php-extensions pdo_pgsql

WORKDIR /app
COPY . /app
COPY Caddyfile /etc/caddy/Caddyfile

ENV APP_ENV=production
ENV SERVER_NAME=:80

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
