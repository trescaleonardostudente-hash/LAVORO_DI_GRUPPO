#!/bin/bash

echo "🚀 Avvio Progetto Verificaasorpresa"
echo "===================================="
echo ""

# 1. Avvia MySQL con Docker
echo "📦 Avvio MySQL con Docker..."
docker-compose up -d

echo ""
echo "⏳ Attendo che MySQL sia pronto..."
sleep 10

# 2. Verifica connessione
echo ""
echo "🔍 Verifico connessione al database..."
docker exec forniture_db mysql -uroot -proot -e "SHOW DATABASES;" | grep Forniture

if [ $? -eq 0 ]; then
    echo "✅ Database Forniture pronto!"
else
    echo "⚠️  Attendo ancora..."
    sleep 5
fi

# 3. Avvia il server PHP
echo ""
echo "🌐 Avvio server PHP su http://localhost:8000"
echo ""
echo "======================================"
echo "📋 Endpoints disponibili:"
echo "   http://localhost:8000              (lista endpoints)"
echo "   http://localhost:8000/api/pezzi-forniti"
echo "   http://localhost:8000/api/fornitori-tutti-pezzi"
echo "======================================"
echo ""
echo "Premi CTRL+C per fermare il server"
echo ""

PHP_BIN=""

if command -v /usr/bin/php >/dev/null 2>&1 && /usr/bin/php -m | grep -qi '^pdo_mysql$'; then
    PHP_BIN="/usr/bin/php"
elif command -v php >/dev/null 2>&1 && php -m | grep -qi '^pdo_mysql$'; then
    PHP_BIN="php"
fi

if [ -z "$PHP_BIN" ]; then
    echo "❌ Nessun runtime PHP con pdo_mysql trovato."
    echo "   Installa il driver MySQL per PHP e riprova."
    echo "   Esempio (Ubuntu): sudo apt-get install -y php8.3-mysql"
    exit 1
fi

PORT=8000
PIDS_ON_PORT=$(lsof -ti :$PORT)
if [ -n "$PIDS_ON_PORT" ]; then
    echo "⚠️  Porta $PORT già in uso, provo a liberarla..."
    for pid in $PIDS_ON_PORT; do
        CMD=$(ps -p "$pid" -o comm=)
        if [[ "$CMD" == php* ]]; then
            kill "$pid" 2>/dev/null || true
        fi
    done
    sleep 1

    if lsof -ti :$PORT >/dev/null 2>&1; then
        echo "❌ Porta $PORT ancora occupata da processo non gestibile automaticamente."
        echo "   Chiudila manualmente con: lsof -ti :$PORT | xargs -r kill -9"
        exit 1
    fi
fi

echo "✅ Uso runtime PHP: $PHP_BIN"
$PHP_BIN -S localhost:$PORT -t public
