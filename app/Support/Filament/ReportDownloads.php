<?php

namespace App\Support\Filament;

use App\Support\Reports\ReportExporter;
use App\Support\Reports\ReportSheet;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * The "Unduh" menu of a report page (FR-RPT-05): the report as Excel or PDF,
 * built from the same figures the page shows.
 */
final class ReportDownloads
{
    /**
     * @param  Closure(): ReportSheet  $sheet
     */
    public static function make(Closure $sheet): ActionGroup
    {
        return ActionGroup::make([
            Action::make('downloadExcel')
                ->label('Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->action(fn () => app(ReportExporter::class)->excel($sheet())),
            Action::make('downloadPdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentText)
                ->action(fn () => app(ReportExporter::class)->pdf($sheet())),
        ])
            ->label('Unduh')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->button()
            ->color('gray');
    }
}
