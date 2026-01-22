<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus;

class CardFactoryStatusTest extends TestCase {
	#[Test]
	public function itVerifiesPropertiesSet(): void {
		$resolveEvent = new CardFactoryStatus(
			factory: $this->createStub( CardFactory::class ),
			factoryNumber: 1,
			cardNumber: '0',
			status: null,
			current: new CardCreated( $this->createStub( CardType::class ), 0, 0, false ) // @phpstan-ignore-line
		);

		$this->assertInstanceOf( Stub::class, $resolveEvent->factory );
		$this->assertSame( 1, $resolveEvent->factoryNumber );
		$this->assertSame( '0', $resolveEvent->cardNumber );
		$this->assertFalse( $resolveEvent->started() );
		$this->assertFalse( $resolveEvent->isCreating() );
		$this->assertFalse( $resolveEvent->finished() );
		$this->assertFalse( $resolveEvent->isSuccess() );
		$this->assertInstanceOf( CardCreated::class, $resolveEvent->current() );
	}

	#[Test]
	public function itEnsuresActionIsSuccessfulBasedOnStatus(): void {
		$factory          = $this->createMock( CardFactory::class );
		$nonCreatingEvent = new CardFactoryStatus( $factory, 0, '0', Status::Omitted, null );

		$this->assertFalse( $nonCreatingEvent->isCreating() );
		$this->assertFalse( $nonCreatingEvent->finished() );

		$current = new CardCreated( $this->createStub( CardType::class ), 2, 'Test Card', true );

		$factory->expects( $invokeMocker = $this->exactly( 2 ) )
			->method( 'getPayload' )
			->willReturnCallback( fn () => [ $invokeMocker->numberOfInvocations() => 'Test Card' ] );

		$creatingEvent = new CardFactoryStatus( $factory, 0, '123', Status::Omitted, $current );

		$this->assertTrue( $creatingEvent->isCreating() );
		$this->assertFalse( $creatingEvent->finished() ); // Invoked "getPayload" #1.

		foreach ( Status::cases() as $status ) {
			$resolveEvent = new CardFactoryStatus( $factory, 0, '456', $status, $current );

			$this->assertTrue( $resolveEvent->started() );
			$this->assertSame( Status::Omitted === $status ? true : false, $resolveEvent->isCreating() );
			$this->assertSame( Status::Success === $status ? true : false, $resolveEvent->isSuccess() );
			$this->assertSame(
				Status::Omitted === $status ? true : false,
				$resolveEvent->finished(),
				"Finished resolving when in omitted status and factory's last payload index matches created card's payload index"
			); // Invoked "getPayload" #2.
		}
	}

	#[Test]
	#[DataProvider( 'provideThrowableMethodNames' )]
	public function itThrowsExceptionOnDirectMethodInvocation( string $methodName, string $expectedMsg ): void {
		$resolveEvent = new CardFactoryStatus( $this->createStub( CardFactory::class ), 0, '0' );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( sprintf( $expectedMsg, 0 ) );
		$resolveEvent->{$methodName}();
	}

	/** @return string[][] */
	public static function provideThrowableMethodNames(): array {
		return [
			[ 'current', CardFactoryStatus::CURRENT_CARD_ERROR ],
			[ 'currentCardName', CardFactoryStatus::CURRENT_CARD_ERROR ],
			[ 'resourceInfo', CardFactoryStatus::RESOURCE_ERROR ],
		];
	}

	#[Test]
	public function itGetsCurrentCardNameEitherFromCardInstanceOrPayloadData(): void {
		$factory = $this->createStub( CardFactory::class );
		$card    = $this->createMock( CardType::class );

		$card->expects( $this->once() )->method( 'getName' )->willReturn( 'Created Card' );

		$cardCreated = new CardCreated( $card, 0, [], true );
		$createEvent = new CardFactoryStatus( $factory, 0, '0', Status::Success, $cardCreated );

		$this->assertSame( 'Created Card', $createEvent->currentCardName(), 'From $cardCreated->card->getName()' );

		$cardNotCreated = new CardCreated( $card, 0, [ 'name' => 'Payload Card' ], false );
		$nonCreateEvent = new CardFactoryStatus( $factory, 0, '0', Status::Failure, $cardNotCreated );

		$this->assertSame( 'Payload Card', $nonCreateEvent->currentCardName(), 'From $cardNotCreated->payloadValue' );
	}

