FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    python3 \
    python3-pip \
    curl \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

RUN chmod +x /app/start.sh

EXPOSE 10000

CMD ["/app/start.sh"]
