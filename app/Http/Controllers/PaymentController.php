<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Paytabscom\Laravel_paytabs\Facades\Paypage;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $user = Auth::user();
        $amount = $request->amount ?? 100; // Montant par défaut

        $transactionReference = uniqid('listing_');

        // Création de la page de paiement PayTabs
        $payment = Paypage::sendPaymentCode('all')
            ->sendTransaction('sale', 'ecom')
            ->sendCart($transactionReference, $amount, 'Payment for Listing')
            ->sendCustomerDetails(
                $user->name,
                $user->email,
                $user->phone ?? '0000000000',
                'MA',
                'Rue Exemple',
                'Casablanca',
                '20000',
                $request->ip()
            )
            ->sendURLs(
                route('paytabs.success'),
                route('paytabs.failure')
            )
            ->sendLanguage('en')
            ->create_pay_page();

        return redirect($payment->getTargetUrl());
    }

    public function paymentSuccess(Request $request)
    {
        // Ici, tu peux récupérer les infos de paiement envoyées par PayTabs via la requête
        // et créer un enregistrement dans ta table payments

        Payment::create([
            'user_id' => Auth::id(),
            'amount' => $request->amount ?? 0,
            'payment_status' => 'paid',
            'payment_method_id' => null, // adapter si tu as cette info
            'listing_id' => null, // adapter selon le contexte
        ]);

        return "Paiement réussi !";
    }

    public function paymentFailure()
    {
        return "Paiement échoué ou annulé.";
    }
}
