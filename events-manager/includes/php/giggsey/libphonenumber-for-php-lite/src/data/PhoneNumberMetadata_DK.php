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
		'NationalNumberPattern' => '[2-9]\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:[2-7]\\d|8[126-9]|9[1-46-9])\\d{6}',
		'ExampleNumber'         => '32123456',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:[2-7]\\d|8[126-9]|9[1-46-9])\\d{6}',
		'ExampleNumber'         => '32123456',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80\\d{6}',
		'ExampleNumber'         => '80123456',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '90\\d{6}',
		'ExampleNumber'         => '90123456',
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
	'id'                            => 'DK',
	'countryCode'                   => 45,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => true,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
