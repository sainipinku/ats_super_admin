<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Google Maps API key from config
     */
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key', '');
    }

    /**
     * Geocode an address string to latitude/longitude coordinates.
     *
     * @param string $address
     * @return array|null ['latitude' => float, 'longitude' => float] or null on failure
     */
    public function geocode(string $address): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('GeocodingService: Google Maps API key not configured.');
            return null;
        }

        if (empty(trim($address))) {
            return null;
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key' => $this->apiKey,
                'region' => 'in',
                'language' => 'en',
            ]);

            if (!$response->successful()) {
                Log::warning('GeocodingService: API request failed.', [
                    'status' => $response->status(),
                    'address' => $address,
                ]);
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]['geometry']['location'])) {
                Log::warning('GeocodingService: No results found.', [
                    'status' => $data['status'] ?? 'UNKNOWN',
                    'address' => $address,
                ]);
                return null;
            }

            $location = $data['results'][0]['geometry']['location'];

            return [
                'latitude' => (float) $location['lat'],
                'longitude' => (float) $location['lng'],
            ];
        } catch (\Exception $e) {
            Log::error('GeocodingService: Exception occurred.', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);
            return null;
        }
    }

    /**
     * Reverse geocode latitude/longitude to an address string.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string|null
     */
    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning('GeocodingService: Google Maps API key not configured.');
            return null;
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$latitude},{$longitude}",
                'key' => $this->apiKey,
                'language' => 'en',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0]['formatted_address'])) {
                return null;
            }

            return $data['results'][0]['formatted_address'];
        } catch (\Exception $e) {
            Log::error('GeocodingService: Reverse geocode exception.', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calculate distance between two coordinates using the Haversine formula.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @param string $unit 'km' or 'mi'
     * @return float Distance in the specified unit
     */
    public static function haversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
        string $unit = 'km'
    ): float {
        $earthRadius = $unit === 'mi' ? 3959 : 6371;

        $lat1Rad = deg2rad($lat1);
        $lng1Rad = deg2rad($lng1);
        $lat2Rad = deg2rad($lat2);
        $lng2Rad = deg2rad($lng2);

        $dlat = $lat2Rad - $lat1Rad;
        $dlng = $lng2Rad - $lng1Rad;

        $a = sin($dlat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($dlng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Build a MySQL haversine SELECT snippet for distance calculation.
     *
     * @param float $latitude
     * @param float $longitude
     * @param string $latColumn
     * @param string $lngColumn
     * @return string SQL snippet
     */
    public static function haversineSql(
        float $latitude,
        float $longitude,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string {
        $lat = (float) $latitude;
        $lng = (float) $longitude;

        return "(6371 * acos(cos(radians({$lat})) * cos(radians({$latColumn})) * cos(radians({$lngColumn}) - radians({$lng})) + sin(radians({$lat})) * sin(radians({$latColumn}))))";
    }
}