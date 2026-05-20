<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['id_user']) || (int)($_SESSION['role'] ?? -1) !== 1) {
    header('Location: login.php?error=forbidden');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$roleInput = $_POST['role'] ?? '3';
$role = is_numeric($roleInput) ? (int)$roleInput : -1;

if ($username === '' || $password === '' || $passwordConfirm === '' || !in_array($role, [1, 2, 3, 4], true)) {
    header('Location: register.php?error=missing');
    exit();
}

if ($password !== $passwordConfirm) {
    header('Location: register.php?error=mismatch');
    exit();
}

try {
    $check = $pdo->prepare('SELECT id FROM utilisateur WHERE nom = :login LIMIT 1');
    $check->execute(['login' => $username]);
    if ($check->fetch(PDO::FETCH_ASSOC)) {
        header('Location: register.php?error=exists');
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $nextUserId = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM utilisateur')->fetchColumn();
    if ($nextUserId <= 0) {
        $nextUserId = 1;
    }

    $insert = $pdo->prepare('INSERT INTO utilisateur (id, nom, password, role) VALUES (:id, :login, :password, :role)');
    $insert->execute([
        'id' => $nextUserId,
        'login' => $username,
        'password' => $hashedPassword,
        'role' => $role,
    ]);

    header('Location: register.php?created=1');
    exit();
} catch (PDOException $e) {
    header('Location: register.php?error=server');
    exit();
}
?>
