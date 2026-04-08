<?php
session_start();
require_once "db.php";

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header("Location: login.php?error=invalid");
    exit();
}

$sql = "SELECT id_user, login, password, role FROM utilisateur WHERE login = :login LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['login' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $ok = false;

    if (password_verify($password, $user['password'])) {
        $ok = true;
    }

    if ($password === $user['password']) {
        $ok = true;
    }

    if ($ok) {
        $role = isset($user['role']) ? (int)$user['role'] : -1;
        if ($role !== 0 && $role !== 1) {
            header("Location: login.php?error=invalid");
            exit();
        }

        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['login'] = $user['login'];
        $_SESSION['role'] = $role;

        if ($role === 1) {
            header("Location: cdi.php");
        } else {
            header("Location: vehicule.php");
        }
        exit();
    }
}

header("Location: login.php?error=invalid");
exit();
?>
