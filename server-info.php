<?php
/**
 * Plugin Name: Server Info - System Health & Diagnostics Suite
 * Plugin URI: https://wordpress.org/plugins/server-info/
 * Description: The ultimate dashboard to monitor server configuration, database health, caching performance, and critical WordPress diagnostics in real-time.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.3
 * Author: Usman Ali Qureshi
 * Author URI: https://usmanaliqureshi.com/
 * Text Domain: server-info
 * Domain Path: /languages/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Set the plugin version.
define( 'SERVER_INFO_PLUGIN_VERSION', '1.0.0' );

// Set the plugin file.
define( 'SERVER_INFO_PLUGIN_FILE', __FILE__ );

// Set the absolute path for the plugin.
define( 'SERVER_INFO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Set the plugin URL root.
define( 'SERVER_INFO_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

/**
 * Class Server_Info
 *
 * Main plugin class that handles the collection and display of server information.
 *
 * @package Server_Info
 * @since 0.0.1
 */
class Server_Info {

	/**
	 * Singleton instance static property.
	 *
	 * @var Server_Info|bool
	 * @since 0.0.1
	 */
	static $instance = false;

	/**
	 * Retrieves the singleton instance of the class.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return Server_Info The singleton instance.
	 */
	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Detect the current environment.
	 *
	 * @return string
	 */
	public static function get_environment_type() {
		$env = 'Production';
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$env = ucfirst( wp_get_environment_type() );
		}
		
		$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		if ( strpos( $host, '.local' ) !== false || strpos( $host, '.test' ) !== false || strpos( $host, 'localhost' ) !== false ) {
			$env = 'Local';
		} elseif ( strpos( $host, 'staging.' ) !== false || strpos( $host, 'dev.' ) !== false ) {
			$env = 'Staging';
		}
		
