export function useMedia() {
    function mediaUrl(path) {
        if (!path) return null;
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) return path;
        return `/storage/${path}`;
    }
    return { mediaUrl };
}
