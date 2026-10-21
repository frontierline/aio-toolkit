<?php
/**
 * Admin settings page (Settings API).
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the settings page, sections, fields and sanitization.
 */
class Settings {

	/**
	 * Option name (single serialized array).
	 */
	const OPTION = 'flaio_options';

	/**
	 * Settings page slug.
	 */
	const PAGE = 'frontierline-aio-toolkit';

	/**
	 * Tabs definition.
	 *
	 * @return array
	 */
	private function tabs() {
		return array(
			'basic'  => __( 'Basic', 'frontierline-aio-toolkit' ),
			'llms'   => __( 'llms.txt', 'frontierline-aio-toolkit' ),
			'robots' => __( 'robots.txt', 'frontierline-aio-toolkit' ),
			'schema' => __( 'JSON-LD', 'frontierline-aio-toolkit' ),
		);
	}

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . FLAIO_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 */
	public function add_menu() {
		add_options_page(
			__( 'AIO Toolkit', 'frontierline-aio-toolkit' ),
			__( 'AIO Toolkit', 'frontierline-aio-toolkit' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a Settings link on the plugins list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url  = admin_url( 'options-general.php?page=' . self::PAGE );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'frontierline-aio-toolkit' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/**
	 * Enqueue admin assets only on our page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'flaio-admin',
			FLAIO_URL . 'admin/assets/css/admin.css',
			array(),
			FLAIO_VERSION
		);
	}

	/**
	 * Register the setting, sections and fields.
	 */
	public function register() {
		register_setting(
			'flaio_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		// Basic.
		add_settings_section( 'flaio_basic', __( 'Site information', 'frontierline-aio-toolkit' ), '__return_false', self::PAGE . '_basic' );
		$this->field( 'basic', 'site_name', __( 'Site name', 'frontierline-aio-toolkit' ), 'text' );
		$this->field( 'basic', 'site_summary', __( 'Site summary', 'frontierline-aio-toolkit' ), 'textarea' );
		$this->field( 'basic', 'company_info', __( 'Company info', 'frontierline-aio-toolkit' ), 'textarea' );

		// llms.txt.
		add_settings_section( 'flaio_llms', __( 'LLM feeds', 'frontierline-aio-toolkit' ), '__return_false', self::PAGE . '_llms' );
		$this->field( 'llms', 'llms_enabled', __( 'Enable llms.txt', 'frontierline-aio-toolkit' ), 'checkbox' );
		$this->field( 'llms', 'llms_full_enabled', __( 'Enable llms-full.txt', 'frontierline-aio-toolkit' ), 'checkbox' );
		$this->field( 'llms', 'llms_full_max_kb', __( 'llms-full.txt max size (KB)', 'frontierline-aio-toolkit' ), 'number' );

		// robots.txt.
		add_settings_section( 'flaio_robots', __( 'AI crawlers', 'frontierline-aio-toolkit' ), array( $this, 'robots_intro' ), self::PAGE . '_robots' );
		$this->field( 'robots', 'robots_enabled', __( 'Allow AI crawlers in robots.txt', 'frontierline-aio-toolkit' ), 'checkbox' );

		// JSON-LD.
		add_settings_section( 'flaio_schema', __( 'Structured data', 'frontierline-aio-toolkit' ), array( $this, 'schema_intro' ), self::PAGE . '_schema' );
		$this->field( 'schema', 'schema_enabled', __( 'Enable JSON-LD output', 'frontierline-aio-toolkit' ), 'checkbox' );
		$this->field( 'schema', 'org_name', __( 'Organization name', 'frontierline-aio-toolkit' ), 'text' );
		$this->field( 'schema', 'org_logo', __( 'Organization logo URL', 'frontierline-aio-toolkit' ), 'url' );
	}

	/**
	 * robots section intro text.
	 */
	public function robots_intro() {
		echo '<p>' . esc_html__( 'Adds Allow rules for major AI crawlers to your virtual robots.txt.', 'frontierline-aio-toolkit' ) . '</p>';
	}

	/**
	 * Known SEO plugins that already output schema.org markup.
	 *
	 * @return string[] Detected plugin names.
	 */
	private function detected_seo_plugins() {
		$known = array(
			'WPSEO_VERSION'      => 'Yoast SEO',
			'RANK_MATH_VERSION'  => 'Rank Math SEO',
			'AIOSEO_VERSION'     => 'All in One SEO',
			'SEOPRESS_VERSION'   => 'SEOPress',
		);
		$found = array();
		foreach ( $known as $constant => $label ) {
			if ( defined( $constant ) ) {
				$found[] = $label;
			}
		}
		return $found;
	}

	/**
	 * Structured data section intro: warn about duplicate schema output.
	 */
	public function schema_intro() {
		$found = $this->detected_seo_plugins();
		if ( empty( $found ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info inline"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma separated list of detected SEO plugin names. */
					__( '%s already outputs schema.org markup. Keeping both is valid, but you can turn JSON-LD off here to avoid duplicate structured data.', 'frontierline-aio-toolkit' ),
					implode( ', ', $found )
				)
			)
		);
	}

	/**
	 * Register a single field.
	 *
	 * @param string $tab   Tab key.
	 * @param string $key   Option key.
	 * @param string $label Field label.
	 * @param string $type  Input type.
	 */
	private function field( $tab, $key, $label, $type ) {
		$section = 'flaio_' . $tab;
		add_settings_field(
			$key,
			$label,
			array( $this, 'render_field' ),
			self::PAGE . '_' . $tab,
			$section,
			array(
				'key'   => $key,
				'type'  => $type,
				'label' => $label,
			)
		);
	}

	/**
	 * Render a field control.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( $args ) {
		$key   = $args['key'];
		$type  = $args['type'];
		$name  = self::OPTION . '[' . $key . ']';
		$value = Plugin::option( $key, $this->default_for( $key ) );

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( '1', (string) $value, false ),
					esc_html__( 'Enabled', 'frontierline-aio-toolkit' )
				);
				break;
			case 'textarea':
				printf(
					'<textarea name="%1$s" rows="4" class="large-text">%2$s</textarea>',
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;
			case 'number':
				printf(
					'<input type="number" min="1" max="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( (string) Llms_Generator::MAX_KB_LIMIT ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			case 'url':
				printf(
					'<input type="url" name="%1$s" value="%2$s" class="regular-text" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
			default:
				printf(
					'<input type="text" name="%1$s" value="%2$s" class="regular-text" />',
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
		}
	}

	/**
	 * Defaults per key.
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	private function default_for( $key ) {
		$defaults = array(
			'llms_enabled'      => '1',
			'llms_full_enabled' => '1',
			'llms_full_max_kb'  => 500,
			'robots_enabled'    => '1',
			'schema_enabled'    => '1',
			'org_name'          => get_bloginfo( 'name' ),
		);
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}

	/**
	 * Sanitize the whole option array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		// Every field is a scalar; nested arrays in a crafted request would
		// otherwise reach trim() / (string) casts below.
		$input = array_filter( $input, 'is_scalar' );
		$clean = get_option( self::OPTION, array() );
		$clean = is_array( $clean ) ? $clean : array();

		/*
		 * Which tab was submitted, so unchecked boxes on that tab reset to 0.
		 * The marker travels inside the submitted option array itself, so this
		 * callback never has to read a superglobal and behaves correctly when
		 * the option is updated programmatically.
		 */
		$tab = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';
		unset( $input['_tab'], $clean['_tab'] );

		$text_keys      = array( 'site_name', 'org_name' );
		$textarea_keys  = array( 'site_summary', 'company_info' );
		$checkbox_keys  = array( 'llms_enabled', 'llms_full_enabled', 'robots_enabled', 'schema_enabled' );

		// An emptied field is stored as "unset" so the built-in default applies again.
		foreach ( $text_keys as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$value = sanitize_text_field( $input[ $k ] );
				if ( '' === $value ) {
					unset( $clean[ $k ] );
				} else {
					$clean[ $k ] = $value;
				}
			}
		}
		foreach ( $textarea_keys as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$value = sanitize_textarea_field( $input[ $k ] );
				if ( '' === $value ) {
					unset( $clean[ $k ] );
				} else {
					$clean[ $k ] = $value;
				}
			}
		}
		if ( isset( $input['org_logo'] ) ) {
			$logo = esc_url_raw( trim( $input['org_logo'] ) );
			if ( '' === $logo ) {
				unset( $clean['org_logo'] );
			} else {
				$clean['org_logo'] = $logo;
			}
		}
		if ( isset( $input['llms_full_max_kb'] ) ) {
			// Same rule as the text fields: an emptied box restores the default
			// rather than clamping absint( '' ) up to a useless 1 KB cap.
			$raw = trim( (string) $input['llms_full_max_kb'] );
			if ( '' === $raw ) {
				unset( $clean['llms_full_max_kb'] );
			} else {
				$clean['llms_full_max_kb'] = min(
					Llms_Generator::MAX_KB_LIMIT,
					max( 1, absint( $raw ) )
				);
			}
		}

		/*
		 * Checkboxes. Any value actually present in the input is carried over;
		 * only the boxes of the submitted tab are reset to 0 when absent.
		 *
		 * Keeping these two steps apart matters: the first time this option is
		 * written, update_option() delegates to add_option() and each of them
		 * runs the sanitize callback, so this method sees its own output a
		 * second time - with _tab already stripped. Without the carry-over
		 * below, that second pass would silently drop the checkbox states the
		 * first pass had just decided.
		 */
		foreach ( $checkbox_keys as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$clean[ $k ] = ( '1' === (string) $input[ $k ] ) ? '1' : '0';
			}
		}

		$tab_checkboxes = array(
			'llms'   => array( 'llms_enabled', 'llms_full_enabled' ),
			'robots' => array( 'robots_enabled' ),
			'schema' => array( 'schema_enabled' ),
		);
		if ( isset( $tab_checkboxes[ $tab ] ) ) {
			foreach ( $tab_checkboxes[ $tab ] as $k ) {
				if ( ! isset( $input[ $k ] ) ) {
					$clean[ $k ] = '0';
				}
			}
		}

		return $clean;
	}

	/**
	 * Render the settings page (delegates to partial).
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tabs   = $this->tabs();
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'basic'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'basic';
		}
		$page = self::PAGE;
		require FLAIO_DIR . 'admin/partials/settings-page.php';
	}
}
