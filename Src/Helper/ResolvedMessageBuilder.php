<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use RuntimeException;
use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;
use Symfony\Component\Console\Output\ConsoleSectionOutput as Output;

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
	protected CardResolver $cardResolver;

	/*
	|------------------------------------------------------------------------------------------------
	| Artifacts during build process. Must be cleared after build is complete
	|------------------------------------------------------------------------------------------------
	*/

	/** Parameter provided to resolve action. */
	private CardFactoryStatus $resolverAction;

	public function __construct( protected InputInterface $input, protected OutputInterface $output, protected int $verbosity ) {}

	public function forCardNumber( string $number ): self {
		$this->cardNumber = $number;

		return $this;
	}

	public function usingCardResolver( CardResolver $resolver ): self {
		$this->cardResolver = $resolver;

		return $this;
	}

	public function withoutPrint( bool $doNotWriteToConsole = true ): self {
		$this->shouldWrite = ! $doNotWriteToConsole;

		return $this;
	}

	public function build( CardFactoryStatus $resolver ): ?Output {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return null;
		}

		$this->resolverAction = $resolver;

		if ( $resolver->isCreating() ) {
			$this->handleCreatedCardFromFactory( $section );
		} else {
			$this->handleResolverStatus( $section );
		}

		$this->shouldWrite && $section->writeln( $section->getContent() );

		unset( $this->resolverAction );

		return $section;
	}

	protected function handleCreatedCardFromFactory( Output $section ): void {
		$lastPayloadIndex = array_key_last( $this->resolverAction->factory->getPayload() );
		$payloadIndex     = $this->resolverAction->event()->payloadIndex;
		$status           = $this->cardResolver->getCoveredCardStatus()[ $payloadIndex ];

		[$response, $symbol]                   = $this->getResponse( $status );
		$shouldCheckNextPayloadFromSameFactory = $lastPayloadIndex !== $payloadIndex && ! $this->shouldExitOnResolve( $status );

		$section->addContent( sprintf( self::CREATED_MESSAGE, $symbol->value, $response, $this->getCreatedCardName() ) );

		$shouldCheckNextPayloadFromSameFactory && $section->addContent( 'Checking against next card...' );
	}

	protected function handleResolverStatus( Output $section ): void {
		if ( $this->resolverAction->notStarted() ) {
			$section->addContent( $this->getStartedMessage() );

			return;
		}

		$number = $this->resolverAction->factoryNumber;

		$section->addContent( sprintf( self::FACTORY_MESSAGE, self::STATE[1], $this->cardNumber, $number ) );

		[$color, $args] = $this->getResolvedMessageArgs();

		$section->addContent( "<bg={$color};fg=black>" . sprintf( self::STATUS_MESSAGE, ...[ ...$args, $number ] ) . '</>' );
	}

	/** @return array{string,array{string,string}} */
	protected function getResolvedMessageArgs(): array {
		return $this->resolverAction->isSuccess()
			? [ 'green', [ Symbol::Tick->value, self::STATUS[0] ] ]
			: [ 'red', [ Symbol::Cross->value, self::STATUS[1] ] ];
	}

	protected function fromPayload( string|int $index ): string {
		[$factory, $number] = [ $this->resolverAction->factory, $this->resolverAction->factoryNumber ];
		$data               = $factory->getPayload()[ $index ];

		return is_array( $data ) && is_string( $data['name'] ?? null )
			? $data['name']
			: throw new RuntimeException( sprintf( self::INVALID_PAYLOAD, $number ) );
	}

	protected function getCreatedCardName(): string {
		$event = $this->resolverAction->event();

		return $event->isCreatableCard ? $this->fromPayload( $event->payloadIndex ) : $event->card?->getName() ?? '';
	}

	protected function getStartedMessage(): string {
		[$factory, $number] = [ $this->resolverAction->factory, $this->resolverAction->factoryNumber ];
		$resourcePath       = $factory->getResourcePath();

		assert( is_string( $resourcePath ) );

		return sprintf( self::FACTORY_MESSAGE, self::STATE[0], $this->cardNumber, $number )
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

	protected function shouldExitOnResolve( Status $status ): bool {
		return Status::Success === $status && ResolvePaymentCard::shouldExitOnResolve( $this->input );
	}
}
