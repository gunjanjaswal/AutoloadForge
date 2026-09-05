<?php
/**
 * Plugin Name:       AutoloadForge
 * Plugin URI:        https://github.com/gunjanjaswal/AutoloadForge
 * Description:       See which options are bloating your autoloaded data, trace each one to a likely plugin, and switch autoload off (reversibly) from a single Tools screen.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Gunjan Jaswal
 * Author URI:        https://www.gunjanjaswal.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       autoloadforge
 *
 * @package AutoloadForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AUTOLOADFORGE_VERSION', '1.0.0' );

/**
 * Store or retrieve the admin page hook suffix.
 *
 * @param string|null $set Hook suffix to store, or null to read the stored value.
 * @return string
 */
function autoloadforge_page_hook( $set = null ) {
	static $hook = '';
	if ( null !== $set ) {
		$hook = $set;
	}
	return $hook;
}

/**
 * Register the Tools submenu page.
 */
function autoloadforge_admin_menu() {
	$hook = add_management_page(
		__( 'AutoloadForge', 'autoloadforge' ),
		__( 'AutoloadForge', 'autoloadforge' ),
		'manage_options',
		'autoloadforge',
		'autoloadforge_render_page'
	);
	autoloadforge_page_hook( $hook );
}
add_action( 'admin_menu', 'autoloadforge_admin_menu' );

/**
 * Enqueue the admin stylesheet, only on the plugin's own screen.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 */
function autoloadforge_enqueue_assets( $hook_suffix ) {
	if ( $hook_suffix !== autoloadforge_page_hook() ) {
		return;
	}

	wp_enqueue_style(
		'autoloadforge-admin',
		plugins_url( 'assets/css/admin.css', __FILE__ ),
		array(),
		AUTOLOADFORGE_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'autoloadforge_enqueue_assets' );

/**
 * Handle the stop / restore autoload action, then redirect back.
 */
function autoloadforge_handle_toggle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'autoloadforge' ) );
	}

	check_admin_referer( 'autoloadforge_toggle' );

	$option = isset( $_POST['option_name'] ) ? sanitize_text_field( wp_unslash( $_POST['option_name'] ) ) : '';
	$op     = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$msg    = 'none';

	if ( '' !== $option && in_array( $op, array( 'stop', 'restore' ), true ) ) {
		$tracked = autoloadforge_get_disabled();

		if ( 'stop' === $op ) {
			// Capture the size from the already-loaded autoload bundle before
			// switching it off, so the size can be shown later without reading
			// the value back by a request-supplied option name.
			$alloptions = wp_load_alloptions();
			$size       = isset( $alloptions[ $option ] ) ? autoloadforge_value_size( $alloptions[ $option ] ) : 0;

			wp_set_option_autoload( $option, false );
			$tracked[ $option ] = $size;
			$msg                = 'stopped';
		} else {
			wp_set_option_autoload( $option, true );
			unset( $tracked[ $option ] );
			$msg = 'restored';
		}

		update_option( 'autoloadforge_disabled', $tracked, false );
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'autoloadforge',
				'alf_msg' => $msg,
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_post_autoloadforge_toggle', 'autoloadforge_handle_toggle' );

/**
 * Build a map of plugin option prefixes to readable plugin names.
 *
 * @return array<string,string>
 */
function autoloadforge_plugin_map() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$all    = get_plugins();
	$active = (array) get_option( 'active_plugins', array() );
	$map    = array();

	foreach ( $active as $file ) {
		$slug = dirname( $file );
		if ( '.' === $slug || '' === $slug ) {
			$slug = basename( $file, '.php' );
		}

		$name = isset( $all[ $file ]['Name'] ) ? $all[ $file ]['Name'] : $slug;

		foreach ( array( $slug, str_replace( '-', '_', $slug ) ) as $key ) {
			if ( strlen( $key ) > 2 ) {
				$map[ $key ] = $name;
			}
		}
	}

	return $map;
}

/**
 * Best-effort guess of where an option came from.
 *
 * @param string               $name Option name.
 * @param array<string,string> $map  Prefix to plugin-name map.
 * @return string
 */
function autoloadforge_guess_source( $name, $map ) {
	$best_key = '';
	foreach ( $map as $key => $label ) {
		if ( 0 === strpos( $name, $key ) && strlen( $key ) > strlen( $best_key ) ) {
			$best_key = $key;
		}
	}

	if ( '' !== $best_key ) {
		return $map[ $best_key ];
	}

	$core_prefixes = array( '_transient_', '_site_transient_', 'theme_mods_', 'widget_', 'wp_', '_wp_', 'rewrite_rules', 'cron', 'siteurl', 'home', 'blogname', 'blogdescription', 'template', 'stylesheet', 'active_plugins', 'recently_edited', 'can_compress_scripts', 'db_version', 'nonce_key' );
	foreach ( $core_prefixes as $prefix ) {
		if ( 0 === strpos( $name, $prefix ) ) {
			return __( 'WordPress core', 'autoloadforge' );
		}
	}

	return __( 'Unknown / theme', 'autoloadforge' );
}

/**
 * Read the tracked "switched off" options as an option-name => size map.
 *
 * A defensive check keeps a legacy flat list of names working.
 *
 * @return array<string,int>
 */
