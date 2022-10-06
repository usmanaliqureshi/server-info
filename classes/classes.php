<?php

// Fix some PHP related issues with older versions
ob_start();

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Server Info Class
 */
class server_info {

	public static function infohouse_css_styles() {
		wp_enqueue_style( 'si-styles', plugins_url( '../assets/css/style.css', __FILE__ ) );
	}

	public static function servinfo_admin_actions() {
		add_options_page(
			__('Server Information', 'si'),
			'Server Info',
			'manage_options',
			'server_info_display',
			array( 'server_info', 'display_infohouse_page' )
		);
	}

	public static function display_infohouse_page() {
		global $wpdb;
		?>

		<div class="wrap server-info">

			<h2 class="infohouse_heading"><?php _e('Server Information', 'si'); ?></h2>

			<hr/>

			<p><?php _e('Server Info plugin shows the general information about the hosting server your WordPress site is currently hosted on. You can find this information helpful for many purposes like performance improvements and so on.', 'si'); ?></p>

			<br/>

			<div class="infohouse_settings_page">

				<div class="table-responsive">

					<table class="table infohouse_table">

						<tr>

							<th colspan="2"><h3><?php _e('Hosting Server Information', 'si'); ?></h3></th>

						</tr>

						<tr>

							<td><h5><?php _e('Operating System', 'si'); ?>:</h5></td>

							<td><p><?php echo php_uname( 's' ); ?></p></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('Server IP', 'si'); ?>:</h5></td>

							<td><p><?php echo $_SERVER['SERVER_ADDR']; ?></p></td>

						</tr>

						<tr>

							<td><h5><?php _e('Server Hostname', 'si'); ?>:</h5></td>

							<td><p><?php echo php_uname( 'n' ); ?></p></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('Server Protocol', 'si'); ?>:</h5></td>

							<td><p><?php echo $_SERVER['SERVER_PROTOCOL']; ?></p></td>

						</tr>

						<tr>

							<td><h5><?php _e('Server Administrator', 'si'); ?>:</h5></td>

							<td><p><?php echo $_SERVER['SERVER_ADMIN']; ?></p></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('Server Web Port', 'si'); ?>:</h5></td>

							<td><p><?php echo $_SERVER['SERVER_PORT']; ?></p></td>

						</tr>

						<tr>

							<td><h5><?php _e('PHP Version', 'si'); ?>:</h5></td>

							<td><p><?php echo phpversion(); ?></p></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('MySQL Version', 'si'); ?>:</h5></td>

							<td>
								<p><?php

									$connection = mysqli_connect( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );

									echo mysqli_get_server_info( $connection ); ?>

								</p>
							</td>

						</tr>

						<tr>

							<td><h5><?php _e('CGI Version', 'si'); ?>:</h5></td>

							<td><p><?php echo $_SERVER['GATEWAY_INTERFACE']; ?></p></td>

						</tr>

						<?php $uptime = exec( "uptime", $system );

						if ( ! empty( $uptime ) ) {

							?>

							<tr class="gray">

								<td><h5><?php _e('System Uptime', 'si'); ?>:</h5></td>

								<td><p><?php echo $uptime; ?></p></td>

							</tr>

						<?php } ?>

					</table>

					<table class="table infohouse_table">

						<tr>

							<th colspan="2"><h3><?php _e('WordPress Information', 'si'); ?></h3></th>

						</tr>

						<tr>

							<td><h5><?php _e('Active Theme', 'si'); ?>:</h5></td>

							<td><?php

								$active_theme = wp_get_theme();

								echo esc_html( $active_theme->get( 'Name' ) );

								?></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('Active Plugins', 'si'); ?>:</h5></td>

							<td><?php

								$active_plugins = get_option( 'active_plugins' );

								echo '<ul>';

								foreach ( $active_plugins as $key => $value ) {

									$string = explode( '/', $value );

									echo '<li>' . $string[0] . '</li>';

								}

								echo '</ul>';

								?></td>

						</tr>

						<tr>

							<td><h5><?php _e('Database Hostname', 'si'); ?>:</h5></td>

							<td><?php echo DB_HOST; ?></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('Database Username', 'si'); ?>:</h5></td>

							<td><?php echo DB_USER; ?></td>

						</tr>

						<tr>

							<td><h5><?php _e('Database Name', 'si'); ?>:</h5></td>

							<td><?php echo DB_NAME; ?></td>

						</tr>

						<tr class="gray">

							<td><h5><?php _e('Database Charset', 'si'); ?>:</h5></td>

							<td><?php echo DB_CHARSET; ?></td>

						</tr>

						<?php

						$db_collate = DB_COLLATE;

						if ( ! empty( $db_collate ) ) {

							?>

							<tr>

								<td><h5><?php _e('Database Collation', 'si'); ?>:</h5></td>

								<td><?php echo DB_COLLATE; ?></td>

							</tr>

						<?php } ?>

						<?php

						$wp_debug = WP_DEBUG;

						if ( ! empty( $wp_debug ) ) {

							?>

							<tr>

								<td><h5><?php _e('WordPress Debugging', 'si'); ?>:</h5></td>

								<td><?php

									if ( $wp_debug = 1 ) {

										echo "Enabled";

									} else {

										echo "Disabled";

									}

									?></td>

							</tr>

						<?php } ?>

						<tr class="gray">

							<td><h5><?php _e('WordPress Memory Limit', 'si'); ?>:</h5></td>

							<td><p><?php echo WP_MEMORY_LIMIT; ?></p></td>

						</tr>

					</table>

				</div>

			</div>

		</div>

		<?php
	}

	public static function server_info_add_dashboard_widgets() {
		wp_add_dashboard_widget(
			'serverinfo_dashboard_widget',
			'Server Info',
			array( 'server_info', 'server_info_dashboard_widget' )
		);
	}

	public static function server_info_dashboard_widget() { ?>

		<table class="table infohouse_table dashboard_inf_table">

			<tr>

				<td><h5><?php _e('Operating System', 'si'); ?>:</h5></td>

				<td><p><?php echo php_uname( "s" ); ?></p></td>

			</tr>

			<tr class="gray">

				<td><h5><?php _e('Server IP', 'si'); ?>:</h5></td>

				<td><p><?php echo $_SERVER['SERVER_ADDR']; ?></p></td>

			</tr>

			<tr>

				<td><h5><?php _e('Server Hostname', 'si'); ?>:</h5></td>

				<td><p><?php echo php_uname( 'n' ); ?></p></td>

			</tr>

			<tr class="gray">

				<td><h5><?php _e('PHP Version', 'si'); ?>:</h5></td>

				<td><p><?php echo phpversion(); ?></p></td>

			</tr>

			<tr>

				<td colspan="2" class="view-more-info"><a class="button button-primary" href="<?php echo admin_url( 'options-general.php?page=server_info_display' ); ?>" ?>View More Information</a></td>

			</tr>

		</table> <?php

	}

}

?>
