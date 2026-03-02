# Verifica a Sorpresa - API REST con Slim Framework

API REST che implementa 10 query SQL sul database **Forniture** utilizzando Slim Framework 4.

## 📋 Contenuto del Repository

- **database.sql** - Dump completo del database con schema e dati di test
- **ESEMPI.md** - Esempi pratici di utilizzo degli endpoints
- **src/** - Codice sorgente dell'applicazione
  - `Database.php` - Classe Singleton per la gestione della connessione PDO
- **public/** - Directory web root
  - `index.php` - File principale con tutti i 10 endpoints
  - `.htaccess` - Configurazione Apache per URL rewriting
- **tests/** - Unit tests con PHPUnit
- **composer.json** - Dipendenze del progetto

## 🚀 Installazione

### Prerequisiti
- PHP >= 7.4
- MySQL/MariaDB
- Composer
- Apache/Nginx (con mod_rewrite per Apache)

### Setup

1. **Clona il repository**
```bash
git clone https://github.com/albertogerosa/verificaasorpresa.git
cd verificaasorpresa
```

2. **Installa le dipendenze**
```bash
composer install
```

3. **Configura il database**
```bash
# Importa il database
mysql -u root -p < database.sql

# Copia e configura le variabili d'ambiente
cp .env.example .env
# Modifica .env con le tue credenziali
```

4. **Configura il web server**

**Apache:**
- Imposta `public/` come DocumentRoot
- Assicurati che mod_rewrite sia abilitato

**PHP Built-in Server (per sviluppo):**
```bash
php -S localhost:8000 -t public
```

## 📡 Endpoints disponibili

Tutti gli endpoints ritornano dati in formato **application/json** e supportano la **paginazione**.

### 🔢 Accesso rapido con endpoint numerati

Per un accesso veloce alle query, usa semplicemente `/1`, `/2`, `/3`, ..., `/10`:

```bash
curl http://localhost:8000/1  # Query 1: Pezzi forniti
curl http://localhost:8000/2  # Query 2: Fornitori che forniscono tutti i pezzi
curl http://localhost:8000/3  # Query 3: Fornitori per colore
# ... fino a /10
```

### 📋 Lista completa degli endpoint

#### Endpoint numerati (accesso rapido)

#### Endpoint numerati (accesso rapido)

1. **GET `/1`** - Pezzi forniti
2. **GET `/2`** - Fornitori che forniscono tutti i pezzi
3. **GET `/3?colore=rosso`** - Fornitori per colore specificato
4. **GET `/4?fornitore=Acme`** - Pezzi esclusivi di un fornitore
5. **GET `/5`** - Fornitori sopra media
6. **GET `/6`** - Fornitori con prezzo massimo per pezzo
7. **GET `/7?colore=rosso`** - Fornitori monocromatici
8. **GET `/8?colore1=rosso&colore2=verde`** - Fornitori con doppio colore (AND)
9. **GET `/9?colore1=rosso&colore2=verde`** - Fornitori con almeno un colore (OR)
10. **GET `/10?min_fornitori=2`** - Pezzi multi-fornitore

#### Endpoint descrittivi (API REST completa)

### 1. Pezzi Forniti
```http
GET /api/pezzi-forniti?page=1&per_page=10
```
Trova i nomi dei pezzi per cui esiste almeno un fornitore.

### 2. Fornitori Completi
```http
GET /api/fornitori-tutti-pezzi?page=1&per_page=10
```
Trova i fornitori che forniscono ogni tipologia di pezzo.

### 3. Fornitori per Colore
```http
GET /api/fornitori-pezzi-colore?colore=rosso&page=1&per_page=10
```
Trova i fornitori che forniscono tutti i pezzi di un determinato colore.

**Parametri:**
- `colore` (string, default: "rosso") - Colore dei pezzi

### 4. Pezzi Esclusivi
```http
GET /api/pezzi-esclusivi?fornitore=Acme&page=1&per_page=10
```
Trova i pezzi forniti da un fornitore specifico e da nessun altro.

**Parametri:**
- `fornitore` (string, default: "Acme") - Nome del fornitore

### 5. Fornitori Sopra Media
```http
GET /api/fornitori-sopra-media?page=1&per_page=10
```
Trova i fornitori che ricaricano su alcuni pezzi più del costo medio.

### 6. Fornitori con Prezzo Massimo
```http
GET /api/fornitori-max-prezzo?page=1&per_page=10
```
Per ciascun pezzo, trova i fornitori che applicano il prezzo più alto.

### 7. Fornitori Monocromatici
```http
GET /api/fornitori-solo-colore?colore=rosso&page=1&per_page=10
```
Trova i fornitori che forniscono SOLO pezzi di un determinato colore.

**Parametri:**
- `colore` (string, default: "rosso") - Colore esclusivo

### 8. Fornitori con Doppio Colore (AND)
```http
GET /api/fornitori-con-colori?colore1=rosso&colore2=verde&page=1&per_page=10
```
Trova i fornitori che forniscono pezzi di entrambi i colori specificati.

**Parametri:**
- `colore1` (string, default: "rosso") - Primo colore
- `colore2` (string, default: "verde") - Secondo colore

### 9. Fornitori con Almeno un Colore (OR)
```http
GET /api/fornitori-o-colori?colore1=rosso&colore2=verde&page=1&per_page=10
```
Trova i fornitori che forniscono pezzi di almeno uno dei colori specificati.

**Parametri:**
- `colore1` (string, default: "rosso") - Primo colore
- `colore2` (string, default: "verde") - Secondo colore

### 10. Pezzi Multi-Fornitore
```http
GET /api/pezzi-multi-fornitori?min_fornitori=2&page=1&per_page=10
```
Trova i pezzi forniti da almeno N fornitori.

**Parametri:**
- `min_fornitori` (integer, default: 2) - Numero minimo di fornitori

## 📊 Formato Risposta

Tutte le risposte seguono questo formato JSON:

```json
{
  "data": [
    // ... risultati della query
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total_items": 42,
    "total_pages": 5
  }
}
```

### Esempio di Risposta
```json
{
  "data": [
    {"pnome": "Vite"},
    {"pnome": "Bullone"},
    {"pnome": "Dado"}
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total_items": 3,
    "total_pages": 1
  }
}
```

## 🔧 Paginazione

Tutti gli endpoints supportano paginazione tramite query parameters:

- `page` - Numero di pagina (default: 1)
- `per_page` - Elementi per pagina (default: 10)

Esempio:
```http
GET /api/pezzi-forniti?page=2&per_page=5
```

## 🧪 Testing

Esegui i test unitari con PHPUnit:

```bash
# Esegui tutti i test
./vendor/bin/phpunit

# Esegui con coverage
./vendor/bin/phpunit --coverage-text
```

I test verificano:
- Correttezza delle query SQL
- Presenza dei campi previsti nelle risposte
- Funzionamento della paginazione

## 🗄️ Struttura Database

Il database **Forniture** contiene tre tabelle:

### Fornitori
- `fid` (VARCHAR, PK) - ID fornitore
- `fnome` (VARCHAR) - Nome fornitore
- `indirizzo` (VARCHAR) - Indirizzo

### Pezzi
- `pid` (VARCHAR, PK) - ID pezzo
- `pnome` (VARCHAR) - Nome pezzo
- `colore` (VARCHAR) - Colore

### Catalogo
- `fid` (VARCHAR, FK) - ID fornitore
- `pid` (VARCHAR, FK) - ID pezzo
- `costo` (REAL) - Costo del pezzo
- PK: (fid, pid)

## 🛠️ Tecnologie Utilizzate

- **Slim Framework 4** - Micro-framework PHP per REST API
- **PDO** - PHP Data Objects per accesso sicuro al database
- **PHPUnit** - Framework per unit testing
- **Composer** - Dependency manager
- **MySQL** - Database relazionale

## 📝 Variabili d'Ambiente

Crea un file `.env` partendo da `.env.example`:

```env
DB_HOST=localhost
DB_NAME=Forniture
DB_USER=root
DB_PASS=your_password
```

## 🔒 Sicurezza

- Tutte le query utilizzano **prepared statements** per prevenire SQL injection
- Validazione e sanitizzazione dei parametri di input
- Gestione centralizzata degli errori

## 👨‍💻 Autore

Alberto Gerosa

## 📄 Licenza

Progetto per verifica didattica