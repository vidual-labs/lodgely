/* Livewire 3 bundles its own Alpine and starts it automatically.
   Importing alpinejs separately would mount Alpine twice and break Livewire,
   Alpine x-data state (e.g. dropdowns staying open) and wire:click handlers. */
