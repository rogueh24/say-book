<?php
/**
 * Plugin Name: LatePoint Password Sync
 * Description: Synchronise les changements de mot de passe entre LatePoint et WordPress/WooCommerce dans les deux sens.
 * Version: 2.0.0
 * Author: Dryss Deppen
 * License: GPLv2 or later
 * Text Domain: latepoint-password-sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'LPSync_LatePoint_Password_Sync' ) ) {

	final class LPSync_LatePoint_Password_Sync {

		const OPTION_ENABLED         = 'lpsync_enabled';
		const OPTION_LOGGING         = 'lpsync_logging';
		const OPTION_SYNC_WP_TO_LP   = 'lpsync_wp_to_lp';
		const VERSION                = '2.0.0';

		/**
		 * Mot de passe capturé tôt dans la requête AJAX, avant que LatePoint ne traite les données.
		 *
		 * @var string|null
		 */
		private static $captured_password = null;

		/**
		 * Verrou anti-boucle infinie.
		 *
		 * @var bool
		 */
		private static $in_progress = false;

		// -------------------------------------------------------------------------
		// Démarrage
		// -------------------------------------------------------------------------

		public static function boot() {
			add_action( 'plugins_loaded', array( __CLASS__, 'init' ) );
		}

		public static function init() {
			// UI admin.
			add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

			if ( ! self::is_enabled() ) {
				return;
			}

			// ── Direction LatePoint → WordPress ──────────────────────────────────
			//
			// Étape 1 : capturer le mot de passe AVANT que LatePoint ne traite la
			// requête AJAX.  On se branche sur wp_ajax_* en priorité 1 (très tôt).
			add_action( 'wp_ajax_latepoint_route_request',        array( __CLASS__, 'capture_password_early' ), 1 );
			add_action( 'wp_ajax_nopriv_latepoint_route_request', array( __CLASS__, 'capture_password_early' ), 1 );

			// Filet de sécurité : si LatePoint expose un filtre sur les paramètres
			// avant sauvegarde, on y capture aussi le mot de passe.
			add_filter( 'latepoint_customer_params_to_save',  array( __CLASS__, 'capture_from_params' ), 1, 1 );
			add_filter( 'latepoint_before_customer_update',   array( __CLASS__, 'capture_from_customer_object' ), 1, 1 );
			add_filter( 'latepoint_before_customer_create',   array( __CLASS__, 'capture_from_customer_object' ), 1, 1 );

			// Étape 2 : appliquer le mot de passe capturé une fois le client sauvé.
			add_action( 'latepoint_customer_updated', array( __CLASS__, 'sync_lp_to_wp' ), 20, 1 );
			add_action( 'latepoint_customer_created', array( __CLASS__, 'sync_lp_to_wp' ), 20, 1 );

			// ── Direction WordPress → LatePoint ──────────────────────────────────
			if ( (int) get_option( self::OPTION_SYNC_WP_TO_LP, 0 ) === 1 ) {
				add_action( 'profile_update',   array( __CLASS__, 'sync_wp_to_lp' ), 20, 2 );
				add_action( 'password_reset',   array( __CLASS__, 'on_password_reset' ), 20, 2 );
				add_action( 'user_register',    array( __CLASS__, 'sync_wp_to_lp_by_id' ), 20, 1 );
			}
		}

		// =========================================================================
		// Direction LatePoint → WordPress
		// =========================================================================

		/**
		 * Capture tôt dans la requête AJAX le mot de passe avant que LatePoint
		 * ne le consomme / efface.
		 */
		public static function capture_password_early() {
			if ( self::$captured_password !== null ) {
				return; // déjà capturé
			}

			// Dump de debug : log tous les champs POST pour aider au diagnostic.
			if ( (int) get_option( self::OPTION_LOGGING, 0 ) === 1 ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$safe_post = array_map( function( $v ) {
					return is_string( $v ) ? '[string:' . strlen( $v ) . ']' : ( is_array( $v ) ? '[array:' . implode( ',', array_keys( $v ) ) . ']' : gettype( $v ) );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				}, wp_unslash( $_POST ) );
				self::log( 'POST keys early: ' . wp_json_encode( $safe_post ) );
			}

			$password = self::extract_password_from_post();

			if ( null !== $password && '' !== $password ) {
				self::$captured_password = $password;
				self::log( 'Mot de passe capturé tôt dans la requête AJAX.' );
			}
		}

		/**
		 * Capture depuis un filtre LatePoint "params_to_save" (tableau associatif).
		 *
		 * @param array $params
		 * @return array  (inchangé, on ne fait que lire)
		 */
		public static function capture_from_params( $params ) {
			if ( self::$captured_password !== null || ! is_array( $params ) ) {
				return $params;
			}

			$candidates = array( 'new_password', 'password', 'customer_password', 'account_password' );
			foreach ( $candidates as $key ) {
				if ( isset( $params[ $key ] ) && is_string( $params[ $key ] ) && '' !== $params[ $key ] ) {
					self::$captured_password = $params[ $key ];
					self::log( 'Mot de passe capturé dans latepoint_customer_params_to_save (' . $key . ').' );
					break;
				}
			}

			return $params;
		}

		/**
		 * Capture depuis l'objet customer avant sauvegarde si LatePoint fournit
		 * un champ password sur l'objet.
		 *
		 * @param mixed $customer
		 * @return mixed  (inchangé)
		 */
		public static function capture_from_customer_object( $customer ) {
			if ( self::$captured_password !== null || ! is_object( $customer ) ) {
				return $customer;
			}

			$candidates = array( 'new_password', 'password', 'plain_password', 'customer_password' );
			foreach ( $candidates as $prop ) {
				if ( isset( $customer->{$prop} ) && is_string( $customer->{$prop} ) && '' !== $customer->{$prop} ) {
					self::$captured_password = $customer->{$prop};
					self::log( 'Mot de passe capturé sur l\'objet customer avant sauvegarde (' . $prop . ').' );
					break;
				}
			}

			return $customer;
		}

		/**
		 * Appliquer le mot de passe capturé sur le compte WordPress.
		 *
		 * @param mixed $customer
		 */
		public static function sync_lp_to_wp( $customer ) {
			if ( self::$in_progress ) {
				return;
			}

			// Récupérer le mot de passe : capturé tôt, ou ultime tentative sur $_POST.
			$password = self::$captured_password;

			if ( null === $password || '' === $password ) {
				// Dernier recours : tenter une lecture directe (cas sans AJAX ou hook early manqué).
				$password = self::extract_password_from_post();
			}

			if ( null === $password || '' === $password ) {
				self::log( 'Aucun mot de passe détecté dans la requête – synchronisation ignorée.' );
				return;
			}

			// Trouver l'utilisateur WordPress.
			$wp_user = self::resolve_wp_user( $customer );

			if ( ! $wp_user ) {
				$email = self::get_customer_email( $customer );
				self::log( sprintf( 'Aucun utilisateur WordPress trouvé pour "%s".', $email ) );
				return;
			}

			// Appliquer le filtre permettant d'annuler ou modifier.
			$password = apply_filters( 'lpsync_resolved_password', $password, $wp_user );

			if ( null === $password || '' === $password ) {
				self::log( 'Synchronisation annulée par le filtre lpsync_resolved_password.' );
				return;
			}

			self::$in_progress = true;

			try {
				/*
				 * On utilise wp_update_user() plutôt que wp_set_password() car :
				 * – wp_set_password() appelle wp_destroy_all_sessions() → déconnexion immédiate.
				 * – wp_update_user() met à jour le hash sans invalider les sessions actives.
				 */
				$result = wp_update_user( array(
					'ID'        => $wp_user->ID,
					'user_pass' => $password,
				) );

				if ( is_wp_error( $result ) ) {
					self::log( sprintf(
						'wp_update_user a retourné une erreur pour user ID %d : %s',
						$wp_user->ID,
						$result->get_error_message()
					) );
				} else {
					self::log( sprintf(
						'[LatePoint → WP] Mot de passe synchronisé pour "%s" (user ID %d).',
						$wp_user->user_email,
						$wp_user->ID
					) );
				}
			} catch ( Throwable $e ) {
				self::log( sprintf( 'Exception lors de la synchronisation : %s', $e->getMessage() ) );
			}

			// Réinitialiser pour les éventuelles requêtes suivantes dans la même exécution.
			self::$captured_password = null;
			self::$in_progress       = false;
		}

		// =========================================================================
		// Direction WordPress → LatePoint
		// =========================================================================

		/**
		 * Déclenché par profile_update (ex. : changement de mot de passe dans WP/WooCommerce).
		 *
		 * @param int      $user_id
		 * @param \WP_User $old_userdata
		 */
		public static function sync_wp_to_lp( $user_id, $old_userdata = null ) {
			if ( self::$in_progress ) {
				return;
			}

			$wp_user = get_userdata( $user_id );
			if ( ! $wp_user ) {
				return;
			}

			// Détecter si le mot de passe a réellement changé.
			if ( $old_userdata instanceof WP_User ) {
				if ( $wp_user->user_pass === $old_userdata->user_pass ) {
					return; // hash inchangé → rien à faire
				}
			}

			self::update_latepoint_customer_password_hash( $wp_user );
		}

		/**
		 * Déclenché par password_reset (formulaire de réinitialisation WP).
		 *
		 * @param \WP_User $user
		 * @param string   $new_password (plain text)
		 */
		public static function on_password_reset( $user, $new_password ) {
			if ( self::$in_progress || ! $user instanceof WP_User ) {
				return;
			}
			// Après password_reset, le hash est déjà mis à jour en base.
			// On recharge l'utilisateur pour avoir le nouveau hash.
			clean_user_cache( $user->ID );
			$fresh_user = get_userdata( $user->ID );
			if ( $fresh_user ) {
				self::update_latepoint_customer_password_hash( $fresh_user );
			}
		}

		/**
		 * Déclenché par user_register.
		 *
		 * @param int $user_id
		 */
		public static function sync_wp_to_lp_by_id( $user_id ) {
			if ( self::$in_progress ) {
				return;
			}
			$wp_user = get_userdata( $user_id );
			if ( $wp_user ) {
				self::update_latepoint_customer_password_hash( $wp_user );
			}
		}

		/**
		 * Met à jour le hash de mot de passe dans la table LatePoint.
		 *
		 * @param \WP_User $wp_user
		 */
		private static function update_latepoint_customer_password_hash( WP_User $wp_user ) {
			global $wpdb;

			// Cherche la table LatePoint (préfixée ou non).
			$table = $wpdb->prefix . 'latepoint_customers';

			// Vérifier que la table existe.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			if ( ! $table_exists ) {
				self::log( sprintf( 'Table %s introuvable – sync WP→LP ignorée.', $table ) );
				return;
			}

			// Chercher le client LatePoint par email ou par wp_user_id.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$lp_customer_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE email = %s OR wp_user_id = %d LIMIT 1",
				$wp_user->user_email,
				$wp_user->ID
			) );

			if ( ! $lp_customer_id ) {
				self::log( sprintf( 'Aucun client LatePoint trouvé pour "%s" (user ID %d) – sync WP→LP ignorée.', $wp_user->user_email, $wp_user->ID ) );
				return;
			}

			self::$in_progress = true;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = $wpdb->update(
				$table,
				array( 'password' => $wp_user->user_pass ),
				array( 'id' => $lp_customer_id ),
				array( '%s' ),
				array( '%d' )
			);

			self::$in_progress = false;

			if ( false === $updated ) {
				self::log( sprintf( '[WP → LatePoint] Échec de la mise à jour du client LP ID %d.', $lp_customer_id ) );
			} else {
				self::log( sprintf( '[WP → LatePoint] Hash synchronisé pour "%s" (LP customer ID %d).', $wp_user->user_email, $lp_customer_id ) );
			}
		}

		// =========================================================================
		// Helpers
		// =========================================================================

		/**
		 * Résout l'objet WP_User à partir d'un objet customer LatePoint.
		 * Priorité : wp_user_id > email.
		 *
		 * @param mixed $customer
		 * @return \WP_User|false
		 */
		private static function resolve_wp_user( $customer ) {
			if ( is_object( $customer ) ) {
				// 1) Via wp_user_id (mode "Utiliser les users WP comme clients").
				foreach ( array( 'wp_user_id', 'wordpress_id', 'user_id' ) as $prop ) {
					if ( ! empty( $customer->{$prop} ) && is_numeric( $customer->{$prop} ) ) {
						$user = get_userdata( (int) $customer->{$prop} );
						if ( $user ) {
							return $user;
						}
					}
				}

				// 2) Via email.
				$email = self::get_customer_email( $customer );
				if ( $email ) {
					$user = get_user_by( 'email', $email );
					if ( $user ) {
						return $user;
					}
				}
			}

			return false;
		}

		/**
		 * Extrait l'email d'un objet customer LatePoint.
		 *
		 * @param mixed $customer
		 * @return string
		 */
		private static function get_customer_email( $customer ) {
			if ( is_object( $customer ) ) {
				foreach ( array( 'email', 'user_email', 'customer_email' ) as $prop ) {
					if ( isset( $customer->{$prop} ) && is_string( $customer->{$prop} ) && is_email( $customer->{$prop} ) ) {
						return $customer->{$prop};
					}
				}

				if ( method_exists( $customer, 'get_email' ) ) {
					$email = $customer->get_email();
					if ( is_string( $email ) && is_email( $email ) ) {
						return $email;
					}
				}
			}

			return '';
		}

		/**
		 * Extrait le mot de passe depuis $_POST / $_REQUEST.
		 * Cherche les noms de champs connus de LatePoint.
		 *
		 * @return string|null
		 */
		private static function extract_password_from_post() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sources = array(
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_unslash( $_POST ),
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				wp_unslash( $_REQUEST ),
			);

			$password_fields = apply_filters( 'lpsync_password_field_names', array(
				'new_password',
				'password',
				'customer_password',
				'account_password',
				'pass1',
				'latepoint_password',
				'customer[new_password]',
				'customer[password]',
				'customer[account_password]',
			) );

			$confirm_fields = apply_filters( 'lpsync_password_confirm_field_names', array(
				'password_confirmation',
				'confirm_password',
				'customer_password_confirmation',
				'pass2',
				'customer[password_confirmation]',
				'customer[new_password_confirmation]',
			) );

			$found_password = null;
			$found_confirm  = null;

			foreach ( $sources as $source ) {
				foreach ( $password_fields as $field ) {
					$val = self::get_nested_value( $source, $field );
					if ( is_string( $val ) && '' !== $val ) {
						$found_password = $val;
						break 2;
					}
				}
			}

			if ( null === $found_password ) {
				return null;
			}

			foreach ( $sources as $source ) {
				foreach ( $confirm_fields as $field ) {
					$val = self::get_nested_value( $source, $field );
					if ( is_string( $val ) && '' !== $val ) {
						$found_confirm = $val;
						break 2;
					}
				}
			}

			if ( null !== $found_confirm && '' !== $found_confirm && $found_confirm !== $found_password ) {
				self::log( 'Mots de passe non identiques – synchronisation ignorée.' );
				return null;
			}

			return $found_password;
		}

		/**
		 * Lit une valeur depuis un tableau en supportant les clés imbriquées
		 * du type "customer[new_password]".
		 *
		 * @param array  $array
		 * @param string $key
		 * @return mixed|null
		 */
		private static function get_nested_value( $array, $key ) {
			if ( ! is_array( $array ) ) {
				return null;
			}

			// Clé directe.
			if ( array_key_exists( $key, $array ) ) {
				return $array[ $key ];
			}

			// Clé imbriquée : "customer[new_password]" → $array['customer']['new_password'].
			if ( false !== strpos( $key, '[' ) && preg_match( '/^([^\[]+)\[([^\]]+)\]$/', $key, $m ) ) {
				$root  = $m[1];
				$child = $m[2];
				if ( isset( $array[ $root ] ) && is_array( $array[ $root ] ) && array_key_exists( $child, $array[ $root ] ) ) {
					return $array[ $root ][ $child ];
				}
			}

			// Recherche récursive légère.
			foreach ( $array as $value ) {
				if ( is_array( $value ) ) {
					$nested = self::get_nested_value( $value, $key );
					if ( null !== $nested ) {
						return $nested;
					}
				}
			}

			return null;
		}

		private static function is_enabled() {
			return (int) get_option( self::OPTION_ENABLED, 1 ) === 1;
		}

		private static function log( $message ) {
			if ( (int) get_option( self::OPTION_LOGGING, 0 ) !== 1 ) {
				return;
			}

			if ( function_exists( 'wc_get_logger' ) ) {
				$logger = wc_get_logger();
				$logger->info( $message, array( 'source' => 'latepoint-password-sync' ) );
				return;
			}

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[LatePoint Password Sync] ' . $message );
		}

		// =========================================================================
		// Interface d'administration
		// =========================================================================

		public static function admin_menu() {
			add_options_page(
				'LatePoint Password Sync',
				'LatePoint Password Sync',
				'manage_options',
				'latepoint-password-sync',
				array( __CLASS__, 'settings_page' )
			);
		}

		public static function register_settings() {
			$bool_args = array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
				'default'           => 0,
			);

			register_setting( 'lpsync_settings', self::OPTION_ENABLED,       array_merge( $bool_args, array( 'default' => 1 ) ) );
			register_setting( 'lpsync_settings', self::OPTION_LOGGING,       $bool_args );
			register_setting( 'lpsync_settings', self::OPTION_SYNC_WP_TO_LP, $bool_args );

			add_settings_section(
				'lpsync_main',
				'Réglages',
				array( __CLASS__, 'section_description' ),
				'latepoint-password-sync'
			);

			add_settings_field( self::OPTION_ENABLED,       'Activer la synchronisation LP → WP', array( __CLASS__, 'field_enabled' ),       'latepoint-password-sync', 'lpsync_main' );
			add_settings_field( self::OPTION_SYNC_WP_TO_LP, 'Activer la synchronisation WP → LP', array( __CLASS__, 'field_sync_wp_to_lp' ), 'latepoint-password-sync', 'lpsync_main' );
			add_settings_field( self::OPTION_LOGGING,       'Activer le debug log',               array( __CLASS__, 'field_logging' ),        'latepoint-password-sync', 'lpsync_main' );
		}

		public static function section_description() {
			echo '<p>Synchronisation bidirectionnelle des mots de passe entre LatePoint et WordPress/WooCommerce.</p>';
			echo '<p>Compatible avec l\'option LatePoint <em>"Utiliser les utilisateurs WordPress comme clients"</em>.</p>';
		}

		public static function field_enabled() {
			$val = (int) get_option( self::OPTION_ENABLED, 1 );
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_ENABLED ) . '" value="1" ' . checked( 1, $val, false ) . '> LatePoint → WordPress/WooCommerce</label>';
		}

		public static function field_sync_wp_to_lp() {
			$val = (int) get_option( self::OPTION_SYNC_WP_TO_LP, 0 );
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_SYNC_WP_TO_LP ) . '" value="1" ' . checked( 1, $val, false ) . '> WordPress/WooCommerce → LatePoint</label>';
		}

		public static function field_logging() {
			$val = (int) get_option( self::OPTION_LOGGING, 0 );
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_LOGGING ) . '" value="1" ' . checked( 1, $val, false ) . '>';
			echo ' Écrire les événements dans le log (WooCommerce ou PHP error_log)</label>';
		}

		public static function sanitize_checkbox( $value ) {
			return empty( $value ) ? 0 : 1;
		}

		public static function settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
			<div class="wrap">
				<h1>LatePoint Password Sync <small style="font-size:.6em;color:#888;">v<?php echo esc_html( self::VERSION ); ?></small></h1>

				<form method="post" action="options.php">
					<?php
					settings_fields( 'lpsync_settings' );
					do_settings_sections( 'latepoint-password-sync' );
					submit_button();
					?>
				</form>

				<hr>
				<h2>Comment ça marche</h2>
				<table class="widefat" style="max-width:720px">
					<thead><tr><th>Direction</th><th>Mécanisme</th></tr></thead>
					<tbody>
					<tr>
						<td><strong>LatePoint → WP</strong></td>
						<td>
							Capture le mot de passe au tout début de la requête AJAX LatePoint (avant traitement),
							puis le synchronise via <code>wp_update_user()</code> sur le compte WP/WooCommerce
							lié au même email ou au même <code>wp_user_id</code>.
							<br><em>N'invalide pas la session en cours.</em>
						</td>
					</tr>
					<tr>
						<td><strong>WP → LatePoint</strong></td>
						<td>
							Sur les hooks <code>profile_update</code> et <code>password_reset</code>,
							met à jour le hash de mot de passe directement dans la table
							<code>wp_latepoint_customers</code>.
						</td>
					</tr>
					</tbody>
				</table>

				<h2 style="margin-top:1.5em">Filtres disponibles</h2>
				<ul>
					<li><code>lpsync_password_field_names</code> – tableau des noms de champs POST à inspecter</li>
					<li><code>lpsync_password_confirm_field_names</code> – tableau des champs de confirmation</li>
					<li><code>lpsync_resolved_password</code> – modifier ou annuler (retourner <code>null</code>) le mot de passe avant synchro</li>
				</ul>

				<h2 style="margin-top:1.5em">Debug</h2>
				<p>Activez le debug log, effectuez un changement de mot de passe depuis LatePoint, puis consultez :</p>
				<ul>
					<li><strong>WooCommerce actif</strong> : WooCommerce › Status › Logs › source <code>latepoint-password-sync</code></li>
					<li><strong>Sans WooCommerce</strong> : <code>wp-content/debug.log</code> (si <code>WP_DEBUG_LOG</code> est activé)</li>
				</ul>
				<p>Le log indique les clés POST disponibles, ce qui vous permet d'ajuster les noms de champs
				via le filtre <code>lpsync_password_field_names</code> si nécessaire.</p>
			</div>
			<?php
		}
	}

	LPSync_LatePoint_Password_Sync::boot();
}