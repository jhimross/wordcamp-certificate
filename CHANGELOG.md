# Changelog

All notable changes to **WordCamp Certificate Generator** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Planned
- Email the certificate link to the attendee after generation
- CSV export of all issued certificates from the admin panel
- QR code on the certificate linking back to the verification URL
- WooCommerce integration — gate certificate generation behind a ticket purchase
- `wcc_before_certificate_insert` and `wcc_after_certificate_insert` action hooks
- `wcc_certificate_data` and `wcc_form_fields` filter hooks

---

## [1.4.1] - 2026-11-19

### Fixed
- Event Details were hard to find in the editor. They now open from an award icon in the top toolbar (own sidebar), the Event-tab panel starts expanded, and the Certificate Design block sidebar has an "Edit event details" button

## [1.4.0] - 2026-11-19

### Added — Certificate builder
- **Starter layouts** (Classic, Modern, Minimal) in the Certificate Design block sidebar; one click swaps the content, colors and fonts are kept
- **Frame & Paper** options: frame style (classic ornaments, double border, simple line, none) and paper orientation (landscape / portrait)
- **Paper background color** and optional **background image** (texture, watermark, or full artwork)
- **Inline placeholders** usable in any heading or paragraph: `{full_name}`, `{event_name}`, `{event_date}`, `{event_location}`, `{organizer_name}`, `{cert_id}`, `{date_issued}`, with a click-to-copy list in the sidebar
- **Event Details panel** in the editor sidebar (date, location, organizer, logo, certificate link) replacing the old meta box
- **Preview certificate** button in the design block sidebar
- New fonts: Cormorant Garamond, Lora, Montserrat, Great Vibes (script)

### Changed
- Certificate fields in the editor now show the event's real name, date, location and organizer, updating live as you type
- Font choices and the event logo are now visible in the editor, not only on the issued certificate
- Printing / Save as PDF fills exactly one A4/Letter sheet in the chosen orientation, and prints background colors

### Fixed
- Default WordPress logo badge was invisible (white on white); now uses the official WordPress logo
- Stale "logo is set on the Settings page" hint in the design block

## [1.3.0] - 2026-11-19

### Added
- **Events**: each WordCamp is now its own Event (WC Certificates → Events) with its own date, location, organizer, logo, certificate design, attendee list, and issued certificates
- **Unique certificate link per event** at `/certificate/{event-slug}/`; attendees claim their certificate there, and issued certificates live at `/certificate/{event-slug}/?token=…`
- **Archive / Restore** events: archived events leave the active list but every certificate link keeps working
- **Duplicate as New Event**: start the next event with the previous event's certificate design, organizer, location, and logo
- Admin-only certificate preview at `/certificate/{event-slug}/?token=preview`
- Event filter on the Certificates Issued and Attendees screens
- `event` attribute for the `[wordcamp_certificate]` shortcode and an Event picker in the Certificate Form block

### Changed
- Event details moved from the Settings page to the Event edit screen (Settings page removed)
- Attendee CSV imports now replace only the selected event's list
- Plugin author is now Jhimross Olinares

### Migration
- On update, an existing single-event setup is converted into a first Event automatically. Existing certificates and attendees are assigned to it, and old `/?wcc_cert=1&token=…` links still work

---

## [1.0.0] — 2025-07-03

### Added
- Initial release of WordCamp Certificate Generator
- Attendee-facing form with **Full Name** and **Email Address** fields
- AJAX-powered form submission with nonce verification and inline field validation
- Auto-generation of a unique, token-based certificate URL per attendee
- Duplicate prevention — the same email always returns the same certificate
- Standalone certificate page rendered outside the WordPress theme loop
- Beautiful certificate design using Playfair Display + Inter (Google Fonts)
  - Gold corner ornaments and inner border
  - Event name, date, and location details pill
  - Organizer signature line and verification seal
  - Unique short certificate ID (first 8 characters of the token)
- **Print / Save as PDF** button with clean print stylesheet
- **Back to Site** navigation button on the certificate page
- WordPress admin menu: **WC Certificates**
  - Certificates log — lists all issued certificates with a direct view link
  - Settings page — configure event name, date, location, and organizer name
- `[wordcamp_certificate]` shortcode with optional `event_name` and `event_date` attribute overrides
- Gutenberg block: **WordCamp Certificate Form**
  - Editor-side preview with Inspector Controls for event name and date
  - Server-side render callback
- `register_activation_hook` creates the `wp_wordcamp_certificates` database table via `dbDelta()`
- Full input sanitization: `sanitize_text_field`, `sanitize_email`, `$wpdb->prepare()`
- Tokens generated with `wp_generate_password( 32, false )`
- Mobile-responsive form and certificate layouts
- `prefers-reduced-motion` media query support

---

[Unreleased]: https://github.com/YOUR_USERNAME/wordcamp-certificate/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/YOUR_USERNAME/wordcamp-certificate/releases/tag/v1.0.0
