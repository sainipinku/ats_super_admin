import React, { useState, useRef, useEffect } from 'react';

const LocationInput = ({ value, onChange, placeholder = "Enter location...", variant = "default" }) => {
    const [suggestions, setSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const inputRef = useRef(null);

    // Custom India locations (commented out for free API)
    /*
    const commonLocations = [
        'Mumbai, Maharashtra', 'Pune, Maharashtra', 'Bangalore, Karnataka',
        // ... 200+ more locations
    ];
    */

    // Load OpenStreetMap Nominatim API (free)
    const getFreeLocationSuggestions = async (input) => {
        if (input.length < 2) {
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }

        try {
            // OpenStreetMap Nominatim API - completely free
            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(input)}&countrycodes=in&limit=5&addressdetails=1`,
                {
                    headers: {
                        'User-Agent': 'ATSLocationApp/1.0' // Required for Nominatim
                    }
                }
            );

            if (response.ok) {
                const data = await response.json();
                const formattedSuggestions = data.map(place => ({
                    display_name: place.display_name,
                    lat: place.lat,
                    lon: place.lon,
                    type: place.type
                }));
                setSuggestions(formattedSuggestions);
                setShowSuggestions(true);
            } else {
                console.error('Nominatim API error');
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
        getFreeLocationSuggestions(inputValue);
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
        if (value.length > 1) {
            getFreeLocationSuggestions(value);
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
                <div className="absolute z-[100] left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-2xl max-h-60 overflow-y-auto">
                    <ul className="py-2">
                        {suggestions.map((suggestion, index) => (
                            <li key={index}>
                                <button
                                    type="button"
                                    onClick={() => handleSuggestionClick(suggestion)}
                                    className="w-full px-4 py-3 text-left hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-3"
                                >
                                    <svg className="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span className="text-sm text-gray-700 dark:text-gray-300">
                                        {suggestion.display_name}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
};

export default LocationInput;
