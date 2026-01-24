<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolverAction;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardResolver as Resolver;
use TheWebSolver\Codegarage\PaymentCard\Traits\CardResolver as ResolverTrait;

class CardResolver implements Resolver {
	use ResolverTrait {
		ResolverTrait::handleResolvedCard as protected handlePaymentCardCreated;
		ResolverTrait::resolve as private resolveUsing;
	}

	/** @var non-empty-list<CardFactory<CardType>> */
	private array $factories;
	/** @var array{?ResolverAction,string,CardFactory<CardType>,int} Action handler, card number, current factory, & its index. */
	private array $resolverArgs;

	/**
	 * @param TFactory $factory
	 * @param TFactory ...$factories
	 * @no-named-arguments
	 * @template TFactory of CardFactory
	 */
	public function __construct( CardFactory $factory, CardFactory ...$factories ) {
		$this->factories = [ $factory, ...$factories ];
	}

	public function resolve( string $cardNumber, bool $exitOnResolve, ?ResolverAction $handler ): CardType|array|null {
		$this->resolverArgs = [ $handler, $cardNumber ];
		$resolved           = [];

		foreach ( $this->factories as $index => $factory ) {
			[$this->resolverArgs[2], $this->resolverArgs[3]] = [ $factory, $factoryNumber = $index + 1 ];

			$handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber ) );

			if ( is_null( $resolvedCards = $this->resolveUsing( $cardNumber, $factory, $exitOnResolve ) ) ) {
				$handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber, Status::Failure ) );

				continue;
			}

			$handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber, Status::Success ) );

			if ( $exitOnResolve ) {
				return $resolvedCards instanceof CardType ? $resolvedCards : end( $resolvedCards );
			}

			$resolved[ $index ] = $resolvedCards;
		}

		return $resolved ? $resolved : null;
	}

	/** @param CardCreated<CardType> $event */
	protected function handleResolvedCard( CardCreated $event ): bool {
		[$handler, $cardNumber, $factory, $factoryNumber] = $this->resolverArgs;
		$eventHandlerStatus                               = $this->handlePaymentCardCreated( $event );

		$handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $cardNumber, Status::Omitted, $event ) );

		return $eventHandlerStatus;
	}
}
