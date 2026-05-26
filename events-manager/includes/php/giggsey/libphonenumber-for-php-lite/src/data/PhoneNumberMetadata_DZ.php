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
		'NationalNumberPattern' => '(?:[1-4]|[5-79]\\d|80)\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 8,
			1 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '9619\\d{5}|(?:1\\d|2[013-79]|3[0-8]|4[013-689])\\d{6}',
		'ExampleNumber'         => '12345678',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:5(?:4[0-29]|5\\d|6[0-2])|6(?:[569]\\d|7[0-6])|7[7-9]\\d)\\d{6}',
		'ExampleNumber'         => '551234567',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{6}',
		'ExampleNumber'         => '800123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '80[3-689]1\\d{5}',
		'ExampleNumber'         => '808123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '80[12]1\\d{5}',
		'ExampleNumber'         => '801123456',
		'PossibleLength'        =>
		array(
			0 => 9,
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
		'NationalNumberPattern' => '98[23]\\d{6}',
		'ExampleNumber'         => '983123456',
		'PossibleLength'        =>
		array(
			0 => 9,
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
	'id'                            => 'DZ',
	'countryCode'                   => 213,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1-4]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '9',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[5-8]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
