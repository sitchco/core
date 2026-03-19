import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { slugify } from '../lib/editor-utils.js';

export default function ModalSettingsPanel({ videoTitle, modalId, setAttributes }) {
    return (
        <PanelBody title={__('Modal Settings', 'sitchco')} initialOpen={true}>
            <TextControl
                label={__('Video Title', 'sitchco')}
                value={videoTitle}
                onChange={(value) => setAttributes({ videoTitle: value })}
                help={__('Used for accessibility and modal heading. Auto-populated from video metadata.', 'sitchco')}
                __nextHasNoMarginBottom
            />
            <TextControl
                label={__('Modal ID', 'sitchco')}
                value={modalId}
                onChange={(value) => setAttributes({ modalId: slugify(value) })}
                help={__('Unique identifier for deep linking. Auto-generated from title.', 'sitchco')}
                __nextHasNoMarginBottom
            />
        </PanelBody>
    );
}
