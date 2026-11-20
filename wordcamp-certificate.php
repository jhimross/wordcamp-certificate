<?php
/**
 * Plugin Name: WordCamp Certificate Generator
 * Plugin URI:  https://jhimross.com/
 * Author URI:  https://profiles.wordpress.org/jhimross/
 * Description: Generates a beautiful Certificate of Attendance for WordCamp attendees. Manage multiple events, each with its own certificate design, attendee list, and unique certificate link.
 * Version:     1.4.1
 * Author:      Jhimross Olinares
 * License:     GPL-2.0-or-later
 * Text Domain: wordcamp-certificate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCC_VERSION', '1.4.1' );

require_once WCC_PLUGIN_DIR . 'includes/design-settings.php';
require_once WCC_PLUGIN_DIR . 'includes/attendees.php';
require_once WCC_PLUGIN_DIR . 'includes/events.php';

// Activation: create DB tables. Event migration and rewrite flushing happen on
// the next `init` (wcc_maybe_upgrade), once the Event post type is registered.
register_activation_hook( __FILE__, 'wcc_activate' );
function wcc_activate() {
	wcc_activate_tables();
	update_option( 'wcc_needs_rewrite_flush', 1 );
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function wcc_activate_tables() {
	global $wpdb;
	$table = $wpdb->prefix . 'wordcamp_certificates';
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		event_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		full_name    VARCHAR(255) NOT NULL,
		email        VARCHAR(255) NOT NULL,
		issued_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
		cert_token   VARCHAR(64) NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY   cert_token (cert_token),
		KEY          email (email),
		KEY          event_id (event_id)
	) {$charset_collate};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	wcc_create_attendees_table();
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
		__( 'Certificates Issued', 'wordcamp-certificate' ),
		__( 'Certificates Issued', 'wordcamp-certificate' ),
		'manage_options',
		'wordcamp-certificates',
		'wcc_admin_page'
	);
	add_submenu_page(
		'wordcamp-certificates',
		__( 'Add New Event', 'wordcamp-certificate' ),
		__( 'Add New Event', 'wordcamp-certificate' ),
		'manage_options',
		'post-new.php?post_type=' . WCC_EVENT_CPT
	);
	add_submenu_page(
		'wordcamp-certificates',
		__( 'Attendees', 'wordcamp-certificate' ),
		__( 'Attendees', 'wordcamp-certificate' ),
		'manage_options',
		'wordcamp-certificate-attendees',
		'wcc_attendees_page'
	);
}

// Enqueue admin styles
add_action( 'admin_enqueue_scripts', 'wcc_admin_scripts' );
function wcc_admin_scripts( $hook ) {
	$screen   = get_current_screen();
	$is_event = $screen && WCC_EVENT_CPT === $screen->post_type;
	if ( strpos( $hook, 'wordcamp-certificate' ) === false && ! $is_event ) return;
	wp_enqueue_style( 'wcc-admin', WCC_PLUGIN_URL . 'assets/admin.css', [], WCC_VERSION );
	if ( $is_event && 'post' === $screen->base ) {
		wp_enqueue_media();
	}
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

// Shortcode: [wordcamp_certificate event="wordcamp-manila-2025"] (slug or ID).
// Without `event`, the newest active event is used.
add_shortcode( 'wordcamp_certificate', 'wcc_shortcode' );
function wcc_shortcode( $atts ) {
	$atts = shortcode_atts( [
		'event'      => '',
		'event_name' => '',
		'event_date' => '',
	], $atts );
	$event    = $atts['event'] ? wcc_get_event( $atts['event'] ) : null;
	$event_id = $event ? $event->ID : ( $atts['event'] ? 0 : wcc_get_default_event_id() );
	return wcc_render_form( $event_id, array_filter( [ 'event_name' => $atts['event_name'] ] ) );
}

/**
 * Render the certificate claim form for an event.
 *
 * @param int   $event_id  Event ID.
 * @param array $overrides Optional display overrides (event_name).
 */
