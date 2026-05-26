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
		'NationalNumberPattern' => '2\\d{4,9}|35\\d{4,5}|(?:60\\d\\d|800)\\d{4,6}|7\\d{5,11}|(?:[14]\\d|3[0-46-9]|50)\\d{4,8}',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 6,
			2 => 7,
			3 => 8,
			4 => 9,
			5 => 10,
			6 => 11,
			7 => 12,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '18[1-8]\\d{3,6}',
		'ExampleNumber'         => '181234567',
		'PossibleLength'        =>
		array(
			0 => 6,
			1 => 7,
			2 => 8,
			3 => 9,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '4946\\d{2,6}|(?:4[0-8]|50)\\d{4,8}',
		'ExampleNumber'         => '412345678',
		'PossibleLength'        =>
		array(
			0 => 6,
			1 => 7,
			2 => 8,
			3 => 9,
			4 => 10,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{4,6}',
		'ExampleNumber'         => '800123456',
		'PossibleLength'        =>
		array(
			0 => 7,
			1 => 8,
			2 => 9,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '[67]00\\d{5,6}',
		'ExampleNumber'         => '600123456',
		'PossibleLength'        =>
		array(
			0 => 8,
			1 => 9,
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
		'NationalNumberPattern' => '20\\d{4,8}|60[12]\\d{5,6}|7(?:099\\d{4,5}|5[03-9]\\d{3,7})|20[2-59]\\d\\d|(?:606|7(?:0[78]|1|3\\d))\\d{7}|(?:10|29|3[09]|70[1-5]\\d)\\d{4,8}',
		'ExampleNumber'         => '10112345',
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
	'id'                            => 'AX',
	'countryCode'                   => 358,
	'internationalPrefix'           => '00|99(?:[01469]|5(?:[14]1|3[23]|5[59]|77|88|9[09]))',
	'preferredInternationalPrefix'  => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'leadingDigits'                 => '18',
	'mobileNumberPortableRegion'    => false,
);
