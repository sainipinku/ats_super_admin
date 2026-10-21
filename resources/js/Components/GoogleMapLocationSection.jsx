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
  const apiKey = import.meta.env.GOOGLE_MAPS_API_KEY || '';

  const { isLoaded, loadError } = useJsApiLoader({
    googleMapsApiKey: apiKey,
    libraries,
    id: 'google-maps-location-picker',
  });

  const mapRef = useRef(null);
  const geocoderRef = useRef(null);
  const searchContainerRef = useRef(null);
  const debounceTimerRef = useRef(null);

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

  const [searchQuery, setSearchQuery] = useState(initialLocationName || initialAddress || '');
  const [showDropdown, setShowDropdown] = useState(false);
  const [loadingSuggestions, setLoadingSuggestions] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);

  // Sync initial coordinates
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
    if (initialLocationName || initialAddress) {
      const addr = initialLocationName || initialAddress;
      setFormattedAddress(addr);
      setSearchQuery(addr);
    }
  }, [initialLat, initialLng, initialLocationName, initialAddress]);

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

  const reverseGeocode = useCallback(
    (lat, lng) => {
      if (!geocoderRef.current) {
        if (typeof window === 'undefined' || !window.google?.maps?.Geocoder) return;
        geocoderRef.current = new window.google.maps.Geocoder();
      }
      geocoderRef.current.geocode(
        { location: { lat, lng } },
        (results, status) => {
          if (status === 'OK' && results?.[0]) {
            const formatted = results[0].formatted_address;
            setFormattedAddress(formatted);
            setSearchQuery(formatted);
            emitChange({
              location_name: formatted,
              project_address: formatted,
              latitude: lat,
              longitude: lng,
            });
          } else {
            const coordStr = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            setFormattedAddress(coordStr);
            setSearchQuery(coordStr);
            emitChange({
              latitude: lat,
              longitude: lng,
            });
          }
        }
      );
    },
    [emitChange]
  );

  const onLoad = useCallback(
    (map) => {
      mapRef.current = map;
      if (typeof window !== 'undefined' && window.google?.maps?.Geocoder) {
        geocoderRef.current = new window.google.maps.Geocoder();
      }
      if (hasInitialCoords && mapRef.current && markerPosition) {
        mapRef.current.setCenter(markerPosition);
        mapRef.current.setZoom(15);
        if (!initialLocationName && !initialAddress) {
          reverseGeocode(markerPosition.lat, markerPosition.lng);
        }
      }
    },
    [hasInitialCoords, markerPosition, initialLocationName, initialAddress, reverseGeocode]
  );

  // Fetch address & landmark suggestions with location bias (Places API New -> Places Service -> Geocoder)
  const fetchSuggestions = useCallback(
    async (query) => {
      const trimmed = query.trim();
      if (trimmed.length < 3) {
        setSuggestions([]);
        setShowDropdown(false);
        return;
      }

      if (!geocoderRef.current) {
        if (typeof window === 'undefined' || !window.google?.maps?.Geocoder) return;
        geocoderRef.current = new window.google.maps.Geocoder();
      }

      setLoadingSuggestions(true);
      setHighlightedIndex(-1);

      // Local bias: Use current map bounds or marker position
      const bounds = mapRef.current?.getBounds();
      const currentCenter = markerPosition
        ? { lat: Number(markerPosition.lat), lng: Number(markerPosition.lng) }
        : undefined;

      let items = [];

      // 1. Try Places API (New) AutocompleteSuggestion (Finds establishments, landmarks, hubs, shops)
      try {
        if (window.google?.maps?.importLibrary) {
          const { AutocompleteSuggestion } = await window.google.maps.importLibrary('places');
          if (AutocompleteSuggestion?.fetchAutocompleteSuggestions) {
            const req = {
              input: trimmed,
              locationBias: bounds || (currentCenter ? { center: currentCenter, radius: 50000 } : undefined),
            };
            const res = await AutocompleteSuggestion.fetchAutocompleteSuggestions(req);
            if (res?.suggestions?.length > 0) {
              items = res.suggestions.map((s, idx) => {
                const p = s.placePrediction;
                return {
                  id: p.placeId || `p-new-${idx}`,
                  placeId: p.placeId,
                  prediction: p,
                  mainText: p.mainText?.text || p.text?.text || trimmed,
                  secondaryText: p.secondaryText?.text || '',
                  fullAddress: p.text?.text || p.mainText?.text || trimmed,
                  type: 'place',
                };
              });
            }
          }
        }
      } catch (err) {
        // Places API (New) not active or errored
      }

      // 2. If no items yet, try classic Places AutocompleteService
      if (items.length === 0) {
        try {
          if (window.google?.maps?.places?.AutocompleteService) {
            const autoService = new window.google.maps.places.AutocompleteService();
            const preds = await new Promise((resolve) => {
              autoService.getPlacePredictions(
                {
                  input: trimmed,
                  bounds: bounds,
                  location: currentCenter ? new window.google.maps.LatLng(currentCenter.lat, currentCenter.lng) : undefined,
                  radius: 50000,
                },
                (results, status) => {
                  if (status === 'OK' && Array.isArray(results)) resolve(results);
                  else resolve([]);
                }
              );
            });
            if (preds.length > 0) {
              items = preds.map((p) => ({
                id: p.place_id,
                placeId: p.place_id,
                mainText: p.structured_formatting?.main_text || p.description,
                secondaryText: p.structured_formatting?.secondary_text || '',
                fullAddress: p.description,
                type: 'place',
              }));
            }
          }
        } catch (err) {
          // AutocompleteService error
        }
      }

      // 3. If still no items, fallback to Geocoder with bounds
      if (items.length === 0) {
        try {
          const geoResults = await new Promise((resolve) => {
            geocoderRef.current.geocode(
              {
                address: trimmed,
                bounds: bounds,
              },
              (results, status) => {
                if (status === 'OK' && Array.isArray(results)) resolve(results);
                else resolve([]);
              }
            );
          });
          if (geoResults.length > 0) {
            items = geoResults.map((r, idx) => {
              const full = r.formatted_address || '';
              const parts = full.split(',').map((p) => p.trim());
              return {
                id: r.place_id || `geo-${idx}`,
                placeId: r.place_id,
                mainText: parts[0] || full,
                secondaryText: parts.slice(1).join(', ') || '',
                fullAddress: full,
                lat: r.geometry?.location?.lat(),
                lng: r.geometry?.location?.lng(),
                type: 'address',
              };
            });
          }
        } catch (err) {
          console.warn('Geocoder error:', err);
        }
      }

      setLoadingSuggestions(false);
      setSuggestions(items);
      setShowDropdown(true);
    },
    [markerPosition]
  );

  // Handle typing with 300ms debounce
  const handleInputChange = (e) => {
    const val = e.target.value;
    setSearchQuery(val);

    if (debounceTimerRef.current) {
      clearTimeout(debounceTimerRef.current);
    }

    if (val.trim().length >= 3) {
      setLoadingSuggestions(true);
      setShowDropdown(true);
      debounceTimerRef.current = setTimeout(() => {
        fetchSuggestions(val);
      }, 300);
    } else {
      setLoadingSuggestions(false);
      setSuggestions([]);
      setShowDropdown(false);
    }
  };

  // Select suggestion from dropdown
  const handleSelectSuggestion = async (item) => {
    if (!item) return;
    setSearchQuery(item.fullAddress);
    setShowDropdown(false);
    setSuggestions([]);
    setHighlightedIndex(-1);

    const applyLocation = (lat, lng, address) => {
      setMarkerPosition({ lat, lng });
      setManualCoords({ lat: String(lat), lng: String(lng) });
      setFormattedAddress(address);
      setSearchQuery(address);

      if (mapRef.current) {
        mapRef.current.panTo({ lat, lng });
        mapRef.current.setZoom(17);
      }

      emitChange({
        location_name: address,
        project_address: address,
        latitude: lat,
        longitude: lng,
      });
      toast.success('Location selected & pinned!');
    };

    // Case 1: Place prediction from Places API (New)
    if (item.prediction?.toPlace) {
      try {
        toast.loading('Pinning place…', { id: 'place-load' });
        const place = item.prediction.toPlace();
        await place.fetchFields({
          fields: ['displayName', 'formattedAddress', 'location'],
        });
        toast.dismiss('place-load');
        if (place.location) {
          applyLocation(
            place.location.lat(),
            place.location.lng(),
            place.formattedAddress || place.displayName || item.fullAddress
          );
          return;
        }
      } catch (e) {
        toast.dismiss('place-load');
      }
    }

    // Case 2: placeId lookup via Geocoder
    if (item.placeId) {
      if (!geocoderRef.current) {
        if (typeof window !== 'undefined' && window.google?.maps?.Geocoder) {
          geocoderRef.current = new window.google.maps.Geocoder();
        }
      }
      if (geocoderRef.current) {
        geocoderRef.current.geocode({ placeId: item.placeId }, (results, status) => {
          if (status === 'OK' && results?.[0]) {
            const best = results[0];
            const lat = best.geometry?.location?.lat();
            const lng = best.geometry?.location?.lng();
            if (lat != null && lng != null) {
              applyLocation(lat, lng, best.formatted_address || item.fullAddress);
              return;
            }
          }
          if (item.lat != null && item.lng != null) {
            applyLocation(item.lat, item.lng, item.fullAddress);
          }
        });
        return;
      }
    }

    // Case 3: direct lat/lng
    if (item.lat != null && item.lng != null) {
      applyLocation(item.lat, item.lng, item.fullAddress);
    }
  };

  // Direct search (Enter key or Search button)
  const handleGeocodeSearch = useCallback(
    async (explicitQuery) => {
      const query = (explicitQuery || searchQuery).trim();
      if (!query) {
        toast.error('Please enter an address or place name to search.');
        return;
      }

      // If dropdown has active results, select top result
      if (suggestions.length > 0) {
        const top = highlightedIndex >= 0 ? suggestions[highlightedIndex] : suggestions[0];
        handleSelectSuggestion(top);
        return;
      }

      setShowDropdown(false);
      setIsSearching(true);

      const bounds = mapRef.current?.getBounds();

      // Try Places API (New) first for landmarks
      try {
        if (window.google?.maps?.importLibrary) {
          const { AutocompleteSuggestion } = await window.google.maps.importLibrary('places');
          if (AutocompleteSuggestion?.fetchAutocompleteSuggestions) {
            const res = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
              input: query,
              locationBias: bounds || markerPosition,
            });
            if (res?.suggestions?.length > 0) {
              const p = res.suggestions[0].placePrediction;
              const place = p.toPlace();
              await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
              if (place.location) {
                setIsSearching(false);
                const lat = place.location.lat();
                const lng = place.location.lng();
                const addr = place.formattedAddress || place.displayName || query;
                setMarkerPosition({ lat, lng });
                setManualCoords({ lat: String(lat), lng: String(lng) });
                setFormattedAddress(addr);
                setSearchQuery(addr);
                if (mapRef.current) {
                  mapRef.current.panTo({ lat, lng });
                  mapRef.current.setZoom(17);
                }
                emitChange({
                  location_name: addr,
                  project_address: addr,
                  latitude: lat,
                  longitude: lng,
                });
                toast.success('Location found & pinned!');
                return;
              }
            }
          }
        }
      } catch (e) {
        // Fallback to Geocoder
      }

      if (!geocoderRef.current) {
        if (typeof window === 'undefined' || !window.google?.maps?.Geocoder) return;
        geocoderRef.current = new window.google.maps.Geocoder();
      }

      geocoderRef.current.geocode({ address: query, bounds }, (results, status) => {
        setIsSearching(false);
        if (status === 'OK' && results?.[0]) {
          const best = results[0];
          const lat = best.geometry.location.lat();
          const lng = best.geometry.location.lng();
          const formatted = best.formatted_address || query;

          setMarkerPosition({ lat, lng });
          setManualCoords({ lat: String(lat), lng: String(lng) });
          setFormattedAddress(formatted);
          setSearchQuery(formatted);

          if (mapRef.current) {
            mapRef.current.panTo({ lat, lng });
            mapRef.current.setZoom(16);
          }

          emitChange({
            location_name: formatted,
            project_address: formatted,
            latitude: lat,
            longitude: lng,
          });
          toast.success('Location found & pinned!');
        } else if (status === 'ZERO_RESULTS') {
          toast.error('No matching location found. Check spelling or try nearby landmarks.');
        } else {
          toast.error(`Search unavailable (${status}). Click on the map to pin.`);
        }
      });
    },
    [searchQuery, suggestions, highlightedIndex, markerPosition, emitChange]
  );

  // Keyboard navigation for dropdown
  const handleKeyDown = (e) => {
    if (!showDropdown || suggestions.length === 0) {
      if (e.key === 'Enter') {
        e.preventDefault();
        handleGeocodeSearch();
      }
      return;
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlightedIndex((prev) => (prev < suggestions.length - 1 ? prev + 1 : 0));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : suggestions.length - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (highlightedIndex >= 0 && highlightedIndex < suggestions.length) {
        handleSelectSuggestion(suggestions[highlightedIndex]);
      } else {
        handleSelectSuggestion(suggestions[0]);
      }
    } else if (e.key === 'Escape') {
      setShowDropdown(false);
    }
  };

  // Click outside listener
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (searchContainerRef.current && !searchContainerRef.current.contains(event.target)) {
        setShowDropdown(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  // Click on map to place pin
  const onMapClick = useCallback(
    (event) => {
      if (!event.latLng) return;
      const lat = event.latLng.lat();
      const lng = event.latLng.lng();
      setMarkerPosition({ lat, lng });
      setManualCoords({ lat: String(lat), lng: String(lng) });
      reverseGeocode(lat, lng);
    },
    [reverseGeocode]
  );

  // Drag marker to adjust location
  const onMarkerDragEnd = useCallback(
    (event) => {
      const lat = event.latLng.lat();
      const lng = event.latLng.lng();
      setMarkerPosition({ lat, lng });
      setManualCoords({ lat: String(lat), lng: String(lng) });
      reverseGeocode(lat, lng);
    },
    [reverseGeocode]
  );

  // Detect current location via browser GPS
  const handleCurrentLocation = useCallback(() => {
    if (!navigator.geolocation) {
      toast.error('Geolocation is not supported by your browser.');
      return;
    }
    toast.loading('Detecting current GPS location…', { id: 'gps-detect' });
    navigator.geolocation.getCurrentPosition(
      (position) => {
        toast.dismiss('gps-detect');
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        setMarkerPosition({ lat, lng });
        setManualCoords({ lat: String(lat), lng: String(lng) });
        if (mapRef.current) {
          mapRef.current.panTo({ lat, lng });
          mapRef.current.setZoom(16);
        }
        reverseGeocode(lat, lng);
        toast.success('Current location detected!');
      },
      (error) => {
        toast.dismiss('gps-detect');
        toast.error(error.message || 'Unable to retrieve location.');
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  }, [reverseGeocode]);

  const handleManualCoordChange = useCallback(
    (field, rawValue) => {
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
          mapRef.current.panTo({ lat, nextLat });
        }
        reverseGeocode(nextLat, nextLng);
      }
    },
    [manualCoords, reverseGeocode]
  );

  const options = useMemo(
    () => ({
      disableDefaultUI: false,
      zoomControl: true,
      streetViewControl: false,
      fullscreenControl: true,
      mapTypeControl: true,
    }),
    []
  );

  if (loadError) {
    return (
      <div className="rounded-xl border border-rose-200 bg-rose-50/50 p-4 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300">
        <p className="font-semibold">Unable to load Google Maps</p>
        <p className="mt-1">
          Please check your internet connection or verify that the Google Maps API key in{' '}
          <code className="rounded bg-rose-100 px-1 py-0.5 dark:bg-rose-900/40">.env</code> is active with Maps JavaScript API enabled.
        </p>
      </div>
    );
  }

  if (!isLoaded) {
    return (
      <div className="flex h-[360px] items-center justify-center rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/50">
        <LoadingSpinner size="sm" />
        <span className="ml-3 text-xs text-slate-500">Loading Google Maps…</span>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {/* Header with GPS Action */}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
          Search Location & Pin Coordinates
        </label>
        <button
          type="button"
          onClick={handleCurrentLocation}
          className="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50/60 px-2.5 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/50 dark:bg-indigo-950/40 dark:text-indigo-300"
        >
          <span>📍</span>
          Use My Current Location
        </button>
      </div>

      {/* Autocomplete Search Bar & Live Dropdown */}
      <div ref={searchContainerRef} className="relative">
        <div className="flex gap-2">
          <div className="relative flex-1">
            <input
              type="text"
              value={searchQuery}
              onChange={handleInputChange}
              onFocus={() => {
                if (searchQuery.trim().length >= 3 && suggestions.length > 0) {
                  setShowDropdown(true);
                }
              }}
              onKeyDown={handleKeyDown}
              placeholder="Search landmark, business, address, or PIN code… (min. 3 characters)"
              className="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 pl-9 pr-8 text-xs text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:focus:border-indigo-400"
            />
            {/* Search icon or loading spinner */}
            <div className="pointer-events-none absolute left-3 top-2.5 flex items-center">
              {loadingSuggestions ? (
                <svg className="h-4 w-4 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
              ) : (
                <svg
                  className="h-4 w-4 text-slate-400"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              )}
            </div>

            {/* Clear Input Button */}
            {searchQuery && (
              <button
                type="button"
                onClick={() => {
                  setSearchQuery('');
                  setSuggestions([]);
                  setShowDropdown(false);
                }}
                className="absolute right-2.5 top-2.5 flex h-5 w-5 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                title="Clear search"
              >
                ✕
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={() => handleGeocodeSearch()}
            disabled={isSearching}
            className="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
          >
            {isSearching ? 'Searching…' : 'Search'}
          </button>
        </div>

        {/* Live Suggestions Dropdown (Opens after 3 characters) */}
        {showDropdown && searchQuery.trim().length >= 3 && (
          <div className="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1.5 shadow-2xl dark:border-slate-800 dark:bg-slate-900">
            {loadingSuggestions && suggestions.length === 0 ? (
              <div className="flex items-center gap-2.5 px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                <svg className="h-4 w-4 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
                <span>Searching matching places & landmarks for "{searchQuery}"…</span>
              </div>
            ) : suggestions.length > 0 ? (
              <div className="divide-y divide-slate-100 dark:divide-slate-800/80">
                <div className="flex items-center justify-between px-3.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                  <span>Matching Locations ({suggestions.length})</span>
                  <span>Click to Pin</span>
                </div>
                {suggestions.map((item, idx) => (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => handleSelectSuggestion(item)}
                    onMouseEnter={() => setHighlightedIndex(idx)}
                    className={`flex w-full items-start gap-3 px-3.5 py-2.5 text-left transition ${highlightedIndex === idx
                      ? 'bg-indigo-50/80 text-indigo-950 dark:bg-indigo-950/60 dark:text-indigo-200'
                      : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/60'
                      }`}
                  >
                    <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-[13px] text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400">
                      {item.type === 'place' ? '🏢' : '📍'}
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-xs font-semibold text-slate-900 dark:text-white">
                        {item.mainText}
                      </p>
                      {item.secondaryText && (
                        <p className="truncate text-[11px] text-slate-500 dark:text-slate-400">
                          {item.secondaryText}
                        </p>
                      )}
                    </div>
                  </button>
                ))}
              </div>
            ) : (
              <div className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                No matching locations found for "{searchQuery}". Try adding a city name, or click directly on the map.
              </div>
            )}
          </div>
        )}
      </div>

      {/* Map Display */}
      <div className="relative overflow-hidden rounded-xl border border-slate-200 shadow-sm dark:border-slate-800">
        <GoogleMap
          mapContainerStyle={mapContainerStyle}
          zoom={hasInitialCoords ? 15 : 5}
          center={markerPosition}
          options={options}
          onLoad={onLoad}
          onClick={onMapClick}
        >
          {markerPosition && (
            <Marker
              position={markerPosition}
              draggable={true}
              onDragEnd={onMarkerDragEnd}
            />
          )}
        </GoogleMap>
        <div className="pointer-events-none absolute bottom-2 left-2 rounded-lg bg-white/95 px-2.5 py-1 text-[11px] font-medium text-slate-700 shadow backdrop-blur-sm dark:bg-slate-900/95 dark:text-slate-300">
          💡 Click anywhere on map or drag pin to adjust
        </div>
      </div>

      {/* Formatted Address & Coordinates Details */}
      <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 text-xs dark:border-slate-800 dark:bg-slate-900/40">
        <div>
          <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Pinned Location & Address
          </div>
          <div className="mt-1 font-medium text-slate-800 break-words dark:text-slate-200">
            {formattedAddress || (
              <span className="italic text-slate-400">
                Search an address above or click anywhere on the map to pin a location.
              </span>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
      <label className="mb-1 block text-[11px] font-semibold text-slate-700 dark:text-slate-300">{label}</label>
      <input
        type={type}
        step={step}
        min={min}
        max={max}
        placeholder={placeholder}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 shadow-sm outline-none placeholder:text-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-950 dark:text-white disabled:cursor-not-allowed disabled:opacity-60"
      />
      {error ? <p className="mt-1 text-[11px] text-rose-500">{error}</p> : null}
    </div>
  );
}
