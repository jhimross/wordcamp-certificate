<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wcc-admin-wrap">
  <h1 class="wcc-admin-title">
    <span class="dashicons dashicons-groups"></span>
    <?php esc_html_e( 'Attendee List', 'wordcamp-certificate' ); ?>
  </h1>
  <p class="wcc-admin-desc">
    <?php esc_html_e( 'Each event has its own attendee list. Import the CampTix attendee export for an event so its certificates are only issued to people on the list. Attendees must submit the same name and email used at registration.', 'wordcamp-certificate' ); ?>
  </p>

  <?php if ( ! $event_id ) : ?>
    <div class="wcc-empty-state">
      <p><?php esc_html_e( 'Create an event first, then import its attendee list here.', 'wordcamp-certificate' ); ?></p>
      <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . WCC_EVENT_CPT ) ); ?>"><?php esc_html_e( 'Add New Event', 'wordcamp-certificate' ); ?></a></p>
    </div>
  </div>
  <?php return; endif; ?>

  <form method="get" class="wcc-event-filter wcc-event-filter--block">
    <input type="hidden" name="page" value="wordcamp-certificate-attendees" />
    <label for="wcc-attendee-event"><strong><?php esc_html_e( 'Event:', 'wordcamp-certificate' ); ?></strong></label>
    <select id="wcc-attendee-event" name="event_id" onchange="this.form.submit()">
      <?php foreach ( $events as $ev ) : ?>
        <option value="<?php echo esc_attr( $ev->ID ); ?>" <?php selected( $event_id, $ev->ID ); ?>><?php echo esc_html( wcc_event_select_label( $ev ) ); ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="button"><?php esc_html_e( 'Switch', 'wordcamp-certificate' ); ?></button></noscript>
  </form>

  <?php if ( $import_result instanceof WP_Error ) : ?>
    <div class="notice notice-error"><p><?php echo esc_html( $import_result->get_error_message() ); ?></p></div>
  <?php elseif ( is_array( $import_result ) ) : ?>
    <div class="notice notice-success">
      <p>
        <?php printf(
          /* translators: %s: number of attendees imported */
          esc_html__( 'Import complete — %s attendees are now on the list.', 'wordcamp-certificate' ),
          '<strong>' . esc_html( number_format_i18n( $import_result['imported'] ) ) . '</strong>'
        ); ?>
      </p>
    </div>
  <?php endif; ?>

  <div class="wcc-attendee-status<?php echo $attendee_count > 0 ? ' wcc-attendee-status--ok' : ' wcc-attendee-status--empty'; ?>">
    <span class="dashicons <?php echo $attendee_count > 0 ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
    <div>
      <?php if ( $attendee_count > 0 ) : ?>
        <strong><?php printf(
          /* translators: %s: number of attendees */
          esc_html__( '%s attendees imported.', 'wordcamp-certificate' ),
          '<strong>' . esc_html( number_format_i18n( $attendee_count ) ) . '</strong>'
        ); ?></strong>
        <?php if ( $last_import ) : ?>
          <p class="description">
            <?php printf(
              /* translators: %s: date/time */
              esc_html__( 'Last imported %s.', 'wordcamp-certificate' ),
              esc_html( date_i18n( 'F j, Y g:i a', strtotime( $last_import ) ) )
            ); ?>
          </p>
        <?php endif; ?>
      <?php else : ?>
        <strong><?php esc_html_e( 'No attendee list imported yet.', 'wordcamp-certificate' ); ?></strong>
        <p class="description"><?php esc_html_e( 'Certificate requests will be blocked until you import the attendee CSV below.', 'wordcamp-certificate' ); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="wcc-attendee-import-form">
    <?php wp_nonce_field( 'wcc_import_attendees' ); ?>
    <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
    <h3><?php esc_html_e( 'Import CampTix Export', 'wordcamp-certificate' ); ?></h3>
    <p class="description">
      <?php printf(
        /* translators: %s: event name */
        esc_html__( 'In CampTix, go to Attendees → Export CSV, then upload that file here. Importing a new file replaces the attendee list for %s only.', 'wordcamp-certificate' ),
        '<strong>' . esc_html( get_the_title( $event_id ) ) . '</strong>'
      ); ?>
    </p>
    <input type="file" name="wcc_attendee_csv" accept=".csv" required />
    <p class="submit">
      <input type="submit" name="wcc_import_attendees" class="button button-primary" value="<?php esc_attr_e( 'Import Attendees', 'wordcamp-certificate' ); ?>" />
    </p>
  </form>

  <div class="wcc-shortcode-box">
    <h3><?php esc_html_e( 'What gets imported', 'wordcamp-certificate' ); ?></h3>
    <p><?php esc_html_e( 'Only the essential fields are kept from the CampTix export:', 'wordcamp-certificate' ); ?></p>
    <ul style="list-style:disc;margin-left:20px;">
      <li><?php esc_html_e( 'Attendee ID', 'wordcamp-certificate' ); ?></li>
      <li><?php esc_html_e( 'First Name / Last Name', 'wordcamp-certificate' ); ?></li>
      <li><?php esc_html_e( 'E-mail Address', 'wordcamp-certificate' ); ?></li>
      <li><?php esc_html_e( 'Ticket Type', 'wordcamp-certificate' ); ?></li>
    </ul>
    <p><?php esc_html_e( 'Only rows with a "Publish" status and a valid email address are imported; cancelled or refunded tickets are skipped automatically.', 'wordcamp-certificate' ); ?></p>
  </div>

  <?php if ( $attendee_count > 0 ) : ?>
  <div class="wcc-attendee-list-box">
    <div class="wcc-attendee-list-header">
      <h3><?php esc_html_e( 'Imported Attendees', 'wordcamp-certificate' ); ?></h3>
      <form method="get" class="wcc-attendee-search-form">
        <input type="hidden" name="page" value="wordcamp-certificate-attendees" />
        <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name, email, or ID…', 'wordcamp-certificate' ); ?>" />
        <button type="submit" class="button"><?php esc_html_e( 'Search', 'wordcamp-certificate' ); ?></button>
        <?php if ( '' !== $search ) : ?>
          <a href="<?php echo esc_url( remove_query_arg( 's' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'wordcamp-certificate' ); ?></a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ( empty( $listing['rows'] ) ) : ?>
      <p class="description"><?php esc_html_e( 'No attendees match your search.', 'wordcamp-certificate' ); ?></p>
    <?php else : ?>
      <table class="widefat striped wcc-table">
        <thead>
          <tr>
            <th><?php esc_html_e( 'Attendee ID', 'wordcamp-certificate' ); ?></th>
            <th><?php esc_html_e( 'First Name', 'wordcamp-certificate' ); ?></th>
            <th><?php esc_html_e( 'Last Name', 'wordcamp-certificate' ); ?></th>
            <th><?php esc_html_e( 'Email', 'wordcamp-certificate' ); ?></th>
            <th><?php esc_html_e( 'Ticket Type', 'wordcamp-certificate' ); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $listing['rows'] as $a ) : ?>
          <tr>
            <td><?php echo esc_html( $a->attendee_id ); ?></td>
            <td><?php echo esc_html( $a->first_name ); ?></td>
            <td><?php echo esc_html( $a->last_name ); ?></td>
            <td><?php echo esc_html( $a->email ); ?></td>
            <td><?php echo esc_html( $a->ticket_type ); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php
      $total_pages = (int) ceil( $listing['total'] / $listing['per_page'] );
      if ( $total_pages > 1 ) :
      ?>
        <div class="wcc-attendee-pagination">
          <span class="description">
            <?php printf(
              /* translators: 1: current page, 2: total pages, 3: total attendees */
              esc_html__( 'Page %1$s of %2$s — %3$s total', 'wordcamp-certificate' ),
              esc_html( number_format_i18n( $listing['paged'] ) ),
              esc_html( number_format_i18n( $total_pages ) ),
              esc_html( number_format_i18n( $listing['total'] ) )
            ); ?>
          </span>
          <span class="wcc-attendee-pagination-links">
            <?php
            $base_args = array_filter( [ 'page' => 'wordcamp-certificate-attendees', 'event_id' => $event_id, 's' => $search ] );
            if ( $listing['paged'] > 1 ) :
              $prev_url = add_query_arg( array_merge( $base_args, [ 'paged' => $listing['paged'] - 1 ] ) );
            ?>
              <a class="button" href="<?php echo esc_url( $prev_url ); ?>">&laquo; <?php esc_html_e( 'Previous', 'wordcamp-certificate' ); ?></a>
            <?php endif; ?>
            <?php if ( $listing['paged'] < $total_pages ) :
              $next_url = add_query_arg( array_merge( $base_args, [ 'paged' => $listing['paged'] + 1 ] ) );
            ?>
              <a class="button" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next', 'wordcamp-certificate' ); ?> &raquo;</a>
            <?php endif; ?>
          </span>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