	/** @param ?CardCreated<CardType> $current */
	#[Test]
	#[DataProvider( 'provideInvalidEventForCurrentCardName' )]
	public function itThrowsExceptionForCurrentCardNameWhenNoEventOrEventPropertiesMismatch(
		?CardCreated $current,
		string $expectedMsg = CardFactoryStatus::PAYLOAD_ERROR
	): void {
		$factory      = $this->createStub( CardFactory::class );
		$resolveEvent = new CardFactoryStatus( $factory, 0, '0', Status::Success, $current );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( sprintf( $expectedMsg, 0 ) );

		$resolveEvent->currentCardName();
	}

	/** @return mixed[] */
	public static function provideInvalidEventForCurrentCardName(): array {
		$card = self::createStub( CardType::class );

		return [
			[ null, CardFactoryStatus::CURRENT_CARD_ERROR ],
			[ new CardCreated( null /* Not created even though it is set as creatable */, '', [], true ) ],
			[ new CardCreated( $card, 'card-key', 'payload data must be an array', false ) ],
			[ new CardCreated( $card, 'card-key', [ 'no-"name"-key' => 'Card Name' ], false ) ],
			[ new CardCreated( $card, 'card-key', [ 'name' => 123 /* Payload's "name" key must have a string value */ ], false ) ],
		];
	}

	#[Test]
	#[DataProvider( 'provideStatusBasedStringInfo' )]
	public function itVerifiesInfoToString( string $methodName, Status $status, string $expectedString ): void {
		$this->assertSame( $expectedString, CardFactoryStatus::{$methodName}( $status ) );
	}

	/** @return array<array{string,Status,string}> */
	public static function provideStatusBasedStringInfo(): array {
		return [
			[ 'resolvedToString', Status::Success, 'Resolved' ],
			[ 'resolvedToString', Status::Failure, 'Could not resolve' ],
			[ 'resolvedToString', Status::Omitted, 'Skipped resolving' ],
			[ 'symbolToString', Status::Success, Symbol::Green->value ],
			[ 'symbolToString', Status::Failure, Symbol::Red->value ],
			[ 'symbolToString', Status::Omitted, Symbol::NotAllowed->value ],
		];
	}

	#[Test]
	#[DataProvider( 'provideResourcePathForFactory' )]
	public function itGetsInfoAboutResourcePathFromFactory( ?string $resourcePath, bool $expectedValidPath ): void {
		$factory = $this->createMock( CardFactory::class );

		$factory->expects( $this->once() )->method( 'getResourcePath' )->willReturn( $resourcePath );

		if ( ! $expectedValidPath ) {
			$this->expectException( LogicException::class );
			$this->expectExceptionMessage( sprintf( CardFactoryStatus::RESOURCE_ERROR, 1 ) );
		}

		$this->assertSame(
			sprintf( CardFactoryStatus::RESOURCE_INFO, $resourcePath ?? '' ),
			( new CardFactoryStatus( $factory, 1, '0' ) )->resourceInfo()
		);
	}

	/** @return array<array{?string,bool}> */
	public static function provideResourcePathForFactory(): array {
		return [
			[ null, false ],
			[ '', false ],
			[ 'invalid/resource/path', false ],
			[ __DIR__, true ],
		];
	}

	#[Test]
	public function itGetsInfoAboutFactoryStatus(): void {
		$factory = $this->createStub( CardFactory::class );

		$this->assertSame(
			sprintf( CardFactoryStatus::FACTORY_INFO, 'Started', '12345', 0 ),
			( new CardFactoryStatus( $factory, 0, '12345', null ) )->factoryInfo()
		);

		foreach ( Status::cases() as $status ) {
			$this->assertSame(
				sprintf( CardFactoryStatus::FACTORY_INFO, 'Finished', '6789', 1 ),
				( new CardFactoryStatus( $factory, 1, '6789', $status ) )->factoryInfo(),
				'Always returns "Finished" info when status is not null'
			);
		}
	}

	#[Test]
	public function itGetsInfoAboutResolvedStatus(): void {
		$factory = $this->createStub( CardFactory::class );

		foreach ( [ null, ...Status::cases() ] as $status ) {
			$isResolved = Status::Success === $status ? 'Resolved' : 'Could not resolve';
			$symbol     = Status::Success === $status ? Symbol::Tick : Symbol::Cross;

			$this->assertSame(
				sprintf( CardFactoryStatus::RESOLVED_INFO, $symbol->value, $isResolved, 0 ),
				( new CardFactoryStatus( $factory, 0, '1', $status ) )->resolvedInfo()
			);
		}
	}
}
