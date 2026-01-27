<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\InputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use TheWebSolver\Codegarage\PaymentCard\Event\CardCreated;
use TheWebSolver\Codegarage\PaymentCard\Event\CardResolved;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use Symfony\Component\Console\Output\OutputInterface as Output;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvesCard;
use TheWebSolver\Codegarage\PaymentCard\Helper\ResolvedMessageHandler;

class ResolvedMessageHandlerTest extends TestCase {
	final public const PAYLOAD_DATA = [
		[ 'name' => 'One' ],
		[ 'name' => 'Two' ],
		[ 'name' => 'Three' ],
		[ 'name' => 'Four' ],
		[ 'name' => 'Five' ],
	];

	final public const COVERED_CARDS_STATUS = [
		Status::Success,
		Status::Failure,
		Status::Omitted,
		Status::Failure,
		Status::Success,
	];

	final public const RESOLVED_STATUS = [
		[ 'Resolved', Symbol::Green->value ],
		[ 'Could not resolve', Symbol::Red->value ],
		[ 'Skipped resolving', Symbol::NotAllowed->value ],
		[ 'Could not resolve', Symbol::Red->value ],
		[ 'Resolved', Symbol::Green->value ],
	];

	/** @var MockObject&CardFactory<CardType> */
	private CardFactory&MockObject $factory;

	protected function setUp(): void {
		$factory = $this->createMock( CardFactory::class );

		// Suppress exception thrown due to invalid resource path.
		$factory->expects( $this->any() )->method( 'getResourcePath' )->willReturn( __DIR__ );

		$this->factory = $factory;
	}

	protected function tearDown(): void {
		unset( $this->factory );
	}

	#[Test]
	public function itDoesNotHandleMessagesWithDesiredVerbosity(): void {
		$output = $this->createMock( ConsoleOutputInterface::class );

		$output->expects( $count = $this->exactly( 2 ) )->method( 'getVerbosity' )->willReturnCallback(
			static fn() => 1 === $count->numberOfInvocations() ? Output::VERBOSITY_SILENT : Output::VERBOSITY_DEBUG
		);

		// Only invokes section method when output verbosity is greater or equal than provided to handler.
		$output->expects( $this->once() )->method( 'section' );

		$input   = $this->createStub( InputInterface::class );
		$handler = ( new ResolvedMessageHandler() )->usingIO( $input, $output, Output::VERBOSITY_NORMAL );
		$event   = new CardResolved( $this->factory, 0, '1' );

		$handler->handle( $event );
		$handler->handle( $event );
	}

	#[Test]
	public function itHandlessMessageWhenFactoryIsCreatingCardInstance(): void {
		$output   = $this->createMock( ConsoleOutputInterface::class );
		$section  = $this->createMock( ConsoleSectionOutput::class );
		$card     = $this->createMock( CardType::class );
		$resolver = $this->createMock( ResolvesCard::class );
		$input    = $this->createMock( InputInterface::class );

		$this->factory->expects( $this->exactly( 5 ) )->method( 'getPayload' )->willReturn( self::PAYLOAD_DATA );
		$output->expects( $this->exactly( 5 ) )->method( 'getVerbosity' )->willReturn( Output::VERBOSITY_DEBUG );
		$output->expects( $this->exactly( 5 ) )->method( 'section' )->willReturn( $section );
		$resolver->expects( $this->exactly( 5 ) )->method( 'getCoveredCardStatus' )->willReturn( self::COVERED_CARDS_STATUS );

		// When event is not finished and covered card's status is success.
		$input->expects( $this->once() )->method( 'getOption' )->with( 'all' );

		$card->expects( $getNameMocker = $this->exactly( 5 ) )->method( 'getName' )->willReturnCallback(
			fn () => self::PAYLOAD_DATA[ $getNameMocker->numberOfInvocations() - 1 ]['name']
		);

		$section->expects( $addContentMocker = $this->exactly( 5 ) )->method( 'addContent' )->willReturnCallback(
			function ( string $actualContent, bool $newline ) use ( $addContentMocker ) {
				$this->assertTrue( $newline );

				$payloadIndex      = $addContentMocker->numberOfInvocations() - 1;
				$cardName          = self::PAYLOAD_DATA[ $payloadIndex ]['name'];
				[$status, $symbol] = self::RESOLVED_STATUS[ $payloadIndex ];
				$expectedContent   = "$symbol " . sprintf( CardResolved::CARD_RESOLVED_INFO, $status, $cardName );

				// Next card check content not added after final card is resolved.
				( 4 !== $payloadIndex ) && ( $expectedContent .= PHP_EOL . CardResolved::CHECK_NEXT_INFO );

				$this->assertSame( $expectedContent, $actualContent );

				return 0;
			}
		);

		$handler = ( new ResolvedMessageHandler( writeToConsole: false ) )->resolvedWith( $resolver )->usingIO( $input, $output );

		foreach ( array_keys( self::PAYLOAD_DATA ) as $index ) {
			$data = self::PAYLOAD_DATA[ $index ];

			$handler->handle(
				new CardResolved( $this->factory, 0, '1', Status::Omitted, new CardCreated( $card, $index, $data, true ) )
			);
		}
	}
}
