<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\LocationModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Location API Controller
 * 
 * Handles CRUD operations for provider locations.
 * Used by both admin interface and public booking flow.
 */
class Locations extends BaseController
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
            
            return $this->response->setJSON([
                'status' => 'ok',
                'data'   => $locations,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location API error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to fetch locations',
            ]);
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
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Location not found',
                ]);
            }
            
            return $this->response->setJSON([
                'status' => 'ok',
                'data'   => $location,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location API error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to fetch location',
            ]);
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
            
            // Validate required fields
            $required = ['provider_id', 'name', 'address', 'contact_number'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status'  => 'error',
                        'message' => "Field '{$field}' is required",
                    ]);
                }
            }
            
            $days = $data['days'] ?? [];
            unset($data['days']);
            
            // Ensure is_active is set
            $data['is_active'] = $data['is_active'] ?? 1;
            
            $locationId = $this->locationModel->createWithDays($data, $days);
            
            if (!$locationId) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'Failed to create location',
                    'errors'  => $this->locationModel->errors(),
                ]);
            }
            
            $location = $this->locationModel->getLocationWithDays($locationId);
            
            return $this->response->setStatusCode(201)->setJSON([
                'status'  => 'ok',
                'message' => 'Location created successfully',
                'data'    => $location,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location create error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to create location',
            ]);
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
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Location not found',
                ]);
            }
            
            $data = $this->request->getJSON(true) ?? $this->request->getPost();
            $days = $data['days'] ?? null;
            unset($data['days'], $data['id'], $data['provider_id']); // Don't allow changing provider
            
            if ($days !== null) {
                $this->locationModel->updateWithDays($id, $data, $days);
            } else {
                $this->locationModel->update($id, $data);
            }
            
            $location = $this->locationModel->getLocationWithDays($id);
            
            return $this->response->setJSON([
                'status'  => 'ok',
                'message' => 'Location updated successfully',
                'data'    => $location,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location update error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to update location',
            ]);
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
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Location not found',
                ]);
            }
            
            // Soft delete by setting is_active = 0
            $this->locationModel->update($id, ['is_active' => 0]);
            
            return $this->response->setJSON([
                'status'  => 'ok',
                'message' => 'Location deleted successfully',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location delete error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to delete location',
            ]);
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
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Location not found',
                ]);
            }
            
            $this->locationModel->setPrimary($id);
            
            return $this->response->setJSON([
                'status'  => 'ok',
                'message' => 'Primary location updated',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location setPrimary error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to set primary location',
            ]);
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
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'provider_id and date are required',
                ]);
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
            
            return $this->response->setJSON([
                'status' => 'ok',
                'data'   => $publicLocations,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location forDate error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to fetch locations for date',
            ]);
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
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'location_id is required',
                ]);
            }
            
            $dates = $this->locationModel->getAvailableDates((int) $locationId, $startDate, $endDate);
            
            return $this->response->setJSON([
                'status' => 'ok',
                'data'   => $dates,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Location availableDates error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to fetch available dates',
            ]);
        }
    }
}
