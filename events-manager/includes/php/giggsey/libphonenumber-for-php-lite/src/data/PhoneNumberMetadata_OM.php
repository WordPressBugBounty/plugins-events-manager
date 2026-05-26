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
		'NationalNumberPattern' => '(?:1505|[279]\\d{3}|500)\\d{4}|800\\d{5,6}',
		'PossibleLength'        =>
		array(
			0 => 7,
			1 => 8,
			2 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '2[1-6]\\d{6}',
		'ExampleNumber'         => '23123456',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:1505|90[1-9]\\d)\\d{4}|(?:7[126-9]|9[1-9])\\d{6}',
		'ExampleNumber'         => '92123456',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '8007\\d{4,5}|(?:500|800[05])\\d{4}',
		'ExampleNumber'         => '80071234',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '900\\d{5}',
		'ExampleNumber'         => '90012345',
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
	'id'                            => 'OM',
	'countryCode'                   => 968,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{4,6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[58]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '2',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{4})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[179]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
