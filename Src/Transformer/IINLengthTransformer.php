<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Transformer;

use DOMText;
use DOMElement;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<object,list<int|int[]>> */
class IINLengthTransformer implements Transformer {
	/** @return list<int|int[]> */
	public function transform( string|array|DOMElement $element, object $scope ): array {
		AssertDOMElement::instance( $element );

		$value = '';

		foreach ( $element->childNodes as $node ) {
			$node instanceof DOMText && ( $value .= trim( $node->textContent ) );
		}

		return $value ? array_reduce( explode( ',', $value ), $this->toIntList( ... ), initial: [] ) : [];
	}

	/**
	 * @param list<int|int[]> $carry
	 * @return non-empty-list<int|int[]>
	 */
	private function toIntList( array $carry, string $item ): array {
		if ( ctype_digit( $item ) ) {
			$carry[] = $this->toInt( $item );

			return $carry;
		}

		$range   = array_map( $this->toInt( ... ), $this->onlyNumbersList( $item ) );
		$carry[] = count( $range ) === 1 ? $range[0] : $range;

		return $carry;
	}

	/** @return list<string> */
	private function onlyNumbersList( string $value ): array {
		return preg_split( '/\D+/', $value, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
	}

	private function toInt( string $value ): int {
		return abs( (int) trim( $value ) );
	}
}
