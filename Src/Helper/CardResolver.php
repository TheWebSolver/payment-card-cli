<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use Closure;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
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
	/** @var array{Closure,string,CardFactory<CardType>,int} Action, card number to resolve, current factory, & its index. */
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

	/**
	 * @param Closure(CardFactoryStatus):mixed $action
	 * @return ($exitOnResolve is true ? CardType|null : null|non-empty-array<int,non-empty-list<CardType>>)
	 */
	public function resolveCard( string $cardNumber, bool $exitOnResolve, Closure $action ): CardType|array|null {
		$this->cliResolveArguments = [ $action, $cardNumber ];
		$resolved                  = [];

		foreach ( $this->factories as $index => $factory ) {
			$this->cliResolveArguments[2] = $factory;
			$this->cliResolveArguments[3] = $factoryNumber = $index + 1;

			$action( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber ) );

			if ( is_null( $resolvedCards = $this->resolve( $cardNumber, $factory, $exitOnResolve ) ) ) {
				$action( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber, Status::Failure ) );

				continue;
			}

			$action( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber, Status::Success ) );

			if ( $exitOnResolve ) {
				return $resolvedCards instanceof CardType ? $resolvedCards : end( $resolvedCards );
			}

			$resolved[ $index ] = $resolvedCards;
		}

		return $resolved ? $resolved : null;
	}

	/** @param CardCreated<CardType> $event */
	protected function handleResolvedCard( CardCreated $event ): bool {
		[$action, $cardNumber, $factory, $factoryNumber] = $this->cliResolveArguments;
		$eventHandlerStatus                              = $this->handlePaymentCardCreated( $event );

		$action( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber, Status::Omitted, $event ) );

		return $eventHandlerStatus;
	}
}
