<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

include __DIR__ . "/connexion.php";

$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : null;

try {
    if ($categorie) {
        $stmt = $pdo->prepare("SELECT * FROM livres WHERE categorie = ? ORDER BY titre ASC");
        $stmt->execute([$categorie]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM livres ORDER BY titre ASC");
        $stmt->execute();
    }

    $livres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($livres, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>