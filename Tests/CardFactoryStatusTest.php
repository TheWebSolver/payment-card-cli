<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus;

class CardFactoryStatusTest extends TestCase {
	#[Test]
	public function itVerifiesPropertiesSet(): void {
		$resolveEvent = new CardFactoryStatus( $this->createStub( CardFactory::class ), 1, '0' );

		$this->assertInstanceOf( Stub::class, $resolveEvent->factory );
		$this->assertSame( 1, $resolveEvent->factoryNumber );
		$this->assertFalse( $resolveEvent->started() );
		$this->assertFalse( $resolveEvent->isCreating() );
		$this->assertFalse( $resolveEvent->finished() );
		$this->assertFalse( $resolveEvent->isSuccess() );
	}

	#[Test]
	public function itEnsuresActionIsSuccessfulBasedOnStatus(): void {
		$factory = $this->createMock( CardFactory::class );
		$event   = new CardCreated( $this->createMock( CardType::class ), 'first', 'Test Card', true );

		$factory->expects( $invokeMocker = $this->exactly( 2 ) )
			->method( 'getPayload' )
			->willReturnCallback( fn () => [ 1 === $invokeMocker->numberOfInvocations() ? 'first' : 'last' => 'Test Card' ] );

		foreach ( Status::cases() as $status ) {
			$resolveEvent = new CardFactoryStatus( $factory, 0, '0', $status, $event );

			$this->assertTrue( $resolveEvent->started() );
			$this->assertSame( Status::Omitted === $status ? true : false, $resolveEvent->isCreating() );
			$this->assertSame( Status::Success === $status ? true : false, $resolveEvent->isSuccess() );
			$this->assertSame( Status::Omitted === $status ? true : false, $resolveEvent->finished() ); // Invoked "getPayload" #1.
		}

		$nonCreatingEvent = new CardFactoryStatus( $factory, 0, '0', Status::Omitted, null );

		$this->assertFalse( $nonCreatingEvent->isCreating() );
		$this->assertFalse( $nonCreatingEvent->finished() );

		$creatingEvent = new CardFactoryStatus( $factory, 0, '0', Status::Omitted, $event );

		$this->assertTrue( $creatingEvent->isCreating() );
		$this->assertFalse( $creatingEvent->finished() ); // Invoked "getPayload" #2.
	}

	#[Test]
	#[DataProvider( 'provideThrowableMethodNames' )]
	public function itThrowsExceptionOnDirectMethodInvocation( string $methodName, string $expectedMsg ): void {
		$resolveEvent = new CardFactoryStatus( $this->createStub( CardFactory::class ), 0, '0' );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( $expectedMsg );
		$resolveEvent->{$methodName}();
	}

	/** @return string[][] */
	public static function provideThrowableMethodNames(): array {
		return [
			[ 'event', CardFactoryStatus::EVENT_ERROR ],
			[ 'currentCardName', CardFactoryStatus::EVENT_ERROR ],
			[ 'resourceInfo', sprintf( CardFactoryStatus::RESOURCE_ERROR, 0 ) ],
		];
	}

	#[Test]
	public function itGetsCurrentCardNameEitherFromCardInstanceOrPayloadData(): void {
		$factory = $this->createStub( CardFactory::class );
		$card    = $this->createMock( CardType::class );

		$card->expects( $this->once() )->method( 'getName' )->willReturn( 'Created Card' );

		$cardCreated = new CardCreated( $card, 0, [], true );
		$createEvent = new CardFactoryStatus( $factory, 0, '0', Status::Success, $cardCreated );

		$this->assertSame( 'Created Card', $createEvent->currentCardName() ); // From $card::getName().

		$cardNotCreated = new CardCreated( $card, 0, [ 'name' => 'Payload Card' ], false );
		$nonCreateEvent = new CardFactoryStatus( $factory, 0, '0', Status::Failure, $cardNotCreated );

		$this->assertSame( 'Payload Card', $nonCreateEvent->currentCardName() ); // From $event->payloadValue.
	}

	/** @param ?CardCreated<CardType> $event */
	#[Test]
	#[DataProvider( 'provideInvalidEventForCurrentCardName' )]
	public function itThrowsExceptionForCurrentCardNameWhenNoEventOrEventPropertiesMismatch(
		?CardCreated $event,
		string $expectedMsg = CardFactoryStatus::PAYLOAD_ERROR
	): void {
		$factory      = $this->createStub( CardFactory::class );
		$resolveEvent = new CardFactoryStatus( $factory, 0, '0', Status::Success, $event );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( sprintf( $expectedMsg, 0 ) );

		$resolveEvent->currentCardName();
	}

	/** @return mixed[] */
	public static function provideInvalidEventForCurrentCardName(): array {
		$card = self::createStub( CardType::class );

		return [
			[ null, CardFactoryStatus::EVENT_ERROR ],
			[ new CardCreated( null /* Not created even though it is set as creatable */, '', [], true ) ],
			[ new CardCreated( $card, 'card-key', 'payload data must be an array', false ) ],
			[ new CardCreated( $card, 'card-key', [ 'no-"name"-key' => 'Card Name' ], false ) ],
			[ new CardCreated( $card, 'card-key', [ 'name' => 123 /* Not a string value */ ], false ) ],
		];
	}
}
