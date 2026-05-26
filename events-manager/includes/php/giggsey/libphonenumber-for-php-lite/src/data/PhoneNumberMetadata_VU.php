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
		'NationalNumberPattern' => '[57-9]\\d{6}|(?:[238]\\d|48)\\d{3}',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:38[0-8]|48[4-9])\\d\\d|(?:2[02-9]|3[4-7]|88)\\d{3}',
		'ExampleNumber'         => '22123',
		'PossibleLength'        =>
		array(
			0 => 5,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:[58]\\d|7[013-7])\\d{5}',
		'ExampleNumber'         => '5912345',
		'PossibleLength'        =>
		array(
			0 => 7,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '81[18]\\d\\d',
		'ExampleNumber'         => '81123',
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
		'NationalNumberPattern' => '9(?:0[1-9]|1[01])\\d{4}',
		'ExampleNumber'         => '9010123',
		'PossibleLength'        =>
		array(
			0 => 7,
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
		'NationalNumberPattern' => '(?:3[03]|900\\d)\\d{3}',
		'ExampleNumber'         => '30123',
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
	'id'                            => 'VU',
	'countryCode'                   => 678,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[57-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
