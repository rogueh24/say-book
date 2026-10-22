<?php
// Routeur du serveur PHP intégré pour la copie locale (équivalent des règles .htaccess de WordPress).
$racine = __DIR__ . '/site';
$chemin = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$fichier = realpath( $racine . $chemin );

if ( $fichier && str_starts_with( $fichier, realpath( $racine ) ) ) {
	if ( is_file( $fichier ) && ! str_ends_with( $fichier, '.php' ) ) {
		return false; // fichier statique servi tel quel
	}
	if ( is_dir( $fichier ) && is_file( $fichier . '/index.php' ) ) {
		$fichier .= '/index.php';
	}
	if ( is_file( $fichier ) && str_ends_with( $fichier, '.php' ) ) {
		$_SERVER['SCRIPT_NAME'] = substr( $fichier, strlen( realpath( $racine ) ) );
		$_SERVER['SCRIPT_FILENAME'] = $fichier;
		chdir( dirname( $fichier ) );
		require $fichier;
		return true;
	}
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $racine . '/index.php';
chdir( $racine );
require $racine . '/index.php';
