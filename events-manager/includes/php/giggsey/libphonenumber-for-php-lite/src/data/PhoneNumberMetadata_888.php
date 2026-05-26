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
		'NationalNumberPattern' => '\\d{11}',
		'PossibleLength'        =>
		array(
			0 => 11,
		),
	),
	'fixedLine'                     =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
		),
	),
	'mobile'                        =>
	array(
		'PossibleLength' =>
		array(
			0 => -1,
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
		'NationalNumberPattern' => '\\d{11}',
		'ExampleNumber'         => '12345678901',
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
	'id'                            => '001',
	'countryCode'                   => 888,
	'internationalPrefix'           => '',
	'sameMobileAndFixedLinePattern' => true,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{5})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
