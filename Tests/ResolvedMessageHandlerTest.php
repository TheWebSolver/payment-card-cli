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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardType;
use Symfony\Component\Console\Output\OutputInterface as Output;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardFactory;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardResolver;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus;
use TheWebSolver\Codegarage\PaymentCard\Helper\ResolvedMessageHandler;

class ResolvedMessageHandlerTest extends TestCase {
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

	/** @return array{ConsoleOutputInterface&MockObject,ConsoleSectionOutput&MockObject} */
	private function getConsoleOutput(): array {
		$output = $this->createMock( ConsoleOutputInterface::class );

		$output->method( 'getVerbosity' )->willReturn( Output::VERBOSITY_DEBUG );

		$output->method( 'section' )->willReturn( $section = $this->createMock( ConsoleSectionOutput::class ) );

		return [ $output, $section ];
	}

	#[Test]
	public function itDoesNotHandleMessagesWithDesiredVerbosity(): void {
		$output = $this->createMock( ConsoleOutputInterface::class );
		$event  = new CardFactoryStatus( $this->factory, 0, '1' );

		$output->expects( $invokeCount = $this->exactly( 2 ) )
			->method( 'getVerbosity' )
			->willReturnCallback(
				fn() => 1 === $invokeCount->numberOfInvocations() ? Output::VERBOSITY_SILENT : Output::VERBOSITY_DEBUG
			);

		$handler = new ResolvedMessageHandler( $this->createStub( InputInterface::class ), $output, Output::VERBOSITY_NORMAL );

		$this->assertNull( $handler->handle( $event ) );

		$handler = new ResolvedMessageHandler( $this->createStub( InputInterface::class ), $output, Output::VERBOSITY_VERY_VERBOSE );

		$this->assertInstanceOf( ConsoleSectionOutput::class, $handler->handle( $event ) );
	}

	#[Test]
	public function itHandlessMessageWhenFactoryIsCreatingCardInstance(): void {
		[$output, $section] = $this->getConsoleOutput();
		$card               = $this->createMock( CardType::class );
		$resolver           = $this->createMock( CardResolver::class );
		$input              = $this->createMock( InputInterface::class );

		$payloadData = [
			1  => [ 'name' => 'One' ],
			3  => [ 'name' => 'Two' ],
			5  => [ 'name' => 'Three' ],
			7  => [ 'name' => 'Four' ],
			9  => [ 'name' => 'Five' ],
		];

		$payloadStatus = [
			1  => Status::Success,
			3  => Status::Failure,
			5  => Status::Omitted,
			7  => Status::Success,
			9  => Status::Failure,
		];

		$resolvedStatus = [
			1 => [ 'Resolved', Symbol::Green->value ],
			7 => [ 'Resolved', Symbol::Green->value ],
			3 => [ 'Could not resolve', Symbol::Red->value ],
			9 => [ 'Could not resolve', Symbol::Red->value ],
			5 => [ 'Skipped resolving', Symbol::NotAllowed->value ],
		];

		$this->factory->method( 'getPayload' )->willReturn( $payloadData );

		$input
			// ->expects( $this->exactly( 1 ) )
			->method( 'getOption' )
			->with( 'all' )
			->willReturn( true );

		$resolver
			->expects( $this->exactly( 5 ) )
			->method( 'getCoveredCardStatus' )
			->willReturn( $payloadStatus );

		$card->expects( $getNameMocker = $this->exactly( 5 ) )
			->method( 'getName' )
			->willReturnCallback(
				function () use ( $getNameMocker ) {
					return match ( $getNameMocker->numberOfInvocations() ) {
						default => '',
						1 => 'One',
						2 => 'Two',
						3 => 'Three',
						4 => 'Four',
						5 => 'Five',
					};
				}
			);

		// Next card check content not added after final card in "9" index is created.
		$section->expects( $addContentMocker = $this->exactly( 9 ) )
			->method( 'addContent' )
			->willReturnCallback(
				function ( string $msg, bool $newline ) use ( $addContentMocker, $payloadData, $resolvedStatus ) {
					$this->assertTrue( $newline );

					$invokedCount = $addContentMocker->numberOfInvocations();
					$currentCard  = $payloadData[ $invokedCount ]['name'] ?? false;

					if ( ! $currentCard ) {
						$this->assertSame( CardFactoryStatus::CHECK_NEXT_INFO, $msg );
					} else {
						[$state, $symbol] = $resolvedStatus[ $invokedCount ];

						$this->assertSame( sprintf( CardFactoryStatus::CARD_RESOLVED_INFO, $symbol, $state, $currentCard ), $msg );
					}

					return 0;
				}
			);

		$handler = ( new ResolvedMessageHandler( $input, $output, Output::VERBOSITY_NORMAL ) )
			->resolvedWith( $resolver )
			->withoutPrint( true );

		foreach ( [ 1, 3, 5, 7, 9 ] as $index ) {
			$value = $payloadData[ $index ];

			$handler->handle(
				new CardFactoryStatus( $this->factory, 0, '1', Status::Omitted, new CardCreated( $card, $index, $value, true ) )
			);
		}
	}
}
