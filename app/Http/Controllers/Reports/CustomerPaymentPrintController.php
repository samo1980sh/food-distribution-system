<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerPaymentPrintController extends Controller
{
    public function __invoke(CustomerPayment $customerPayment): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        Gate::authorize('print', $customerPayment);

        $customerPayment->load([
            'customer',
            'salesInvoice',
            'warehouse',
            'vehicle',
            'route',
            'salesRepresentative',
            'creator',
            'confirmer',
        ]);

        return view('reports.customer-payments.print', [
            'customerPayment' => $customerPayment,
            'amountInWords' => $this->amountInWords(
                (float) $customerPayment->amount,
            ),
        ]);
    }

    private function amountInWords(float $amount): string
    {
        $amount = max($amount, 0);

        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);

        if ($fraction === 100) {
            $whole++;
            $fraction = 0;
        }

        if (! class_exists(\NumberFormatter::class)) {
            return 'فقط '.number_format($amount, 2).' ليرة سورية لا غير';
        }

        $formatter = new \NumberFormatter(
            'ar',
            \NumberFormatter::SPELLOUT,
        );

        $wholeWords = $formatter->format($whole);

        if ($wholeWords === false) {
            return 'فقط '.number_format($amount, 2).' ليرة سورية لا غير';
        }

        $result = $wholeWords.' ليرة سورية';

        if ($fraction > 0) {
            $fractionWords = $formatter->format($fraction);

            if ($fractionWords !== false) {
                $result .= ' و'.$fractionWords.' قرشًا';
            }
        }

        return 'فقط '.$result.' لا غير';
    }
}