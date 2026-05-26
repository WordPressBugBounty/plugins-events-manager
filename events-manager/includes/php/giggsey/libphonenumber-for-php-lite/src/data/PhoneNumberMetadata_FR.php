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
		'NationalNumberPattern' => '[1-9]\\d{8}',
		'PossibleLength'        =>
		array(
			0 => 9,
		),
	),
	'fixedLine'                     =>
	array(
		'NationalNumberPattern' => '(?:26[013-9]|59[1-35-9])\\d{6}|(?:[13]\\d|2[0-57-9]|4[1-9]|5[0-8])\\d{7}',
		'ExampleNumber'         => '123456789',
	),
	'mobile'                        =>
	array(
		'NationalNumberPattern' => '(?:6(?:[0-24-8]\\d|3[0-8]|9[589])|7[3-9]\\d)\\d{6}',
		'ExampleNumber'         => '612345678',
	),
	'tollFree'                      =>
	array(
		'NationalNumberPattern' => '80[0-5]\\d{6}',
		'ExampleNumber'         => '801234567',
	),
	'premiumRate'                   =>
	array(
		'NationalNumberPattern' => '836(?:0[0-36-9]|[1-9]\\d)\\d{4}|8(?:1[2-9]|2[2-47-9]|3[0-57-9]|[569]\\d|8[0-35-9])\\d{6}',
		'ExampleNumber'         => '891123456',
	),
	'sharedCost'                    =>
	array(
		'NationalNumberPattern' => '8(?:1[01]|2[0156]|4[02]|84)\\d{6}',
		'ExampleNumber'         => '884012345',
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
		'NationalNumberPattern' => '9\\d{8}',
		'ExampleNumber'         => '912345678',
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
		'NationalNumberPattern' => '80[6-9]\\d{6}',
		'ExampleNumber'         => '806123456',
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
	'id'                            => 'FR',
	'countryCode'                   => 33,
	'internationalPrefix'           => '00',
	'nationalPrefix'                => '0',
	'nationalPrefixForParsing'      => '0',
	'sameMobileAndFixedLinePattern' => false,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{4})',
			'format'                               => '$1',
			'leadingDigitsPatterns'                =>
			array(
				0 => '10',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{3})',
			'format'                               => '$1 $2',
			'leadingDigitsPatterns'                =>
			array(
				0 => '1',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		2 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '8',
			),
			'nationalPrefixFormattingRule'         => '0 $1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		3 =>
		array(
			'pattern'                              => '(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4 $5',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1-79]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'intlNumberFormat'              =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4',
			'leadingDigitsPatterns'                =>
			array(
				0 => '8',
			),
			'nationalPrefixFormattingRule'         => '0 $1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
		1 =>
		array(
			'pattern'                              => '(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2})',
			'format'                               => '$1 $2 $3 $4 $5',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1-79]',
			),
			'nationalPrefixFormattingRule'         => '0$1',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => true,
);
