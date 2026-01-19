<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use Closure;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Traits\CardResolver as Resolver;

class CardResolver {
	use Resolver {
		Resolver::handleResolvedCard as protected handlePaymentCardCreated;
	}

	/** @var non-empty-list<CardFactory<CardType>> */
	private array $factories;
	/** @var array{Closure,CardFactory<CardType>,int} Action, current factory & its index */
	private array $cliResolveArguments;

	/**
	 * @param TFactory $factory
	 * @param TFactory ...$factories
	 * @no-named-arguments
	 * @template TFactory of CardFactory
	 */
	public function __construct( CardFactory $factory, CardFactory ...$factories ) {
		$this->factories = [ $factory, ...$factories ];
	}

	/** @return ($exitOnResolve is true ? CardType|null : null|non-empty-array<int,non-empty-list<CardType>>) */
	public function resolveCard( string $cardNumber, bool $exitOnResolve, Closure $action ): CardType|array|null {
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

				return $resolvedCards instanceof CardType ? $resolvedCards : end( $resolvedCards );
			}

			$resolved[ $index ] = $resolvedCards;
		}//end foreach

		$action( 'exit', $factoryNumber, $factory );

		return $resolved ? $resolved : null;
	}

	/** @param CardCreated<CardType> $event */
	protected function handleResolvedCard( CardCreated $event ): bool {
		[$action, $factory, $factoryNumber] = $this->cliResolveArguments;
		$eventHandlerStatus                 = $this->handlePaymentCardCreated( $event );

		$action( 'created', $factoryNumber, $factory, $event );

		return $eventHandlerStatus;
	}
}
