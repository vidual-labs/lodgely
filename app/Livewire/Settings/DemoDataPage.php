<?php

namespace App\Livewire\Settings;

use App\Domain\Demo\DemoDataManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DemoDataPage extends Component
{
    /** @var array{loaded: bool, demo_leads: int, demo_users: int, has_import: bool, ad_metrics: int, ad_metrics_removable: bool} */
    public array $status = [
        'loaded' => false,
        'demo_leads' => 0,
        'demo_users' => 0,
        'has_import' => false,
        'ad_metrics' => 0,
        'ad_metrics_removable' => true,
    ];

    public function mount(DemoDataManager $manager): void
    {
        $this->guardOperator();
        $this->status = $manager->status();
    }

    public function load(DemoDataManager $manager): void
    {
        $this->guardOperator();

        $result = $manager->load();

        if ($result['already_loaded']) {
            $this->dispatch('toast', message: __('Demo data is already loaded.'));
        } else {
            Log::info('lodgely.demo.loaded', [
                'user_id' => auth()->id(),
                'created_leads' => $result['created_leads'],
                'created_users' => $result['created_users'],
            ]);

            $this->dispatch('toast', message: __(':leads demo leads loaded.', ['leads' => $result['created_leads']]));
        }

        $this->status = $manager->status();
    }

    public function unload(DemoDataManager $manager): void
    {
        $this->guardOperator();

        $result = $manager->unload();

        Log::info('lodgely.demo.unloaded', [
            'user_id' => auth()->id(),
            'deleted_leads' => $result['deleted_leads'],
            'deleted_users' => $result['deleted_users'],
            'deleted_ad_metrics' => $result['deleted_ad_metrics'],
        ]);

        $message = __(':leads demo leads removed.', ['leads' => $result['deleted_leads']]);
        if ($result['deleted_ad_metrics'] > 0) {
            $message .= ' '.__(':rows ad-metrics rows removed.', ['rows' => $result['deleted_ad_metrics']]);
        }

        $this->dispatch('toast', message: $message);

        $this->status = $manager->status();
    }

    public function render(): View
    {
        return view('livewire.settings.demo-data-page');
    }

    private function guardOperator(): void
    {
        abort_unless(auth()->user()?->isOperator(), 403);
    }
}
