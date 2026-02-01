<?php

/**
 * =============================================================================
 * V1 BASE API CONTROLLER (DEPRECATED)
 * =============================================================================
 * 
 * @file        app/Controllers/Api/V1/BaseApiController.php
 * @description Versioned base controller for V1 API namespace. Extends main
 *              BaseApiController for backward compatibility with older clients.
 * 
 * NO ROUTES - Abstract class extended by V1 API controllers
 * 
 * PURPOSE:
 * -----------------------------------------------------------------------------
 * Provides API versioning support:
 * - V1 controllers extend this for namespace clarity
 * - Inherits all methods from main BaseApiController
 * - Allows for version-specific overrides if needed
 * 
 * DEPRECATION NOTICE:
 * -----------------------------------------------------------------------------
 * ⚠️ This class is maintained for backward compatibility only.
 * New API development should use App\Controllers\Api\BaseApiController directly.
 * 
 * VERSION STRATEGY:
 * -----------------------------------------------------------------------------
 * - /api/v1/* routes use V1 namespace controllers
 * - /api/* routes use main Api namespace (latest)
 * - V1 maintained for existing integrations
 * 
 * @deprecated Use App\Controllers\Api\BaseApiController directly
 * @see         app/Controllers/Api/BaseApiController.php
 * @package     App\Controllers\Api\V1
 * @extends     BaseApiController
 * @author      WebSchedulr Team
 * @copyright   2024-2026 WebSchedulr
 * =============================================================================
 */

namespace App\Controllers\Api\V1;

use App\Controllers\Api\BaseApiController as ApiBaseController;

/**
 * V1 Base API Controller
 * 
 * Extends the main BaseApiController for backward compatibility.
 * New V1 controllers should extend this class.
 * 
 * @deprecated Use App\Controllers\Api\BaseApiController directly
 * @package WebSchedulr
 * @since 2.0.0
 */
class BaseApiController extends ApiBaseController
{
    // All methods inherited from parent
}
