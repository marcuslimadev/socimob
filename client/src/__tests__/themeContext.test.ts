import { describe, expect, it } from 'vitest';
import { getNextTheme, INTERNAL_THEMES } from '@/contexts/ThemeContext';

describe('internal themes', () => {
  it('offers light, dark and navy themes', () => {
    expect(INTERNAL_THEMES.map(theme => theme.value)).toEqual(['light', 'dark', 'navy']);
  });

  it('cycles through all themes', () => {
    expect(getNextTheme('light')).toBe('dark');
    expect(getNextTheme('dark')).toBe('navy');
    expect(getNextTheme('navy')).toBe('light');
  });
});
