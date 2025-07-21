import domReady from '@wordpress/dom-ready';
import { unregisterBlockType } from '@wordpress/blocks';
import { select, subscribe } from '@wordpress/data';

((wp, config) => {
    const checkBlockVisibility = (blockName, postType, context) => {
        const rule = config[blockName];
        if (!rule) {
            return false;
        }

        const { allowPostType, allowContext } = rule;
        const postTypeMatch = allowPostType ? Boolean(allowPostType[postType]) : false;
        const contextMatch = allowContext ? Boolean(allowContext[context]) : false;
        return (allowPostType || allowContext) && !(postTypeMatch || contextMatch);
    };

    const initializeBlockVisibility = (editor, postType) => {
        const isSiteEditor = !!select('core/edit-site');
        const editorContext = isSiteEditor ? 'core/edit-site' : 'core/edit-post';

        wp.blocks.getBlockTypes().forEach(({ name }) => {
            if (checkBlockVisibility(name, postType, editorContext)) {
                unregisterBlockType(name);
            }
        });
    };

    domReady(() => {
        const unsubscribe = subscribe(() => {
            const editor = select('core/editor');
            if (!editor?.getCurrentPostType) {
                return;
            }

            const postType = editor.getCurrentPostType();
            if (!postType) {
                return;
            }

            unsubscribe();
            initializeBlockVisibility(editor, postType);
        });
    });
})(window.wp, window.sitchcoBlockVisibility || {});
