<?php
/**
 * Plugin Name: Say-Book – ajustements
 * Description: Petits réglages techniques du site, versionnés dans le dépôt rogueh24/say-book (toujours actif : mu-plugin).
 * Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'litespeed_optm_js_defer_exc', 'saybook_ajustements_js_non_retardes' );
add_filter( 'litespeed_optm_gm_js_exc', 'saybook_ajustements_js_non_retardes' ); // liste utilisée en « mode invité »

/**
 * LiteSpeed Cache retarde tous les scripts jusqu'au premier toucher. Sur certains téléphones,
 * LatePoint s'exécutait alors avant sa bibliothèque du champ téléphone (erreur « Cannot read
 * properties of undefined (reading 'getInstance') » à l'étape « Vos coordonnées », réservation
 * bloquée). On charge donc normalement jQuery, les dépendances de LatePoint et LatePoint lui-même.
 *
 * Le popup « Résumé » de LatePoint sur mobile est laissé tel quel (choix du propriétaire, 23/10/2026).
 */
function saybook_ajustements_js_non_retardes( $exclusions ) {
	return array_merge(
		(array) $exclusions,
		array( 'jquery.min.js', 'jquery-migrate.min.js', 'wp-includes/js/dist/hooks', 'wp-includes/js/dist/i18n', 'wp-i18n', 'latepoint' )
	);
}
