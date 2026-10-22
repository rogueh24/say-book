<?php
/**
 * Plugin Name: Say Barbershop - LatePoint Client
 * Description: Ajoute un onglet "Mes Rendez-vous" dans l'espace "Mon Compte" de WooCommerce pour afficher le dashboard LatePoint.
 * Version: 1.0
 * Author: Say Barbershop
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Sécurité : empêche l'accès direct au fichier
}

// 1. AJOUT DU LIEN DANS LE MENU
add_filter( 'woocommerce_account_menu_items', 'say_add_latepoint_menu_item', 20 );
function say_add_latepoint_menu_item( $items ) {
    $new_items = array();
    
    // On recrée le tableau pour insérer notre lien juste avant "Déconnexion"
    foreach ( $items as $key => $value ) {
        if ( 'customer-logout' === $key ) {
            $new_items['mes-rendez-vous'] = 'Mes Rendez-vous';
        }
        $new_items[ $key ] = $value;
    }
    return $new_items;
}

// 2. ENREGISTREMENT DE L'URL (ENDPOINT)
add_action( 'init', 'say_add_latepoint_endpoint' );
function say_add_latepoint_endpoint() {
    add_rewrite_endpoint( 'mes-rendez-vous', EP_ROOT | EP_PAGES );
}

// 3. AFFICHAGE DU CONTENU (SHORTCODE)
add_action( 'woocommerce_account_mes-rendez-vous_endpoint', 'say_latepoint_content' );
function say_latepoint_content() {
    echo '<div class="say-latepoint-dashboard">';
    echo '<h3 style="margin-bottom:20px;">Gérer mes rendez-vous</h3>';
    // Affiche le dashboard client de LatePoint
    echo do_shortcode( '[latepoint_customer_dashboard]' );
    echo '</div>';
}

// 4. RÉGLAGE DE L'ERREUR 404 À L'ACTIVATION
// Cette fonction force WordPress à reconnaître le nouveau lien dès que tu actives le plugin
register_activation_hook( __FILE__, 'say_flush_rewrite_rules' );
function say_flush_rewrite_rules() {
    add_rewrite_endpoint( 'mes-rendez-vous', EP_ROOT | EP_PAGES );
    flush_rewrite_rules();
}

// 5. PETIT BONUS : AJOUT D'UNE ICÔNE (CALENDRIER) DANS LE MENU
add_action('wp_head', 'say_latepoint_icon_css');
function say_latepoint_icon_css() {
    if ( is_account_page() ) {
        echo '<style>
            /* Ajoute une icône calendrier devant le lien */
            .woocommerce-MyAccount-navigation-link--mes-rendez-vous a:before {
                content: "\f508"; /* Code Dashicon Calendrier */
                font-family: "dashicons";
                margin-right: 10px;
                vertical-align: middle;
            }
        </style>';
    }
}