<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Quotes\Actions\RejectQuote;
use App\Domain\Quotes\Actions\SendQuote;
use App\Domain\Quotes\Exceptions\InvalidQuoteTransition;
use App\Http\Requests\Quotes\RejectQuoteRequest;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class QuoteStatusController extends Controller
{
    public function send(Request $request, Quote $quote, SendQuote $send): RedirectResponse
    {
        try {
            $send($request->user(), $quote);
        } catch (InvalidQuoteTransition $exception) {
            $this->invalidTransition($exception);
        }

        return to_route('quotes.show', $quote)->with('success', 'Cotización marcada como enviada');
    }

    public function accept(Request $request, Quote $quote, AcceptQuote $accept): RedirectResponse
    {
        try {
            $accept($request->user(), $quote);
        } catch (InvalidQuoteTransition $exception) {
            $this->invalidTransition($exception);
        }

        return to_route('quotes.show', $quote)->with('success', 'Cotización marcada como aceptada');
    }

    public function reject(RejectQuoteRequest $request, Quote $quote, RejectQuote $reject): RedirectResponse
    {
        try {
            $reject($request->user(), $quote, $request->validated('rejection_reason'));
        } catch (InvalidQuoteTransition $exception) {
            $this->invalidTransition($exception);
        }

        return to_route('quotes.show', $quote)->with('success', 'Cotización marcada como rechazada');
    }

    private function invalidTransition(InvalidQuoteTransition $exception): never
    {
        throw ValidationException::withMessages(['status' => $exception->getMessage()]);
    }
}
