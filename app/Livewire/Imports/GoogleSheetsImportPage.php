<?php

namespace App\Livewire\Imports;

use App\Domain\Leads\Services\ImportRunner;
use App\Importers\GoogleSheets\GoogleSheetsClient;
use App\Importers\GoogleSheets\GoogleSheetsLeadSource;
use App\Models\GoogleSheetSource;
use App\Models\Import;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
class GoogleSheetsImportPage extends Component
{
    public string $mode = 'list'; // 'list' | 'form'

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [
        'label'                => '',
        'spreadsheet_id'       => '',
        'sheet_range'          => 'Sheet1',
        'has_header_row'       => true,
        'default_client_name'  => '',
        'default_campaign_name' => '',
        'refresh_hours'        => 24,
        'is_active'            => true,
    ];

    public bool $columnsLoaded = false;

    /**
     * Each element: ['index' => int, 'display' => string, 'field' => string]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $detectedColumns = [];

    public ?string $loadError = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->reset(['editingId', 'columnsLoaded', 'detectedColumns', 'loadError']);
        $this->form = [
            'label'                => '',
            'spreadsheet_id'       => '',
            'sheet_range'          => 'Sheet1',
            'has_header_row'       => true,
            'default_client_name'  => '',
            'default_campaign_name' => '',
            'refresh_hours'        => 24,
            'is_active'            => true,
        ];
        $this->mode = 'form';
    }

    public function editSource(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $source = GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);

        $this->editingId = $id;
        $this->form = [
            'label'                => $source->label,
            'spreadsheet_id'       => $source->spreadsheet_id,
            'sheet_range'          => $source->sheet_range,
            'has_header_row'       => (bool) $source->has_header_row,
            'default_client_name'  => (string) ($source->default_client_name ?? ''),
            'default_campaign_name' => (string) ($source->default_campaign_name ?? ''),
            'refresh_hours'        => $source->refresh_hours,
            'is_active'            => (bool) $source->is_active,
        ];

        // Restore saved column mapping as detectedColumns for display.
        $map = is_array($source->column_map) ? $source->column_map : [];
        $this->detectedColumns = [];
        foreach ($map as $indexStr => $field) {
            $this->detectedColumns[] = [
                'index'   => (int) $indexStr,
                'display' => 'Column '.($this->indexToLetter((int) $indexStr)),
                'field'   => (string) ($field ?? ''),
            ];
        }
        $this->columnsLoaded = ! empty($this->detectedColumns);
        $this->loadError = null;
        $this->mode = 'form';
    }

    public function loadColumns(GoogleSheetsClient $client): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $this->loadError = null;
        $this->columnsLoaded = false;
        $this->detectedColumns = [];

        $spreadsheetId = trim((string) ($this->form['spreadsheet_id'] ?? ''));
        $range = trim((string) ($this->form['sheet_range'] ?? 'Sheet1'));
        $hasHeader = (bool) ($this->form['has_header_row'] ?? true);

        if ($spreadsheetId === '') {
            $this->loadError = __('Enter a spreadsheet ID before loading columns.');
            return;
        }

        try {
            // Fetch only the first 2 rows to detect columns cheaply.
            $limitedRange = $this->rangeWithRowLimit($range, 2);
            $rows = $client->fetchValues($spreadsheetId, $limitedRange);
        } catch (Throwable $e) {
            $this->loadError = $e->getMessage();
            return;
        }

        if (empty($rows)) {
            $this->loadError = __('The sheet returned no data. Check the spreadsheet ID and range.');
            return;
        }

        $headerRow = $rows[0] ?? [];
        $existingMap = [];
        if ($this->editingId) {
            $s = GoogleSheetSource::find($this->editingId);
            $existingMap = is_array($s?->column_map) ? $s->column_map : [];
        }

        $this->detectedColumns = [];
        foreach ($headerRow as $i => $cell) {
            $display = $hasHeader
                ? (string) ($cell !== '' && $cell !== null ? $cell : 'Column '.$this->indexToLetter($i))
                : 'Column '.$this->indexToLetter($i);

            $this->detectedColumns[] = [
                'index'   => $i,
                'display' => $display,
                'field'   => (string) ($existingMap[(string) $i] ?? ''),
            ];
        }

        $this->columnsLoaded = true;
    }

    public function saveSource(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $data = $this->validate([
            'form.label'                => ['required', 'string', 'max:120'],
            'form.spreadsheet_id'       => ['required', 'string', 'max:255'],
            'form.sheet_range'          => ['required', 'string', 'max:120'],
            'form.has_header_row'       => ['boolean'],
            'form.default_client_name'  => ['nullable', 'string', 'max:120'],
            'form.default_campaign_name' => ['nullable', 'string', 'max:120'],
            'form.refresh_hours'        => ['required', 'integer', 'min:1', 'max:8760'],
            'form.is_active'            => ['boolean'],
        ])['form'];

        // Build column_map from detectedColumns.
        $columnMap = [];
        foreach ($this->detectedColumns as $col) {
            $field = trim((string) ($col['field'] ?? ''));
            if ($field !== '') {
                $columnMap[(string) $col['index']] = $field;
            }
        }

        $payload = [
            'tenant_id'             => Tenant::DEFAULT_ID,
            'label'                 => $data['label'],
            'spreadsheet_id'        => $data['spreadsheet_id'],
            'sheet_range'           => $data['sheet_range'],
            'has_header_row'        => (bool) $data['has_header_row'],
            'column_map'            => $columnMap ?: null,
            'default_client_name'   => $data['default_client_name'] ?: null,
            'default_campaign_name' => $data['default_campaign_name'] ?: null,
            'refresh_hours'         => (int) $data['refresh_hours'],
            'is_active'             => (bool) $data['is_active'],
        ];

        if ($this->editingId) {
            GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)
                ->findOrFail($this->editingId)
                ->update($payload);
        } else {
            GoogleSheetSource::create($payload);
        }

        $this->mode = 'list';
        $this->dispatch('toast', message: __('Sheet source saved.'), type: 'success');
    }

    public function deleteSource(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id)->delete();
        $this->dispatch('toast', message: __('Sheet source deleted.'), type: 'success');
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $source = GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
    }

    public function fetchNow(int $id, ImportRunner $runner, GoogleSheetsLeadSource $source): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);

        $sheetSource = GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)->findOrFail($id);

        $import = Import::create([
            'tenant_id' => Tenant::DEFAULT_ID,
            'user_id'   => auth()->id(),
            'source'    => $source->key(),
            'label'     => $sheetSource->label.' · '.now()->format('Y-m-d H:i'),
            'meta'      => ['sheet_source_id' => $sheetSource->id],
        ]);

        try {
            $result = $runner->run($import, $source);
            $sheetSource->update(['last_fetched_at' => now()]);

            $this->dispatch('toast', message: __(
                'Fetched: :imported imported, :dup duplicates, :inv invalid.',
                [
                    'imported' => $result->rows_imported,
                    'dup'      => $result->rows_duplicate,
                    'inv'      => $result->rows_invalid,
                ]
            ), type: 'success');
        } catch (Throwable $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function backToList(): void
    {
        $this->mode = 'list';
        $this->reset(['editingId', 'columnsLoaded', 'detectedColumns', 'loadError']);
    }

    public function render(): View
    {
        $sources = GoogleSheetSource::forTenant(Tenant::DEFAULT_ID)
            ->latest()
            ->get();

        $recentImports = Import::where('source', 'google_sheets')
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.imports.google-sheets-import-page', [
            'sources'       => $sources,
            'recentImports' => $recentImports,
            'leadFields'    => GoogleSheetSource::leadFields(),
            'refreshOptions' => [
                1 => __('Every hour'),
                6 => __('Every 6 hours'),
                12 => __('Every 12 hours'),
                24 => __('Every 24 hours'),
                48 => __('Every 2 days'),
                168 => __('Weekly'),
            ],
        ]);
    }

    private function indexToLetter(int $i): string
    {
        $letter = '';
        $i++;
        while ($i > 0) {
            $remainder = ($i - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $i = (int) (($i - 1) / 26);
        }

        return $letter;
    }

    /**
     * Constrain the row dimension of a Sheets range to at most $maxRows rows,
     * so column-loading doesn't pull thousands of rows on first contact.
     */
    private function rangeWithRowLimit(string $range, int $maxRows): string
    {
        // If no row numbers present (e.g. "Sheet1" or "Sheet1!A:Z"), append row limit.
        if (! preg_match('/\d/', $range)) {
            // Remove trailing unbounded col like "A:Z" → "A1:Z{max}"
            if (preg_match('/^(.+!)?([A-Za-z]+):([A-Za-z]+)$/', $range, $m)) {
                return $m[1].$m[2].'1:'.$m[3].$maxRows;
            }

            return $range.'!A1:ZZ'.$maxRows;
        }

        return $range;
    }
}
