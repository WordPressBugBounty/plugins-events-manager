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
		'NationalNumberPattern'   => '[13]\\d{5}',
		'PossibleLength'          =>
		array(
			0 => 6,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 5,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern'   => '(?:1(?:06|17|28|39)|3[0-2]\\d)\\d{3}',
		'ExampleNumber'           => '106609',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 5,
		),
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern'   => '(?:14|3[58])\\d{4}',
		'ExampleNumber'           => '381234',
		'PossibleLengthLocalOnly' =>
		array(
			0 => 5,
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
	'id'                            => 'NF',
	'countryCode'                   => 672,
	'internationalPrefix'           => '00',
	'nationalPrefixForParsing'      => '([0-258]\\d{4})$',
	'nationalPrefixTransformRule'   => '3$1',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '1[0-3]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d)(\\d{5})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[13]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
