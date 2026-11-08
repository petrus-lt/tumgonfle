<?php
require_once __DIR__ . '/gaz_reel.php';

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
 *   'modele'   'ideale' (gaz parfait, défaut) ou 'reel' (facteur de
 *              compressibilité, modèle Subsurface/realblender, gaz_reel.php)
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
    // Modèle de gaz : 'ideale' = gaz parfait (comportement historique,
    // défaut), 'reel' = correction par le facteur de compressibilité Z.
    $modele = (($entree['modele'] ?? 'ideale') === 'reel') ? 'reel' : 'ideale';

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

        // --- Grandeurs "normales" : la quantité de matière, conservée ---
        // En GAZ PARFAIT, le nombre de moles est proportionnel à p*V : tout se
        // ramène aux pressions (quantité = pression en "bar-litres").
        // En GAZ RÉEL, on exprime les quantités en litres normaux
        //   q(p, mélange) = p * Z(1) / Z(p)
        // et on retrouve la pression correspondant à une quantité par
        // l'inversion itérative find_p() (voir gaz_reel.php). Les bilans
        // d'O2 ci-dessous sont alors RIGOUREUSEMENT les mêmes, ils portent
        // sur q au lieu de p.
        $q_norm = function (float $pression, float $fraction) use ($modele): float {
            return $modele === 'reel' ? quantite_normale($pression, $fraction) : $pression;
        };
        $vers_pression = function (float $fraction, float $quantite) use ($modele): float {
            return $modele === 'reel' ? pression_pour_quantite($fraction, $quantite) : $quantite;
        };

        $q_i = $q_norm($p_init, $f_i);    // quantité de gaz au départ
        $q_f = $q_norm($p_cible, $f_t);   // quantité de gaz à la cible

        // Quantité minimale de mélange riche qu'il faut CONSERVER au bloc
        // pour atteindre la cible par simple vidange + air :
        //   q_cut = q_cible * (F_cible - 0.21) / (F_init - 0.21)
        // (F_init > 0.21 est garanti ici : f_cible ≥ 21 et f_cible < f_init ;
        // sinon la formule n'a pas de sens et on reste sur q_f.)
        $q_cut = ($f_i > $air) ? $q_f * ($f_t - $air) / ($f_i - $air) : $q_f;

        if ($f_t < $f_i - $eps && $q_i >= $q_cut) {
            // ===== Méthode "dilution" : on veut MOINS d'O2 que le départ. =====
            // On ne peut pas retirer l'O2 sélectivement ; on vide donc une
            // partie du bloc (la fraction d'O2 du gaz RESTANT ne change pas
            // pendant la vidange), puis on complète à l'air (21% d'O2).
            //
            // Équilibre de l'O2 en quantités conservées :
            //   F_cible*q_cible = F_init*q_cut + 0.21*(q_cible - q_cut)
            //                     ^O2 restant    ^O2 amené par l'air ajouté
            // On isole la quantité à conserver, puis sa pression de coupure :
            //   q_cut = q_cible * (F_cible - 0.21) / (F_init - 0.21)
            //   p_cut = pression(q_cut, mélange de départ)
            //
            // Exemple : 200 bar d'EAN50 que l'on veut convertir en EAN32 à
            // 200 bar. Gaz parfait : p_cut = 200 * 0.11/0.29 ≈ 75.9 bar.
            // Gaz réel : q_cible ≈ 195.1 NL par litre de bloc, q_cut ≈ 74.0,
            // d'où p_cut ≈ 72.6 bar (l'écart vient de ce que l'EAN50 se
            // comprime différemment de l'EAN32 aux hautes pressions).
            $p_cut = $vers_pression($f_i, $q_cut);

            // Rien n'est "acheté" dans cette méthode : seul de l'air est
            // ajouté (non facturé ici). Le gaz jeté à la vidange est
            // chiffré en litres d'O2 gazeux perdus, à titre indicatif.
            $resultat = [
                'methode'    => 'dilution',
                'modele'     => $modele,
                'p_cut'      => round($p_cut, 1),           // vidanger jusqu'ici
                'p_cible'    => $p_cible,                   // compléter jusqu'ici
                'f_init'     => $f_init,
                'o2'         => 0.0,                        // pas d'O2 ajouté
                'prix'       => 0.0,                        // air non facturé
                'air_ajoute' => round(($q_f - $q_cut) * $v_bloc, 1), // litres normaux d'air
                'o2_gache'   => round($f_i * ($q_i - $q_cut) * $v_bloc, 1),
                'nom'        => $nom,
                'v_bloc'     => $v_bloc,
            ];
        } elseif ($f_t > $f_i + $eps || $q_i < $q_f) {
            // ===== Méthode "enrichissement" : O2 pur puis complément d'air. =====
            // Cas : on veut PLUS d'O2 ; OU le même mélange à plus haute
            // pression (compléter à l'air seul ferait baisser le % d'O2) ;
            // OU on veut MOINS d'O2 mais le départ est trop pauvre en quantité
            // pour la simple vidange (q_init < q_cut, cf. branche précédente).
            // Le rachat d'O2 compense alors ce qui manque, et l'air final
            // dilue jusqu'à la cible. C'est exactement la recette "10 bar
            // d'O2 pur -> EAN32 à 200 bar" : ajouter ~17.8 bar d'O2 (arrêt à
            // ~27.8 bar), puis compléter à l'air. On a q_i < q_cut < q_f,
            // donc la garde "pas de place" ci-dessous ne peut pas se déclencher.
            if ($f_t > $f_i + $eps && $q_i >= $q_f) {
                // Pas de place dans le bloc pour ajouter de l'O2.
                $erreurs[] = "La pression initiale doit être inférieure à la pression cible pour ajouter de l'O2.";
            } else {
                // Équilibre de l'O2 en quantités conservées. En appelant Q_o2
                // la quantité d'O2 pur ajoutée, le complément restant étant
                // de l'air (21% d'O2) :
                //   F_cible*q_cible = F_init*q_init      (déjà en place)
                //                   + Q_o2               (O2 pur ajouté)
                //                   + 0.21*(q_cible - q_init - Q_o2)  (air)
                // D'où, en isolant Q_o2 :
                //   Q_o2 = (F_cible*q_cible - F_init*q_init - 0.21*(q_cible - q_init)) / (1 - 0.21)
                // À mélange constant (F_cible = F_init) elle se réduit à
                //   Q_o2 = (q_cible - q_init)*(F - 0.21)/0.79
                // qui est bien la quantité d'O2 à ajouter pour garder la même
                // fraction en montant en pression.
                $q_o2 = (($f_t * $q_f) - ($f_i * $q_i) - $air * ($q_f - $q_i)) / (1 - $air);

                // Un calcul "négatif" se produit quand le départ est déjà plus
                // riche que nécessaire ; la garde ci-dessus l'écarte, on met
                // max(0,...) en ceinture-bretelles.
                $q_o2 = max(0, $q_o2);

                // Mélange intermédiaire après tout l'O2, avant le complément
                // d'air : sa fraction décide de la pression manométrique
                // d'arrêt de l'O2 (en gaz réel, Z du mélange intermédiaire
                // diffère de celui du départ ET de la cible).
                $q_apres_o2 = $q_i + $q_o2;
                $f_apres_o2 = $q_apres_o2 > 0 ? (($f_i * $q_i) + $q_o2) / $q_apres_o2 : $f_i;
                $p_fin_o2   = $vers_pression($f_apres_o2, $q_apres_o2);

                // Conversion litres et facturation : la quantité d'O2 (en
                // litres normaux, c'est-à-dire en "bar" équivalent à 1 atm en
                // gaz parfait) fois le volume du bloc = litres d'O2 utilisés.
                $litres_o2  = $q_o2 * $v_bloc;
                $prix_total = ($litres_o2 / 1000) * $prix_m3;

                $resultat = [
                    'methode'  => 'o2',
                    'modele'   => $modele,
                    'p_o2'     => round($q_o2, 1), // à ajouter (bar au sens gaz parfait)
                    'p_fin_o2' => round($p_fin_o2, 1), // arrêt O2
                    'o2'       => round($litres_o2, 2),
                    'prix'     => round($prix_total, 2),
                    'p_cible'  => $p_cible,
                    'f_init'   => $f_init,
                    'nom'      => $nom,
                    'v_bloc'   => $v_bloc,
                ];

                // Vérification finale : est-ce que l'O2 nécessaire dépasse la
                // pression finale ? (mélange physiquement impossible)
                $impossible = ($modele === 'reel')
                    ? ($q_apres_o2 > $q_f + 1e-9)
                    : ($resultat['p_fin_o2'] > $p_cible);
                if ($impossible) {
                    $erreurs[] = "Mélange impossible : la quantité d'O2 pur requise dépasse la pression cible.";
                    $resultat = null;
                }
            }
        } elseif ($p_init > $p_cible) {
            // ===== Mélange constant en sur-pression : simple vidange. =====
            // Vidanger ne change pas la fraction d'O2 : redescendre à P_cible
            // suffit, aucun gaz n'est ajouté. Identique dans les deux modèles.
            $resultat = [
                'methode'    => 'vidange',
                'modele'     => $modele,
                'p_cut'      => round($p_cible, 1),
                'p_cible'    => $p_cible,
                'f_init'     => $f_init,
                'o2'         => 0.0,
                'prix'       => 0.0,
                'air_ajoute' => 0.0,
                'o2_gache'   => round($f_i * ($q_i - $q_f) * $v_bloc, 1),
                'nom'        => $nom,
                'v_bloc'     => $v_bloc,
            ];
        } else {
            // Rien à faire : le bloc est déjà au mélange et à la pression voulus.
            $erreurs[] = "Le bloc est déjà au mélange et à la pression demandés : rien à faire.";
        }
    }

    return ['resultat' => $resultat, 'erreurs' => $erreurs];
}
