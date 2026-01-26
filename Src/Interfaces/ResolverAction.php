<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Interfaces;

use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus;

interface ResolverAction {
	/**
	 * Sets card resolver that resolves card number.
	 */
	public function resolvedWith( CardResolver $resolver ): self;

	/**
	 * Handles resolved cord type.
	 */
	public function handle( CardFactoryStatus $event ): mixed;
}
