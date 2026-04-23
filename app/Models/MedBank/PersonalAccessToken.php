<?php

namespace App\Models\MedBank;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    protected $connection = 'mysqlHyggeRBH';
}
