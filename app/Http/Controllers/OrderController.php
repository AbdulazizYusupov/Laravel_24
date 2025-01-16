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

    public function remove($id)
    {
        $cart = session('cart', []);
        if (isset($cart[$id])) {
            unset($cart[$id]);
        }

        session(['cart' => $cart]);
        return redirect()->route('cart.index');
    }

    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'date' => 'required|date',
            'name' => 'required',
        ]);

        $order = Card::create([
            'company_id' => $validated['company_id'],
            'date' => $validated['date'],
            'name' => $validated['name'],
        ]);

        $carts = session('cart', []);

        foreach ($carts as $cart) {
            CardMeal::create([
                'meal_id' => $cart['meal_id'],
                'card_id' => $order->id,
            ]);
        }

        $users = User::where('company_id', $validated['company_id'])->get();

        $message = "<b>Yangi Buyurtma!</b>\n";
        $message .= "🆔 <b>Buyurtma ID:</b> #{$order->id}\n";
        $message .= "⏰ <b>Kun:</b> " . $order->date . "\n";
        $message .= "🍴 <b>Taomlar:</b>\n";

        $totalPrice = 0;
        $mealButtons = [];

        foreach ($carts as $cart) {
            $meal = Meal::find($cart['meal_id']);
            $price = $meal->price;

            $totalPrice += $price;

            $message .= "🍽️ <b>{$meal->name}</b>: " . number_format($price) . " so'm\n";

            $mealButtons[] = [
                'text' => "{$meal->name}",
                'callback_data' => "meal_{$meal->id}"
            ];
        }

        $message .= "\n💳 <b>Jami summa:</b> " . number_format($totalPrice) . " so'm\n";

        $keyboard = [
            'inline_keyboard' => array_merge(
                array_chunk($mealButtons, 2),
                [
                    [
                        ['text' => '✅ Qabul qilish', 'callback_data' => 'accept_' . $order->id],
                        ['text' => '❌ Rad etish', 'callback_data' => 'reject_' . $order->id]
                    ]
                ]
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

            Http::post($token . '/sendMessage', $payload);
        }

        session()->forget('cart');
        return redirect()->route('meal.index');
    }

}
