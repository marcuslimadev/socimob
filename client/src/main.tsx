import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

const applyBrandingToDocument = (data: any) => {
  if (!data || typeof document === "undefined") return;

  if (data?.name) {
    document.title = data.name;
  }

  const faviconUrl = data?.favicon_url || data?.logo_url || data?.logo;
  if (faviconUrl) {
    let faviconLink = document.querySelector("link[rel~='icon']") as HTMLLinkElement | null;
    if (!faviconLink) {
      faviconLink = document.createElement("link");
      faviconLink.rel = "icon";
      document.head.appendChild(faviconLink);
    }
    faviconLink.href = faviconUrl;
  }

  const root = document.documentElement;

  if (data?.primary_color) {
    root.style.setProperty("--primary", data.primary_color);
    root.style.setProperty("--sidebar-primary", data.primary_color);
  }
  if (data?.secondary_color) {
    root.style.setProperty("--secondary", data.secondary_color);
  }

  if (data?.font_primary) {
    root.style.setProperty("--font-primary", data.font_primary);
  }
  if (data?.font_secondary) {
    root.style.setProperty("--font-secondary", data.font_secondary);
  }

  if (data?.font_url) {
    let fontLink = document.querySelector("link[data-tenant-font]") as HTMLLinkElement | null;
    if (!fontLink) {
      fontLink = document.createElement("link");
      fontLink.rel = "stylesheet";
      fontLink.setAttribute("data-tenant-font", "true");
      document.head.appendChild(fontLink);
    }
    fontLink.href = data.font_url;
  }
};

const applyTenantBranding = async () => {
  try {
    // Apply cached branding instantly to avoid flicker.
    const cached = localStorage.getItem("tenant_branding_cache");
    if (cached) {
      try {
        const cachedData = JSON.parse(cached);
        applyBrandingToDocument(cachedData);
      } catch {
        // ignore cache parsing errors
      }
    }

    const response = await fetch("/portal/config", {
      headers: {
        "X-Tenant-Domain": window.location.hostname,
      },
    });

    if (!response.ok) return;
    const payload = await response.json();
    const data = payload?.data || payload?.tenant || payload;

    applyBrandingToDocument(data);
    localStorage.setItem("tenant_branding_cache", JSON.stringify(data));
  } catch {
    // No-op: keep default branding if request fails.
  }
};

if (typeof document !== "undefined") {
  document.documentElement.classList.add("dark");
}

applyTenantBranding();
createRoot(document.getElementById("root")!).render(<App />);
