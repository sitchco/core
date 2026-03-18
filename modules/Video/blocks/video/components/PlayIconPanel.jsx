import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function PlayIconPanel({ provider, playIconStyle, playIconX, playIconY, clickBehavior, setAttributes }) {
    const playIconStyleOptions =
        provider === 'youtube'
            ? [
                  {
                      label: __('Dark', 'sitchco'),
                      value: 'dark',
                  },
                  {
                      label: __('Light', 'sitchco'),
                      value: 'light',
                  },
                  {
                      label: __('Red', 'sitchco'),
                      value: 'red',
                  },
              ]
            : [
                  {
                      label: __('Dark', 'sitchco'),
                      value: 'dark',
                  },
                  {
                      label: __('Light', 'sitchco'),
                      value: 'light',
                  },
              ];
    return (
        <PanelBody title={__('Play Icon', 'sitchco')} initialOpen={true}>
            <SelectControl
                label={__('Icon Style', 'sitchco')}
                value={playIconStyle}
                options={playIconStyleOptions}
                onChange={(value) => setAttributes({ playIconStyle: value })}
                __nextHasNoMarginBottom
            />
            <RangeControl
                label={__('Horizontal Position', 'sitchco')}
                value={playIconX}
                onChange={(value) => setAttributes({ playIconX: value })}
                min={0}
                max={100}
                step={1}
                help={__('Position as percentage (50% = centered)', 'sitchco')}
                __nextHasNoMarginBottom
            />
            <RangeControl
                label={__('Vertical Position', 'sitchco')}
                value={playIconY}
                onChange={(value) => setAttributes({ playIconY: value })}
                min={0}
                max={100}
                step={1}
                help={__('Position as percentage (50% = centered)', 'sitchco')}
                __nextHasNoMarginBottom
            />
            <SelectControl
                label={__('Click Behavior', 'sitchco')}
                value={clickBehavior}
                options={[
                    {
                        label: __('Entire poster', 'sitchco'),
                        value: 'poster',
                    },
                    {
                        label: __('Play icon only', 'sitchco'),
                        value: 'icon',
                    },
                ]}
                onChange={(value) => setAttributes({ clickBehavior: value })}
                help={__(
                    'Controls whether clicking anywhere on the poster or only the play icon starts the video',
                    'sitchco'
                )}
                __nextHasNoMarginBottom
            />
        </PanelBody>
    );
}
