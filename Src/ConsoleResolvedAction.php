<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolvedAction;

interface ConsoleResolvedAction extends ResolvedAction {
	/**
	 * Sets IO for performing resolved action when resolver is running in console.
	 *
	 * @param OutputInterface::VERBOSITY* $verbosity The output verbosity where resolved action should be performed.
	 */
	public function usingIO( InputInterface $input, OutputInterface $output, int $verbosity = OutputInterface::VERBOSITY_VERY_VERBOSE ): self;
}
