<?php
session_start();
require_once "db.php";

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header("Location: login.php?error=invalid");
    exit();
}

$sql = "SELECT id, nom, password, role FROM utilisateur WHERE nom = :login LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['login' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    if (password_verify($password, $user['password'])) {
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $rehashStmt = $pdo->prepare("UPDATE utilisateur SET password = :password WHERE id = :id_user");
            $rehashStmt->execute([
                'password' => $newHash,
                'id_user' => (int)$user['id']
            ]);
        }

        $role = isset($user['role']) ? (int)$user['role'] : -1;
        if (!in_array($role, [1, 2, 3, 4], true)) {
            header("Location: login.php?error=invalid");
            exit();
        }

        $_SESSION['id_user'] = $user['id'];
        $_SESSION['login'] = $user['nom'];
        $_SESSION['role'] = $role;

        if ($role === 2) {
            header("Location: cdi.php");
        } elseif ($role === 3) {
            header("Location: vehicule.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}

header("Location: login.php?error=invalid");
exit();
?>
