// SEO Engine panel for the block editor. Plain JS on purpose: no build step.
( function( wp, config ) {
  const { createElement: el, useEffect } = wp.element;
  const { useSelect, useDispatch } = wp.data;
  const { TextControl, TextareaControl } = wp.components;
  const { __, sprintf } = wp.i18n;
  const PluginDocumentSettingPanel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || wp.editPost.PluginDocumentSettingPanel;
  const PANEL = 'mwseo-editor-panel/mwseo-editor-panel';
  const OPENED_ONCE = 'mwseo-editor-panel-opened-once';
  const TITLE_KEY = '_kiss_seo_title';
  const EXCERPT_KEY = '_kiss_seo_excerpt';

  // Same rule as Quick Edit: count the custom value, or the default it falls back to.
  const counter = ( value, fallback, max ) => {
    const length = ( value || fallback || '' ).length;
    const text = sprintf( __( '%1$d / %2$d characters', 'seo-engine' ), length, max );
    return length > max ? el( 'span', { style: { color: '#cc1818' } }, text ) : text;
  };

  const SeoEnginePanel = () => {
    const { meta, panel } = useSelect( ( select ) => {
      const editor = select( 'core/editor' );
      return {
        meta: editor.getEditedPostAttribute( 'meta' ) || {},
        panel: editor.getCurrentPostAttribute( 'mwseo_panel' ) || {},
      };
    }, [] );
    const { editPost } = useDispatch( 'core/editor' );

    // Panels start collapsed and users miss it (that's how this panel came to be), so open it
    // the first time only. After that, WordPress remembers whatever the user chose.
    const store = wp.data.select( 'core/editor' ).isEditorPanelOpened ? 'core/editor' : 'core/edit-post';
    useEffect( () => {
      try {
        if ( window.localStorage.getItem( OPENED_ONCE ) ) {
          return;
        }
        window.localStorage.setItem( OPENED_ONCE, '1' );
      }
      catch ( e ) {
        return;
      }
      if ( !wp.data.select( store ).isEditorPanelOpened( PANEL ) ) {
        wp.data.dispatch( store ).toggleEditorPanelOpened( PANEL );
      }
    }, [] );
    const setMeta = ( key ) => ( value ) => editPost( { meta: { [ key ]: value } } );
    const title = meta[ TITLE_KEY ] || '';
    const excerpt = meta[ EXCERPT_KEY ] || '';

    return el( PluginDocumentSettingPanel, { name: 'mwseo-editor-panel', title: 'SEO Engine' },
      el( TextControl, {
        label: __( 'SEO Title', 'seo-engine' ),
        value: title,
        placeholder: panel.default_title || '',
        onChange: setMeta( TITLE_KEY ),
        help: counter( title, panel.default_title, config.titleMax ),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true,
      } ),
      el( 'div', { style: { height: 16 } } ),
      el( TextareaControl, {
        label: __( 'SEO Description', 'seo-engine' ),
        value: excerpt,
        placeholder: panel.default_excerpt || '',
        onChange: setMeta( EXCERPT_KEY ),
        help: counter( excerpt, panel.default_excerpt, config.excerptMax ),
        rows: 4,
        __nextHasNoMarginBottom: true,
      } ),
      el( 'p', { style: { marginTop: 16, marginBottom: 0 } },
        panel.no_index
          ? __( 'Hidden from search engines (No Index).', 'seo-engine' )
          : __( 'Visible to search engines.', 'seo-engine' )
      ),
      panel.canonical && el( 'p', { style: { marginTop: 8, marginBottom: 0, wordBreak: 'break-all' } },
        __( 'Canonical:', 'seo-engine' ) + ' ' + panel.canonical
      ),
      config.moreUrl && el( 'p', { style: { marginTop: 12, marginBottom: 0 } },
        el( 'a', { href: config.moreUrl }, __( 'More in SEO Engine →', 'seo-engine' ) )
      )
    );
  };

  wp.plugins.registerPlugin( 'mwseo-editor-panel', { render: SeoEnginePanel } );
} )( window.wp, window.mwseoEditorPanel || {} );
