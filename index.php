<?php
require 'db.php';
require 'calcul.php';

// Initialisation des variables par défaut
$nom_pers = "";
$v_bloc = "7";
$p_init = "0";
$f_init = "21";
$p_cible = "200";
$f_cible = "50";
$prix_o2 = "10";

// 1. Logique d'enregistrement
if (isset($_POST['valider_enregistrement'])) {
    $stmt = $pdo->prepare("INSERT INTO gonflages (proprietaire, volume_bloc, litres_o2_utilises, prix_facture) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_POST['nom'],
        $_POST['vol_bloc'],
        $_POST['o2_calc'],
        $_POST['prix_calc']
    ]);
    echo "<p style='color:green; text-align:center;'>Enregistrement réussi !</p>";
}

// 2. Logique de calcul
// Le calcul et ses contrôles vivent dans calcul_melange() (calcul.php),
// séparés de l'affichage pour être testables sans base ni navigateur
// (suite de tests : tests/test_calcul.php).
$resultat = null;
$erreurs = [];

if (isset($_POST['calculer'])) {
    $nom_pers = $_POST['proprietaire'];
    $v_bloc     = (float)$_POST['v_bloc'];
    $p_init     = (float)$_POST['p_init'];
    $p_cible    = (float)$_POST['p_cible'];
    $f_init     = (float)$_POST['f_init'];
    $f_cible    = (float)$_POST['f_cible'];
    $prix_m3 = (float)$_POST['prix_o2'];

    $calc = calcul_melange([
        'nom'     => $nom_pers,
        'v_bloc'  => $v_bloc,
        'p_init'  => $p_init,
        'p_cible' => $p_cible,
        'f_init'  => $f_init,
        'f_cible' => $f_cible,
        'prix_o2' => $prix_m3,
    ]);
    $resultat = $calc['resultat'];
    $erreurs  = $calc['erreurs'];
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

    <?php if (!empty($erreurs)): ?>
        <div class="error-box">
            <strong>Erreur de saisie :</strong>
            <ul>
                <?php foreach($erreurs as $e): ?>
                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Propriétaire du bloc :</label>
        <input type="text" name="proprietaire" value="<?php echo htmlspecialchars($nom_pers, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nom du plongeur" required>

        <label>Prix de l'O2 (au m³) :</label>
        <input type="number" step="0.1" name="prix_o2" value="<?php echo htmlspecialchars($prix_o2, ENT_QUOTES, 'UTF-8'); ?>" required>

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
        <?php if ($resultat['methode'] === 'o2'): ?>
            <p>1. Ajouter <b><?php echo $resultat['p_o2']; ?> bar</b> d'O2 pur.</p>
            <p>2. Arrêter l'O2 à <b><?php echo $resultat['p_fin_o2']; ?> bar</b>.</p>
            <p>3. Compléter à l'air jusqu'à <b><?php echo htmlspecialchars((string)$resultat['p_cible'], ENT_QUOTES, 'UTF-8'); ?> bar</b>.</p>
        <?php elseif ($resultat['methode'] === 'dilution'): ?>
            <p>1. Vidanger le bloc jusqu'à <b><?php echo $resultat['p_cut']; ?> bar</b> (le mélange reste à <?php echo htmlspecialchars((string)$resultat['f_init'], ENT_QUOTES, 'UTF-8'); ?>% O2).</p>
            <p>2. Compléter à l'air jusqu'à <b><?php echo htmlspecialchars((string)$resultat['p_cible'], ENT_QUOTES, 'UTF-8'); ?> bar</b>.</p>
            <p>Air ajouté : <?php echo round(($resultat['p_cible'] - $resultat['p_cut']) * $resultat['v_bloc']); ?> L.</p>
            <p><b>Attention</b> : cette méthode rejette environ <b><?php echo $resultat['o2_gache']; ?> L d'O2</b> à la vidange.</p>
        <?php else: ?>
            <p>Vider le bloc jusqu'à <b><?php echo $resultat['p_cut']; ?> bar</b> : le mélange est inchangé (<?php echo htmlspecialchars((string)$resultat['f_init'], ENT_QUOTES, 'UTF-8'); ?>% O2).</p>
            <p>Environ <?php echo $resultat['o2_gache']; ?> L d'O2 sont rejetés.</p>
        <?php endif; ?>
        <hr>
        <p>Total O2 : <?php echo $resultat['o2']; ?> Litres</p>
        <p>Prix : <b><?php echo $resultat['prix']; ?> €</b></p>
        <?php if ($resultat['methode'] !== 'o2'): ?>
        <p><i>Le complément d'air comprimé n'est pas facturé par ce calcul.</i></p>
        <?php endif; ?>
        
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
