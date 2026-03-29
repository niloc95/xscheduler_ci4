<?php

/**
 * =============================================================================
 * LOCATIONS API CONTROLLER
 * =============================================================================
 * 
 * @file        app/Controllers/Api/Locations.php
 * @description API for managing provider business locations including
 *              addresses, operating hours, and service availability.
 * 
 * API ENDPOINTS:
 * -----------------------------------------------------------------------------
 * GET    /api/locations                   : List all locations
 * GET    /api/locations/:id               : Get location details
 * POST   /api/locations                   : Create new location
 * PUT    /api/locations/:id               : Update location
 * DELETE /api/locations/:id               : Delete location
 * GET    /api/locations/:id/providers     : Get providers at location
 * GET    /api/locations/for-date          : Available locations for provider+date
 * GET    /api/locations/available-dates   : Dates when a location is available
 * 
 * QUERY PARAMETERS (GET /api/locations):
 * -----------------------------------------------------------------------------
 * - provider_id  : Filter by provider
 * - with_days    : Include operating days (1/0)
 * - is_active    : Filter by active status
 * 
 * LOCATION STRUCTURE:
 * -----------------------------------------------------------------------------
 * {
 *   "id": 1,
 *   "provider_id": 2,
 *   "name": "Melrose Practice",
 *   "address": "21 Delta Rd, Eltonhill, JHB 2196",
 *   "contact_number": "+27 11 555 1234",
 *   "is_primary": true,
 *   "is_active": true,
 *   "days": [1, 2, 3]
 * }
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Supports multi-location providers:
 * - Define physical locations/branches
 * - Assign working days per location
 * - Working hours remain global to the provider
 * - Location-based booking flow
 * 
 * @see         app/Models/LocationModel.php for data layer
 * @see         app/Views/user-management/components/provider-locations.php for admin UI
 * @package     App\Controllers\Api
 * @extends     BaseApiController
 * @author      Nilesh Nagin Cara
 * @copyright   2024-2026 Nilesh Nagin Cara
 * =============================================================================
 */

namespace App\Controllers\Api;

use App\Models\LocationModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Location API Controller
 * 
 * Handles CRUD operations for provider locations.
 * Used by both admin interface and public booking flow.
 */
class Locations extends BaseApiController
{
    protected LocationModel $locationModel;

    public function __construct()
    {
        $this->locationModel = new LocationModel();
    }

