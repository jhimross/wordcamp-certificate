<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap wcc-admin-wrap">
  <h1 class="wcc-admin-title">
    <span class="dashicons dashicons-admin-settings"></span>
    <?php esc_html_e( 'Certificate Settings', 'wordcamp-certificate' ); ?>
  </h1>

  <form method="post" class="wcc-settings-form">
    <?php wp_nonce_field( 'wcc_settings' ); ?>

    <table class="form-table">
      <tr>
        <th scope="row">
          <label for="wcc_event_name"><?php esc_html_e( 'Event Name', 'wordcamp-certificate' ); ?></label>
        </th>
        <td>
          <input type="text" id="wcc_event_name" name="wcc_event_name" class="regular-text"
            value="<?php echo esc_attr( get_option( 'wcc_event_name', 'WordCamp' ) ); ?>" />
          <p class="description"><?php esc_html_e( 'e.g. WordCamp Manila 2025', 'wordcamp-certificate' ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="wcc_event_date"><?php esc_html_e( 'Event Date', 'wordcamp-certificate' ); ?></label>
        </th>
        <td>
          <input type="text" id="wcc_event_date" name="wcc_event_date" class="regular-text"
            value="<?php echo esc_attr( get_option( 'wcc_event_date', '' ) ); ?>"
            placeholder="<?php esc_attr_e( 'e.g. July 12–13, 2025', 'wordcamp-certificate' ); ?>" />
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="wcc_event_location"><?php esc_html_e( 'Event Location', 'wordcamp-certificate' ); ?></label>
        </th>
        <td>
          <input type="text" id="wcc_event_location" name="wcc_event_location" class="regular-text"
            value="<?php echo esc_attr( get_option( 'wcc_event_location', '' ) ); ?>"
            placeholder="<?php esc_attr_e( 'e.g. Manila, Philippines', 'wordcamp-certificate' ); ?>" />
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="wcc_organizer_name"><?php esc_html_e( 'Organizer Name', 'wordcamp-certificate' ); ?></label>
        </th>
        <td>
          <input type="text" id="wcc_organizer_name" name="wcc_organizer_name" class="regular-text"
            value="<?php echo esc_attr( get_option( 'wcc_organizer_name', get_bloginfo( 'name' ) ) ); ?>" />
          <p class="description"><?php esc_html_e( 'Appears on the signature line of the certificate.', 'wordcamp-certificate' ); ?></p>
        </td>
      </tr>
    </table>

    <div class="wcc-shortcode-box">
      <h3><?php esc_html_e( '📋 How to Use', 'wordcamp-certificate' ); ?></h3>
      <p><?php esc_html_e( 'Add this shortcode to any page or post:', 'wordcamp-certificate' ); ?></p>
      <code class="wcc-shortcode-code">[wordcamp_certificate]</code>
      <p><?php esc_html_e( 'Or with custom overrides:', 'wordcamp-certificate' ); ?></p>
      <code class="wcc-shortcode-code">[wordcamp_certificate event_name="WordCamp Manila 2025" event_date="July 12, 2025"]</code>
    </div>

    <p class="submit">
      <input type="submit" name="wcc_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'wordcamp-certificate' ); ?>" />
    </p>
  </form>
</div>
