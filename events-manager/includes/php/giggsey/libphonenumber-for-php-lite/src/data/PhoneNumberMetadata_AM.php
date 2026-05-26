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
		'NationalNumberPattern'   => '(?:[1-489]\\d|55|60|77)\\d{6}',
		'PossibleLength'          =>
		array(
			0 => 8,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 5,
			1 => 6,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:(?:1[0-25]|47)\\d|2(?:2[2-46]|3[1-8]|4[2-69]|5[2-7]|6[1-9]|8[1-7])|3[12]2)\\d{5}',
		'ExampleNumber'           => '10123456',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 5,
			1 => 6,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:33|4[1349]|55|77|88|9[13-9])\\d{6}',
		'ExampleNumber'         => '77123456',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{5}',
		'ExampleNumber'         => '80012345',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '90[016]\\d{5}',
		'ExampleNumber'         => '90012345',
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '80[1-4]\\d{5}',
		'ExampleNumber'         => '80112345',
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
		'NationalNumberPattern' => '60(?:2[78]|3[5-9]|4[02-9]|5[0-46-9]|[6-8]\\d|9[0-2])\\d{4}',
		'ExampleNumber'         => '60271234',
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
	'id'                            => 'AM',
	'countryCode'                   => 374,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[89]0',
			),
			'nationalPrefixFormattingRule'         => '0 $1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{5})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '2|3[12]',
			),
			'nationalPrefixFormattingRule'         => '(0$1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '1|47',
			),
			'nationalPrefixFormattingRule'         => '(0$1)',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		3 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[3-9]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
