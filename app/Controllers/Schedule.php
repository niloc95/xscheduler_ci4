<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ServiceModel;
use App\Models\UserModel;

class Schedule extends BaseController
{
    public function index()
    {
        $services = (new ServiceModel())->orderBy('name','ASC')->findAll();
        $providers = (new UserModel())->getProviders();
        return view('schedule/index', [
            'services' => $services,
            'providers' => $providers,
        ]);
    }
}
