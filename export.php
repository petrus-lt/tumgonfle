<?php
// Configuration Connexion BDD
$host = 'localhost';
$db   = 'gonflages_db';
$user = 'gonflageuser';
$pass = 'monsuperpass';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass);
    
    // 1. Récupération des données
    $query = $pdo->query("SELECT date_gonflage, proprietaire, volume_bloc, litres_o2_utilises, prix_facture, paye FROM gonflages ORDER BY date_gonflage DESC");
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);

    // 2. Paramétrage des headers pour le téléchargement
    $filename = "factures_gonflage_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    // 3. Création du fichier CSV
    $output = fopen('php://output', 'w');
    
    // Correction pour Excel : Ajout du BOM UTF-8 pour les accents
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-têtes des colonnes
    fputcsv($output, ['Date', 'Propriétaire', 'Volume Bloc (L)', 'O2 utilisé (L)', 'Prix (€)', 'Statut Paiement'], ';');

    // Données
    foreach ($rows as $row) {
        // Transformation du booléen paye en texte
        $row['paye'] = $row['paye'] ? 'Payé' : 'À payer';
        fputcsv($output, $row, ';');
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>
