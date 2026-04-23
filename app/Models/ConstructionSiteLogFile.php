<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionSiteLogFile extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'construction_site_log_id',
        'company_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function constructionSiteLog(): BelongsTo
    {
        return $this->belongsTo(ConstructionSiteLog::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
