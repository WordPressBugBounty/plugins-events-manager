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
		'NationalNumberPattern' => '722\\d{6}|(?:3|6\\d)\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 8,
			1 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '3(?:0(?:24|3[12]|4[1-35-7]|5[13]|6[189]|[78]1|9[1478])|1\\d\\d)\\d{4}',
		'ExampleNumber'         => '30241234',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '6[0-356]\\d{7}',
		'ExampleNumber'         => '601123456',
		'PossibleLength'        =>
		array(
			0 => 9,
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
		'NationalNumberPattern' => '722\\d{6}',
		'ExampleNumber'         => '722123456',
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
	'id'                            => 'GN',
	'countryCode'                   => 224,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '3',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[67]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
