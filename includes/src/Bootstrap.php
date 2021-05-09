<?php

namespace CodeVerve\WCPLL;

use CodeVerve\WCPLL\Core\PLL_Hooks;
use CodeVerve\WCPLL\Core\WC_Hooks;
use CodeVerve\WCPLL\Core\WP_Hooks;
use IgniteKit\WP\Notices\NoticesManager;

/**
 * Class Bootstrap
 * @package CodeVerve\WCPLL
 */
final class Bootstrap {

	/**
	 * The instance
	 * @var self
	 */
	private static $_instance;

	/**
	 * Minimum PLL version
	 * @var string
	 */
	protected $min_pll_version;

	/**
	 * The plugin basename
	 * @var string
	 */
	protected $plugin_basename;

	/**
	 * WooCommerce related hooks
	 * @var WC_Hooks
	 */
	public $WC;
	/**
	 * Polylang related hooks
	 * @var PLL_Hooks
	 */
	public $PLL;
	/**
	 * WordPress related hooks
	 * @var WP_Hooks
	 */
	public $WP;

	/**
	 * The notices manager
	 * @var NoticesManager
	 */
	public $notices;

	/**
	 * Returns the plugin instance
	 * @return Bootstrap
	 */
	public static function get_instance() {
		if ( self::$_instance === null ) {
			self::$_instance = new Bootstrap( WPIDG_BASENAME, WPIDG_MIN_PLL_VERSION );
		}

		return self::$_instance;
	}

	/**
	 * Bootstrap constructor.
	 *
	 * @param $plugin_basename
	 * @param $min_pll_version
	 */
	private function __construct( $plugin_basename, $min_pll_version ) {

		// Init notices.
		$this->notices = new NoticesManager( 'wcpllint' );

		// Setters.
		$this->plugin_basename = $plugin_basename;
		$this->min_pll_version = $min_pll_version;

		register_deactivation_hook( WPIDG_FILE, array( $this, 'deactivation' ) );

		// Bail if deactivation.
		if ( $this->is_deactivation() ) {
			return;
		}

		// Remove Polylang for WooCommerce notice
		add_filter( 'pll_can_display_notice', array( $this, 'remove_pllwc_notice' ), 10, 2 );

		// Fix for pretty permalinks
		add_filter( 'pll_languages_list', array( $this, 'languages_list' ), 7 );
		add_filter( 'pll_after_languages_cache', array( $this, 'after_languages_cache' ), 20 );

		// Init polylang
		add_action( 'pll_init', array( $this, 'init' ) );

		// Admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueues' ) );

	}

	/**
	 * Run the plugin
	 */
	public function init() {

		// Check for WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->print_missing_dependency_notice( 'WooCommerce' );

			return;
		}

		// Check for PolyLang
		if ( ! defined( 'POLYLANG_VERSION' ) ) {
			$this->print_missing_dependency_notice( 'Polylang' );

			return;
		}

		// Check for PLL WC Plugin.
		if ( defined( 'PLLWC_VERSION' ) ) {
			$this->print_conflict_notice( 'Polylang for WooCommerce' );

			return;
		}

		// Check for PLL WC Plugin.
		if ( defined( 'Hyyan_WPI_DIR' ) ) {
			$this->print_conflict_notice( 'Hyyan WooCommerce Polylang Integration' );

			return;
		}

		// Check polylang version
		if ( defined( 'POLYLANG_VERSION' ) && version_compare( POLYLANG_VERSION, $this->min_pll_version, '<' ) ) {
			$this->print_incompatibility_notice();

			return;
		}

		// Give users a warm welcome
		$this->print_welcome_notice();

