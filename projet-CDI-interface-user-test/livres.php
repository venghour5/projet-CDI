<?php
include "connexion.php";

$id = $_GET['id'];

$sql = "SELECT * FROM livres WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$livre = $stmt->fetch(PDO::FETCH_ASSOC);
?>