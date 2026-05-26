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
		'NationalNumberPattern' => '(?:[25-79]\\d\\d|800)\\d{4}',
		'PossibleLength'        =>
		array(
			0 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '5(?:2\\d|8[1-9])\\d{4}',
		'ExampleNumber'         => '5212345',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:290|5[69]\\d|6(?:[03]0|22|4[0-2]|[69]\\d)|7(?:[34]\\d|7[07])|9(?:6[45]|9[4-8]))\\d{4}',
		'ExampleNumber'         => '5601234',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{4}',
		'ExampleNumber'         => '8001234',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '900\\d{4}',
		'ExampleNumber'         => '9001234',
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
		'NationalNumberPattern' => '(?:28\\d|501)\\d{4}',
		'ExampleNumber'         => '5011234',
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
	'id'                            => 'AW',
	'countryCode'                   => 297,
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
				0 => '[25-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
