// Primary Category panel for the block editor (SEO Engine). Plain JS on purpose: no build step.
( function( wp ) {
  const { createElement: el } = wp.element;
  const { useSelect, useDispatch } = wp.data;
  const { SelectControl } = wp.components;
  const { __ } = wp.i18n;
  const PluginDocumentSettingPanel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || wp.editPost.PluginDocumentSettingPanel;
  const META_KEY = '_mwseo_primary_category';

  const PrimaryCategoryPanel = () => {
    const { categories, primary } = useSelect( ( select ) => {
      const editor = select( 'core/editor' );
      const ids = editor.getEditedPostAttribute( 'categories' ) || [];
      const meta = editor.getEditedPostAttribute( 'meta' ) || {};
      const terms = ids.length ? select( 'core' ).getEntityRecords( 'taxonomy', 'category', { include: ids, per_page: -1, context: 'view' } ) : [];
      return { categories: terms || [], primary: meta[ META_KEY ] || 0 };
    }, [] );
    const { editPost } = useDispatch( 'core/editor' );

    // Only meaningful when there is a choice to make.
    if ( categories.length < 2 ) {
      return null;
    }

    const options = [ { value: 0, label: __( 'Automatic', 'seo-engine' ) } ]
      .concat( categories.map( ( term ) => ( { value: term.id, label: term.name } ) ) );

    return el( PluginDocumentSettingPanel, { name: 'mwseo-primary-category', title: __( 'Primary Category', 'seo-engine' ) },
      el( SelectControl, {
        value: categories.some( ( term ) => term.id === primary ) ? primary : 0,
        options,
        onChange: ( value ) => editPost( { meta: { [ META_KEY ]: parseInt( value, 10 ) || 0 } } ),
        help: __( 'Used for breadcrumbs. Automatic uses the one set in Yoast or Rank Math, or the first category.', 'seo-engine' ),
        __nextHasNoMarginBottom: true,
      } )
    );
  };

  wp.plugins.registerPlugin( 'mwseo-primary-category', { render: PrimaryCategoryPanel } );
} )( window.wp );
