import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

const TENANT_HOST = typeof window !== "undefined" ? window.location.hostname : "default";
const TENANT_BRANDING_CACHE_KEY = `tenant_branding_cache:${TENANT_HOST}`;
const LEGACY_BRANDING_CACHE_KEY = "tenant_branding_cache";
const EXCLUSIVA_HOSTS = new Set(["exclusivalarimoveis.com", "www.exclusivalarimoveis.com"]);
const EXCLUSIVA_GOOGLE_SITE_VERIFICATION = "daGiw9u34wtS7Jiizz0xPq5_Z03he3yi9ic2zEzmIhQ";

const applyTenantMetaTags = () => {
  if (typeof document === "undefined") return;

  const existingTag = document.querySelector("meta[name='google-site-verification']") as HTMLMetaElement | null;

  if (!EXCLUSIVA_HOSTS.has(TENANT_HOST)) {
    existingTag?.remove();
    return;
  }

  const verificationTag = existingTag || document.createElement("meta");
  verificationTag.setAttribute("name", "google-site-verification");
  verificationTag.setAttribute("content", EXCLUSIVA_GOOGLE_SITE_VERIFICATION);

  if (!existingTag) {
    document.head.appendChild(verificationTag);
  }
};

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
    const cached =
      localStorage.getItem(TENANT_BRANDING_CACHE_KEY) || localStorage.getItem(LEGACY_BRANDING_CACHE_KEY);
    if (cached) {
      try {
        const cachedData = JSON.parse(cached);
        applyBrandingToDocument(cachedData);
      } catch {
        // ignore cache parsing errors
      }
    }

    const response = await fetch("/api/portal/config", {
      headers: {
        "X-Tenant-Domain": window.location.hostname,
      },
    });

    if (!response.ok) return;
    const payload = await response.json();
    const data = payload?.data || payload?.tenant || payload;

    applyBrandingToDocument(data);
    localStorage.setItem(TENANT_BRANDING_CACHE_KEY, JSON.stringify(data));

    if (localStorage.getItem(LEGACY_BRANDING_CACHE_KEY)) {
      localStorage.removeItem(LEGACY_BRANDING_CACHE_KEY);
    }
  } catch {
    // No-op: keep default branding if request fails.
  }
};

if (typeof document !== "undefined") {
  document.documentElement.classList.add("dark");
  applyTenantMetaTags();
}

if (typeof window !== "undefined" && "serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker
      .getRegistrations()
      .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
      .catch(() => {
        // ignore service worker cleanup errors
      });

    if ("caches" in window) {
      caches
        .keys()
        .then((cacheNames) => Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName))))
        .catch(() => {
          // ignore cache cleanup errors
        });
    }
  });
}

const bootstrap = async () => {
  const tenantBootstrapTimeoutMs = 2500;

  await Promise.race([
    applyTenantBranding(),
    new Promise((resolve) => setTimeout(resolve, tenantBootstrapTimeoutMs)),
  ]);

  createRoot(document.getElementById("root")!).render(<App />);
};

bootstrap();
