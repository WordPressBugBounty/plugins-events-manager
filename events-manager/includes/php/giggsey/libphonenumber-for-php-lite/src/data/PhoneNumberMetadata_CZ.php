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
		'NationalNumberPattern' => '(?:[2-578]\\d|60)\\d{7}|9\\d{8,11}',
		'PossibleLength'        =>
		array(
			0 => 9,
			1 => 10,
			2 => 11,
			3 => 12,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:2\\d|3[1257-9]|4[16-9]|5[13-9])\\d{7}',
		'ExampleNumber'         => '212345678',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:60[1-8]\\d|7(?:0(?:[2-5]\\d|60)|190|[2379]\\d\\d))\\d{5}',
		'ExampleNumber'         => '601123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{6}',
		'ExampleNumber'         => '800123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '9(?:0[05689]|76)\\d{6}',
		'ExampleNumber'         => '900123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '8[134]\\d{7}',
		'ExampleNumber'         => '811234567',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'personalNumber'                =>
	array(
		'NationalNumberPattern' => '70[01]\\d{6}',
		'ExampleNumber'         => '700123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '9[17]0\\d{6}',
		'ExampleNumber'         => '910123456',
		'PossibleLength'        =>
		array(
			0 => 9,
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
		'NationalNumberPattern' => '9(?:5\\d|7[2-4])\\d{6}',
		'ExampleNumber'         => '972123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'voicemail'                     =>
	array(
		'NationalNumberPattern' => '9(?:3\\d{9}|6\\d{7,10})',
		'ExampleNumber'         => '93123456789',
	),
	'noInternationalDialling'       =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'id'                            => 'CZ',
	'countryCode'                   => 420,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2-8]|9[015-7]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '96',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '9',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		3 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '9',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
