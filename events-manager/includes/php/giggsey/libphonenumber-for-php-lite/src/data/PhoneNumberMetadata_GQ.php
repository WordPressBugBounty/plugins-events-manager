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
		'NationalNumberPattern' => '222\\d{6}|(?:3\\d|55|[89]0)\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '33[0-24-9]\\d[46]\\d{4}|3(?:33|5\\d)\\d[7-9]\\d{4}',
		'ExampleNumber'         => '333091234',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:222|55\\d)\\d{6}',
		'ExampleNumber'         => '222123456',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80\\d[1-9]\\d{5}',
		'ExampleNumber'         => '800123456',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '90\\d[1-9]\\d{5}',
		'ExampleNumber'         => '900123456',
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
	'id'                            => 'GQ',
	'countryCode'                   => 240,
	'internationalPrefix'           => '00',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[235]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{6})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[89]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
