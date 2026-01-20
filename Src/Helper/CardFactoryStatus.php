<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use LogicException;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;

final readonly class CardFactoryStatus {
	public const STARTED  = 'started';
	public const FINISHED = 'finished';

	public const EVENT_ERROR     = "Card resolver action's event cannot be used when factory is not creating cards";
	public const CHECK_NEXT_INFO = 'Checking against next card...';

	/** @placeholder `%s`: Current factory number */
	public const RESOURCE_ERROR = 'Could not resolve payload resource path from factory #%s';
	/** @placeholder `%s`: Current factory number */
	public const PAYLOAD_ERROR = 'Could not resolve Card name against payload from factory #%s';
	/** @placeholder: `%s`: Realpath of Payload resource */
	public const RESOURCE_INFO = 'Payload resource path: %s';
	/** @placeholder: `1:` Factory started or finished, `2:` Current factory number */
	public const FACTORY_INFO = '%1$s resolving card number "%2$s" against payload from Factory #%3$d';
	/** @placeholder `1:` Symbol, `2:` Card resolved or not, `3:` Current factory number */
	public const RESOLVED_INFO = '%1$s %2$s Card against payload from Factory #%3$d';
	/** @placeholder `1:` Symbol, `2:` Card instance created or not, `3:` Card name */
	public const CREATED_INFO = '%1$s %2$s card number as "%3$s" card';

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

	public function started(): bool {
		return null !== $this->status;
	}

	/** @phpstan-assert-if-true =CardCreated<CardType> $this->event */
	public function isCreating(): bool {
		return Status::Omitted === $this->status && null !== $this->event;
	}

	public function finished(): bool {
		return $this->isCreating() && array_key_last( $this->factory->getPayload() ) === $this->event->payloadIndex;
	}

	public function isSuccess(): bool {
		return Status::Success === $this->status;
	}

	/**
	 * @return CardCreated<CardType>
	 * @throws LogicException When this method is invoked when factory is not creating cards.
	 * @see self::isCreating() Returns true when event is registered. Always check.
	 */
	public function event(): CardCreated {
		return $this->event ?? throw new LogicException( self::EVENT_ERROR );
	}

	/** @throws LogicException When cannot retrieve Card name from either created Card instance or payload data. */
	public function currentCardName(): string {
		if ( $this->event()->isCreatableCard ) {
			// Card is never null when it is a creatable card. Safeguard just in case...
			return $this->event()->card?->getName() ?? $this->throwPayloadError();
		}

		$data = $this->factory->getPayload()[ $this->event()->payloadIndex ] ?? null;

		// The "name" key/value pair always exists if payload data follows Card Schema. Safeguard just in case...
		return is_array( $data ) && is_string( $data['name'] ?? null ) ? $data['name'] : $this->throwPayloadError();
	}

	public function resolvedToString( Status $status ): string {
		return match ( $status ) {
			Status::Success => 'Resolved',
			Status::Failure => 'Could not resolve',
			Status::Omitted => 'Skipped resolving',
		};
	}

	public function symbolToString( Status $status ): string {
		return match ( $status ) {
			Status::Success => Symbol::Green->value,
			Status::Failure => Symbol::Red->value,
			Status::Omitted => Symbol::NotAllowed->value,
		};
	}

	/** @throws LogicException When cannot retrieve resource path from factory. */
	public function resourceInfo(): string {
		return sprintf(
			self::RESOURCE_INFO,
			realpath( $this->factory->getResourcePath() ?? $this->throwResourceError() ) ?: $this->throwResourceError()
		);
	}

	public function createdInfo( Status $status ): string {
		return sprintf( self::CREATED_INFO, $this->symbolToString( $status ), $this->resolvedToString( $status ), $this->currentCardName() );
	}

	public function resolvedInfo( string $cardNumber ): string {
		$args = $this->isSuccess()
			? [ Symbol::Tick->value, $this->resolvedToString( Status::Success ), $cardNumber ]
			: [ Symbol::Cross->value, $this->resolvedToString( Status::Failure ), $cardNumber ];

		return sprintf( self::RESOLVED_INFO, ...$args );
	}

	private function throwPayloadError(): never {
		throw new LogicException( sprintf( self::PAYLOAD_ERROR, $this->factoryNumber ) );
	}

	private function throwResourceError(): never {
		throw new LogicException( sprintf( self::RESOURCE_ERROR, $this->factoryNumber ) );
	}
}
