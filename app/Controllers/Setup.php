<?php

namespace App\Controllers;

class Setup extends BaseController
{
    public function setup(): string
    {
        return view('setup');
    }
  
}
