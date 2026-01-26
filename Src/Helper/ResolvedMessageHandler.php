<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardResolved;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvesCard;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvedAction;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;
use Symfony\Component\Console\Output\ConsoleSectionOutput as Output;

class ResolvedMessageHandler implements ResolvedAction {
	protected bool $shouldWrite = true;
	private ResolvesCard $cardResolver;

	/*
	|------------------------------------------------------------------------------------------------
	| Artifacts during handling process. Must be cleared after it is complete
	|------------------------------------------------------------------------------------------------
	*/

	/** Parameter provided to handle method. */
	private CardResolved $event;

	/** @param OutputInterface::VERBOSITY* $verbosity */
	public function __construct( protected InputInterface $input, protected OutputInterface $output, protected int $verbosity ) {}

	public function resolvedWith( ResolvesCard $resolver ): self {
		$this->cardResolver = $resolver;

		return $this;
	}

	public function withoutPrint( bool $doNotWriteToConsole = true ): self {
		$this->shouldWrite = ! $doNotWriteToConsole;

		return $this;
	}

	public function handle( CardResolved $event ): ?Output {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return null;
		}

		$this->event = $event;

		if ( $event->isCreating() ) {
			$this->handleCardResolvedInfo( $section );
		} else {
			$this->handleFactoryResolvedInfo( $section );
		}

		$this->shouldWrite && $section->writeln( $section->getContent() );

		unset( $this->event );

		return $section;
	}

	private function handleCardResolvedInfo( Output $section ): void {
		$status = $this->cardResolver->getCoveredCardStatus()[ $this->event->current()->payloadIndex ];
		$symbol = match ( $status ) {
			Status::Success => Symbol::Green,
			Status::Failure => Symbol::Red,
			Status::Omitted => Symbol::NotAllowed,
		};

		$section->addContent( "{$symbol->value} {$this->event->cardResolvedInfo( $status )}" );

		$this->factoryStoppedCreatingCards( $status ) || $section->addContent( CardResolved::CHECK_NEXT_INFO );
	}

	private function handleFactoryResolvedInfo( Output $section ): int {
		$section->addContent( $this->event->factoryStatusInfo() );

		if ( ! $this->event->started() ) {
			return $section->addContent( "<info>{$this->event->resourceInfo()}</>" );
		}

		$symbol = ( $this->event->isSuccess() ? Symbol::Tick : Symbol::Cross )->value;
		$info   = $this->event->factoryResolvedInfo();

		return $section->addContent( $this->colorize( $this->event->isSuccess() ? 'green' : 'red', "{$symbol} {$info}" ) );
	}

	private function factoryStoppedCreatingCards( Status $status ): bool {
		return $this->event->finished()
			|| ( Status::Success === $status && ResolvePaymentCard::shouldExitOnResolve( $this->input ) );
	}

	public static function colorize( string $bg, string $info, string $fg = 'black' ): string {
		return "<bg={$bg};fg={$fg}>{$info}</>";
	}
}
