<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * LocationModel - Provider Multi-Location Support
 * 
 * Manages provider locations with:
 * - Friendly name (primary identifier)
 * - Full physical address
 * - Contact number
 * - Working days assignment
 * 
 * Working hours remain global to the provider across all locations.
 */
class LocationModel extends BaseModel
{
    protected $table            = 'xs_locations';
    protected $primaryKey       = 'id';
    protected $allowedFields    = [
        'provider_id',
        'name',
        'address',
        'contact_number',
        'is_primary',
        'is_active',
    ];

    protected $validationRules = [
        'provider_id'    => 'required|integer',
        'name'           => 'required|min_length[2]|max_length[255]',
        'address'        => 'required|min_length[5]',
        'contact_number' => 'required|min_length[5]|max_length[50]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Location name is required',
        ],
        'address' => [
            'required' => 'Physical address is required',
        ],
        'contact_number' => [
            'required' => 'Contact number is required',
        ],
    ];

    /**
     * Get all active locations for a provider
     */
    public function getProviderLocations(int $providerId, bool $activeOnly = true): array
    {
        $builder = $this->where('provider_id', $providerId);
        
        if ($activeOnly) {
            $builder->where('is_active', 1);
        }
        
        return $builder->orderBy('is_primary', 'DESC')
                       ->orderBy('name', 'ASC')
                       ->findAll();
    }

    /**
     * Get a location with its working days
     */
    public function getLocationWithDays(int $locationId): ?array
    {
        $location = $this->find($locationId);
        
        if (!$location) {
            return null;
        }
        
        $location['days'] = $this->getLocationDays($locationId);
        
        return $location;
    }

    /**
     * Get working days for a location
     * Returns array of day numbers (0=Sunday, 1=Monday, etc.)
     */
    public function getLocationDays(int $locationId): array
    {
        $db = \Config\Database::connect();
        $result = $db->table('xs_location_days')
                     ->where('location_id', $locationId)
                     ->get()
                     ->getResultArray();
        
        return array_column($result, 'day_of_week');
    }

    /**
     * Set working days for a location
     * 
     * @param int $locationId
     * @param array $days Array of day numbers (0-6)
     */
    public function setLocationDays(int $locationId, array $days): bool
    {
        $db = \Config\Database::connect();
        
        // Remove existing days
        $db->table('xs_location_days')
           ->where('location_id', $locationId)
           ->delete();
        
        // Insert new days
        if (!empty($days)) {
            $insertData = [];
            foreach ($days as $day) {
                $day = (int) $day;
                if ($day >= 0 && $day <= 6) {
                    $insertData[] = [
                        'location_id'  => $locationId,
                        'day_of_week'  => $day,
                    ];
                }
            }
            
            if (!empty($insertData)) {
                $db->table('xs_location_days')->insertBatch($insertData);
            }
        }
        
        return true;
    }

    /**
     * Get all locations for a provider with their working days
     */
    public function getProviderLocationsWithDays(int $providerId): array
    {
        $locations = $this->getProviderLocations($providerId);
        
        foreach ($locations as &$location) {
            $location['days'] = $this->getLocationDays($location['id']);
        }
        
        return $locations;
    }

    /**
     * Get location(s) available for a specific day of week
     * 
     * @param int $providerId
     * @param int $dayOfWeek 0=Sunday, 1=Monday, etc.
     * @return array
     */
    public function getLocationsForDay(int $providerId, int $dayOfWeek): array
    {
        $db = \Config\Database::connect();
        
        return $db->table('xs_locations l')
                  ->select('l.*')
                  ->join('xs_location_days ld', 'ld.location_id = l.id')
                  ->where('l.provider_id', $providerId)
                  ->where('l.is_active', 1)
                  ->where('ld.day_of_week', $dayOfWeek)
                  ->orderBy('l.is_primary', 'DESC')
                  ->orderBy('l.name', 'ASC')
                  ->get()
                  ->getResultArray();
    }

    /**
     * Set a location as primary (unsets other primary locations for same provider)
     */
    public function setPrimary(int $locationId): bool
    {
        $location = $this->find($locationId);
        
        if (!$location) {
            return false;
        }
        
        // Unset existing primary
        $this->where('provider_id', $location['provider_id'])
             ->set('is_primary', 0)
             ->update();
        
        // Set new primary
        return $this->update($locationId, ['is_primary' => 1]);
    }

    /**
     * Get provider's primary location
     */
    public function getPrimaryLocation(int $providerId): ?array
    {
        return $this->where('provider_id', $providerId)
                    ->where('is_primary', 1)
                    ->where('is_active', 1)
                    ->first();
    }

    /**
     * Check if a provider has any locations configured
     */
    public function hasLocations(int $providerId): bool
    {
        return $this->where('provider_id', $providerId)
                    ->where('is_active', 1)
                    ->countAllResults() > 0;
    }

    /**
     * Get location data formatted for appointment snapshot
     */
    public function getLocationSnapshot(int $locationId): array
    {
        $location = $this->find($locationId);
        
        if (!$location) {
            return [
                'location_id'      => null,
                'location_name'    => null,
                'location_address' => null,
                'location_contact' => null,
            ];
        }
        
        return [
            'location_id'      => $location['id'],
            'location_name'    => $location['name'],
            'location_address' => $location['address'],
            'location_contact' => $location['contact_number'],
        ];
    }

    /**
     * Create location with days in a single transaction
     */
    public function createWithDays(array $data, array $days): int|false
    {
        $db = \Config\Database::connect();
        $db->transStart();
        
        $locationId = $this->insert($data);
        
        if ($locationId && !empty($days)) {
            $this->setLocationDays($locationId, $days);
        }
        
        // If this is the first location, make it primary
        $existingCount = $this->where('provider_id', $data['provider_id'])
                              ->where('id !=', $locationId)
                              ->countAllResults();
        
        if ($existingCount === 0) {
            $this->update($locationId, ['is_primary' => 1]);
        }
        
        $db->transComplete();
        
        return $db->transStatus() ? $locationId : false;
    }

    /**
     * Update location with days in a single transaction
     */
    public function updateWithDays(int $locationId, array $data, array $days): bool
    {
        $db = \Config\Database::connect();
        $db->transStart();
        
        $this->update($locationId, $data);
        $this->setLocationDays($locationId, $days);
        
        $db->transComplete();
        
        return $db->transStatus();
    }

    /**
     * Get available dates for a location in a date range
     * Returns dates that match the location's working days
     */
    public function getAvailableDates(int $locationId, string $startDate, string $endDate): array
    {
        $days = $this->getLocationDays($locationId);
        
        if (empty($days)) {
            return [];
        }
        
        $availableDates = [];
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        while ($current <= $end) {
            $dayOfWeek = (int) $current->format('w'); // 0=Sunday
            
            if (in_array($dayOfWeek, $days)) {
                $availableDates[] = $current->format('Y-m-d');
            }
            
            $current->modify('+1 day');
        }
        
        return $availableDates;
    }
}
