(function (wp, settings) {
    const Panel = wp.editor.PluginDocumentSettingPanel || wp.editPost.PluginDocumentSettingPanel;
    const h = wp.element.createElement;
    const __ = wp.i18n.__;
    if (!Panel || !settings) return;
    const choices = [
        {value: 'auto', label: __('Follow category and default rules', 'wp-discord-post')},
        {value: 'off', label: __('Do not notify Discord', 'wp-discord-post')},
        ...settings.channels
    ];

    function RelayPanel() {
        const state = wp.data.useSelect(function (select) {
            const editor = select('core/editor');
            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                title: editor.getEditedPostAttribute('title'),
                excerpt: editor.getEditedPostAttribute('excerpt')
            };
        }, []);
        const {editPost} = wp.data.useDispatch('core/editor');
        const value = state.meta[settings.meta] || 'auto';
        const selected = choices.find(function (channel) { return channel.value === value; });
        const route = value === 'auto' ? settings.route : selected || {};
        const options = selected ? choices : [...choices, {value, label: __('Unavailable channel', 'wp-discord-post')}];
        const card = settings.card.embed;
        return h(Panel, {name: 'blackfury-relay', title: 'BlackFury Relay'},
            h(wp.components.SelectControl, {
                label: __('Destination', 'wp-discord-post'),
                value,
                options,
                onChange: function (next) { editPost({meta: {[settings.meta]: next}}); }
            }),
            value !== 'off' && h('div', {className: 'bfr-preview'},
                h('span', {className: 'bfr-eyebrow'}, route.label || __('Unavailable channel', 'wp-discord-post')),
                h('p', null, settings.card.content),
                card && h('div', {className: 'bfr-embed', style: {'--bfr-accent': route.color || '#5865f2'}},
                    h('strong', null, typeof state.title === 'string' ? state.title || card.title : card.title),
                    h('p', null, typeof state.excerpt === 'string' ? state.excerpt || card.description : card.description),
                    card.image && h('img', {src: card.image, alt: ''})
                )
            ),
            h('p', {className: 'description'}, __('Save your draft and reload to refresh the message, image and category routing.', 'wp-discord-post'))
        );
    }
    wp.plugins.registerPlugin('blackfury-relay', {render: RelayPanel});
})(window.wp, window.blackfuryRelayEditor);
