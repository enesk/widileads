<?php

declare(strict_types=1);

namespace App\Models;

use App\Constants\LeadExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Ein angeforderter Lead-Export (FB-073).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int|null $requested_by
 * @property LeadExportStatus $status
 * @property list<string> $columns
 * @property array<string, mixed>|null $filters
 * @property int|null $row_count
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $failure_reason
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read User|null $requester
 */
class LeadExport extends Model
{
    protected $fillable = [
        'tenant_id',
        'requested_by',
        'columns',
        'filters',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === LeadExportStatus::READY
            && $this->disk !== null
            && $this->path !== null;
    }

    public function fileName(): string
    {
        return 'leads-'.($this->created_at?->format('Y-m-d-His') ?? 'export').'.csv';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadExportStatus::class,
            'columns' => 'array',
            'filters' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $export): void {
            if (($export->uuid ?? '') === '') {
                $export->uuid = (string) Str::uuid();
            }
        });
    }
}
