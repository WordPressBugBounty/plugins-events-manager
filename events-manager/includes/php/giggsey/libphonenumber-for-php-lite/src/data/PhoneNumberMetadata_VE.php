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
		'NationalNumberPattern'   => '[68]00\\d{7}|(?:[24]\\d|[59]0)\\d{8}',
		'PossibleLength'          =>
		array(
			0 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:2(?:12|3[457-9]|[467]\\d|[58][1-9]|9[1-6])|[4-6]00)\\d{7}',
		'ExampleNumber'           => '2121234567',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '4(?:1[24-8]|2[46])\\d{7}',
		'ExampleNumber'         => '4121234567',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{7}',
		'ExampleNumber'         => '8001234567',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '90[01]\\d{7}',
		'ExampleNumber'         => '9001234567',
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
		'NationalNumberPattern'   => '501\\d{7}',
		'ExampleNumber'           => '5010123456',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
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
	'id'                            => 'VE',
	'countryCode'                   => 58,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{7})',
			'format'                               => '$1-$2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[24-689]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '$CC $1',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
