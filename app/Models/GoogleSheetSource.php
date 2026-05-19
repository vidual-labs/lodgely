<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single Google Sheet configured as a recurring lead source.
 *
 * column_map: {column_index_string: lead_field_key}
 *   e.g. {"0": "full_name", "1": "email", "2": "phone"}
 * Missing indices or null values mean "skip that column".
 *
 * Always resolve via GoogleSheetSource::forTenant() scopes so tenant
 * isolation is never accidentally bypassed.
 */
class GoogleSheetSource extends Model
{
    protected $table = 'google_sheet_sources';

    protected $fillable = [
        'tenant_id',
        'label',
        'spreadsheet_id',
        'sheet_range',
        'has_header_row',
        'column_map',
        'default_client_name',
        'default_campaign_name',
        'refresh_hours',
        'last_fetched_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_header_row'  => 'boolean',
            'column_map'      => 'array',
            'refresh_hours'   => 'integer',
            'last_fetched_at' => 'datetime',
            'is_active'       => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_fetched_at === null) {
            return true;
        }

        return $this->last_fetched_at->addHours($this->refresh_hours)->isPast();
    }

    /** Mappable lead field keys and their display labels. */
    public static function leadFields(): array
    {
        return [
            // Core contact
            'full_name'      => 'Full name',
            'email'          => 'Email',
            'phone'          => 'Phone',
            'message'        => 'Message',
            // Identity / source tracking
            'lead_id'        => 'Lead ID (external)',
            'form_id'        => 'Form ID',
            'created_time'   => 'Created time',
            // Assignment
            'client_name'    => 'Client name',
            'campaign_name'  => 'Campaign name',
            'source'         => 'Source',
            'platform'       => 'Platform',
            // Status & priority
            'status'         => 'Status (new/reviewed/incomplete/forwarded)',
            'priority'       => 'Priority (low/medium/high)',
            // Outreach toggles — any truthy value (yes/1/true) marks the outreach
            'is_qualified'   => 'Qualified (outreach)',
            'is_called'      => 'Called (outreach)',
            'is_mailed'      => 'Mailed (outreach)',
            // Extra flags stored in custom answers
            'is_quality'     => 'Is quality',
            'is_converted'   => 'Is converted',
            // Custom form answers (stored as custom_answers JSON)
            'question_01'    => 'Custom answer 1',
            'question_02'    => 'Custom answer 2',
            'question_03'    => 'Custom answer 3',
            'question_04'    => 'Custom answer 4',
            // UTM attribution (stored as custom_answers JSON)
            'utm_source'     => 'UTM source',
            'utm_medium'     => 'UTM medium',
            'utm_campaign'   => 'UTM campaign',
            'utm_content'    => 'UTM content',
            'utm_term'       => 'UTM term',
            // Named custom answer — operator supplies the key
            'custom_answer'  => 'Custom answer (named key)…',
        ];
    }
}
