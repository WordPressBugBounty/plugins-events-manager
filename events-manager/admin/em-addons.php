<?php
namespace EM\Admin;

/**
 * The Events > Add-ons screen: the add-ons sold on eventsmanagerpro.com, and where each one stands on this site.
 *
 * The list, its prices, the cards' pictures and any promotion notice come from eventsmanagerpro.com when an administrator opens this screen, at most once a day plus one manual refresh, and nowhere else in the plugin. It is a standard WordPress request, so the store sees the site address in its user agent and nothing more. The readme discloses this under External services.
 */
class Addons {

	const FEED = 'https://eventsmanagerpro.com/api/add-ons/';
	const HOSTS = array( 'eventsmanagerpro.com', 'wp-events-plugin.com', 'pxlink.cc' );
	const OPTION = 'dbem_addons_catalogue';
	const VERSION = 1;

	public static function init() {
		add_filter( 'em_create_events_submenu', array( static::class, 'menu' ), 1 );
		add_action( 'admin_post_em_addons_refresh', array( static::class, 'handle_refresh' ) );
	}

	/**
	 * @param array $plugin_pages
	 * @return array
	 */
	public static function menu( $plugin_pages ) {
		$plugin_pages['addons'] = add_submenu_page( 'edit.php?post_type=' . EM_POST_TYPE_EVENT, __( 'Events Manager Add-ons', 'events-manager' ), __( 'Add-ons', 'events-manager' ), 'manage_options', 'events-manager-addons', array( static::class, 'page' ) );
		if ( static::hidden() ) {
			// Taken out of the menu after it is registered, so the screen still opens from a direct link.
			remove_submenu_page( 'edit.php?post_type=' . EM_POST_TYPE_EVENT, 'events-manager-addons' );
			// WordPress reads a screen's title from its menu entry, which is gone.
			add_filter( 'admin_title', function ( $admin_title, $title ) {
				return $title === '' && ( $_GET['page'] ?? '' ) === 'events-manager-addons' ? __( 'Events Manager Add-ons', 'events-manager' ) . ' ' . ltrim( $admin_title ) : $admin_title; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
			}, 10, 2 );
		}
		return $plugin_pages;
	}

	/**
	 * @return bool Whether the site has taken the screen out of its menu with EM_HIDE_ADDONS
	 */
	public static function hidden() {
		return defined( 'EM_HIDE_ADDONS' ) && EM_HIDE_ADDONS;
	}

	/**
	 * How an add-on's own plugin reports its license, keyed by plugin slug. An add-on missing here is still listed; it just shows no license state.
	 *
	 * @return array slug => license class name
	 */
	public static function license_classes() {
		return apply_filters( 'em_addons_license_classes', array(
			'events-manager-io'      => 'EM_IO\License',
			'events-manager-connect' => 'EM\Connect\License',
			'events-manager-zoom-pro' => 'EM\Zoom\License',
		) );
	}

	/**
	 * Plans this site is licensed for, by catalogue key (pro-plus), so an add-on such a plan includes is offered as a download instead of a purchase. Events Manager itself cannot know this; a plugin that holds the site's license answers through the filter.
	 *
	 * @return string[]
	 */
	public static function site_plans() {
		return array_map( 'sanitize_key', array_filter( (array) apply_filters( 'em_addons_site_plans', array() ), 'is_string' ) );
	}

	/**
	 * A link into the store, marked so a visit that started on this screen can be told apart there. The marks describe the link, not the site.
	 *
	 * @param string $url
	 * @param string $content What was clicked, such as an add-on key
	 * @return string
	 */
	public static function store_link( $url, $content ) {
		return $url ? add_query_arg( array( 'utm_source' => 'events-manager', 'utm_medium' => 'plugin', 'utm_campaign' => 'addons-screen', 'utm_content' => $content ), $url ) : '';
	}

	/**
	 * The catalogue without contacting the store: the copy saved by the last download when there is one, otherwise the copy shipped with this version.
	 *
	 * @return array{addons: array, plans: array, account: string, downloads: string, licenses: string, checked: int, tried: int, refreshed: int}
	 */
	public static function catalogue() {
		$saved = get_site_option( static::OPTION );
		if ( is_array( $saved ) && ! empty( $saved['addons'] ) && ! empty( $saved['checked'] ) ) {
			return $saved + array( 'tried' => 0, 'refreshed' => 0 );
		}
		$bundled = static::clean( json_decode( (string) file_get_contents( __DIR__ . '/addons.json' ), true ) );
		$bundled = $bundled ? $bundled : array( 'addons' => array(), 'plans' => array(), 'account' => '', 'downloads' => '', 'licenses' => '', 'checked' => 0 );
		$bundled['tried'] = is_array( $saved ) && ! empty( $saved['tried'] ) ? (int) $saved['tried'] : 0;
		$bundled['refreshed'] = is_array( $saved ) && ! empty( $saved['refreshed'] ) ? (int) $saved['refreshed'] : 0;
		return $bundled;
	}

	/**
	 * The catalogue for the screen: downloaded again when the saved copy is over a day old or a promotion it shows has ended, and after a failed download not before an hour has passed, so opening the screen repeatedly does not keep contacting the store.
	 *
	 * @return array See catalogue()
	 */
	public static function load() {
		$catalogue = static::catalogue();
		$fresh = $catalogue['checked'] && $catalogue['checked'] > time() - DAY_IN_SECONDS;
		// A copy holding promotion prices is asked for again once that promotion has ended, so the screen never offers a price the store no longer charges.
		if ( ! empty( $catalogue['reduced_until'] ) && time() >= $catalogue['reduced_until'] && $catalogue['checked'] < $catalogue['reduced_until'] ) {
			$fresh = false;
		}
		$waiting = $catalogue['tried'] && $catalogue['tried'] > time() - HOUR_IN_SECONDS;
		if ( ! $fresh && ! $waiting ) {
			static::refresh();
			$catalogue = static::catalogue();
		}
		return $catalogue;
	}

	/**
	 * Whether the Refresh link may download the list now: once a day on top of the screen's own daily download, and not within an hour of a failed attempt.
	 *
	 * @param array $catalogue See catalogue()
	 * @return bool
	 */
	public static function can_refresh( $catalogue ) {
		$waiting = $catalogue['tried'] && $catalogue['tried'] > time() - HOUR_IN_SECONDS;
		return ! $waiting && $catalogue['refreshed'] <= time() - DAY_IN_SECONDS;
	}

