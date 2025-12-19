/**
 * Minimal stub for '@vue/devtools-api'.
 *
 * Pinia imports only the `setupDevtoolsPlugin` helper. We provide
 * a no-op implementation so the dependency tree doesn't attempt to
 * connect with the Vue Devtools browser extension when running in
 * production, preventing repeated `chrome.runtime` errors.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function setupDevtoolsPlugin(..._args: any[]): void {
  if (import.meta.env.DEV) {
    console.info(
      '[devtools] Vue Devtools integration disabled. Set VITE_ENABLE_DEVTOOLS=true to re-enable it.',
    )
  }
}