function wcc_render_form( $event_id, $overrides = [] ) {
	$event = wcc_get_event( $event_id );
	if ( ! $event ) {
		if ( current_user_can( 'edit_pages' ) ) {
			return '<p class="wcc-form-notice">' . esc_html__( 'WordCamp Certificate: no published event found. Create one under WC Certificates → Add New Event.', 'wordcamp-certificate' ) . '</p>';
		}
		return '';
	}
	$atts = [
		'event_id'   => $event->ID,
		'event_name' => $overrides['event_name'] ?? get_the_title( $event ),
	];
	wp_enqueue_style( 'wcc-frontend' );
	wp_enqueue_script( 'wcc-frontend' );
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
	$event     = wcc_get_event( absint( $_POST['event_id'] ?? 0 ) );

	if ( ! $event ) {
		wp_send_json_error( [ 'message' => __( 'This event could not be found. Please contact the event organizer.', 'wordcamp-certificate' ) ] );
	}
	$event_id = $event->ID;

	if ( empty( $full_name ) || empty( $email ) ) {
		wp_send_json_error( [ 'message' => __( 'Please provide your full name and email address.', 'wordcamp-certificate' ) ] );
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => __( 'Please enter a valid email address.', 'wordcamp-certificate' ) ] );
	}

	$verified = wcc_verify_attendee( $full_name, $email, $event_id );
	if ( is_wp_error( $verified ) ) {
		wp_send_json_error( [ 'message' => $verified->get_error_message() ] );
	}

	// Use the official full name from the imported attendee list, not whatever
	// was typed into the form (which may be a nickname/display name variant).
	$canonical_name = trim( $verified->first_name . ' ' . $verified->last_name );

	global $wpdb;
	$table = $wpdb->prefix . 'wordcamp_certificates';

	// Check if already issued for this event
	$existing = $wpdb->get_row( $wpdb->prepare(
		"SELECT cert_token FROM {$table} WHERE event_id = %d AND email = %s",
		$event_id,
		$email
	) );

	if ( $existing ) {
		$token = $existing->cert_token;
	} else {
		$token = wp_generate_password( 32, false );
		$wpdb->insert( $table, [
			'event_id'   => $event_id,
			'full_name'  => $canonical_name,
			'email'      => $email,
			'issued_at'  => current_time( 'mysql' ),
			'cert_token' => $token,
		] );
	}

	$cert_url = wcc_get_certificate_url( $token, $event_id );

	wp_send_json_success( [ 'cert_url' => $cert_url, 'name' => $canonical_name ] );
}

// Certificate render endpoint. Two URL shapes are supported:
//   /certificate/{event-slug}/?token=XYZ   (1.3+, under the event's unique link)
//   /?wcc_cert=1&token=XYZ                 (pre-1.3 links, still valid)
// Admins can also open /certificate/{event-slug}/?token=preview to see the
// event's design filled with sample data.
add_action( 'template_redirect', 'wcc_render_certificate' );
function wcc_render_certificate() {
	$on_event_page = is_singular( WCC_EVENT_CPT );
	if ( ! isset( $_GET['token'] ) || ( ! isset( $_GET['wcc_cert'] ) && ! $on_event_page ) ) return;

	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
	if ( empty( $token ) ) {
		wp_die( esc_html__( 'Invalid certificate token.', 'wordcamp-certificate' ) );
	}

	if ( 'preview' === $token && $on_event_page ) {
		$event_id = get_queried_object_id();
		if ( ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'wordcamp-certificate' ) );
		}
		$cert = (object) [
			'event_id'   => $event_id,
			'full_name'  => 'Jane Doe',
			'cert_token' => 'PREVIEW0',
			'issued_at'  => current_time( 'mysql' ),
		];
	} else {
		global $wpdb;
		$table = $wpdb->prefix . 'wordcamp_certificates';
		$cert  = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE cert_token = %s",
			$token
		) );

		// A certificate opened under an event link must belong to that event.
		if ( ! $cert || ( $on_event_page && (int) $cert->event_id !== (int) get_queried_object_id() ) ) {
			wp_die( esc_html__( 'Certificate not found.', 'wordcamp-certificate' ), '', [ 'response' => 404 ] );
		}
	}

	$event      = wcc_get_event_data( (int) $cert->event_id );
	$logo_url   = $event['logo_url'];
	$design     = wcc_get_design_settings( (int) $cert->event_id );
	$fonts      = wcc_get_font_choices();
	$body_html  = wcc_render_certificate_body( $cert, $event );
	$event_name = $event['event_name'];
	$back_url   = $cert->event_id && get_post( $cert->event_id ) ? get_permalink( $cert->event_id ) : home_url( '/' );

	include WCC_PLUGIN_DIR . 'templates/certificate.php';
	exit;
}

