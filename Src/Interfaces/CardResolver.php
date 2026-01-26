<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Interfaces;

use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;

interface CardResolver {
	/**
	 * Sets card number that resolves the card type.
	 *
	 * This must be implemented as an immutable method such that resolver state does not change on subsequent invocation.
	 */
	public function for( string $cardNumber ): self;

	/**
	 * Sets card factories to resolve the card type.
	 *
	 * This must be implemented as an immutable method such that resolver state does not change on subsequent invocation.
	 *
	 * @param CardFactory<TCardType> $factory
	 * @param CardFactory<TCardType> ...$factories
	 * @no-named-arguments
	 * @template TCardType of CardType
	 */
	public function using( CardFactory $factory, CardFactory ...$factories ): self;

	/**
	 * Sets handler that handles card type during resolving process.
	 *
	 * This must be implemented as an immutable method such that resolver state does not change on subsequent invocation.
	 */
	public function handleWith( ResolverAction $handler ): self;

	/**
	 * Resolves card type instance validated for provided card number.
	 *
	 * @param bool $exitOnResolve Whether resolving should stop once card number is valid and card type is resolved.
	 * @return ($exitOnResolve is true ? CardType|null : null|non-empty-array<int,non-empty-list<CardType>>)
	 */
	public function resolve( bool $exitOnResolve ): CardType|array|null;

	/**
	 * Gets all card status that are covered when resolving card type.
	 *
	 * @return Status[]
	 */
	public function getCoveredCardStatus(): array;
}
