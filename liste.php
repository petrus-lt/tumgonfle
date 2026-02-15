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
    <title>Suivi des paiements</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-ok { color: green; font-weight: bold; }
        .status-no { color: red; }
    </style>
</head>
<body>
    <nav><a href="index.php">Retour Calculateur</a></nav>
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
