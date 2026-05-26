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
		'NationalNumberPattern'   => '1\\d{9}|[1-9]\\d{7,8}',
		'PossibleLength'          =>
		array(
			0 => 8,
			1 => 9,
			2 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
			1 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '23(?:4(?:[2-4]|[56]\\d)|[568]\\d\\d)\\d{4}|23[236-9]\\d{5}|(?:2[4-6]|3[2-6]|4[2-4]|[5-7][2-5])(?:(?:[237-9]|4[56]|5\\d)\\d{5}|6\\d{5,6})',
		'ExampleNumber'           => '23756789',
		'PossibleLength'          =>
		array(
			0 => 8,
			1 => 9,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
			1 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:(?:1[28]|3[18]|9[67])\\d|6[016-9]|7(?:[07-9]|[16]\\d)|8(?:[013-79]|8\\d))\\d{6}|(?:1\\d|9[0-57-9])\\d{6}|(?:2[3-6]|3[2-6]|4[2-4]|[5-7][2-5])48\\d{5}',
		'ExampleNumber'         => '91234567',
		'PossibleLength'        =>
		array(
			0 => 8,
			1 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '1800(?:1\\d|2[019])\\d{4}',
		'ExampleNumber'         => '1800123456',
		'PossibleLength'        =>
		array(
			0 => 10,
		),
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '1900(?:1\\d|2[09])\\d{4}',
		'ExampleNumber'         => '1900123456',
		'PossibleLength'        =>
		array(
			0 => 10,
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
	'id'                            => 'KH',
	'countryCode'                   => 855,
	'internationalPrefix'           => '00[14-9]',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3,4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1-9]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{4})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '1',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