function autoloadforge_get_disabled() {
	$tracked = get_option( 'autoloadforge_disabled', array() );
	if ( ! is_array( $tracked ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $tracked as $key => $value ) {
		if ( is_int( $key ) ) {
			$normalized[ (string) $value ] = 0;
		} else {
			$normalized[ $key ] = (int) $value;
		}
	}

	return $normalized;
}

/**
 * Byte length of an option value as stored.
 *
 * @param mixed $value Option value.
 * @return int
 */
function autoloadforge_value_size( $value ) {
	return strlen( is_string( $value ) ? $value : maybe_serialize( $value ) );
}

/**
 * Render the admin page.
 */
function autoloadforge_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$alloptions = wp_load_alloptions();
	$map        = autoloadforge_plugin_map();

	$sizes = array();
	$total = 0;
	foreach ( $alloptions as $name => $value ) {
		$size          = autoloadforge_value_size( $value );
		$sizes[ $name ] = $size;
		$total         += $size;
	}
	arsort( $sizes );

	$count     = count( $sizes );
	$limit     = 100;
	$shown     = array_slice( $sizes, 0, $limit, true );
	$post_url  = admin_url( 'admin-post.php' );

	// Health state.
	if ( $total < 512 * 1024 ) {
		$health_label = __( 'Healthy', 'autoloadforge' );
		$health_class = 'alf-health-good';
	} elseif ( $total < 1024 * 1024 ) {
		$health_label = __( 'Getting heavy', 'autoloadforge' );
		$health_class = 'alf-health-warn';
	} else {
		$health_label = __( 'Too heavy', 'autoloadforge' );
		$health_class = 'alf-health-bad';
	}

	$disabled = autoloadforge_get_disabled();

	$msg = isset( $_GET['alf_msg'] ) ? sanitize_key( wp_unslash( $_GET['alf_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap autoloadforge">
		<h1><?php esc_html_e( 'AutoloadForge', 'autoloadforge' ); ?></h1>

		<?php if ( 'stopped' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Autoload switched off for that option.', 'autoloadforge' ); ?></p></div>
		<?php elseif ( 'restored' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Autoload switched back on.', 'autoloadforge' ); ?></p></div>
		<?php endif; ?>

		<div class="alf-cards">
			<div class="alf-card">
				<span class="alf-card-num"><?php echo esc_html( size_format( $total, 1 ) ); ?></span>
				<span class="alf-card-label"><?php esc_html_e( 'Total autoloaded', 'autoloadforge' ); ?></span>
			</div>
			<div class="alf-card">
				<span class="alf-card-num"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
				<span class="alf-card-label"><?php esc_html_e( 'Autoloaded options', 'autoloadforge' ); ?></span>
			</div>
			<div class="alf-card">
				<span class="alf-card-num <?php echo esc_attr( $health_class ); ?>"><?php echo esc_html( $health_label ); ?></span>
				<span class="alf-card-label"><?php esc_html_e( 'Under ~800KB is the goal', 'autoloadforge' ); ?></span>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'Autoloaded options load on every single page request. Switching off the ones you do not need on every page trims that weight. It is reversible, so nothing here is permanent.', 'autoloadforge' ); ?>
		</p>

		<h2>
			<?php
			/* translators: %d: number of options shown in the table. */
			printf( esc_html__( 'Largest autoloaded options (top %d)', 'autoloadforge' ), (int) $limit );
			?>
		</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Option', 'autoloadforge' ); ?></th>
					<th><?php esc_html_e( 'Size', 'autoloadforge' ); ?></th>
					<th><?php esc_html_e( 'Likely source', 'autoloadforge' ); ?></th>
					<th><?php esc_html_e( 'Action', 'autoloadforge' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $shown as $name => $size ) : ?>
				<tr>
					<td><code><?php echo esc_html( $name ); ?></code></td>
					<td><?php echo esc_html( size_format( $size, 1 ) ); ?></td>
					<td><?php echo esc_html( autoloadforge_guess_source( $name, $map ) ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="alf-inline-form">
							<?php wp_nonce_field( 'autoloadforge_toggle' ); ?>
							<input type="hidden" name="action" value="autoloadforge_toggle" />
							<input type="hidden" name="op" value="stop" />
							<input type="hidden" name="option_name" value="<?php echo esc_attr( $name ); ?>" />
							<button type="submit" class="button button-small"><?php esc_html_e( 'Stop autoloading', 'autoloadforge' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( ! empty( $disabled ) ) : ?>
			<h2><?php esc_html_e( 'Switched off by AutoloadForge', 'autoloadforge' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These options no longer autoload. Restore any of them if you notice something needs it back.', 'autoloadforge' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Option', 'autoloadforge' ); ?></th>
						<th><?php esc_html_e( 'Size', 'autoloadforge' ); ?></th>
						<th><?php esc_html_e( 'Action', 'autoloadforge' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $disabled as $name => $size ) : ?>
					<tr>
						<td><code><?php echo esc_html( $name ); ?></code></td>
						<td><?php echo esc_html( size_format( (int) $size, 1 ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="alf-inline-form">
								<?php wp_nonce_field( 'autoloadforge_toggle' ); ?>
								<input type="hidden" name="action" value="autoloadforge_toggle" />
								<input type="hidden" name="op" value="restore" />
								<input type="hidden" name="option_name" value="<?php echo esc_attr( $name ); ?>" />
								<button type="submit" class="button button-small"><?php esc_html_e( 'Restore autoloading', 'autoloadforge' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Add support and contact links to the plugin's action links.
 *
 * @param array $links Existing action links.
 * @return array
 */
function autoloadforge_action_links( $links ) {
	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://ko-fi.com/gunjanjaswal' ),
		esc_html__( 'Support on Ko-fi', 'autoloadforge' )
	);
	$links[] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( 'mailto:hello@gunjanjaswal.me' ),
		esc_html__( 'Contact developer', 'autoloadforge' )
	);

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'autoloadforge_action_links' );
