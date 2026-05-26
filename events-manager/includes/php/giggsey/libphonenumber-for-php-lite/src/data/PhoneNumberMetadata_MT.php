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
		'NationalNumberPattern' => '3550\\d{4}|(?:[2579]\\d\\d|800)\\d{5}',
		'PossibleLength'        =>
		array(
			0 => 8,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '20(?:3[1-4]|6[059])\\d{4}|2(?:0[19]|[1-357]\\d|60)\\d{5}',
		'ExampleNumber'         => '21001234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:7(?:210|[79]\\d\\d)|9(?:[29]\\d\\d|69[67]|8(?:1[1-3]|89|97)))\\d{4}',
		'ExampleNumber'         => '96961234',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '800(?:02|[3467]\\d)\\d{3}',
		'ExampleNumber'         => '80071234',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '5(?:0(?:0(?:37|43)|(?:6\\d|70|9[0168])\\d)|[12]\\d0[1-5])\\d{3}',
		'ExampleNumber'         => '50037123',
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
		'NationalNumberPattern' => '3550\\d{4}',
		'ExampleNumber'         => '35501234',
	),
	'pager'                         =>
	array(
		'NationalNumberPattern' => '7117\\d{4}',
		'ExampleNumber'         => '71171234',
	),
	'uan'                           =>
	array(
		'NationalNumberPattern' => '501\\d{5}',
		'ExampleNumber'         => '50112345',
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
	'id'                            => 'MT',
	'countryCode'                   => 356,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{4})(\\d{4})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[2357-9]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
