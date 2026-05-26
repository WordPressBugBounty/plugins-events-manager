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
		'NationalNumberPattern' => '(?:1|6\\d)\\d{7}|[135-9]\\d{5}',
		'PossibleLength'        =>
		array(
			0 => 6,
			1 => 8,
			2 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '[78]\\d{5}',
		'ExampleNumber'         => '712345',
		'PossibleLength'        =>
		array(
			0 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '690\\d{6}|[356]\\d{5}',
		'ExampleNumber'         => '312345',
		'PossibleLength'        =>
		array(
			0 => 6,
			1 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '180[02]\\d{4}',
		'ExampleNumber'         => '18001234',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '[19]\\d{5}',
		'ExampleNumber'         => '912345',
		'PossibleLength'        =>
		array(
			0 => 6,
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
		'PossibleLength' =>
		array(
			0 => -1,
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
		'NationalNumberPattern' => '1800\\d{4}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'id'                            => 'AD',
	'countryCode'                   => 376,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[135-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{4})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '1',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '6',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
