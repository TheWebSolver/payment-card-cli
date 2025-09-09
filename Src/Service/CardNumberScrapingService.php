<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Service;

use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\PaymentCard\CardFactory;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\PaymentCard\Transformer\Proxy;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Service\TableScrapingService;

/**
 * @template TTracer of TableTracer<string|list<int|int[]>>
 * @template-extends TableScrapingService<string|list<int|int[]>,TTracer>
 */
#[ScrapeFrom( 'Payment Card Number', 'https://en.wikipedia.org/wiki/Payment_card_number', 'payment-card-number.html' )]
class CardNumberScrapingService extends TableScrapingService {
	/** @param TTracer $tableTracer */
	public function __construct( protected TableTracer $tableTracer, ?ScrapeFrom $scrapeFrom = null ) {
		parent::__construct( $tableTracer->traceWithout( Table::Caption, Table::THead ), $scrapeFrom );
	}

	protected function defaultCachePath(): string {
		return CardFactory::RESOURCE_PATH;
	}

	protected function hydrateWithDefaultTransformers( TableTraced $event ): void {
		parent::hydrateWithDefaultTransformers( $event );

		$event->tracer->addTransformer( Table::Column, new Proxy() );
	}
}
