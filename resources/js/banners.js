// Procedural "texture art" banners shown for users who haven't uploaded their
// own cover image. Indexed deterministically by user id so the same person
// always gets the same artwork across pages.

export const BANNER_TEXTURES = [
  'radial-gradient(120% 80% at 10% 0%, rgba(255,255,255,.35) 0%, transparent 60%), radial-gradient(120% 80% at 90% 100%, #FFC8DC 0%, transparent 60%), linear-gradient(135deg, #6C63FF 0%, #5A52D5 60%, #FF6584 130%)',
  'radial-gradient(70% 90% at 80% 20%, rgba(255,255,255,.28) 0%, transparent 60%), conic-gradient(from 220deg at 30% 70%, #0F766E, #14B8A6, #67E8F9, #0F766E)',
  'radial-gradient(60% 70% at 20% 10%, #FDE68A 0%, transparent 55%), radial-gradient(70% 80% at 90% 90%, #FB7185 0%, transparent 55%), linear-gradient(135deg, #7C3AED 0%, #DB2777 80%)',
  'repeating-linear-gradient(45deg, rgba(255,255,255,.08) 0 14px, transparent 14px 28px), linear-gradient(135deg, #0EA5E9 0%, #1E1B4B 90%)',
  'radial-gradient(80% 60% at 50% 0%, #FCD34D 0%, transparent 60%), linear-gradient(180deg, #F97316 0%, #7C2D12 100%)',
  'conic-gradient(from 90deg at 50% 50%, #1E1B4B, #312E81, #4F46E5, #312E81, #1E1B4B)',
  'radial-gradient(80% 60% at 30% 80%, #34D399 0%, transparent 65%), radial-gradient(70% 70% at 80% 30%, #818CF8 0%, transparent 60%), linear-gradient(135deg, #0F172A 0%, #1F2937 100%)',
  'repeating-linear-gradient(135deg, rgba(255,255,255,.1) 0 22px, transparent 22px 44px), linear-gradient(135deg, #BE185D 0%, #7E22CE 100%)',
  'radial-gradient(60% 80% at 50% 50%, #FBBF24 0%, transparent 60%), linear-gradient(135deg, #0F172A 0%, #7C2D12 100%)',
  'radial-gradient(80% 60% at 50% 50%, rgba(255,255,255,.18) 0%, transparent 60%), conic-gradient(from 0deg, #1D4ED8, #7C3AED, #DB2777, #F97316, #1D4ED8)',
  'radial-gradient(70% 60% at 30% 30%, #6EE7B7 0%, transparent 55%), radial-gradient(70% 60% at 70% 80%, #93C5FD 0%, transparent 55%), linear-gradient(135deg, #1E293B 0%, #0F172A 100%)',
  'repeating-radial-gradient(circle at 25% 25%, rgba(255,255,255,.08) 0 8px, transparent 8px 32px), linear-gradient(135deg, #831843 0%, #3B0764 100%)',
];

export function textureForUser(userId) {
  const n = Number(userId) || 0;
  const i = ((n % BANNER_TEXTURES.length) + BANNER_TEXTURES.length) % BANNER_TEXTURES.length;
  return BANNER_TEXTURES[i];
}

// Paint a banner onto an element: use the uploaded image when present,
// otherwise the deterministic texture seeded by the user's id.
export function applyBannerTo(el, { id, banner_image } = {}) {
  if (!el) return;
  if (banner_image) {
    el.style.backgroundImage = `url('${banner_image}')`;
    el.style.backgroundSize = 'cover';
    el.style.backgroundPosition = 'center';
    el.style.backgroundRepeat = 'no-repeat';
  } else {
    el.style.backgroundImage = textureForUser(id);
  }
}
