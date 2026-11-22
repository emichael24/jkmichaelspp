export function hostedVideo(url) {
    try {
        const parsed = new URL(url);
        if (!['http:', 'https:'].includes(parsed.protocol)) return null;
        const host = parsed.hostname.toLowerCase();
        const parts = parsed.pathname.split('/').filter(Boolean);
        if (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be'].includes(host)) {
            const id = host === 'youtu.be' ? parts[0] : ['embed', 'shorts', 'live'].includes(parts[0]) ? parts[1] : parsed.searchParams.get('v');
            return /^[\w-]{11}$/.test(id || '') ? { provider: 'youtube', id } : null;
        }
        if (['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'].includes(host)) {
            const id = parts[0] === 'video' ? parts[1] : parts[0];
            return /^\d+$/.test(id || '') ? { provider: 'vimeo', id } : null;
        }
    } catch { /* Direct media and incomplete links are handled by the caller. */ }
    return null;
}
