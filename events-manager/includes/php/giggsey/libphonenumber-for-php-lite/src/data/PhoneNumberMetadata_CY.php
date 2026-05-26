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
		'NationalNumberPattern' => '(?:[279]\\d|[58]0)\\d{6}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '2[2-6]\\d{6}',
		'ExampleNumber'         => '22345678',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '9(?:10|[4-79]\\d)\\d{5}',
		'ExampleNumber'         => '96123456',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800\\d{5}',
		'ExampleNumber'         => '80001234',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '90[09]\\d{5}',
		'ExampleNumber'         => '90012345',
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '80[1-9]\\d{5}',
		'ExampleNumber'         => '80112345',
	),
	'personalNumber'                =>
	array(
		'NationalNumberPattern' => '700\\d{5}',
		'ExampleNumber'         => '70012345',
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
		'NationalNumberPattern' => '(?:50|77)\\d{6}',
		'ExampleNumber'         => '77123456',
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
	'id'                            => 'CY',
	'countryCode'                   => 357,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[257-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
