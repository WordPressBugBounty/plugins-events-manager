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
		'NationalNumberPattern'   => '(?:[589]\\d\\d|787)\\d{7}',
		'PossibleLength'          =>
		array(
			0 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:787|939)[2-9]\\d{6}',
		'ExampleNumber'           => '7872345678',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern'   => '(?:787|939)[2-9]\\d{6}',
		'ExampleNumber'           => '7872345678',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '8(?:00|33|44|55|66|77|88)[2-9]\\d{6}',
		'ExampleNumber'         => '8002345678',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '900[2-9]\\d{6}',
		'ExampleNumber'         => '9002345678',
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
		'NationalNumberPattern' => '52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[125-9]|33|44|66|77|88)[2-9]\\d{6}',
		'ExampleNumber'         => '5002345678',
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
	'id'                            => 'PR',
	'countryCode'                   => 1,
	'internationalPrefix'           => '011',
	'nationalPrefix'                => '1',
	'nationalPrefixForParsing'      => '1',
	'sameMobileAndFixedLinePattern' => true,
	'numberFormat'                  =>
	array(),
	'mainCountryForCode'            => false,
	'leadingDigits'                 => '787|939',
	'mobileNumberPortableRegion'    => true,
);
