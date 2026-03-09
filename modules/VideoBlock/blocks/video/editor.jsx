import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

function Edit({ attributes }) {
    const blockProps = useBlockProps();
    const { url } = attributes;
    return (
        <div {...blockProps}>
            <InspectorControls>{/* Inspector controls will be added in Plan 02 */}</InspectorControls>
            {!url && (
                <Placeholder
                    icon="video-alt3"
                    label={__('Video', 'sitchco')}
                    instructions={__('Enter a video URL in the block settings.', 'sitchco')}
                />
            )}
            {url && (
                <div className="sitchco-video-editor-preview">
                    {/* Full oEmbed preview will be added in Plan 02 */}
                    <p>{url}</p>
                </div>
            )}
            <InnerBlocks />
        </div>
    );
}

function Save() {
    const blockProps = useBlockProps.save();
    return (
        <div {...blockProps}>
            <InnerBlocks.Content />
        </div>
    );
}

registerBlockType(metadata.name, {
    edit: Edit,
    save: Save,
});
