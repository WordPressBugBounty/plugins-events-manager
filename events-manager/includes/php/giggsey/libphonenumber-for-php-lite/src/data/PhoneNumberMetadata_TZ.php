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
		'NationalNumberPattern' => '(?:[25-8]\\d|41|90)\\d{7}',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '2[2-8]\\d{7}',
		'ExampleNumber'         => '222345678',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '77[2-9]\\d{6}|(?:6[125-9]|7[13-689])\\d{7}',
		'ExampleNumber'         => '621234567',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80[08]\\d{6}',
		'ExampleNumber'         => '800123456',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '90\\d{7}',
		'ExampleNumber'         => '900123456',
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '8(?:40|6[01])\\d{6}',
		'ExampleNumber'         => '840123456',
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
		'NationalNumberPattern' => '41\\d{7}',
		'ExampleNumber'         => '412345678',
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
		'NationalNumberPattern' => '(?:8(?:[04]0|6[01])|90\\d)\\d{6}',
	),
	'id'                            => 'TZ',
	'countryCode'                   => 255,
	'internationalPrefix'           => '00[056]',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[89]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{3})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[24]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{2})(\\d{7})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '5',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		3 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})(\\d{3})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[67]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
