<?php
// db.php
$host = 'localhost';
$db   = 'gonflages_db';
$user = 'gonflageuser';
$pass = 'monsuperpass';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    // On active les erreurs SQL pour le developpement
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
