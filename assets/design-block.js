/* WordCamp Certificate – "Certificate Design" block.
   A real drag-and-drop Gutenberg canvas: the certificate wording and layout
   are ordinary blocks (heading, paragraph, group, columns, image, separator,
   Certificate Field tokens) that can be reordered, edited inline, deleted,
   and have new blocks inserted among them. The sidebar holds the look:
   starter layouts, frame & paper, colors, background, fonts, logo, and the
   list of {placeholders} usable in any text.

   NOTE: save() output must stay exactly as it is, or existing designs would
   fail block validation. All new options are attributes only (stored in the
   block comment) and are applied by the server when the certificate renders. */
(function (blocks, element, blockEditor, components, i18n, data) {
  var el = element.createElement;
  var Fragment = element.Fragment;
  var useState = element.useState;
  var useBlockProps = blockEditor.useBlockProps;
  var InnerBlocks = blockEditor.InnerBlocks;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelColorSettings = blockEditor.PanelColorSettings;
  var MediaUpload = blockEditor.MediaUpload;
  var MediaUploadCheck = blockEditor.MediaUploadCheck;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var ToggleControl = components.ToggleControl;
  var Button = components.Button;
  var useSelect = data.useSelect;
  var useDispatch = data.useDispatch;
  var __ = i18n.__;

  function cfg() { return window.wccDesignData || {}; }

  var FALLBACK_FONTS = [
    { label: 'Playfair Display', value: 'playfair', family: "'Playfair Display', serif" },
    { label: 'Merriweather', value: 'merriweather', family: "'Merriweather', serif" },
    { label: 'Georgia (system)', value: 'georgia', family: 'Georgia, serif' },
    { label: 'Inter', value: 'inter', family: "'Inter', sans-serif" },
    { label: 'Roboto', value: 'roboto', family: "'Roboto', sans-serif" },
    { label: 'Arial (system)', value: 'arial', family: 'Arial, sans-serif' },
  ];
  function fonts() { return cfg().fonts || FALLBACK_FONTS; }
  function fontFamily(key) {
    var f = fonts().filter(function (x) { return x.value === key; })[0];
    return f ? f.family : undefined;
  }

  var FRAME_OPTIONS = [
    { label: __('Classic — gold corner ornaments', 'wordcamp-certificate'), value: 'classic' },
    { label: __('Double border', 'wordcamp-certificate'), value: 'double' },
    { label: __('Simple line', 'wordcamp-certificate'), value: 'simple' },
    { label: __('No frame', 'wordcamp-certificate'), value: 'none' },
  ];
  var ORIENTATION_OPTIONS = [
    { label: __('Landscape (A4 / Letter)', 'wordcamp-certificate'), value: 'landscape' },
    { label: __('Portrait (A4 / Letter)', 'wordcamp-certificate'), value: 'portrait' },
  ];

  // ── Starter layouts ────────────────────────────────────────────────────
  // Every item is a normal, movable/removable/insertable block.
  var CLASSIC = [
    ['wordcamp-certificate/field', { field: 'event_name' }],
    ['core/separator', { className: 'cert-divider-block' }],
    ['core/paragraph', { content: __('This is to certify that', 'wordcamp-certificate'), className: 'cert-subtitle' }],
    ['core/heading', { level: 1, content: __('Certificate of Attendance', 'wordcamp-certificate'), className: 'cert-title' }],
    ['core/paragraph', { content: __('proudly presented to', 'wordcamp-certificate'), className: 'cert-presented' }],
    ['wordcamp-certificate/field', { field: 'full_name' }],
    ['core/paragraph', { content: __('has successfully attended and participated in {event_name}, a community-driven WordPress conference dedicated to sharing knowledge, fostering collaboration, and celebrating the open web.', 'wordcamp-certificate'), className: 'cert-desc' }],
    ['core/group', { className: 'cert-details', layout: { type: 'flex', flexWrap: 'wrap' } }, [
      ['wordcamp-certificate/field', { field: 'event_date' }],
      ['wordcamp-certificate/field', { field: 'event_location' }],
    ]],
    ['core/separator', { className: 'cert-divider-block' }],
    ['core/group', { className: 'cert-footer', layout: { type: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' } }, [
      ['core/group', { className: 'cert-sig', layout: { type: 'flex', orientation: 'vertical' } }, [
        ['core/paragraph', { content: __('Organizer', 'wordcamp-certificate'), className: 'cert-sig-label' }],
        ['wordcamp-certificate/field', { field: 'organizer_name' }],
      ]],
      ['core/group', { className: 'cert-seal', layout: { type: 'flex', orientation: 'vertical' } }, [
        ['core/paragraph', { content: __('Certificate ID', 'wordcamp-certificate'), className: 'cert-sig-label' }],
        ['wordcamp-certificate/field', { field: 'cert_id' }],
      ]],
      ['core/group', { className: 'cert-sig', layout: { type: 'flex', orientation: 'vertical' } }, [
        ['core/paragraph', { content: __('Date Issued', 'wordcamp-certificate'), className: 'cert-sig-label' }],
        ['wordcamp-certificate/field', { field: 'date_issued' }],
      ]],
    ]],
  ];

  var MODERN = [
    ['core/paragraph', { content: __('Certificate', 'wordcamp-certificate'), className: 'cert-subtitle' }],
    ['core/heading', { level: 1, content: __('of Attendance', 'wordcamp-certificate'), className: 'cert-title' }],
    ['core/separator', { className: 'cert-divider-block' }],
    ['core/paragraph', { content: __('This certificate is proudly presented to', 'wordcamp-certificate'), className: 'cert-presented' }],
    ['wordcamp-certificate/field', { field: 'full_name', fontFamily: 'greatvibes', fontSize: 64, fontWeight: '400' }],
    ['core/paragraph', { content: __('for attending <strong>{event_name}</strong> on {event_date} in {event_location}.', 'wordcamp-certificate'), className: 'cert-desc' }],
    ['core/group', { className: 'cert-footer', layout: { type: 'flex', justifyContent: 'space-between', flexWrap: 'wrap' } }, [
      ['core/group', { className: 'cert-sig cert-sig--line', layout: { type: 'flex', orientation: 'vertical' } }, [
        ['wordcamp-certificate/field', { field: 'organizer_name' }],
        ['core/paragraph', { content: __('Organizer', 'wordcamp-certificate'), className: 'cert-sig-label' }],
      ]],
      ['core/group', { className: 'cert-sig cert-sig--line', layout: { type: 'flex', orientation: 'vertical' } }, [
        ['wordcamp-certificate/field', { field: 'date_issued' }],
        ['core/paragraph', { content: __('Date Issued', 'wordcamp-certificate'), className: 'cert-sig-label' }],
      ]],
    ]],
    ['core/paragraph', { content: __('Certificate ID: {cert_id}', 'wordcamp-certificate'), className: 'cert-sig-label' }],
  ];

  var MINIMAL = [
    ['wordcamp-certificate/field', { field: 'event_name' }],
    ['core/spacer', { height: '32px' }],
    ['core/paragraph', { content: __('Certificate of Attendance', 'wordcamp-certificate'), className: 'cert-subtitle' }],
    ['wordcamp-certificate/field', { field: 'full_name' }],
    ['core/paragraph', { content: __('attended {event_name} · {event_date} · {event_location}', 'wordcamp-certificate'), className: 'cert-desc' }],
    ['core/spacer', { height: '32px' }],
    ['core/paragraph', { content: __('{organizer_name} — Certificate ID {cert_id}', 'wordcamp-certificate'), className: 'cert-sig-label' }],
  ];

  var PRESETS = [
    { key: 'classic', label: __('Classic', 'wordcamp-certificate'), template: CLASSIC, attrs: { frameStyle: 'classic' } },
    { key: 'modern', label: __('Modern', 'wordcamp-certificate'), template: MODERN, attrs: { frameStyle: 'double' } },
    { key: 'minimal', label: __('Minimal', 'wordcamp-certificate'), template: MINIMAL, attrs: { frameStyle: 'simple' } },
  ];

  var ALLOWED_BLOCKS = [
    'core/heading', 'core/paragraph', 'core/group', 'core/columns', 'core/column',
    'core/separator', 'core/spacer', 'core/buttons', 'core/button', 'core/image',
    'wordcamp-certificate/field',
  ];


  function mediaUrl(media) {
    if (!media) return '';
    var s = media.media_details && media.media_details.sizes;
    return (s && s.medium && s.medium.source_url) || media.source_url || '';
  }

  // Small click-to-copy chip for the placeholders panel.
  function PlaceholderChip(props) {
    var copied = useState(false);
    return el(Button, {
      variant: 'secondary',
      size: 'small',
      className: 'wcc-placeholder-chip',
      label: props.label,
      showTooltip: true,
      onClick: function () {
        if (navigator.clipboard) navigator.clipboard.writeText(props.token);
        copied[1](true);
        setTimeout(function () { copied[1](false); }, 1200);
      },
    }, copied[0] ? __('Copied!', 'wordcamp-certificate') : props.token);
  }

  blocks.registerBlockType('wordcamp-certificate/design', {
    title: __('Certificate Design', 'wordcamp-certificate'),
    icon: 'art',
    category: 'wordcamp-certificate',
    description: __('Drag, drop, and edit the certificate layout directly. Layouts, frame, colors, background and fonts are in the sidebar. On an Event, saving updates that event\'s certificate.', 'wordcamp-certificate'),
    supports: { html: false, multiple: false },

    edit: function (props) {
      var attrs = props.attributes;
      var setAttributes = props.setAttributes;
      var clientId = props.clientId;

      var ctx = useSelect(function (select) {
        var ed = select('core/editor');
        var meta = (ed && ed.getEditedPostAttribute('meta')) || {};
        var logoId = meta._wcc_event_logo_id || 0;
        return {
          logo: logoId ? select('core').getMedia(logoId) : null,
          permalink: ed ? ed.getPermalink() : '',
          published: ed ? ed.getCurrentPostAttribute('status') === 'publish' : false,
          hasInner: select('core/block-editor').getBlockOrder(clientId).length > 0,
        };
      }, [clientId]);
      var replaceInnerBlocks = useDispatch('core/block-editor').replaceInnerBlocks;

      function setAttr(key) {
        return function (value) {
          var patch = {};
          patch[key] = value === undefined ? '' : value;
          setAttributes(patch);
        };
      }

      function applyPreset(preset) {
        if (ctx.hasInner && !window.confirm(__('Replace the current certificate content with the "%s" layout? Colors and fonts are kept.', 'wordcamp-certificate').replace('%s', preset.label))) {
          return;
        }
        replaceInnerBlocks(clientId, blocks.createBlocksFromInnerBlocksTemplate(preset.template), false);
        setAttributes(preset.attrs);
      }

      var canvasStyle = {
        '--wc-blue': attrs.primaryColor,
        '--wc-dark': attrs.darkColor,
        '--wc-gold': attrs.goldColor,
        '--wc-light': attrs.lightColor,
        '--wc-border': attrs.borderColor,
        '--wc-heading-font': fontFamily(attrs.headingFont),
        '--wc-body-font': fontFamily(attrs.bodyFont),
      };
      var certStyle = { '--wc-bg': attrs.bgColor || '#ffffff' };
      if (attrs.bgImageUrl) certStyle['--wc-bg-image'] = 'url("' + attrs.bgImageUrl + '")';

      var orientation = attrs.orientation === 'portrait' ? 'portrait' : 'landscape';
      var blockProps = useBlockProps({ className: 'wcc-cert-canvas', style: canvasStyle });
      var logoUrl = mediaUrl(ctx.logo);
      var previewUrl = ctx.permalink ? ctx.permalink + (ctx.permalink.indexOf('?') === -1 ? '?' : '&') + 'token=preview' : '';
      var fontOptions = fonts().map(function (f) { return { label: f.label, value: f.value }; });
      var placeholders = cfg().placeholders || {};

      return el('div', blockProps,
        el(InspectorControls, {},
          cfg().isEvent && el('div', { className: 'wcc-event-shortcut' },
            el(Button, { variant: 'secondary', icon: 'awards', onClick: function () { window.wccOpenEventDetails && window.wccOpenEventDetails(); } },
              __('Edit event details (date, location, organizer, logo)', 'wordcamp-certificate'))
          ),
          el(PanelBody, { title: __('Layout', 'wordcamp-certificate'), initialOpen: !ctx.hasInner },
            el('p', { className: 'wcc-panel-help' }, __('Start from a ready-made layout, then customize anything.', 'wordcamp-certificate')),
            el('div', { className: 'wcc-preset-grid' },
              PRESETS.map(function (p) {
                return el(Button, {
                  key: p.key,
                  className: 'wcc-preset wcc-preset--' + p.key,
                  onClick: function () { applyPreset(p); },
                },
                  el('span', { className: 'wcc-preset__thumb', 'aria-hidden': true },
                    el('i'), el('b'), el('i')
                  ),
                  el('span', { className: 'wcc-preset__label' }, p.label)
                );
              })
            )
          ),
          el(PanelBody, { title: __('Frame & Paper', 'wordcamp-certificate'), initialOpen: false },
            el(SelectControl, {
              label: __('Frame style', 'wordcamp-certificate'),
              value: attrs.frameStyle,
              options: FRAME_OPTIONS,
              onChange: setAttr('frameStyle'),
              __nextHasNoMarginBottom: true,
            }),
            el(SelectControl, {
              label: __('Paper orientation', 'wordcamp-certificate'),
              value: orientation,
              options: ORIENTATION_OPTIONS,
              onChange: setAttr('orientation'),
              help: __('Used for the on-screen proportions and when attendees print or save as PDF.', 'wordcamp-certificate'),
              __nextHasNoMarginBottom: true,
            })
          ),
          el(PanelColorSettings, {
            title: __('Colors', 'wordcamp-certificate'),
            initialOpen: false,
            colorSettings: [
              { value: attrs.primaryColor, onChange: setAttr('primaryColor'), label: __('Primary (frame, accents, name)', 'wordcamp-certificate') },
              { value: attrs.darkColor, onChange: setAttr('darkColor'), label: __('Dark (headings, text)', 'wordcamp-certificate') },
              { value: attrs.goldColor, onChange: setAttr('goldColor'), label: __('Gold (ornaments, dividers)', 'wordcamp-certificate') },
              { value: attrs.lightColor, onChange: setAttr('lightColor'), label: __('Light (detail pill background)', 'wordcamp-certificate') },
              { value: attrs.borderColor, onChange: setAttr('borderColor'), label: __('Inner border', 'wordcamp-certificate') },
              { value: attrs.bgColor, onChange: setAttr('bgColor'), label: __('Paper background', 'wordcamp-certificate') },
            ],
          }),
          el(PanelBody, { title: __('Background Image', 'wordcamp-certificate'), initialOpen: false },
            el('p', { className: 'wcc-panel-help' }, __('Optional texture, watermark, or full certificate artwork. It covers the whole paper area.', 'wordcamp-certificate')),
            attrs.bgImageUrl && el('img', { src: attrs.bgImageUrl, alt: '', className: 'wcc-bg-thumb' }),
            el(MediaUploadCheck, {},
              el(MediaUpload, {
                allowedTypes: ['image'],
                value: attrs.bgImageId,
                onSelect: function (media) { setAttributes({ bgImageId: media.id, bgImageUrl: media.url }); },
                render: function (o) {
                  return el('div', { className: 'wcc-button-row' },
                    el(Button, { variant: 'secondary', onClick: o.open }, attrs.bgImageUrl ? __('Replace image', 'wordcamp-certificate') : __('Select image', 'wordcamp-certificate')),
                    attrs.bgImageUrl ? el(Button, { variant: 'link', isDestructive: true, onClick: function () { setAttributes({ bgImageId: 0, bgImageUrl: '' }); } }, __('Remove', 'wordcamp-certificate')) : null
                  );
                },
              })
            )
          ),
          el(PanelBody, { title: __('Typography', 'wordcamp-certificate'), initialOpen: false },
            el(SelectControl, {
              label: __('Heading font', 'wordcamp-certificate'),
              value: attrs.headingFont,
              options: fontOptions,
              onChange: setAttr('headingFont'),
              help: __('Event name, title, attendee name, signatures.', 'wordcamp-certificate'),
              __nextHasNoMarginBottom: true,
            }),
            el(SelectControl, {
              label: __('Body font', 'wordcamp-certificate'),
              value: attrs.bodyFont,
              options: fontOptions,
              onChange: setAttr('bodyFont'),
              __nextHasNoMarginBottom: true,
            }),
            el('p', { className: 'wcc-panel-help' }, __('Tip: select a Certificate Field (e.g. the attendee name) to give it its own font, size and color.', 'wordcamp-certificate'))
          ),
          el(PanelBody, { title: __('Logo', 'wordcamp-certificate'), initialOpen: false },
            el(ToggleControl, {
              label: __('Show event logo', 'wordcamp-certificate'),
              checked: !!attrs.showLogo,
              onChange: setAttr('showLogo'),
              __nextHasNoMarginBottom: true,
            }),
            cfg().isEvent
              ? el(Button, { variant: 'secondary', icon: 'awards', className: 'wcc-preview-btn', onClick: function () { window.wccOpenEventDetails && window.wccOpenEventDetails(); } },
                  __('Edit event details & logo', 'wordcamp-certificate'))
              : el('p', { className: 'wcc-panel-help' }, __('Without an event logo, a WordPress badge is shown.', 'wordcamp-certificate'))
          ),
          el(PanelBody, { title: __('Placeholders', 'wordcamp-certificate'), initialOpen: false },
            el('p', { className: 'wcc-panel-help' }, __('Type these into any heading or paragraph; they are replaced with real values on each certificate. Click to copy.', 'wordcamp-certificate')),
            el('div', { className: 'wcc-placeholder-list' },
              Object.keys(placeholders).map(function (token) {
                return el(PlaceholderChip, { key: token, token: token, label: placeholders[token] });
              })
            )
          ),
          cfg().isEvent && el(PanelBody, { title: __('Preview', 'wordcamp-certificate'), initialOpen: true },
            ctx.published
              ? el(Fragment, {},
                  el(Button, { variant: 'primary', href: previewUrl, target: '_blank', rel: 'noopener', className: 'wcc-preview-btn' }, __('Preview certificate', 'wordcamp-certificate')),
                  el('p', { className: 'wcc-panel-help' }, __('Opens the real certificate page with sample data. Save first to see your latest changes.', 'wordcamp-certificate'))
                )
              : el('p', { className: 'wcc-panel-help' }, __('Publish the event to preview the printable certificate.', 'wordcamp-certificate'))
          )
        ),
        el('div', { className: 'wcc-design-block-notice' },
          __('Certificate Design — drag blocks to reorder, click to edit text, or use + to add elements. Pick a starter layout, frame and colors in the sidebar.', 'wordcamp-certificate')
        ),
        el('div', { className: 'cert-wrap cert-wrap--' + orientation },
          el('div', { className: 'cert cert--frame-' + (attrs.frameStyle || 'classic') + ' cert--' + orientation, style: certStyle },
            el('div', { className: 'cert-inner' },
              attrs.showLogo && el('div', { className: 'cert-header' },
                el('div', { className: 'wc-logo' },
                  logoUrl
                    ? el('img', { src: logoUrl, alt: '' })
                    : el('img', { src: cfg().defaultLogo, alt: 'WordPress', className: 'wc-logo-default' })
                )
              ),
              el('div', { className: 'wcc-cert-body' },
                el(InnerBlocks, {
                  template: CLASSIC,
                  allowedBlocks: ALLOWED_BLOCKS,
                  templateLock: false,
                })
              )
            )
          )
        )
      );
    },

    save: function () {
      // InnerBlocks content is saved via save.Content below. Keep unchanged.
      var blockProps = blockEditor.useBlockProps.save({ className: 'wcc-cert-canvas' });
      return el('div', blockProps,
        el('div', { className: 'cert-wrap' },
          el('div', { className: 'cert' },
            el('div', { className: 'cert-inner' },
              el('div', { className: 'wcc-cert-body' },
                el(InnerBlocks.Content, null)
              )
            )
          )
        )
      );
    },
  });
}(
  window.wp.blocks,
  window.wp.element,
  window.wp.blockEditor,
  window.wp.components,
  window.wp.i18n,
  window.wp.data
));
