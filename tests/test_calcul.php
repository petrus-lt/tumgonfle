<?php
// tests/test_calcul.php
// Suite de non-régression du moteur de calcul (calcul.php).
// Lancement : php tests/test_calcul.php
// Ne touche ni à la base de données ni au navigateur ; code retour 0 si tout passe.

require __DIR__ . '/../calcul.php';

$verifs = 0;
$echecs = 0;

function check(string $id, string $libelle, string $attendu, string $obtenu): void
{
    global $verifs, $echecs;
    $verifs++;
    if ($attendu === $obtenu) {
        echo "  ok   $id  $libelle\n";
    } else {
        $echecs++;
        echo "  FAIL $id  $libelle : attendu <$attendu>, obtenu <$obtenu>\n";
    }
}

// Comparaison de valeurs numériques après arrondi (format 2 décimales fixes).
function checkNum(string $id, string $libelle, float $attendu, $obtenu): void
{
    check($id, $libelle, sprintf('%.2f', $attendu), is_numeric($obtenu) ? sprintf('%.2f', (float)$obtenu) : 'null');
}

function checkErreur(string $id, string $libelle, array $calc, string $fragment): void
{
    $trouve = false;
    foreach ($calc['erreurs'] as $e) {
        if (strpos($e, $fragment) !== false) {
            $trouve = true;
            break;
        }
    }
    check($id, $libelle, 'erreur contenant <' . $fragment . '>', $trouve ? 'erreur contenant <' . $fragment . '>' : 'aucune erreur correspondante');
    check($id . '.null', $libelle . ' : pas de résultat', 'null', $calc['resultat'] === null ? 'null' : gettype($calc['resultat']));
}

// Entrée par défaut : 12 L, 200 -> 200 bar, prix 10 EUR/m3.
function entree(array $surcharge = []): array
{
    return array_merge([
        'nom' => 'Testeur', 'v_bloc' => 12, 'p_init' => 200, 'p_cible' => 200,
        'f_init' => 21, 'f_cible' => 32, 'prix_o2' => 10,
    ], $surcharge);
}

echo "-- Enrichissement à l'O2 pur (méthode historique) --\n";

// B : air -> EAN32 à 200 bar, bloc 11,1 L (cas de référence connu).
$r = calcul_melange(entree(['v_bloc' => 11.1, 'p_init' => 0, 'f_init' => 21, 'f_cible' => 32]));
check('B1', 'méthode', 'o2', $r['resultat']['methode'] ?? 'null');
checkNum('B2', 'pression d\'O2 à ajouter (22/0.79 ≈ 27.85)', 27.8, $r['resultat']['p_o2'] ?? null);
checkNum('B3', 'arrêt O2', 27.8, $r['resultat']['p_fin_o2'] ?? null);
checkNum('B4', 'litres d\'O2', 309.11, $r['resultat']['o2'] ?? null);
checkNum('B5', 'prix (10 EUR/m3)', 3.09, $r['resultat']['prix'] ?? null);
check('B6', 'aucune erreur', '0', (string)count($r['erreurs']));

// D : mélange constant à plus haute pression -> complément d'O2, pas d'air seul.
$r = calcul_melange(entree(['f_init' => 32, 'f_cible' => 32, 'p_init' => 150, 'p_cible' => 200]));
check('D1', 'méthode', 'o2', $r['resultat']['methode'] ?? 'null');
checkNum('D2', 'pression d\'O2 (50*0.11/0.79 ≈ 6.96)', 7.0, $r['resultat']['p_o2'] ?? null);
checkNum('D3', 'arrêt O2', 157.0, $r['resultat']['p_fin_o2'] ?? null);
checkNum('D4', 'litres d\'O2', 83.54, $r['resultat']['o2'] ?? null);

// G : bloc déjà plein -> pas de place pour de l'O2.
$r = calcul_melange(entree(['f_init' => 21, 'f_cible' => 32]));
checkErreur('G1', 'enrichissement impossible à pleine pression', $r, 'pression initiale doit être inférieure');

echo "-- Appauvrissement par dilution (vidange + air) --\n";

