<?php
$host = 'localhost';
$db   = 'gonflages_db';
$user = 'gonflageuser';
$pass = 'monsuperpass';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Globales
$globale = $pdo->query("SELECT SUM(litres_o2_utilises) as total_o2, SUM(prix_facture) as total_ca FROM gonflages")->fetch();

// Par personne
$par_pers = $pdo->query("SELECT proprietaire, SUM(litres_o2_utilises) as o2, SUM(prix_facture) as euros, COUNT(*) as nb 
                         FROM gonflages 
                         GROUP BY proprietaire 
                         ORDER BY o2 DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques Gonflage</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .card { background: #eee; padding: 20px; margin-bottom: 20px; border-radius: 8px; display: inline-block; min-width: 200px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        nav { margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav>
        <a href="index.php">Calculateur</a> | <a href="liste.php">Paiements</a> | <b>Statistiques</b>
    </nav>

    <h2>Statistiques Globales</h2>
    <div class="card">
        <strong>O2 Total consommé :</strong><br>
        <span style="font-size: 24px;"><?php echo round($globale['total_o2'], 1); ?> L</span>
    </div>
    <div class="card">
        <strong>Chiffre d'Affaires :</strong><br>
        <span style="font-size: 24px;"><?php echo round($globale['total_ca'], 2); ?> €</span>
    </div>

    <h2>Consommation par utilisateur</h2>
    <table>
        <tr>
            <th>Plongeur</th>
            <th>Nb Gonflages</th>
            <th>Total O2 (L)</th>
            <th>Total Facturé (€)</th>
        </tr>
        <?php foreach ($par_pers as $p): ?>
        <tr>
            <td><?php echo htmlspecialchars($p['proprietaire']); ?></td>
            <td><?php echo $p['nb']; ?></td>
            <td><?php echo round($p['o2'], 1); ?> L</td>
            <td><?php echo round($p['euros'], 2); ?> €</td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
