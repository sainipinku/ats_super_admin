import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  GoogleMap,
  Marker,
  useJsApiLoader,
} from '@react-google-maps/api';
import toast from 'react-hot-toast';
import LoadingSpinner from '@/Components/LoadingSpinner.jsx';

const mapContainerStyle = {
  width: '100%',
  height: '360px',
};

const libraries = ['places'];

export default function GoogleMapLocationSection({
  initialLat = null,
  initialLng = null,
  initialAddress = '',
  initialLocationName = '',
  onChange,
}) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';

  const { isLoaded, loadError } = useJsApiLoader({
    googleMapsApiKey: apiKey,
    libraries,
    id: 'google-maps-location-picker',
  });

  const mapRef = useRef(null);
  const geocoderRef = useRef(null);
  const autocompleteSessionTokenRef = useRef(null);
  const skipAutocompleteInputRef = useRef(null);

  const hasInitialCoords =
    initialLat != null && initialLng != null && initialLat !== '' && initialLng !== '';

  const [markerPosition, setMarkerPosition] = useState(
    hasInitialCoords
      ? { lat: Number(initialLat), lng: Number(initialLng) }
      : { lat: 20.5937, lng: 78.9629 }
  );
  const [formattedAddress, setFormattedAddress] = useState(
    initialLocationName || initialAddress || ''
  );
  const [searchInput, setSearchInput] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [searchError, setSearchError] = useState('');
  const [manualCoords, setManualCoords] = useState({
    lat: hasInitialCoords ? String(initialLat) : '',
    lng: hasInitialCoords ? String(initialLng) : '',
  });

  useEffect(() => {
    const lat = Number(initialLat);
    const lng = Number(initialLng);
    if (
      initialLat != null &&
      initialLng != null &&
      initialLat !== '' &&
      initialLng !== '' &&
      Number.isFinite(lat) &&
      Number.isFinite(lng)
    ) {
      setMarkerPosition({ lat, lng });
      setManualCoords({ lat: String(initialLat), lng: String(initialLng) });
    }
    if (initialLocationName) {
      setFormattedAddress(initialLocationName);
    }
  }, [initialLat, initialLng, initialLocationName]);

  const emitChange = useCallback(
    (patch) => {
      if (!onChange) return;
      onChange({
        location_name: patch.location_name ?? formattedAddress,
        project_address: patch.project_address ?? formattedAddress,
        latitude: patch.latitude ?? markerPosition.lat,
        longitude: patch.longitude ?? markerPosition.lng,
      });
    },
    [onChange, formattedAddress, markerPosition]
  );

  const reverseGeocode = useCallback((lat, lng) => {
    if (!geocoderRef.current) {
      if (typeof window === 'undefined' || !window.google) return;
      geocoderRef.current = new window.google.maps.Geocoder();
    }
    geocoderRef.current.geocode(
      { location: { lat, lng } },
      (results, status) => {
        if (status === 'OK' && results?.[0]) {
          const formatted = results[0].formatted_address;
          setFormattedAddress(formatted);
          emitChange({
            location_name: formatted,
            project_address: formatted,
            latitude: lat,
            longitude: lng,
          });
        } else {
          setFormattedAddress('Address lookup unavailable');
          emitChange({
            latitude: lat,
            longitude: lng,
          });
        }
      }
    );
  }, [emitChange]);

  const onLoad = useCallback((map) => {
    mapRef.current = map;
    if (typeof window !== 'undefined' && window.google) {
      geocoderRef.current = new window.google.maps.Geocoder();
    }
    if (hasInitialCoords && mapRef.current && markerPosition) {
      mapRef.current.setCenter(markerPosition);
      mapRef.current.setZoom(15);
      if (!initialLocationName && !initialAddress) {
        reverseGeocode(markerPosition.lat, markerPosition.lng);
      }
    }
  }, [hasInitialCoords, markerPosition, initialLocationName, initialAddress, reverseGeocode]);

  useEffect(() => {
    const input = searchInput.trim();
    const skipAutocomplete = skipAutocompleteInputRef.current === input;
    skipAutocompleteInputRef.current = null;
    if (!isLoaded || !input || skipAutocomplete) {
      setSuggestions([]);
      setIsSearching(false);
      setSearchError('');
      return undefined;
    }

    let cancelled = false;
    const timeoutId = window.setTimeout(async () => {
      setIsSearching(true);
      setSearchError('');
      try {
        const places = window.google.maps.places;
        autocompleteSessionTokenRef.current ??= new places.AutocompleteSessionToken();
        const response = await places.AutocompleteSuggestion.fetchAutocompleteSuggestions({
          input,
          sessionToken: autocompleteSessionTokenRef.current,
        });
        if (!cancelled) {
          setSuggestions((response.suggestions || []).filter((item) => item.placePrediction));
        }
      } catch (error) {
        if (!cancelled) {
          console.error('Google Places autocomplete failed:', error);
          setSuggestions([]);
          setSearchError('Location search failed. Check your API key, billing, and that Places API (New) is enabled for your Google Maps project.');
        }
      } finally {
        if (!cancelled) setIsSearching(false);
      }
    }, 300);

    return () => {
      cancelled = true;
      window.clearTimeout(timeoutId);
    };
  }, [isLoaded, searchInput]);

  const selectSuggestion = useCallback(async (suggestion) => {
    try {
      const place = suggestion.placePrediction.toPlace();
      await place.fetchFields({
        fields: ['displayName', 'formattedAddress', 'location'],
      });
      const location = place.location;
      if (!location) {
        toast.error('Selected location has no coordinates.');
        return;
      }
      const lat = location.lat();
      const lng = location.lng();
      const locationName = place.displayName || place.formattedAddress || '';
      const address = place.formattedAddress || locationName;
      setMarkerPosition({ lat, lng });
      setManualCoords({ lat: String(lat), lng: String(lng) });
      setFormattedAddress(address);
      skipAutocompleteInputRef.current = locationName;
      setSearchInput(locationName);
      setSuggestions([]);
      setSearchError('');
      autocompleteSessionTokenRef.current = null;
      if (mapRef.current) {
        mapRef.current.panTo({ lat, lng });
        mapRef.current.setZoom(15);
      }
      emitChange({
        location_name: locationName,
        project_address: address,
        latitude: lat,
        longitude: lng,
      });
    } catch (error) {
      console.error('Google Places location details failed:', error);
      toast.error('Unable to load details for the selected location.');
    }
  }, [emitChange]);

  const onMarkerDragEnd = useCallback((event) => {
    const lat = event.latLng.lat();
    const lng = event.latLng.lng();
    setMarkerPosition({ lat, lng });
    setManualCoords({ lat: String(lat), lng: String(lng) });
    reverseGeocode(lat, lng);
  }, [reverseGeocode]);

  const handleManualCoordChange = useCallback((field, rawValue) => {
    setManualCoords((prev) => ({ ...prev, [field]: rawValue }));
    const other = field === 'lat' ? manualCoords.lng : manualCoords.lat;
    const parsedField = Number(rawValue);
    const parsedOther = Number(other);
    if (
      rawValue !== '' &&
      Number.isFinite(parsedField) &&
      other !== '' &&
      Number.isFinite(parsedOther)
    ) {
      if (field === 'lat' && (parsedField < -90 || parsedField > 90)) return;
      if (field === 'lng' && (parsedOther < -180 || parsedOther > 180)) return;
      const nextLat = field === 'lat' ? parsedField : parsedOther;
      const nextLng = field === 'lng' ? parsedField : parsedOther;
      setMarkerPosition({ lat: nextLat, lng: nextLng });
      if (mapRef.current) {
        mapRef.current.panTo({ lat: nextLat, lng: nextLng });
      }
      reverseGeocode(nextLat, nextLng);
    }
  }, [manualCoords, reverseGeocode]);

  const options = useMemo(
    () => ({
      disableDefaultUI: false,
      zoomControl: true,
      streetViewControl: false,
      fullscreenControl: false,
      mapTypeControl: true,
    }),
    []
  );

  if (loadError) {
    return (
      <div className="rounded-md border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
        Unable to load Google Maps. Check your API key or internet connection and try again.
      </div>
    );
  }

  if (!isLoaded) {
    return (
      <div className="flex h-[420px] items-center justify-center rounded-md border border-border/60 bg-muted/30">
        <LoadingSpinner size="sm" />
        <span className="ml-3 text-sm text-muted-foreground">Loading map…</span>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="space-y-2">
        <label className="text-xs font-medium text-foreground">Search Location</label>
        <div className="relative">
          <input
            type="text"
            placeholder="Start typing an address, place, or landmark…"
            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none ring-offset-background focus:border-ring focus:ring-2 focus:ring-ring/30 placeholder:text-muted-foreground"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            role="combobox"
            aria-autocomplete="list"
            aria-expanded={suggestions.length > 0}
          />
          {suggestions.length > 0 && (
            <ul
              className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-border bg-background py-1 shadow-lg"
              role="listbox"
            >
              {suggestions.map((suggestion) => {
                const prediction = suggestion.placePrediction;
                if (!prediction) return null;
                return (
                  <li key={prediction.placeId} role="option" aria-selected="false">
                    <button
                      type="button"
                      className="w-full px-3 py-2 text-left text-sm text-foreground hover:bg-muted"
                      onClick={() => selectSuggestion(suggestion)}
                    >
                      {prediction.text.toString()}
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
          {isSearching && (
            <p className="mt-1 text-xs text-muted-foreground">Searching locations…</p>
          )}
          {searchError && (
            <p className="mt-1 text-xs text-destructive" role="alert">{searchError}</p>
          )}
        </div>
      </div>

      <div className="overflow-hidden rounded-md border border-border/60">
        <GoogleMap
          mapContainerStyle={mapContainerStyle}
          zoom={hasInitialCoords ? 15 : 5}
          center={markerPosition}
          options={options}
          onLoad={onLoad}
        >
          {markerPosition && (
            <Marker
              position={markerPosition}
              draggable={true}
              onDragEnd={onMarkerDragEnd}
            />
          )}
        </GoogleMap>
      </div>

      <div className="rounded-md border border-border/60 bg-muted/20 p-3 text-sm space-y-2">
        <div>
          <div className="text-[11px] uppercase tracking-wide text-muted-foreground">Formatted Address</div>
          <div className="mt-0.5 text-foreground break-words">
            {formattedAddress || (
              <span className="text-muted-foreground italic">
                Search for a location or drag the pin on the map above.
              </span>
            )}
          </div>
        </div>
        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <CoordInput
            label="Latitude"
            value={manualCoords.lat}
            onChange={(v) => handleManualCoordChange('lat', v)}
            type="number"
            step="any"
            min="-90"
            max="90"
            placeholder="-90.0 to 90.0"
            error={
              manualCoords.lat !== '' &&
              (Number(manualCoords.lat) < -90 || Number(manualCoords.lat) > 90)
                ? 'Must be -90 to 90'
                : undefined
            }
          />
          <CoordInput
            label="Longitude"
            value={manualCoords.lng}
            onChange={(v) => handleManualCoordChange('lng', v)}
            type="number"
            step="any"
            min="-180"
            max="180"
            placeholder="-180.0 to 180.0"
            error={
              manualCoords.lng !== '' &&
              (Number(manualCoords.lng) < -180 || Number(manualCoords.lng) > 180)
                ? 'Must be -180 to 180'
                : undefined
            }
          />
        </div>
      </div>
    </div>
  );
}

function CoordInput({ label, value, onChange, type = 'text', step, min, max, placeholder, error }) {
  return (
    <div>
      <label className="mb-1 block text-[12px] font-medium text-foreground">{label}</label>
      <input
        type={type}
        step={step}
        min={min}
        max={max}
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm outline-none ring-offset-background focus:border-ring focus:ring-2 focus:ring-ring/30 placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-60"
      />
      {error ? <p className="mt-1 text-xs text-destructive">{error}</p> : null}
    </div>
  );
}
