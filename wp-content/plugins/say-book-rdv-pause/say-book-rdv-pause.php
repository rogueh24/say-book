<?php
/**
 * Plugin Name: Say-Book – Pause réservation
 * Description: Affiche un message sur la page RDV et bloque la réservation en ligne pendant l'incident chez l'hébergeur. Désactiver l'extension pour tout remettre comme avant.
 * Version: 1.0.2
 * Author: Say-Book
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Textes affichés : à modifier ici si besoin.
const SBRP_TITRE   = 'Réservation en ligne momentanément indisponible';
const SBRP_TEXTE   = 'Notre hébergeur effectue une maintenance de sécurité. La prise de rendez-vous en ligne revient très bientôt.';
const SBRP_CONTACT = 'En attendant, prenez rendez-vous :';
const SBRP_TEL     = '07 83 09 60 54';
const SBRP_EMAIL   = 'contact@say-book.com';
const SBRP_ADRESSE = '<strong>SAY BARBERSHOP</strong><br>5 rue des 4 frères Mougeotte, <span class="sbrp-nowrap">88100 Saint-Dié-des-Vosges</span>';
const SBRP_PAGE    = 'rdv'; // slug de la page de réservation

add_action( 'wp_footer', 'sbrp_afficher' );

function sbrp_afficher() {
	$sur_page_rdv = is_page( SBRP_PAGE );
	?>
	<style>
		#sbrp-voile{position:fixed;inset:0;z-index:999999;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,.6)}
		#sbrp-voile.sbrp-ouvert{display:flex}
		#sbrp-boite{max-width:460px;width:100%;background:#fff;color:#222;border-radius:14px;padding:28px 24px;text-align:center;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.35)}
		#sbrp-boite h2{margin:0 0 12px;font-size:21px;line-height:1.3;color:#111}
		#sbrp-boite p{margin:0 0 12px}
		#sbrp-boite a{color:#8a5a2b;font-weight:600;white-space:nowrap}
		#sbrp-boite .sbrp-nowrap{white-space:nowrap}
		#sbrp-boite .sbrp-contacts{display:flex;flex-direction:column;align-items:center;gap:8px;margin:0 0 16px}
		#sbrp-boite .sbrp-contacts a{display:inline-flex;align-items:center;gap:8px;text-decoration:none}
		#sbrp-boite .sbrp-contacts svg{width:20px;height:20px;flex:none}
		#sbrp-boite .sbrp-boutons{margin-top:18px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
		#sbrp-boite .sbrp-btn{display:inline-block;padding:10px 18px;border-radius:999px;border:0;background:#111;color:#fff;font-weight:600;text-decoration:none;cursor:pointer;font-size:15px}
		<?php if ( $sur_page_rdv ) : ?>
		.latepoint-w,.latepoint-booking-form-element-w{pointer-events:none!important;opacity:.35!important;filter:grayscale(1)}
		<?php endif; ?>
	</style>
	<div id="sbrp-voile" role="dialog" aria-modal="true" aria-labelledby="sbrp-titre">
		<div id="sbrp-boite">
			<h2 id="sbrp-titre"><?php echo esc_html( SBRP_TITRE ); ?></h2>
			<p><?php echo esc_html( SBRP_TEXTE ); ?></p>
			<p><?php echo esc_html( SBRP_CONTACT ); ?></p>
			<div class="sbrp-contacts">
				<a href="tel:+33<?php echo esc_attr( substr( str_replace( ' ', '', SBRP_TEL ), 1 ) ); ?>" aria-label="Téléphone">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1z"/></svg>
					<?php echo esc_html( SBRP_TEL ); ?>
				</a>
				<a href="mailto:<?php echo esc_attr( SBRP_EMAIL ); ?>" aria-label="E-mail">
					<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5-8-5V6l8 5 8-5z"/></svg>
					<?php echo esc_html( SBRP_EMAIL ); ?>
				</a>
			</div>
			<p><?php echo wp_kses_post( SBRP_ADRESSE ); ?></p>
			<div class="sbrp-boutons">
				<?php if ( $sur_page_rdv ) : ?>
					<a class="sbrp-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a>
				<?php else : ?>
					<button type="button" class="sbrp-btn" id="sbrp-fermer">J'ai compris</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script>
	(function () {
		var voile = document.getElementById('sbrp-voile');
		function ouvrir() { voile.classList.add('sbrp-ouvert'); }
		var fermer = document.getElementById('sbrp-fermer');
		if (fermer) fermer.addEventListener('click', function () { voile.classList.remove('sbrp-ouvert'); });

		<?php if ( $sur_page_rdv ) : ?>
		ouvrir();
		<?php endif; ?>

		// Partout sur le site : un clic sur un bouton de réservation LatePoint ouvre le message au lieu du formulaire.
		document.addEventListener('click', function (e) {
			var cible = e.target.closest('.latepoint-book-button, .os-trigger-booking, [data-os-action*="booking"], .latepoint-w');
			if (!cible || voile.contains(e.target)) return;
			e.preventDefault();
			e.stopImmediatePropagation();
			ouvrir();
		}, true);
	})();
	</script>
	<?php
}
