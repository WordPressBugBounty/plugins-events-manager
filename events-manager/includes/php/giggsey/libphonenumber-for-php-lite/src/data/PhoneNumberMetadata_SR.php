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
		'NationalNumberPattern' => '(?:[2-5]|68|[78]\\d)\\d{5}',
		'PossibleLength'        =>
		array(
			0 => 6,
			1 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:2[1-3]|3[0-7]|(?:4|68)\\d|5[2-58])\\d{4}',
		'ExampleNumber'         => '211234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:7[124-7]|8[124-9])\\d{5}',
		'ExampleNumber'         => '7412345',
		'PossibleLength'        =>
		array(
			0 => 7,
		),
	),
	'tollFree'                      =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'premiumRate'                   =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
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
		'NationalNumberPattern' => '56\\d{4}',
		'ExampleNumber'         => '561234',
		'PossibleLength'        =>
		array(
			0 => 6,
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
	'id'                            => 'SR',
	'countryCode'                   => 597,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1-$2-$3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '56',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})',
			'format'                               => '$1-$2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2-5]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{4})',
			'format'                               => '$1-$2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[6-8]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
