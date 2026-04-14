<?php
require 'db.php';

// Sécurisation de la mise à jour : on passe en POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paye'])) {
    $id_a_payer = (int)$_POST['paye'];
    if ($id_a_payer > 0) {
        $stmt = $pdo->prepare("UPDATE gonflages SET paye = 1 WHERE id = ?");
        $stmt->execute([$id_a_payer]);

        // Redirection pour "nettoyer" l'URL et éviter de repayer en rafraîchissant la page
        header("Location: liste.php");
        exit();
    }
}

// Traitement du paiement groupé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paye_groupe'])) {
    $nom_client = $_POST['paye_groupe'];
    $stmt = $pdo->prepare("UPDATE gonflages SET paye = 1 WHERE proprietaire = ? AND paye = 0");
    $stmt->execute([$nom_client]);
    header("Location: liste.php");
    exit();
}

$gonflages = $pdo->query("SELECT * FROM gonflages ORDER BY date_gonflage DESC")->fetchAll();

// Calcul des sommes dues par personne pour le récapitulatif
$dettes = $pdo->query("SELECT proprietaire, SUM(prix_facture) as total_du
                       FROM gonflages
                       WHERE paye = 0
                       GROUP BY proprietaire
		       HAVING total_du > 0")->fetchAll();

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

    <?php if (!empty($dettes)): ?>
    <h2> Récapitulatif par personne</h2>
    <table style="margin-bottom: 40px; border: 2px solid #eee;">
        <thead>
            <tr>
                <th>Plongeur</th>
                <th>Montant Total dû</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dettes as $d): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($d['proprietaire'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td style="color: #d9534f; font-weight: bold;"><?php echo number_format($d['total_du'], 2); ?> €</td>
                <td>
                    <form method="POST" action="liste.php" onsubmit="return confirm('Régler la totalité des factures pour <?php echo addslashes(htmlspecialchars($d['proprietaire'])); ?> ?')" style="display:inline;">
                        <input type="hidden" name="paye_groupe" value="<?php echo htmlspecialchars($d['proprietaire'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn-action btn-success" style="cursor:pointer; border:none;">
                            Tout régler
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <hr>
    <?php endif; ?>

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
                        <form method="POST" action="liste.php" onsubmit="return confirm('Confirmer le paiement pour <?php echo addslashes(htmlspecialchars($g['proprietaire'])); ?> ?')" style="display:inline;">
                            <input type="hidden" name="paye" value="<?php echo (int)$g['id']; ?>">
                            <button type="submit" class="btn-action btn-success" style="padding: 5px 10px; font-size: 0.8em; cursor:pointer; border:none;">
                                Marquer payé
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</body>
</html>
