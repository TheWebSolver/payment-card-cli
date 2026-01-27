<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use TheWebSolver\Codegarage\Cli\Console;
use TheWebSolver\Codegarage\Cli\Enums\Symbol;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Event\CardResolved;
use TheWebSolver\Codegarage\PaymentCard\ConsoleResolvedAction;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvesCard;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;
use Symfony\Component\Console\Output\ConsoleSectionOutput as Output;

class ResolvedMessageHandler implements ConsoleResolvedAction {
	private ResolvesCard $cardResolver;
	private InputInterface $input;
	private OutputInterface $output;
	/** @var OutputInterface::VERBOSITY* */
	private int $verbosity;

	/*
	|------------------------------------------------------------------------------------------------
	| Artifacts during handling process. Must be cleared after it is complete
	|------------------------------------------------------------------------------------------------
	*/

	/** Parameter provided to handle method. */
	private CardResolved $event;

	/** @param bool $writeToConsole Whether to print output section messages to the console or not. */
	public function __construct( private readonly bool $writeToConsole = true ) {}
	public function resolvedWith( ResolvesCard $resolver ): self {
		$this->cardResolver = $resolver;

		return $this;
	}

	public function usingIO( InputInterface $input, OutputInterface $output, int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE ): self {
		$this->input     = $input;
		$this->output    = $output;
		$this->verbosity = $verbosity;

		return $this;
	}

	public function handle( CardResolved $event ): void {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return;
		}

		$this->event = $event;

		if ( $event->isCreating() ) {
			$this->handleCardResolvedInfo( $section );
		} else {
			$this->handleFactoryResolvedInfo( $section );
		}

		$this->writeToConsole && $section->writeln( $section->getContent() );

		unset( $this->event );
	}

	private function handleCardResolvedInfo( Output $section ): void {
		$status = $this->cardResolver->getCoveredCardStatus()[ $this->event->current()->payloadIndex ];
		$symbol = match ( $status ) {
			Status::Success => Symbol::Green,
			Status::Failure => Symbol::Red,
			Status::Omitted => Symbol::NotAllowed,
		};

		$content = "{$symbol->value} {$this->event->cardResolvedInfo( $status )}";

		$this->factoryStoppedCreatingCards( $status ) || ( $content .= PHP_EOL . CardResolved::CHECK_NEXT_INFO );

		$section->addContent( $content );
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
