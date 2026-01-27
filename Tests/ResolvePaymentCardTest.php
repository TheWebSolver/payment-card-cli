<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use LogicException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TheWebSolver\Codegarage\PaymentCard\Console\ResolvePaymentCard;

class ResolvePaymentCardTest extends TestCase {
	#[Test]
	public function itThrowsExceptionWhenPayloadOptionNotPassed(): void {
		$tester = new CommandTester( new ResolvePaymentCard() );

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage( ResolvePaymentCard::PAYLOAD_MISSING );

		$tester->execute( [ 'card-number' => '378282246310005' ] );
	}

	#[Test]
	public function itResolvesCardType(): void {
		$tester = new CommandTester( new ResolvePaymentCard() );

		$tester->execute(
			[
				'card-number' => '378282246310005',
				'--payload'   => [
					[
						'american-express' => [
							'name'       => 'American Express',
							'alias'      => 'american-express',
							'code'       => [ 'CID', 4 ],
							'breakpoint' => [ 4, 10 ],
							'length'     => [ 15 ],
							'idRange'    => [ 34, 37 ],
						],
					],
				],
			]
		);

		$this->assertStringContainsString( 'Given payment card number "378282246310005" is valid as American Express', $tester->getDisplay() );
		$tester->assertCommandIsSuccessful();
	}
}
