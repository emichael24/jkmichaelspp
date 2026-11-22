<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmOrganization extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function contacts()
    {
        return $this->hasMany(CrmContact::class, 'organization_id');
    }

    public function deals()
    {
        return $this->hasMany(CrmDeal::class, 'organization_id');
    }
}