// A : 200 bar EAN50 -> EAN32 à 200 bar, bloc 12 L (cas demandé).
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32]));
check('A1', 'méthode', 'dilution', $r['resultat']['methode'] ?? 'null');
checkNum('A2', 'pression de coupure (200*0.11/0.29 ≈ 75.86)', 75.9, $r['resultat']['p_cut'] ?? null);
checkNum('A3', 'O2 rejeté (0.5*124.14*12)', 744.8, $r['resultat']['o2_gache'] ?? null);
checkNum('A4', 'O2 ajouté nul', 0.0, $r['resultat']['o2'] ?? null);
checkNum('A5', 'prix nul (air non facturé)', 0.0, $r['resultat']['prix'] ?? null);
check('A6', 'aucune erreur', '0', (string)count($r['erreurs']));

// I : 100% O2 -> EAN32 : coupure à P*(0.32-0.21)/(1-0.21).
$r = calcul_melange(entree(['f_init' => 100, 'f_cible' => 32]));
check('I1', 'méthode', 'dilution', $r['resultat']['methode'] ?? 'null');
checkNum('I2', 'pression de coupure (22/0.79 ≈ 27.85)', 27.8, $r['resultat']['p_cut'] ?? null);
checkNum('I3', 'O2 rejeté (172.15*12)', 2065.8, $r['resultat']['o2_gache'] ?? null);

// F : diluer jusqu'à l'air (21%) -> vidange complète, P_cut = 0.
$r = calcul_melange(entree(['f_init' => 32, 'f_cible' => 21]));
check('F1', 'méthode', 'dilution', $r['resultat']['methode'] ?? 'null');
checkNum('F2', 'pression de coupure nulle', 0.0, $r['resultat']['p_cut'] ?? null);
checkNum('F3', 'O2 rejeté (0.32*200*12)', 768.0, $r['resultat']['o2_gache'] ?? null);

// J : pression cible au-dessus du départ -> la coupure ne dépend que du mélange.
$r = calcul_melange(entree(['v_bloc' => 11, 'f_init' => 40, 'f_cible' => 32, 'p_init' => 220, 'p_cible' => 235]));
check('J1', 'méthode', 'dilution', $r['resultat']['methode'] ?? 'null');
checkNum('J2', 'pression de coupure (235*0.11/0.19)', 136.1, $r['resultat']['p_cut'] ?? null);
checkNum('J3', 'O2 rejeté (0.4*83.95*11)', 369.4, $r['resultat']['o2_gache'] ?? null);

// E : départ trop bas -> infaisable avec O2 + air seuls.
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32, 'p_init' => 50]));
checkErreur('E1', 'dilution infaisable si départ < P_cut', $r, 'il faudrait au moins 76 bar');

// M : faisabilité à la limite (P_cut ≈ 75.86).
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32, 'p_init' => 76]));
check('M1', 'départ 76 bar faisable', 'dilution', $r['resultat']['methode'] ?? 'null');
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32, 'p_init' => 75]));
check('M2', 'départ 75 bar refusé', 'null', $r['resultat'] === null ? 'null' : gettype($r['resultat']));

echo "-- Mélange constant / cas limites --\n";

// C : sur-pression à mélange constant -> simple vidange.
$r = calcul_melange(entree(['f_init' => 32, 'f_cible' => 32, 'p_init' => 231]));
check('C1', 'méthode', 'vidange', $r['resultat']['methode'] ?? 'null');
checkNum('C2', 'vidange jusqu\'à la cible', 200.0, $r['resultat']['p_cut'] ?? null);
checkNum('C3', 'O2 rejeté (0.32*31*12)', 119.0, $r['resultat']['o2_gache'] ?? null);

// H : déjà au bon mélange et à la bonne pression.
$r = calcul_melange(entree(['f_init' => 32, 'f_cible' => 32]));
checkErreur('H1', 'rien à faire', $r, 'rien à faire');

// K/L : contrôles de saisie.
$r = calcul_melange(entree(['p_cible' => 320]));
checkErreur('K1', 'pression cible > 300 refusée', $r, '300');
$r = calcul_melange(entree(['f_init' => 101]));
check('L1', '% initial hors bornes -> erreur', '1', (string)(count($r['erreurs']) >= 1 ? 1 : 0));
$r = calcul_melange(entree(['f_cible' => 18]));
check('L2', '% cible < 21 refusé', '1', (string)(count($r['erreurs']) >= 1 ? 1 : 0));

echo "-- Modèle gaz réel (facteur de compressibilité Subsurface/realblender) --\n";

// Simulation "aller-retour" de la recette affichée : on rejoue les étapes
// avec les pressions arrondies rendues à l'écran et on doit retrouver la
// fraction d'O2 cible (aux erreurs d'arrichage près). C'est le contrôle le
// plus fort : il valide cohérence interne ET inversion pression<->quantité.
function q_modele(float $p, float $f, string $modele): float
{
    return $modele === 'reel' ? quantite_normale($p, $f) : $p;
}

