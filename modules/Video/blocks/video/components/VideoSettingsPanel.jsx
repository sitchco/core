import { PanelBody, TextControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function VideoSettingsPanel({ url, displayMode, onUrlChange, setAttributes, error, hasInnerBlocks }) {
    return (
        <PanelBody title={__('Video Settings', 'sitchco')} initialOpen={true}>
            <TextControl
                label={__('Video URL', 'sitchco')}
                value={url}
                onChange={onUrlChange}
                placeholder="https://youtube.com/watch?v=..."
                __nextHasNoMarginBottom
            />
            {url && error && hasInnerBlocks && (
                <Notice status="warning" isDismissible={false} className="sitchco-video__embed-warning">
                    {error}
                </Notice>
            )}
            <SelectControl
                label={__('Display Mode', 'sitchco')}
                value={displayMode}
                options={[
                    {
                        label: __('Inline', 'sitchco'),
                        value: 'inline',
                    },
                    {
                        label: __('Modal', 'sitchco'),
                        value: 'modal',
                    },
                    {
                        label: __('Modal Only', 'sitchco'),
                        value: 'modal-only',
                    },
                ]}
                onChange={(value) => setAttributes({ displayMode: value })}
                __nextHasNoMarginBottom
            />
        </PanelBody>
    );
}
