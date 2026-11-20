<?php
/**
 * Events: each WordCamp is a `wcc_event` post that owns its own details
 * (date, location, organizer, logo), its own certificate design (the
 * "Certificate Design" block in the post content), its own attendee list,
 * and its own unique public certificate link (the event permalink).
 *
 * Past events can be archived (kept, with all certificate links still
 * working, but moved out of the active list) and any event can be
 * duplicated to start the next one with the same design.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WCC_EVENT_CPT  = 'wcc_event';
const WCC_DB_VERSION = 2;

/* -------------------------------------------------------------------------
 * Post type
 * ---------------------------------------------------------------------- */

add_action( 'init', 'wcc_register_event_post_type', 5 );
function wcc_register_event_post_type() {
	register_post_type( WCC_EVENT_CPT, [
		'labels'             => [
			'name'               => __( 'Events', 'wordcamp-certificate' ),
			'singular_name'      => __( 'Event', 'wordcamp-certificate' ),
			'add_new'            => __( 'Add New Event', 'wordcamp-certificate' ),
			'add_new_item'       => __( 'Add New Event', 'wordcamp-certificate' ),
			'edit_item'          => __( 'Edit Event & Certificate Design', 'wordcamp-certificate' ),
			'new_item'           => __( 'New Event', 'wordcamp-certificate' ),
			'view_item'          => __( 'View Certificate Page', 'wordcamp-certificate' ),
			'search_items'       => __( 'Search Events', 'wordcamp-certificate' ),
			'not_found'          => __( 'No events found.', 'wordcamp-certificate' ),
			'not_found_in_trash' => __( 'No events found in Trash.', 'wordcamp-certificate' ),
			'all_items'          => __( 'Events', 'wordcamp-certificate' ),
		],
		'public'             => true,
		'publicly_queryable' => true,
		'exclude_from_search'=> true,
		'show_in_nav_menus'  => false,
		'show_ui'            => true,
		'show_in_menu'       => 'wordcamp-certificates',
		'show_in_rest'       => true,
		'has_archive'        => false,
		'rewrite'            => [ 'slug' => 'certificate', 'with_front' => false ],
		'supports'           => [ 'title', 'editor', 'revisions', 'custom-fields' ],
		'capability_type'    => 'page',
		'map_meta_cap'       => true,
		// New events start with the certificate design canvas ready to edit.
		'template'           => [ [ 'wordcamp-certificate/design' ] ],
	] );

	// Exposed to the block editor so the "Event Details" sidebar panel can edit
	// them and the certificate canvas can preview them live.
	$auth = function ( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	};
	foreach ( [ '_wcc_event_date', '_wcc_event_location', '_wcc_organizer_name' ] as $key ) {
		register_post_meta( WCC_EVENT_CPT, $key, [
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		] );
	}
	register_post_meta( WCC_EVENT_CPT, '_wcc_event_logo_id', [
		'type'              => 'integer',
		'single'            => true,
		'default'           => 0,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => $auth,
	] );
}

/* -------------------------------------------------------------------------
 * Install / upgrade
 * ---------------------------------------------------------------------- */

/** Does a column exist on a table? Works on both MySQL and Studio's SQLite. */
function wcc_column_exists( $table, $column ) {
	global $wpdb;
	return (bool) $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );
}

