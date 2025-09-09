<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Transformer;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;

/** @template-implements Transformer<TableTracer<string>,string|list<int|int[]>> */
class Proxy implements Transformer {
	/** @param Transformer<contravariant TableTracer<string>,string> $base */
	public function __construct( private readonly Transformer $base = new MarshallItem() ) {}

	public function transform( string|array|DOMElement $element, object $scope ): string|array {
		return ( match ( $scope->getCurrentIterationCount( Table::Column ) ) {
			default => $this->base,
			1       => new NameTransformer(),
			3       => new StatusTransformer(),
			2, 4    => new IINLengthTransformer(),
		} )->transform( $element, $scope );
	}
}
