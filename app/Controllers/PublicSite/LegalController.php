<?php

namespace App\Controllers\PublicSite;

use App\Controllers\BaseController;
use App\Models\SettingModel;

class LegalController extends BaseController
{
    public function index()
    {
        $settingModel = new SettingModel();
        $legal = $settingModel->getByPrefix('legal.');

        $data = [
            'title' => 'Legal',
            'cookieNotice' => (string) ($legal['legal.cookie_notice'] ?? ''),
            'terms' => (string) ($legal['legal.terms'] ?? ''),
            'privacy' => (string) ($legal['legal.privacy'] ?? ''),
            'cancellationPolicy' => (string) ($legal['legal.cancellation_policy'] ?? ''),
            'reschedulingPolicy' => (string) ($legal['legal.rescheduling_policy'] ?? ''),
            'termsUrl' => (string) ($legal['legal.terms_url'] ?? ''),
            'privacyUrl' => (string) ($legal['legal.privacy_url'] ?? ''),
        ];

        return view('public/legal', $data);
    }
}