/** Add the event_id column to the certificate and attendee tables if missing. */
function wcc_add_event_columns() {
	global $wpdb;
	foreach ( [ $wpdb->prefix . 'wordcamp_certificates', $wpdb->prefix . 'wordcamp_attendees' ] as $table ) {
		if ( ! wcc_column_exists( $table, 'event_id' ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN event_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0" );
			$wpdb->query( "CREATE INDEX event_id ON {$table} (event_id)" );
		}
	}
}

/**
 * Runs once per DB version bump (also on fresh activation). Ensures the
 * tables have per-event columns and migrates a pre-1.3 single-event setup
 * (global options + unscoped certificates/attendees) into a first Event, so
 * every existing certificate link keeps working.
 */
add_action( 'init', 'wcc_maybe_upgrade', 20 );
function wcc_maybe_upgrade() {
	if ( get_option( 'wcc_needs_rewrite_flush' ) ) {
		delete_option( 'wcc_needs_rewrite_flush' );
		flush_rewrite_rules( false );
	}

	$installed = (int) get_option( 'wcc_db_version', 0 );
	if ( $installed >= WCC_DB_VERSION ) {
		return;
	}

	wcc_activate_tables();
	wcc_add_event_columns();

	global $wpdb;
	$certs_table     = $wpdb->prefix . 'wordcamp_certificates';
	$attendees_table = $wpdb->prefix . 'wordcamp_attendees';

	$has_legacy_data = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$certs_table} WHERE event_id = 0" )
		+ (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$attendees_table} WHERE event_id = 0" )
		+ ( false !== get_option( 'wcc_event_name', false ) ? 1 : 0 );

	if ( $has_legacy_data ) {
		$event_id = wcc_create_event_from_legacy_settings();
		if ( $event_id ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$certs_table} SET event_id = %d WHERE event_id = 0", $event_id ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$attendees_table} SET event_id = %d WHERE event_id = 0", $event_id ) );
		}
	}

	update_option( 'wcc_db_version', WCC_DB_VERSION );
	flush_rewrite_rules( false );
}

