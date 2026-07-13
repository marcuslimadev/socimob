import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const css = readFileSync(resolve(process.cwd(), 'client/src/index.css'), 'utf8');

function variablesFor(selector: string): Record<string, string> {
  const start = css.indexOf(`${selector} {`);
  if (start < 0) throw new Error(`Selector not found: ${selector}`);
  const block = css.slice(start, css.indexOf('}', start));
  return Object.fromEntries(
    [...block.matchAll(/--([\w-]+):\s*(#[0-9a-fA-F]{6})\s*;/g)].map(match => [match[1], match[2]]),
  );
}

function luminance(hex: string): number {
  const channels = [1, 3, 5].map(index => parseInt(hex.slice(index, index + 2), 16) / 255);
  const linear = channels.map(channel => channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4);
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}

function contrast(left: string, right: string): number {
  const [bright, dark] = [luminance(left), luminance(right)].sort((a, b) => b - a);
  return (bright + 0.05) / (dark + 0.05);
}

describe('theme contrast', () => {
  for (const selector of [':root', '.dark', '.navy']) {
    it(`${selector} keeps essential text pairs at WCAG AA`, () => {
      const theme = variablesFor(selector);
      expect(contrast(theme.foreground, theme.background)).toBeGreaterThanOrEqual(4.5);
      expect(contrast(theme['card-foreground'], theme.card)).toBeGreaterThanOrEqual(4.5);
      expect(contrast(theme['primary-foreground'], theme.primary)).toBeGreaterThanOrEqual(4.5);
      expect(contrast(theme['muted-foreground'], theme.muted)).toBeGreaterThanOrEqual(4.5);
    });
  }
});