// Admin page: list all issued certificates
function wcc_admin_page() {
	global $wpdb;
	$table    = $wpdb->prefix . 'wordcamp_certificates';
	$events   = wcc_get_events_for_select();
	$event_id = isset( $_GET['event_id'] ) ? absint( $_GET['event_id'] ) : 0;
	$certs    = $event_id
		? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_id = %d ORDER BY issued_at DESC", $event_id ) )
		: $wpdb->get_results( "SELECT * FROM {$table} ORDER BY issued_at DESC" );
	include WCC_PLUGIN_DIR . 'templates/admin.php';
}

// Attendees page: import CampTix export, view current list status
function wcc_attendees_page() {
	$events   = wcc_get_events_for_select();
	$event_id = isset( $_REQUEST['event_id'] ) ? absint( $_REQUEST['event_id'] ) : 0;
	if ( ! $event_id || WCC_EVENT_CPT !== get_post_type( $event_id ) ) {
		$event_id = wcc_get_default_event_id() ?: ( $events ? $events[0]->ID : 0 );
	}

	$import_result = null;
	if ( isset( $_POST['wcc_import_attendees'] ) && check_admin_referer( 'wcc_import_attendees' ) ) {
		if ( ! empty( $_FILES['wcc_attendee_csv']['tmp_name'] ) && UPLOAD_ERR_OK === $_FILES['wcc_attendee_csv']['error'] ) {
			$import_result = wcc_import_attendees_csv( $_FILES['wcc_attendee_csv']['tmp_name'], $event_id );
		} else {
			$import_result = new WP_Error( 'wcc_no_file', __( 'Please choose a CSV file to upload.', 'wordcamp-certificate' ) );
		}
	}

	$attendee_count = wcc_get_attendee_count( $event_id );
	$last_import    = wcc_get_last_import_time( $event_id );

	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$paged   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$listing = wcc_get_attendees( [ 'event_id' => $event_id, 'search' => $search, 'paged' => $paged, 'per_page' => 20 ] );

	include WCC_PLUGIN_DIR . 'templates/attendees.php';
}

// Register a dedicated block category so certificate blocks group together in the inserter.
add_filter( 'block_categories_all', 'wcc_block_categories' );
function wcc_block_categories( $categories ) {
	return array_merge( [ [ 'slug' => 'wordcamp-certificate', 'title' => __( 'WordCamp Certificate', 'wordcamp-certificate' ) ] ], $categories );
}

