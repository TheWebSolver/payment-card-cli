<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Cli\Helper;

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
	private bool $shouldWrite = true;

	private string $cardNumber;
	private PaymentCardResolver $cardResolver;

	public function __construct(
		private readonly InputInterface $input,
		private readonly OutputInterface $output,
		private readonly int $verbosity
	) {}

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
			$this->handleResolvedContext( $context, $section, $factoryNumber );
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

		$section->addContent( sprintf( '%1$s %2$s card number as "%3$s" card', $symbol->value, $response, $name ) );

		$shouldCheckNextPayloadFromSameFactory && $section->addContent( 'Checking against next card...' );
	}

	protected function handleResolvedContext( string $context, ConsoleSectionOutput $section, int $factoryNumber ): void {
		match ( $context ) {
			default    => null,
			'started'  => $section->addContent( sprintf( 'Started resolving card number "%1$s" against payload from Factory #%2$d', $this->cardNumber, $factoryNumber ) ),
			'success'  => $section->addContent( sprintf( '%1$s Resolved Payment Card against payload from Factory #%2$d.', Symbol::Tick->value, $factoryNumber ) ),
			'exit'     => $section->addContent( 'Exiting...' ),
			'finished' => $section->addContent( sprintf( 'Finished resolving card number "%1$s" against payload from Factory #%2$d', $this->cardNumber, $factoryNumber ) ),
			'failure'  => $section->addContent( sprintf( '%1$s Could not resolve Payment Card against payload from Factory #%2$d', Symbol::Cross->value, $factoryNumber ) ),
		};
	}

	/** @return array{non-empty-string,Symbol} */
	protected function getResponse( Status $status ): array {
		return match ( $status ) {
			Status::Success => [ 'Resolved', Symbol::Green ],
			Status::Failure => [ 'Could not resolve', Symbol::Red ],
			Status::Omitted => [ 'Skipped', Symbol::NotAllowed ],
		};
	}

	private function shouldExitOnResolve( Status $status ): bool {
		return Status::Success === $status && ResolvePaymentCard::shouldExitOnResolve( $this->input );
	}
}
