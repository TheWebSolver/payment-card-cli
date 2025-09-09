<?php
declare( strict_types = 1 );

! defined( 'THEWEBSOLVER_CODEGARAGE_PAYMENT_CARD_CLI' )
	|| ( fwrite( STDERR, 'The Payment Card Cli package has already been initialized.' ) && die );

use TheWebSolver\Codegarage\Cli\Cli;
use TheWebSolver\Codegarage\Cli\Bootstrap;
use TheWebSolver\Codegarage\Cli\Container;

registerMainBootstrapFileForPaymentCardCommands();

Bootstrap::commands(
	action: loadContainerAndRunPaymentCardCommands( ... ),
	packages: [
		'main' => 'thewebsolver/payment-card',
		'cli'  => 'thewebsolver/payment-card-cli',
	]
);

/** Loads main CLI package bootstrap file which in turn discovers composer autoloader & configures commands. */
function registerMainBootstrapFileForPaymentCardCommands( string $slash = DIRECTORY_SEPARATOR ): void {
	require_once __DIR__ . "{$slash}vendor{$slash}thewebsolver{$slash}cli{$slash}bootstrap.php";
}

function loadContainerAndRunPaymentCardCommands( Bootstrap $bootstrap ): void {
	define( 'THEWEBSOLVER_CODEGARAGE_PAYMENT_CARD_CLI', true );
	define( 'THEWEBSOLVER_CODEGARAGE_PAYMENT_CARD_ROOT_PATH', $bootstrap->rootPath );

	$bootstrap->loadDirectories();

	$container = $bootstrap->config['container'] ?? new Container(
		bindings: [ Cli::class => [ Cli::class, true ] ]
	);

	$bootstrap->config['commandLoader']->load( $container );

	$container->get( Cli::class )->run();
}
