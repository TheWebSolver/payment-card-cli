<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\Scraper\Factory;
use TheWebSolver\Codegarage\PaymentCard\Tracer\WikiPaymentCardsTracer;
use TheWebSolver\Codegarage\PaymentCard\Service\CardNumberScrapingService;

class ScrapingTest extends TestCase {
	public const WIKI_CARDS = __DIR__ . DIRECTORY_SEPARATOR . 'Resource' . DIRECTORY_SEPARATOR . 'wiki-cards.php';

	#[Test]
	public function itParsesScrapedPaymentCardDetailsFromWikiSite(): void {
		$factory  = new Factory( new CardNumberScrapingService( new WikiPaymentCardsTracer() ) );
		$iterator = $factory->generate();

		foreach ( require_once self::WIKI_CARDS as $expectedCard ) {
			$this->assertSame( $expectedCard, $iterator->current()->getArrayCopy() );

			$iterator->next();
		}

		$this->assertFalse( $iterator->valid() );
	}
}
