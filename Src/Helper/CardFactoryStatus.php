<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use LogicException;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;

final readonly class CardFactoryStatus {
	/**
	 * @param CardFactory<CardType>  $factory
	 * @param ?CardCreated<CardType> $event
	 */
	public function __construct(
		public CardFactory $factory,
		public int $factoryNumber,
		private ?Status $status = null,
		private ?CardCreated $event = null
	) {}

	public function notStarted(): bool {
		return null === $this->status;
	}

	public function isSuccess(): bool {
		return Status::Success === $this->status;
	}

	public function isCreating(): bool {
		return Status::Omitted === $this->status && null !== $this->event;
	}

	/**
	 * @return CardCreated<CardType>
	 * @throws LogicException When this method is invoked when factory is not creating cards.
	 */
	public function event(): CardCreated {
		return $this->event ?? throw new LogicException( 'Event cannot be used when factory is not creating cards.' );
	}
}