/** Build the first Event from the old single-event Settings page + global design. */
function wcc_create_event_from_legacy_settings() {
	global $wpdb;

	// Reuse the block markup of the old "Certificate Design" page, if one exists,
	// so the event opens in the editor looking exactly like the live certificate.
	$content     = '<!-- wp:wordcamp-certificate/design /-->';
	$design_post = $wpdb->get_var(
		"SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%<!-- wp:wordcamp-certificate/design%' AND post_type NOT IN ('revision','" . WCC_EVENT_CPT . "') AND post_status <> 'trash' ORDER BY post_modified DESC LIMIT 1"
	);
	if ( $design_post ) {
		$block = wcc_find_block( parse_blocks( get_post_field( 'post_content', $design_post ) ), 'wordcamp-certificate/design' );
		if ( $block ) {
			$content = serialize_block( $block );
		}
	}

	$event_id = wp_insert_post( [
		'post_type'    => WCC_EVENT_CPT,
		'post_status'  => 'publish',
		'post_title'   => get_option( 'wcc_event_name', 'WordCamp' ),
		'post_content' => $content,
	], true );
	if ( is_wp_error( $event_id ) ) {
		return 0;
	}

	update_post_meta( $event_id, '_wcc_event_date', get_option( 'wcc_event_date', '' ) );
	update_post_meta( $event_id, '_wcc_event_location', get_option( 'wcc_event_location', '' ) );
	update_post_meta( $event_id, '_wcc_organizer_name', get_option( 'wcc_organizer_name', get_bloginfo( 'name' ) ) );
	update_post_meta( $event_id, '_wcc_event_logo_id', (int) get_option( 'wcc_event_logo_id', 0 ) );
	if ( get_option( 'wcc_certificate_design' ) ) {
		update_post_meta( $event_id, '_wcc_design', get_option( 'wcc_certificate_design' ) );
	}
	if ( get_option( 'wcc_certificate_body_html' ) ) {
		update_post_meta( $event_id, '_wcc_body_html', get_option( 'wcc_certificate_body_html' ) );
	}

	return (int) $event_id;
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/** Is this event archived? */
function wcc_is_event_archived( $event_id ) {
	return '1' === get_post_meta( $event_id, '_wcc_archived', true );
}

/**
 * Resolve an event from an ID or slug. Returns the WP_Post or null.
 * Only published events (archived or not) are returned.
 */
function wcc_get_event( $id_or_slug ) {
	if ( ! $id_or_slug ) {
		return null;
	}
	$post = is_numeric( $id_or_slug )
		? get_post( (int) $id_or_slug )
		: get_page_by_path( sanitize_title( $id_or_slug ), OBJECT, WCC_EVENT_CPT );
	if ( ! $post || WCC_EVENT_CPT !== $post->post_type || 'publish' !== $post->post_status ) {
		return null;
	}
	return $post;
}

/** The newest published, non-archived event — the default for a bare [wordcamp_certificate] shortcode. */
function wcc_get_default_event_id() {
	$ids = get_posts( [
		'post_type'      => WCC_EVENT_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => [
			'relation' => 'OR',
			[ 'key' => '_wcc_archived', 'compare' => 'NOT EXISTS' ],
			[ 'key' => '_wcc_archived', 'value' => '1', 'compare' => '!=' ],
		],
	] );
	return $ids ? (int) $ids[0] : 0;
}

/** All events for admin dropdowns, active first, newest first. */
function wcc_get_events_for_select() {
	$posts = get_posts( [
		'post_type'      => WCC_EVENT_CPT,
		'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
	usort( $posts, function ( $a, $b ) {
		return (int) wcc_is_event_archived( $a->ID ) - (int) wcc_is_event_archived( $b->ID );
	} );
	return $posts;
}

/** Label for an event in admin dropdowns. */
function wcc_event_select_label( $event ) {
	$label = get_the_title( $event ) ?: __( '(untitled event)', 'wordcamp-certificate' );
	if ( wcc_is_event_archived( $event->ID ) ) {
		$label .= ' — ' . __( 'Archived', 'wordcamp-certificate' );
	} elseif ( 'publish' !== $event->post_status ) {
		$label .= ' — ' . __( 'Draft', 'wordcamp-certificate' );
	}
	return $label;
}

/** The event details used on the certificate. */
function wcc_get_event_data( $event_id ) {
	$event_id = (int) $event_id;
	$logo_id  = (int) get_post_meta( $event_id, '_wcc_event_logo_id', true );
	$date     = (string) get_post_meta( $event_id, '_wcc_event_date', true );
	$org      = (string) get_post_meta( $event_id, '_wcc_organizer_name', true );
	return [
		'event_id'       => $event_id,
		'event_name'     => get_the_title( $event_id ) ?: 'WordCamp',
		'event_date'     => '' !== $date ? $date : date_i18n( 'F j, Y' ),
		'event_location' => (string) get_post_meta( $event_id, '_wcc_event_location', true ),
		'organizer_name' => '' !== $org ? $org : get_bloginfo( 'name' ),
		'logo_url'       => $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '',
	];
}

/** The unique public link for an event's certificate page (where attendees claim theirs). */
function wcc_get_event_link( $event_id ) {
	return get_permalink( $event_id );
}

/** The link to one issued certificate, under its event's unique link. */
function wcc_get_certificate_url( $token, $event_id = 0 ) {
	if ( $event_id && get_post( $event_id ) ) {
		return add_query_arg( 'token', rawurlencode( $token ), get_permalink( $event_id ) );
	}
	return add_query_arg( [ 'wcc_cert' => '1', 'token' => rawurlencode( $token ) ], home_url( '/' ) );
}

/** Count certificates issued / attendees imported for an event. */
function wcc_count_for_event( $table_suffix, $event_id ) {
	global $wpdb;
	$table = $wpdb->prefix . $table_suffix;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_id = %d", $event_id ) );
}

/* -------------------------------------------------------------------------
 * Editor: "Event Details" sidebar panel + live data for the design canvas
 * ---------------------------------------------------------------------- */

add_action( 'enqueue_block_editor_assets', 'wcc_enqueue_event_editor_assets' );
function wcc_enqueue_event_editor_assets() {
	$fonts = [];
	foreach ( wcc_get_font_choices() as $key => $font ) {
		$fonts[] = [ 'value' => $key, 'label' => $font['label'], 'family' => $font['family'] ];
	}
	$data = [
		'fonts'       => $fonts,
		'isEvent'     => false,
		'defaultLogo' => includes_url( 'images/w-logo-blue.png' ),
		'placeholders'=> [
			'{full_name}'      => __( 'Attendee name', 'wordcamp-certificate' ),
			'{event_name}'     => __( 'Event name', 'wordcamp-certificate' ),
			'{event_date}'     => __( 'Event date', 'wordcamp-certificate' ),
			'{event_location}' => __( 'Event location', 'wordcamp-certificate' ),
			'{organizer_name}' => __( 'Organizer name', 'wordcamp-certificate' ),
			'{cert_id}'        => __( 'Certificate ID', 'wordcamp-certificate' ),
			'{date_issued}'    => __( 'Date issued', 'wordcamp-certificate' ),
		],
	];

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && WCC_EVENT_CPT === $screen->post_type ) {
		$data['isEvent']     = true;
		$data['siteName']    = get_bloginfo( 'name' );
		wp_enqueue_script(
			'wcc-event-panel',
			WCC_PLUGIN_URL . 'assets/event-panel.js',
			[ 'wp-plugins', 'wp-editor', 'wp-data', 'wp-core-data', 'wp-components', 'wp-element', 'wp-block-editor', 'wp-i18n' ],
			WCC_VERSION,
			true
		);
	}

	// One shared data object for the design block, field block and panel.
	wp_add_inline_script( 'wcc-design-block-editor', 'window.wccDesignData = ' . wp_json_encode( $data ) . ';', 'before' );
}

/* -------------------------------------------------------------------------
 * Events list: columns, Active/Archived views, Archive/Restore/Duplicate
 * ---------------------------------------------------------------------- */

add_filter( 'manage_' . WCC_EVENT_CPT . '_posts_columns', 'wcc_event_columns' );
function wcc_event_columns( $columns ) {
	return [
		'cb'           => $columns['cb'],
		'title'        => __( 'Event', 'wordcamp-certificate' ),
		'wcc_date'     => __( 'Event Date', 'wordcamp-certificate' ),
		'wcc_status'   => __( 'Status', 'wordcamp-certificate' ),
		'wcc_link'     => __( 'Certificate Link', 'wordcamp-certificate' ),
		'wcc_counts'   => __( 'Attendees / Certificates', 'wordcamp-certificate' ),
	];
}

add_action( 'manage_' . WCC_EVENT_CPT . '_posts_custom_column', 'wcc_event_column_content', 10, 2 );
function wcc_event_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'wcc_date':
			$date     = get_post_meta( $post_id, '_wcc_event_date', true );
			$location = get_post_meta( $post_id, '_wcc_event_location', true );
			echo esc_html( $date ?: '—' );
			if ( $location ) {
				echo '<br><span class="description">' . esc_html( $location ) . '</span>';
			}
			break;
		case 'wcc_status':
			if ( wcc_is_event_archived( $post_id ) ) {
				echo '<span class="wcc-status-badge wcc-status-badge--archived">' . esc_html__( 'Archived', 'wordcamp-certificate' ) . '</span>';
			} elseif ( 'publish' === get_post_status( $post_id ) ) {
				echo '<span class="wcc-status-badge wcc-status-badge--active">' . esc_html__( 'Active', 'wordcamp-certificate' ) . '</span>';
			} else {
				echo '<span class="wcc-status-badge">' . esc_html__( 'Draft', 'wordcamp-certificate' ) . '</span>';
			}
			break;
		case 'wcc_link':
			if ( 'publish' === get_post_status( $post_id ) ) {
				$link = wcc_get_event_link( $post_id );
				echo '<input type="text" readonly class="wcc-link-input" onfocus="this.select()" value="' . esc_attr( $link ) . '" />';
			} else {
				echo '<span class="description">' . esc_html__( 'Publish to get a link', 'wordcamp-certificate' ) . '</span>';
			}
			break;
		case 'wcc_counts':
			$attendees_url = add_query_arg( [ 'page' => 'wordcamp-certificate-attendees', 'event_id' => $post_id ], admin_url( 'admin.php' ) );
			$certs_url     = add_query_arg( [ 'page' => 'wordcamp-certificates', 'event_id' => $post_id ], admin_url( 'admin.php' ) );
			printf(
				'<a href="%s">%s</a> / <a href="%s">%s</a>',
				esc_url( $attendees_url ),
				esc_html( number_format_i18n( wcc_count_for_event( 'wordcamp_attendees', $post_id ) ) ),
				esc_url( $certs_url ),
				esc_html( number_format_i18n( wcc_count_for_event( 'wordcamp_certificates', $post_id ) ) )
			);
			break;
	}
}

