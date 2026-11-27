#!/bin/ash
# Wrap the upstream panel entrypoint so the mysql CLI used for schema load
# does not require TLS against our plaintext CI MariaDB.
set -eu

cd /app

printf '%s\n' \
  '[client]' \
  'ssl=0' \
  'ssl-mode=DISABLED' \
  > /root/.my.cnf
chmod 600 /root/.my.cnf

# Cover non-root mysql invocations if the image drops privileges.
if [ -d /home ]; then
  for home in /home/*; do
    [ -d "$home" ] || continue
    cp /root/.my.cnf "$home/.my.cnf" 2>/dev/null || true
  done
fi
if id nginx >/dev/null 2>&1; then
  nginx_home="$(getent passwd nginx | cut -d: -f6 || true)"
  if [ -n "${nginx_home:-}" ] && [ -d "$nginx_home" ]; then
    cp /root/.my.cnf "$nginx_home/.my.cnf" 2>/dev/null || true
  fi
fi

# Also install a mysql wrapper that forces --ssl=0 for schema dumps.
if command -v mysql >/dev/null 2>&1; then
  REAL_MYSQL="$(command -v mysql)"
  if [ ! -e /usr/local/bin/mysql.real ]; then
    cp "$REAL_MYSQL" /usr/local/bin/mysql.real 2>/dev/null || ln -sf "$REAL_MYSQL" /usr/local/bin/mysql.real
  fi
  cat > /usr/local/bin/mysql <<'EOF'
#!/bin/ash
exec /usr/local/bin/mysql.real --ssl=0 "$@"
EOF
  chmod +x /usr/local/bin/mysql
  export PATH="/usr/local/bin:${PATH}"
fi

if [ -f /app/.github/docker/entrypoint.sh ]; then
  exec /bin/ash /app/.github/docker/entrypoint.sh "$@"
fi
if [ -f .github/docker/entrypoint.sh ]; then
  exec /bin/ash .github/docker/entrypoint.sh "$@"
fi

exec "$@"
