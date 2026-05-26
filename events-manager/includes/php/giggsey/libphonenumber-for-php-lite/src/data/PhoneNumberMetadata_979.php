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
		'NationalNumberPattern'   => '[1359]\\d{8}',
		'PossibleLength'          =>
		array(
			0 => 9,
		),
		'PossibleLengthLocalOnly' =>
		array(
			0 => 8,
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
		'NationalNumberPattern'   => '[1359]\\d{8}',
		'ExampleNumber'           => '123456789',
		'PossibleLengthLocalOnly' =>
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
	'id'                            => '001',
	'countryCode'                   => 979,
	'internationalPrefix'           => '',
	'sameMobileAndFixedLinePattern' => true,
	'numberFormat'                  =>
	array(
		0 =>
		array(
			'pattern'                              => '(\\d)(\\d{4})(\\d{4})',
			'format'                               => '$1 $2 $3',
			'leadingDigitsPatterns'                =>
			array(
				0 => '[1359]',
			),
			'nationalPrefixFormattingRule'         => '',
			'domesticCarrierCodeFormattingRule'    => '',
			'nationalPrefixOptionalWhenFormatting' => false,
		),
	),
	'mainCountryForCode'            => false,
	'mobileNumberPortableRegion'    => false,
);
