<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Cli;

use LogicException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Data\Flag;
use TheWebSolver\Codegarage\Cli\Data\Positional;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\PaymentCard\Cli\Helper\PaymentCardResolver;
use TheWebSolver\Codegarage\PaymentCard\Cli\Helper\ResolvedMessageBuilder;

#[Command( 'resolve', 'payment-card', 'Resolves payment card against payload resource provided' )]
#[Positional( 'card-number', 'Payment Card Number to resolve against payload', isOptional: false )]
#[Flag( 'all', 'Continue further Payment Card creation after first resolve', isNegatable: false )]
#[Associative( 'payload', 'Payload to create Payment Card instances to validate given card number', isOptional: false, isVariadic: true, shortcut: 'P' )]
class ResolvePaymentCard extends Console {
	public function initialize( InputInterface $input, OutputInterface $output ): void {
		if ( ! $input->getOption( 'payload' ) ) {
			throw new LogicException( 'Atleast one payload resource path is required to validate Payment Card Number' );
		}
	}
	public function execute( InputInterface $input, OutputInterface $output ): int {
		$payloads   = $input->getOption( 'payload' );
		$cardNumber = $input->getArgument( 'card-number' );

		assert( is_array( $payloads ) );
		assert( is_string( $cardNumber ) );

		$factories      = array_map( $this->createFactoriesFromPayload( ... ), $payloads );
		$messageBuilder = $this->getMessageBuilder( $input, $output )
			->forCardNumber( $cardNumber )
			->usingCardResolver( $resolver = new PaymentCardResolver( ...array_values( $factories ) ) );

		$resolvedCards = $resolver->resolveCard( $cardNumber, static::shouldExitOnResolve( $input ), $messageBuilder->build( ... ) );

		$output->writeln( sprintf( 'Given payment card number "%1$s" is %2$s', $cardNumber, null !== $resolvedCards ? 'valid' : 'invalid' ) );

		return self::SUCCESS;
	}

	public static function shouldExitOnResolve( InputInterface $input ): bool {
		return false !== $input->getOption( 'all' ) ? false : true;
	}

	protected function getMessageBuilder( InputInterface $input, OutputInterface $output ): ResolvedMessageBuilder {
		return new ResolvedMessageBuilder( $input, $output, OutputInterface::VERBOSITY_VERY_VERBOSE );
	}

	private function createFactoriesFromPayload( mixed $resource ): PaymentCardFactory {
		return new PaymentCardFactory(
			is_string( $resource ) ? $resource : throw new LogicException( 'Payload resource is not of string type' )
		);
	}
}
