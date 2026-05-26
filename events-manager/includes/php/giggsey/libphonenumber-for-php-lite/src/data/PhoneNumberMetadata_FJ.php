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
		'NationalNumberPattern' => '45\\d{5}|(?:0800\\d|[235-9])\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 7,
			1 => 11,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '603\\d{4}|(?:3[0-5]|6[25-7]|8[58])\\d{5}',
		'ExampleNumber'         => '3212345',
		'PossibleLength'        =>
		array(
			0 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:[279]\\d|45|5[01568]|8[034679])\\d{5}',
		'ExampleNumber'         => '7012345',
		'PossibleLength'        =>
		array(
			0 => 7,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '0800\\d{7}',
		'ExampleNumber'         => '08001234567',
		'PossibleLength'        =>
		array(
			0 => 11,
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
	'id'                            => 'FJ',
	'countryCode'                   => 679,
	'internationalPrefix'           => '0(?:0|52)',
	'preferredInternationalPrefix'  => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[235-9]|45',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{4})(\\d{3})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '0',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
