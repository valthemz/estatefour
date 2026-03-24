<?php
/**
 * Estate No. 4 - Backend API Bridge
 * Menangani interaksi antara Frontend (HTML/JS) dan MySQL Database
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Konfigurasi Database
$host = "localhost";
$db_name = "technopreneur";
$username = "root"; // Ganti dengan username database hosting Anda
$password = "";     // Ganti dengan password database hosting Anda

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["success" => false, "message" => "Gagal terhubung ke database"]);
    exit;
}

$action = $_GET['action'] ?? '';

// --- BAGIAN 1: AUTENTIKASI ADMIN ---

// Registrasi Admin Baru
if ($action == 'register') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['username']) || empty($data['password'])) {
        echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
        exit;
    }
    
    // Hash password demi keamanan
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    try {
        $sql = "INSERT INTO admins (username, password) VALUES (:user, :pass)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user' => $data['username'], ':pass' => $hashed_password]);
        echo json_encode(["success" => true, "message" => "Admin berhasil didaftarkan"]);
    } catch(Exception $e) {
        echo json_encode(["success" => false, "message" => "Username sudah digunakan"]);
    }
}

// Login Admin
elseif ($action == 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    $sql = "SELECT * FROM admins WHERE username = :user";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':user' => $data['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($data['password'], $user['password'])) {
        echo json_encode(["success" => true, "username" => $user['username']]);
    } else {
        echo json_encode(["success" => false, "message" => "Username atau Password salah"]);
    }
}

// --- BAGIAN 2: MANAJEMEN PESANAN ---

// Menyimpan Pesanan Baru (Mendukung Unggah File)
elseif ($action == 'save') {
    // Gunakan $_POST karena data dikirim via FormData
    $orderId = $_POST['orderId'] ?? '';
    $name = $_POST['name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $method = $_POST['method'] ?? '';
    $cart = $_POST['cart'] ?? '';
    $total = $_POST['totalPrice'] ?? 0;
    $payment = $_POST['payment'] ?? '';
    
    $receipt_filename = null;

    // Logika unggah gambar struk
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $target_dir = "uploads/";
        
        // Buat folder jika belum ada
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $extension = pathinfo($_FILES["receipt"]["name"], PATHINFO_EXTENSION);
        // Nama file unik: IDPesanan_Waktu.ekstensi
        $receipt_filename = $orderId . "_" . time() . "." . $extension;
        move_uploaded_file($_FILES["receipt"]["tmp_name"], $target_dir . $receipt_filename);
    }

    try {
        $sql = "INSERT INTO orders (order_id, customer_name, whatsapp, method, items_json, total_price, payment_method, receipt_path) 
                VALUES (:oid, :name, :phone, :method, :items, :total, :pay, :receipt)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':oid'     => $orderId,
            ':name'    => $name,
            ':phone'   => $phone,
            ':method'  => $method,
            ':items'   => $cart,
            ':total'   => $total,
            ':pay'     => $payment,
            ':receipt' => $receipt_filename
        ]);
        echo json_encode(["success" => true]);
    } catch(Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}

// Menampilkan Daftar Pesanan
elseif ($action == 'list') {
    $stmt = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Memperbarui Status (Pending -> Ready -> Completed)
elseif ($action == 'update_status') {
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $conn->prepare("UPDATE orders SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $data['status'], ':id' => $data['id']]);
    echo json_encode(["success" => true]);
}

// Menghapus Pesanan
elseif ($action == 'delete') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        // Hapus file gambar jika ada sebelum menghapus baris database
        $stmt_img = $conn->prepare("SELECT receipt_path FROM orders WHERE id = :id");
        $stmt_img->execute([':id' => $id]);
        $row = $stmt_img->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['receipt_path']) {
            @unlink("uploads/" . $row['receipt_path']);
        }

        $stmt = $conn->prepare("DELETE FROM orders WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(["success" => true]);
    }
}
?>