<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\ReportExport;
use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ReportExport $export
    ) {}

    public function handle(): void
    {
        $this->export->update(['status' => 'processing']);

        $orgId = $this->export->organization_id;
        $filters = $this->export->filters ?? [];
        $type = $this->export->type;

        $fileName = "reports/org_{$orgId}/report_{$this->export->id}_{$type}.csv";

        $output = fopen('php://temp', 'r+');
        if (! $output) {
            $this->export->update(['status' => 'failed']);

            return;
        }

        $rowCount = 0;

        if ($type === 'financial') {
            fputcsv($output, ['Invoice Number', 'Customer', 'Status', 'Issued Date', 'Due Date', 'Subtotal', 'Tax', 'Total', 'Paid', 'Balance']);

            $query = Invoice::query()
                ->where('organization_id', $orgId)
                ->when($filters['branch_id'] ?? null, fn ($q, $b) => $q->where('branch_id', $b))
                ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '>=', $d))
                ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('issued_at', '<=', $d))
                ->with(['customer', 'payments']);

            foreach ($query->cursor() as $inv) {
                fputcsv($output, [
                    $inv->invoice_number,
                    $inv->customer?->name ?? 'N/A',
                    $inv->status instanceof \BackedEnum ? $inv->status->value : (string) $inv->status,
                    $inv->issued_at?->toDateString(),
                    $inv->due_at?->toDateString(),
                    $inv->subtotal,
                    $inv->tax_amount,
                    $inv->total,
                    $inv->total_paid,
                    $inv->balance,
                ]);
                $rowCount++;
            }
        } elseif ($type === 'work_orders') {
            fputcsv($output, ['Work Order Number', 'Title', 'Customer', 'Technician', 'Status', 'Scheduled Start', 'Scheduled End', 'Completed At']);

            $query = WorkOrder::query()
                ->where('organization_id', $orgId)
                ->when($filters['branch_id'] ?? null, fn ($q, $b) => $q->where('branch_id', $b))
                ->when($filters['technician_id'] ?? null, fn ($q, $t) => $q->where('assigned_to_user_id', $t))
                ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('scheduled_start_at', '>=', $d))
                ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('scheduled_start_at', '<=', $d))
                ->with(['customer', 'assignedTo']);

            foreach ($query->cursor() as $wo) {
                fputcsv($output, [
                    $wo->work_order_number,
                    $wo->title,
                    $wo->customer?->name ?? 'N/A',
                    $wo->assignedTo?->name ?? 'Unassigned',
                    $wo->status instanceof \BackedEnum ? $wo->status->value : (string) $wo->status,
                    $wo->scheduled_start_at?->toIso8601String(),
                    $wo->scheduled_end_at?->toIso8601String(),
                    $wo->completed_at?->toIso8601String(),
                ]);
                $rowCount++;
            }
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        Storage::disk('local')->put($fileName, (string) $csvContent);

        $this->export->update([
            'status' => 'completed',
            'file_path' => $fileName,
            'row_count' => $rowCount,
        ]);
    }
}
