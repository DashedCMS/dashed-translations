@php
    $statussen = $this::statussen();
    $rows = $this->getRows();
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Filament\Support\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    <x-filament::section>
        <div class="fi-ta-content overflow-x-auto">
            <table class="fi-ta-table w-full table-auto text-sm">
                <thead>
                    <tr class="fi-ta-row">
                        <th class="fi-ta-header-cell px-3 py-2 text-start">
                            <span class="fi-ta-header-cell-label">{{ __('Taal') }}</span>
                        </th>
                        @foreach ($statussen as $meta)
                            <th class="fi-ta-header-cell px-3 py-2 text-start">
                                <span class="fi-ta-header-cell-label">{{ $meta['label'] }}</span>
                            </th>
                        @endforeach
                        <th class="fi-ta-header-cell px-3 py-2 text-start">
                            <span class="fi-ta-header-cell-label">{{ __('Bezig') }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-2 font-semibold">{{ $row['locale'] }}</td>
                            @foreach ($row['cells'] as $cell)
                                <td class="fi-ta-cell px-3 py-2">
                                    <x-filament::badge
                                        tag="a"
                                        :href="$cell['url']"
                                        :color="$cell['color']"
                                        size="lg"
                                    >
                                        {{ $cell['count'] }}
                                    </x-filament::badge>
                                </td>
                            @endforeach
                            <td class="fi-ta-cell px-3 py-2">
                                <x-filament::badge :color="$row['running'] > 0 ? 'info' : 'gray'" size="lg">
                                    {{ $row['running'] }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
