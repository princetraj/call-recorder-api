<?php

namespace App\Exports;

use App\Models\CallLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CallLogsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithChunkReading
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * Define the headings for the export.
     */
    public function headings(): array
    {
        return [
            'ID',
            'Caller Name',
            'Caller Number',
            'Call Type',
            'Duration (seconds)',
            'Duration (formatted)',
            'Call Date & Time',
            'Agent Name',
            'Branch',
            'SIM Name',
            'SIM Number',
            'SIM Slot',
            'Notes',
            'Recordings Count',
        ];
    }

    /**
     * Map the data for each row.
     */
    public function map($callLog): array
    {
        $durationFormatted = $this->formatDuration($callLog->call_duration);

        return [
            $callLog->id,
            $callLog->caller_name ?? 'Unknown',
            $callLog->caller_number,
            ucfirst($callLog->call_type),
            $callLog->call_duration,
            $durationFormatted,
            $callLog->call_timestamp->format('Y-m-d H:i:s'),
            $callLog->user ? $callLog->user->name : 'N/A',
            $callLog->user && $callLog->user->branch ? $callLog->user->branch->name : 'N/A',
            $callLog->sim_name ?? 'N/A',
            $callLog->sim_number ?? 'N/A',
            $callLog->sim_slot_index !== null ? 'Slot ' . ($callLog->sim_slot_index + 1) : 'N/A',
            $callLog->notes ?? '',
            $callLog->recordings_count ?? 0,
        ];
    }

    /**
     * Apply styles to the worksheet.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row (headers) as bold
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Format duration from seconds to MM:SS.
     */
    private function formatDuration($seconds)
    {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Define chunk size for reading data in batches.
     * OPTIMIZED: Process 1000 rows at a time to prevent memory issues with large exports.
     */
    public function chunkSize(): int
    {
        return 1000;
    }
}
