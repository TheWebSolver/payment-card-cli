<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;

class CardFactoryStatusTest extends TestCase {
	#[Test]
	public function itVerifiesPropertiesSet(): void {
		$action = new CardFactoryStatus( $this->createStub( CardFactory::class ), factoryNumber: 1 );

		$this->assertInstanceOf( Stub::class, $action->factory );
		$this->assertSame( 1, $action->factoryNumber );
		$this->assertFalse( $action->started() );
		$this->assertFalse( $action->isCreating() );
		$this->assertFalse( $action->finished() );
		$this->assertFalse( $action->isSuccess() );
	}

	#[Test]
	public function itEnsuresActionIsSuccessfulBasedOnStatus(): void {
		$factory = $this->createMock( CardFactory::class );
		$event   = new CardCreated( $this->createMock( CardType::class ), 'last', 'value', true );

		$factory->expects( $invokeCount = $this->exactly( 2 ) )
			->method( 'getPayload' )
			->willReturnCallback( fn () => [ 1 === $invokeCount->numberOfInvocations() ? 'last' : 'first' => 'card' ] );

		foreach ( Status::cases() as $status ) {
			// @phpstan-ignore-next-line -- Mocked card type for event.
			$action = new CardFactoryStatus( $factory, 0, $status, $event );

			$this->assertTrue( $action->started() );
			$this->assertSame( Status::Omitted === $status ? true : false, $action->isCreating() );
			$this->assertSame( Status::Success === $status ? true : false, $action->isSuccess() );
			$this->assertSame( Status::Omitted === $status ? true : false, $action->finished() ); // Invoked "getPayload" #1.
		}

		$nonCreateState = new CardFactoryStatus( $factory, 0, Status::Omitted, null );

		$this->assertFalse( $nonCreateState->finished() );

		// @phpstan-ignore-next-line -- Mocked card type for event.
		$createState = new CardFactoryStatus( $factory, 0, Status::Omitted, $event );

		$this->assertFalse( $createState->finished() ); // Invoked "getPayload" #2.
	}

	#[Test]
	#[DataProvider( 'provideThrowableMethodNames' )]
	public function itThrowsExceptionOnBadMethodInvocation( string $methodName, string $expectedMsg ): void {
		$action = new CardFactoryStatus( $this->createStub( CardFactory::class ), factoryNumber: 0 );

		$this->expectExceptionMessage( $expectedMsg );
		$action->{$methodName}();
	}

	/** @return string[][] */
	public static function provideThrowableMethodNames(): array {
		return [
			[ 'event', CardFactoryStatus::EVENT_ERROR ],
			[ 'currentCardName', CardFactoryStatus::EVENT_ERROR ],
			[ 'resourceInfo', sprintf( CardFactoryStatus::RESOURCE_ERROR, 0 ) ],
		];
	}
}
