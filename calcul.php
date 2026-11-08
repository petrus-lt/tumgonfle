<?php
// calcul.php
// Moteur de calcul des mélanges nitrox (O2 pur + air), séparé de l'affichage
// pour pouvoir être testé sans base de données ni navigateur
// (suite de tests : tests/test_calcul.php).
//
// Hypothèse physique : GAZ PARFAITS. On raisonne en "bar-litres" : à
// température constante la quantité de matière d'un gaz est proportionnelle à
// P*V, donc le volume du bloc se factorise et tout se ramène à un équilibre
// des pressions partielles d'O2. Aux pressions de gonflage (200-300 bar) les
// gaz réels s'écartent de l'idéal de quelques pourcents : un calculateur en
// gaz réel donne quelques bars de moins à la vidange. L'analyse du mélange
// final à l'analyseur de gaz reste obligatoire avant usage.

/**
 * Calcule la "recette" pour amener un bloc de son état initial à l'état voulu.
 *
 * Entrée (array) :
 *   'v_bloc'   volume du bloc en litres
 *   'p_init'   pression de départ en bar
 *   'p_cible'  pression cible en bar
 *   'f_init'   % d'O2 du mélange de départ (21 à 100)
 *   'f_cible'  % d'O2 du mélange voulu (21 à 100)
 *   'prix_o2'  prix de l'O2 au m3 (sert à facturer l'O2 ajouté)
 *   'nom'      nom du client, retransmis tel quel
 *
 * Sortie : ['resultat' => array|null, 'erreurs' => string[]]
 *   resultat['methode'] : 'o2'       ajout d'O2 pur puis complément d'air
 *                         'dilution' vidange partielle puis complément d'air
 *                         'vidange'  simple vidange (mélange inchangé)
 * Autres clés : 'p_cut', 'p_cible', 'f_init', 'p_o2', 'p_fin_o2',
 *               'o2', 'prix', 'o2_gache', 'nom', 'v_bloc' (selon la méthode).
 */
