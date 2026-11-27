#!/bin/ash
# Wrap the upstream panel entrypoint so schema load works against plaintext CI MariaDB.
# The panel image ships a MariaDB client that rejects MySQL-only options like ssl-mode.
set -eu

cd /app

# MariaDB-compatible client defaults only.
printf '%s\n' '[client]' 'ssl=0' > /root/.my.cnf
chmod 600 /root/.my.cnf

if [ -d /home ]; then
  for home in /home/*; do
    [ -d "$home" ] || continue
    cp /root/.my.cnf "$home/.my.cnf" 2>/dev/null || true
  done
fi

# Force --ssl=0 even when Laravel invokes mysql without reading .my.cnf.
if command -v mysql >/dev/null 2>&1; then
  REAL_MYSQL="$(command -v mysql)"
  case "$REAL_MYSQL" in
    /usr/local/bin/mysql) ;;
    *)
      mkdir -p /usr/local/bin
      if [ ! -e /usr/local/bin/mysql.real ]; then
        ln -sf "$REAL_MYSQL" /usr/local/bin/mysql.real
      fi
      cat > /usr/local/bin/mysql <<'EOF'
#!/bin/ash
exec /usr/local/bin/mysql.real --ssl=0 "$@"
EOF
      chmod +x /usr/local/bin/mysql
      ;;
  esac
  export PATH="/usr/local/bin:${PATH}"
fi

# Compose entrypoint overrides can drop image CMD; never exec an empty argv.
if [ "$#" -eq 0 ]; then
  set -- supervisord -n -c /etc/supervisord.conf
fi

if [ -f /app/.github/docker/entrypoint.sh ]; then
  exec /bin/ash /app/.github/docker/entrypoint.sh "$@"
fi
if [ -f .github/docker/entrypoint.sh ]; then
  exec /bin/ash .github/docker/entrypoint.sh "$@"
fi

exec "$@"
