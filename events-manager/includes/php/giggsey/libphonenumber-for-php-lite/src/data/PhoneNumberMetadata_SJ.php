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
		'NationalNumberPattern' => '0\\d{4}|(?:[489]\\d|79)\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '79\\d{6}',
		'ExampleNumber'         => '79123456',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:4[015-8]|9\\d)\\d{6}',
		'ExampleNumber'         => '41234567',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80[01]\\d{5}',
		'ExampleNumber'         => '80012345',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '82[09]\\d{5}',
		'ExampleNumber'         => '82012345',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '810(?:0[0-6]|[2-8]\\d)\\d{3}',
		'ExampleNumber'         => '81021234',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'personalNumber'                =>
	array(
		'NationalNumberPattern' => '880\\d{5}',
		'ExampleNumber'         => '88012345',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '85[0-5]\\d{5}',
		'ExampleNumber'         => '85012345',
		'PossibleLength'        =>
		array(
			0 => 8,
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
		'NationalNumberPattern' => '(?:0[2-9]|81(?:0(?:0[7-9]|1\\d)|5\\d\\d))\\d{3}',
		'ExampleNumber'         => '02000',
	),
	'voicemail'                     =>
	array(
		'NationalNumberPattern' => '81[23]\\d{5}',
		'ExampleNumber'         => '81212345',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'noInternationalDialling'       =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'id'                            => 'SJ',
	'countryCode'                   => 47,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'leadingDigits'                 => '79',
	'mobileNumberPortableRegion'    => false,
);
