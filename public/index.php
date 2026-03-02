<?php

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Carica variabili d'ambiente da .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$app = AppFactory::create();
$app->setBasePath((function () {
    return str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
})());

// Middleware per gestire gli errori
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Helper per la paginazione
function paginate($data, $page = 1, $perPage = 10)
{
    $offset = ($page - 1) * $perPage;
    $paginatedData = array_slice($data, $offset, $perPage);

    return [
        'data' => $paginatedData
    ];
}

// Helper per inviare risposta JSON
function jsonResponse(Response $response, $data, $status = 200)
{
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}

/**
 * ENDPOINT 1: Trovare i pnome dei pezzi per cui esiste un qualche fornitore
 * GET /api/pezzi-forniti
 */
$app->get('/api/pezzi-forniti', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT p.pnome 
                  FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid";

        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 2: Trovare gli fnome dei fornitori che forniscono ogni pezzo
 * GET /api/fornitori-tutti-pezzi
 */
$app->get('/api/fornitori-tutti-pezzi', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT f.fnome 
                  FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * 
                      FROM Pezzi p 
                      WHERE NOT EXISTS (
                          SELECT * 
                          FROM Catalogo c 
                          WHERE c.fid = f.fid AND c.pid = p.pid
                      )
                  )";

        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 3: Trovare gli fnome dei fornitori che forniscono tutti i pezzi di un determinato colore
 * GET /api/fornitori-pezzi-colore?colore=rosso
 */
$app->get('/api/fornitori-pezzi-colore', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore = $params['colore'] ?? 'rosso';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT f.fnome 
                  FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * 
                      FROM Pezzi p 
                      WHERE p.colore = :colore 
                      AND NOT EXISTS (
                          SELECT * 
                          FROM Catalogo c 
                          WHERE c.fid = f.fid AND c.pid = p.pid
                      )
                  )";

        $stmt = $db->prepare($query);
        $stmt->execute(['colore' => $colore]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 4: Trovare i pnome dei pezzi forniti da un fornitore specifico e da nessun altro
 * GET /api/pezzi-esclusivi?fornitore=Acme
 */
$app->get('/api/pezzi-esclusivi', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $fornitore = $params['fornitore'] ?? 'Acme';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT p.pnome 
                  FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE f.fnome = :fornitore 
                  AND p.pid NOT IN (
                      SELECT c2.pid 
                      FROM Catalogo c2 
                      JOIN Fornitori f2 ON c2.fid = f2.fid 
                      WHERE f2.fnome <> :fornitore2
                  )";

        $stmt = $db->prepare($query);
        $stmt->execute(['fornitore' => $fornitore, 'fornitore2' => $fornitore]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 5: Trovare i fid dei fornitori che ricaricano su alcuni pezzi più del costo medio
 * GET /api/fornitori-sopra-media
 */
$app->get('/api/fornitori-sopra-media', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT c.fid, f.fnome 
                  FROM Catalogo c 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE c.costo > (
                      SELECT AVG(c2.costo) 
                      FROM Catalogo c2 
                      WHERE c2.pid = c.pid
                  )";

        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 6: Per ciascun pezzo, trovare gli fnome dei fornitori che ricaricano di più
 * GET /api/fornitori-max-prezzo
 */
$app->get('/api/fornitori-max-prezzo', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT p.pnome, f.fnome, c.costo 
                  FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE c.costo = (
                      SELECT MAX(c2.costo) 
                      FROM Catalogo c2 
                      WHERE c2.pid = p.pid
                  )";

        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 7: Trovare i fid dei fornitori che forniscono SOLO pezzi di un determinato colore
 * GET /api/fornitori-solo-colore?colore=rosso
 */
$app->get('/api/fornitori-solo-colore', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore = $params['colore'] ?? 'rosso';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT f.fid, f.fnome 
                  FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * 
                      FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore <> :colore
                  ) AND EXISTS (
                      SELECT * 
                      FROM Catalogo c 
                      WHERE c.fid = f.fid
                  )";

        $stmt = $db->prepare($query);
        $stmt->execute(['colore' => $colore]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 8: Trovare i fid dei fornitori che forniscono un pezzo di colore1 E un pezzo di colore2
 * GET /api/fornitori-con-colori?colore1=rosso&colore2=verde
 */
$app->get('/api/fornitori-con-colori', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore1 = $params['colore1'] ?? 'rosso';
        $colore2 = $params['colore2'] ?? 'verde';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT f.fid, f.fnome 
                  FROM Fornitori f 
                  WHERE EXISTS (
                      SELECT * 
                      FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore = :colore1
                  ) 
                  AND EXISTS (
                      SELECT * 
                      FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore = :colore2
                  )";

        $stmt = $db->prepare($query);
        $stmt->execute(['colore1' => $colore1, 'colore2' => $colore2]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 9: Trovare i fid dei fornitori che forniscono un pezzo di colore1 O di colore2
 * GET /api/fornitori-o-colori?colore1=rosso&colore2=verde
 */
$app->get('/api/fornitori-o-colori', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore1 = $params['colore1'] ?? 'rosso';
        $colore2 = $params['colore2'] ?? 'verde';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT f.fid, f.fnome 
                  FROM Fornitori f 
                  JOIN Catalogo c ON f.fid = c.fid 
                  JOIN Pezzi p ON c.pid = p.pid 
                  WHERE p.colore = :colore1 OR p.colore = :colore2";

        $stmt = $db->prepare($query);
        $stmt->execute(['colore1' => $colore1, 'colore2' => $colore2]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT 10: Trovare i pid dei pezzi forniti da almeno N fornitori
 * GET /api/pezzi-multi-fornitori?min_fornitori=2
 */
$app->get('/api/pezzi-multi-fornitori', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $minFornitori = $params['min_fornitori'] ?? 2;
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT c.pid, p.pnome, COUNT(DISTINCT c.fid) as num_fornitori 
                  FROM Catalogo c 
                  JOIN Pezzi p ON c.pid = p.pid 
                  GROUP BY c.pid, p.pnome 
                  HAVING COUNT(DISTINCT c.fid) >= :min_fornitori";

        $stmt = $db->prepare($query);
        $stmt->execute(['min_fornitori' => $minFornitori]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT NUMERATI /1, /2, /3, ... per accesso diretto alle query
 */

// Query 1: Pezzi forniti
$app->get('/1', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT p.pnome FROM Pezzi p JOIN Catalogo c ON p.pid = c.pid";
        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 2: Fornitori che forniscono tutti i pezzi
$app->get('/2', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT f.fnome FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * FROM Pezzi p 
                      WHERE NOT EXISTS (
                          SELECT * FROM Catalogo c 
                          WHERE c.fid = f.fid AND c.pid = p.pid
                      )
                  )";
        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 3: Fornitori che forniscono tutti i pezzi rossi
$app->get('/3', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore = $params['colore'] ?? 'rosso';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT f.fnome FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * FROM Pezzi p 
                      WHERE p.colore = :colore 
                      AND NOT EXISTS (
                          SELECT * FROM Catalogo c 
                          WHERE c.fid = f.fid AND c.pid = p.pid
                      )
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute(['colore' => $colore]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 4: Pezzi forniti dalla Acme e da nessun altro
$app->get('/4', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $fornitore = $params['fornitore'] ?? 'Acme';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT p.pnome FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE f.fnome = :fornitore 
                  AND p.pid NOT IN (
                      SELECT c2.pid FROM Catalogo c2 
                      JOIN Fornitori f2 ON c2.fid = f2.fid 
                      WHERE f2.fnome <> :fornitore2
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute(['fornitore' => $fornitore, 'fornitore2' => $fornitore]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 5: Fornitori che ricaricano sopra la media
$app->get('/5', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT c.fid, f.fnome FROM Catalogo c 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE c.costo > (
                      SELECT AVG(c2.costo) FROM Catalogo c2 WHERE c2.pid = c.pid
                  )";
        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 6: Fornitori con prezzo massimo per pezzo
$app->get('/6', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT p.pnome, f.fnome, c.costo FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE c.costo = (
                      SELECT MAX(c2.costo) FROM Catalogo c2 WHERE c2.pid = p.pid
                  )";
        $stmt = $db->query($query);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 7: Fornitori che forniscono SOLO pezzi rossi
$app->get('/7', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore = $params['colore'] ?? 'rosso';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT f.fid, f.fnome FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore <> :colore
                  ) AND EXISTS (
                      SELECT * FROM Catalogo c WHERE c.fid = f.fid
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute(['colore' => $colore]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 8: Fornitori con pezzi rossi E verdi
$app->get('/8', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore1 = $params['colore1'] ?? 'rosso';
        $colore2 = $params['colore2'] ?? 'verde';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT f.fid, f.fnome FROM Fornitori f 
                  WHERE EXISTS (
                      SELECT * FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore = :colore1
                  ) AND EXISTS (
                      SELECT * FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore = :colore2
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute(['colore1' => $colore1, 'colore2' => $colore2]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 9: Fornitori con pezzi rossi O verdi
$app->get('/9', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $colore1 = $params['colore1'] ?? 'rosso';
        $colore2 = $params['colore2'] ?? 'verde';
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT DISTINCT f.fid, f.fnome FROM Fornitori f 
                  JOIN Catalogo c ON f.fid = c.fid 
                  JOIN Pezzi p ON c.pid = p.pid 
                  WHERE p.colore = :colore1 OR p.colore = :colore2";
        $stmt = $db->prepare($query);
        $stmt->execute(['colore1' => $colore1, 'colore2' => $colore2]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Query 10: Pezzi forniti da almeno N fornitori
$app->get('/10', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $params = $request->getQueryParams();
        $minFornitori = $params['min_fornitori'] ?? 2;
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = "SELECT c.pid, p.pnome, COUNT(DISTINCT c.fid) as num_fornitori 
                  FROM Catalogo c 
                  JOIN Pezzi p ON c.pid = p.pid 
                  GROUP BY c.pid, p.pnome 
                  HAVING COUNT(DISTINCT c.fid) >= :min_fornitori";
        $stmt = $db->prepare($query);
        $stmt->execute(['min_fornitori' => $minFornitori]);
        $results = $stmt->fetchAll();

        $data = paginate($results, $page, $perPage);
        return jsonResponse($response, $data);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

/**
 * ENDPOINT UNICO: Restituisce tutte le 10 query insieme
 * GET /all
 */
$app->get('/all', function (Request $request, Response $response) {
    try {
        $db = Database::getInstance()->getConnection();
        $results = [];

        // Query 1: Pezzi forniti
        $query1 = "SELECT DISTINCT p.pnome FROM Pezzi p JOIN Catalogo c ON p.pid = c.pid";
        $stmt1 = $db->query($query1);
        $results['query_1_pezzi_forniti'] = $stmt1->fetchAll();

        // Query 2: Fornitori che forniscono tutti i pezzi
        $query2 = "SELECT f.fnome FROM Fornitori f 
                   WHERE NOT EXISTS (
                       SELECT * FROM Pezzi p 
                       WHERE NOT EXISTS (
                           SELECT * FROM Catalogo c 
                           WHERE c.fid = f.fid AND c.pid = p.pid
                       )
                   )";
        $stmt2 = $db->query($query2);
        $results['query_2_fornitori_tutti_pezzi'] = $stmt2->fetchAll();

        // Query 3: Fornitori che forniscono tutti i pezzi rossi
        $query3 = "SELECT f.fnome FROM Fornitori f 
                   WHERE NOT EXISTS (
                       SELECT * FROM Pezzi p 
                       WHERE p.colore = 'rosso' 
                       AND NOT EXISTS (
                           SELECT * FROM Catalogo c 
                           WHERE c.fid = f.fid AND c.pid = p.pid
                       )
                   )";
        $stmt3 = $db->query($query3);
        $results['query_3_fornitori_pezzi_rossi'] = $stmt3->fetchAll();

        // Query 4: Pezzi forniti dalla Acme e da nessun altro
        $query4 = "SELECT p.pnome FROM Pezzi p 
                   JOIN Catalogo c ON p.pid = c.pid 
                   JOIN Fornitori f ON c.fid = f.fid 
                   WHERE f.fnome = 'Acme' 
                   AND p.pid NOT IN (
                       SELECT c2.pid FROM Catalogo c2 
                       JOIN Fornitori f2 ON c2.fid = f2.fid 
                       WHERE f2.fnome <> 'Acme'
                   )";
        $stmt4 = $db->query($query4);
        $results['query_4_pezzi_esclusivi_acme'] = $stmt4->fetchAll();

        // Query 5: Fornitori che ricaricano sopra la media
        $query5 = "SELECT DISTINCT c.fid, f.fnome FROM Catalogo c 
                   JOIN Fornitori f ON c.fid = f.fid 
                   WHERE c.costo > (
                       SELECT AVG(c2.costo) FROM Catalogo c2 WHERE c2.pid = c.pid
                   )";
        $stmt5 = $db->query($query5);
        $results['query_5_fornitori_sopra_media'] = $stmt5->fetchAll();

        // Query 6: Fornitori con prezzo massimo per pezzo
        $query6 = "SELECT p.pnome, f.fnome, c.costo FROM Pezzi p 
                   JOIN Catalogo c ON p.pid = c.pid 
                   JOIN Fornitori f ON c.fid = f.fid 
                   WHERE c.costo = (
                       SELECT MAX(c2.costo) FROM Catalogo c2 WHERE c2.pid = p.pid
                   )";
        $stmt6 = $db->query($query6);
        $results['query_6_fornitori_max_prezzo'] = $stmt6->fetchAll();

        // Query 7: Fornitori che forniscono SOLO pezzi rossi
        $query7 = "SELECT DISTINCT f.fid, f.fnome FROM Fornitori f 
                   WHERE NOT EXISTS (
                       SELECT * FROM Catalogo c 
                       JOIN Pezzi p ON c.pid = p.pid 
                       WHERE c.fid = f.fid AND p.colore <> 'rosso'
                   ) AND EXISTS (
                       SELECT * FROM Catalogo c WHERE c.fid = f.fid
                   )";
        $stmt7 = $db->query($query7);
        $results['query_7_fornitori_solo_rossi'] = $stmt7->fetchAll();

        // Query 8: Fornitori con pezzi rossi E verdi
        $query8 = "SELECT f.fid, f.fnome FROM Fornitori f 
                   WHERE EXISTS (
                       SELECT * FROM Catalogo c 
                       JOIN Pezzi p ON c.pid = p.pid 
                       WHERE c.fid = f.fid AND p.colore = 'rosso'
                   ) AND EXISTS (
                       SELECT * FROM Catalogo c 
                       JOIN Pezzi p ON c.pid = p.pid 
                       WHERE c.fid = f.fid AND p.colore = 'verde'
                   )";
        $stmt8 = $db->query($query8);
        $results['query_8_fornitori_rosso_e_verde'] = $stmt8->fetchAll();

        // Query 9: Fornitori con pezzi rossi O verdi
        $query9 = "SELECT DISTINCT f.fid, f.fnome FROM Fornitori f 
                   JOIN Catalogo c ON f.fid = c.fid 
                   JOIN Pezzi p ON c.pid = p.pid 
                   WHERE p.colore = 'rosso' OR p.colore = 'verde'";
        $stmt9 = $db->query($query9);
        $results['query_9_fornitori_rosso_o_verde'] = $stmt9->fetchAll();

        // Query 10: Pezzi forniti da almeno 2 fornitori
        $query10 = "SELECT c.pid, p.pnome, COUNT(DISTINCT c.fid) as num_fornitori 
                    FROM Catalogo c 
                    JOIN Pezzi p ON c.pid = p.pid 
                    GROUP BY c.pid, p.pnome 
                    HAVING COUNT(DISTINCT c.fid) >= 2";
        $stmt10 = $db->query($query10);
        $results['query_10_pezzi_multi_fornitori'] = $stmt10->fetchAll();

        return jsonResponse($response, [
            'message' => 'Risultati di tutte le 10 query',
            'total_queries' => 10,
            'results' => $results
        ]);
    } catch (Exception $e) {
        return jsonResponse($response, ['error' => $e->getMessage()], 500);
    }
});

// Route di default
$app->get('/frontend', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/frontend.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
});

// Route di default
$app->get('/', function (Request $request, Response $response) {
    $endpoints = [
        'Frontend' => '/frontend - Interfaccia web Bootstrap per eseguire le query',
        'Endpoint unico (tutte le query)' => '/all - Restituisce i risultati di tutte le 10 query insieme',
        'Accesso query numerato' => [
            '1' => '/1 - Pezzi forniti',
            '2' => '/2 - Fornitori che forniscono tutti i pezzi',
            '3' => '/3 - Fornitori che forniscono tutti i pezzi di un colore',
            '4' => '/4 - Pezzi esclusivi di un fornitore',
            '5' => '/5 - Fornitori che ricaricano sopra la media',
            '6' => '/6 - Fornitori con prezzo massimo per pezzo',
            '7' => '/7 - Fornitori che vendono solo pezzi di un colore',
            '8' => '/8 - Fornitori con pezzi di entrambi i colori (AND)',
            '9' => '/9 - Fornitori con pezzi di almeno uno dei colori (OR)',
            '10' => '/10 - Pezzi forniti da più fornitori'
        ],
        'Endpoints descrittivi' => [
            '/api/pezzi-forniti',
            '/api/fornitori-tutti-pezzi',
            '/api/fornitori-pezzi-colore?colore=rosso',
            '/api/pezzi-esclusivi?fornitore=Acme',
            '/api/fornitori-sopra-media',
            '/api/fornitori-max-prezzo',
            '/api/fornitori-solo-colore?colore=rosso',
            '/api/fornitori-con-colori?colore1=rosso&colore2=verde',
            '/api/fornitori-o-colori?colore1=rosso&colore2=verde',
            '/api/pezzi-multi-fornitori?min_fornitori=2'
        ]
    ];

    $data = [
        'message' => 'API Forniture - Verifica a Sorpresa',
        'endpoints' => $endpoints,
        'note' => 'Le risposte delle query restituiscono solo il campo data'
    ];

    return jsonResponse($response, $data);
});

$app->run();
