<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Cli\Helper;

use Closure;
use TheWebSolver\Codegarage\PaymentCard\PaymentCard;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\PaymentCard\Event\PaymentCardCreated;
use TheWebSolver\Codegarage\PaymentCard\Traits\PaymentCardResolver as Resolver;

class PaymentCardResolver {
	use Resolver {
		Resolver::handleResolvedCard as protected handlePaymentCardCreated;
	}

	/** @var non-empty-list<PaymentCardFactory> */
	private array $factories;

	/** @no-named-arguments */
	public function __construct( PaymentCardFactory $factory, PaymentCardFactory ...$factories ) {
		$this->factories = [ $factory, ...$factories ];
	}

	/** @var array{Closure,PaymentCardFactory,int} Action, current factory & its index */
	private array $cliResolveArguments;

	/** @return ($exitOnResolve is true ? ?PaymentCard : non-empty-array<int,non-empty-list<PaymentCard>>|null) */
	public function resolveCard( string $cardNumber, bool $exitOnResolve, Closure $action ): PaymentCard|array|null {
		$this->cliResolveArguments[0] = $action;
		$resolved                     = [];

		foreach ( $this->factories as $index => $factory ) {
			$this->cliResolveArguments[1] = $factory;
			$this->cliResolveArguments[2] = $factoryNumber = $index + 1;

			$action( 'started', $factoryNumber, $factory );

			if ( is_null( $resolvedCards = $this->resolve( $cardNumber, $factory, $exitOnResolve ) ) ) {
				$action( 'finished', $factoryNumber, $factory );
				$action( 'failure', $factoryNumber, $factory );

				continue;
			}

			$action( 'finished', $factoryNumber, $factory );
			$action( 'success', $factoryNumber, $factory );

			if ( $exitOnResolve ) {
				$action( 'exit', $factoryNumber, $factory );

				return $resolvedCards instanceof PaymentCard ? $resolvedCards : end( $resolvedCards );
			}

			$resolved[ $index ] = $resolvedCards;
		}//end foreach

		$action( 'exit', $factoryNumber, $factory );

		return $resolved ? $resolved : null;
	}

	protected function handleResolvedCard( PaymentCardCreated $event ): bool {
		[$action, $factory, $factoryNumber] = $this->cliResolveArguments;
		$eventHandlerStatus                 = $this->handlePaymentCardCreated( $event );

		$action( 'created', $factoryNumber, $factory, $event );

		return $eventHandlerStatus;
	}
}
