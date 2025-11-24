<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Service;

use Iterator;
use ValueError;
use TheWebSolver\Codegarage\PaymentCard\CardFactory;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Service\ScrapingService;

/**
 * @template-extends ScrapingService<Iterator<
 *  string,
 *  array{
 *   niceType : string,
 *   type     : string,
 *   patterns : list<int|string|list<int|string>>,
 *   gaps     : list<int|string>,
 *   lengths  : list<int|string>,
 *   code     : array{name:string,size:int|string}
 *  }
 * >>
 */
#[ScrapeFrom( 'Braintree GitHub', 'https://raw.githubusercontent.com/braintree/credit-card-type/refs/heads/main/src/lib/card-types.ts', 'cards.ts' )]
class BraintreeCardTypeScrapingService extends ScrapingService {
	final public const CARD_TYPE_REGEX       = '.*?{(.*?}, )}';
	final public const DIGIT_REGEX           = '[0-9]+';
	final public const DIGIT_SEPARATOR_REGEX = '[, ?]+';
	final public const DIGIT_VALUE_REGEX     = '[\[]+[0-9,? ?]+[\]]';
	final public const DIGIT_RANGE_REGEX     = '[\[]+' . self::DIGIT_REGEX . '+' . self::DIGIT_SEPARATOR_REGEX . self::DIGIT_REGEX . '[\]]';

	final public const INVALID_JS_OBJECT_FORMAT = 'Invalid JS Object for extracting Braintree Github Card Types.';
	/** @placeholder: `%S` Given string to extract card type */
	final public const INVALID_JS_OBJECT_FOR_CARD_TYPE = 'Invalid JS Object for extracting Braintree GitHub Card details key/value pair. %s given.';
	/** @placeholder: `%S` Given string to extract card code details. */
	final public const INVALID_JS_OBJECT_FOR_CARD_CODE = 'Invalid JS Object for extracting Braintree GitHub Card Code details. %s given.';

	final public const INVALID_JS_OBJECT_KEYS_FOR_CARD_TYPE = 'Card Code must have "name" and "size" key/value pair.';
	final public const INVALID_JS_OBJECT_KEYS_FOR_CARD_CODE = 'Card Code must have "name" and "size" key/value pair.';
	/** @placeholder: `%S` Given string to extract numeric values. */
	final public const INVALID_FORMAT_FOR_NUMERIC_VALUE = 'Card details must be either a number or comma separated numbers enclosed by "[" and "]". %s given.';
	/** @placeholder: `%S` Given string to extract numeric value range. */
	final public const INVALID_FORMAT_FOR_NUMERIC_RANGE = 'Card details in range enclosed by "[" and "]" must only be numbers. %s given.';

	public function __construct( private bool $numericToInteger = true ) {
		parent::__construct();
	}

	public function defaultCachePath(): string {
		return CardFactory::RESOURCE_PATH;
	}

	/** @var array{
	 *  niceType : string,
	 *  type     : string,
	 *  patterns : list<int|string|list<int|string>>,
	 *  gaps     : list<int|string>,
	 *  lengths  : list<int|string>,
	 *  code     : array{name:string,size:int|string}
	 * }
	 */
	private array $cardDetails;
	/** @var array<'niceType'|'type'|'patterns'|'gaps'|'lengths',string> */
	private array $rawCardDetails;

	public function parse( string $content ): Iterator {
		$content = Normalize::controlsAndWhitespacesIn( $content );
		$content = explode( 'cardTypes: CardCollection = {', $content )[1];
		$numeric = [ 'patterns', 'gaps', 'lengths' ];

		foreach ( $this->matchedCardTypes( $content ) as $details ) {
			[$cardDetails, $codeDetails] = explode( 'code: {', $details, 2 );
			$this->rawCardDetails        = $this->extractKeyValue( trim( $cardDetails ) );
			$this->cardDetails['type']   = $type = $this->rawCardDetails['type'];
			$this->cardDetails['code']   = $this->extractCardCodeDetails( $codeDetails );

			array_walk( $numeric, $this->extractDigitsInCardDetails( ... ) );

			unset( $this->rawCardDetails );

			yield $type => $this->cardDetails;
		}
	}

	/** @return list<string> */
	private function matchedCardTypes( string $string ): array {
		return preg_match_all( '/' . self::CARD_TYPE_REGEX . '/', $string, $cardTypes )
			? $cardTypes[1]
			: $this->throw( self::INVALID_JS_OBJECT_FORMAT );
	}

	/** @return array<'niceType'|'type'|'patterns'|'gaps'|'lengths',string> */
	private function extractKeyValue( string $string ): array {
		$details = preg_match_all( "/{$this->getCardObjectKeyValueRegex()}/", $string, $matchedGroup, PREG_SET_ORDER )
			? array_reduce( $matchedGroup, $this->toCardObjectKeyValue( ... ), initial: [] )
			: null;

		return $details ?: $this->throw( self::INVALID_JS_OBJECT_FOR_CARD_TYPE, $string );
	}

