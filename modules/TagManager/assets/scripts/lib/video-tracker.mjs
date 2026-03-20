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
            extra: (data) => ({ video_milestone: data.milestone }),
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
                pushEvent({
                    event,
                    video_id: data.id,
                    video_provider: data.provider,
                    video_url: data.url,
                    ...extra?.(data),
                });
            },
            20,
            'tag-manager'
        );
    }
}