function melange_final_o2(array $in, array $res): float
{
    $modele = $res['modele'];
    $f_i = $in['f_init'] / 100;
    $q_i = q_modele($in['p_init'], $f_i, $modele);
    // Étape 1 : O2 pur jusqu'à la pression d'arrêt affichée.
    $phi1 = max($f_i, 0.21);
    $q1 = $q_i;
    for ($i = 0; $i < 60; $i++) {
        $q1 = q_modele($res['p_fin_o2'], $phi1, $modele);
        $phi1_n = ($f_i * $q_i + ($q1 - $q_i)) / $q1; // O2 ajouté pur (fraction 1)
        if (abs($phi1_n - $phi1) < 1e-12) { $phi1 = $phi1_n; break; }
        $phi1 = $phi1_n;
    }
    // Étape 2 : complément d'air jusqu'à la pression cible affichée.
    $phi2 = $phi1;
    for ($i = 0; $i < 60; $i++) {
        $q2 = q_modele($res['p_cible'], $phi2, $modele);
        $phi2_n = ($phi1 * $q1 + 0.21 * ($q2 - $q1)) / $q2;
        if (abs($phi2_n - $phi2) < 1e-12) { $phi2 = $phi2_n; break; }
        $phi2 = $phi2_n;
    }
    return 100 * $phi2;
}

function melange_final_dilution(array $in, array $res): float
{
    $modele = $res['modele'];
    $f_i = $in['f_init'] / 100;
    // Étape 1 : vidange jusqu'à la pression de coupure affichée.
    $q_cut = q_modele($res['p_cut'], $f_i, $modele);
    // Étape 2 : complément d'air jusqu'à la pression cible affichée.
    $phi = 0.21;
    for ($i = 0; $i < 60; $i++) {
        $q_fin = q_modele($res['p_cible'], $phi, $modele);
        $phi_n = ($f_i * $q_cut + 0.21 * ($q_fin - $q_cut)) / $q_fin;
        if (abs($phi_n - $phi) < 1e-12) { $phi = $phi_n; break; }
        $phi = $phi_n;
    }
    return 100 * $phi;
}

function check_fraction(string $id, float $attendu_pct, float $obtenu_pct): void
{
    // Tolérance 0.1 point : les pressions rendues à l'écran sont arrondies
    // au dixième de bar, et cette arrondi se propage dans la simulation.
    global $verifs, $echecs;
    $verifs++;
    if (abs($obtenu_pct - $attendu_pct) <= 0.1) {
        echo "  ok   $id  retour au mélange cible après simulation (" . sprintf('%.3f', $obtenu_pct) . " ≈ " . sprintf('%.2f', $attendu_pct) . ")\n";
    } else {
        $echecs++;
        echo "  FAIL $id  retour au mélange cible : attendu ~" . sprintf('%.2f', $attendu_pct) . "±0.1, obtenu " . sprintf('%.3f', $obtenu_pct) . "\n";
    }
}

// Le défaut reste le gaz parfait (non-régression du choix par omission).
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32]));
check('X0', 'modèle par défaut', 'ideale', $r['resultat']['modele'] ?? 'absent');

// A réel : même cas que A (200 bar EAN50 -> EAN32 à 200 bar, 12 L).
$in = entree(['f_init' => 50, 'f_cible' => 32, 'modele' => 'reel']);
$r = calcul_melange($in);
check('AR1', 'méthode', 'dilution', $r['resultat']['methode'] ?? 'null');
checkNum('AR2', 'coupure ~72.6 bar (vs 75.9 en gaz parfait)', 72.6, $r['resultat']['p_cut'] ?? null);
checkNum('AR3', 'air ajouté (litres normaux)', 1453.0, $r['resultat']['air_ajoute'] ?? null);
checkNum('AR4', 'O2 rejeté', 747.3, $r['resultat']['o2_gache'] ?? null);
check_fraction('AR5', 32.0, melange_final_dilution($in, $r['resultat']));

// A idéal : la simulation aller-retour fonctionne aussi en gaz parfait.
$in = entree(['f_init' => 50, 'f_cible' => 32]);
$r = calcul_melange($in);
check_fraction('AI5', 32.0, melange_final_dilution($in, $r['resultat']));

