<?php

namespace Zchted\Affogato;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'log_id';
    protected $fillable = [
        'module',
        'action',
        'user_id',
        'data_before',
        'data_after'
    ];
    protected $guarded = [];
    protected $casts = [
        'log_id' => 'integer',
        'module' => 'string',
        'action' => 'string',
        'user_id' => 'integer',
        'data_before' => 'array',
        'data_after' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
