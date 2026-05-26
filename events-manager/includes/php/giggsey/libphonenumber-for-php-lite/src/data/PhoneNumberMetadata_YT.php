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
		'NationalNumberPattern' => '(?:80|9\\d)\\d{7}|(?:26|63)9\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '269(?:0[0-467]|15|5[0-4]|6\\d|[78]0)\\d{4}',
		'ExampleNumber'         => '269601234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '639(?:0[0-79]|1[019]|[267]\\d|3[09]|40|5[05-9]|9[04-79])\\d{4}',
		'ExampleNumber'         => '639012345',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80\\d{7}',
		'ExampleNumber'         => '801234567',
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
		'NationalNumberPattern' => '9(?:(?:39|47)8[01]|769\\d)\\d{4}',
		'ExampleNumber'         => '939801234',
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
	'id'                            => 'YT',
	'countryCode'                   => 262,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