/** "Active" / "Archived" views above the events list. */
add_filter( 'views_edit-' . WCC_EVENT_CPT, 'wcc_event_views' );
function wcc_event_views( $views ) {
	$current  = isset( $_GET['wcc_view'] ) ? sanitize_key( $_GET['wcc_view'] ) : '';
	$base     = admin_url( 'edit.php?post_type=' . WCC_EVENT_CPT );
	$archived = count( get_posts( [
		'post_type'      => WCC_EVENT_CPT,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_wcc_archived',
		'meta_value'     => '1',
	] ) );

	$views['wcc_active'] = sprintf(
		'<a href="%s"%s>%s</a>',
		esc_url( add_query_arg( 'wcc_view', 'active', $base ) ),
		'active' === $current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Active', 'wordcamp-certificate' )
	);
	$views['wcc_archived'] = sprintf(
		'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
		esc_url( add_query_arg( 'wcc_view', 'archived', $base ) ),
		'archived' === $current ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Archived', 'wordcamp-certificate' ),
		esc_html( number_format_i18n( $archived ) )
	);
	if ( $current && isset( $views['all'] ) ) {
		$views['all'] = str_replace( ' class="current" aria-current="page"', '', $views['all'] );
	}
	return $views;
}

add_action( 'pre_get_posts', 'wcc_filter_event_views' );
function wcc_filter_event_views( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || WCC_EVENT_CPT !== $query->get( 'post_type' ) ) {
		return;
	}
	$view = isset( $_GET['wcc_view'] ) ? sanitize_key( $_GET['wcc_view'] ) : '';
	if ( 'archived' === $view ) {
		$query->set( 'meta_query', [ [ 'key' => '_wcc_archived', 'value' => '1' ] ] );
	} elseif ( 'active' === $view ) {
		$query->set( 'meta_query', [
			'relation' => 'OR',
			[ 'key' => '_wcc_archived', 'compare' => 'NOT EXISTS' ],
			[ 'key' => '_wcc_archived', 'value' => '1', 'compare' => '!=' ],
		] );
	}
}

