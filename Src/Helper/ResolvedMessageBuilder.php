<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Helper;

use TheWebSolver\Codegarage\Cli\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Enums\Status;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;
use Symfony\Component\Console\Output\ConsoleSectionOutput as Output;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardFactoryStatus as Action;

class ResolvedMessageBuilder {
	protected bool $shouldWrite = true;
	protected CardResolver $cardResolver;

	/*
	|------------------------------------------------------------------------------------------------
	| Artifacts during build process. Must be cleared after build is complete
	|------------------------------------------------------------------------------------------------
	*/

	/** Parameter provided to build message. */
	private Action $action;

	/** @param OutputInterface::VERBOSITY* $verbosity */
	public function __construct( protected InputInterface $input, protected OutputInterface $output, protected int $verbosity ) {}

	public function usingCardResolver( CardResolver $resolver ): self {
		$this->cardResolver = $resolver;

		return $this;
	}

	public function withoutPrint( bool $doNotWriteToConsole = true ): self {
		$this->shouldWrite = ! $doNotWriteToConsole;

		return $this;
	}

	public function build( Action $action ): ?Output {
		if ( ! $section = Console::getOutputSection( $this->output, $this->verbosity ) ) {
			return null;
		}

		$this->action = $action;

		if ( $action->isCreating() ) {
			$this->handleCardCreated( $section );
		} else {
			$this->handleCardResolved( $section );
		}

		$this->shouldWrite && $section->writeln( $section->getContent() );

		unset( $this->action );

		return $section;
	}

	protected function handleCardCreated( Output $section ): void {
		$status = $this->cardResolver->getCoveredCardStatus()[ $this->action->event()->payloadIndex ];

		$section->addContent( $this->action->createdInfo( $status ) );

		$this->factoryStoppedCreatingCards( $status ) || $section->addContent( Action::CHECK_NEXT_INFO );
	}

	protected function handleCardResolved( Output $section ): void {
		$section->addContent( $this->action->factoryInfo() );

		if ( ! $this->action->started() ) {
			$section->addContent( "<info>{$this->action->resourceInfo()}</>" );

			return;
		}

		$color = $this->action->isSuccess() ? 'green' : 'red';

		$section->addContent( "<bg={$color};fg=black>{$this->action->resolvedInfo()}</>" );
	}

	protected function factoryStoppedCreatingCards( Status $status ): bool {
		return $this->action->finished()
			|| ( Status::Success === $status && ResolvePaymentCard::shouldExitOnResolve( $this->input ) );
	}
}
