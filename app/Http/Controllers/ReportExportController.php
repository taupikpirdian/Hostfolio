<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): StreamedResponse
    {
        $data = $request->validate(['report' => ['required', Rule::in(['inventory', 'resources', 'income', 'expenses', 'cashflow', 'aging', 'unpaid', 'overdue', 'income_by_service', 'expense_by_provider', 'renewals'])], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'days' => ['nullable', 'integer', Rule::in([0, 7, 14, 30, 60])]]);
        $area = in_array($data['report'], ['inventory', 'resources']) ? 'inventory' : 'billing';
        abort_unless($request->user()->canView($area), 403);

        return response()->streamDownload(function () use ($reports, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $page = 1;
            $writtenHeader = false;
            do {
                $rows = $reports->rows($data['report'], $data['from'] ?? null, $data['to'] ?? null, (int) ($data['days'] ?? 30), $page, 250);
                if ($rows && ! $writtenHeader) {
                    fputcsv($out, array_keys($rows[0]), ',', '"', '');
                    $writtenHeader = true;
                }
                foreach ($rows as $row) {
                    fputcsv($out, array_map(function ($value) {
                        $value = (string) $value;

                        return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                    }, array_values($row)), ',', '"', '');
                }
                $page++;
            } while (($page - 1) * 250 < $reports->totalRows);
            fclose($out);
        }, 'hostfolio-'.$data['report'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
