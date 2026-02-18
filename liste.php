<?php
require 'db.php';

// Sécurisation de la mise à jour : on force l'ID en entier
if (isset($_GET['paye'])) {
    $id_a_payer = (int)$_GET['paye'];
    if ($id_a_payer > 0) {
        $stmt = $pdo->prepare("UPDATE gonflages SET paye = 1 WHERE id = ?");
        $stmt->execute([$id_a_payer]);

        // Redirection pour "nettoyer" l'URL et éviter de repayer en rafraîchissant la page
        header("Location: liste.php");
        exit();
    }
}

$gonflages = $pdo->query("SELECT * FROM gonflages ORDER BY date_gonflage DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des paiements</title>
    <link rel="stylesheet" href="style.css">
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
        <thead>
            <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Volume</th>
                <th>O2 utilisé</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gonflages as $g): ?>
            <tr>
                <td><?php echo htmlspecialchars($g['date_gonflage'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($g['proprietaire'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($g['volume_bloc'], ENT_QUOTES, 'UTF-8'); ?> L</td>
                <td><?php echo htmlspecialchars($g['litres_o2_utilises'], ENT_QUOTES, 'UTF-8'); ?> L</td>
                <td><?php echo htmlspecialchars($g['prix_facture'], ENT_QUOTES, 'UTF-8'); ?> €</td>
                <td class="<?php echo $g['paye'] ? 'status-ok' : 'status-no'; ?>">
                    <strong><?php echo $g['paye'] ? 'Payé' : 'À payer'; ?></strong>
                </td>
                <td>
                    <?php if (!$g['paye']): ?>
                        <a href="liste.php?paye=<?php echo (int)$g['id']; ?>"
                           class="btn-action btn-success"
                           onclick="return confirm('Confirmer le paiement pour <?php echo addslashes(htmlspecialchars($g['proprietaire'])); ?> ?')"
                           style="padding: 5px 10px; font-size: 0.8em; text-decoration: none;">
                           Marquer payé
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</body>
</html>
