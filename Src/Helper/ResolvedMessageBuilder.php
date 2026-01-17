<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use ReflectionClass;
use RuntimeException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;
use Symfony\Component\Console\Output\ConsoleSectionOutput as Output;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory as Factory;
use TheWebSolver\Codegarage\PaymentCard\Event\PaymentCardCreated as Event;

class ResolvedMessageBuilder {
	final public const STATE  = [ 'Started', 'Finished' ];
	final public const STATUS = [ 'Resolved', 'Could not resolve', 'Skipped resolving' ];
	/** @placeholder: `%s`: Realpath of Payload resource */
	final public const RESOURCE_PATH_MESSAGE = 'Payload resource path: %s';
	/** @placeholder: `1:` Factory state, `2:` Current factory number */
	final public const FACTORY_MESSAGE = '%1$s resolving card number "%2$s" against payload from Factory #%3$d';
	/** @placeholder `1:` Symbol, `2:` Resolve status, `3:` Current factory number */
	final public const STATUS_MESSAGE = '%1$s %2$s Payment Card against payload from Factory #%3$d.';
	/** @placeholder `1:` Symbol, `2:` Resolve status, `3:` Payment Card name */
	final public const CREATED_MESSAGE = '%1$s %2$s card number as "%3$s" card';
	/** @placeholder `%s`: Current factory number */
	final public const INVALID_PAYLOAD = 'Could not resolve Payment Card name against payload from factory #%s.';

	protected bool $shouldWrite = true;

	protected string $cardNumber;
	protected PaymentCardResolver $cardResolver;

	/*
	|------------------------------------------------------------------------------------------------
	| Artifacts during build process. Must be cleared after build is complete
	|------------------------------------------------------------------------------------------------
	*/

	/** @var array{Factory,int} */
	private array $currentFactory;

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

	public function build( string $context, int $factoryNumber, Factory $factory, ?Event $event = null ): ?Output {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return null;
		}

		$this->currentFactory = [ $factory, $factoryNumber ];

		if ( $event ) {
			$this->handleCreatedCardFromFactory( $event, $section );
		} else {
			$this->handleResolvedContext( $context, $section );
		}

		$this->shouldWrite && $section->writeln( $section->getContent() );

		unset( $this->currentFactory );

		return $section;
	}

	protected function handleCreatedCardFromFactory( Event $event, Output $section ): void {
		$lastPayloadIndex = array_key_last( $this->currentFactory[0]->getPayload() );
		$payloadIndex     = $event->payloadIndex;
		$status           = $this->cardResolver->getCoveredCardStatus()[ $payloadIndex ];
		$name             = ! $event->isCreatableCard ? $this->fromPayload( $event->payloadIndex ) : $event->card?->getName() ?? '';

		[$response, $symbol]                   = $this->getResponse( $status );
		$shouldCheckNextPayloadFromSameFactory = $lastPayloadIndex !== $payloadIndex && ! $this->shouldExitOnResolve( $status );

		$section->addContent( sprintf( self::CREATED_MESSAGE, $symbol->value, $response, $name ) );

		$shouldCheckNextPayloadFromSameFactory && $section->addContent( 'Checking against next card...' );
	}

	protected function handleResolvedContext( string $context, Output $section ): void {
		[$factory, $number] = $this->currentFactory;

		match ( $context ) {
			default    => null,
			'started'  => $section->addContent( $this->getFactoryStartedMessage( $factory, $number ) ),
			'success'  => $section->addContent( '<bg=green>' . sprintf( self::STATUS_MESSAGE, Symbol::Tick->value, self::STATUS[0], $number ) . '</>' ),
			'exit'     => $section->addContent( 'Exiting...' ),
			'finished' => $section->addContent( sprintf( self::FACTORY_MESSAGE, self::STATE[1], $this->cardNumber, $number ) ),
			'failure'  => $section->addContent( '<bg=red>' . sprintf( self::STATUS_MESSAGE, Symbol::Cross->value, self::STATUS[1], $number ) . '</>' ),
		};
	}

	protected function getFactoryStartedMessage( Factory $factory, int $factoryNumber ): string {
		$resourcePath = ( new ReflectionClass( $factory ) )->getProperty( 'filePath' )->getValue( $factory );

		assert( is_string( $resourcePath ) );

		return sprintf( self::FACTORY_MESSAGE, self::STATE[0], $this->cardNumber, $factoryNumber )
		. PHP_EOL
		. '<info>' . sprintf( self::RESOURCE_PATH_MESSAGE, realpath( $resourcePath ) ) . '</>';
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

	private function fromPayload( string|int $index ): string {
		[$factory, $number] = $this->currentFactory;
		$data               = $factory->getPayload()[ $index ];

		return is_array( $data ) && is_string( $data['name'] ?? null )
			? $data['name']
			: throw new RuntimeException( sprintf( self::INVALID_PAYLOAD, $number ) );
	}
}
