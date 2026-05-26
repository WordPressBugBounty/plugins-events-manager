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
		'NationalNumberPattern'   => '1534\\d{6}|(?:[3578]\\d|90)\\d{8}',
		'PossibleLength'          =>
		array(
			0 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '1534[0-24-8]\\d{5}',
		'ExampleNumber'           => '1534456789',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '7(?:(?:(?:50|82)9|937)\\d|7(?:00[378]|97\\d))\\d{5}',
		'ExampleNumber'         => '7797712345',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80(?:07(?:35|81)|8901)\\d{4}',
		'ExampleNumber'         => '8007354567',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '(?:8(?:4(?:4(?:4(?:05|42|69)|703)|5(?:041|800))|7(?:0002|1206))|90(?:066[59]|1810|71(?:07|55)))\\d{4}',
		'ExampleNumber'         => '9018105678',
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
		'NationalNumberPattern' => '701511\\d{4}',
		'ExampleNumber'         => '7015115678',
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '56\\d{8}',
		'ExampleNumber'         => '5612345678',
	),
	'pager'                         =>
	array(
		'NationalNumberPattern' => '76(?:464|652)\\d{5}|76(?:0[0-28]|2[356]|34|4[01347]|5[49]|6[0-369]|77|8[14]|9[139])\\d{6}',
		'ExampleNumber'         => '7640123456',
	),
	'uan'                           =>
	array(
		'NationalNumberPattern' => '(?:3(?:0(?:07(?:35|81)|8901)|3\\d{4}|4(?:4(?:4(?:05|42|69)|703)|5(?:041|800))|7(?:0002|1206))|55\\d{4})\\d{4}',
		'ExampleNumber'         => '5512345678',
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
	'id'                            => 'JE',
	'countryCode'                   => 44,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '([0-24-8]\\d{5})$|0',
	'nationalPrefixTransformRule'   => '1534$1',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
