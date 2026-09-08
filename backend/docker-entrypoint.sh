#!/bin/sh
set -e

# On a stateless PaaS (Render), the filesystem is not persisted across
# deploys/restarts, so the JWT keypair can't just live in config/jwt/ like
# it does locally — it's supplied as base64-encoded environment variables
# and written to the expected file paths here, once, before the app boots.
if [ -n "$JWT_PRIVATE_KEY_B64" ] && [ ! -f config/jwt/private.pem ]; then
    mkdir -p config/jwt
    echo "$JWT_PRIVATE_KEY_B64" | base64 -d > config/jwt/private.pem
    echo "$JWT_PUBLIC_KEY_B64" | base64 -d > config/jwt/public.pem
    chmod 600 config/jwt/private.pem
fi

php bin/console doctrine:migrations:migrate --no-interaction

# Render (and most PaaS) assign the listen port dynamically via $PORT.
exec php -S 0.0.0.0:"${PORT:-8000}" -t public
