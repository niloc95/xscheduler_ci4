<?php

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
