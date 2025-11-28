<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Calendar-specific configuration, including prototype feature flags.
 */
class Calendar extends BaseConfig
{
    /**
     * When true, prototype assets/endpoints can be exposed for QA users.
     */
    public bool $prototypeEnabled;

    /**
     * Shared identifier used by telemetry + frontend guards.
     */
    public string $prototypeFeatureKey = 'calendar_prototype';

    /**
     * Ensure env overrides are respected without touching repo defaults.
     */
    public function __construct()
    {
        parent::__construct();
        $this->prototypeEnabled = (bool) env('calendar.prototype_enabled', false);
    }
}
