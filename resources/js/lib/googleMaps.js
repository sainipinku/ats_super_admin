/**
 * Shared Google Maps Loader configuration for CadMax SPA.
 * Keeping id and libraries identical across all components prevents:
 * "Loader must not be called again with different options" errors.
 */

export const GOOGLE_MAPS_LOADER_ID = 'cadmax-google-maps-script';
export const GOOGLE_MAPS_LIBRARIES = ['places'];

/**
 * Resolves the Google Maps API key across props, Inertia page props,
 * window globals, and import.meta.env.
 */
export function resolveGoogleMapsApiKey(propApiKey = null, pageProps = null) {
  return (
    propApiKey ||
    pageProps?.google_maps_api_key ||
    (typeof window !== 'undefined' && window.GOOGLE_MAPS_API_KEY) ||
    (typeof window !== 'undefined' && window.__APP_SETTINGS?.google_maps_api_key) ||
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY ||
    ''
  ).trim();
}
