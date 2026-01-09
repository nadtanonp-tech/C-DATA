<?php

namespace App\Filament\Widgets;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class MonthSelectorWidget extends Widget implements HasForms
{
    use InteractsWithForms;
    
    protected static string $view = 'filament.widgets.month-selector-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;

    public ?string $selectedMonth = null;
    public ?string $selectedYear = null;
    public bool $viewByYear = false;

    public function mount(): void
    {
        $this->selectedMonth = Carbon::now()->format('Y-m');
        $this->selectedYear = Carbon::now()->format('Y');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Toggle::make('viewByYear')
                    ->label('ดูตามปี')
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        if ($state) {
                            $this->dispatch('year-changed', year: $this->selectedYear);
                        } else {
                            $this->dispatch('month-changed', month: $this->selectedMonth);
                        }
                    }),
                Select::make('selectedMonth')
                    ->label('เลือกเดือน')
                    ->options($this->getMonthOptions())
                    ->default(Carbon::now()->format('Y-m'))
                    ->visible(fn ($get) => !$get('viewByYear'))
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->dispatch('month-changed', month: $state);
                    }),
                Select::make('selectedYear')
                    ->label('เลือกปี')
                    ->options($this->getYearOptions())
                    ->default(Carbon::now()->format('Y'))
                    ->visible(fn ($get) => $get('viewByYear'))
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->dispatch('year-changed', year: $state);
                    }),
            ])
            ->columns(3);
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกเดือน
     */
    private function getMonthOptions(): array
    {
        $options = [];
        $now = Carbon::now();

        // 24 เดือนก่อนหน้า
        for ($i = 24; $i >= 1; $i--) {
            $date = $now->copy()->subMonths($i);
            $options[$date->format('Y-m')] = $date->locale('th')->translatedFormat('F Y');
        }

        // เดือนปัจจุบัน
        $options[$now->format('Y-m')] = $now->locale('th')->translatedFormat('F Y') . ' (เดือนนี้)';

        // 6 เดือนถัดไป
        for ($i = 1; $i <= 6; $i++) {
            $date = $now->copy()->addMonths($i);
            $options[$date->format('Y-m')] = $date->locale('th')->translatedFormat('F Y');
        }

        return $options;
    }

    /**
     * สร้าง options สำหรับ dropdown เลือกปี
     */
    private function getYearOptions(): array
    {
        $options = [];
        $now = Carbon::now();
        
        // 10 ปีก่อนหน้า
        for ($i = 10; $i >= 1; $i--) {
            $year = $now->copy()->subYears($i)->format('Y');
            $thaiYear = (int)$year + 543;
            $options[$year] = "พ.ศ. {$thaiYear} ({$year})";
        }
        
        // ปีปัจจุบัน
        $currentYear = $now->format('Y');
        $currentThaiYear = (int)$currentYear + 543;
        $options[$currentYear] = "พ.ศ. {$currentThaiYear} ({$currentYear}) (ปีนี้)";

        // 2 ปีถัดไป
        for ($i = 1; $i <= 2; $i++) {
            $year = $now->copy()->addYears($i)->format('Y');
            $thaiYear = (int)$year + 543;
            $options[$year] = "พ.ศ. {$thaiYear} ({$year})";
        }

        return $options;
    }
}
