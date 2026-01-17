<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Console;

use Iterator;
use ArrayObject;
use TheWebSolver\Codegarage\Cli\Helper\Parser;
use TheWebSolver\Codegarage\Cli\Data\Associative;
use TheWebSolver\Codegarage\Cli\Attribute\Command;
use Symfony\Component\Console\Output\OutputInterface;
use TheWebSolver\Codegarage\Scraper\Enums\FileFormat;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;
use TheWebSolver\Codegarage\Scraper\Interfaces\Scrapable;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\ScrapedTable;
use TheWebSolver\Codegarage\Cli\Integration\Scraper\TableConsole;
use TheWebSolver\Codegarage\Scraper\Integration\Cli\TableConsole as CliTableConsole;

/** @template-extends TableConsole<string|int> */
#[Command( 'scrape', ScrapeWikiPaymentCard::NAME, 'Scrapes various payment card types from Wikipedia' )]
#[Associative( 'extension', suggestedValues: FileFormat::class, default: FileFormat::Json )]
class ScrapeWikiPaymentCard extends TableConsole {
	/** @use CliTableConsole<string|int> */
	use CliTableConsole;

	final public const NAME = 'wiki-payment-card';

	/** @param Scrapable<Iterator<array-key,ArrayObject<array-key,string|int>>,TableTracer<string|int>> $service */
	public function __construct( private readonly Scrapable $service, ?string $dirPath = '', string $fileName = '' ) {
		$this->withCachePath( $dirPath, $fileName ?: self::NAME );

		parent::__construct();
	}

	public function scraper(): Scrapable {
		return $this->service;
	}

	protected function defaultCachePath(): string {
		return PaymentCardFactory::RESOURCE_PATH;
	}

	/** @return string[] */
	public static function accentedActionSuggestions(): array {
		return array_map( strtolower( ... ), (array) Parser::parseBackedEnumValue( AccentedChars::class ) );
	}

	protected function getOutputTable( OutputInterface $output, bool $cachingDisabled ): ScrapedTable {
		return ( new ScrapedTable( $output, $cachingDisabled ) )->setHeaderTitle( 'Payment Card Name & Details' );
	}

	protected function getTableContextForOutput(): string {
		return 'Payment Cards';
	}
}
