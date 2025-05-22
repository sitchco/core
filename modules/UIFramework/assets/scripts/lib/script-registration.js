const scripts = new Map();

export function registerScript(name, url) {
    if (!scripts.has(name)) {
        scripts.set(name, {
            url,
            promise: null,
            requested: false
        });
    }
}

export function loadScript(name, url) {
    if (!scripts.has(name)) {
        if (url) {
            registerScript(name, url);
        } else {
            return Promise.reject(new Error(`Script "${name}" is not registered and no URL was provided.`));
        }
    }

    const scriptInfo = scripts.get(name);

    if (!scriptInfo.requested) {
        scriptInfo.requested = true;

        scriptInfo.promise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = scriptInfo.url;
            script.async = true;
            script.onload = () => resolve(name);
            script.onerror = () => reject(new Error(`Failed to load script: ${scriptInfo.url}`));
            document.head.appendChild(script);
        });
    }

    return scriptInfo.promise;
}
