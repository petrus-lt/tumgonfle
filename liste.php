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

if (isset($_GET['paye'])) {
    $stmt = $pdo->prepare("UPDATE gonflages SET paye = 1 WHERE id = ?");
    $stmt->execute([$_GET['paye']]);
}

$gonflages = $pdo->query("SELECT * FROM gonflages ORDER BY date_gonflage DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des paiements</title>
    <style>
        body { font-family: -apple-system, sans-serif; margin: 10px; background: #f4f4f4; color: #333; }
        nav { margin-bottom: 20px; text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        nav a { background: #eee; padding: 8px 12px; border-radius: 4px; text-decoration: none; color: #007bff; font-size: 0.9em; font-weight: bold; border: 1px solid #ccc; }
        .container { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { border-bottom: 1px solid #ddd; padding: 12px 8px; text-align: left; font-size: 0.9em; }
        th { background-color: #f8f9fa; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-no { color: #dc3545; font-weight: bold; }
        .btn-paye { background: #28a745; color: white; padding: 6px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8em; }
    </style>
</head>
<body>
    <nav>
        <a href="index.php">Calculateur</a>
        <a href="liste.php"><b>Liste des factures</b></a>
        <a href="stats.php">Statistiques</a>
        <a href="export.php">Exporter CSV</a>
    </nav>
    <div class="container">
    <h2>Historique des Gonflages</h2>
    <table>
        <tr>
            <th>Date</th>
            <th>Client</th>
            <th>Volume Bloc</th>
            <th>O2 utilisé</th>
            <th>Prix</th>
            <th>Statut</th>
            <th>Action</th>
        </tr>
        <?php foreach ($gonflages as $g): ?>
        <tr>
            <td><?php echo $g['date_gonflage']; ?></td>
            <td><?php echo htmlspecialchars($g['proprietaire']); ?></td>
            <td><?php echo $g['volume_bloc']; ?>L</td>
            <td><?php echo $g['litres_o2_utilises']; ?> L</td>
            <td><?php echo $g['prix_facture']; ?> €</td>
            <td class="<?php echo $g['paye'] ? 'status-ok' : 'status-no'; ?>">
                <?php echo $g['paye'] ? 'Payé' : 'À payer'; ?>
            </td>
            <td>
                <?php if (!$g['paye']): ?>
                    <a href="liste.php?paye=<?php echo $g['id']; ?>">Marquer payé</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
