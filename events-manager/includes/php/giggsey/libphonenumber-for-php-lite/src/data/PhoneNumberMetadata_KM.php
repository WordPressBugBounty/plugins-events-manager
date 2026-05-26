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
		'NationalNumberPattern'   => '[3478]\\d{6}',
		'PossibleLength'          =>
		array(
			0 => 7,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 4,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '7[4-7]\\d{5}',
		'ExampleNumber'           => '7712345',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 4,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '[34]\\d{6}',
		'ExampleNumber'         => '3212345',
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
		'NationalNumberPattern' => '8\\d{6}',
		'ExampleNumber'         => '8001234',
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
	'id'                            => 'KM',
	'countryCode'                   => 269,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[3478]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
