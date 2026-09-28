export const limits = { width: [72, 400], height: [54, 300] };
export const units = { cm: 72 / 2.54, mm: 72 / 25.4, px: 72 / 96 };
export const validSize = (width, height) => Number.isFinite(width) && Number.isFinite(height)
    && width >= limits.width[0] && width <= limits.width[1]
    && height >= limits.height[0] && height <= limits.height[1];
export const sizeLabel = (width, height) => `${Number((width / units.cm).toFixed(2))} × ${Number((height / units.cm).toFixed(2))} cm`;
export const presetKey = (userId) => `signwork:qr-presets:v1:${userId}`;
export function readPresets(storage, userId) {
    try {
        const values = JSON.parse(storage.getItem(presetKey(userId)) || '[]');
        return Array.isArray(values) ? values.filter(p => p && typeof p.name === 'string'
            && p.name.trim().length > 0 && p.name.length <= 40 && validSize(p.width, p.height)).slice(0, 20) : [];
    } catch { return []; }
}
export function writePresets(storage, userId, presets) {
    try { storage.setItem(presetKey(userId), JSON.stringify(presets)); return true; }
    catch { return false; }
}