	/**
	 * Downloads the current catalogue from the store and saves it. Called when the screen is opened with a stale copy and by its Refresh link, never in the background.
	 *
	 * @param bool $manual whether the Refresh link asked for it, which uses up that link for a day
	 * @return bool
	 */
	public static function refresh( $manual = false ) {
		$saved = get_site_option( static::OPTION );
		$saved = is_array( $saved ) ? $saved : array();
		// Sent with WordPress's standard user agent, which carries the site's address: that is all the store learns about the site, and nothing is added to it. A site with no copy yet waits longer, since the alternative is the list shipped with the plugin.
		$response = wp_remote_get( static::FEED, array( 'timeout' => empty( $saved['checked'] ) ? 10 : 5 ) );
		$catalogue = null;
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$catalogue = static::clean( json_decode( wp_remote_retrieve_body( $response ), true ) );
		}
		if ( ! $catalogue ) {
			// Keeps whatever was saved before and notes the attempt, so load() leaves the store alone for an hour.
			$saved['tried'] = time();
			update_site_option( static::OPTION, $saved );
			return false;
		}
		$catalogue['checked'] = time();
		$catalogue['tried'] = 0;
		$catalogue['refreshed'] = $manual ? time() : ( ! empty( $saved['refreshed'] ) ? (int) $saved['refreshed'] : 0 );
		update_site_option( static::OPTION, $catalogue );
		return true;
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'events-manager' ), 403 );
		}
		check_admin_referer( 'em_addons_refresh' );
		$action = static::refresh_action( static::catalogue() );
		if ( $action === 'wait' || $action === 'soon' ) {
			wp_safe_redirect( add_query_arg( 'em_addons_refreshed', $action, static::url() ) );
			exit;
		}
		$refreshed = static::refresh( $action === 'refresh' );
		wp_safe_redirect( add_query_arg( 'em_addons_refreshed', $refreshed ? '1' : '0', static::url() ) );
		exit;
	}

	/**
	 * What a click on Refresh or Try again may do now. After a failed download it is a retry, which does not use up the day's Refresh but is held to one a minute; otherwise it is the once-a-day Refresh.
	 *
	 * @param array $catalogue See catalogue()
	 * @return string retry, soon (a retry was made under a minute ago), refresh or wait (the day's Refresh is used)
	 */
	public static function refresh_action( $catalogue ) {
		if ( $catalogue['tried'] ) {
			return $catalogue['tried'] > time() - MINUTE_IN_SECONDS ? 'soon' : 'retry';
		}
		return static::can_refresh( $catalogue ) ? 'refresh' : 'wait';
	}

	/**
	 * @return string
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=' . EM_POST_TYPE_EVENT . '&page=events-manager-addons' );
	}

	/**
	 * Keeps only what the screen prints from a decoded catalogue, and only links to our own sites, so a tampered or mistaken feed cannot put markup or a foreign link on an admin screen.
	 *
	 * @param mixed $data
	 * @return array|null null when the catalogue is not one this version understands
	 */
	public static function clean( $data ) {
		if ( ! is_array( $data ) || (int) ( $data['version'] ?? 0 ) !== static::VERSION || empty( $data['addons'] ) || ! is_array( $data['addons'] ) ) {
			return null;
		}
		$catalogue = array( 'addons' => array(), 'plans' => array(), 'checked' => 0 );
		foreach ( array( 'account', 'downloads', 'licenses' ) as $link ) {
			$catalogue[ $link ] = static::clean_url( $data[ $link ] ?? '' );
		}
		foreach ( (array) ( $data['plans'] ?? array() ) as $key => $plan ) {
			if ( ! is_array( $plan ) || empty( $plan['name'] ) ) {
				continue;
			}
			$catalogue['plans'][ sanitize_key( $key ) ] = array(
				'name'     => sanitize_text_field( $plan['name'] ),
				'short'    => sanitize_text_field( $plan['short'] ?? $plan['name'] ),
				'from'      => is_numeric( $plan['from'] ?? null ) ? (float) $plan['from'] : 0,
				'from_deal' => is_numeric( $plan['from_deal'] ?? null ) ? (float) $plan['from_deal'] : 0,
				'save_up_to' => is_numeric( $plan['save_up_to'] ?? null ) ? max( 0, min( 99, (int) $plan['save_up_to'] ) ) : 0,
				'checkout'  => static::clean_url( $plan['checkout'] ?? '' ),
			);
		}
		foreach ( $data['addons'] as $addon ) {
			if ( ! is_array( $addon ) || empty( $addon['slug'] ) || empty( $addon['name'] ) ) {
				continue;
			}
			$catalogue['addons'][] = array(
				'key'         => sanitize_key( $addon['key'] ?? $addon['slug'] ),
				'slug'        => sanitize_key( $addon['slug'] ),
				'name'        => sanitize_text_field( $addon['name'] ),
				'short'       => sanitize_text_field( $addon['short'] ?? $addon['name'] ),
				'summary'     => sanitize_text_field( $addon['summary'] ?? '' ),
				'url'         => static::clean_url( $addon['url'] ?? '' ),
				'buy'         => static::clean_url( $addon['buy'] ?? '' ),
				'from'        => is_numeric( $addon['from'] ?? null ) ? (float) $addon['from'] : 0,
				'from_deal'   => is_numeric( $addon['from_deal'] ?? null ) ? (float) $addon['from_deal'] : 0,
				'save_up_to'  => is_numeric( $addon['save_up_to'] ?? null ) ? max( 0, min( 99, (int) $addon['save_up_to'] ) ) : 0,
				'included_in' => array_map( 'sanitize_key', array_filter( (array) ( $addon['included_in'] ?? array() ), 'is_string' ) ),
			);
		}
		$catalogue['images'] = array();
		foreach ( is_array( $data['images'] ?? null ) ? $data['images'] : array() as $key => $image ) {
			$image = static::clean_url( $image );
			if ( $image && count( $catalogue['images'] ) < 50 ) {
				$catalogue['images'][ sanitize_key( $key ) ] = $image;
			}
		}
		$catalogue['extras'] = array();
		foreach ( is_array( $data['extras'] ?? null ) ? $data['extras'] : array() as $key => $extra ) {
			if ( is_array( $extra ) && count( $catalogue['extras'] ) < 20 ) {
				$catalogue['extras'][ sanitize_key( $key ) ] = array(
					'from' => is_numeric( $extra['from'] ?? null ) ? max( 0, (float) $extra['from'] ) : 0,
					'url'  => static::clean_url( $extra['url'] ?? '' ),
				);
			}
		}
		$catalogue['reduced_until'] = is_numeric( $data['reduced_until'] ?? null ) ? max( 0, (int) $data['reduced_until'] ) : 0;
		$notice = is_array( $data['notice'] ?? null ) ? $data['notice'] : array();
		$text = is_scalar( $notice['text'] ?? null ) ? sanitize_text_field( (string) $notice['text'] ) : '';
		if ( $text !== '' ) {
			$url = static::clean_url( $notice['url'] ?? '' );
			$catalogue['notice'] = array(
				'text'  => $text,
				'link'  => $url && is_scalar( $notice['link'] ?? null ) ? sanitize_text_field( (string) $notice['link'] ) : '',
				'url'   => $url,
				'ends'  => is_numeric( $notice['ends'] ?? null ) ? max( 0, (int) $notice['ends'] ) : 0,
				'timer' => ! empty( $notice['timer'] ),
			);
		}
		return $catalogue['addons'] ? $catalogue : null;
	}

	/**
	 * @param mixed $url
	 * @return string The URL when it is https on one of our own sites, otherwise empty
	 */
	public static function clean_url( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || $parts['scheme'] !== 'https' || empty( $parts['host'] ) ) {
			return '';
		}
		return in_array( strtolower( $parts['host'] ), static::HOSTS, true ) ? esc_url_raw( $url ) : '';
	}

	/**
	 * Where an add-on stands on this site.
	 *
	 * @param array $addon Catalogue entry
	 * @return array{state: string, plugin: string, until: int, license_url: string, included: string} state is one of missing, inactive, unlicensed, expired or licensed, or active when the add-on is running and does not report a license; included is the key of a plan this site is licensed for that includes the add-on, if any
	 */
	public static function status( $addon ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$status = array( 'state' => 'missing', 'plugin' => '', 'until' => 0, 'license_url' => '', 'included' => '' );
		$included = array_intersect( (array) ( $addon['included_in'] ?? array() ), static::site_plans() );
		$status['included'] = $included ? (string) reset( $included ) : '';
		foreach ( array_keys( get_plugins() ) as $plugin ) {
			if ( dirname( $plugin ) === $addon['slug'] ) {
				$status['plugin'] = $plugin;
				break;
			}
		}
		if ( $status['plugin'] === '' ) {
			return $status;
		}
		if ( ! is_plugin_active( $status['plugin'] ) ) {
			$status['state'] = 'inactive';
			return $status;
		}
		$status['state'] = 'active';
		$class = static::license_classes()[ $addon['slug'] ] ?? '';
		if ( $class && class_exists( $class ) && method_exists( $class, 'get_license' ) ) {
			$license = $class::get_license();
			$status['license_url'] = method_exists( $class, 'get_license_admin_url' ) ? (string) $class::get_license_admin_url() : '';
			$status['until'] = (int) ( $license->until ?? 0 );
			if ( empty( $license->key ) || empty( $license->activated ) ) {
				$status['state'] = 'unlicensed';
			} elseif ( empty( $license->valid ) || $status['until'] <= time() ) {
				$status['state'] = 'expired';
			} else {
				$status['state'] = 'licensed';
			}
		}
		return $status;
	}

	/**
	 * @param float $amount
	 * @return string $49 or $49.50
	 */
	public static function price( $amount ) {
		return '$' . number_format_i18n( (float) $amount, floor( (float) $amount ) == (float) $amount ? 0 : 2 );
	}

	/**
	 * @param float $list
	 * @param float $price
	 * @return int Whole percent saved, rounded up
	 */
	public static function percent_off( $list, $price ) {
		if ( $list <= 0 || $price >= $list ) {
			return 0;
		}
		// Rounded first so a float such as 20.000000000000004 does not become 21.
		return (int) ceil( round( ( $list - $price ) / $list * 100, 4 ) );
	}

	/**
	 * Prints "From $29 a year", with the usual price crossed out while the price is reduced, and the saving beside it: the biggest one on any site tier when the list gives it ("Up to 40% off"), otherwise this price's own.
	 *
	 * @param float $from
	 * @param float $from_deal The price it is reduced from; no more than $from when it is not reduced
	 * @param int   $save_up_to The biggest whole-percent saving across the site tiers, or 0 when the list does not say
	 */
	public static function from_price( $from, $from_deal = 0, $save_up_to = 0 ) {
		$reduced = (float) $from_deal > (float) $from;
		$price = '<strong>' . esc_html( static::price( $from ) ) . '</strong>' . ( $reduced ? ' <s>' . esc_html( static::price( $from_deal ) ) . '</s>' : '' );
		// translators: %s: price
		echo wp_kses( sprintf( __( 'From %s a year', 'events-manager' ), $price ), array( 'strong' => array(), 's' => array() ) );
		if ( $save_up_to > 0 ) {
			// translators: %d: percentage
			echo ' <span class="em-addon-save">' . esc_html( sprintf( __( 'Up to %d%% off', 'events-manager' ), $save_up_to ) ) . '</span>';
		} elseif ( $reduced ) {
			// translators: %d: percentage
			echo ' <span class="em-addon-save">' . esc_html( sprintf( __( 'Save %d%%', 'events-manager' ), static::percent_off( $from_deal, $from ) ) ) . '</span>';
		}
	}

	/**
	 * How this site's own Pro or Pro Plus license stands, as Events Manager Pro reports it.
	 *
	 * @return array{running: bool, until: int, license_url: string} running says whether Events Manager Pro is active on this site
	 */
	public static function pro_license() {
		$pro = array( 'running' => defined( 'EMP_VERSION' ), 'until' => 0, 'license_url' => '' );
		$class = 'EM_Pro\\License';
		if ( class_exists( $class ) && method_exists( $class, 'get_license' ) ) {
			$pro['until'] = (int) ( $class::get_license()->until ?? 0 );
			$pro['license_url'] = method_exists( $class, 'get_license_admin_url' ) ? (string) $class::get_license_admin_url() : '';
		}
		return $pro;
	}

	/**
	 * The Pro and Pro Plus cards, each with where it stands on this site.
	 *
	 * @param array $catalogue See catalogue()
	 * @return array key => plan, plus summary, features, more (whether the list ends with "And much more"), compare (its features page) and state: licensed when it is this site's plan, included when this site's plan contains it, active for Events Manager Pro running under a plan this screen was not told, upgrade for the plan above this site's, otherwise offer
	 */
	public static function plans( $catalogue ) {
		$about = array(
			'pro'      => array(
				'summary'  => __( 'Take bookings and payments on your own site, through your own payment gateway account, with no commission taken.', 'events-manager' ),
				'features' => array(
					__( 'Payments with PayPal, Stripe, Square, Authorize.net or offline', 'events-manager' ),
					__( 'Custom booking forms and individual attendee forms', 'events-manager' ),
					__( 'Coupons and discount codes', 'events-manager' ),
					__( 'PDF tickets and invoices', 'events-manager' ),
					__( 'QR code check-in from your phone', 'events-manager' ),
				),
				'more'     => true,
				'compare'  => 'https://wp-events-plugin.com/features/',
			),
			'pro-plus' => array(
				'summary'  => __( 'Everything in Pro, plus every add-on listed here, on a single license.', 'events-manager' ),
				'features' => array(
					__( 'Everything in Pro', 'events-manager' ),
					// A feature given as a pair is the feature and a smaller line of fine print beneath it.
					array( __( 'All our sold add-ons', 'events-manager' ), __( 'WooCommerce sold separately on Woo.com', 'events-manager' ) ),
				),
				'more'     => false,
				'compare'  => 'https://wp-events-plugin.com/pricing/',
			),
		);
		$site = static::site_plans();
		$mine = in_array( 'pro-plus', $site, true ) ? 'pro-plus' : ( in_array( 'pro', $site, true ) ? 'pro' : '' );
		$cards = array();
		foreach ( $about as $key => $content ) {
			if ( empty( $catalogue['plans'][ $key ]['name'] ) ) {
				continue;
			}
			if ( $mine === $key ) {
				$state = 'licensed';
			} elseif ( $mine === 'pro-plus' ) {
				$state = 'included';
			} elseif ( $mine === 'pro' ) {
				$state = 'upgrade';
			} else {
				$state = $key === 'pro' && static::pro_license()['running'] ? 'active' : 'offer';
			}
			$cards[ $key ] = $catalogue['plans'][ $key ] + $content + array( 'state' => $state );
		}
		return $cards;
	}

	/**
	 * @param string $key pro or pro-plus
	 * @param array  $plan See plans()
	 * @param array  $catalogue
	 */
	public static function plan_card( $key, $plan, $catalogue ) {
		$pro = static::pro_license();
		$checkout = static::store_link( $plan['checkout'], $key );
		$license_url = $pro['license_url'] ? $pro['license_url'] : $catalogue['licenses'];
		$badges = array(
			'licensed' => __( 'Your plan', 'events-manager' ),
			'active'   => __( 'Active', 'events-manager' ),
			// translators: %s: plan name, such as Pro Plus
			'included' => sprintf( __( 'Included in %s', 'events-manager' ), $catalogue['plans']['pro-plus']['short'] ?? '' ),
		);
		?>
		<div class="em-addon em-addon-plan is-<?php echo esc_attr( $plan['state'] ); ?>" id="em-addon-plan-<?php echo esc_attr( $key ); ?>">
			<div class="em-addon-body">
				<?php if ( ! empty( $badges[ $plan['state'] ] ) ) : ?>
					<span class="em-addon-badge"><?php echo esc_html( $badges[ $plan['state'] ] ); ?></span>
				<?php endif; ?>
				<h2><?php echo esc_html( $plan['name'] ); ?></h2>
				<p class="em-addon-summary"><?php echo esc_html( $plan['summary'] ); ?></p>
				<ul class="em-addon-features">
					<?php foreach ( $plan['features'] as $feature ) : ?>
						<li>
							<?php echo esc_html( is_array( $feature ) ? $feature[0] : $feature ); ?>
							<?php if ( is_array( $feature ) && ! empty( $feature[1] ) ) : ?>
								<small><?php echo esc_html( $feature[1] ); ?></small>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
					<?php if ( ! empty( $plan['more'] ) ) : ?>
						<li class="em-addon-more"><?php esc_html_e( 'And much more', 'events-manager' ); ?></li>
					<?php endif; ?>
				</ul>
				<div class="em-addon-foot">
					<?php if ( $plan['state'] === 'licensed' && $pro['until'] ) : ?>
						<p class="em-addon-price">
							<?php
							// translators: %s: date
							echo esc_html( sprintf( __( 'Updates and support until %s', 'events-manager' ), date_i18n( get_option( 'date_format' ), $pro['until'] ) ) );
							?>
						</p>
					<?php elseif ( in_array( $plan['state'], array( 'offer', 'upgrade' ), true ) && $plan['from'] > 0 ) : ?>
						<p class="em-addon-price"><?php static::from_price( $plan['from'], $plan['from_deal'] ?? 0, $plan['save_up_to'] ?? 0 ); ?></p>
					<?php endif; ?>
					<?php if ( $plan['state'] !== 'included' ) : ?>
						<p class="em-addon-actions">
							<?php if ( $plan['state'] === 'licensed' && $checkout ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( $checkout ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Renew or upgrade', 'events-manager' ); ?></a>
							<?php elseif ( $plan['state'] === 'upgrade' && $checkout ) : ?>
								<?php // translators: %s: plan short name, such as Pro Plus ?>
								<a class="button button-primary" href="<?php echo esc_url( $checkout ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( __( 'Upgrade to %s', 'events-manager' ), $plan['short'] ) ); ?></a>
							<?php elseif ( $plan['state'] === 'offer' && $checkout ) : ?>
								<?php // translators: %s: plan short name, such as Pro Plus ?>
								<a class="button button-primary" href="<?php echo esc_url( $checkout ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( __( 'Get %s', 'events-manager' ), $plan['short'] ) ); ?></a>
							<?php endif; ?>
							<a class="button" href="<?php echo esc_url( static::store_link( $plan['compare'], $key . '-features' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Compare features', 'events-manager' ); ?></a>
							<?php if ( in_array( $plan['state'], array( 'licensed', 'active' ), true ) && $license_url ) : ?>
								<a href="<?php echo esc_url( $license_url ); ?>"<?php echo $pro['license_url'] ? '' : ' target="_blank" rel="noopener"'; ?>><?php esc_html_e( 'View license', 'events-manager' ); ?></a>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Prints the picture at the top of a card. Pictures come with the list, from our own sites only; a card with none gets a plain plugin icon when $placeholder is set.
	 *
	 * @param string $image URL, already through clean_url()
	 * @param bool   $placeholder
	 */
	public static function media( $image, $placeholder = false ) {
		if ( $image ) {
			// No referrer, so the picture's host is not told which admin screen asked for it.
			echo '<div class="em-addon-media"><img src="' . esc_url( $image ) . '" alt="" loading="lazy" referrerpolicy="no-referrer"></div>';
		} elseif ( $placeholder ) {
			echo '<div class="em-addon-media is-placeholder"><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span></div>';
		}
	}

	/**
	 * Whether a plugin is on this site, by its folder name.
	 *
	 * @param string $slug
	 * @return array{state: string, plugin: string} state is missing, inactive or active
	 */
	public static function plugin_state( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( array_keys( get_plugins() ) as $plugin ) {
			if ( dirname( $plugin ) === $slug ) {
				return array( 'state' => is_plugin_active( $plugin ) ? 'active' : 'inactive', 'plugin' => $plugin );
			}
		}
		return array( 'state' => 'missing', 'plugin' => '' );
	}

	/**
	 * Add-ons that are not bought through the store's add-on checkout, listed after those that are. The list from the store may replace an entry's price (from) and link (url).
	 *
	 * @return array[]
	 */
	public static function extras() {
		return array(
			array(
				'key'     => 'app',
				'name'    => __( 'Events Manager app', 'events-manager' ),
				'summary' => __( 'Check attendees in at the door by scanning tickets, manage events and bookings, and follow booking and sales status from your phone. Available on the App Store for iPhone and iPad. Included with Pro Plus, or available by subscription for any Events Manager site.', 'events-manager' ),
				'url'     => 'https://wp-events-plugin.com/features/mobile-apps/',
			),
			array(
				'key'     => 'woocommerce',
				'slug'    => 'events-manager-woocommerce',
				'name'    => __( 'Events Manager for WooCommerce', 'events-manager' ),
				'summary' => __( 'Sell event tickets through your WooCommerce cart and checkout, with bookings kept in sync with orders, and take payment with any gateway WooCommerce supports. Sold separately on Woo.com and not included in Pro Plus.', 'events-manager' ),
				'url'     => 'https://pxlink.cc/wc',
				'from'    => 79,
				'buy'     => __( 'Buy on Woo.com', 'events-manager' ),
			),
		);
	}

	/**
	 * Free plugins from the same team, on WordPress.org.
	 *
	 * @return array[]
	 */
	public static function companions() {
		return array(
			array(
				'key'     => 'wp-fullcalendar',
				'slug'    => 'wp-fullcalendar',
				'name'    => __( 'WP FullCalendar', 'events-manager' ),
				'summary' => __( 'Shows events, posts or other post types in an AJAX month, week or day calendar that visitors can filter by category or tag. Events Manager integrates with it automatically.', 'events-manager' ),
				'url'     => 'https://wordpress.org/plugins/wp-fullcalendar/',
				'free'    => true,
			),
			array(
				'key'     => 'zoom',
				'slug'    => 'events-manager-zoom',
				'name'    => __( 'Zoom Integration', 'events-manager' ),
				'summary' => __( 'Create Zoom meetings and webinars from the event editor, and register attendees on Zoom when they book. With Pro, attendees are registered once they pay and receive their own join links.', 'events-manager' ),
				'url'     => 'https://wordpress.org/plugins/events-manager-zoom/',
				'free'    => true,
			),
			array(
				'key'     => 'wpml',
				'slug'    => 'events-manager-wpml',
				'name'    => __( 'WPML Compatibility', 'events-manager' ),
				'summary' => __( 'Makes Events Manager translatable with WPML, including tickets, location addresses, booking forms and email templates. Supports recurring events and multisite. Requires WPML.', 'events-manager' ),
				'url'     => 'https://wordpress.org/plugins/events-manager-wpml/',
				'free'    => true,
			),
		);
	}

	/**
	 * A card for a plugin this screen only describes: whether it is on the site, and where to read about it.
	 *
	 * @param array $item See extras() and companions()
	 * @param array $catalogue
	 */
	public static function plugin_card( $item, $catalogue ) {
		$plugin = ! empty( $item['slug'] ) ? static::plugin_state( $item['slug'] ) : array( 'state' => '', 'plugin' => '' );
		$badges = array( 'active' => __( 'Active', 'events-manager' ), 'inactive' => __( 'Installed', 'events-manager' ) );
		$live = $catalogue['extras'][ $item['key'] ] ?? array();
		$url = ! empty( $live['url'] ) ? $live['url'] : $item['url'];
		$from = ! empty( $live['from'] ) ? $live['from'] : ( $item['from'] ?? 0 );
		// A price and a buy button only make sense for a plugin the site does not have yet.
		$sold = $plugin['state'] === 'missing' && $from > 0 && ! empty( $item['buy'] );
		?>
		<div class="em-addon is-<?php echo esc_attr( $plugin['state'] === 'active' ? 'licensed' : $plugin['state'] ); ?>" id="em-addon-<?php echo esc_attr( $item['key'] ); ?>">
			<?php static::media( $catalogue['images'][ $item['key'] ] ?? '', true ); ?>
			<div class="em-addon-body">
				<?php if ( ! empty( $badges[ $plugin['state'] ] ) ) : ?>
					<span class="em-addon-badge"><?php echo esc_html( $badges[ $plugin['state'] ] ); ?></span>
				<?php endif; ?>
				<h2><?php echo esc_html( $item['name'] ); ?></h2>
				<p class="em-addon-summary"><?php echo esc_html( $item['summary'] ); ?></p>
				<div class="em-addon-foot">
					<?php if ( $sold ) : ?>
						<p class="em-addon-price"><?php static::from_price( $from ); ?></p>
					<?php endif; ?>
					<p class="em-addon-actions">
						<?php if ( $sold ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['buy'] ); ?></a>
						<?php elseif ( $plugin['state'] === 'inactive' && current_user_can( 'activate_plugin', $plugin['plugin'] ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $plugin['plugin'] ) ), 'activate-plugin_' . $plugin['plugin'] ) ); ?>"><?php esc_html_e( 'Activate plugin', 'events-manager' ); ?></a>
						<?php elseif ( $plugin['state'] === 'missing' && ! empty( $item['free'] ) && current_user_can( 'install_plugins' ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . rawurlencode( $item['slug'] ) ), 'install-plugin_' . $item['slug'] ) ); ?>"><?php esc_html_e( 'Install', 'events-manager' ); ?></a>
						<?php endif; ?>
						<?php if ( ! $sold ) : ?>
							<a class="button" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'events-manager' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	public static function page() {
		$catalogue = static::load();
		$plans = $catalogue['plans'];
		$notice = $catalogue['notice'] ?? null;
		$plan_cards = static::plans( $catalogue );
		?>
		<div class="wrap em-addons">
			<h1><?php esc_html_e( 'Events Manager Add-ons', 'events-manager' ); ?></h1>
			<?php if ( $catalogue['tried'] ) : ?>
				<div class="notice notice-warning em-addons-offline">
					<p>
						<?php
						if ( $catalogue['checked'] ) {
							// translators: %s: date
							echo esc_html( sprintf( __( 'eventsmanagerpro.com could not be reached, so this is the list as it was on %s. Prices and offers may have changed since.', 'events-manager' ), date_i18n( get_option( 'date_format' ), $catalogue['checked'] ) ) );
						} else {
							esc_html_e( 'eventsmanagerpro.com could not be reached, so these are the add-ons that came with your version of Events Manager. Prices may be out of date, and pictures and current offers are missing.', 'events-manager' );
						}
						?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=em_addons_refresh' ), 'em_addons_refresh' ) ); ?>"><?php esc_html_e( 'Try again', 'events-manager' ); ?></a>
					</p>
				</div>
			<?php endif; ?>
			<?php if ( $notice && ( ! $notice['ends'] || $notice['ends'] > time() ) ) : ?>
				<div class="em-addons-promo"<?php echo $notice['ends'] ? ' data-ends="' . (int) $notice['ends'] . '"' : ''; ?>>
					<strong><?php echo esc_html( $notice['text'] ); ?></strong>
					<?php if ( $notice['url'] && $notice['link'] ) : ?>
						<a href="<?php echo esc_url( static::store_link( $notice['url'], 'notice' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $notice['link'] ); ?></a>
					<?php endif; ?>
					<?php if ( $notice['timer'] && $notice['ends'] ) : ?>
						<?php // translators: %s: time left, such as 1d 04:12:33 ?>
						<span class="em-addons-timer" data-label="<?php echo esc_attr( __( 'Ends in %s', 'events-manager' ) ); ?>"></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['em_addons_refreshed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only ?>
				<?php if ( $_GET['em_addons_refreshed'] === '1' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Add-ons and prices are up to date.', 'events-manager' ); ?></p></div>
				<?php elseif ( $_GET['em_addons_refreshed'] === 'wait' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'The add-on list can be refreshed once a day. Please try again tomorrow.', 'events-manager' ); ?></p></div>
				<?php elseif ( $_GET['em_addons_refreshed'] === 'soon' ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'That was tried a moment ago. Please wait a minute and try again.', 'events-manager' ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $plan_cards ) : ?>
				<div class="em-addons-section">
					<h2><?php esc_html_e( 'Events Manager Pro', 'events-manager' ); ?></h2>
					<p class="em-addons-intro"><?php esc_html_e( 'Pro installs alongside the free Events Manager plugin. Every license includes one year of updates and Pro support.', 'events-manager' ); ?></p>
					<div class="em-addons-grid em-addons-plans">
						<?php foreach ( $plan_cards as $key => $plan ) : ?>
							<?php static::plan_card( $key, $plan, $catalogue ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="em-addons-section">
				<h2><?php esc_html_e( 'Add-ons', 'events-manager' ); ?></h2>
				<p class="em-addons-intro">
					<?php esc_html_e( 'Add-ons are separate plugins that work with the free Events Manager plugin, so you do not need Events Manager Pro to use them.', 'events-manager' ); ?>
					<?php if ( ! empty( $plans['pro-plus'] ) ) : ?>
						<?php
						// translators: %s: plan name
						echo esc_html( sprintf( __( '%s includes every add-on sold here.', 'events-manager' ), $plans['pro-plus']['name'] ) );
						?>
					<?php endif; ?>
				</p>
				<div class="em-addons-grid">
					<?php foreach ( $catalogue['addons'] as $addon ) : ?>
						<?php static::card( $addon, static::status( $addon ), $catalogue ); ?>
					<?php endforeach; ?>
					<?php foreach ( static::extras() as $item ) : ?>
						<?php static::plugin_card( $item, $catalogue ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="em-addons-section">
				<h2><?php esc_html_e( 'Free companion plugins', 'events-manager' ); ?></h2>
				<p class="em-addons-intro"><?php esc_html_e( 'Free plugins from the Events Manager team, available on WordPress.org.', 'events-manager' ); ?></p>
				<div class="em-addons-grid">
					<?php foreach ( static::companions() as $item ) : ?>
						<?php static::plugin_card( $item, $catalogue ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php if ( $catalogue['checked'] ) : ?>
			<p class="em-addons-checked">
				<?php
				// translators: 1: the site the list comes from, 2: date
				echo wp_kses( sprintf( __( 'Last checked via %1$s on %2$s.', 'events-manager' ), '<code>' . esc_html( (string) wp_parse_url( static::FEED, PHP_URL_HOST ) ) . '</code>', esc_html( date_i18n( get_option( 'date_format' ), $catalogue['checked'] ) ) ), array( 'code' => array() ) );
				?>
				<?php if ( static::can_refresh( $catalogue ) ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=em_addons_refresh' ), 'em_addons_refresh' ) ); ?>"><?php esc_html_e( 'Refresh', 'events-manager' ); ?></a>
				<?php endif; ?>
			</p>
			<?php endif; ?>
			<?php if ( ! static::hidden() ) : ?>
			<p class="em-addons-checked">
				<?php
				// translators: %s: a line of PHP code
				echo wp_kses( sprintf( __( 'If you would prefer to hide this page, add %s to your wp-config.php file.', 'events-manager' ), "<code>define( 'EM_HIDE_ADDONS', true );</code>" ), array( 'code' => array() ) );
				?>
			</p>
			<?php endif; ?>
		</div>
		<style>
			.em-addons-section { max-width: 1400px; margin: 26px 0 0; }
			.em-addons-section > h2 { margin: 0 0 6px; font-size: 18px; }
			.em-addons-intro { max-width: 60em; margin: 0 0 14px; font-size: 14px; color: #50575e; }
			.em-addons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 20px; margin: 0 0 8px; }
			.em-addons-plans { grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); max-width: 1000px; }
			.em-addon { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
			.em-addon.is-licensed { border-color: #68de7c; }
			.em-addon-media { height: 150px; background: #f6f7f7; border-bottom: 1px solid #f0f0f1; display: flex; align-items: center; justify-content: center; }
			.em-addon-media img { width: 100%; height: 100%; object-fit: cover; object-position: top center; display: block; }
			.em-addon-media .dashicons { font-size: 48px; width: 48px; height: 48px; color: #c3c4c7; }
			.em-addon-body { flex: 1; display: flex; flex-direction: column; align-items: flex-start; padding: 20px; }
			.em-addon-body > * { align-self: stretch; }
			.em-addon h2 { margin: 0 0 8px; font-size: 16px; }
			.em-addon-plan h2 { font-size: 18px; }
			.em-addon-badge { align-self: flex-start; margin: 0 0 10px; border: 1px solid #c3c4c7; border-radius: 12px; padding: 1px 10px; font-size: 12px; font-weight: 600; line-height: 1.6; background: #f6f7f7; color: #50575e; }
			.em-addon.is-licensed .em-addon-badge, .em-addon.is-included .em-addon-badge { background: #edfaef; border-color: #68de7c; color: #00691c; }
			.em-addon.is-inactive .em-addon-badge, .em-addon.is-active .em-addon-badge { background: #f0f6fc; border-color: #72aee6; color: #0a4b78; }
			.em-addon.is-expired .em-addon-badge, .em-addon.is-unlicensed .em-addon-badge { background: #fcf0f1; border-color: #f86368; color: #b32d2e; }
			.em-addon-summary { margin: 0 0 12px; }
			.em-addon-features { margin: 0 0 14px; padding: 12px 0 0; border-top: 1px solid #f0f0f1; list-style: none; }
			.em-addon-features li { position: relative; margin: 0 0 7px; padding-left: 24px; }
			.em-addon-features li::before { content: "\f147"; font-family: dashicons; position: absolute; left: 0; top: -1px; font-size: 18px; color: #00a32a; }
			.em-addon-features li small { display: block; margin-top: 2px; color: #787c82; font-size: 12px; }
			.em-addon-features li.em-addon-more { color: #646970; font-style: italic; }
			.em-addon-features li.em-addon-more::before { content: "\f132"; color: #a7aaad; }
			.em-addon-foot { margin-top: auto; }
			.em-addon-price { margin: 0 0 12px; color: #50575e; }
			.em-addon-price strong { color: #1d2327; font-size: 18px; }
			.em-addon-included { display: block; margin-top: 4px; font-size: 12px; }
			.em-addon-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0; }
			.em-addon-note { margin: 12px 0 0; color: #646970; font-size: 12px; }
			.em-addons-checked { color: #646970; font-size: 12px; }
			.em-addons-checked code { font-size: 11px; }
			.em-addon-price s { color: #787c82; }
			.em-addon-save { display: inline-block; margin-left: 4px; padding: 0 8px; border-radius: 10px; background: #b32d2e; color: #fff; font-size: 12px; line-height: 1.7; white-space: nowrap; }
			.em-addons-promo { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; max-width: 1400px; box-sizing: border-box; margin: 14px 0 6px; padding: 12px 16px; background: #1d2327; color: #fff; border-radius: 4px; font-size: 14px; }
			.em-addons-promo a { color: #f0c33c; }
			.em-addons-timer { margin-left: auto; font-variant-numeric: tabular-nums; }
		</style>
		<script>
			( function () {
				var promo = document.querySelector( '.em-addons-promo[data-ends]' );
				if ( ! promo ) { return; }
				var timer = promo.querySelector( '.em-addons-timer' ), ends = parseInt( promo.getAttribute( 'data-ends' ), 10 ) * 1000;
				var two = function ( n ) { return ( n < 10 ? '0' : '' ) + n; };
				var tick = function () {
					var left = Math.floor( ( ends - Date.now() ) / 1000 );
					if ( left <= 0 ) { promo.style.display = 'none'; return; }
					if ( timer ) { timer.textContent = timer.getAttribute( 'data-label' ).replace( '%s', ( left >= 86400 ? Math.floor( left / 86400 ) + 'd ' : '' ) + two( Math.floor( left % 86400 / 3600 ) ) + ':' + two( Math.floor( left % 3600 / 60 ) ) + ':' + two( left % 60 ) ); }
					setTimeout( tick, 1000 );
				};
				tick();
			} )();
		</script>
		<?php
	}

	/**
	 * @param array $addon
	 * @param array $status See status()
	 * @param array $catalogue
	 */
	public static function card( $addon, $status, $catalogue ) {
		$badges = array(
			'missing'    => '',
			'inactive'   => __( 'Installed, not active', 'events-manager' ),
			'active'     => __( 'Active', 'events-manager' ),
			'unlicensed' => __( 'License not activated', 'events-manager' ),
			'expired'    => __( 'License expired', 'events-manager' ),
			'licensed'   => __( 'Licensed', 'events-manager' ),
		);
		$included = array();
		foreach ( $addon['included_in'] as $plan ) {
			if ( ! empty( $catalogue['plans'][ $plan ] ) ) {
				$included[] = $catalogue['plans'][ $plan ]['short'];
			}
		}
		$external = ' target="_blank" rel="noopener"';
		// A site licensed for a plan that includes the add-on is pointed at its download, never at a purchase.
		$plan_name = $status['included'] && ! empty( $catalogue['plans'][ $status['included'] ] ) ? $catalogue['plans'][ $status['included'] ]['short'] : '';
		$unsettled = in_array( $status['state'], array( 'missing', 'inactive', 'unlicensed', 'expired' ), true );
		$buy = static::store_link( $addon['buy'], $addon['key'] );
		$badge = $badges[ $status['state'] ];
		if ( $plan_name && $status['state'] === 'missing' ) {
			// translators: %s: plan name
			$badge = sprintf( __( 'Included with your %s', 'events-manager' ), $plan_name );
		}
		?>
		<div class="em-addon is-<?php echo esc_attr( $status['state'] ); ?>" id="em-addon-<?php echo esc_attr( $addon['key'] ); ?>">
			<?php static::media( $catalogue['images'][ $addon['key'] ] ?? '', true ); ?>
			<div class="em-addon-body">
			<?php if ( $badge ) : ?>
				<span class="em-addon-badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>
			<h2><?php echo esc_html( $addon['name'] ); ?></h2>
			<p class="em-addon-summary"><?php echo esc_html( $addon['summary'] ); ?></p>
			<div class="em-addon-foot">
			<?php if ( $plan_name && $unsettled ) : ?>
				<p class="em-addon-price">
					<?php
					// translators: %s: plan name
					echo esc_html( sprintf( __( 'Your %s license includes this add-on.', 'events-manager' ), $plan_name ) );
					?>
				</p>
			<?php elseif ( $unsettled && $addon['from'] > 0 ) : ?>
				<p class="em-addon-price">
					<?php
					static::from_price( $addon['from'], $addon['from_deal'] ?? 0, $addon['save_up_to'] ?? 0 );
					if ( $included ) {
						// translators: %s: plan name
						echo '<span class="em-addon-included">' . esc_html( sprintf( __( 'or included with %s', 'events-manager' ), implode( ', ', $included ) ) ) . '</span>';
					}
					?>
				</p>
			<?php elseif ( $status['state'] === 'licensed' && $status['until'] ) : ?>
				<p class="em-addon-price">
					<?php
					// translators: %s: date
					echo esc_html( sprintf( __( 'Updates and support until %s', 'events-manager' ), date_i18n( get_option( 'date_format' ), $status['until'] ) ) );
					?>
				</p>
			<?php endif; ?>
			<p class="em-addon-actions">
				<?php if ( $status['state'] === 'missing' && $plan_name && $catalogue['downloads'] ) : ?>
					<?php // translators: %s: add-on short name ?>
					<a class="button button-primary" href="<?php echo esc_url( $catalogue['downloads'] ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( sprintf( __( 'Download %s', 'events-manager' ), $addon['short'] ) ); ?></a>
				<?php elseif ( $status['state'] === 'missing' ) : ?>
					<?php if ( $buy ) : ?>
						<?php // translators: %s: add-on short name ?>
						<a class="button button-primary" href="<?php echo esc_url( $buy ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( sprintf( __( 'Buy %s', 'events-manager' ), $addon['short'] ) ); ?></a>
					<?php endif; ?>
				<?php elseif ( $status['state'] === 'inactive' ) : ?>
					<?php if ( current_user_can( 'activate_plugin', $status['plugin'] ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $status['plugin'] ) ), 'activate-plugin_' . $status['plugin'] ) ); ?>"><?php esc_html_e( 'Activate plugin', 'events-manager' ); ?></a>
					<?php endif; ?>
				<?php elseif ( $status['state'] === 'unlicensed' ) : ?>
					<?php if ( $status['license_url'] ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $status['license_url'] ); ?>"><?php esc_html_e( 'Activate license', 'events-manager' ); ?></a>
					<?php endif; ?>
					<?php if ( $buy && ! $plan_name ) : ?>
						<?php // translators: %s: add-on short name ?>
						<a class="button" href="<?php echo esc_url( $buy ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( sprintf( __( 'Buy %s', 'events-manager' ), $addon['short'] ) ); ?></a>
					<?php endif; ?>
				<?php elseif ( $status['state'] === 'expired' ) : ?>
					<?php if ( $buy && ! $plan_name ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $buy ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Renew license', 'events-manager' ); ?></a>
					<?php endif; ?>
					<?php if ( $plan_name && $catalogue['licenses'] ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $catalogue['licenses'] ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Manage licenses', 'events-manager' ); ?></a>
					<?php endif; ?>
				<?php elseif ( $status['state'] === 'licensed' && $catalogue['account'] ) : ?>
					<a class="button" href="<?php echo esc_url( $catalogue['account'] ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Manage in your account', 'events-manager' ); ?></a>
				<?php endif; ?>
				<?php if ( $addon['url'] ) : ?>
					<a class="button" href="<?php echo esc_url( $addon['url'] ); ?>"<?php echo $external; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Learn more', 'events-manager' ); ?></a>
				<?php endif; ?>
			</p>
			<?php if ( $status['state'] === 'expired' && $plan_name ) : ?>
				<p class="em-addon-note">
					<?php
					// translators: %s: plan name
					echo esc_html( sprintf( __( 'This site is activated under a license that has expired. Deactivate it from your licenses page, then activate it again here and choose your %s license.', 'events-manager' ), $plan_name ) );
					?>
				</p>
			<?php elseif ( $status['state'] === 'missing' && $plan_name ) : ?>
				<p class="em-addon-note"><?php esc_html_e( 'Upload the zip under Plugins > Add New Plugin, then activate your license from its settings.', 'events-manager' ); ?></p>
			<?php elseif ( $status['state'] === 'missing' && $catalogue['downloads'] ) : ?>
				<p class="em-addon-note">
					<?php
					// translators: %s: link to the downloads page
					echo wp_kses( sprintf( __( 'Already have a license? Download the plugin from %s, upload it under Plugins > Add New Plugin, then activate your license from its settings.', 'events-manager' ), '<a href="' . esc_url( $catalogue['downloads'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'your downloads page', 'events-manager' ) . '</a>' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) );
					?>
				</p>
			<?php endif; ?>
			</div>
			</div>
		</div>
		<?php
	}
}
Addons::init();