add_filter( 'post_row_actions', 'wcc_event_row_actions', 10, 2 );
function wcc_event_row_actions( $actions, $post ) {
	if ( WCC_EVENT_CPT !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}
	unset( $actions['inline hide-if-no-js'] ); // Quick Edit can't edit event meta.

	$archived = wcc_is_event_archived( $post->ID );
	$action   = $archived ? 'wcc_restore_event' : 'wcc_archive_event';
	$actions[ $action ] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . $action . '&event_id=' . $post->ID ), $action . '_' . $post->ID ) ),
		$archived ? esc_html__( 'Restore', 'wordcamp-certificate' ) : esc_html__( 'Archive', 'wordcamp-certificate' )
	);
	$actions['wcc_duplicate_event'] = sprintf(
		'<a href="%s" title="%s">%s</a>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wcc_duplicate_event&event_id=' . $post->ID ), 'wcc_duplicate_event_' . $post->ID ) ),
		esc_attr__( 'Start a new event with this certificate design', 'wordcamp-certificate' ),
		esc_html__( 'Duplicate as New Event', 'wordcamp-certificate' )
	);
	return $actions;
}

add_action( 'admin_post_wcc_archive_event', 'wcc_handle_archive_event' );
add_action( 'admin_post_wcc_restore_event', 'wcc_handle_archive_event' );
function wcc_handle_archive_event() {
	$event_id = absint( $_GET['event_id'] ?? 0 );
	$action   = sanitize_key( $_GET['action'] ?? '' );
	check_admin_referer( $action . '_' . $event_id );
	if ( ! current_user_can( 'edit_post', $event_id ) || WCC_EVENT_CPT !== get_post_type( $event_id ) ) {
		wp_die( esc_html__( 'You are not allowed to change this event.', 'wordcamp-certificate' ) );
	}
	if ( 'wcc_archive_event' === $action ) {
		update_post_meta( $event_id, '_wcc_archived', '1' );
	} else {
		delete_post_meta( $event_id, '_wcc_archived' );
	}
	wp_safe_redirect( add_query_arg( 'wcc_notice', 'wcc_archive_event' === $action ? 'archived' : 'restored', admin_url( 'edit.php?post_type=' . WCC_EVENT_CPT ) ) );
	exit;
}

