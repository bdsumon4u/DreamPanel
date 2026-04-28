<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $hidden = [
        // 'password',
        // 'token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'token' => 'encrypted',
            'ftp_port' => 'integer',
            'ssh_port' => 'integer',
        ];
    }

    public function hostings(): HasMany
    {
        return $this->hasMany(Hosting::class);
    }
}
