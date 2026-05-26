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
		'NationalNumberPattern' => '[56]94\\d{6}|(?:80|9\\d)\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '594(?:[02-49]\\d|1[0-5]|5[6-9]|6[0-3]|80)\\d{4}',
		'ExampleNumber'         => '594101234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '694(?:[0-249]\\d|3[0-8])\\d{4}',
		'ExampleNumber'         => '694201234',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80[0-5]\\d{6}',
		'ExampleNumber'         => '800012345',
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
		'NationalNumberPattern' => '9(?:(?:396|76\\d)\\d|476[0-5])\\d{4}',
		'ExampleNumber'         => '976012345',
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
	'id'                            => 'GF',
	'countryCode'                   => 594,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[56]|9[47]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[89]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
