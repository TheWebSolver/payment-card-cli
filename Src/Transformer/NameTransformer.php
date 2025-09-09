<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Transformer;

use DOMText;
use DOMElement;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<object,string> */
class NameTransformer implements Transformer {
	public function transform( string|array|DOMElement $element, object $scope ): string {
		AssertDOMElement::instance( $element );

		$content = ' ';

		foreach ( $element->childNodes as $node ) {
			if ( $node instanceof DOMElement && ( 'a' === $node->tagName ) && ( $elValue = $node->textContent ) ) {
				$content .= trim( $elValue ) . ' ';
			}

			if ( $node instanceof DOMText && ( $txtValue = $node->textContent ) ) {
				$content .= trim( $txtValue ) . ' ';
			}
		}

		return trim( $content );
	}
}
