export const width = () => Math.max(document.documentElement.clientWidth, window.innerWidth || 0);

export const height = () => Math.max(document.documentElement.clientHeight, window.innerHeight || 0);

export const scrollPosition = () => {
    const scrollTop = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
    const windowHeight = height();
    return {
        top: scrollTop,
        bottom: scrollTop + windowHeight,
        height: windowHeight,
    };
};

export const isInViewport = (el) => {
    if (!el || !(el instanceof Element)) {
        return false;
    }

    const rect = el.getBoundingClientRect();
    return rect.bottom >= 0 && rect.right >= 0 && rect.top <= height() && rect.left <= width();
};

export const isVisible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
