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
 * Register the Tools submenu page.
 */
function autoloadforge_admin_menu() {
	add_management_page(
		__( 'AutoloadForge', 'autoloadforge' ),
		__( 'AutoloadForge', 'autoloadforge' ),
		'manage_options',
		'autoloadforge',
		'autoloadforge_render_page'
	);
}
add_action( 'admin_menu', 'autoloadforge_admin_menu' );

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
		$tracked = get_option( 'autoloadforge_disabled', array() );
		if ( ! is_array( $tracked ) ) {
			$tracked = array();
		}

		if ( 'stop' === $op ) {
			wp_set_option_autoload( $option, false );
			if ( ! in_array( $option, $tracked, true ) ) {
				$tracked[] = $option;
			}
			$msg = 'stopped';
		} else {
			wp_set_option_autoload( $option, true );
			$tracked = array_values( array_diff( $tracked, array( $option ) ) );
			$msg     = 'restored';
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

	// Health colour.
	if ( $total < 512 * 1024 ) {
		$health_label = __( 'Healthy', 'autoloadforge' );
		$health_color = '#2a8a3e';
	} elseif ( $total < 1024 * 1024 ) {
		$health_label = __( 'Getting heavy', 'autoloadforge' );
		$health_color = '#b26a00';
	} else {
		$health_label = __( 'Too heavy', 'autoloadforge' );
		$health_color = '#c1272d';
	}

	$disabled = get_option( 'autoloadforge_disabled', array() );
	$disabled = is_array( $disabled ) ? $disabled : array();

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
				<span class="alf-card-num" style="color:<?php echo esc_attr( $health_color ); ?>"><?php echo esc_html( $health_label ); ?></span>
				<span class="alf-card-label"><?php esc_html_e( 'Under ~800KB is the goal', 'autoloadforge' ); ?></span>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'Autoloaded options load on every single page request. Switching off the ones you do not need on every page trims that weight. It is reversible, so nothing here is permanent.', 'autoloadforge' ); ?>
		</p>

		<h2><?php printf( esc_html__( 'Largest autoloaded options (top %d)', 'autoloadforge' ), (int) $limit ); ?></h2>
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
						<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="margin:0">
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
				<?php foreach ( $disabled as $name ) : ?>
					<tr>
						<td><code><?php echo esc_html( $name ); ?></code></td>
						<td><?php echo esc_html( size_format( autoloadforge_value_size( get_option( $name ) ), 1 ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="margin:0">
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

	<style>
		.autoloadforge .alf-cards { display:flex; gap:16px; flex-wrap:wrap; margin:16px 0 8px; }
		.autoloadforge .alf-card { background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:16px 20px; min-width:160px; }
		.autoloadforge .alf-card-num { display:block; font-size:24px; font-weight:600; line-height:1.2; }
		.autoloadforge .alf-card-label { display:block; color:#646970; font-size:12px; margin-top:4px; }
		.autoloadforge table { margin-top:8px; max-width:900px; }
		.autoloadforge td code { background:transparent; padding:0; }
	</style>
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
