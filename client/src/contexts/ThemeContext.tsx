import React, { createContext, useContext, useEffect, useState } from "react";

export type Theme = "light" | "dark" | "navy";

export const INTERNAL_THEMES: Array<{ value: Theme; label: string }> = [
  { value: "light", label: "Claro" },
  { value: "dark", label: "Escuro" },
  { value: "navy", label: "Azul-marinho" },
];

export function getNextTheme(theme: Theme): Theme {
  const index = INTERNAL_THEMES.findIndex(option => option.value === theme);
  return INTERNAL_THEMES[(index + 1) % INTERNAL_THEMES.length].value;
}

interface ThemeContextType {
  theme: Theme;
  setTheme: (theme: Theme) => void;
  toggleTheme: () => void;
  switchable: boolean;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

interface ThemeProviderProps {
  children: React.ReactNode;
  defaultTheme?: Theme;
  switchable?: boolean;
}

export function ThemeProvider({
  children,
  defaultTheme = "light",
  switchable = false,
}: ThemeProviderProps) {
  const [theme, setThemeState] = useState<Theme>(() => {
    if (switchable) {
      const stored = localStorage.getItem("theme");
      return INTERNAL_THEMES.some(option => option.value === stored)
        ? (stored as Theme)
        : defaultTheme;
    }
    return defaultTheme;
  });

  const setTheme = (nextTheme: Theme) => {
    if (!switchable) return;
    setThemeState(nextTheme);
  };

  useEffect(() => {
    const root = document.documentElement;
    const body = document.body;

    root.classList.remove("light", "dark", "navy");
    root.classList.add(theme);

    root.dataset.theme = theme;
    if (body) {
      body.dataset.theme = theme;
    }

    if (switchable) {
      localStorage.setItem("theme", theme);
    }
  }, [theme, switchable]);

  const toggleTheme = () => {
    if (!switchable) return;
    setThemeState(getNextTheme);
  };

  return (
    <ThemeContext.Provider value={{ theme, setTheme, toggleTheme, switchable }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error("useTheme must be used within ThemeProvider");
  }
  return context;
}
