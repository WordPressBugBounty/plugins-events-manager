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
		'NationalNumberPattern' => '(?:[3469]\\d|52|[78]0)\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:3[1478]|4[124-6]|52)\\d{6}',
		'ExampleNumber'         => '31234567',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '6\\d{7}',
		'ExampleNumber'         => '61234567',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80[02]\\d{5}',
		'ExampleNumber'         => '80012345',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '9(?:0[0239]|10)\\d{5}',
		'ExampleNumber'         => '90012345',
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '808\\d{5}',
		'ExampleNumber'         => '80812345',
	),
	'personalNumber'                =>
	array(
		'NationalNumberPattern' => '70[05]\\d{5}',
		'ExampleNumber'         => '70012345',
	),
	'voip'                          =>
	array(
		'NationalNumberPattern' => '[89]01\\d{5}',
		'ExampleNumber'         => '80123456',
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
		'NationalNumberPattern' => '70[67]\\d{5}',
		'ExampleNumber'         => '70712345',
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
	'id'                            => 'LT',
	'countryCode'                   => 370,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '8',
	'nationalPrefixForParsing'      => '[08]',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d)(\\d{3})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '52[0-7]',
			),
			'nationalPrefixFormattingRule'         => '(8-$1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => true,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[7-9]',
			),
			'nationalPrefixFormattingRule'         => '8 $1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => true,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '37|4(?:[15]|6[1-8])',
			),
			'nationalPrefixFormattingRule'         => '(8-$1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => true,
		),
		3 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{5})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[3-6]',
			),
			'nationalPrefixFormattingRule'         => '(8-$1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => true,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