		return $env;
	}

	/**
	 * Plugin constructor.
	 *
	 * @since 0.0.1
	 * @access private
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initializes plugin hooks and actions.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_hud' ), 999 );

		// Footer HUD
		add_filter( 'admin_footer_text', array( $this, 'add_admin_footer_text' ), 999 );
		add_filter( 'update_footer', array( $this, 'add_update_footer_text' ), 999 );
	}

	/**
	 * Add Always-On HUD to WordPress Admin Bar.
	 */
	public function add_admin_bar_hud( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$env = self::get_environment_type();
		$env_color = '#d63638'; // Red for Production
		if ( 'Local' === $env ) {
			$env_color = '#00a32a'; // Green for Local
		} elseif ( 'Staging' === $env ) {
			$env_color = '#dba617'; // Orange for Staging
		}

		$php_version = substr( phpversion(), 0, 3 );
		
		$memory_usage = memory_get_peak_usage( true );
		$memory_limit_bytes = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );

		if ( $memory_limit_bytes > 0 ) {
			$percentage = round( ( $memory_usage / $memory_limit_bytes ) * 100 );
			$memory_percentage = $percentage . '%';
		} else {
			$memory_percentage = size_format( $memory_usage );
		}

		$badge_html = '<span style="display:inline-block; padding:0 6px; border-radius:3px; background-color:' . esc_attr( $env_color ) . '; color:#fff; font-weight:bold; font-size:11px; text-transform:uppercase; margin-right:8px; line-height:1.6;">' . esc_html( $env ) . '</span>';
		$title_html = $badge_html . ' PHP ' . esc_html( $php_version ) . ' | RAM ' . esc_html( $memory_percentage );

		$wp_admin_bar->add_node( array(
			'id'    => 'si_admin_bar_hud',
			'title' => $title_html,
			'href'  => admin_url( 'options-general.php?page=server_info_display' ),
			'meta'  => array(
				'title' => esc_attr__( 'Server Info', 'server-info' ),
			),
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_ip',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Server IP: ', 'server-info' ) . ( isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1' ),
		) );

		$web_server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
		if ( strlen( $web_server ) > 30 ) {
			$web_server = substr( $web_server, 0, 27 ) . '...';
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_web_server',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Web Server: ', 'server-info' ) . $web_server,
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_os',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Operating System: ', 'server-info' ) . PHP_OS,
		) );

		global $wp_version, $wpdb;
		$db_version = $wpdb->get_var( 'SELECT VERSION()' );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_db',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'Database: ', 'server-info' ) . $db_version,
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_wp',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'WordPress: ', 'server-info' ) . $wp_version,
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_php_limit',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'PHP Memory Limit: ', 'server-info' ) . ini_get( 'memory_limit' ),
		) );

		$wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
		$wp_admin_bar->add_node( array(
			'id'     => 'si_hud_wp_limit',
			'parent' => 'si_admin_bar_hud',
			'title'  => esc_html__( 'WP Memory Limit: ', 'server-info' ) . $wp_memory_limit,
		) );
	}

	/**
	 * Add server info to the left side of the admin footer.
	 *
	 * @param string $text The existing footer text.
	 * @return string
	 */
	public function add_admin_footer_text( $text ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $text;
		}

		$memory_usage = memory_get_peak_usage( true );
		$memory_limit_bytes = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		
		$memory_str = size_format( $memory_usage );
		$percentage = '';
		if ( $memory_limit_bytes > 0 ) {
			$percentage = ' (' . round( ( $memory_usage / $memory_limit_bytes ) * 100 ) . '%)';
			$memory_str .= ' of ' . size_format( $memory_limit_bytes );
		}
		
		$wp_memory_limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
		$ip = isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
		$hostname = gethostname();
		if ( ! $hostname && isset( $_SERVER['SERVER_NAME'] ) ) {
			$hostname = $_SERVER['SERVER_NAME'];
		}
		
		$php_version = phpversion();
		$os = PHP_OS;

		$info = ' <span style="color:#666;">| Memory: ' . $memory_str . $percentage . ' | WP LIMIT: ' . $wp_memory_limit . ' | IP ' . $ip . ' (' . $hostname . ') | PHP ' . $php_version . ' @' . $os . '</span>';

		return $text . $info;
	}

	/**
	 * Add server info to the right side of the admin footer.
	 *
	 * @param string $text The existing update footer text.
	 * @return string
	 */
	public function add_update_footer_text( $text ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $text;
		}

		global $wp_version, $wpdb;
		$php_version = phpversion();
		$web_server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
		
		// Truncate overly long web server strings (like nginx/1.26.1)
		if ( strlen( $web_server ) > 30 ) {
			$web_server = substr( $web_server, 0, 27 ) . '...';
		}
		
		$db_version = $wpdb->get_var( 'SELECT VERSION()' );

		$info = '<span style="color:#666;">WordPress ' . $wp_version . ' | PHP ' . $php_version . ' | Server: ' . $web_server . ' | MySQL ' . $db_version . '</span>';

		return $info;
	}



	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function load_i18n() {
		load_plugin_textdomain( 'server-info', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Enqueues admin styles and scripts and sets dynamic theme variables.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function admin_scripts( $hook ) {
		if ( 'settings_page_server_info_display' !== $hook && 'index.php' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 'server-info', SERVER_INFO_PLUGIN_URL . 'assets/css/style.css', array(), SERVER_INFO_PLUGIN_VERSION, 'all' );

		$color_scheme = get_user_option( 'admin_color' );
		global $_wp_admin_css_colors;
		
		if ( isset( $_wp_admin_css_colors[ $color_scheme ] ) ) {
			$colors = $_wp_admin_css_colors[ $color_scheme ]->colors;
			// 0 = Base, 1 = Highlight, 2 = Notification, 3 = Action
			$color_1 = isset( $colors[0] ) ? $colors[0] : '#1e1e1e';
			$color_2 = isset( $colors[1] ) ? $colors[1] : '#0073aa';
			$color_3 = isset( $colors[2] ) ? $colors[2] : '#00a0d2';
			$color_4 = isset( $colors[3] ) ? $colors[3] : '#82878c';

			$custom_css = "
				:root {
					--si-theme-1: {$color_1};
					--si-theme-2: {$color_2};
					--si-theme-3: {$color_3};
					--si-theme-4: {$color_4};
				}
			";
			wp_add_inline_style( 'server-info', $custom_css );
		}
	}

	/**
	 * Registers the plugin options page under Settings.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function add_plugin_menu() {
		add_options_page(
			esc_html__( 'Server Information', 'server-info' ),
			esc_html__( 'Server Info', 'server-info' ),
			'manage_options',
			'server_info_display',
			array( 'Server_Info', 'display_server_info' )
		);
	}

	/**
	 * Registers the dashboard widget.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public function add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'serverinfo_dashboard_widget',
			esc_html__( 'Server Info', 'server-info' ),
			array( 'server_info', 'display_dashboard_widget' )
		);
	}

	/**
	 * Renders the dashboard widget content.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public static function display_dashboard_widget() {
		$info = self::site_info();
		?>
        <div class="si-card" style="box-shadow: none; padding: 0; background: transparent;">
            <ul class="si-list">
				<?php
				$fields_to_show = array( 'operating_system', 'server_ip', 'server_hostname', 'php_version' );
				foreach ( $fields_to_show as $key ) {
					if ( isset( $info['wp-server']['fields'][ $key ] ) ) {
						$field = $info['wp-server']['fields'][ $key ];
						?>
                        <li class="si-list-item">
                            <span class="si-item-label"><?php echo esc_html( $field['label'] ); ?></span>
                            <span class="si-item-value"><?php echo esc_html( $field['value'] ); ?></span>
                        </li>
						<?php
					}
				}
				?>
            </ul>
            <div style="margin-top: 15px;">
                <a class="si-btn" style="width: 100%; text-align: center; display: block;" href="<?php echo esc_url( admin_url( 'options-general.php?page=server_info_display' ) ); ?>"><?php esc_html_e( 'View More Information', 'server-info' ); ?></a>
            </div>
        </div>
		<?php
	}

	/**
	 * Gathers and structures all server and WordPress information.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return array Multi-dimensional array containing server data.
	 */
	public static function site_info() {
		global $wpdb;

		// Set up the array that holds all information.
		$info = array();

		$info['wp-server'] = array(
			'label'  => esc_html__( 'Hosting Server Information', 'server-info' ),
			'fields' => array(),
		);

		if ( function_exists( 'phpversion' ) ) {
			$php_version_debug = phpversion();
			// Whether PHP supports 64-bit.
			$php64bit = ( 64 === PHP_INT_SIZE * 8 );

			$php_version = $php_version_debug;
		} else {
			$php_version = esc_html__( 'Unable to determine PHP version', 'server-info' );
		}

		if ( function_exists( 'php_uname' ) ) {
			$server_architecture = sprintf( '%s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'm' ) );
			$os_family = php_uname( 's' );
			
			// Extract specific Linux distribution based on community feedback
			if ( 'Linux' === $os_family ) {
				if ( @is_readable( '/etc/os-release' ) ) {
					$os_release = @parse_ini_file( '/etc/os-release' );
					if ( ! empty( $os_release['PRETTY_NAME'] ) ) {
						$server_architecture .= ' (' . trim( $os_release['PRETTY_NAME'], '"\'' ) . ')';
					}
				} elseif ( @is_readable( '/etc/issue' ) ) {
					$issue = @file_get_contents( '/etc/issue' );
					if ( $issue ) {
						$parts = explode( '\\', $issue );
						if ( ! empty( $parts[0] ) ) {
							$server_architecture .= ' (' . trim( $parts[0] ) . ')';
						}
					}
				}
			}
		} else {
			$server_architecture = 'unknown';
		}
		$info['wp-server']['fields']['operating_system'] = array(
			'label' => esc_html__( 'Operating System', 'server-info' ),
			'value' => ( 'unknown' !== $server_architecture ? $server_architecture : esc_html__( 'Unable to determine server architecture', 'server-info' ) )
		);

		if ( function_exists( 'php_uname' ) ) {
			$info['wp-server']['fields']['server_hostname'] = array(
				'label' => esc_html__( 'Server Hostname', 'server-info' ),
				'value' => php_uname( 'n' )
			);
		}

		if ( isset( $_SERVER['SERVER_ADDR'] ) ) {
			$info['wp-server']['fields']['server_ip'] = array(
				'label' => esc_html__( 'Server IP', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_ADDR'] )
			);
		}

		if ( isset( $_SERVER['SERVER_PROTOCOL'] ) ) {
			$info['wp-server']['fields']['server_protocol'] = array(
				'label' => esc_html__( 'Server Protocol', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_PROTOCOL'] )
			);
		}

		if ( isset( $_SERVER['SERVER_ADMIN'] ) ) {
			$info['wp-server']['fields']['server_administrator'] = array(
				'label' => esc_html__( 'Server Administrator', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_ADMIN'] )
			);
		}

		if ( isset( $_SERVER['SERVER_PORT'] ) ) {
			$info['wp-server']['fields']['server_web_port'] = array(
				'label' => esc_html__( 'Server Web Port', 'server-info' ),
				'value' => esc_html( $_SERVER['SERVER_PORT'] )
			);
		}

		$uptime = '';
		$disable_functions = ini_get( 'disable_functions' );
		if ( function_exists( 'exec' ) && is_callable( 'exec' ) && ( ! is_string( $disable_functions ) || false === stripos( $disable_functions, 'exec' ) ) ) {
			$uptime = @exec( "uptime" );
		}
		if ( ! empty( $uptime ) ) {
			$info['wp-server']['fields']['system_uptime'] = array(
				'label' => esc_html__( 'System Uptime', 'server-info' ),
				'value' => esc_html( $uptime )
			);
		}

		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			if ( ! empty( $load ) ) {
				$info['wp-server']['fields']['load_average'] = array(
					'label' => esc_html__( 'Load Average', 'server-info' ),
					'value' => implode( ', ', $load )
				);
			}
		}

		if ( function_exists( 'memory_get_usage' ) ) {
			$info['wp-server']['fields']['memory_usage'] = array(
				'label' => esc_html__( 'PHP Memory Usage', 'server-info' ),
				'value' => number_format( memory_get_usage( true ) / 1048576, 2 ) . ' MB'
			);
		}

		$extensions = array( 'curl', 'mbstring', 'gd', 'imagick', 'zip', 'redis', 'memcached', 'opcache' );
		$active_exts = array();
		foreach ( $extensions as $ext ) {
			if ( extension_loaded( $ext ) ) {
				$active_exts[] = $ext;
			}
		}
		if ( ! empty( $active_exts ) ) {
			$info['wp-server']['fields']['active_extensions'] = array(
				'label' => esc_html__( 'Active PHP Extensions', 'server-info' ),
				'value' => implode( ', ', $active_exts )
			);
		}

		$wp_config_path = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config_path ) ) {
			$info['wp-server']['fields']['wp_config_perms'] = array(
				'label' => esc_html__( 'wp-config.php Permissions', 'server-info' ),
				'value' => substr( sprintf( '%o', fileperms( $wp_config_path ) ), -4 )
			);
		}

		$upload_dir = wp_upload_dir();
		$uploads_path = $upload_dir['basedir'];
		$content_path = WP_CONTENT_DIR;

		if ( file_exists( $content_path ) ) {
			$info['wp-server']['fields']['wp_content_perms'] = array(
				'label' => esc_html__( 'wp-content Permissions', 'server-info' ),
				'value' => substr( sprintf( '%o', fileperms( $content_path ) ), -4 ),
			);
		}

		if ( file_exists( $uploads_path ) ) {
			$info['wp-server']['fields']['uploads_perms'] = array(
				'label' => esc_html__( 'Uploads Directory Permissions', 'server-info' ),
				'value' => substr( sprintf( '%o', fileperms( $uploads_path ) ), -4 ),
			);
		}

		$info['wp-server']['fields']['httpd_software'] = array(
			'label' => esc_html__( 'Web server', 'server-info' ),
			'value' => ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : esc_html__( 'Unable to determine what web server software is used', 'server-info' ) )
		);

		$info['wp-server']['fields']['php_version'] = array(
			'label' => esc_html__( 'PHP version', 'server-info' ),
			'value' => $php_version,
		);

		// Some servers disable `ini_set()` and `ini_get()`, we check this before trying to get configuration values.
		if ( function_exists( 'ini_get' ) ) {
			$info['wp-server']['fields']['memory_limit'] = array(
				'label' => esc_html__( 'PHP memory limit', 'server-info' ),
				'value' => ini_get( 'memory_limit' ),
			);
		}

		// Server Location via IP-API
		$location = get_transient( 'si_server_location' );
		if ( false === $location ) {
			$response = wp_remote_get( 'http://ip-api.com/json/' );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				if ( $body && 'success' === $body->status ) {
					$location = sprintf( '%s, %s (%s)', $body->city, $body->country, $body->isp );
					set_transient( 'si_server_location', $location, 30 * DAY_IN_SECONDS );
				} else {
					$location = esc_html__( 'Unknown', 'server-info' );
				}
			} else {
				$location = esc_html__( 'Unknown', 'server-info' );
			}
		}

		$info['wp-server']['fields']['server_location'] = array(
			'label' => esc_html__( 'Datacenter Location', 'server-info' ),
			'value' => $location,
		);

		if ( isset( $_SERVER['GATEWAY_INTERFACE'] ) ) {
			$info['wp-server']['fields']['CGI_version'] = array(
				'label' => esc_html__( 'CGI Version', 'server-info' ),
				'value' => esc_html( $_SERVER['GATEWAY_INTERFACE'] )
			);
		}

		/**
		 * Database
		 */
		$info['wp-database'] = array(
			'label'  => esc_html__( 'Database', 'server-info' ),
			'fields' => array(),
		);

		// Populate the database fields.
		if ( is_resource( $wpdb->dbh ) ) {
			// Old mysql extension.
			$extension = 'mysql';
		} else if ( is_object( $wpdb->dbh ) ) {
			// mysqli or PDO.
			$extension = get_class( $wpdb->dbh );
		} else {
			// Unknown sql extension.
			$extension = null;
		}

		$server = $wpdb->get_var( 'SELECT VERSION()' );

		if ( isset( $wpdb->use_mysqli ) && $wpdb->use_mysqli && isset( $wpdb->dbh->client_info ) ) {
			$client_version = $wpdb->dbh->client_info;
		} else {
			if ( function_exists('mysql_get_client_info') && preg_match( '|[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2}|', @mysql_get_client_info(), $matches ) ) {
				$client_version = $matches[0];
			} else {
				$client_version = null;
			}
		}

		$info['wp-database']['fields']['extension'] = array(
			'label' => esc_html__( 'Extension', 'server-info' ),
			'value' => $extension,
		);

		$info['wp-database']['fields']['server_version'] = array(
			'label' => esc_html__( 'Server version', 'server-info' ),
			'value' => $server,
		);

		$info['wp-database']['fields']['client_version'] = array(
			'label' => esc_html__( 'Client version', 'server-info' ),
			'value' => $client_version,
		);

		$info['wp-database']['fields']['database_user'] = array(
			'label'   => esc_html__( 'Database username', 'server-info' ),
			'value'   => $wpdb->dbuser,
			'private' => true,
		);

		$info['wp-database']['fields']['database_host'] = array(
			'label'   => esc_html__( 'Database host', 'server-info' ),
			'value'   => $wpdb->dbhost,
			'private' => true,
		);

		$info['wp-database']['fields']['database_name'] = array(
			'label'   => esc_html__( 'Database name', 'server-info' ),
			'value'   => $wpdb->dbname,
			'private' => true,
		);

		$db_size = $wpdb->get_var( "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . esc_sql( $wpdb->dbname ) . "'" );
		if ( $db_size ) {
			$info['wp-database']['fields']['database_size'] = array(
				'label' => esc_html__( 'Total Database size', 'server-info' ),
				'value' => number_format( $db_size / 1048576, 2 ) . ' MB',
			);

			// Top 5 largest tables
			$top_tables = $wpdb->get_results( "
				SELECT table_name AS name, 
				       round(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
				FROM information_schema.TABLES 
				WHERE table_schema = '" . DB_NAME . "'
				ORDER BY (data_length + index_length) DESC 
				LIMIT 5
			" );

			if ( ! empty( $top_tables ) ) {
				$tables_html = array();
				foreach ( $top_tables as $tbl ) {
					$tables_html[ $tbl->name ] = $tbl->size_mb . ' MB';
				}
				$info['wp-database']['fields']['top_tables'] = array(
					'label' => esc_html__( 'Top 5 Largest Tables', 'server-info' ),
					'value' => $tables_html,
				);
			}
		}

		$info['wp-database']['fields']['database_prefix'] = array(
			'label'   => esc_html__( 'Table prefix', 'server-info' ),
			'value'   => $wpdb->prefix,
			'private' => true,
		);

		$info['wp-database']['fields']['database_charset'] = array(
			'label'   => esc_html__( 'Database charset', 'server-info' ),
			'value'   => $wpdb->charset,
			'private' => true,
		);

		$info['wp-database']['fields']['database_collate'] = array(
			'label'   => esc_html__( 'Database collation', 'server-info' ),
			'value'   => $wpdb->collate,
			'private' => true,
		);

		$max_connections = $wpdb->get_var( "SHOW VARIABLES LIKE 'max_connections'", 1 );
		$max_allowed_packet = $wpdb->get_var( "SHOW VARIABLES LIKE 'max_allowed_packet'", 1 );

		if ( $max_connections ) {
			$info['wp-database']['fields']['max_connections'] = array(
				'label' => esc_html__( 'Max Connections', 'server-info' ),
				'value' => $max_connections,
			);
		}

		if ( $max_allowed_packet ) {
			$info['wp-database']['fields']['max_allowed_packet'] = array(
				'label' => esc_html__( 'Max Allowed Packet', 'server-info' ),
				'value' => size_format( $max_allowed_packet ),
			);
		}

		/**
		 * Caching & Performance
		 */
		$info['wp-caching'] = array(
			'label'  => esc_html__( 'Caching & Performance', 'server-info' ),
			'fields' => array(),
		);

		$object_cache_file = WP_CONTENT_DIR . '/object-cache.php';
		$info['wp-caching']['fields']['object_cache'] = array(
			'label' => esc_html__( 'Object Cache Drop-in', 'server-info' ),
			'value' => file_exists( $object_cache_file ) ? esc_html__( 'Active', 'server-info' ) : esc_html__( 'Inactive', 'server-info' ),
		);

		if ( function_exists( 'opcache_get_status' ) ) {
			// Suppress warnings in case OPcache is restricted by config
			$opcache = @opcache_get_status( false );
			if ( is_array( $opcache ) && ! empty( $opcache['opcache_enabled'] ) ) {
				$hit_rate = 0;
				if ( isset( $opcache['opcache_statistics']['opcache_hit_rate'] ) ) {
					$hit_rate = round( $opcache['opcache_statistics']['opcache_hit_rate'], 2 );
				}
				$info['wp-caching']['fields']['opcache_status'] = array(
					'label' => esc_html__( 'OPcache Status', 'server-info' ),
					'value' => sprintf( esc_html__( 'Enabled (Hit Rate: %s%%)', 'server-info' ), $hit_rate ),
				);
			} else {
				$info['wp-caching']['fields']['opcache_status'] = array(
					'label' => esc_html__( 'OPcache Status', 'server-info' ),
					'value' => esc_html__( 'Disabled or Restricted', 'server-info' ),
				);
			}
		}

		/**
		 * WordPress Information
		 */
		$is_multisite    = is_multisite();
		$info['wp-info'] = array(
			'label'  => esc_html__( 'WordPress Information', 'server-info' ),
			'fields' => array(
				'multisite' => array(
					'label' => esc_html__( 'Is this a multisite?', 'server-info' ),
					'value' => $is_multisite ? esc_html__( 'Yes', 'server-info' ) : esc_html__( 'No', 'server-info' ),
				),
			),
		);

		if ( is_multisite() ) {
			$network_query = new WP_Network_Query();
			$network_ids   = $network_query->query(
				array(
					'fields'        => 'ids',
					'number'        => 100,
					'no_found_rows' => false,
				)
			);

			$site_count = 0;
			foreach ( $network_ids as $network_id ) {
				$site_count += get_blog_count( $network_id );
			}

			$info['wp-info']['fields']['user_count'] = array(
				'label' => esc_html__( 'User count', 'server-info' ),
				'value' => get_user_count(),
			);

			$info['wp-info']['fields']['site_count'] = array(
				'label' => esc_html__( 'Site count', 'server-info' ),
				'value' => $site_count,
			);

			$info['wp-info']['fields']['network_count'] = array(
				'label' => esc_html__( 'Network count', 'server-info' ),
				'value' => $network_query->found_networks,
			);
		} else {
			$user_count = count_users();

			$info['wp-info']['fields']['user_count'] = array(
				'label' => esc_html__( 'User count', 'server-info' ),
				'value' => $user_count['total_users'],
			);
		}

		$active_theme              = wp_get_theme();
		$info['wp-info']['fields'] = array(
			'name' => array(
				'label' => esc_html__( 'Active Theme', 'server-info' ),
				'value' => sprintf(
					esc_html__( '%1$s (%2$s)', 'server-info' ),
					$active_theme->name,
					$active_theme->stylesheet
				),
			),
		);

		// List all available plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins          = get_plugins();
		$plugins_active   = array();
		$plugins_inactive = array();

		foreach ( $plugins as $plugin_path => $plugin ) {
			$plugin_author = $plugin['Author'];

			if ( ! empty( $plugin_author ) ) {
				$plugin_author = sprintf( esc_html__( 'By %s', 'server-info' ), $plugin_author );
			} else {
				$plugin_author = '';
			}

			if ( is_plugin_active( $plugin_path ) ) {
				$plugins_active[ $plugin['Name'] ] = $plugin_author;
			} else {
				$plugins_inactive[ $plugin['Name'] ] = $plugin_author;
			}
		}

		if ( empty( $plugins_active ) ) {
			$plugins_active = esc_html__( 'None', 'server-info' );
		}
		$info['wp-info']['fields']['plugins_active'] = array(
			'label' => esc_html__( 'Active Plugins', 'server-info' ),
			'value' => $plugins_active,
		);

		if ( empty( $plugins_inactive ) ) {
			$plugins_inactive = esc_html__( 'None', 'server-info' );
		}
		$info['wp-info']['fields']['plugins_inactive'] = array(
			'label' => esc_html__( 'Inactive Plugins', 'server-info' ),
			'value' => $plugins_inactive,
		);

		$info['wp-info']['fields']['WP_MEMORY_LIMIT'] = array(
			'label' => esc_html__( 'WordPress Memory Limit', 'server-info' ),
			'value' => WP_MEMORY_LIMIT,
		);

		$info['wp-info']['fields']['WP_MAX_MEMORY_LIMIT'] = array(
			'label' => esc_html__( 'WordPress Max Memory Limit', 'server-info' ),
			'value' => WP_MAX_MEMORY_LIMIT,
		);

		$info['wp-info']['fields']['WP_DEBUG'] = array(
			'label' => esc_html__( 'WordPress Debugging', 'server-info' ),
			'value' => WP_DEBUG ? esc_html__( 'Enabled', 'server-info' ) : esc_html__( 'Disabled', 'server-info' )
		);

		$cron_status = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? esc_html__( 'Disabled via wp-config.php', 'server-info' ) : esc_html__( 'Enabled', 'server-info' );
		$info['wp-info']['fields']['cron_status'] = array(
			'label' => esc_html__( 'WP-Cron Status', 'server-info' ),
			'value' => $cron_status,
		);

		$cron_array = _get_cron_array();
		if ( ! empty( $cron_array ) ) {
			$upcoming_crons = array();
			$count = 0;
			foreach ( $cron_array as $timestamp => $cron_hooks ) {
				foreach ( $cron_hooks as $hook => $keys ) {
					if ( $count >= 3 ) break 2;
					$time_diff = human_time_diff( current_time( 'timestamp' ), $timestamp );
					$upcoming_crons[ $hook ] = sprintf( esc_html__( 'In %s', 'server-info' ), $time_diff );
					$count++;
				}
			}
			if ( ! empty( $upcoming_crons ) ) {
				$info['wp-info']['fields']['upcoming_crons'] = array(
					'label' => esc_html__( 'Next 3 Cron Events', 'server-info' ),
					'value' => $upcoming_crons,
				);
			}
		}

		return $info;
	}

	/**
	 * Renders the main plugin settings page with tabs.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public static function display_server_info() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'server';
		$info = self::site_info();
		
		// Helper vars for top KPIs
		$php_version = phpversion();
		$memory_usage = 'N/A';
		if ( function_exists('memory_get_usage') ) {
			$memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
		}
		
		global $wp_version, $wpdb;
		$db_version_raw = $wpdb->get_var( 'SELECT VERSION()' );
		$db_type = stripos( $db_version_raw, 'MariaDB' ) !== false ? 'MariaDB' : 'MySQL';
		preg_match( '/[0-9]+(?:\.[0-9]+)*/', $db_version_raw, $matches );
		$db_version = isset($matches[0]) ? $matches[0] : $db_version_raw;
		
		$web_server = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
		if (strlen($web_server) > 15) {
			$web_server = substr($web_server, 0, 15) . '...';
		}
		
		// CPU Load
		$cpu_load = 'N/A';
		$cpu_pct = 0;
		if ( function_exists('sys_getloadavg') ) {
			$load = sys_getloadavg();
			if ( is_array($load) ) {
				$cpu_load = round($load[0], 2);
				$cpu_pct = min(100, round(($load[0] / 4) * 100)); // Rough estimate
			}
		}
		
		// Memory Usage (PHP)
		$mem_used_bytes = memory_get_usage(true);
		$mem_limit_str = ini_get('memory_limit');
		$mem_limit_bytes = wp_convert_hr_to_bytes($mem_limit_str);
		if ( $mem_limit_bytes <= 0 ) {
			$mem_limit_bytes = $mem_used_bytes; 
		}
		$mem_pct = min(100, round(($mem_used_bytes / $mem_limit_bytes) * 100));
		
		// Disk Space
		$disk_pct = 0;
		if ( function_exists('disk_total_space') && function_exists('disk_free_space') ) {
			$disk_total = @disk_total_space( ABSPATH );
			$disk_free = @disk_free_space( ABSPATH );
			if ( $disk_total > 0 ) {
				$disk_used = $disk_total - $disk_free;
				$disk_pct = min(100, round(($disk_used / $disk_total) * 100));
			}
		}

		// Calculate Overall Health Score
		$health_score = 100;
		$health_reasons = array();
		
		// PHP Version check
		$php_v = phpversion();
		if ( version_compare( $php_v, '7.4', '<' ) ) {
			$health_score -= 30;
			$health_reasons[] = "Critical: PHP version is very outdated ($php_v).";
		} elseif ( version_compare( $php_v, '8.3', '<' ) ) {
			$health_score -= 10;
			$health_reasons[] = "Warning: PHP version ($php_v) is below recommended 8.3.";
		}

		// Memory Limit Check
		$mem_limit_int = intval( $mem_limit_str );
		if ( false !== strpos( $mem_limit_str, 'G' ) ) {
			$mem_limit_int *= 1024;
		}
		if ( $mem_limit_int > 0 && $mem_limit_int < 256 ) {
			$health_score -= 10;
			$health_reasons[] = "Warning: Low PHP memory limit ($mem_limit_str).";
		}
		
		// WordPress Core Check
		global $wp_version;
		$core_updates = get_site_transient('update_core');
		if ( isset( $core_updates->updates ) && is_array( $core_updates->updates ) ) {
			foreach ( $core_updates->updates as $update ) {
				if ( $update->response === 'upgrade' ) {
					$health_score -= 10;
					$health_reasons[] = "Warning: WordPress core is outdated.";
					break;
				}
			}
		}

		// Security Check
		$wp_config_path = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config_path ) && is_writable( $wp_config_path ) ) {
			$health_score -= 10;
			$health_reasons[] = "Security: wp-config.php is writable.";
		}
		
		// Resource Checks
		if ( $mem_pct > 90 ) {
			$health_score -= 5;
			$health_reasons[] = "Warning: High memory usage ($mem_pct%).";
		}
		if ( $disk_pct > 90 ) {
			$health_score -= 5;
			$health_reasons[] = "Warning: High disk usage ($disk_pct%).";
		}
		
		$health_score = max(0, $health_score);
		
		// Determine Status
		if ( $health_score >= 90 ) {
			$health_status = 'Excellent';
			$health_color = '#10b981'; // Green
			$health_bg = '#d1fae5';
			$health_msg = 'Your server is running smoothly.';
		} elseif ( $health_score >= 70 ) {
			$health_status = 'Fair';
			$health_color = '#f59e0b'; // Amber
			$health_bg = '#fef3c7';
			$health_msg = 'Needs attention: ' . (isset($health_reasons[0]) ? $health_reasons[0] : 'Suboptimal settings.');
		} else {
			$health_status = 'Critical';
			$health_color = '#ef4444'; // Red
			$health_bg = '#fee2e2';
			$health_msg = 'Urgent: ' . (isset($health_reasons[0]) ? $health_reasons[0] : 'Multiple severe issues.');
		}
		?>
		<div class="wrap"><h1 style="display:none;"></h1></div>
		<div class="server-info-wrapper">
			
			<div class="si-sidebar">
				<div class="si-brand">
					<div class="si-brand-icon"><span class="dashicons dashicons-networking"></span></div>
					<div class="si-brand-text">
						<h2>Server Info <span class="si-version-tag">v<?php echo SERVER_INFO_PLUGIN_VERSION; ?></span></h2>
						<p class="si-brand-sub">System Health & Diagnostics</p>
					</div>
				</div>

				<div class="si-nav">
					<a href="?page=server_info_display&tab=server" class="si-nav-item <?php echo 'server' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Overview', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=database" class="si-nav-item <?php echo 'database' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-database"></span> <?php esc_html_e( 'Database', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=wordpress" class="si-nav-item <?php echo 'wordpress' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-wordpress"></span> <?php esc_html_e( 'WordPress Core', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=phpinfo" class="si-nav-item <?php echo 'phpinfo' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'PHP Information', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=caching" class="si-nav-item <?php echo 'caching' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Caching', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=diagnostics" class="si-nav-item <?php echo 'diagnostics' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Diagnostics & Logs', 'server-info' ); ?>
					</a>
					<a href="?page=server_info_display&tab=more_plugins" class="si-nav-item <?php echo 'more_plugins' === $active_tab ? 'active' : ''; ?>">
						<span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'More Plugins', 'server-info' ); ?>
					</a>
				</div>

				<div class="si-health-widget">
					<h3 class="si-health-title">Overall Health</h3>
					<p class="si-health-score" style="color: <?php echo esc_attr($health_color); ?>"><?php echo esc_html($health_score); ?>%</p>
					<p class="si-health-status" style="color: <?php echo esc_attr($health_color); ?>"><?php echo esc_html($health_status); ?></p>
					<div class="si-health-chart">
						<svg viewBox="0 0 100 30" preserveAspectRatio="none">
							<path d="M0,20 C15,20 15,5 30,5 C45,5 45,25 60,25 C75,25 75,10 90,10 C95,10 98,15 100,20 L100,30 L0,30 Z" fill="<?php echo esc_attr($health_bg); ?>" />
							<path d="M0,20 C15,20 15,5 30,5 C45,5 45,25 60,25 C75,25 75,10 90,10 C95,10 98,15 100,20" fill="none" stroke="<?php echo esc_attr($health_color); ?>" stroke-width="2" />
						</svg>
					</div>
					<p class="si-health-desc"><?php echo esc_html($health_msg); ?></p>
					<a href="?page=server_info_display&tab=diagnostics" class="si-btn-outline">View Health Details &rarr;</a>
				</div>

				<div class="si-rating-widget">
					<div class="si-rating-stars">
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
						<span class="dashicons dashicons-star-filled"></span>
					</div>
					<p class="si-rating-title">Love Server Info?</p>
					<p class="si-rating-desc">Please leave us a 5-star rating!</p>
					<a href="https://wordpress.org/support/plugin/server-info/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer" class="si-btn-outline si-rating-btn">Leave a Rating &rarr;</a>
				</div>
			</div>

			<div class="si-main">
				<div class="si-topbar">
					<div class="si-page-title">
						<h1>Overview</h1>
						<p>Real-time summary of your server health and configuration.</p>
					</div>
					<div class="si-actions">
						<a href="?page=server_info_display" class="si-btn-secondary">
							<span class="dashicons dashicons-update"></span> Refresh Data
						</a>
					</div>
				</div>

				<?php if ( 'server' === $active_tab ) : ?>
				
				<div class="si-kpi-grid">
					<div class="si-kpi-card <?php echo $health_score >= 80 ? 'status-excellent' : 'status-primary'; ?>" style="--si-success: <?php echo esc_attr($health_color); ?>;">
						<div class="si-kpi-icon" style="background: <?php echo esc_attr($health_color); ?>20; color: <?php echo esc_attr($health_color); ?>;"><span class="dashicons dashicons-chart-line"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">Server Health</div>
							<div class="si-kpi-value"><?php echo esc_html($health_score); ?>%</div>
							<div class="si-kpi-status" style="color: <?php echo esc_attr($health_color); ?>;"><?php echo esc_html($health_status); ?></div>
						</div>
					</div>
					<div class="si-kpi-card status-primary">
						<div class="si-kpi-icon purple"><span class="dashicons dashicons-editor-code"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">PHP Version</div>
							<div class="si-kpi-value"><?php echo esc_html($php_version); ?></div>
							<div class="si-kpi-status success">Latest &check;</div>
						</div>
					</div>
					<div class="si-kpi-card status-primary">
						<div class="si-kpi-icon blue"><span class="dashicons dashicons-dashboard"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">Memory Usage</div>
							<div class="si-kpi-value"><?php echo esc_html($memory_usage); ?></div>
							<div class="si-kpi-status neutral">Used / Limit</div>
						</div>
					</div>
					<div class="si-kpi-card status-excellent">
						<div class="si-kpi-icon green"><span class="dashicons dashicons-database"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">Database</div>
							<div class="si-kpi-value">MySQL <?php echo esc_html($db_version); ?></div>
							<div class="si-kpi-status success">Healthy</div>
						</div>
					</div>
					<div class="si-kpi-card status-primary">
						<div class="si-kpi-icon orange"><span class="dashicons dashicons-wordpress"></span></div>
						<div class="si-kpi-content">
							<div class="si-kpi-label">WordPress</div>
							<div class="si-kpi-value"><?php echo esc_html($wp_version); ?></div>
							<div class="si-kpi-status success">Up to date &check;</div>
						</div>
					</div>
				</div>

				<div class="si-dashboard-grid">
					<div class="si-section">
						<div class="si-section-header">
							<h3 class="si-section-title">Hosting Environment</h3>
						</div>
						<ul class="si-table-list">
							<?php
							$fields = array( 'operating_system', 'server_hostname', 'server_ip', 'server_protocol', 'server_port' );
							foreach ( $fields as $key ) {
								if ( isset( $info['wp-server']['fields'][ $key ] ) ) {
									$field = $info['wp-server']['fields'][ $key ];
									?>
									<li class="si-table-row">
										<span class="si-table-label"><span class="dashicons dashicons-networking"></span> <?php echo esc_html( $field['label'] ); ?></span>
										<span class="si-table-val"><?php echo esc_html( $field['value'] ); ?></span>
									</li>
									<?php
								}
							}
							?>
						</ul>
					</div>

					<div class="si-section">
						<div class="si-section-header">
							<h3 class="si-section-title">System Resources</h3>
						</div>
						<div class="si-resources-circles">
							<div class="si-circle-wrapper">
								<div class="si-circle-chart">
									<svg viewBox="0 0 100 100">
										<circle class="si-circle-bg" cx="50" cy="50" r="40"></circle>
										<circle class="si-circle-progress" cx="50" cy="50" r="40" stroke-dasharray="251" stroke-dashoffset="<?php echo esc_attr(251 - (251 * $cpu_pct / 100)); ?>"></circle>
									</svg>
									<div class="si-circle-val"><?php echo esc_html($cpu_load); ?></div>
									<div class="si-circle-label">Load</div>
								</div>
								<div class="si-circle-title">CPU Load</div>
							</div>
							<div class="si-circle-wrapper">
								<div class="si-circle-chart">
									<svg viewBox="0 0 100 100">
										<circle class="si-circle-bg" cx="50" cy="50" r="40"></circle>
										<circle class="si-circle-progress blue" cx="50" cy="50" r="40" stroke-dasharray="251" stroke-dashoffset="<?php echo esc_attr(251 - (251 * $mem_pct / 100)); ?>"></circle>
									</svg>
									<div class="si-circle-val"><?php echo esc_html($mem_pct); ?>%</div>
									<div class="si-circle-label">Used</div>
								</div>
								<div class="si-circle-title">Memory</div>
							</div>
							<div class="si-circle-wrapper">
								<div class="si-circle-chart">
									<svg viewBox="0 0 100 100">
										<circle class="si-circle-bg" cx="50" cy="50" r="40"></circle>
										<circle class="si-circle-progress" cx="50" cy="50" r="40" stroke-dasharray="251" stroke-dashoffset="<?php echo esc_attr(251 - (251 * $disk_pct / 100)); ?>"></circle>
									</svg>
									<div class="si-circle-val"><?php echo esc_html($disk_pct); ?>%</div>
									<div class="si-circle-label">Used</div>
								</div>
								<div class="si-circle-title">Disk Space</div>
							</div>
						</div>
						<div class="si-memory-bars">
							<div class="si-mem-stat">
								<span class="si-mem-label"><span class="dashicons dashicons-minus"></span> Total Memory</span>
								<span class="si-mem-val"><?php echo ini_get('memory_limit'); ?></span>
							</div>
							<div class="si-mem-stat">
								<span class="si-mem-label"><span class="dashicons dashicons-minus" style="color:var(--si-primary);"></span> Used Memory</span>
								<span class="si-mem-val"><?php echo esc_html($memory_usage); ?></span>
							</div>
						</div>
						<div class="si-section-footer">
							<a href="?page=server_info_display&tab=diagnostics">View Performance Details &rarr;</a>
						</div>
					</div>

					<div class="si-section">
						<div class="si-section-header">
							<h3 class="si-section-title">PHP Configuration</h3>
						</div>
						<div class="si-data-grid">
							<?php
							$php_fields = array( 'php_version', 'php_memory_limit', 'php_max_execution_time', 'php_post_max_size' );
							foreach ( $php_fields as $key ) {
								if ( isset( $info['wp-server']['fields'][ $key ] ) ) {
									$field = $info['wp-server']['fields'][ $key ];
									?>
									<div class="si-data-item">
										<span class="si-data-item-label"><span class="dashicons dashicons-media-code"></span> <?php echo esc_html( $field['label'] ); ?></span>
										<span class="si-data-item-val"><?php echo esc_html( $field['value'] ); ?></span>
									</div>
									<?php
								}
							}
							?>
						</div>
						<div class="si-section-footer" style="margin-top: 30px;">
							<a href="?page=server_info_display&tab=phpinfo">View All PHP Settings &rarr;</a>
						</div>
					</div>
					
					<div class="si-section" style="background:transparent; border:none; box-shadow:none; padding:0;">
						<div class="si-section-header">
							<h3 class="si-section-title">Quick Actions</h3>
						</div>
						<div class="si-actions-grid">
							<a href="?page=server_info_display&tab=diagnostics" class="si-action-card">
								<div class="si-action-icon"><span class="dashicons dashicons-analytics"></span></div>
								<div class="si-action-texts">
									<span class="si-action-title">View Logs</span>
									<span class="si-action-desc">Access error logs</span>
								</div>
							</a>
							<a href="?page=server_info_display&tab=phpinfo" class="si-action-card">
								<div class="si-action-icon"><span class="dashicons dashicons-editor-code"></span></div>
								<div class="si-action-texts">
									<span class="si-action-title">PHP Info</span>
									<span class="si-action-desc">View phpinfo()</span>
								</div>
							</a>
						</div>
					</div>
				</div>
				<?php else: 
					// Fallback for other tabs (Database, WP Core, Diagnostics, Plugins)
					if ( 'diagnostics' === $active_tab ) {
						self::display_diagnostics_tab();
					} elseif ( 'phpinfo' === $active_tab ) {
						self::display_phpinfo_tab();
					} elseif ( 'more_plugins' === $active_tab ) {
						self::display_more_plugins_tab();
					} else {
						$group_key = 'wp-server';
						if ( 'database' === $active_tab ) {
							$group_key = 'wp-database';
						} elseif ( 'wordpress' === $active_tab ) {
							$group_key = 'wp-info';
						} elseif ( 'caching' === $active_tab ) {
							$group_key = 'wp-caching';
						}

						if ( isset( $info[ $group_key ] ) && ! empty( $info[ $group_key ]['fields'] ) ) {
							$details = $info[ $group_key ];
							echo '<div class="si-section">';
							echo '<div class="si-section-header"><h3 class="si-section-title">' . esc_html( $details['label'] ) . '</h3></div>';
							echo '<ul class="si-table-list">';
							foreach ( $details['fields'] as $field ) {
								$value_html = '';
								if ( is_array( $field['value'] ) ) {
									$value_html .= '<div class="si-array-val">';
									foreach ( $field['value'] as $name => $val ) {
										if ( empty( $val ) ) {
											$value_html .= sprintf( '<div>%s</div>', esc_html( $name ) );
										} else {
											$value_html .= sprintf( '<div><strong>%s</strong> %s</div>', esc_html( $name ), esc_html( $val ) );
										}
									}
									$value_html .= '</div>';
								} else {
									$value_html = esc_html( $field['value'] );
								}
								?>
								<li class="si-table-row <?php echo is_array( $field['value'] ) ? 'has-array' : ''; ?>">
									<span class="si-table-label"><span class="dashicons dashicons-arrow-right-alt2"></span> <?php echo esc_html( $field['label'] ); ?></span>
									<span class="si-table-val"><?php echo $value_html; ?></span>
								</li>
								<?php
							}
							echo '</ul></div>';
						}
					}
				endif; ?>

			</div>
		</div>
		<?php
	}

	/**
	 * Displays the more plugins tab content for cross-promotion.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public static function display_more_plugins_tab() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array(
			array(
				'name' => esc_html__( 'Advance Canonical URL', 'server-info' ),
				'desc' => esc_html__( 'Easily manage and customize canonical URLs to eliminate duplicate content issues and boost your SEO rankings.', 'server-info' ),
				'icon' => 'dashicons-admin-links',
				'slug' => 'advance-canonical-url',
				'file' => 'advance-canonical-url/advance-canonical-url.php',
			),
			array(
				'name' => esc_html__( 'Metaviewer - Debug Meta Data', 'server-info' ),
				'desc' => esc_html__( 'The ultimate developer tool to instantly view, inspect, and debug post, user, and term meta data directly from the frontend.', 'server-info' ),
				'icon' => 'dashicons-visibility',
				'slug' => 'metaviewer-debug-meta-data',
				'file' => 'metaviewer-debug-meta-data/metaviewer-debug-meta-data.php',
			),
			array(
				'name' => esc_html__( 'Randomize Password', 'server-info' ),
				'desc' => esc_html__( 'Enhance your security by forcing highly secure, completely randomized passwords for user accounts upon creation or reset.', 'server-info' ),
				'icon' => 'dashicons-lock',
				'slug' => 'randomize-password',
				'file' => 'randomize-password/randomize-password.php',
			),
		);
		?>
		<div class="si-dashboard-grid si-plugins-grid">
			<?php foreach ( $plugins as $plugin ) : 
				$is_installed = file_exists( WP_PLUGIN_DIR . '/' . $plugin['file'] );
				$is_active    = $is_installed && is_plugin_active( $plugin['file'] );
			?>
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon">
						<span class="dashicons <?php echo esc_attr( $plugin['icon'] ); ?>"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php echo esc_html( $plugin['name'] ); ?></h3>
				</div>
				<p class="si-plugin-card-desc">
					<?php echo esc_html( $plugin['desc'] ); ?>
				</p>
				<div>
					<?php if ( $is_active ) : ?>
						<button type="button" class="si-btn-active" disabled><?php esc_html_e( 'Active', 'server-info' ); ?></button>
					<?php elseif ( $is_installed ) : 
						$activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin['file'] ) ), 'activate-plugin_' . $plugin['file'] );
					?>
						<a href="<?php echo esc_url( $activate_url ); ?>" class="si-btn-activate"><?php esc_html_e( 'Activate', 'server-info' ); ?></a>
					<?php else : 
						$install_url = wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=' . urlencode( $plugin['slug'] ) ), 'install-plugin_' . $plugin['slug'] );
					?>
						<a href="<?php echo esc_url( $install_url ); ?>" class="si-btn-install"><?php esc_html_e( 'Install Now', 'server-info' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Display the PHP Info tab.
	 *
	 * @return void
	 */
	public static function display_phpinfo_tab() {
		ob_start();
		phpinfo();
		$pinfo = ob_get_contents();
		ob_end_clean();

		$pinfo = preg_replace( '%^.*<body>(.*)</body>.*$%ms','$1', $pinfo );
		
		?>
		<div class="si-section si-phpinfo-wrapper">
			<div class="si-section-header">
				<h3 class="si-section-title"><?php esc_html_e( 'Comprehensive PHP Information', 'server-info' ); ?></h3>
			</div>
			<div class="si-phpinfo-content">
				<?php echo $pinfo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Displays the diagnostics and logs tab content.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @return void
	 */
	public static function display_diagnostics_tab() {		$php_version = phpversion();
		$php_status = 'Optimal';
		$php_impact = 'No Impact';
		$php_color = 'var(--si-success)';
		if ( version_compare( $php_version, '7.4', '<' ) ) {
			$php_status = 'Critical';
			$php_impact = '-30% Penalty';
			$php_color = 'var(--si-danger)';
		} elseif ( version_compare( $php_version, '8.3', '<' ) ) {
			$php_status = 'Warning';
			$php_impact = '-10% Penalty';
			$php_color = 'var(--si-warning)';
		}

		$memory_limit = ini_get( 'memory_limit' );
		$memory_limit_int = intval( $memory_limit );
		if ( false !== strpos( $memory_limit, 'G' ) ) {
			$memory_limit_int *= 1024;
		}
		$mem_status = 'Good';
		$mem_impact = 'No Impact';
		$mem_color = 'var(--si-success)';
		if ( $memory_limit_int > 0 && $memory_limit_int < 256 ) {
			$mem_status = 'Low';
			$mem_impact = '-10% Penalty';
			$mem_color = 'var(--si-warning)';
		}

		$wp_config_path = ABSPATH . 'wp-config.php';
		$is_config_writable = file_exists( $wp_config_path ) && is_writable( $wp_config_path );
		$config_status = $is_config_writable ? 'Writable (Insecure)' : 'Secure';
		$config_impact = $is_config_writable ? '-10% Penalty' : 'No Impact';
		$config_color = $is_config_writable ? 'var(--si-danger)' : 'var(--si-success)';
		
		global $wp_version;
		$core_updates = get_site_transient('update_core');
		$wp_status = 'Up to date';
		$wp_impact = 'No Impact';
		$wp_color = 'var(--si-success)';
		if ( isset( $core_updates->updates ) && is_array( $core_updates->updates ) ) {
			foreach ( $core_updates->updates as $update ) {
				if ( $update->response === 'upgrade' ) {
					$wp_status = 'Update Available';
					$wp_impact = '-10% Penalty';
					$wp_color = 'var(--si-warning)';
					break;
				}
			}
		}

		$error_log = ini_get( 'error_log' );
		$log_status = ! empty( $error_log ) ? esc_html( $error_log ) : 'Not configured';
		
		?>
		<div class="si-dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-bottom: 24px;">
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($php_color); ?>;">
						<span class="dashicons dashicons-media-code"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'PHP Version', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<strong><?php echo esc_html($php_version); ?></strong> - <span style="color: <?php echo esc_attr($php_color); ?>; font-weight: 600;"><?php echo esc_html($php_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($php_impact); ?>
				</div>
			</div>
			
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($mem_color); ?>;">
						<span class="dashicons dashicons-dashboard"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'PHP Memory Limit', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<strong><?php echo esc_html($memory_limit); ?></strong> - <span style="color: <?php echo esc_attr($mem_color); ?>; font-weight: 600;"><?php echo esc_html($mem_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($mem_impact); ?>
				</div>
			</div>

			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($config_color); ?>;">
						<span class="dashicons dashicons-lock"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'wp-config.php', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<span style="color: <?php echo esc_attr($config_color); ?>; font-weight: 600;"><?php echo esc_html($config_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($config_impact); ?>
				</div>
			</div>

			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: <?php echo esc_attr($wp_color); ?>;">
						<span class="dashicons dashicons-wordpress"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'WordPress Core', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px;">
					<strong><?php echo esc_html($wp_version); ?></strong> - <span style="color: <?php echo esc_attr($wp_color); ?>; font-weight: 600;"><?php echo esc_html($wp_status); ?></span>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> <?php echo esc_html($wp_impact); ?>
				</div>
			</div>
			
			<div class="si-card">
				<div class="si-plugin-card-header">
					<div class="si-plugin-card-icon" style="color: var(--si-text-main);">
						<span class="dashicons dashicons-analytics"></span>
					</div>
					<h3 class="si-plugin-card-title"><?php esc_html_e( 'Native PHP Error Log', 'server-info' ); ?></h3>
				</div>
				<div style="margin-bottom: 12px; font-size: 12px; word-break: break-all;">
					<?php echo esc_html($log_status); ?>
				</div>
				<div style="font-size: 13px; color: var(--si-text-muted); background: var(--si-bg); padding: 8px; border-radius: 6px;">
					<strong>Score Impact:</strong> No Impact
				</div>
			</div>
		</div>

			<div class="si-section">
				<div class="si-section-header">
					<h3 class="si-section-title"><?php esc_html_e( 'Server Debug Log', 'server-info' ); ?></h3>
				</div>
				<div class="si-terminal">
					<?php
					$log_file = WP_CONTENT_DIR . '/debug.log';
					if ( file_exists( $log_file ) && is_readable( $log_file ) ) {
						$lines = file( $log_file );
						if ( is_array( $lines ) && ! empty( $lines ) ) {
							$last_lines = array_slice( $lines, -30 );
							foreach ( $last_lines as $line ) {
								echo esc_html( $line ) . '<br/>';
							}
						} else {
							echo esc_html__( 'Debug log is currently empty.', 'server-info' );
						}
					} else {
						echo esc_html__( 'Debug log not found or not readable. Ensure WP_DEBUG and WP_DEBUG_LOG are enabled in wp-config.php.', 'server-info' );
					}
					?>
				</div>
			</div>
		<?php
	}
}

// Instantiate the Server_Info class
$Server_Info = Server_Info::getInstance();
