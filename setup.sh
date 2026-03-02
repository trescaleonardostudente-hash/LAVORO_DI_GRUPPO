#!/bin/bash

# Uscita immediata se un comando fallisce
set -e

# CONFIGURA QUI IL TUO UTENTE E PASSWORD
PMA_USER="BERTU"
PMA_PASS="BERTU"
BLOWFISH_SECRET="qwertyuiopasdfghjklzxcvbnmqwerty"

echo "🛠️  Aggiornamento pacchetti e installazione Apache, PHP, MariaDB..."
sudo apt update
sudo apt install -y apache2 php libapache2-mod-php php-mysql mariadb-server wget unzip

echo "🚀 Avvio di MariaDB..."
sudo service mariadb start

echo "🔒 Esecuzione configurazione sicura MariaDB (automatica con expect)..."
sudo mariadb <<EOF
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

echo "📂 Installazione phpMyAdmin..."
cd /var/www/html
sudo wget https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.zip
sudo unzip phpMyAdmin-latest-all-languages.zip
sudo mv phpMyAdmin-*-all-languages phpmyadmin
sudo rm phpMyAdmin-latest-all-languages.zip

echo "⚙️  Configurazione phpMyAdmin..."
cd phpmyadmin
sudo cp config.sample.inc.php config.inc.php
sudo sed -i "s/\(\$cfg\['blowfish_secret'\] = \).*/\1'$BLOWFISH_SECRET';/" config.inc.php

echo "🌐 Configurazione Apache per phpMyAdmin..."
cat <<EOCONF | sudo tee /etc/apache2/conf-available/phpmyadmin.conf
Alias /phpmyadmin /var/www/html/phpmyadmin

<Directory /var/www/html/phpmyadmin>
    Options Indexes FollowSymLinks
    DirectoryIndex index.php
    AllowOverride All
    Require all granted
</Directory>
EOCONF


sudo service apache2 restart
sudo a2enconf phpmyadmin
sudo service apache2 restart

echo "👤 Creazione utente MariaDB per phpMyAdmin..."
sudo mariadb <<EOF
CREATE USER IF NOT EXISTS '$PMA_USER'@'localhost' IDENTIFIED BY '$PMA_PASS';
GRANT ALL PRIVILEGES ON *.* TO '$PMA_USER'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
EOF

echo ""
echo "✅ Installazione completata!"
echo "🔗 Accedi a phpMyAdmin all'indirizzo:"
echo "    http://localhost/phpmyadmin&quot;
echo ""
echo "👤 Credenziali di accesso:"
echo "    Utente: $PMA_USER"
echo "    Password: $PMA_PASS"