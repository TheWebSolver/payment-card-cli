<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;
use Symfony\Component\Console\Output\ConsoleSectionOutput as Output;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus as Event;

class ResolvedMessageBuilder {
	protected bool $shouldWrite = true;
	protected CardResolver $cardResolver;

	/*
	|------------------------------------------------------------------------------------------------
	| Artifacts during build process. Must be cleared after build is complete
	|------------------------------------------------------------------------------------------------
	*/

	/** Parameter provided to build message. */
	private Event $event;

	/** @param OutputInterface::VERBOSITY* $verbosity */
	public function __construct( protected InputInterface $input, protected OutputInterface $output, protected int $verbosity ) {}

	public function using( CardResolver $resolver ): self {
		$this->cardResolver = $resolver;

		return $this;
	}

	public function withoutPrint( bool $doNotWriteToConsole = true ): self {
		$this->shouldWrite = ! $doNotWriteToConsole;

		return $this;
	}

	public function build( Event $event ): ?Output {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return null;
		}

		$this->event = $event;

		if ( $event->isCreating() ) {
			$this->handleCardCreated( $section );
		} else {
			$this->handleCardResolved( $section );
		}

		$this->shouldWrite && $section->writeln( $section->getContent() );

		unset( $this->event );

		return $section;
	}

	protected function handleCardCreated( Output $section ): void {
		$status = $this->cardResolver->getCoveredCardStatus()[ $this->event->current()->payloadIndex ];

		$section->addContent( $this->event->createdInfo( $status ) );

		$this->factoryStoppedCreatingCards( $status ) || $section->addContent( Event::CHECK_NEXT_INFO );
	}

	protected function handleCardResolved( Output $section ): int {
		$section->addContent( $this->event->factoryInfo() );

		return ! $this->event->started()
			? $section->addContent( "<info>{$this->event->resourceInfo()}</>" )
			: $section->addContent( $this->colorize( $this->event->isSuccess() ? 'green' : 'red', $this->event->resolvedInfo() ) );
	}

	protected function factoryStoppedCreatingCards( Status $status ): bool {
		return $this->event->finished()
			|| ( Status::Success === $status && ResolvePaymentCard::shouldExitOnResolve( $this->input ) );
	}

	protected function colorize( string $bg, string $info, string $fg = 'black' ): string {
		return "<bg={$bg};fg={$fg}>{$info}</>";
	}
}
