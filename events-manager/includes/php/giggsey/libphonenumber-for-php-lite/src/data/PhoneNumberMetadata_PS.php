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
		'NationalNumberPattern'   => '[2489]2\\d{6}|(?:1\\d|5)\\d{8}',
		'PossibleLength'          =>
		array(
			0 => 8,
			1 => 9,
			2 => 10,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:22[2-47-9]|42[45]|82[014-68]|92[3569])\\d{5}',
		'ExampleNumber'           => '22234567',
		'PossibleLength'          =>
		array(
			0 => 8,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 7,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '5[69]\\d{7}',
		'ExampleNumber'         => '599123456',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '1800\\d{6}',
		'ExampleNumber'         => '1800123456',
		'PossibleLength'        =>
		array(
			0 => 10,
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
		'NationalNumberPattern' => '1700\\d{6}',
		'ExampleNumber'         => '1700123456',
		'PossibleLength'        =>
		array(
			0 => 10,
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
	'id'                            => 'PS',
	'countryCode'                   => 970,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d)(\\d{3})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2489]',
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
				0 => '5',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
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