function calcul_melange(array $entree): array
{
    $nom     = (string)($entree['nom'] ?? '');
    $v_bloc  = (float)($entree['v_bloc'] ?? 0);
    $p_init  = (float)($entree['p_init'] ?? 0);
    $p_cible = (float)($entree['p_cible'] ?? 0);
    $f_init  = (float)($entree['f_init'] ?? 0);
    $f_cible = (float)($entree['f_cible'] ?? 0);
    $prix_m3 = (float)($entree['prix_o2'] ?? 0);

    $resultat = null;
    $erreurs  = [];

    // --- Sécurités métier (mêmes règles que le gonfleur applique en atelier) ---
    if ($p_init < 0) {
        $erreurs[] = "La pression initiale doit être positive.";
    }
    if ($p_cible > 300) {
        $erreurs[] = "La pression cible ne doit pas dépasser 300 bars.";
    }
    if ($f_init < 21 || $f_init > 100) {
        $erreurs[] = "Le pourcentage initial doit être entre 21% et 100%.";
    }
    if ($f_cible < 21 || $f_cible > 100) {
        // En dessous de 21%, il faudrait un gaz appauvrissant (N2 pur) :
        // l'outil ne travaille qu'avec de l'O2 pur et de l'air.
        $erreurs[] = "Le pourcentage cible doit être entre 21% et 100%.";
    }
    if ($prix_m3 < 0) {
        $erreurs[] = "Le prix ne peut être négatif.";
    }
    if ($v_bloc < 0) {
        $erreurs[] = "Le volume du bloc ne peut être négatif.";
    }

    if (empty($erreurs)) {
        $f_i = $f_init / 100;   // fraction d'O2 de départ (0..1)
        $f_t = $f_cible / 100;  // fraction d'O2 visée (0..1)
        $air = 0.21;            // fraction d'O2 de l'air de complément
        // Tolérance de saisie : à moins de 0,1 % d'écart, les deux mélanges
        // sont considérés identiques (pas de vidange ni d'ajout d'O2 "utile").
        $eps = 0.001;

        if ($f_t < $f_i - $eps) {
            // ===== Méthode "dilution" : on veut MOINS d'O2 que le départ. =====
            // On ne peut pas retirer l'O2 sélectivement ; on vide donc une
            // partie du bloc (la fraction d'O2 du gaz RESTANT ne change pas
            // pendant la vidange), puis on complète à l'air (21% d'O2).
            //
            // Équilibre de l'O2 en bar-litres à la pression cible
            // (V = volume du bloc, factorisé de part et d'autre) :
            //   F_cible*P_cible = F_init*P_cut + 0.21*(P_cible - P_cut)
            //                     ^O2 restant    ^O2 amené par l'air ajouté
            // On isole la pression de vidange (de coupure) P_cut :
            //   P_cut*(F_init - 0.21) = P_cible*(F_cible - 0.21)
            //   P_cut = P_cible * (F_cible - 0.21) / (F_init - 0.21)
            //
            // Exemple : 200 bar d'EAN50 que l'on veut convertir en EAN32 à
            // 200 bar : P_cut = 200 * (0.32-0.21)/(0.50-0.21)
            //                  = 200 * 0.11/0.29 ≈ 75.9 bar (gaz parfait ;
            // en gaz réel un calculateur certifié descend vers ~72 bar).
            $p_cut = ($p_cible * ($f_t - $air)) / ($f_i - $air);

            // Faisabilité : il faut assez de mélange riche au départ.
            // En dessous de P_cut bar de départ, même en vidant au maximum il
            // manquerait de l'O2 pour atteindre F_cible avec de l'air seul.
            if ($p_init < $p_cut) {
                $erreurs[] = "Mélange impossible : il faudrait au moins " . round($p_cut) . " bar de départ pour appauvrir à " . round($f_cible) . "% en complétant à l'air.";
            } else {
                // Rien n'est "acheté" dans cette méthode : seul de l'air est
                // ajouté (non facturé ici). Le gaz jeté à la vidange est
                // chiffré en litres d'O2 gazeux perdus, à titre indicatif.
                $resultat = [
                    'methode'  => 'dilution',
                    'p_cut'    => round($p_cut, 1),           // vidanger jusqu'ici
                    'p_cible'  => $p_cible,                   // compléter jusqu'ici
                    'f_init'   => $f_init,
                    'o2'       => 0.0,                        // pas d'O2 ajouté
                    'prix'     => 0.0,                        // air non facturé
                    'o2_gache' => round($f_i * ($p_init - $p_cut) * $v_bloc, 1),
                    'nom'      => $nom,
                    'v_bloc'   => $v_bloc,
                ];
            }
        } elseif ($f_t > $f_i + $eps || $p_init < $p_cible) {
            // ===== Méthode "enrichissement" : O2 pur puis complément d'air. =====
            // Cas : on veut PLUS d'O2, OU le même mélange à plus haute pression
            // (compléter à l'air seul ferait baisser le pourcentage d'O2).
            if ($f_t > $f_i + $eps && $p_init >= $p_cible) {
                // Pas de place dans le bloc pour ajouter de l'O2.
                $erreurs[] = "La pression initiale doit être inférieure à la pression cible pour ajouter de l'O2.";
            } else {
                // Équilibre des pressions partielles d'O2 à la cible.
                // En appelant P_o2 la pression d'O2 pur ajoutée et en sachant
                // que le reste du complément est de l'air (21% d'O2) :
                //   P_cible*F_cible = P_init*F_init      (déjà en place)
                //                   + P_o2               (O2 pur ajouté)
                //                   + 0.21*(P_cible - P_init - P_o2)  (air)
                // D'où la formule classique, en isolant P_o2 :
                //   P_o2 = (P_cible*F_cible - P_init*F_init - 0.21*(P_cible - P_init)) / (1 - 0.21)
                // À mélange constant (F_cible = F_init) elle se réduit à
                //   P_o2 = (P_cible - P_init)*(F - 0.21)/0.79
                // qui est bien la pression d'O2 à ajouter pour garder la même
                // fraction en montant en pression.
                $pression_o2_a_ajouter = (($p_cible * $f_t) - ($p_init * $f_i) - 0.21 * ($p_cible - $p_init)) / (1 - 0.21);

                // Un calcul "négatif" se produit quand le départ est déjà plus
                // riche que nécessaire ; la garde ci-dessus l'écarte, on met
                // max(0,...) en ceinture-bretelles.
                $pression_o2_a_ajouter = max(0, $pression_o2_a_ajouter);

                // Conversion litres et facturation : la pression d'O2 ajoutée
                // fois le volume du bloc = litres d'O2 utilisés (bar-litres = L
                // à 1 atm pour un gaz parfait).
                $litres_o2  = $pression_o2_a_ajouter * $v_bloc;
                $prix_total = ($litres_o2 / 1000) * $prix_m3;

                $resultat = [
                    'methode'  => 'o2',
                    'p_o2'     => round($pression_o2_a_ajouter, 1), // à ajouter
                    'p_fin_o2' => round($p_init + $pression_o2_a_ajouter, 1), // arrêt O2
                    'o2'       => round($litres_o2, 2),
                    'prix'     => round($prix_total, 2),
                    'p_cible'  => $p_cible,
                    'f_init'   => $f_init,
                    'nom'      => $nom,
                    'v_bloc'   => $v_bloc,
                ];

                // Vérification finale : est-ce que l'O2 nécessaire dépasse la
                // pression finale ? (mélange physiquement impossible)
                if ($resultat['p_fin_o2'] > $p_cible) {
                    $erreurs[] = "Mélange impossible : la quantité d'O2 pur requise dépasse la pression cible.";
                    $resultat = null;
                }
            }
        } elseif ($p_init > $p_cible) {
            // ===== Mélange constant en sur-pression : simple vidange. =====
            // Vidanger ne change pas la fraction d'O2 : redescendre à P_cible
            // suffit, aucun gaz n'est ajouté.
            $resultat = [
                'methode'  => 'vidange',
                'p_cut'    => round($p_cible, 1),
                'p_cible'  => $p_cible,
                'f_init'   => $f_init,
                'o2'       => 0.0,
                'prix'     => 0.0,
                'o2_gache' => round($f_i * ($p_init - $p_cible) * $v_bloc, 1),
                'nom'      => $nom,
                'v_bloc'   => $v_bloc,
            ];
        } else {
            // Rien à faire : le bloc est déjà au mélange et à la pression voulus.
            $erreurs[] = "Le bloc est déjà au mélange et à la pression demandés : rien à faire.";
        }
    }

    return ['resultat' => $resultat, 'erreurs' => $erreurs];
}
