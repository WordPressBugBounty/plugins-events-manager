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
		'NationalNumberPattern' => '0800\\d{4}|(?:[237]\\d|900)\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 8,
			1 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '[23][2-5]\\d{6}',
		'ExampleNumber'         => '22171234',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '7[6-9]\\d{6}',
		'ExampleNumber'         => '76123456',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '0800\\d{4}',
		'ExampleNumber'         => '08001234',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '900\\d{6}',
		'ExampleNumber'         => '900012345',
		'PossibleLength'        =>
		array(
			0 => 9,
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
		'NationalNumberPattern' => '70\\d{6}',
		'ExampleNumber'         => '70012345',
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
		'NationalNumberPattern' => '0800\\d{4}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'id'                            => 'SZ',
	'countryCode'                   => 268,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{4})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[0237]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{5})(\\d{4})',
			'format'                               => '$1 $2',
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
	'mobileNumberPortableRegion'    => false,
);
