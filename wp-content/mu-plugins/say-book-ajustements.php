<?php
/**
 * Plugin Name: Say-Book – ajustements
 * Description: Petits réglages d'affichage du site, versionnés dans le dépôt rogueh24/say-book (toujours actif : mu-plugin).
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'saybook_ajustements_js', 100 );

/**
 * Réservation LatePoint sur téléphone : le popup « Résumé » ne s'ouvre que si le client touche
 * l'icône panier (utile pour retirer une prestation). S'il s'ouvre tout seul (constaté sur Android
 * en choisissant une prestation), on le referme aussitôt.
 */
function saybook_ajustements_js() {
	?>
	<script id="say-book-ajustements">
	(function () {
		var dernierToucher = 0;
		var DECLENCHEUR = '.latepoint-lightbox-summary-trigger';
		['pointerdown', 'touchstart', 'click', 'keydown'].forEach(function (type) {
			document.addEventListener(type, function (e) {
				if (e.target.closest && e.target.closest(DECLENCHEUR)) {
					dernierToucher = Date.now();
				}
			}, true);
		});
		// LatePoint peut remplacer le formulaire après le chargement : on surveille toute la page.
		new MutationObserver(function (changements) {
			if (Date.now() - dernierToucher <= 1000) {
				return;
			}
			changements.forEach(function (c) {
				if (c.target.classList && c.target.classList.contains('show-summary-on-mobile')) {
					c.target.classList.remove('show-summary-on-mobile');
				}
			});
		}).observe(document.documentElement, { attributes: true, attributeFilter: ['class'], subtree: true });
	})();
	</script>
	<?php
}
