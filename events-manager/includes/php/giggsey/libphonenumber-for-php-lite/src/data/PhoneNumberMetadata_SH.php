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
		'NationalNumberPattern' => '(?:[256]\\d|8)\\d{3}',
		'PossibleLength'        =>
		array(
			0 => 4,
			1 => 5,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '2(?:[0-57-9]\\d|6[4-9])\\d\\d',
		'ExampleNumber'         => '22158',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '[56]\\d{4}',
		'ExampleNumber'         => '51234',
		'PossibleLength'        =>
		array(
			0 => 5,
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
		'NationalNumberPattern' => '262\\d\\d',
		'ExampleNumber'         => '26212',
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
	'id'                            => 'SH',
	'countryCode'                   => 290,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => true,
	'leadingDigits'                 => '[256]',
	'mobileNumberPortableRegion'    => false,
);
