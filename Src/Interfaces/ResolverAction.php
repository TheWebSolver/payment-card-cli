<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Interfaces;

use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus;

interface ResolverAction {
	/**
	 * Gets card resolver.
	 *
	 * @return CardResolver
	 */
	public function getResolver(): CardResolver;

	/**
	 * Handles resolved cords.
	 */
	public function handle( CardFactoryStatus $event ): mixed;
}
