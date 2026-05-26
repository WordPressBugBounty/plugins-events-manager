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
		'NationalNumberPattern'   => '[235-7]\\d{8}|[1-9]\\d{7}',
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
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:1\\d|[27][2-9]|3[2-7]|4[24-9]|5[2-79]|6[23689]|8[2-57-9]|9[2-69])\\d{6}',
		'ExampleNumber'           => '12345678',
		'PossibleLength'          =>
		array(
			0 => 8,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
			1 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:[257]0|3[01])\\d{7}',
		'ExampleNumber'         => '201234567',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '(?:[48]0\\d|680[29])\\d{5}',
		'ExampleNumber'         => '80123456',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '9[01]\\d{6}',
		'ExampleNumber'         => '90123456',
		'PossibleLength'        =>
		array(
			0 => 8,
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
		'NationalNumberPattern' => '21\\d{7}',
		'ExampleNumber'         => '211234567',
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
		'NationalNumberPattern' => '38\\d{7}',
		'ExampleNumber'         => '381234567',
		'PossibleLength'        =>
		array(
			0 => 9,
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
		'NationalNumberPattern' => '(?:[48]0\\d|680[29])\\d{5}',
	),
	'id'                            => 'HU',
	'countryCode'                   => 36,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '06',
	'nationalPrefixForParsing'      => '06',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d)(\\d{3})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '1',
			),
			'nationalPrefixFormattingRule'         => '(06 $1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[27][2-9]|3[2-7]|4[24-9]|5[2-79]|6|8[2-57-9]|9[2-69]',
			),
			'nationalPrefixFormattingRule'         => '(06 $1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{3,4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2-9]',
			),
			'nationalPrefixFormattingRule'         => '06 $1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
