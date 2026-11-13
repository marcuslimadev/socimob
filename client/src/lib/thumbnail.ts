const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';

export type ThumbWidth = 160 | 320 | 640;

/**
 * URL da miniatura de uma foto de imóvel enviada pelo CRM (gerada e cacheada no servidor).
 * Fotos externas (portais) e data/blob URLs voltam sem alteração.
 */
export function thumbnailUrl(src: string, width: ThumbWidth = 320): string {
  if (!src || !/\/uploads\/properties\//.test(src)) {
    return src;
  }

  return `${API_BASE_URL.replace(/\/$/, '')}/img-thumb?w=${width}&u=${encodeURIComponent(src)}`;
}
