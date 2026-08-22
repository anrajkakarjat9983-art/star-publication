#!/bin/bash
set -e

DATADIR=/var/lib/mysql

if [ ! -d "$DATADIR/mysql" ]; then
  mysql_install_db --user=mysql --datadir="$DATADIR"
fi

mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld "$DATADIR"

mysqld_safe --user=mysql --bind-address=127.0.0.1 &

for i in $(seq 1 60); do
  if mysqladmin --user=root ping --silent 2>/dev/null; then break; fi
  sleep 1
done

mysql --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${DB_PASS}';
FLUSH PRIVILEGES;
SQL

exec apache2-foreground
