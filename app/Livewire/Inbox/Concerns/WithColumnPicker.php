<?php

namespace App\Livewire\Inbox\Concerns;

use App\Models\Lead;

/**
 * Per-user picked column set for the inbox table.
 *
 * Two dimensions:
 *  - "columns": stable picks from {@see self::AVAILABLE_COLUMNS}.
 *  - "questions": custom-question column heads discovered from
 *    lead.custom_answers. Each picked question becomes its own column;
 *    the cell renders the lead's answer to that exact question (or "—").
 *
 * Persisted to users.inbox_columns (JSONB). Null = role-based default.
 */
trait WithColumnPicker
{
    public bool $showColumnPicker = false;

    /** @var list<string> Picked static-column keys. */
    public array $pickedColumns = [];

    /** @var list<string> Picked custom-question column heads (raw question strings). */
    public array $pickedQuestions = [];

    /**
     * All static columns available to the picker, in display order.
     * "received" is always rendered as the anchor column and is not pickable.
     */
    public const AVAILABLE_COLUMNS = [
        'name', 'email', 'phone',
        'client', 'source', 'campaign', 'form', 'platform',
        'status', 'priority', 'outreach',
    ];

    /** Hard cap on combined picks (static + question). Keeps the table readable. */
    public const MAX_TOTAL_COLUMNS = 7;

    /** Sub-cap on custom-question columns so they don't crowd out core fields. */
    public const MAX_QUESTION_COLUMNS = 3;

    /** @return list<string> */
    protected function defaultPickedColumns(): array
    {
        $user = auth()->user();
        if ($user && $user->isClient()) {
            // Clients only ever see one client_name — no point in a Client column.
            return ['name', 'email', 'source', 'status', 'priority', 'outreach'];
        }

        return ['name', 'client', 'source', 'status', 'priority', 'outreach'];
    }

    protected function loadColumnPicker(): void
    {
        $stored = auth()->user()?->inbox_columns;

        if (is_array($stored) && isset($stored['columns']) && is_array($stored['columns'])) {
            $this->pickedColumns = array_values(array_intersect(
                self::AVAILABLE_COLUMNS,
                array_map('strval', $stored['columns']),
            ));
        } else {
            $this->pickedColumns = $this->defaultPickedColumns();
        }

        $this->pickedQuestions = (is_array($stored) && isset($stored['questions']) && is_array($stored['questions']))
            ? array_values(array_unique(array_map('strval', $stored['questions'])))
            : [];

        $this->enforceColumnCaps();
    }

    public function openColumnPicker(): void
    {
        $this->showColumnPicker = true;
    }

    public function closeColumnPicker(): void
    {
        $this->showColumnPicker = false;
    }

    public function togglePickedColumn(string $key): void
    {
        if (! in_array($key, self::AVAILABLE_COLUMNS, true)) {
            return;
        }

        $idx = array_search($key, $this->pickedColumns, true);
        if ($idx === false) {
            if (count($this->pickedColumns) + count($this->pickedQuestions) >= self::MAX_TOTAL_COLUMNS) {
                $this->dispatch('toast', message: __('Column limit reached (:max). Remove one first.', ['max' => self::MAX_TOTAL_COLUMNS]), type: 'warning');

                return;
            }
            $this->pickedColumns[] = $key;
        } else {
            array_splice($this->pickedColumns, $idx, 1);
            $this->pickedColumns = array_values($this->pickedColumns);
        }
    }

    public function togglePickedQuestion(string $question): void
    {
        $question = trim($question);
        if ($question === '') {
            return;
        }

        $idx = array_search($question, $this->pickedQuestions, true);
        if ($idx === false) {
            if (count($this->pickedQuestions) >= self::MAX_QUESTION_COLUMNS) {
                $this->dispatch('toast', message: __('Question-column limit reached (:max). Remove one first.', ['max' => self::MAX_QUESTION_COLUMNS]), type: 'warning');

                return;
            }
            if (count($this->pickedColumns) + count($this->pickedQuestions) >= self::MAX_TOTAL_COLUMNS) {
                $this->dispatch('toast', message: __('Column limit reached (:max). Remove one first.', ['max' => self::MAX_TOTAL_COLUMNS]), type: 'warning');

                return;
            }
            $this->pickedQuestions[] = $question;
        } else {
            array_splice($this->pickedQuestions, $idx, 1);
            $this->pickedQuestions = array_values($this->pickedQuestions);
        }
    }

    public function saveColumnPicker(): void
    {
        $this->enforceColumnCaps();

        $user = auth()->user();
        if ($user) {
            $user->forceFill([
                'inbox_columns' => [
                    'columns'   => $this->pickedColumns,
                    'questions' => $this->pickedQuestions,
                ],
            ])->save();
        }

        $this->showColumnPicker = false;
        $this->dispatch('toast', message: __('Columns updated.'), type: 'success');
    }

    public function resetColumnPicker(): void
    {
        $this->pickedColumns = $this->defaultPickedColumns();
        $this->pickedQuestions = [];

        $user = auth()->user();
        if ($user) {
            $user->forceFill(['inbox_columns' => null])->save();
        }

        $this->dispatch('toast', message: __('Columns reset to default.'));
    }

    /**
     * Distinct questions found in custom_answers across the leads currently
     * visible to this user. DB-agnostic — pulls the JSONB column into PHP
     * and flattens. Bounded to leads in the visible scope so a client
     * only sees questions from their own forms.
     *
     * @return list<string>
     */
    protected function availableQuestionsFor(\Illuminate\Database\Eloquent\Builder $scopedQuery): array
    {
        $questions = [];

        (clone $scopedQuery)
            ->whereNotNull('custom_answers')
            ->select('custom_answers')
            ->orderByDesc('id')
            ->limit(500) // hard upper bound — discovery is best-effort, not authoritative
            ->cursor()
            ->each(function ($row) use (&$questions) {
                $answers = $row->custom_answers;
                if (! is_array($answers)) {
                    return;
                }
                foreach ($answers as $qa) {
                    $q = trim((string) ($qa['question'] ?? ''));
                    if ($q !== '') {
                        $questions[$q] = true;
                    }
                }
            });

        $list = array_keys($questions);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    private function enforceColumnCaps(): void
    {
        if (count($this->pickedQuestions) > self::MAX_QUESTION_COLUMNS) {
            $this->pickedQuestions = array_slice($this->pickedQuestions, 0, self::MAX_QUESTION_COLUMNS);
        }

        $room = max(0, self::MAX_TOTAL_COLUMNS - count($this->pickedQuestions));
        if (count($this->pickedColumns) > $room) {
            $this->pickedColumns = array_slice($this->pickedColumns, 0, $room);
        }
    }
}
