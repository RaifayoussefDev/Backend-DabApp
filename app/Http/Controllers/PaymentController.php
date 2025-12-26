<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Paytabscom\Laravel_paytabs\Facades\Paypage;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;

use App\Services\NotificationService;

class PaymentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
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

        // Notifier l'utilisateur
        if ($user = Auth::user()) {
            try {
                $this->notificationService->sendToUser($user, 'payment_success', [
                    'amount' => $request->amount ?? 0,
                    'currency' => 'AED', // Devrait être dynamique
                    'item_title' => 'Payment / دفع',
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send payment_success notification: ' . $e->getMessage());
            }
        }

        return "Paiement réussi !";
    }

    public function paymentFailure()
    {
        if ($user = Auth::user()) {
            try {
                $this->notificationService->sendToUser($user, 'payment_failed', [
                    'item_title' => 'Payment / دفع',
                    'error' => 'Transaction failed / فشل المعاملة'
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send payment_failed notification: ' . $e->getMessage());
            }
        }
        return "Paiement échoué ou annulé.";
    }
}
