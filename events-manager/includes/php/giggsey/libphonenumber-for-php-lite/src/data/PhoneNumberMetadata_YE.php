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
		'NationalNumberPattern'   => '(?:1|7\\d)\\d{7}|[1-7]\\d{6}',
		'PossibleLength'          =>
		array(
			0 => 7,
			1 => 8,
			2 => 9,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '78[0-7]\\d{4}|17\\d{6}|(?:[12][2-68]|3[2358]|4[2-58]|5[2-6]|6[3-58]|7[24-6])\\d{5}',
		'ExampleNumber'           => '1234567',
		'PossibleLength'          =>
		array(
			0 => 7,
			1 => 8,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '7[01378]\\d{7}',
		'ExampleNumber'         => '712345678',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
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
	'id'                            => 'YE',
	'countryCode'                   => 967,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d)(\\d{3})(\\d{3,4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1-6]|7(?:[24-6]|8[0-7])',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '7',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
