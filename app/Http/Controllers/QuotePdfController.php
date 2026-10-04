<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Quotes\QuotePdfDocument;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class QuotePdfController extends Controller
{
    public function __invoke(Request $request, Quote $quote, QuotePdfDocument $document)
    {
        Gate::authorize('view', $quote);

        $filename = 'cotizacion-'.$quote->number.'.pdf';
        $pdf = Pdf::loadView('quotes.pdf', $document($quote))->setPaper('a4');

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
