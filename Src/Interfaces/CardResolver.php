<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Interfaces;

use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;

interface CardResolver {
	/**
	 * Resolves card type instance that matches the registered card number.
	 *
	 * @param bool $exitOnResolve Whether to stop validating card number and matching against subsequent card types.
	 * @return ($exitOnResolve is true ? CardType|null : null|non-empty-array<int,non-empty-list<CardType>>)
	 */
	public function resolve( string $cardNumber, bool $exitOnResolve, ?ResolverAction $handler ): CardType|array|null;

	/**
	 * Gets all card status that are covered when resolving card type.
	 *
	 * @return Status[]
	 */
	public function getCoveredCardStatus(): array;
}
