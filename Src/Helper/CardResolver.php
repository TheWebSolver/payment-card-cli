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
	/** @var array{CardFactory<CardType>,int} Current factory, & its index. */
	private array $currentFactory;
	private string $cardNumber;
	private ?ResolverAction $handler = null;

	public function for( string $cardNumber ): Resolver {
		$this->cardNumber ??= $cardNumber;

		return $this;
	}

	public function using( CardFactory $factory, CardFactory ...$factories ): Resolver {
		$this->factories ??= [ $factory, ...$factories ];

		return $this;
	}

	public function handleWith( ResolverAction $handler ): Resolver {
		$this->handler ??= $handler->resolvedWith( $this );

		return $this;
	}

	public function resolve( bool $exitOnResolve ): CardType|array|null {
		$resolved = [];

		foreach ( $this->factories as $index => $factory ) {
			$this->currentFactory = [ $factory, $factoryNumber = $index + 1 ];

			$this->handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $this->cardNumber ) );

			$resolvedCards = $this->resolveUsing( $this->cardNumber, $factory, $exitOnResolve );
			$status        = ( $hasNoCards = null === $resolvedCards ) ? Status::Failure : Status::Success;

			$this->handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $this->cardNumber, $status ) );

			if ( $hasNoCards ) {
				continue;
			}

			if ( $exitOnResolve ) {
				return $resolvedCards instanceof CardType ? $resolvedCards : end( $resolvedCards );
			}

			$resolved[ $index ] = $resolvedCards;
		}

		return $resolved ? $resolved : null;
	}

	/** @param CardCreated<CardType> $event */
	protected function handleResolvedCard( CardCreated $event ): bool {
		[$factory, $factoryNumber] = $this->currentFactory;
		$eventHandlerStatus        = $this->handlePaymentCardCreated( $event );

		$this->handler?->handle( new CardFactoryStatus( $factory, $factoryNumber, $this->cardNumber, Status::Omitted, $event ) );

		return $eventHandlerStatus;
	}
}
