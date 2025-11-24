<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\Scraper\Service\ScrapingService;
use TheWebSolver\Codegarage\PaymentCard\Service\BraintreeCardTypeScrapingService;

class ScrapingTest extends TestCase {
	public const RESOURCE_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'Resource';
	public const WIKI_CARDS         = self::RESOURCE_DIRECTORY . DIRECTORY_SEPARATOR . 'wiki-cards.php';

	#[Test]
	public function itParsesScrapedPaymentCardDetailsFromWikiSite(): void {
		$iterator = ( new PaymentCardFactory() )->generateRowIterator();

		foreach ( require_once self::WIKI_CARDS as $expectedCard ) {
			$this->assertSame( $expectedCard, $iterator->current()->getArrayCopy() );

			$iterator->next();
		}

		$this->assertFalse( $iterator->valid() );
	}

	#[Test]
	public function itScrapesFromBraintreeGithub(): void {
		$mastercard = $this->getMasterCard( new BraintreeCardTypeScrapingService( numericToInteger: true ) );

		$this->assertSame( [ 4, 8, 12 ], $mastercard['gaps'] );
		$this->assertSame( [ 16 ], $mastercard['lengths'] );
		$this->assertSame(
			[ [ 51, 55 ], [ 2221, 2229 ], [ 223, 229 ], [ 23, 26 ], [ 270, 271 ], 2720 ],
			$mastercard['patterns']
		);

		$this->assertSame(
			[
				'name' => 'CVC',
				'size' => 3,
			],
			$mastercard['code']
		);

		$mastercard = $this->getMasterCard( new BraintreeCardTypeScrapingService( numericToInteger: false ) );

		$this->assertSame( [ '4', '8', '12' ], $mastercard['gaps'] );
		$this->assertSame( [ '16' ], $mastercard['lengths'] );
		$this->assertSame(
			[ [ '51','55' ], [ '2221','2229' ], [ '223','229' ], [ '23','26' ], [ '270','271' ], '2720' ],
			$mastercard['patterns']
		);
		$this->assertSame(
			[
				'name' => 'CVC',
				'size' => '3',
			],
			$mastercard['code']
		);
	}

	private function getMasterCard( ScrapingService $scraper ): array {
		if ( $scraper->withCachePath( self::RESOURCE_DIRECTORY, 'cards.ts' )->hasCache() ) {
			$iterator = $scraper->parse( $scraper->fromCache() );
		} else {
			$scraper->toCache( $scraper->scrape() );

			$iterator = $scraper->parse( $scraper->fromCache() );
		}

		$mastercard = null;

		while ( $iterator->valid() ) {
			if ( 'mastercard' === $iterator->key() ) {
				$mastercard = (array) $iterator->current();

				break;
			}

			$iterator->next();
		}

		return $mastercard;
	}
}
