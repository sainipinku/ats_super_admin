import React, { useState, useRef, useEffect } from 'react';

const LocationInput = ({ value, onChange, placeholder = "Enter location...", variant = "default" }) => {
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const inputRef = useRef(null);

    // Google Maps API key from environment
    const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';

    // Load Google Maps Places API
    const getLocationSuggestions = async (input) => {
        if (input.length < 2) {
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }

        if (!GOOGLE_MAPS_API_KEY) {
            console.error('Google Maps API key not found');
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }

        try {
            // Google Maps Places API - Autocomplete with locality focus
            const response = await fetch(
                `https://maps.googleapis.com/maps/api/place/autocomplete/json?input=${encodeURIComponent(input)}&key=${GOOGLE_MAPS_API_KEY}&types=(cities)|(establishment)|(geocode)&components=country:in&language=en&limit=5`,
                {
                    headers: {
                        'Accept': 'application/json',
                    }
                }
            );

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'OK' && data.predictions) {
                    const formattedSuggestions = data.predictions.map(prediction => {
                        const parts = prediction.description.split(',').map(s => s.trim());
                        const locality = parts[0] || '';
                        const city = parts[1] || '';
                        const state = parts[2] || '';
                        return {
                            display_name: prediction.description,
                            place_id: prediction.place_id,
                            type: prediction.types?.[0] || 'location',
                            structured_formatting: prediction.structured_formatting,
                            locality,
                            city,
                            state
                        };
                    });
                    setSuggestions(formattedSuggestions);
                    setShowSuggestions(true);
                } else {
                    console.error('Google Maps API error:', data.status, data.error_message);
                    setSuggestions([]);
                    setShowSuggestions(false);
                }
            } else {
                console.error('Google Maps API HTTP error');
                setSuggestions([]);
                setShowSuggestions(false);
            }
        } catch (error) {
            console.error('Error fetching location suggestions:', error);
            setSuggestions([]);
            setShowSuggestions(false);
        }
    };

    const handleInputChange = (e) => {
        const inputValue = e.target.value;
        onChange(inputValue);
        getLocationSuggestions(inputValue);
    };

    const handleSuggestionClick = (suggestion) => {
        onChange(suggestion.display_name);
        setSuggestions([]);
        setShowSuggestions(false);
    };

    const handleBlur = () => {
        setTimeout(() => {
            setShowSuggestions(false);
        }, 200);
    };

    const handleFocus = () => {
        // Don't show suggestions on focus, only on typing
        if (value && value.length >= 2) {
            getLocationSuggestions(value);
        }
    };

    const handleGetCurrentLocation = () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const location = `${position.coords.latitude.toFixed(4)}, ${position.coords.longitude.toFixed(4)}`;
                    onChange(location);
                    setSuggestions([]);
                    setShowSuggestions(false);
                },
                (error) => {
                    console.error('Error getting location:', error);
                }
            );
        } else {
            alert('Geolocation is not supported by your browser');
        }
    };

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (inputRef.current && !inputRef.current.contains(event.target)) {
                setShowSuggestions(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    return (
        <div className="relative" ref={inputRef}>
            <div className="relative">
                <input
                    type="text"
                    value={value}
                    onChange={handleInputChange}
                    onFocus={handleFocus}
                    onBlur={handleBlur}
                    placeholder={placeholder}
                    className={`w-full outline-none ${
                        variant === 'pill'
                            ? 'bg-transparent px-0 py-0 text-slate-700 placeholder-slate-400 pr-0'
                            : variant === 'hero'
                                ? 'bg-transparent px-0 py-0 text-inherit placeholder-gray-400 pr-8'
                                : variant === 'transparent' 
                                    ? 'bg-transparent px-0 py-0 text-inherit placeholder-gray-500 pr-12' 
                                    : 'px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white pr-12'
                    }`}
                />
                
                {/* Current Location Button - Hidden in hero and pill variants */}
                {variant !== 'hero' && variant !== 'pill' && (
                    <button
                        type="button"
                        onClick={handleGetCurrentLocation}
                        className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-blue-500 transition-colors"
                        title="Use current location"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                )}
            </div>

            {/* Suggestions Dropdown */}
            {showSuggestions && suggestions.length > 0 && (
                <div className="absolute z-[9999] left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-2xl max-h-60 overflow-y-auto backdrop-blur-sm min-w-[280px]">
                    <div className="py-1">
                        {suggestions.map((suggestion, index) => (
                            <button
                                key={index}
                                type="button"
                                onClick={() => handleSuggestionClick(suggestion)}
                                className="w-full px-4 py-3 text-left hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                            >
                                <svg className="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            <div className="flex-1 min-w-0">
                                <span className="text-sm text-gray-700 dark:text-gray-300 block truncate">
                                    {suggestion.locality}
                                </span>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {suggestion.city}{suggestion.city && suggestion.state ? ', ' : ''}{suggestion.state}
                                </span>
                            </div>
                            </button>
                        ))}
                    </div>
                    <div className="px-4 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Powered by Google Maps
                            </p>
                    </div>
                </div>
            )}
        </div>
    );
};

export default LocationInput;
