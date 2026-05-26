<?php
/**
 * libphonenumber-for-php-lite data file
 * This file has been @generated from libphonenumber data
 * Do not modify!
 * @internal
 */

return array(
	'generalDesc'                   =>
	array(
		'NationalNumberPattern' => '[1-7]\\d{7}|8\\d{4,7}|90\\d{4,6}',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 6,
			2 => 7,
			3 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:[1-357][2-8]|4[24-8])\\d{6}',
		'ExampleNumber'           => '12345678',
		'PossibleLength'          =>
		array(
			0 => 8,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '65(?:[178]\\d|5[56]|6[01])\\d{4}|(?:[37][01]|4[0139]|51|6[489])\\d{6}',
		'ExampleNumber'         => '31234567',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80\\d{4,6}',
		'ExampleNumber'         => '80123456',
		'PossibleLength'        =>
		array(
			0 => 6,
			1 => 7,
			2 => 8,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '89[1-3]\\d{2,5}|90\\d{4,6}',
		'ExampleNumber'         => '90123456',
	),
	'sharedCost'                    =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'personalNumber'                =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '(?:59\\d\\d|8(?:1(?:[67]\\d|8[0-589])|2(?:0\\d|2[0-37-9]|8[0-2489])|3[389]\\d))\\d{4}',
		'ExampleNumber'         => '59012345',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'pager'                         =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'uan'                           =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'voicemail'                     =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'noInternationalDialling'       =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'id'                            => 'SI',
	'countryCode'                   => 386,
	'internationalPrefix'           => '00|10(?:22|66|88|99)',
	'preferredInternationalPrefix'  => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3,6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '8[09]|9',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{5})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '59|8',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[37][01]|4[0139]|51|6',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		3 =>
		array(
			'pattern'                              => '(\\d)(\\d{3})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1-57]',
			),
			'nationalPrefixFormattingRule'         => '(0$1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
