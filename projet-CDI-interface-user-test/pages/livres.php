<?php
include "connexion.php";

$reponse = $pdo->query("SELECT * FROM livres");
$livres = $reponse->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($livres);
?>