<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\PaymentCard\Cli\Helper\PaymentCardResolver;

class PaymentCardResolverTest extends TestCase {
	public const DOMESTIC_CARDS = [
		'dnc' => [
			'name'       => 'Dummy Nepal Card',
			'alias'      => 'dnc',
			'type'       => 'Debit Card',
			'code'       => [
				'name' => 'CVC',
				'size' => 3,
			],
			'breakpoint' => [ 4, 8, 12 ],
			'length'     => [ 16, 18, 19 ],
			'idRange'    => [ 50, 64, [ 90, 93 ] ],
		],
	];

	private PaymentCardResolver $resolver;

	public function setUp(): void {
		$this->resolver = new PaymentCardResolver(
			new PaymentCardFactory( self::DOMESTIC_CARDS ),
			new PaymentCardFactory( PaymentCardFactory::RESOURCE_PATH . DIRECTORY_SEPARATOR . 'paymentCards.json' )
		);
	}

	public function tearDown(): void {
		unset( $this->resolver );
	}

	#[Test]
	#[DataProvider( 'provideNumbersForExit' )]
	public function itResolvesEitherCardOrNullWhenExitStatusIsTrue( string $number, string $expectedAlias = '' ): void {
		$action = static function () {};

		if ( $expectedAlias ) {
			$this->assertSame( $expectedAlias, $this->resolver->resolveCard( $number, true, $action )?->getAlias() );
		} else {
			$this->assertNull( $this->resolver->resolveCard( $number, true, $action ) );
		}
	}

	/** @return list<list<string>> */
	public static function provideNumbersForExit(): array {
		return [
			[ 'non-a-number' ],
			[ '9792030000000000', 'troy' ],
			[ '6011277750635920', 'discover' ],
			[ '6500830000000002', 'troy' ],
			[ '6460435912011101', 'dnc' ],
		];
	}

	#[Test]
	public function itResolvesEitherCardOrNullWhenExitStatusIsFalse(): void {
		$resolvedCards = $this->resolver->resolveCard( '6460435912011101', false, function () {} );

		$this->assertCount( 2, $resolvedCards ?? [], 'Ues both factories payload to resolve card.' );

		// @phpstan-ignore-next-line
		$this->assertSame( 'dnc', $resolvedCards[0][0]->getAlias(), 'Matches "64" from Dummy payload' );
		$this->assertFalse( isset( $resolvedCards[0][1] ) );
		// @phpstan-ignore-next-line
		$this->assertSame( 'discover', $resolvedCards[1][0]->getAlias(), 'Matches "64" from Discover payload' );
		$this->assertFalse( isset( $resolvedCards[1][1] ) );
	}
}
