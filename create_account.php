<?php
require_once "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if ($username === '' || $password === '' || $passwordConfirm === '') {
    header("Location: register.php?error=missing");
    exit();
}

if ($password !== $passwordConfirm) {
    header("Location: register.php?error=mismatch");
    exit();
}

try {
    $check = $pdo->prepare("SELECT id_user FROM utilisateur WHERE login = :login LIMIT 1");
    $check->execute(['login' => $username]);
    if ($check->fetch(PDO::FETCH_ASSOC)) {
        header("Location: register.php?error=exists");
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare("INSERT INTO utilisateur (login, password, role) VALUES (:login, :password, :role)");
    $insert->execute([
        'login' => $username,
        'password' => $hashedPassword,
        'role' => 0
    ]);

    header("Location: register.php?created=1");
    exit();
} catch (PDOException $e) {
    header("Location: register.php?error=server");
    exit();
}
?>
