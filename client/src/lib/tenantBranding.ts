export interface TenantBranding {
  name?: string;
  slogan?: string;
  logo?: string;
  logo_url?: string;
  favicon_url?: string;
  mascot_url?: string;
  primary_color?: string;
  secondary_color?: string;
  font_primary?: string;
  font_secondary?: string;
  font_url?: string;
  contact_phone?: string;
  contact_email?: string;
  domain?: string;
  portal_finalidades?: string[];
  creci?: string;
  about_text?: string;
  services?: string[];
  endereco?: string;
  office_hours?: string;
}

export const fetchTenantBranding = async (): Promise<TenantBranding | null> => {
  try {
    const response = await fetch("/api/portal/config", {
      headers: {
        "X-Tenant-Domain": window.location.hostname,
      },
    });

    if (!response.ok) return null;
    const payload = await response.json();
    const data = payload?.data || payload?.tenant || payload || null;

    // Ensure logo_url is properly set
    if (data && !data.logo_url && data.logo) {
      data.logo_url = data.logo;
    }

    return data;
  } catch (error) {
    console.warn('Failed to fetch tenant branding:', error);
    return null;
  }
};

export const hexToRgba = (hex: string, alpha: number) => {
  const cleaned = hex.replace("#", "");
  if (cleaned.length !== 6) return `rgba(0,0,0,${alpha})`;
  const r = parseInt(cleaned.slice(0, 2), 16);
  const g = parseInt(cleaned.slice(2, 4), 16);
  const b = parseInt(cleaned.slice(4, 6), 16);
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};
