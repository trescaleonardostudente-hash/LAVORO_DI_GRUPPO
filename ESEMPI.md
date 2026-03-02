# Esempi di utilizzo API Forniture

## 🚀 Accesso rapido con endpoint numerati

### Query 1: Pezzi forniti
```bash
curl http://localhost:8000/1
```

### Query 2: Fornitori che forniscono tutti i pezzi
```bash
curl http://localhost:8000/2
```

### Query 3: Fornitori che forniscono tutti i pezzi di un colore
```bash
# Default: rosso
curl http://localhost:8000/3

# Con parametro personalizzato
curl "http://localhost:8000/3?colore=blu"
```

### Query 4: Pezzi esclusivi di un fornitore
```bash
# Default: Acme
curl http://localhost:8000/4

# Con parametro personalizzato
curl "http://localhost:8000/4?fornitore=Beta"
```

### Query 5: Fornitori che ricaricano sopra la media
```bash
curl http://localhost:8000/5
```

### Query 6: Fornitori con prezzo massimo per pezzo
```bash
curl http://localhost:8000/6
```

### Query 7: Fornitori che vendono solo pezzi di un colore
```bash
# Default: rosso
curl http://localhost:8000/7

# Con parametro personalizzato
curl "http://localhost:8000/7?colore=blu"
```

### Query 8: Fornitori con pezzi di entrambi i colori (AND)
```bash
# Default: rosso e verde
curl http://localhost:8000/8

# Con parametri personalizzati
curl "http://localhost:8000/8?colore1=rosso&colore2=blu"
```

### Query 9: Fornitori con pezzi di almeno uno dei colori (OR)
```bash
# Default: rosso o verde
curl http://localhost:8000/9

# Con parametri personalizzati
curl "http://localhost:8000/9?colore1=rosso&colore2=blu"
```

### Query 10: Pezzi forniti da almeno N fornitori
```bash
# Default: almeno 2 fornitori
curl http://localhost:8000/10

# Con parametro personalizzato
curl "http://localhost:8000/10?min_fornitori=3"
```

## 📄 Paginazione

Tutti gli endpoint supportano la paginazione:

```bash
# Prima pagina, 5 elementi per pagina
curl "http://localhost:8000/1?page=1&per_page=5"

# Seconda pagina, 10 elementi per pagina
curl "http://localhost:8000/6?page=2&per_page=10"
```

## 🔍 Formato risposta

Tutte le risposte sono in formato JSON:

```json
{
  "data": [
    { "pnome": "Vite" },
    { "pnome": "Bullone" }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total_items": 2,
    "total_pages": 1
  }
}
```

## 🌐 Test nel browser

Apri nel browser:
- http://localhost:8000 - Lista di tutti gli endpoint
- http://localhost:8000/1 - Query 1
- http://localhost:8000/6 - Query 6
- ecc...

## 📊 Test tutti gli endpoint

```bash
# Test rapido di tutte le query
for i in {1..10}; do 
  echo "Query $i:"
  curl -s "http://localhost:8000/$i" | jq '.data | length'
done
```

## 🎨 Con jq per output formattato

```bash
# Output formattato e colorato
curl -s http://localhost:8000/6 | jq

# Estrai solo i dati
curl -s http://localhost:8000/1 | jq '.data'

# Estrai un campo specifico
curl -s http://localhost:8000/1 | jq '.data[].pnome'
```
