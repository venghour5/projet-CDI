<?php
$host = "localhost";
$dbname = "cdi_v2";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log('Erreur connexion base: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur de connexion à la base de données.');
}
?>
