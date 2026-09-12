#!/bin/bash
set -e

# 1. Dynamic Port Binding for Render
PORT="${PORT:-80}"
echo "==> Configuring Apache to listen on port: $PORT"
sed -i "s/Listen [0-9]*/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# 2. Auto-generate or Sync .env from Environment Variables
echo "==> Configuring /var/www/html/.env from environment..."
if [ ! -f /var/www/html/.env ]; then
    touch /var/www/html/.env
fi

# If env vars provided in container runtime (e.g. Render Dashboard), inject/update .env
if [ -n "$SUPABASE_URL" ]; then
    if grep -q "^SUPABASE_URL=" /var/www/html/.env; then
        sed -i "s|^SUPABASE_URL=.*|SUPABASE_URL=$SUPABASE_URL|" /var/www/html/.env
    else
        echo "SUPABASE_URL=$SUPABASE_URL" >> /var/www/html/.env
    fi
fi

if [ -n "$SUPABASE_ANON_KEY" ]; then
    if grep -q "^SUPABASE_ANON_KEY=" /var/www/html/.env; then
        sed -i "s|^SUPABASE_ANON_KEY=.*|SUPABASE_ANON_KEY=$SUPABASE_ANON_KEY|" /var/www/html/.env
    else
        echo "SUPABASE_ANON_KEY=$SUPABASE_ANON_KEY" >> /var/www/html/.env
    fi
fi

if [ -n "$SUPABASE_SERVICE_ROLE_KEY" ]; then
    if grep -q "^SUPABASE_SERVICE_ROLE_KEY=" /var/www/html/.env; then
        sed -i "s|^SUPABASE_SERVICE_ROLE_KEY=.*|SUPABASE_SERVICE_ROLE_KEY=$SUPABASE_SERVICE_ROLE_KEY|" /var/www/html/.env
    else
        echo "SUPABASE_SERVICE_ROLE_KEY=$SUPABASE_SERVICE_ROLE_KEY" >> /var/www/html/.env
    fi
fi

# 3. Ensure writable directories for uploads and session / json data
echo "==> Initializing upload and data directories..."
mkdir -p /var/www/html/uploads \
         /var/www/html/uploads/students \
         /var/www/html/student/uploads \
         /var/www/html/faculty/uploads \
         /var/www/html/admin/uploads \
         /var/www/html/data

chmod -R 777 /var/www/html/uploads \
             /var/www/html/uploads/students \
             /var/www/html/student/uploads \
             /var/www/html/faculty/uploads \
             /var/www/html/admin/uploads \
             /var/www/html/data

chown -R www-data:www-data /var/www/html

echo "==> Starting Apache web server..."
exec apache2-foreground
