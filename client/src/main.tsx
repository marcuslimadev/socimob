import { createRoot } from "react-dom/client";
import App from "./App";
import "./index.css";

const applyTenantBranding = async () => {
  try {
    const response = await fetch("/portal/config", {
      headers: {
        "X-Tenant-Domain": window.location.hostname,
      },
    });

    if (!response.ok) return;
    const payload = await response.json();
    const data = payload?.data || payload?.tenant || payload;

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

    if (typeof document !== "undefined") {
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
    }
  } catch {
    // No-op: keep default branding if request fails.
  }
};

applyTenantBranding().finally(() => {
  createRoot(document.getElementById("root")!).render(<App />);
});
