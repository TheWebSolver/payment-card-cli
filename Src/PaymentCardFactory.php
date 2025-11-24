<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard;

use TheWebSolver\Codegarage\Scraper\TableFactory;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\ScrapeTraceableTable;
use TheWebSolver\Codegarage\PaymentCard\Tracer\WikiPaymentCardsTracer;
use TheWebSolver\Codegarage\PaymentCard\Service\WikiCardTypeScrapingService;

/** @template-extends TableFactory<string|list<int|int[]>,TableTracer<string|list<int|int[]>>> */
class PaymentCardFactory extends TableFactory {
	/** @param ScrapeTraceableTable<string|list<int|int[]>,covariant TableTracer<string|list<int|int[]>>> $service */
	public function __construct(
		private ScrapeTraceableTable $service = new WikiCardTypeScrapingService( new WikiPaymentCardsTracer() )
	) {}

	public function scraper(): ScrapeTraceableTable {
		return $this->service;
	}
}
