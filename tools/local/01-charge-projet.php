<?php
/**
 * Plugin Name: Copie locale — mu-plugins du projet
 * Description: Charge les mu-plugins versionnés de C:\Claude\say-book\wp-content\mu-plugins, pour les tester ici avant le déploiement.
 */
foreach ( glob( 'C:/Claude/say-book/wp-content/mu-plugins/*.php' ) ?: array() as $fichier ) {
	require_once $fichier;
}
