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
		'NationalNumberPattern' => '(?:[2-6]|8\\d{5})\\d{4}|[78]\\d{6}|[68]\\d{5}',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 6,
			2 => 7,
			3 => 10,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '6[1-9]\\d{3}|(?:[2-5]|60)\\d{4}',
		'ExampleNumber'         => '22123',
		'PossibleLength'        =>
		array(
			0 => 5,
			1 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:7[1-35-7]|8(?:[3-7]|9\\d{3}))\\d{5}',
		'ExampleNumber'         => '7212345',
		'PossibleLength'        =>
		array(
			0 => 7,
			1 => 10,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{3}',
		'ExampleNumber'         => '800123',
		'PossibleLength'        =>
		array(
			0 => 6,
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
	'id'                            => 'WS',
	'countryCode'                   => 685,
	'internationalPrefix'           => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{5})',
			'format'                               => '$1',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2-5]|6[1-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3,7})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[68]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{5})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '7',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
