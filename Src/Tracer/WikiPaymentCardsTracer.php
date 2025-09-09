<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Tracer;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Traits\Table\HtmlTableFromNode;

/** @template-implements TableTracer<string|list<int|int[]>> */
class WikiPaymentCardsTracer implements TableTracer {
	/** @use HtmlTableFromNode<string|list<int|int[]>> */
	use HtmlTableFromNode;

	public const TARGET_ELEMENT_HEADS = [ 'Issuing network', 'IIN ranges', 'Active', 'Length', 'Validation' ];

	protected function isTargetedTable( string|DOMElement $node ): bool {
		if ( ! $node instanceof DOMElement ) {
			return false;
		}

		if ( ! ( $tableHeadRow = $node->firstChild?->firstChild ) instanceof DOMElement ) {
			return false;
		}

		if ( Table::Row->value !== $tableHeadRow->tagName || 5 !== $tableHeadRow->childElementCount ) {
			return false;
		}

		( $tracer = new self() )->inferTableHeadFrom( $tableHeadRow->childNodes );
		$heads = $tracer->getTableHead()[ $tracer->getTableId( true ) ] ?? null;

		return empty( array_diff( self::TARGET_ELEMENT_HEADS, $heads?->toArray() ?? [] ) );
	}
}
