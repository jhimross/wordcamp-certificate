<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $event_name ); ?> – Certificate of Attendance</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --wc-blue:   #21759b;
    --wc-dark:   #0a2a40;
    --wc-gold:   #c9a84c;
    --wc-light:  #f0f7fb;
    --wc-border: #d4e8f3;
  }

  html, body {
    width: 100%; height: 100%;
    background: #e8eff4;
    font-family: 'Inter', sans-serif;
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh;
  }

  .cert-wrap {
    width: 900px; max-width: 96vw;
    padding: 24px;
  }

  /* ── Certificate card ── */
  .cert {
    background: #fff;
    position: relative;
    border: 3px solid var(--wc-blue);
    border-radius: 4px;
    padding: 56px 72px;
    overflow: hidden;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
  }

  /* Corner ornaments */
  .cert::before, .cert::after {
    content: '';
    position: absolute;
    width: 80px; height: 80px;
    border: 3px solid var(--wc-gold);
  }
  .cert::before { top: 12px; left: 12px;  border-right: none; border-bottom: none; }
  .cert::after  { bottom: 12px; right: 12px; border-left: none; border-top: none; }

  /* Inner border */
  .cert-inner {
    border: 1px solid var(--wc-border);
    padding: 40px;
    position: relative;
  }
  .cert-inner::before, .cert-inner::after {
    content: '';
    position: absolute;
    width: 48px; height: 48px;
    border: 1px solid var(--wc-gold);
  }
  .cert-inner::before { bottom: -1px; left: -1px; border-top: none; border-right: none; }
  .cert-inner::after  { top: -1px; right: -1px;   border-bottom: none; border-left: none; }

  /* Header row */
  .cert-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 32px;
  }

  .wc-logo {
    width: 56px; height: 56px;
    background: var(--wc-blue);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .wc-logo svg { width: 34px; height: 34px; fill: #fff; }

  .cert-event-name {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--wc-dark);
    line-height: 1.1;
  }

  /* Divider */
  .cert-divider {
    display: flex; align-items: center; gap: 12px;
    margin: 24px 0;
  }
  .cert-divider::before, .cert-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(to right, transparent, var(--wc-gold));
  }
  .cert-divider::after {
    background: linear-gradient(to left, transparent, var(--wc-gold));
  }
  .cert-divider-diamond {
    width: 8px; height: 8px;
    background: var(--wc-gold);
    transform: rotate(45deg);
  }

  /* Main body */
  .cert-body { text-align: center; }

  .cert-subtitle {
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: var(--wc-blue);
    margin-bottom: 8px;
  }

  .cert-title {
    font-family: 'Playfair Display', serif;
    font-size: 42px;
    font-weight: 700;
    color: var(--wc-dark);
    line-height: 1.1;
    margin-bottom: 24px;
  }

  .cert-presented {
    font-size: 14px;
    color: #6b7280;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 16px;
  }

  .cert-name {
    font-family: 'Playfair Display', serif;
    font-size: 48px;
    font-weight: 600;
    color: var(--wc-blue);
    line-height: 1.2;
    margin-bottom: 8px;
    position: relative;
    display: inline-block;
  }
  .cert-name::after {
    content: '';
    display: block;
    height: 2px;
    background: linear-gradient(to right, transparent, var(--wc-gold), transparent);
    margin-top: 6px;
  }

  .cert-desc {
    font-size: 15px;
    color: #374151;
    line-height: 1.7;
    max-width: 520px;
    margin: 20px auto 0;
  }

  /* Event details pill */
  .cert-details {
    display: inline-flex;
    gap: 24px;
    background: var(--wc-light);
    border: 1px solid var(--wc-border);
    border-radius: 40px;
    padding: 10px 28px;
    margin-top: 28px;
    font-size: 13px;
    color: var(--wc-dark);
    font-weight: 500;
  }
  .cert-detail-item {
    display: flex; align-items: center; gap: 6px;
  }
  .cert-detail-icon { color: var(--wc-blue); font-size: 14px; }

  /* Footer */
  .cert-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 44px;
    padding-top: 24px;
    border-top: 1px dashed var(--wc-border);
  }
  .cert-sig {
    text-align: center;
    min-width: 160px;
  }
  .cert-sig-line {
    height: 1px;
    background: var(--wc-dark);
    margin-bottom: 8px;
  }
  .cert-sig-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #6b7280;
  }
  .cert-sig-name {
    font-family: 'Playfair Display', serif;
    font-size: 15px;
    color: var(--wc-dark);
    font-weight: 600;
    margin-top: 2px;
  }

  .cert-seal {
    text-align: center;
  }
  .cert-seal-circle {
    width: 80px; height: 80px;
    border-radius: 50%;
    border: 2px solid var(--wc-gold);
    display: flex; align-items: center; justify-content: center;
    flex-direction: column;
    gap: 2px;
    margin: 0 auto;
  }
  .cert-seal-inner {
    width: 64px; height: 64px;
    border-radius: 50%;
    border: 1px solid var(--wc-blue);
    background: var(--wc-light);
    display: flex; align-items: center; justify-content: center;
  }
  .cert-seal-inner svg { width: 32px; height: 32px; fill: var(--wc-blue); }

  .cert-id {
    font-size: 10px;
    color: #9ca3af;
    margin-top: 4px;
    letter-spacing: 1px;
  }

  /* Print button */
  .cert-actions {
    text-align: center;
    margin-top: 24px;
    display: flex;
    gap: 12px;
    justify-content: center;
  }
  .cert-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.2s;
  }
  .cert-btn-primary {
    background: var(--wc-blue);
    color: #fff;
  }
  .cert-btn-primary:hover { background: var(--wc-dark); color: #fff; }
  .cert-btn-secondary {
    background: #fff;
    color: var(--wc-dark);
    border: 1px solid var(--wc-border);
  }
  .cert-btn-secondary:hover { background: var(--wc-light); }

  @media print {
    html, body { background: #fff; display: block; }
    .cert-wrap { width: 100%; padding: 0; }
    .cert-actions { display: none; }
    .cert { border-color: #000; }
  }

  @media (max-width: 600px) {
    .cert { padding: 28px 20px; }
    .cert-inner { padding: 20px; }
    .cert-title { font-size: 28px; }
    .cert-name  { font-size: 32px; }
    .cert-footer { flex-direction: column; align-items: center; gap: 24px; }
    .cert-details { flex-direction: column; gap: 8px; padding: 12px 20px; }
  }
</style>
</head>
<body>
<div class="cert-wrap">
  <!-- ── Certificate ── -->
  <div class="cert" id="certificate">
    <div class="cert-inner">

      <!-- Header -->
      <div class="cert-header">
        <div class="wc-logo">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12.158 12.786l-2.698 7.84c.806.237 1.657.365 2.54.365a9.294 9.294 0 0 0 3.848-.83 1.02 1.02 0 0 1-.08-.155l-3.61-7.22zM3.008 7.44L7.38 19.8a9.338 9.338 0 0 0 5.77-12.48 9.338 9.338 0 0 0-10.142.12zm16.06 9.29a9.312 9.312 0 0 0 1.562-5.23c0-3.28-1.69-6.16-4.252-7.84l3.576 12.432a9.27 9.27 0 0 0 .114.638zM12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"/></svg>
        </div>
        <div class="cert-event-name"><?php echo esc_html( $event_name ); ?></div>
      </div>

      <div class="cert-divider"><div class="cert-divider-diamond"></div></div>

      <!-- Body -->
      <div class="cert-body">
        <p class="cert-subtitle">This is to certify that</p>
        <h1 class="cert-title">Certificate of Attendance</h1>
        <p class="cert-presented">proudly presented to</p>
        <div class="cert-name"><?php echo esc_html( $cert->full_name ); ?></div>
        <p class="cert-desc">
          has successfully attended and participated in <strong><?php echo esc_html( $event_name ); ?></strong>,
          a community-driven WordPress conference dedicated to sharing knowledge,
          fostering collaboration, and celebrating the open web.
        </p>

        <!-- Event details -->
        <div class="cert-details">
          <div class="cert-detail-item">
            <span class="cert-detail-icon">📅</span>
            <?php echo esc_html( $event_date ); ?>
          </div>
          <?php if ( $event_location ) : ?>
          <div class="cert-detail-item">
            <span class="cert-detail-icon">📍</span>
            <?php echo esc_html( $event_location ); ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="cert-divider" style="margin-top:36px;"><div class="cert-divider-diamond"></div></div>

      <!-- Footer -->
      <div class="cert-footer">
        <div class="cert-sig">
          <div class="cert-sig-line"></div>
          <div class="cert-sig-label">Organizer</div>
          <div class="cert-sig-name"><?php echo esc_html( $organizer_name ); ?></div>
        </div>

        <div class="cert-seal">
          <div class="cert-seal-circle">
            <div class="cert-seal-inner">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M23 12l-2.44-2.78.34-3.68-3.61-.82-1.89-3.18L12 3 8.6 1.54 6.71 4.72l-3.61.81.34 3.68L1 12l2.44 2.78-.34 3.69 3.61.82 1.89 3.18L12 21l3.4 1.46 1.89-3.18 3.61-.82-.34-3.68L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
            </div>
          </div>
          <div class="cert-id">ID: <?php echo esc_html( strtoupper( substr( $cert->cert_token, 0, 8 ) ) ); ?></div>
        </div>

        <div class="cert-sig">
          <div class="cert-sig-line"></div>
          <div class="cert-sig-label">Date Issued</div>
          <div class="cert-sig-name"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $cert->issued_at ) ) ); ?></div>
        </div>
      </div>

    </div>
  </div>

  <!-- Actions -->
  <div class="cert-actions">
    <button class="cert-btn cert-btn-primary" onclick="window.print()">
      🖨️ Print / Save as PDF
    </button>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cert-btn cert-btn-secondary">
      ← Back to Site
    </a>
  </div>
</div>
</body>
</html>
