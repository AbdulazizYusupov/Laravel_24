<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardMeal;
use App\Models\Company;
use App\Models\Meal;
use App\Models\MealOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        $ids = is_array($cart) ? array_keys($cart) : [];
        $models = Meal::whereIn('id', $ids)->get();
        $companies = Company::where('status', '=', 1)->get();
        return view('card', compact('models', 'companies'));
    }

    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);


        $users = User::where('company_id', $validated['company_id'])->get();

        $carts = Meal::all();

        $message = "📋 <b>Menu!</b>\n";
        $message .= "🆔 <b>Buyurtma berish!</b>\n";
        $message .= "🍴 <b>Tanlang:</b>\n";

        $mealButtons = [];

        foreach ($carts as $cart) {
            $meal = Meal::find($cart->id);

            $mealButtons[] = [
                'text' => "{$meal->name}",
                'callback_data' => "meal_{$meal->id}"
            ];
        }

        $keyboard = [
            'inline_keyboard' => array_merge(
                array_chunk($mealButtons, 4),
            )
        ];

        foreach ($users as $user) {
            $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
            $payload = [
                'chat_id' => $user->chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ];

            $response = Http::post($token . '/sendMessage', $payload);

            if ($response->failed()) {
                logger('Telegram API xatosi: ' . $response->body());
            }
        }

        return redirect()->route('meal.index');
    }
}
