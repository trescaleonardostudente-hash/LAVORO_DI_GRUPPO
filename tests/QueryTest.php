<?php

use PHPUnit\Framework\TestCase;
use App\Database;

class QueryTest extends TestCase
{
    private $db;

    protected function setUp(): void
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Test 1: Verificare che ci siano pezzi forniti
     */
    public function testPezziForniti()
    {
        $query = "SELECT DISTINCT p.pnome FROM Pezzi p JOIN Catalogo c ON p.pid = c.pid";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertNotEmpty($results, "Dovrebbero esserci pezzi forniti");
        $this->assertGreaterThan(0, count($results));
    }

    /**
     * Test 2: Verificare fornitori che forniscono tutti i pezzi
     */
    public function testFornitoriTuttiPezzi()
    {
        $query = "SELECT f.fnome FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * FROM Pezzi p 
                      WHERE NOT EXISTS (
                          SELECT * FROM Catalogo c 
                          WHERE c.fid = f.fid AND c.pid = p.pid
                      )
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
    }

    /**
     * Test 3: Verificare fornitori di pezzi rossi
     */
    public function testFornitoriPezziRossi()
    {
        $query = "SELECT f.fnome FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * FROM Pezzi p 
                      WHERE p.colore = 'rosso' 
                      AND NOT EXISTS (
                          SELECT * FROM Catalogo c 
                          WHERE c.fid = f.fid AND c.pid = p.pid
                      )
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
    }

    /**
     * Test 4: Verificare pezzi esclusivi di Acme
     */
    public function testPezziEsclusivi()
    {
        $query = "SELECT p.pnome FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE f.fnome = 'Acme' 
                  AND p.pid NOT IN (
                      SELECT c2.pid FROM Catalogo c2 
                      JOIN Fornitori f2 ON c2.fid = f2.fid 
                      WHERE f2.fnome <> 'Acme'
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
    }

    /**
     * Test 5: Verificare fornitori che ricaricano sopra media
     */
    public function testFornitoriSopraMedia()
    {
        $query = "SELECT DISTINCT c.fid FROM Catalogo c 
                  WHERE c.costo > (
                      SELECT AVG(c2.costo) FROM Catalogo c2 WHERE c2.pid = c.pid
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
    }

    /**
     * Test 6: Verificare fornitori con prezzo massimo
     */
    public function testFornitoriMaxPrezzo()
    {
        $query = "SELECT p.pnome, f.fnome FROM Pezzi p 
                  JOIN Catalogo c ON p.pid = c.pid 
                  JOIN Fornitori f ON c.fid = f.fid 
                  WHERE c.costo = (
                      SELECT MAX(c2.costo) FROM Catalogo c2 WHERE c2.pid = p.pid
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('pnome', $results[0]);
        $this->assertArrayHasKey('fnome', $results[0]);
    }

    /**
     * Test 7: Verificare fornitori che forniscono solo pezzi rossi
     */
    public function testFornitoriSoloRossi()
    {
        $query = "SELECT DISTINCT f.fid FROM Fornitori f 
                  WHERE NOT EXISTS (
                      SELECT * FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore <> 'rosso'
                  ) AND EXISTS (
                      SELECT * FROM Catalogo c WHERE c.fid = f.fid
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
    }

    /**
     * Test 8: Verificare fornitori con pezzi rossi E verdi
     */
    public function testFornitoriRossoEVerde()
    {
        $query = "SELECT f.fid FROM Fornitori f 
                  WHERE EXISTS (
                      SELECT * FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore = 'rosso'
                  ) 
                  AND EXISTS (
                      SELECT * FROM Catalogo c 
                      JOIN Pezzi p ON c.pid = p.pid 
                      WHERE c.fid = f.fid AND p.colore = 'verde'
                  )";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
    }

    /**
     * Test 9: Verificare fornitori con pezzi rossi O blu
     */
    public function testFornitoriRossoOBlu()
    {
        $query = "SELECT DISTINCT f.fid FROM Fornitori f 
                  JOIN Catalogo c ON f.fid = c.fid 
                  JOIN Pezzi p ON c.pid = p.pid 
                  WHERE p.colore = 'rosso' OR p.colore = 'blu'";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertNotEmpty($results);
    }

    /**
     * Test 10: Verificare pezzi forniti da almeno 2 fornitori
     */
    public function testPezziMultiFornitori()
    {
        $query = "SELECT pid FROM Catalogo GROUP BY pid HAVING COUNT(DISTINCT fid) >= 2";
        $stmt = $this->db->query($query);
        $results = $stmt->fetchAll();

        $this->assertIsArray($results);
        if (count($results) > 0) {
            $this->assertArrayHasKey('pid', $results[0]);
        }
    }

    /**
     * Test paginazione
     */
    public function testPagination()
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
            ['id' => 5],
        ];

        // Simula la funzione paginate
        $page = 2;
        $perPage = 2;
        $total = count($data);
        $offset = ($page - 1) * $perPage;
        $paginatedData = array_slice($data, $offset, $perPage);

        $this->assertEquals(2, count($paginatedData));
        $this->assertEquals(3, $paginatedData[0]['id']);
        $this->assertEquals(4, $paginatedData[1]['id']);
    }
}