	/** @return array{name:string,size:int|string} */
	private function extractCardCodeDetails( string $string ): array {
		$details = preg_match_all( "/{$this->getCardCodeKeyValueRegex()}/", $string, $matchedGroups, PREG_SET_ORDER )
			? array_reduce( $matchedGroups, $this->toCardCodeNameAndSize( ... ), initial: [] )
			: null;

		return $details ?: $this->throw( self::INVALID_JS_OBJECT_FOR_CARD_CODE, $string );
	}

	/**
	 * @param array{}       $carry
	 * @param array<string> $group
	 * @return array<'niceType'|'type'|'patterns'|'gaps'|'lengths',string>
	 */
	private function toCardObjectKeyValue( array $carry, array $group ): array {
		$key = $group[1] ?? null;

		match ( $key ) {
			default => $this->throw( self::INVALID_JS_OBJECT_KEYS_FOR_CARD_TYPE ),
			'niceType', 'type', 'patterns', 'gaps', 'lengths' => $carry[ $key ] = trim( $group[2], '"' ),
		};

		return $carry;
	}

	/**
	 * @param array{name?:string,size?:int|string} $carry
	 * @param array<string>                        $group
	 * @return array{name:string,size:int|string}
	 */
	private function toCardCodeNameAndSize( array $carry, array $group ): array {
		$key = $group[1] ?? null;

		match ( $key ) {
			default => $this->throw( self::INVALID_JS_OBJECT_FOR_CARD_CODE ),
			'name'  => $carry[ $key ] = $group[2],
			'size'  => $carry[ $key ] = $this->numericToInteger ? intval( $group[2] ) : $group[2],
		};

		assert( isset( $carry['name'], $carry['size'] ) );

		return $carry;
	}

	private function getCardObjectKeyValueRegex(): string {
		$key                                = '[niceType|type|patterns|gaps|lengths]+';
		$keyValueSeparator                  = '?:\:[ ]+?';
		$anyValue                           = '.*?';
		$captureIfSucceededByNextKeyInitial = '?=, ?[n|t|p|g|l]';
		$orCaptureNumericValue              = self::DIGIT_VALUE_REGEX;

		return "($key)($keyValueSeparator)($anyValue($captureIfSucceededByNextKeyInitial)|$orCaptureNumericValue)";
	}

	private function getCardCodeKeyValueRegex(): string {
		$key                 = '[name|size]+';
		$keyValueSeparator   = '?:\:[ ]+?';
		$captureIfIsCodeName = '?:"?([A-Z]+';
		$orCaptureCodeLength = self::DIGIT_REGEX;

		return "($key)($keyValueSeparator)($captureIfIsCodeName|$orCaptureCodeLength))";
	}

	private function maybeRemoveOpeningClosingBracketsFrom( string $string ): string {
		$offset = 0;
		$length = null;

		str_starts_with( $string, '[' ) && $offset = 1;
		str_ends_with( $string, ']' ) && $length   = -1;

		( $offset || $length ) && ( $string = substr( $string, $offset, $length ) );

		return trim( $string );
	}

	/** @param 'patterns'|'gaps'|'lengths' $key */
	private function extractDigitsInCardDetails( string $key ): void {
		$subject           = $this->maybeRemoveOpeningClosingBracketsFrom( $raw = $this->rawCardDetails[ $key ] );
		$digitOrRangeRegex = self::DIGIT_RANGE_REGEX . '|' . self::DIGIT_REGEX;

		preg_match_all( "/{$digitOrRangeRegex}/", $subject, $singleOrRange )
			|| $this->throw( self::INVALID_FORMAT_FOR_NUMERIC_VALUE, $raw );

		$details = $singleOrRange[0];

		array_walk( $details, $this->toNumberOrNumberRange( ... ) );

		$this->cardDetails[ $key ] = $details;
	}

	/** @param-out string|int|list<string|int> $string */
	private function toNumberOrNumberRange( string &$string ): void {
		if ( ! str_starts_with( $string, '[' ) ) {
			$this->numericToInteger && ( $string = intval( $string ) );

			return;
		}

		preg_match_all( '/' . self::DIGIT_REGEX . '/', $string, $range )
			|| $this->throw( self::INVALID_FORMAT_FOR_NUMERIC_RANGE, $string );

		$string = $this->numericToInteger ? array_map( intval( ... ), $range[0] ) : $range[0];
	}

	private function throw( string $msg, string|int ...$placeholderArgs ): never {
		throw new ValueError( sprintf( $msg, ...$placeholderArgs ) );
	}
}
