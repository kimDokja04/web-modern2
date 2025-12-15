<?php
// Izinkan akses dari domain manapun (penting untuk React/CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// Pastikan PHP tidak meng-outputkan error HTML yang memecah JSON respon
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(0);
// --- KONFIGURASI DATABASE: menggunakan SQLite untuk kemudahan lokal ---
$dbFile = __DIR__ . '/db.sqlite';

// Buat koneksi PDO ke SQLite; file akan dibuat otomatis jika belum ada
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Buat tabel jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        price REAL NOT NULL
    )");
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Koneksi database gagal: " . $e->getMessage()]);
    exit();
}

// Ambil metode HTTP (GET, POST, PUT, DELETE)
// Ambil metode HTTP (GET, POST, PUT, DELETE)
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    // --- READ (GET) ---
    case 'GET':
        try {
            $stmt = $pdo->query("SELECT id, name, price FROM products ORDER BY id DESC");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(["success" => true, "data" => $products]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
        break;

    // --- CREATE (POST) ---
    case 'POST':
        $name = trim($input['name'] ?? '');
        $price = floatval($input['price'] ?? 0);
        if ($name === '' || $price <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Nama dan harga harus valid."]);
            exit();
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");
            $stmt->execute([':name' => $name, ':price' => $price]);
            echo json_encode(["success" => true, "message" => "Produk berhasil ditambahkan.", "id" => $pdo->lastInsertId()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Gagal menambahkan produk: " . $e->getMessage()]);
        }
        break;

    // --- UPDATE (PUT) ---
    case 'PUT':
        $id = intval($_GET['id'] ?? ($input['id'] ?? 0));
        $name = trim($input['name'] ?? '');
        $price = floatval($input['price'] ?? 0);

        if ($id <= 0 || $name === '' || $price <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID, nama, dan harga harus valid."]);
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE products SET name = :name, price = :price WHERE id = :id");
            $stmt->execute([':name' => $name, ':price' => $price, ':id' => $id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Produk berhasil diperbarui."]);
            } else {
                echo json_encode(["success" => true, "message" => "Produk berhasil diperbarui, atau tidak ada perubahan data."]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Gagal memperbarui produk: " . $e->getMessage()]);
        }
        break;

    // --- DELETE (DELETE) ---
    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "ID produk harus diisi."]);
            exit();
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Produk berhasil dihapus."]);
            } else {
                http_response_code(404);
                echo json_encode(["success" => false, "message" => "Produk tidak ditemukan."]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Gagal menghapus produk: " . $e->getMessage()]);
        }
        break;

    // --- OPSI (Untuk pre-flight CORS) ---
    case 'OPTIONS':
        http_response_code(200);
        exit();
        
    default:
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Metode tidak diizinkan."]);
        break;
}