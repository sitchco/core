import { isHttpLink } from './dom-utils.mjs';
import { getStoredUtmParams } from './utm-storage.mjs';

function matchesDomain(hostname, domain) {
    return hostname === domain || hostname.endsWith('.' + domain);
}

function isOutboundLink(link, domains) {
    if (!isHttpLink(link)) {
        return false;
    }
    if (link.hostname === window.location.hostname) {
        return false;
    }
    return domains.some((domain) => matchesDomain(link.hostname, domain));
}

function decorateLink(link, utmParams) {
    try {
        const url = new URL(link.href);

        for (const [key, value] of Object.entries(utmParams)) {
            url.searchParams.set(key, value);
        }

        link.href = url.toString();
    } catch {
        // Invalid URL
    }
}

function decorateMatchingLinks(root, domains, utmParams) {
    const links = root.querySelectorAll('a[href]');

    for (const link of links) {
        if (isOutboundLink(link, domains)) {
            decorateLink(link, utmParams);
        }
    }
}

export function registerOutboundDecorator() {
    const domains = Object.keys(window.sitchco?.tagManager?.outboundDomains || {});
    if (!domains.length) {
        return;
    }

    const utmParams = getStoredUtmParams();
    if (!Object.keys(utmParams).length) {
        return;
    }

    decorateMatchingLinks(document, domains, utmParams);

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    continue;
                }
                if (node.tagName === 'A' && node.href && isOutboundLink(node, domains)) {
                    decorateLink(node, utmParams);
                } else if (node.querySelectorAll) {
                    decorateMatchingLinks(node, domains, utmParams);
                }
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
}