		// Init plugin.
		$this->PLL = new PLL_Hooks();
		$this->WC  = new WC_Hooks();
		$this->WP  = new WP_Hooks();
	}

	/**
	 * Fired on deactivation
	 */
	public function deactivation() {
		$welcome_notice = $this->notices->get_notice( 'welcome', 'custom' );
		if ( ! is_null( $welcome_notice ) ) {
			$welcome_notice->reset();
		}
	}

	/**
	 * Is plugin deactivation?
	 * @return bool
	 */
	public function is_deactivation() {
		return isset( $_GET['action'], $_GET['plugin'] ) && 'deactivate' === $_GET['action'] && $this->plugin_basename === $_GET['plugin'];
	}


	/**
	 * Modify the language list when front age is shop and using plain permalinks.
	 *
	 * @param $languages
	 *
	 * @return mixed
	 */
	public function languages_list( $languages ) {
		return $this->set_home_urls( $languages );
	}

	/***
	 * Properly set the home urls if the shop is front page, not cached and using plain permalinks.
	 *
	 * @param $languages
	 *
	 * @return mixed
	 */
	public function after_languages_cache( $languages ) {
		if ( ( defined( 'PLL_CACHE_LANGUAGES' ) && ! PLL_CACHE_LANGUAGES ) || ( defined( 'PLL_CACHE_HOME_URL' ) && ! PLL_CACHE_HOME_URL ) ) {
			return $this->set_home_urls( $languages );
		}

		return $languages;
	}


	/**
	 * Set the home urls if home is front and plain permalinks are used.
	 *
	 * @param $languages
	 *
	 * @return mixed
	 */
	protected function set_home_urls( $languages ) {
		if ( ! get_option( 'permalink_structure' ) && 'page' === get_option( 'show_on_front' ) && function_exists( 'wc_get_page_id' ) && in_array( wc_get_page_id( 'shop' ), wp_list_pluck( $languages, 'page_on_front' ) ) ) {
			$options = get_option( 'polylang' );
			foreach ( $languages as $k => $lang ) {
				if ( ! $options['hide_default'] || $lang->slug !== $options['default_lang'] ) {
					$languages[ $k ]->home_url = home_url( '/?post_type=product&lang=' . $lang->slug );
				}
			}
		}

		return $languages;
	}

	/**
	 * Print out the incompatibility with polylang notice
	 */
	protected function print_incompatibility_notice() {

		$message = __( sprintf( 'You are using outdated version of Polylang. Please update it to %s at least in order to be able to use WooCommerce Polylang Integration.', $this->min_pll_version ), 'woocommerce-polylang-integration' );
		$this->notices->add_error( 'pll_outdated', sprintf( '<h3>WooCommerce Polylang Integration</h3><p>%s</p>', $message ), NoticesManager::DISMISS_DISABLED );
	}

	/**
	 * Print out the missing dependency notice
	 *
	 * @param $dependency
	 */
	protected function print_missing_dependency_notice( $dependency ) {
		$message = __( sprintf( 'Ooops! It looks like you are missing <strong>%s</strong>. Please install <strong>%s</strong> in order to be able to use this plugin.', $dependency, $dependency ), 'woocommerce-polylang-integration' );
		$this->notices->add_error( 'pll_missing_' . str_replace( '-', '_', sanitize_title( $dependency ) ), sprintf( '<h3>WooCommerce Polylang Integration</h3><p>%s</p>', $message ), NoticesManager::DISMISS_DISABLED );
	}


	/**
	 * Print out conflict notices
	 *
	 * @param $conflict
	 */
	protected function print_conflict_notice( $conflict ) {
		$message = __( sprintf( 'Ooops! It looks like you have installed <strong>%s</strong> plugin which is not compatible with <strong>WooCommerce Polylang Integration</strong>. Please deactivate <strong>%s</strong> if you want to use <strong>WooCommerce Polylang Integration</strong>.', $conflict, $conflict ), 'woocommerce-polylang-integration' );
		$this->notices->add_error( 'conflict_' . str_replace( '-', '_', sanitize_title( $conflict ) ), sprintf( '<h3>WooCommerce Polylang Integration</h3><p>%s</p>', $message ), MONTH_IN_SECONDS );
	}

	/**
	 * Print out the welcome notice
	 * @return void
	 */
	protected function print_welcome_notice() {
		ob_start();
		?>
        <div class="instructions dgv-instructions">
            <div class="dgv-instructions-card dgv-instructions-card-shadow">
                <div class="dgv-instructions-row dgv-instructions-header">
                    <div class="dgv-instructions-colf">
                        <p class="lead"><?php echo sprintf( __( 'Thanks for installing %s', 'woocommerce-polylang-integration' ), '<strong class="green">WooCommerce Polylang Integration</strong>' ); ?></p>
                        <p class="desc"><?php echo sprintf( __( 'This is completely <strong class="underline">plug and play</strong> solution and you don\'t have to do anything except translate <strong>My Account</strong>, <strong>Cart</strong>, <strong>Checkout</strong> and the <strong>Products</strong> in all languages. Also, <a href="%s">check string translations</a> to translate strings like payment gateway name, etc.', 'woocommerce-polylang-integration' ), admin_url('admin.php?page=mlang_strings&s&group=WooCommerce&paged=1') ); ?></p>
                        <p class="desc"><?php echo sprintf( __( 'If you found this plugin <strong>useful</strong> for your business, please take a minute to <a target="_blank" title="Give this plugin a good five star rating :)" href="https://wordpress.org/support/plugin/woocommerce-polylang-integration/reviews/#new-post">review it.</a> It will be greatly appreciated and will boost our motivation to maintian this plugin in future.', 'woocommerce-polylang-integration' ) ); ?></p>
                        <p class="desc"><?php echo sprintf( __( 'To verify if the plugin is running correctly, click on the button bellow and navigate to the "WooCommerce Polylang Integration" box. It will tell you if you are missing anything.', 'woocommerce-polylang-integration' ) ); ?></p>
                        <p class="desc"><?php echo sprintf( '<a class="button" title="%s" href="%s">%s</a>', __( 'Check if the plugin is running correctly or if you are missing something.', 'woocommerce-polylang-integration' ), admin_url( 'admin.php?page=wc-status#wpi-status' ), __( 'View Status', 'woocommerce-polylang-integration' ) ); ?></p>

                    </div>
                </div>
                <div class="dgv-instructions-row dgv-instructions-mb-10">
                    <div class="dgv-instructions-colf">
                        <div class="dgv-instructions-extra">
                            <h4 class="navy"><?php _e( 'Found problem? Report it!', 'woocommerce-polylang-integration' ); ?></h4>
                            <p>
								<?php _e( 'If you found a bug or you want to report a problem please open a support ticket <a target="_blank" href="https://wordpress.org/support/plugin/woocommerce-polylang-integration/">here</a> or on <a target="_blank" href="https://github.com/gdarko/woocommerce-polylang-integration">Github!</a>', 'woocommerce-polylang-integration' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
		<?php
		$content = ob_get_clean();
		$this->notices->add_custom( 'welcome', $content, NoticesManager::DISMISS_FOREVER );
	}

	/**
	 * Removes the pllwc compatibility notice.
	 *
	 * @param bool $display
	 * @param $notice
	 *
	 * @return bool
	 */
	public function remove_pllwc_notice( $display, $notice ) {
		return $notice === 'pllwc' ? false : $display;
	}

	/**
	 * Admin scripts and styles
	 */
	public function enqueues() {
		wp_enqueue_style( 'wcpllint', WPIDG_URL . 'assets/admin.css', array(), filemtime( WPIDG_PATH . 'assets/admin.css' ), 'all' );
	}

}