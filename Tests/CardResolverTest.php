<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\PaymentCard\PaymentCardFactory;
use TheWebSolver\Codegarage\PaymentCard\Helper\CardResolver;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\ResolverAction;
use TheWebSolver\Codegarage\PaymentCard\Interfaces\CardResolver as Resolver;

class CardResolverTest extends TestCase {
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

	private Resolver $resolver;

	public function setUp(): void {
		$this->resolver = ( new CardResolver() )->using(
			new PaymentCardFactory( self::DOMESTIC_CARDS ),
			new PaymentCardFactory( PaymentCardFactory::RESOURCE_PATH . DIRECTORY_SEPARATOR . 'paymentCards.json' )
		);
	}

	public function tearDown(): void {
		unset( $this->resolver );
	}

	#[Test]
	#[DataProvider( 'provideNumbersForExit' )]
	public function itResolvesEitherCardOrNullWhenExitStatusIsTrue( string $number, ?string $expectedName = null ): void {
		$this->assertSame( $expectedName, $this->resolver->for( $number )->resolve( true )?->getName() );
	}

	/** @return list<list<string>> */
	public static function provideNumbersForExit(): array {
		return [
			[ 'non-a-number' ],
			[ '9792030000000000', 'Troy' ],
			[ '6011277750635920', 'Discover' ],
			[ '6500830000000002', 'Troy' ],
			[ '6460435912011101', 'Dummy Nepal Card' ],
		];
	}

	#[Test]
	public function itResolvesEitherCardOrNullWhenExitStatusIsFalse(): void {
		$action        = $this->createStub( ResolverAction::class );
		$resolvedCards = $this->resolver->for( '6460435912011101' )->resolve( false );

		$this->assertCount( 2, $resolvedCards ?? [], 'Ues both factories payload to resolve card.' );

		// @phpstan-ignore-next-line
		$this->assertSame( 'Dummy Nepal Card', $resolvedCards[0][0]->getName(), 'Matches "64" from Dummy payload' );
		$this->assertFalse( isset( $resolvedCards[0][1] ) );
		// @phpstan-ignore-next-line
		$this->assertSame( 'Discover', $resolvedCards[1][0]->getName(), 'Matches "64" from Discover payload' );
		$this->assertFalse( isset( $resolvedCards[1][1] ) );
	}
}
