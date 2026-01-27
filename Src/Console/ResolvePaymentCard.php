<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Console;

use LogicException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\CardResolver;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\PaymentCard\ConsoleResolvedAction;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvesCard;
use TheWebSolver\Codegarage\PaymentCard\Helper\ResolvedMessageHandler;

#[Command( 'resolve', 'payment-card', 'Resolves payment card against payload resource provided' )]
#[Positional( 'card-number', 'Payment Card Number to resolve against payload', isOptional: false )]
#[Flag( 'all', 'Continue further Payment Card creation after first resolve', isNegatable: false )]
#[Associative( 'payload', 'Payload to create Payment Card instances to validate given card number', isOptional: false, isVariadic: true, shortcut: 'P' )]
class ResolvePaymentCard extends Console {
	public function __construct(
		private readonly ResolvesCard $resolver = new CardResolver(),
		private ConsoleResolvedAction $handler = new ResolvedMessageHandler()
	) {
		parent::__construct();
	}

	public function initialize( InputInterface $input, OutputInterface $output ): void {
		if ( ! $input->getOption( 'payload' ) ) {
			throw new LogicException( 'Atleast one payload resource path is required to validate Payment Card Number' );
		}
	}

	public function execute( InputInterface $input, OutputInterface $output ): int {
		$cardNumber = $input->getArgument( 'card-number' );

		assert( is_string( $cardNumber ) );

		$resolvedCards = $this->resolver->for( $cardNumber )
			->using( ...$this->createFactoriesFromPayload( $input ) )
			->handleWith( $this->handler->usingIO( $input, $output ) )
			->resolve( static::shouldExitOnResolve( $input ) );

		$output->writeln( sprintf( 'Given payment card number "%1$s" is %2$s', $cardNumber, null !== $resolvedCards ? 'valid' : 'invalid' ) );

		return null !== $resolvedCards ? self::SUCCESS : self::FAILURE;
	}

	public static function shouldExitOnResolve( InputInterface $input ): bool {
		return false !== $input->getOption( 'all' ) ? false : true;
	}

	/** @return non-empty-list<PaymentCardFactory> */
	private function createFactoriesFromPayload( InputInterface $input ): array {
		/** @var non-empty-list<non-empty-string> */
		$payloads  = $input->getOption( 'payload' );
		$factories = [];

		foreach ( $payloads as $resourcePath ) {
			$factories[] = PaymentCardFactory::createFromFile( $resourcePath );
		}

		return $factories;
	}
}
