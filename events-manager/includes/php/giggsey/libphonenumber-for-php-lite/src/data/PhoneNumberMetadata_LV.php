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
		'NationalNumberPattern' => '(?:[268]\\d|90)\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '6\\d{7}',
		'ExampleNumber'         => '63123456',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '23(?:23[0-57-9]|33[0238])\\d{3}|2(?:[0-24-9]\\d\\d|3(?:0[07]|[14-9]\\d|2[024-9]|3[0-24-9]))\\d{4}',
		'ExampleNumber'         => '21234567',
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
		'NationalNumberPattern' => '81\\d{6}',
		'ExampleNumber'         => '81123456',
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
	'id'                            => 'LV',
	'countryCode'                   => 371,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[269]|8[01]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
