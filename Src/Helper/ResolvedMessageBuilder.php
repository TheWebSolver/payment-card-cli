<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Cli\Helper;

use ReflectionClass;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\PaymentCard\Cli\ResolvePaymentCard;
use TheWebSolver\Codegarage\PaymentCard\Event\PaymentCardCreated;

class ResolvedMessageBuilder {
	public const STATE  = [ 'Started', 'Finished' ];
	public const STATUS = [ 'Resolved', 'Could not resolve', 'Skipped resolving' ];
	/** @placeholder: %s: Realpath of Payload resource */
	public const RESOURCE_PATH_MESSAGE = 'Payload resource path: %s';
	/** @placeholder: 1: Factory state, 2: Current factory index */
	public const FACTORY_MESSAGE = '%1$s resolving card number "%2$s" against payload from Factory #%3$d';
	/** @placeholder 1: Symbol, 2: Resolve status, 3: Current factory index */
	public const STATUS_MESSAGE = '%1$s %2$s Payment Card against payload from Factory #%3$d.';
	/** @placeholder 1: Symbol, 2: Resolve status, 3: Payment Card name */
	public const CREATED_MESSAGE = '%1$s %2$s card number as "%3$s" card';

	private bool $shouldWrite = true;

	private string $cardNumber;
	private PaymentCardResolver $cardResolver;

	public function __construct( private InputInterface $input, private OutputInterface $output, private int $verbosity ) {}

	public function forCardNumber( string $number ): self {
		$this->cardNumber = $number;

		return $this;
	}

	public function usingCardResolver( PaymentCardResolver $resolver ): self {
		$this->cardResolver = $resolver;

		return $this;
	}

	public function withoutPrint( bool $doNotWriteToConsole = true ): self {
		$this->shouldWrite = ! $doNotWriteToConsole;

		return $this;
	}

	public function build( string $context, int $factoryNumber, PaymentCardFactory $factory, ?PaymentCardCreated $event = null ): ?ConsoleSectionOutput {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return null;
		}

		if ( $event ) {
			$this->handleCreatedCardFromFactory( $factory, $event, $section );
		} else {
			$this->handleResolvedContext( $context, $section, $factory, $factoryNumber );
		}

		$this->shouldWrite && $section->writeln( $section->getContent() );

		return $section;
	}

	protected function handleCreatedCardFromFactory( PaymentCardFactory $factory, PaymentCardCreated $event, ConsoleSectionOutput $section ): void {
		$lastPayloadIndex = array_key_last( $factory->getPayload() );
		$payloadIndex     = $event->payloadIndex;
		$status           = $this->cardResolver->getCoveredCardStatus()[ $payloadIndex ];
		$name             = ! $event->isCreatableCard ? $factory->create( $payloadIndex )->getName() : $event->card?->getName() ?? '';

		[$response, $symbol]                   = $this->getResponse( $status );
		$shouldCheckNextPayloadFromSameFactory = $lastPayloadIndex !== $payloadIndex && ! $this->shouldExitOnResolve( $status );

		$section->addContent( sprintf( self::CREATED_MESSAGE, $symbol->value, $response, $name ) );

		$shouldCheckNextPayloadFromSameFactory && $section->addContent( 'Checking against next card...' );
	}

	protected function handleResolvedContext( string $context, ConsoleSectionOutput $section, PaymentCardFactory $factory, int $factoryNumber ): void {
		match ( $context ) {
			default    => null,
			'started'  => $section->addContent( $this->getFactoryStartedMessage( $factory, $factoryNumber ) ),
			'success'  => $section->addContent( sprintf( self::STATUS_MESSAGE, Symbol::Tick->value, self::STATUS[0], $factoryNumber ) ),
			'exit'     => $section->addContent( 'Exiting...' ),
			'finished' => $section->addContent( sprintf( self::FACTORY_MESSAGE, self::STATE[1], $this->cardNumber, $factoryNumber ) ),
			'failure'  => $section->addContent( sprintf( self::STATUS_MESSAGE, Symbol::Cross->value, self::STATUS[1], $factoryNumber ) ),
		};
	}

	protected function getFactoryStartedMessage( PaymentCardFactory $factory, int $factoryNumber ): string {
		$resourcePath = ( new ReflectionClass( $factory ) )->getProperty( 'filePath' )->getValue( $factory );

		assert( is_string( $resourcePath ) );

		return sprintf( self::FACTORY_MESSAGE, self::STATE[0], $this->cardNumber, $factoryNumber )
		. PHP_EOL
		. sprintf( self::RESOURCE_PATH_MESSAGE, realpath( $resourcePath ) );
	}

	/** @return array{non-empty-string,Symbol} */
	protected function getResponse( Status $status ): array {
		return match ( $status ) {
			Status::Success => [ self::STATUS[0], Symbol::Green ],
			Status::Failure => [ self::STATUS[1], Symbol::Red ],
			Status::Omitted => [ self::STATUS[2], Symbol::NotAllowed ],
		};
	}

	private function shouldExitOnResolve( Status $status ): bool {
		return Status::Success === $status && ResolvePaymentCard::shouldExitOnResolve( $this->input );
	}
}