// B réel : air -> EAN32 à 200 bar, 11,1 L (le vrai modèle demande un peu
// moins d'O2 et arrête l'O2 plus tôt que les 27.8 bar du gaz parfait).
$in = ['v_bloc' => 11.1, 'p_init' => 0, 'p_cible' => 200, 'f_init' => 21, 'f_cible' => 32, 'prix_o2' => 10, 'modele' => 'reel', 'nom' => 'Testeur'];
$r = calcul_melange($in);
check('BR1', 'méthode', 'o2', $r['resultat']['methode'] ?? 'null');
checkNum('BR2', 'quantité d\'O2', 27.2, $r['resultat']['p_o2'] ?? null);
checkNum('BR3', 'arrêt O2 (pression réelle)', 26.7, $r['resultat']['p_fin_o2'] ?? null);
checkNum('BR4', 'litres d\'O2', 301.52, $r['resultat']['o2'] ?? null);
checkNum('BR5', 'prix', 3.02, $r['resultat']['prix'] ?? null);
check_fraction('BR6', 32.0, melange_final_o2($in, $r['resultat']));

// D réel : complément à mélange constant 150 -> 200.
$in = entree(['f_init' => 32, 'f_cible' => 32, 'p_init' => 150, 'modele' => 'reel']);
$r = calcul_melange($in);
checkNum('DR2', 'quantité d\'O2', 6.3, $r['resultat']['p_o2'] ?? null);
checkNum('DR3', 'arrêt O2', 156.4, $r['resultat']['p_fin_o2'] ?? null);
check_fraction('DR4', 32.0, melange_final_o2($in, $r['resultat']));

// J réel : pression cible au-dessus du départ (40% -> 32% à 235 bar).
$in = entree(['v_bloc' => 11, 'f_init' => 40, 'f_cible' => 32, 'p_init' => 220, 'p_cible' => 235, 'modele' => 'reel']);
$r = calcul_melange($in);
checkNum('JR2', 'pression de coupure', 128.7, $r['resultat']['p_cut'] ?? null);
checkNum('JR3', 'O2 rejeté', 369.4, $r['resultat']['o2_gache'] ?? null);
check_fraction('JR4', 32.0, melange_final_dilution($in, $r['resultat']));

// F réel : diluer jusqu'à l'air -> coupure à 0.
$in = entree(['f_init' => 32, 'f_cible' => 21, 'modele' => 'reel']);
$r = calcul_melange($in);
checkNum('FR2', 'coupure nulle', 0.0, $r['resultat']['p_cut'] ?? null);
check_fraction('FR3', 21.0, melange_final_dilution($in, $r['resultat']));

// I réel : 100% O2 -> 32%.
$in = entree(['f_init' => 100, 'f_cible' => 32, 'modele' => 'reel']);
$r = calcul_melange($in);
checkNum('IR2', 'pression de coupure', 26.7, $r['resultat']['p_cut'] ?? null);
checkNum('IR3', 'O2 rejeté', 2179.8, $r['resultat']['o2_gache'] ?? null);

// E réel : faisabilité (coupure réelle ~72.6 bar).
$in = entree(['f_init' => 50, 'f_cible' => 32, 'p_init' => 50, 'modele' => 'reel']);
$r = calcul_melange($in);
checkErreur('ER1', 'dilution réelle infaisable si départ < coupure', $r, 'au moins 73 bar');

// M réel : limite de faisabilité de part et d'autre de 72.6 bar.
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32, 'p_init' => 73, 'modele' => 'reel']));
check('MR1', 'départ 73 bar faisable en réel', 'dilution', $r['resultat']['methode'] ?? 'null');
$r = calcul_melange(entree(['f_init' => 50, 'f_cible' => 32, 'p_init' => 72, 'modele' => 'reel']));
check('MR2', 'départ 72 bar refusé en réel', 'null', $r['resultat'] === null ? 'null' : gettype($r['resultat']));

// C réel : sur-pression à mélange constant -> vidange identique au gaz parfait.
$r = calcul_melange(entree(['f_init' => 32, 'f_cible' => 32, 'p_init' => 231, 'modele' => 'reel']));
check('CR1', 'méthode', 'vidange', $r['resultat']['methode'] ?? 'null');
checkNum('CR2', 'vidange jusqu\'à la cible', 200.0, $r['resultat']['p_cut'] ?? null);

echo "--\n";
if ($echecs === 0) {
    echo "Tous les tests passent ($verifs vérifications).\n";
    exit(0);
}
echo "$echecs échec(s) sur $verifs vérifications.\n";
exit(1);
