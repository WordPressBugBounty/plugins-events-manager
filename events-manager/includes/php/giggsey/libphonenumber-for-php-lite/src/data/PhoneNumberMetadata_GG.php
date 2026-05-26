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
		'NationalNumberPattern'   => '(?:1481|[357-9]\\d{3})\\d{6}|8\\d{6}(?:\\d{2})?',
		'PossibleLength'          =>
		array(
			0 => 7,
			1 => 9,
			2 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '1481[25-9]\\d{5}',
		'ExampleNumber'           => '1481256789',
		'PossibleLength'          =>
		array(
			0 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '7(?:(?:781|839)\\d|911[17])\\d{5}',
		'ExampleNumber'         => '7781123456',
		'PossibleLength'        =>
		array(
			0 => 10,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80[08]\\d{7}|800\\d{6}|8001111',
		'ExampleNumber'         => '8001234567',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '(?:8(?:4[2-5]|7[0-3])|9(?:[01]\\d|8[0-3]))\\d{7}|845464\\d',
		'ExampleNumber'         => '9012345678',
		'PossibleLength'        =>
		array(
			0 => 7,
			1 => 10,
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
		'NationalNumberPattern' => '70\\d{8}',
		'ExampleNumber'         => '7012345678',
		'PossibleLength'        =>
		array(
			0 => 10,
		),
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '56\\d{8}',
		'ExampleNumber'         => '5612345678',
		'PossibleLength'        =>
		array(
			0 => 10,
		),
	),
	'pager'                         =>
	array(
		'NationalNumberPattern' => '76(?:464|652)\\d{5}|76(?:0[0-28]|2[356]|34|4[01347]|5[49]|6[0-369]|77|8[14]|9[139])\\d{6}',
		'ExampleNumber'         => '7640123456',
		'PossibleLength'        =>
		array(
			0 => 10,
		),
	),
	'uan'                           =>
	array(
		'NationalNumberPattern' => '(?:3[0347]|55)\\d{8}',
		'ExampleNumber'         => '5512345678',
		'PossibleLength'        =>
		array(
			0 => 10,
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
	'id'                            => 'GG',
	'countryCode'                   => 44,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '([25-9]\\d{5})$|0',
	'nationalPrefixTransformRule'   => '1481$1',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
