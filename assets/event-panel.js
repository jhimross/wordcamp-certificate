/* WordCamp Certificate – "Event Details" for Events, reachable three ways:
     1. its own sidebar, opened from the award icon in the editor's top toolbar
     2. a panel in the sidebar's Event tab (opened automatically)
     3. the "Edit event details" button in the Certificate Design block sidebar
   It Edits the event meta through the editor store, so the
   certificate canvas (fields, logo) updates live while you type, and the
   values save together with the post. */
(function (plugins, editor, data, components, element, blockEditor, i18n) {
  var el = element.createElement;
  var __ = i18n.__;
  var useSelect = data.useSelect;
  var useDispatch = data.useDispatch;
  var PluginDocumentSettingPanel = (editor && editor.PluginDocumentSettingPanel) ||
    (window.wp.editPost && window.wp.editPost.PluginDocumentSettingPanel);
  var TextControl = components.TextControl;
  var Button = components.Button;
  var ExternalLink = components.ExternalLink;
  var MediaUpload = blockEditor.MediaUpload;
  var MediaUploadCheck = blockEditor.MediaUploadCheck;

  var PluginSidebar = (editor && editor.PluginSidebar) || (window.wp.editPost && window.wp.editPost.PluginSidebar);
  var PluginSidebarMoreMenuItem = (editor && editor.PluginSidebarMoreMenuItem) || (window.wp.editPost && window.wp.editPost.PluginSidebarMoreMenuItem);
  var PanelBody = components.PanelBody;

  var PLUGIN = 'wcc-event-details';
  var SIDEBAR = PLUGIN + '/wcc-event-sidebar';
  var DOC_PANEL = PLUGIN + '/wcc-event-details';

  /** Open the Event Details sidebar (used by the design block's button too). */
  function openEventSidebar() {
    var iface = data.dispatch('core/interface');
    if (data.dispatch('core/editor').openGeneralSidebar) {
      data.dispatch('core/editor').openGeneralSidebar(SIDEBAR);
    } else if (data.dispatch('core/edit-post') && data.dispatch('core/edit-post').openGeneralSidebar) {
      data.dispatch('core/edit-post').openGeneralSidebar(SIDEBAR);
    } else if (iface) {
      iface.enableComplementaryArea('core', SIDEBAR);
    }
  }
  window.wccOpenEventDetails = openEventSidebar;

  function EventDetailsFields() {
    var state = useSelect(function (select) {
      var ed = select('core/editor');
      var meta = ed.getEditedPostAttribute('meta') || {};
      var logoId = meta._wcc_event_logo_id || 0;
      return {
        meta: meta,
        status: ed.getEditedPostAttribute('status'),
        savedStatus: ed.getCurrentPostAttribute('status'),
        permalink: ed.getPermalink(),
        logo: logoId ? select('core').getMedia(logoId) : null,
        logoId: logoId,
      };
    }, []);
    var editPost = useDispatch('core/editor').editPost;

    function setMeta(key) {
      return function (value) {
        var patch = {};
        patch[key] = value;
        editPost({ meta: patch });
      };
    }

    var logoUrl = state.logo
      ? ((state.logo.media_details && state.logo.media_details.sizes && state.logo.media_details.sizes.medium &&
          state.logo.media_details.sizes.medium.source_url) || state.logo.source_url)
      : '';
    var published = state.savedStatus === 'publish';
    var previewUrl = state.permalink ? state.permalink + (state.permalink.indexOf('?') === -1 ? '?' : '&') + 'token=preview' : '';

    return el('div', { className: 'wcc-event-panel' },
      el('p', { className: 'wcc-event-panel__hint' },
        __('The event name is the post title. These details fill the certificate fields live.', 'wordcamp-certificate')),
      el(TextControl, {
        label: __('Event Date', 'wordcamp-certificate'),
        value: state.meta._wcc_event_date || '',
        placeholder: __('e.g. July 12–13, 2025', 'wordcamp-certificate'),
        onChange: setMeta('_wcc_event_date'),
        __nextHasNoMarginBottom: true,
      }),
      el(TextControl, {
        label: __('Event Location', 'wordcamp-certificate'),
        value: state.meta._wcc_event_location || '',
        placeholder: __('e.g. Manila, Philippines', 'wordcamp-certificate'),
        onChange: setMeta('_wcc_event_location'),
        __nextHasNoMarginBottom: true,
      }),
      el(TextControl, {
        label: __('Organizer Name', 'wordcamp-certificate'),
        value: state.meta._wcc_organizer_name || '',
        placeholder: (window.wccDesignData && window.wccDesignData.siteName) || '',
        help: __('Appears on the signature line.', 'wordcamp-certificate'),
        onChange: setMeta('_wcc_organizer_name'),
        __nextHasNoMarginBottom: true,
      }),

      el('div', { className: 'wcc-event-panel__logo' },
        el('span', { className: 'components-base-control__label wcc-event-panel__label' }, __('Event Logo', 'wordcamp-certificate')),
        logoUrl && el('img', { src: logoUrl, alt: '', className: 'wcc-event-panel__logo-img' }),
        el(MediaUploadCheck, {},
          el(MediaUpload, {
            allowedTypes: ['image'],
            value: state.logoId,
            onSelect: function (media) { setMeta('_wcc_event_logo_id')(media.id); },
            render: function (o) {
              return el('div', { className: 'wcc-event-panel__logo-buttons' },
                el(Button, { variant: 'secondary', onClick: o.open },
                  state.logoId ? __('Replace logo', 'wordcamp-certificate') : __('Select logo', 'wordcamp-certificate')),
                state.logoId ? el(Button, { variant: 'link', isDestructive: true, onClick: function () { setMeta('_wcc_event_logo_id')(0); } },
                  __('Remove', 'wordcamp-certificate')) : null
              );
            },
          })
        )
      ),

      el('div', { className: 'wcc-event-panel__link' },
        el('span', { className: 'components-base-control__label wcc-event-panel__label' }, __('Certificate Link', 'wordcamp-certificate')),
        published && state.permalink
          ? el(element.Fragment, {},
              el('input', { type: 'text', readOnly: true, value: state.permalink, className: 'components-text-control__input', onFocus: function (e) { e.target.select(); } }),
              el('p', {},
                el(ExternalLink, { href: state.permalink }, __('Open certificate page', 'wordcamp-certificate')),
                ' · ',
                el(ExternalLink, { href: previewUrl }, __('Preview certificate', 'wordcamp-certificate'))
              )
            )
          : el('p', { className: 'wcc-event-panel__hint' }, __('Publish this event to get its unique certificate link.', 'wordcamp-certificate'))
      )
    );
  }

  function EventDetailsPlugin() {
    // Make sure the Event-tab panel starts expanded so it's never hidden away.
    var docPanelOpen = useSelect(function (select) {
      var ed = select('core/editor');
      return ed.isEditorPanelOpened ? ed.isEditorPanelOpened(DOC_PANEL) : true;
    }, []);
    var toggle = useDispatch('core/editor').toggleEditorPanelOpened;
    element.useEffect(function () {
      if (!docPanelOpen && toggle) toggle(DOC_PANEL);
    }, []);

    var title = __('Event Details', 'wordcamp-certificate');
    return el(element.Fragment, {},
      PluginSidebarMoreMenuItem && el(PluginSidebarMoreMenuItem, { target: 'wcc-event-sidebar', icon: 'awards' }, title),
      PluginSidebar && el(PluginSidebar, { name: 'wcc-event-sidebar', title: title, icon: 'awards' },
        el(PanelBody, { initialOpen: true }, el(EventDetailsFields))
      ),
      PluginDocumentSettingPanel && el(PluginDocumentSettingPanel, { name: 'wcc-event-details', title: title, className: 'wcc-event-doc-panel' },
        el(EventDetailsFields)
      )
    );
  }

  plugins.registerPlugin(PLUGIN, { render: EventDetailsPlugin, icon: 'awards' });
}(
  window.wp.plugins,
  window.wp.editor,
  window.wp.data,
  window.wp.components,
  window.wp.element,
  window.wp.blockEditor,
  window.wp.i18n
));
