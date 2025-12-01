(function (wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { ToggleControl, PanelRow } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { createElement, Fragment } = wp.element;
    const { __ } = wp.i18n;

    const RagSidebar = () => {
        const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {});
        const { editPost } = useDispatch('core/editor');

        // _ubc_rag_skip_indexing: true means skipped.
        // We want "Index this content" to be true when NOT skipped.
        const isIndexed = !meta._ubc_rag_skip_indexing;

        const toggleIndexing = (value) => {
            // If value is true (Index this), we set skip to false (or null/empty).
            // If value is false (Don't index), we set skip to true.
            editPost({ meta: { ...meta, _ubc_rag_skip_indexing: value ? false : true } });
        };

        const statusData = window.ubcRagData && window.ubcRagData.status;

        return createElement(
            Fragment,
            {},
            createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'ubc-rag-sidebar',
                    title: window.ubcRagData.labels.title,
                    icon: 'database',
                },
                createElement(
                    PanelRow,
                    {},
                    createElement(
                        ToggleControl,
                        {
                            label: window.ubcRagData.labels.indexThis,
                            checked: isIndexed,
                            onChange: toggleIndexing,
                            help: isIndexed ? '' : window.ubcRagData.labels.notIndexed
                        }
                    )
                ),
                statusData && createElement(
                    PanelRow,
                    {},
                    createElement('div', { className: 'ubc-rag-gutenberg-status' },
                        createElement('strong', {}, window.ubcRagData.labels.status + ' '),
                        createElement('span', { dangerouslySetInnerHTML: { __html: statusData.icon + ' ' + statusData.label } })
                    )
                )
            )
        );
    };

    registerPlugin('ubc-rag-sidebar', {
        render: RagSidebar,
        icon: 'database',
    });
})(window.wp);
