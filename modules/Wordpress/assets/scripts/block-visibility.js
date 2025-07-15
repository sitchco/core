import domReady from '@wordpress/dom-ready';
import { unregisterBlockType } from '@wordpress/blocks';
import { select, subscribe } from '@wordpress/data';

domReady(() => {
    const unsubscribe = subscribe(() => {
        const editor = select('core/editor');
        if (!editor || typeof editor.getCurrentPostType !== 'function') {
            return;
        }

        const postType = editor.getCurrentPostType();
        if (!postType) {
            return;
        }

        const isSiteEditor = !!select('core/edit-site');
        const context = isSiteEditor ? 'core/edit-site' : 'core/edit-post';
        console.log(context);

        const config = window.sitchcoBlockVisibility || {};
        unsubscribe();
        wp.blocks.getBlockTypes().forEach(onEachBlock);

        function onEachBlock({ name }) {
            const rule = config[name];
            if (!rule) {
                return;
            }

            const allowPT = rule.allowPostType;
            const allowCTX = rule.allowContext;

            const postMatch = allowPT ? Boolean(allowPT[postType]) : false;
            const contextMatch = allowCTX ? Boolean(allowCTX[context]) : false;
            if ((allowPT || allowCTX) && !(postMatch || contextMatch)) {
                console.log('unregistering', name);
                unregisterBlockType(name);
            }
        }
    });
});