// Register blocks
add_action( 'init', 'wcc_register_block' );
function wcc_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) return;

	register_block_type( 'wordcamp-certificate/form', [
		'editor_script'   => 'wcc-block-editor',
		'render_callback' => 'wcc_block_render',
		'attributes'      => [
			'event'     => [ 'type' => 'string', 'default' => '' ],
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

	// Certificate Design block: full visual editor for the certificate look & feel.
	wp_register_script(
		'wcc-design-block-editor',
		WCC_PLUGIN_URL . 'assets/design-block.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-core-data' ],
		WCC_VERSION,
		true
	);
	// Load the certificate fonts inside the (iframed) editor so font choices
	// are visible while designing, not only on the issued certificate.
	wp_register_style( 'wcc-google-fonts', wcc_get_google_fonts_url(), [], null );
	wp_register_style(
		'wcc-design-block-editor',
		WCC_PLUGIN_URL . 'assets/design-block-editor.css',
		[ 'wcc-google-fonts' ],
		WCC_VERSION
	);
	wp_register_style(
		'wcc-cert-canvas',
		WCC_PLUGIN_URL . 'assets/cert-canvas.css',
		[],
		WCC_VERSION
	);
	register_block_type( 'wordcamp-certificate/design', [
		'editor_script' => 'wcc-design-block-editor',
		'editor_style'  => 'wcc-design-block-editor',
		'style'         => 'wcc-cert-canvas',
		'attributes'    => wcc_design_block_attributes(),
	] );

	// Certificate Field block: a movable token for dynamic per-attendee/event data.
	wp_register_script(
		'wcc-field-block',
		WCC_PLUGIN_URL . 'assets/field-block.js',
		[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ],
		WCC_VERSION,
		true
	);
	register_block_type( 'wordcamp-certificate/field', [
		'editor_script' => 'wcc-field-block',
		'style'         => 'wcc-cert-canvas',
		'attributes'    => [
			'field'         => [ 'type' => 'string', 'default' => 'full_name' ],
			'fontFamily'    => [ 'type' => 'string', 'default' => '' ],
			'fontSize'      => [ 'type' => 'number', 'default' => 0 ],
			'fontWeight'    => [ 'type' => 'string', 'default' => '' ],
			'fontStyle'     => [ 'type' => 'string', 'default' => '' ],
			'textTransform' => [ 'type' => 'string', 'default' => 'none' ],
			'letterSpacing' => [ 'type' => 'number', 'default' => 0 ],
			'color'         => [ 'type' => 'string', 'default' => '' ],
		],
	] );
}

/** Attribute schema for the design block, shared by PHP registration and used as a reference by design-block.js. */
function wcc_design_block_attributes() {
	$defaults = wcc_get_design_defaults();
	return [
		'primaryColor' => [ 'type' => 'string', 'default' => $defaults['primary_color'] ],
		'darkColor'    => [ 'type' => 'string', 'default' => $defaults['dark_color'] ],
		'goldColor'    => [ 'type' => 'string', 'default' => $defaults['gold_color'] ],
		'lightColor'   => [ 'type' => 'string', 'default' => $defaults['light_color'] ],
		'borderColor'  => [ 'type' => 'string', 'default' => $defaults['border_color'] ],
		'headingFont'  => [ 'type' => 'string', 'default' => $defaults['heading_font'] ],
		'bodyFont'     => [ 'type' => 'string', 'default' => $defaults['body_font'] ],
		'showLogo'     => [ 'type' => 'boolean', 'default' => $defaults['show_logo'] ],
		'frameStyle'   => [ 'type' => 'string', 'default' => $defaults['frame_style'] ],
		'orientation'  => [ 'type' => 'string', 'default' => $defaults['orientation'] ],
		'bgColor'      => [ 'type' => 'string', 'default' => $defaults['bg_color'] ],
		'bgImageId'    => [ 'type' => 'number', 'default' => 0 ],
		'bgImageUrl'   => [ 'type' => 'string', 'default' => '' ],
	];
}

function wcc_block_render( $attributes ) {
	$shortcode_atts = '';
	if ( ! empty( $attributes['event'] ) ) {
		$shortcode_atts .= ' event="' . esc_attr( $attributes['event'] ) . '"';
	}
	if ( ! empty( $attributes['eventName'] ) ) {
		$shortcode_atts .= ' event_name="' . esc_attr( $attributes['eventName'] ) . '"';
	}
	if ( ! empty( $attributes['eventDate'] ) ) {
		$shortcode_atts .= ' event_date="' . esc_attr( $attributes['eventDate'] ) . '"';
	}
	return do_shortcode( '[wordcamp_certificate' . $shortcode_atts . ']' );
}
