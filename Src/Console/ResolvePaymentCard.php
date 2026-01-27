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
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\ConsoleResolvedAction;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvesCard;
use TheWebSolver\Codegarage\PaymentCard\Helper\ResolvedMessageHandler;

#[Command( 'resolve', 'payment-card', 'Resolves payment card against payload resource provided' )]
#[Positional( 'card-number', 'Payment Card Number to resolve against payload', isOptional: false )]
#[Flag( 'all', 'Continue further Payment Card creation after first resolve', isNegatable: false )]
#[Associative( 'payload', 'Payload to create Payment Card instances to validate given card number', isOptional: false, isVariadic: true, shortcut: 'P' )]
class ResolvePaymentCard extends Console {
	final public const PAYLOAD_MISSING = 'Atleast one payload resource path is required to validate Payment Card Number';
	/** @placeholder: `1:` card number, `2:` status, `3:` suffix */
	final public const RESOLVED_MESSAGE = 'Given payment card number "%1$s" is %2$s%3$s';

	public function __construct(
		private readonly ResolvesCard $resolver = new CardResolver(),
		private ConsoleResolvedAction $handler = new ResolvedMessageHandler()
	) {
		parent::__construct();
	}

	public function initialize( InputInterface $input, OutputInterface $output ): void {
		$input->getOption( 'payload' ) || throw new LogicException( self::PAYLOAD_MISSING );
	}

	public function execute( InputInterface $input, OutputInterface $output ): int {
		$cardNumber = $input->getArgument( 'card-number' );

		assert( is_string( $cardNumber ) || is_int( $cardNumber ) );

		$resolvedCards = $this->resolver->for( $cardNumber )
			->using( ...$this->createFactoriesFromPayload( $input ) )
			->handleWith( $this->handler->usingIO( $input, $output ) )
			->resolve( static::shouldExitOnResolve( $input ) );

		if ( ! $resolvedCards ) {
			$output->writeln( sprintf( self::RESOLVED_MESSAGE, $cardNumber, 'invalid', '' ) );

			return self::FAILURE;
		}

		$cardName = $resolvedCards instanceof CardType ? $resolvedCards->getName() : $this->getCardNameList( $resolvedCards );

		$output->writeln( sprintf( self::RESOLVED_MESSAGE, $cardNumber, 'valid', " as $cardName" ) );

		return self::SUCCESS;
	}

	public static function shouldExitOnResolve( InputInterface $input ): bool {
		return false !== $input->getOption( 'all' ) ? false : true;
	}

	/** @return non-empty-list<PaymentCardFactory> */
	private function createFactoriesFromPayload( InputInterface $input ): array {
		/** @var non-empty-list<non-empty-string> In CLI context. Could be an array when testing. */
		$payloads  = $input->getOption( 'payload' );
		$factories = [];

		foreach ( $payloads as $payload ) {
			$factories[] = new PaymentCardFactory( $payload );
		}

		return $factories;
	}

	/** @param array<int,CardType[]> $cards Resolved card types indexed by respective factory iteration count. */
	private function getCardNameList( array $cards ): string {
		$cardNames = [];

		foreach ( $cards as $cardTypes ) {
			foreach ( $cardTypes as $card ) {
				$cardNames[] = $card->getName();
			}
		}

		return implode( ', ', $cardNames );
	}
}
