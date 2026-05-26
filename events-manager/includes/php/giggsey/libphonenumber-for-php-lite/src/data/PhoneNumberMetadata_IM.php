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
		'NationalNumberPattern'   => '1624\\d{6}|(?:[3578]\\d|90)\\d{8}',
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
		'NationalNumberPattern'   => '1624(?:230|[5-8]\\d\\d)\\d{3}',
		'ExampleNumber'           => '1624756789',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '76245[06]\\d{4}|7(?:4576|[59]24\\d|624[0-4689])\\d{5}',
		'ExampleNumber'         => '7924123456',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '808162\\d{4}',
		'ExampleNumber'         => '8081624567',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '8(?:440[49]06|72299\\d)\\d{3}|(?:8(?:45|70)|90[0167])624\\d{4}',
		'ExampleNumber'         => '9016247890',
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
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '56\\d{8}',
		'ExampleNumber'         => '5612345678',
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
		'NationalNumberPattern' => '3440[49]06\\d{3}|(?:3(?:08162|3\\d{4}|45624|7(?:0624|2299))|55\\d{4})\\d{4}',
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
	'id'                            => 'IM',
	'countryCode'                   => 44,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '([25-8]\\d{5})$|0',
	'nationalPrefixTransformRule'   => '1624$1',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'leadingDigits'                 => '74576|(?:16|7[56])24',
	'mobileNumberPortableRegion'    => false,
);
