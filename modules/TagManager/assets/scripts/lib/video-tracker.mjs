export function registerVideoTracker(pushEvent) {
    const { hooks } = window.sitchco;

    const events = [
        {
            hook: 'video-play',
            event: 'video_play',
        },
        {
            hook: 'video-pause',
            event: 'video_pause',
        },
        {
            hook: 'video-progress',
            event: 'video_milestone',
            extra: (data) => ({ milestone: data.milestone }),
        },
        {
            hook: 'video-ended',
            event: 'video_ended',
        },
    ];

    for (const { hook, event, extra } of events) {
        hooks.addAction(
            hook,
            (data) => {
                pushEvent(
                    {
                        event,
                        video: {
                            id: data.id,
                            provider: data.provider,
                            url: data.url,
                            ...extra?.(data),
                        },
                    },
                    data.element
                );
            },
            20,
            'tag-manager'
        );
    }
}
