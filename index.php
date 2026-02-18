<?php
require 'db.php';

// Initialisation des variables par défaut
$nom_pers = "";
$v_bloc = "7";
$p_init = "0";
$f_init = "21";
$p_cible = "200";
$f_cible = "50";
$prix_o2 = "0.01";

// 1. Logique d'enregistrement
if (isset($_POST['valider_enregistrement'])) {
    $stmt = $pdo->prepare("INSERT INTO gonflages (proprietaire, volume_bloc, litres_o2_utilises, prix_facture) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['nom'],
        $_POST['vol_bloc'],
        $_POST['o2_calc'],
        $_POST['prix_calc']
    ]);
    echo "<p style='color:green;'>Enregistrement réussi !</p>";
}

// 2. Logique de calcul
$resultat = null;
if (isset($_POST['calculer'])) {
    $nom_pers = $_POST['proprietaire'];
    $v_bloc = filter_input(INPUT_POST, 'v_bloc', FILTER_VALIDATE_FLOAT);
    $p_init = filter_input(INPUT_POST, 'p_init', FILTER_VALIDATE_FLOAT);
    $p_cible = filter_input(INPUT_POST, 'p_cible', FILTER_VALIDATE_FLOAT);
    $f_init = filter_input(INPUT_POST, 'f_init', FILTER_VALIDATE_FLOAT);
    $f_cible = filter_input(INPUT_POST, 'f_cible', FILTER_VALIDATE_FLOAT);
    $prix_litre = filter_input(INPUT_POST, 'prix_o2', FILTER_VALIDATE_FLOAT);

    // Calcul : On cherche le volume d'O2 pur a ajouter pour atteindre la cible
    // Formule simplifiée pour complément O2 pur puis Air (0.21)
    $pression_o2_a_ajouter = (($p_cible * ($f_cible/100)) - ($p_init * ($f_init/100)) - 0.21 * ($p_cible - $p_init)) / (1 - 0.21);

    // Sécurité : si le calcul donne < 0 (mélange impossible par ajout d'O2)
    $pression_o2_a_ajouter = max(0, $pression_o2_a_ajouter);
    $litres_o2 = $pression_o2_a_ajouter * $v_bloc;
    $prix_total = $litres_o2 * $prix_litre;
    
    $resultat = [
        'p_o2' => round($pression_o2_a_ajouter, 1),
        'p_fin_o2' => round($p_init + $pression_o2_a_ajouter, 1), // Pression a laquelle on arrête l'O2
        'o2' => round($litres_o2, 2),
        'prix' => round($prix_total, 2),
        'nom' => $nom_pers,
        'v_bloc' => $v_bloc
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Gonflage O2</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav>
    <a href="index.php"><b>Calculateur</b></a>
    <a href="liste.php">Liste des factures</a>
    <a href="stats.php">Statistiques</a>
    <a href="export.php">Exporter CSV</a>
</nav>

<div class="container">
    <h2>Calcul de gonflage Nitrox (O2 Pur + Air)</h2>
    <form method="post">
        <label>Propriétaire du bloc :</label>
        <input type="text" name="proprietaire" value="<?php echo htmlspecialchars($nom_pers, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nom du plongeur" required>

        <label>Prix de l'O2 (au litre) :</label>
        <input type="number" step="0.001" name="prix_o2" value="<?php echo htmlspecialchars($prix_o2, ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Volume du bloc (ex: 5.6, 11.1, 12) :</label>
        <input type="number" step="0.1" name="v_bloc" value="<?php echo htmlspecialchars($v_bloc, ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Pression Initiale (Bar) :</label>
        <input type="number" name="p_init" value="<?php echo htmlspecialchars($p_init, ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>Pression Cible (Bar) :</label>
        <input type="number" name="p_cible" value="<?php echo htmlspecialchars($p_cible, ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>% O2 Initial :</label>
        <input type="number" name="f_init" value="<?php echo htmlspecialchars($f_init, ENT_QUOTES, 'UTF-8'); ?>" required>

        <label>% O2 Cible :</label>
        <input type="number" name="f_cible" value="<?php echo htmlspecialchars($f_cible, ENT_QUOTES, 'UTF-8'); ?>" required>

        <button type="submit" name="calculer">Calculer</button>
    </form>

    <?php if ($resultat): ?>
    <div class="result">
        <h3>Résultat :</h3>
        <p>1. Ajouter <b><?php echo $resultat['p_o2']; ?> bar</b> d'O2 pur.</p>
        <p>2. Arrêter l'O2 à <b><?php echo $resultat['p_fin_o2']; ?> bar</b>.</p>
        <p>3. Compléter à l'air jusqu'à <b><?php echo $_POST['p_cible']; ?> bar</b>.</p>
        <hr>
        <p>Total O2 : <?php echo $resultat['o2']; ?> Litres</p>
        <p>Prix : <b><?php echo $resultat['prix']; ?> €</b></p>
        
        <form method="post">
            <input type="hidden" name="nom" value="<?php echo htmlspecialchars($resultat['nom'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="o2_calc" value="<?php echo htmlspecialchars($resultat['o2'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="prix_calc" value="<?php echo htmlspecialchars($resultat['prix'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="vol_bloc" value="<?php echo htmlspecialchars($resultat['v_bloc'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" name="valider_enregistrement" style="background: #28a745;">Valider et Facturer</button>
        </form>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
