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

echo "--\n";
if ($echecs === 0) {
    echo "Tous les tests passent ($verifs vérifications).\n";
    exit(0);
}
echo "$echecs échec(s) sur $verifs vérifications.\n";
exit(1);
