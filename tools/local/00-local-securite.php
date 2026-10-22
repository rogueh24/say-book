<?php
/**
 * Plugin Name: Copie locale — sécurité (rien ne sort)
 * Description: Uniquement sur la copie locale de say-book. Bloque toutes les requêtes HTTP sortantes, tous les e-mails, et garde désactivées les extensions qui contactent l'extérieur (SMS, WhatsApp, agendas, Mailchimp, paiements…). Ce fichier n'est PAS dans le dépôt et ne doit jamais aller sur le serveur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garde-fou : ne rien faire ailleurs que sur la copie locale.
if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || WP_ENVIRONMENT_TYPE !== 'local' ) {
	return;
}

// 1. Requêtes HTTP : toutes bloquées, y compris vers le site local lui-même (le serveur PHP
//    intégré ne traite qu'une requête à la fois : un appel à soi-même bloquerait la page).
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	error_log( '[copie locale] requête bloquée : ' . (string) wp_parse_url( $url, PHP_URL_HOST ) );
	return new WP_Error( 'copie_locale', 'Requête sortante bloquée sur la copie locale.' );
}, 0, 3 );

// 2. E-mails : jamais envoyés, seulement notés dans le journal.
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	error_log( '[copie locale] e-mail non envoyé : ' . ( is_array( $atts['to'] ) ? count( $atts['to'] ) . ' destinataires' : '1 destinataire' ) . ' — ' . ( $atts['subject'] ?? '' ) );
	return true;
}, 0, 2 );

// 3. Extensions qui contactent l'extérieur : toujours désactivées ici.
const COPIE_LOCALE_EXTENSIONS_COUPEES = array(
	'latepoint-sms-twilio/latepoint-sms-twilio.php',
	'latepoint-whatsapp-meta/latepoint-whatsapp-meta.php',
	'latepoint-google-calendar/latepoint-google-calendar.php',
	'latepoint-outlook-calendar/latepoint-outlook-calendar.php',
	'latepoint-apple-calendar/latepoint-apple-calendar.php',
	'latepoint-mailchimp/latepoint-mailchimp.php',
	'latepoint-shortener-shortio/latepoint-shortener-shortio.php',
	'suremails/suremails.php',
	'facebook-for-woocommerce/facebook-for-woocommerce.php',
	'google-site-kit/google-site-kit.php',
	'woocommerce-payments/woocommerce-payments.php',
	'litespeed-cache/litespeed-cache.php',
	'customer-reviews-collector-for-woocommerce/customer-reviews-collector-for-woocommerce.php',
	'wp-reviews-plugin-for-google/wp-reviews-plugin-for-google.php',
);
add_filter( 'option_active_plugins', function ( $plugins ) {
	return array_values( array_diff( (array) $plugins, COPIE_LOCALE_EXTENSIONS_COUPEES ) );
}, 0 );

// 4. Bandeau pour ne jamais confondre avec le vrai site.
add_action( 'admin_bar_menu', function ( $bar ) {
	$bar->add_node( array( 'id' => 'copie-locale', 'title' => '⚠ COPIE LOCALE — rien ne sort', 'meta' => array( 'html' => '<style>#wp-admin-bar-copie-locale>.ab-item{background:#b32d2e!important;color:#fff!important}</style>' ) ) );
}, 1 );
add_action( 'wp_footer', function () {
	if ( ! is_admin_bar_showing() ) {
		echo '<div style="position:fixed;left:8px;bottom:8px;z-index:999999;background:#b32d2e;color:#fff;font:12px/1 sans-serif;padding:6px 8px;border-radius:4px">COPIE LOCALE</div>';
	}
} );
