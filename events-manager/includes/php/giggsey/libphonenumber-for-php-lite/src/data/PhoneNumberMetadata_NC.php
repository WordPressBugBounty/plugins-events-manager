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
		'NationalNumberPattern' => '(?:050|[2-57-9]\\d\\d)\\d{3}',
		'PossibleLength'        =>
		array(
			0 => 6,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:2[03-9]|3[0-5]|4[1-7]|88)\\d{4}',
		'ExampleNumber'         => '201234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:5[0-4]|[79]\\d|8[0-79])\\d{4}',
		'ExampleNumber'         => '751234',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '050\\d{3}',
		'ExampleNumber'         => '050012',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '36\\d{4}',
		'ExampleNumber'         => '366711',
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
	'id'                            => 'NC',
	'countryCode'                   => 687,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})',
			'format'                               => '$1',
			'leadingDigitsPatterns'                =>
			array(
				0 => '5[6-8]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1.$2.$3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[02-57-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'intlNumberFormat'              =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1.$2.$3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[02-57-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
