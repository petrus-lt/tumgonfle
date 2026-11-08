<?php
// gaz_reel.php
// Modèle de GAZ RÉEL pour les mélanges nitrox (O2 + N2).
//
// Facteur de compressibilité Z = pV/nRT obtenu par ajustement polynomial
// (développement viriel tronqué) identique à celui de Subsurface
// (core/gas-model.c) et de realblender de Dirk Willem van de Put /
// atdotde : https://github.com/atdotde/realblender
// Coefficients (pression en bar, Z sans dimension) :
//   Z(p) = 1 + fO2*(c1.p + c2.p^2 + c3.p^3) + fN2*(idem pour N2)
//
// Hypothèses : tout se fait à température ambiante constante (remplir
// lentement et laisser refroidir) ; la composition relative ne change pas
// avec la pression (chaque composante suit le même Z que le mélange).

const GAZ_REEL_O2 = [
    -7.18092073703e-04,
    +2.81852572808e-06,
    -1.50290620492e-09,
];

const GAZ_REEL_N2 = [
    -2.19260353292e-04,
    +2.92844845532e-06,
    -2.07613482075e-09,
];

// Termes de viriel d'un gaz pur à la pression $p.
function viriel_gaz_reel(float $p, array $coef): float
{
    return $coef[0] * $p + $coef[1] * $p * $p + $coef[2] * $p * $p * $p;
}

// Facteur de compressibilité Z d'un nitrox de fraction d'O2 $f_o2 (0..1).
function z_facteur(float $p, float $f_o2): float
{
    return 1
        + $f_o2 * viriel_gaz_reel($p, GAZ_REEL_O2)
        + (1 - $f_o2) * viriel_gaz_reel($p, GAZ_REEL_N2);
}

// Quantité de gaz enfermée dans le bloc à la pression $p, exprimée en
// "litres normaux" (litres à ~1 atm, par litre de volume de bloc). C'est la
// quantité conservée (proportionnelle au nombre de moles), l'équivalent en
// gaz réel de la pression en gaz parfait : q = p * Z(1) / Z(p).
function quantite_normale(float $p, float $f_o2): float
{
    if ($p <= 0) {
        return 0.0;
    }
    return $p * z_facteur(1.0, $f_o2) / z_facteur($p, $f_o2);
}

// Pression qui enferme la quantité $q (litres normaux) d'un nitrox de
// fraction $f_o2 : inversion itérative de quantite_normale(), identique à
// find_p() de realblender.
function pression_pour_quantite(float $f_o2, float $q): float
{
    if ($q <= 0) {
        return 0.0;
    }
    $p = $q;
    for ($i = 0; $i < 100; $i++) {
        $p_nouvelle = $q * z_facteur($p, $f_o2) / z_facteur(1.0, $f_o2);
        if (abs($p_nouvelle - $p) < 1e-9) {
            return $p_nouvelle;
        }
        $p = $p_nouvelle;
    }
    return $p; // convergence de toutes façons très rapide (|Z-1| < 0.1)
}
