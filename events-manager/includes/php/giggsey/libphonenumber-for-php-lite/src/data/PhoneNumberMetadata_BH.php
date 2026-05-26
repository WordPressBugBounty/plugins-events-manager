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
		'NationalNumberPattern' => '[136-9]\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:1(?:3[1356]|6[0156]|7\\d)\\d|6(?:1[16]\\d|500|6(?:0\\d|3[12]|44|55|7[7-9]|88)|9[69][69])|7(?:[07]\\d\\d|1(?:11|78)))\\d{4}',
		'ExampleNumber'         => '17001234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:3(?:[0-79]\\d|8[0-57-9])\\d|6(?:3(?:00|33|6[16])|441|6(?:3[03-9]|[69]\\d|7[0-689])))\\d{4}',
		'ExampleNumber'         => '36001234',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '8[02369]\\d{6}',
		'ExampleNumber'         => '80123456',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '(?:87|9[0-8])\\d{6}',
		'ExampleNumber'         => '90123456',
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '84\\d{6}',
		'ExampleNumber'         => '84123456',
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
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'id'                            => 'BH',
	'countryCode'                   => 973,
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
				0 => '[13679]|8[02-4679]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