add_action( 'admin_post_wcc_duplicate_event', 'wcc_handle_duplicate_event' );
function wcc_handle_duplicate_event() {
	$source_id = absint( $_GET['event_id'] ?? 0 );
	check_admin_referer( 'wcc_duplicate_event_' . $source_id );
	$source = get_post( $source_id );
	if ( ! $source || WCC_EVENT_CPT !== $source->post_type || ! current_user_can( 'edit_post', $source_id ) || ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You are not allowed to duplicate this event.', 'wordcamp-certificate' ) );
	}

	$new_id = wp_insert_post( [
		'post_type'    => WCC_EVENT_CPT,
		'post_status'  => 'draft',
		/* translators: %s: original event title */
		'post_title'   => sprintf( __( '%s (Copy)', 'wordcamp-certificate' ), $source->post_title ),
		'post_content' => wp_slash( $source->post_content ),
	], true );
	if ( is_wp_error( $new_id ) ) {
		wp_die( esc_html( $new_id->get_error_message() ) );
	}

	// Carry over the design and the reusable bits (organizer, logo, location).
	// The date is left blank because it's almost always different.
	foreach ( [ '_wcc_design', '_wcc_body_html', '_wcc_event_location', '_wcc_organizer_name', '_wcc_event_logo_id' ] as $key ) {
		$value = get_post_meta( $source_id, $key, true );
		if ( '' !== $value && null !== $value ) {
			update_post_meta( $new_id, $key, wp_slash( $value ) );
		}
	}

	wp_safe_redirect( get_edit_post_link( $new_id, 'raw' ) );
	exit;
}

add_action( 'admin_notices', 'wcc_event_admin_notices' );
function wcc_event_admin_notices() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-' . WCC_EVENT_CPT !== $screen->id || empty( $_GET['wcc_notice'] ) ) {
		return;
	}
	$notice = sanitize_key( $_GET['wcc_notice'] );
	if ( 'archived' === $notice ) {
		$msg = __( 'Event archived. Its certificate links keep working; find it under "Archived".', 'wordcamp-certificate' );
	} elseif ( 'restored' === $notice ) {
		$msg = __( 'Event restored to the active list.', 'wordcamp-certificate' );
	} else {
		return;
	}
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
}

/* -------------------------------------------------------------------------
 * Public certificate page (the event's unique link)
 * ---------------------------------------------------------------------- */

/**
 * On an event's page, replace the post content (the design canvas) with the
 * claim form for that event. Runs late so wpautop doesn't touch the form.
 */
add_filter( 'the_content', 'wcc_event_page_content', 99 );
function wcc_event_page_content( $content ) {
	if ( ! is_singular( WCC_EVENT_CPT ) || (int) get_the_ID() !== (int) get_queried_object_id() ) {
		return $content;
	}
	return wcc_render_form( get_queried_object_id() );
}

/** Give the "Certificate Form" block the list of events to choose from. */
add_action( 'enqueue_block_editor_assets', 'wcc_localize_block_events' );
function wcc_localize_block_events() {
	$events = [];
	foreach ( wcc_get_events_for_select() as $event ) {
		if ( 'publish' === $event->post_status ) {
			$events[] = [ 'value' => $event->post_name, 'label' => wcc_event_select_label( $event ) ];
		}
	}
	wp_localize_script( 'wcc-block-editor', 'wccBlockData', [ 'events' => $events ] );
}
