<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\PaymentCard\Service;

use Iterator;
use ValueError;
use ArrayObject;
use TheWebSolver\Codegarage\PaymentCard\CardFactory;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Attributes\ScrapeFrom;
use TheWebSolver\Codegarage\Scraper\Service\ScrapingService;

/** @template-extends ScrapingService<string|int|list<int|string|list<int|string>>> */
#[ScrapeFrom( 'Braintree GitHub', 'https://raw.githubusercontent.com/braintree/credit-card-type/refs/heads/main/src/lib/card-types.ts', 'cards.ts' )]
class BraintreeGithubScrapingService extends ScrapingService {
	final public const CARD_TYPE_REGEX       = '.*?{(.*?}, )}';
	final public const DIGIT_REGEX           = '[0-9]+';
	final public const DIGIT_SEPARATOR_REGEX = '[, ?]+';
	final public const DIGIT_VALUE_REGEX     = '[\[]+[0-9,? ?]+[\]]';
	final public const DIGIT_RANGE_REGEX     = '[\[]+' . self::DIGIT_REGEX . '+' . self::DIGIT_SEPARATOR_REGEX . self::DIGIT_REGEX . '[\]]';

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
	/** @var array{niceType:string,type:string,patterns:string,gaps:string,lengths:string} */
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

			yield $type => new ArrayObject( $this->cardDetails );
		}
	}

	/**
	 * @return list<string>
	 * @throws ValueError When cannot extract card types.
	 */
	private function matchedCardTypes( string $string ): array {
		return preg_match_all( '/' . self::CARD_TYPE_REGEX . '/', $string, $cardTypes )
			? $cardTypes[1]
			: throw new ValueError( 'Invalid JS Object for extracting Braintree Github Card Types.' );
	}

	/**
	 * @return array{niceType:string,type:string,patterns:string,gaps:string,lengths:string}
	 * @throws ValueError When invalid JS Object given.
	 */
	private function extractKeyValue( string $string ): array {
		return preg_match_all( "/{$this->getCardObjectKeyValueRegex()}/", $string, $matchedGroup, PREG_SET_ORDER )
			? array_reduce( $matchedGroup, $this->toCardObjectKeyValue( ... ), initial: [] )
			: throw new ValueError(
				sprintf(
					'Invalid JS Object for extracting Braintree GitHub Card details key/value pair. %s given.',
					$string
				)
			);
	}

	/**
	 * @return array{name:string,size:int|string}
	 * @throws ValueError When cannot extract code key/value pair.
	 */
	private function extractCardCodeDetails( string $string ): array {
		return preg_match_all( "/{$this->getCardCodeKeyValueRegex()}/", $string, $matchedGroups, PREG_SET_ORDER )
			? array_reduce( $matchedGroups, $this->toCardCodeNameAndSize( ... ), initial: [] )
			: throw new ValueError(
				sprintf( 'Invalid JS Object for extracting Braintree GitHub Card Code details. %s given.', $string )
			);
	}

	/**
	 * @param array<string,string> $carry
	 * @param array<string>        $group
	 * @return array<string,string>
	 */
	private function toCardObjectKeyValue( array $carry, array $group ): array {
		$carry[ $group[1] ] = trim( $group[2], '"' );

		return $carry;
	}

	/**
	 * @param array<string,int|string> $carry
	 * @param array<string>            $group
	 * @return array<string,int|string>
	 */
	private function toCardCodeNameAndSize( array $carry, array $group ): array {
		'size' === $group[1] && $this->numericToInteger && ( $group[2] = intval( $group[2] ) );

		$carry[ (string) $group[1] ] = $group[2];

		return $carry;
	}

	private function getCardObjectKeyValueRegex(): string {
		$indexGroup                  = '([niceType|type|patterns|gaps|lengths]+)';
		$jsKeyValueSeparatorAndSpace = '\: ';
		$valueGroupOpen              = '(';
		$valueGroupClose             = ')';
		$anyValue                    = '.*?';
		$captureTillNextIndexInitial = '(?=, ?[n|t|p|g|l])|';
		$orCaptureNumericValue       = self::DIGIT_VALUE_REGEX;

		return $indexGroup
			. $jsKeyValueSeparatorAndSpace
			. $valueGroupOpen . $anyValue . $captureTillNextIndexInitial . $orCaptureNumericValue . $valueGroupClose;
	}

	private function getCardCodeKeyValueRegex(): string {
		$indexGroup                     = '([name|size]+)';
		$jsKeyValueSeparatorAndControls = '(?:\:[ ]+?)';
		$captureIfIsCodeName            = '?:"?([A-Z]+|';
		$orCaptureCodeLength            = self::DIGIT_REGEX . ')';
		$valueGroupOpen                 = '(';
		$valueGroupClose                = ')';

		return $indexGroup
			. $jsKeyValueSeparatorAndControls
			. $valueGroupOpen . $captureIfIsCodeName . $orCaptureCodeLength . $valueGroupClose;
	}

	private function maybeRemoveOpeningClosingBracketsFrom( string $string ): string {
		$offset = 0;
		$length = null;

		str_starts_with( $string, '[' ) && $offset = 1;
		str_ends_with( $string, ']' ) && $length   = -1;

		( $offset || $length ) && ( $string = substr( $string, $offset, $length ) );

		return trim( $string );
	}

	/**
	 * @param 'patterns'|'gaps'|'lengths' $key
	 * @throws ValueError When cannot extract numeric value.
	 */
	private function extractDigitsInCardDetails( string $key ): void {
		$subject           = $this->maybeRemoveOpeningClosingBracketsFrom( $raw = $this->rawCardDetails[ $key ] );
		$digitOrRangeRegex = self::DIGIT_RANGE_REGEX . '|' . self::DIGIT_REGEX;

		if ( ! preg_match_all( "/{$digitOrRangeRegex}/", $subject, $singleOrRange ) ) {
			throw new ValueError(
				sprintf(
					'Card details must be either a number or comma separated numbers enclosed by "[" and "]". %s given.',
					$raw
				)
			);
		}

		$details = $singleOrRange[0];

		array_walk( $details, $this->toNumberOrNumberRange( ... ) );

		$this->cardDetails[ $key ] = $details;
	}

	/**
	 * @param-out string|int|list<string|int> $string
	 * @throws ValueError When cannot match range pattern.
	 */
	private function toNumberOrNumberRange( string &$string ): void {
		if ( ! str_starts_with( $string, '[' ) ) {
			$this->numericToInteger && ( $string = intval( $string ) );

			return;
		}

		if ( ! preg_match_all( '/' . self::DIGIT_REGEX . '/', $string, $range ) ) {
			throw new ValueError(
				sprintf( 'Card details in range enclosed by "[" and "]" must only be numbers. %s given.', $string )
			);
		}

		$string = $this->numericToInteger ? array_map( intval( ... ), $range[0] ) : $range[0];
	}
}
