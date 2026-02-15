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

// Filtres
$mois = $_GET['mois'] ?? '';
$annee = $_GET['annee'] ?? date('Y');
$where = "WHERE 1=1";
$params = [];

if ($annee != 'all') {
    $where .= " AND YEAR(date_gonflage) = ?";
    $params[] = $annee;
    if ($mois != '') {
        $where .= " AND MONTH(date_gonflage) = ?";
        $params[] = $mois;
    }
}

// Globales avec filtre
$stmt_glob = $pdo->prepare("SELECT SUM(litres_o2_utilises) as total_o2, SUM(prix_facture) as total_ca FROM gonflages $where");
$stmt_glob->execute($params);
$globale = $stmt_glob->fetch();

// Par personne avec filtre
$stmt_pers = $pdo->prepare("SELECT proprietaire, SUM(litres_o2_utilises) as o2, SUM(prix_facture) as euros, COUNT(*) as nb
                            FROM gonflages
                            $where
                            GROUP BY proprietaire
                            ORDER BY o2 DESC");
$stmt_pers->execute($params);
$par_pers = $stmt_pers->fetchAll();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques Gonflage</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
    <a href="index.php">Calculateur</a>
    <a href="liste.php">Liste des factures</a>
    <a href="stats.php"><b>Statistiques</b></a>
    <a href="export.php">Exporter CSV</a>

    </nav>

    <form method="GET" style="background:#eee; padding:15px; border-radius:8px; margin-bottom:20px;">
        <label>Année :</label>
        <select name="annee">
            <option value="all">Toutes</option>
            <?php for($i=date('Y'); $i>=2024; $i--) echo "<option value='$i' ".($annee==$i?'selected':'').">$i</option>"; ?>
        </select>

        <label>Mois :</label>
        <select name="mois">
            <option value="">Tous les mois</option>
            <?php
            for($m=1; $m<=12; $m++) {
                $name = date('F', mktime(0, 0, 0, $m, 1));
                echo "<option value='$m' ".($mois==$m?'selected':'').">$name</option>";
            }
            ?>
        </select>
        <button type="submit">Filtrer</button>
    </form>

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
