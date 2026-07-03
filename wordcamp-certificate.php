<?php
/**
 * Plugin Name: WordCamp Certificate Generator
 * Plugin URI:  https://wordcamp.org
 * Description: Generates a beautiful Certificate of Attendance for WordCamp attendees. Attendees enter their name and email to receive a downloadable certificate.
 * Version:     1.0.0
 * Author:      WordPress Studio
 * License:     GPL-2.0-or-later
 * Text Domain: wordcamp-certificate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCC_VERSION', '1.0.0' );

// Activation: create DB table
register_activation_hook( __FILE__, 'wcc_activate' );
function wcc_activate() {
	global $wpdb;
	$table = $wpdb->prefix . 'wordcamp_certificates';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		full_name    VARCHAR(255) NOT NULL,
		email        VARCHAR(255) NOT NULL,
		issued_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
		cert_token   VARCHAR(64) NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY   cert_token (cert_token),
		KEY          email (email)
	) {$charset_collate};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

// Admin menu
add_action( 'admin_menu', 'wcc_admin_menu' );
function wcc_admin_menu() {
	add_menu_page(
		__( 'WordCamp Certificates', 'wordcamp-certificate' ),
		__( 'WC Certificates', 'wordcamp-certificate' ),
		'manage_options',
		'wordcamp-certificates',
		'wcc_admin_page',
		'dashicons-awards',
		30
	);
	add_submenu_page(
		'wordcamp-certificates',
		__( 'Settings', 'wordcamp-certificate' ),
		__( 'Settings', 'wordcamp-certificate' ),
		'manage_options',
		'wordcamp-certificate-settings',
		'wcc_settings_page'
	);
}

// Enqueue admin styles
add_action( 'admin_enqueue_scripts', 'wcc_admin_scripts' );
function wcc_admin_scripts( $hook ) {
	if ( strpos( $hook, 'wordcamp-certificate' ) === false ) return;
	wp_enqueue_style( 'wcc-admin', WCC_PLUGIN_URL . 'assets/admin.css', [], WCC_VERSION );
}

// Enqueue frontend scripts/styles
add_action( 'wp_enqueue_scripts', 'wcc_frontend_scripts' );
function wcc_frontend_scripts() {
	wp_enqueue_style( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.css', [], WCC_VERSION );
	wp_enqueue_script( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.js', [ 'jquery' ], WCC_VERSION, true );
	wp_localize_script( 'wcc-frontend', 'wccData', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'wcc_generate_nonce' ),
	] );
}

// Shortcode
add_shortcode( 'wordcamp_certificate', 'wcc_shortcode' );
function wcc_shortcode( $atts ) {
	$atts = shortcode_atts( [
		'event_name' => get_option( 'wcc_event_name', 'WordCamp' ),
		'event_date' => get_option( 'wcc_event_date', date( 'F j, Y' ) ),
	], $atts );
	ob_start();
	include WCC_PLUGIN_DIR . 'templates/form.php';
	return ob_get_clean();
}

// AJAX: generate certificate
add_action( 'wp_ajax_wcc_generate', 'wcc_ajax_generate' );
add_action( 'wp_ajax_nopriv_wcc_generate', 'wcc_ajax_generate' );
function wcc_ajax_generate() {
	check_ajax_referer( 'wcc_generate_nonce', 'nonce' );

	$full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
	$email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

	if ( empty( $full_name ) || empty( $email ) ) {
		wp_send_json_error( [ 'message' => __( 'Please provide your full name and email address.', 'wordcamp-certificate' ) ] );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'wordcamp-certificate' ) ] );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'wordcamp_certificates';

	// Check if already issued
	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT cert_token FROM {$table} WHERE email = %s",
		$email
	) );

	if ( $existing ) {
		$token = $existing->cert_token;
	} else {
		$token = wp_generate_password( 32, false );
		$wpdb->insert( $table, [
			'full_name'  => $full_name,
			'email'      => $email,
			'issued_at'  => current_time( 'mysql' ),
			'cert_token' => $token,
		] );
	}

	$cert_url = add_query_arg( [
		'wcc_cert'  => '1',
		'token'     => $token,
	], home_url( '/' ) );

	wp_send_json_success( [ 'cert_url' => $cert_url, 'name' => $full_name ] );
}

// Certificate render endpoint
add_action( 'template_redirect', 'wcc_render_certificate' );
function wcc_render_certificate() {
	if ( ! isset( $_GET['wcc_cert'] ) || ! isset( $_GET['token'] ) ) return;

	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
	if ( empty( $token ) ) {
		wp_die( esc_html__( 'Invalid certificate token.', 'wordcamp-certificate' ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'wordcamp_certificates';
	$cert  = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE cert_token = %s",
		$token
	) );

	if ( ! $cert ) {
		wp_die( esc_html__( 'Certificate not found.', 'wordcamp-certificate' ) );
	}

	$event_name = get_option( 'wcc_event_name', 'WordCamp' );
	$event_date = get_option( 'wcc_event_date', date( 'F j, Y' ) );
	$event_location = get_option( 'wcc_event_location', '' );
	$organizer_name = get_option( 'wcc_organizer_name', get_bloginfo( 'name' ) );

	include WCC_PLUGIN_DIR . 'templates/certificate.php';
	exit;
}

// Admin page: list all issued certificates
function wcc_admin_page() {
	global $wpdb;
	$table = $wpdb->prefix . 'wordcamp_certificates';
	$certs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY issued_at DESC" );
	include WCC_PLUGIN_DIR . 'templates/admin.php';
}

// Settings page
function wcc_settings_page() {
	if ( isset( $_POST['wcc_save_settings'] ) && check_admin_referer( 'wcc_settings' ) ) {
		update_option( 'wcc_event_name',     sanitize_text_field( $_POST['wcc_event_name'] ) );
		update_option( 'wcc_event_date',     sanitize_text_field( $_POST['wcc_event_date'] ) );
		update_option( 'wcc_event_location', sanitize_text_field( $_POST['wcc_event_location'] ) );
		update_option( 'wcc_organizer_name', sanitize_text_field( $_POST['wcc_organizer_name'] ) );
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'wordcamp-certificate' ) . '</p></div>';
	}
	include WCC_PLUGIN_DIR . 'templates/settings.php';
}

// Register block
add_action( 'init', 'wcc_register_block' );
function wcc_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) return;
	register_block_type( 'wordcamp-certificate/form', [
		'editor_script'   => 'wcc-block-editor',
		'render_callback' => 'wcc_block_render',
		'attributes'      => [
			'eventName' => [ 'type' => 'string', 'default' => '' ],
			'eventDate' => [ 'type' => 'string', 'default' => '' ],
		],
	] );
	wp_register_script(
		'wcc-block-editor',
		WCC_PLUGIN_URL . 'assets/block.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ],
		WCC_VERSION,
		true
	);
}

function wcc_block_render( $attributes ) {
	$shortcode_atts = '';
	if ( ! empty( $attributes['eventName'] ) ) {
		$shortcode_atts .= ' event_name="' . esc_attr( $attributes['eventName'] ) . '"';
	}
	if ( ! empty( $attributes['eventDate'] ) ) {
		$shortcode_atts .= ' event_date="' . esc_attr( $attributes['eventDate'] ) . '"';
	}
	return do_shortcode( '[wordcamp_certificate' . $shortcode_atts . ']' );
}
