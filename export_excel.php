<?php
/**
 * File ini berfungsi sebagai sumber data untuk Microsoft Excel.
 * Mengambil data dari MySQL database 'technopreneur' dan mengonversinya ke format CSV.
 */

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pesanan_estate_no4.csv');

// Konfigurasi Database - Diperbarui sesuai input Anda
$host = "localhost";
$db_name = "technopreneur";
$username = "theestateadmin"; // Username database Anda
$password = "442644";         // Password database Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data pesanan dari tabel orders
    $stmt = $pdo->query("SELECT order_id, customer_name, whatsapp, method, total_price, payment_method, status, created_at FROM orders ORDER BY created_at DESC");
    
    // Buka output stream untuk menulis CSV
    $output = fopen('php://output', 'w');

    // Menentukan judul kolom untuk file Excel (Header)
    fputcsv($output, array(
        'ID Pesanan', 
        'Nama Pelanggan', 
        'WhatsApp', 
        'Metode Pengambilan', 
        'Total Harga', 
        'Metode Pembayaran', 
        'Status', 
        'Tanggal & Waktu'
    ));

    // Memasukkan data pesanan baris demi baris ke dalam CSV
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    // Menampilkan pesan jika terjadi kesalahan koneksi
    die("Kesalahan Koneksi: " . $e->getMessage());
}
?>