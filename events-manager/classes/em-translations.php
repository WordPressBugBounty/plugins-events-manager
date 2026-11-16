<?php
namespace EM;

/**
 * Loads the translation files a plugin ships in its own languages folder on top of any wordpress.org language pack.
 *
 * WordPress only loads the first file it finds for a text domain and looks in wp-content/languages before the plugin's folder, so on its own a bundled file is ignored wherever a language pack exists. Here the language pack loads first and the bundled file after it; the first file loaded wins each string, so volunteer and site-owner translations keep precedence and the bundled file fills the strings they lack.
 */
class Translations {

	/**
	 * @var string[] Text domain => folder holding that domain's bundled translation files.
	 */
	public static $folders = array();

	/**
	 * @var bool
	 */
	protected static $loading = false;

	/**
	 * @param string $domain
	 * @param string $folder
	 */
	public static function register( $domain, $folder ) {
		global $wp_textdomain_registry;
		static::$folders[ $domain ] = untrailingslashit( $folder );
		// This is all load_plugin_textdomain() does since WP 6.7: just-in-time loading then finds the bundled file for locales without a language pack, and nothing is loaded this early.
		if ( $wp_textdomain_registry instanceof \WP_Textdomain_Registry ) {
			$wp_textdomain_registry->set_custom_path( $domain, static::$folders[ $domain ] );
		}
		add_filter( 'override_load_textdomain', array( static::class, 'override_load_textdomain' ), 10, 4 );
	}

	/**
	 * Whenever WordPress loads a registered text domain, loads the file it asked for and then the bundled file for the same locale.
	 *
	 * @param bool        $override
	 * @param string      $domain
	 * @param string      $mofile
	 * @param string|null $locale Not passed before WP 6.2.
	 * @return bool
	 */
	public static function override_load_textdomain( $override, $domain, $mofile, $locale = null ) {
		if ( $override || static::$loading || empty( static::$folders[ $domain ] ) ) {
			return $override;
		}
		$locale            = $locale ? $locale : determine_locale();
		$bundled           = static::$folders[ $domain ] . '/' . $domain . '-' . $locale . '.mo';
		$asked_for_bundled = wp_normalize_path( $mofile ) === wp_normalize_path( $bundled );
		$load_bundled      = false !== static::get_file( $domain, $locale );
		if ( ! $asked_for_bundled && ! $load_bundled ) {
			return $override;
		}
		// WordPress asks for the bundled file once it was the last file loaded for a locale, and WP 6.2 to 6.4 ask for it over a language pack whenever both exist, so the language pack goes back in front.
		$files = array( $asked_for_bundled ? WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo' : $mofile );
		if ( $load_bundled ) {
			$files[] = $bundled;
		}
		static::$loading = true;
		$loaded          = false;
		foreach ( $files as $file ) {
			$loaded = load_textdomain( $domain, $file, $locale ) || $loaded;
		}
		static::$loading = false;
		// Returning false lets WordPress go on to load the file it asked for, which would bring back a bundled file that was turned off.
		return $loaded || $asked_for_bundled;
	}

	/**
	 * @param string $domain
	 * @param string $locale
	 * @return string|false Path of the bundled .mo file for the locale, or false if there is none or bundled files are turned off.
	 */
	public static function get_file( $domain, $locale ) {
		if ( empty( static::$folders[ $domain ] ) ) {
			return false;
		}
		/**
		 * Filters whether to load the translation file bundled with the plugin, on top of any wordpress.org language pack.
		 *
		 * @param bool   $load   Default true.
		 * @param string $domain Text domain.
		 * @param string $locale Locale being loaded.
		 */
		if ( ! apply_filters( 'em_bundled_translations', true, $domain, $locale ) ) {
			return false;
		}
		$file = static::$folders[ $domain ] . '/' . $domain . '-' . $locale . '.mo';
		// From WP 6.5 load_textdomain() prefers a .l10n.php file beside the .mo path it is given, so either one counts.
		if ( is_readable( $file ) || is_readable( substr( $file, 0, -3 ) . '.l10n.php' ) ) {
			return $file;
		}
		return false;
	}
}

Translations::register( 'events-manager', EM_DIR . '/languages' );