    /**
     * GET /api/locations
     * 
     * List locations, optionally filtered by provider
     */
    public function index(): ResponseInterface
    {
        try {
            $providerId = $this->request->getGet('provider_id');
            $withDays = $this->request->getGet('with_days') === '1';
            
            if ($providerId) {
                $locations = $withDays 
                    ? $this->locationModel->getProviderLocationsWithDays((int) $providerId)
                    : $this->locationModel->getProviderLocations((int) $providerId);
            } else {
                $locations = $this->locationModel->where('is_active', 1)->findAll();
                
                if ($withDays) {
                    foreach ($locations as &$location) {
                        $location['days'] = $this->locationModel->getLocationDays($location['id']);
                    }
                }
            }
            
            return $this->ok($locations);
        } catch (\Throwable $e) {
            log_message('error', 'Location API error: ' . $e->getMessage());
            return $this->serverError('Failed to fetch locations', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/locations/:id
     * 
     * Get single location with working days
     */
    public function show(int $id): ResponseInterface
    {
        try {
            $location = $this->locationModel->getLocationWithDays($id);
            
            if (!$location) {
                return $this->notFound('Location not found', ['location_id' => $id]);
            }
            
            return $this->ok($location);
        } catch (\Throwable $e) {
            log_message('error', 'Location API error: ' . $e->getMessage());
            return $this->serverError('Failed to fetch location', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/locations
     * 
     * Create a new location for a provider
     */
    public function create(): ResponseInterface
    {
        try {
            $data = $this->request->getJSON(true) ?? $this->request->getPost();
            
            // Validate required fields — only provider_id and name are mandatory;
            // address and contact_number are optional on creation.
            $required = ['provider_id', 'name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->badRequest("Field '{$field}' is required", ['field' => $field]);
                }
            }

            // Default optional fields to empty strings
            $data['address'] = $data['address'] ?? '';
            $data['contact_number'] = $data['contact_number'] ?? '';

            // Enforce max locations per provider (Location A + Location B)
            $existingCount = count($this->locationModel->getProviderLocations((int) $data['provider_id']));
            if ($existingCount >= \App\Models\LocationModel::MAX_LOCATIONS_PER_PROVIDER) {
                return $this->badRequest(
                    'Maximum of ' . \App\Models\LocationModel::MAX_LOCATIONS_PER_PROVIDER . ' locations per provider allowed',
                    ['provider_id' => (int) $data['provider_id']]
                );
            }
            
            $days = $data['days'] ?? [];
            unset($data['days']);
            
            // Ensure is_active is set
            $data['is_active'] = $data['is_active'] ?? 1;
            
            $locationId = $this->locationModel->createWithDays($data, $days);
            
            if (!$locationId) {
                return $this->validationError($this->locationModel->errors());
            }
            
            $location = $this->locationModel->getLocationWithDays($locationId);
            
            return $this->created($location, ['message' => 'Location created successfully']);
        } catch (\Throwable $e) {
            log_message('error', 'Location create error: ' . $e->getMessage());
            return $this->serverError('Failed to create location', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/locations/:id
     * 
     * Update an existing location
     */
    public function update(int $id): ResponseInterface
    {
        try {
            $existing = $this->locationModel->find($id);
            
            if (!$existing) {
                return $this->notFound('Location not found', ['location_id' => $id]);
            }
            
            $data = $this->request->getJSON(true) ?? $this->request->getPost();
            $days = $data['days'] ?? null;
            unset($data['days'], $data['id'], $data['provider_id']); // Don't allow changing provider
            
            if ($days !== null) {
                $this->locationModel->updateWithDays($id, $data, $days);
            } elseif (!empty($data)) {
                $this->locationModel->update($id, $data);
            }
            
            $location = $this->locationModel->getLocationWithDays($id);
            
            return $this->ok($location, ['message' => 'Location updated successfully']);
        } catch (\Throwable $e) {
            log_message('error', 'Location update error: ' . $e->getMessage());
            return $this->serverError('Failed to update location', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/locations/:id
     * 
     * Soft-delete a location (sets is_active = 0)
     */
    public function delete(int $id): ResponseInterface
    {
        try {
            $existing = $this->locationModel->find($id);
            
            if (!$existing) {
                return $this->notFound('Location not found', ['location_id' => $id]);
            }
            
            // Soft delete by setting is_active = 0
            $this->locationModel->update($id, ['is_active' => 0]);
            
            return $this->ok(['location_id' => $id], ['message' => 'Location deleted successfully']);
        } catch (\Throwable $e) {
            log_message('error', 'Location delete error: ' . $e->getMessage());
            return $this->serverError('Failed to delete location', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/locations/:id/set-primary
     * 
     * Set a location as the provider's primary location
     */
    public function setPrimary(int $id): ResponseInterface
    {
        try {
            $existing = $this->locationModel->find($id);
            
            if (!$existing) {
                return $this->notFound('Location not found', ['location_id' => $id]);
            }
            
            $this->locationModel->setPrimary($id);
            
            return $this->ok(['location_id' => $id], ['message' => 'Primary location updated']);
        } catch (\Throwable $e) {
            log_message('error', 'Location setPrimary error: ' . $e->getMessage());
            return $this->serverError('Failed to set primary location', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/locations/for-date
     * 
     * Get locations available for a specific provider and date
     * Used by public booking flow
     */
    public function forDate(): ResponseInterface
    {
        try {
            $providerId = $this->request->getGet('provider_id');
            $date = $this->request->getGet('date');
            
            if (!$providerId || !$date) {
                return $this->badRequest('provider_id and date are required', ['required' => ['provider_id', 'date']]);
            }
            
            $dayOfWeek = (int) date('w', strtotime($date));
            $locations = $this->locationModel->getLocationsForDay((int) $providerId, $dayOfWeek);
            
            // For public API, only return safe fields (no internal IDs for display)
            $publicLocations = array_map(function($loc) {
                return [
                    'id'             => $loc['id'],
                    'name'           => $loc['name'],
                    'address'        => $loc['address'],
                    'contact_number' => $loc['contact_number'],
                    'is_primary'     => (bool) $loc['is_primary'],
                ];
            }, $locations);
            
            return $this->ok($publicLocations);
        } catch (\Throwable $e) {
            log_message('error', 'Location forDate error: ' . $e->getMessage());
            return $this->serverError('Failed to fetch locations for date', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/locations/available-dates
     * 
     * Get dates when a specific location is available
     * Used by public booking calendar
     */
    public function availableDates(): ResponseInterface
    {
        try {
            $locationId = $this->request->getGet('location_id');
            $startDate = $this->request->getGet('start_date') ?? date('Y-m-d');
            $endDate = $this->request->getGet('end_date') ?? date('Y-m-d', strtotime('+30 days'));
            
            if (!$locationId) {
                return $this->badRequest('location_id is required', ['required' => ['location_id']]);
            }
            
            $dates = $this->locationModel->getAvailableDates((int) $locationId, $startDate, $endDate);
            
            return $this->ok($dates);
        } catch (\Throwable $e) {
            log_message('error', 'Location availableDates error: ' . $e->getMessage());
            return $this->serverError('Failed to fetch available dates', ['exception' => $e->getMessage()]);
        }
    }
}
