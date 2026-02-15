<?php
// Configuration Connexion BDD
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
    $nom_pers = htmlspecialchars($_POST['proprietaire']);
    $v_bloc = $_POST['v_bloc'];
    $p_init = $_POST['p_init'];
    $p_cible = $_POST['p_cible'];
    $f_init = $_POST['f_init'];
    $f_cible = $_POST['f_cible'];
    $prix_litre = $_POST['prix_o2'];

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
    <title>Gestion Gonflage O2</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background: #f4f4f4; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        input { margin-bottom: 10px; width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px; }
        .result { background: #e7f3ff; padding: 15px; margin-top: 20px; border-left: 5px solid #007bff; }
        nav { margin-bottom: 20px; }
    </style>
</head>
<body>

<nav>
    <a href="index.php"><b>Calculateur</b></a> |
    <a href="liste.php">Liste des factures</a> |
    <a href="stats.php">Statistiques</a> |
    <a href="export.php">Exporter CSV</a>
</nav>

<div class="container">
    <h2>Calcul de gonflage Nitrox (O2 Pur + Air)</h2>
    <form method="post">
        <label>Propriétaire du bloc :</label>
        <input type="text" name="proprietaire" value="<?php echo $nom_pers; ?>" placeholder="Nom du plongeur" required>

        <label>Prix de l'O2 (au litre) :</label>
        <input type="number" step="0.001" name="prix_o2" value="<?php echo $prix_o2; ?>" required>

        <label>Volume du bloc (ex: 5.6, 11.1, 12) :</label>
        <input type="number" step="0.1" name="v_bloc" value="<?php echo $v_bloc; ?>" required>

        <label>Pression Initiale (Bar) :</label>
        <input type="number" name="p_init" value="<?php echo $p_init; ?>" required>

        <label>Pression Cible (Bar) :</label>
        <input type="number" name="p_cible" value="<?php echo $p_cible; ?>" required>

        <label>% O2 Initial :</label>
        <input type="number" name="f_init" value="<?php echo $f_init; ?>" required>

        <label>% O2 Cible :</label>
        <input type="number" name="f_cible" value="<?php echo $f_cible; ?>" required>

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
            <input type="hidden" name="nom" value="<?php echo $resultat['nom']; ?>">
            <input type="hidden" name="o2_calc" value="<?php echo $resultat['o2']; ?>">
            <input type="hidden" name="prix_calc" value="<?php echo $resultat['prix']; ?>">
            <input type="hidden" name="vol_bloc" value="<?php echo $resultat['v_bloc']; ?>">
            <button type="submit" name="valider_enregistrement" style="background: #28a745;">Valider et Facturer</button>
        </form>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
