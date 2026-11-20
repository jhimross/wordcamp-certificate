<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wcc-admin-wrap">
  <h1 class="wcc-admin-title">
    <span class="dashicons dashicons-awards"></span>
    <?php esc_html_e( 'WordCamp Certificates Issued', 'wordcamp-certificate' ); ?>
  </h1>
  <div class="wcc-admin-toolbar">
    <p class="wcc-admin-desc">
      <?php printf(
        esc_html__( 'Total certificates issued: %s', 'wordcamp-certificate' ),
        '<strong>' . count( $certs ) . '</strong>'
      ); ?>
    </p>
    <?php if ( $events ) : ?>
    <form method="get" class="wcc-event-filter">
      <input type="hidden" name="page" value="wordcamp-certificates" />
      <label for="wcc-event-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by event', 'wordcamp-certificate' ); ?></label>
      <select id="wcc-event-filter" name="event_id" onchange="this.form.submit()">
        <option value="0"><?php esc_html_e( 'All events', 'wordcamp-certificate' ); ?></option>
        <?php foreach ( $events as $ev ) : ?>
          <option value="<?php echo esc_attr( $ev->ID ); ?>" <?php selected( $event_id, $ev->ID ); ?>><?php echo esc_html( wcc_event_select_label( $ev ) ); ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="button"><?php esc_html_e( 'Filter', 'wordcamp-certificate' ); ?></button></noscript>
    </form>
    <?php endif; ?>
  </div>

  <?php if ( empty( $certs ) ) : ?>
    <div class="wcc-empty-state">
      <span class="dashicons dashicons-awards wcc-empty-icon"></span>
      <p><?php esc_html_e( 'No certificates have been generated yet.', 'wordcamp-certificate' ); ?></p>
      <?php if ( $events ) : ?>
        <p><?php esc_html_e( 'Share an event\'s certificate link (WC Certificates → Events) with its attendees, or add the [wordcamp_certificate event="your-event-slug"] shortcode to any page.', 'wordcamp-certificate' ); ?></p>
      <?php else : ?>
        <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . WCC_EVENT_CPT ) ); ?>"><?php esc_html_e( 'Create your first event', 'wordcamp-certificate' ); ?></a></p>
      <?php endif; ?>
    </div>
  <?php else : ?>
    <table class="widefat striped wcc-table">
      <thead>
        <tr>
          <th><?php esc_html_e( '#', 'wordcamp-certificate' ); ?></th>
          <th><?php esc_html_e( 'Full Name', 'wordcamp-certificate' ); ?></th>
          <th><?php esc_html_e( 'Event', 'wordcamp-certificate' ); ?></th>
          <th><?php esc_html_e( 'Email', 'wordcamp-certificate' ); ?></th>
          <th><?php esc_html_e( 'Date Issued', 'wordcamp-certificate' ); ?></th>
          <th><?php esc_html_e( 'Certificate', 'wordcamp-certificate' ); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ( $certs as $i => $c ) :
          $cert_url = wcc_get_certificate_url( $c->cert_token, (int) $c->event_id );
        ?>
        <tr>
          <td><?php echo intval( $i + 1 ); ?></td>
          <td><strong><?php echo esc_html( $c->full_name ); ?></strong></td>
          <td><?php echo $c->event_id ? esc_html( get_the_title( $c->event_id ) ) : '—'; ?></td>
          <td><?php echo esc_html( $c->email ); ?></td>
          <td><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $c->issued_at ) ) ); ?></td>
          <td>
            <a href="<?php echo esc_url( $cert_url ); ?>" target="_blank" class="button button-small">
              <?php esc_html_e( 'View Certificate', 'wordcamp-certificate' ); ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
