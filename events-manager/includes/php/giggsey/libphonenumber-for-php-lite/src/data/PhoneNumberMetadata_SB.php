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
		'NationalNumberPattern' => '(?:[1-6]|[7-9]\\d\\d)\\d{4}',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:1[4-79]|[23]\\d|4[0-2]|5[03]|6[0-37])\\d{3}',
		'ExampleNumber'         => '40123',
		'PossibleLength'        =>
		array(
			0 => 5,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '48\\d{3}|(?:(?:7[1-9]|8[4-9])\\d|9(?:1[2-9]|2[013-9]|3[0-2]|[46]\\d|5[0-46-9]|7[0-689]|8[0-79]|9[0-8]))\\d{4}',
		'ExampleNumber'         => '7421234',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '1[38]\\d{3}',
		'ExampleNumber'         => '18123',
		'PossibleLength'        =>
		array(
			0 => 5,
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
		'NationalNumberPattern' => '5[12]\\d{3}',
		'ExampleNumber'         => '51123',
		'PossibleLength'        =>
		array(
			0 => 5,
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
	'id'                            => 'SB',
	'countryCode'                   => 677,
	'internationalPrefix'           => '0[01]',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{5})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '7|8[4-9]|9(?:[1-8]|9[0-8])',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
