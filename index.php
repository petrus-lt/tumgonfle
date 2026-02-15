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
    $v_bloc = $_POST['v_bloc'];
    $p_init = $_POST['p_init'];
    $p_cible = $_POST['p_cible'];
    $f_init = $_POST['f_init'] / 100;
    $f_cible = $_POST['f_cible'] / 100;
    $prix_litre = $_POST['prix_o2'];

    // Calcul : On cherche le volume d'O2 pur à ajouter pour atteindre la cible
    // Formule simplifiée pour complément O2 pur puis Air (0.21)
    $pression_o2_a_ajouter = ($p_cible * $f_cible - $p_init * $f_init - 0.21 * ($p_cible - $p_init)) / (1 - 0.21);
    
    $litres_o2 = max(0, $pression_o2_a_ajouter * $v_bloc);
    $prix_total = $litres_o2 * $prix_litre;
    
    $resultat = [
        'o2' => round($litres_o2, 2),
        'prix' => round($prix_total, 2),
        'nom' => $_POST['proprietaire'],
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
    <a href="index.php">Calculateur</a> | 
    <a href="liste.php">Liste des factures</a>
</nav>

<div class="container">
    <h2>Calcul de gonflage Nitrox (O2 Pur + Air)</h2>
    <form method="post">
        <label>Propriétaire du bloc :</label>
        <input type="text" name="proprietaire" required>
        
        <label>Prix de l'O2 (au litre) :</label>
        <input type="number" step="0.001" name="prix_o2" value="0.01" required>

        <label>Volume du bloc (Litres) :</label>
        <input type="number" name="v_bloc" value="7" required>

        <label>Pression Initiale (Bar) :</label>
        <input type="number" name="p_init" value="0" required>

        <label>Pression Cible (Bar) :</label>
        <input type="number" name="p_cible" value="200" required>

        <label>% O2 Initial :</label>
        <input type="number" name="f_init" value="21" required>

        <label>% O2 Cible :</label>
        <input type="number" name="f_cible" value="40" required>

        <button type="submit" name="calculer">Calculer</button>
    </form>

    <?php if ($resultat): ?>
    <div class="result">
        <h3>Résultat :</h3>
        <p>O2 à ajouter : <strong><?php echo $resultat['o2']; ?> Litres</strong></p>
        <p>Prix estimé : <strong><?php echo $resultat['prix']; ?> €</strong></p>
        
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
