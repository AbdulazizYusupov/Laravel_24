<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
use App\Models\Card;
use App\Models\CardMeal;
use App\Models\Company;
use App\Models\Meal;
use App\Models\Order;
use App\Models\OrderMeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BotController extends Controller
{
    public function store(int $chatId, string $text, $replyMarkup = null)
    {
        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }

        Http::post($token . '/sendMessage', $payload);
    }

    public function bot(Request $request)
    {
        try {
            $data = $request->all();
            $chat_id = $data['message']['chat']['id'] ?? null;
            $text = $data['message']['text'] ?? null;
            $photo = $data['message']['photo'] ?? null;
            $call = $data['callback_query'] ?? null;
            $message_id = $data['message']['message_id'] ?? null;
            $call_id = $data['callback_query']['message']['chat']['id'] ?? null;
            $callmid = $data['callback_query']['message']['message_id'] ?? null;


            if ($text === '/start') {
                $this->store($chat_id, "Welcome, please enter:", [
                    'keyboard' => [
                        [
                            ['text' => 'Register'],
                        ]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]);
                return;
            }
            if ($text === 'Register') {
                $this->store($chat_id, "Choose:", [
                    'keyboard' => [
                        [
                            ['text' => 'Company holder'],
                            ['text' => 'Employee of company']
                        ]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]);
                return;
            }

            if ($text == 'Employee of company') {
                Cache::put("register_step_{$chat_id}", 'user');
                $this->store($chat_id, "Please, enter your company name:", [
                    'remove_keyboard' => true,
                ]);
            }

            if (Cache::get("register_step_{$chat_id}") === 'user' && $text != 'Employee of company') {
                if (strlen($text) < 2) {
                    $this->store($chat_id, "Name should be at least 2 characters!");
                    return;
                }

                Cache::put("register_user_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'name');
                $this->store($chat_id, "Please, enter your name:");
                return;
            }

            if ($text == 'Company holder') {
                $this->store($chat_id, "Please, enter your company name:", [
                    'remove_keyboard' => true
                ]);
                Cache::put("register_step_{$chat_id}", 'holder');
            }

            if (Cache::get("register_step_{$chat_id}") == 'holder' && $text != 'Company holder') {
                if (strlen($text) < 2) {
                    $this->store($chat_id, "Company name should be at least 2 characters!");
                    return;
                }

                Cache::put("register_holder_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'logo');
                $this->store($chat_id, "Company logo:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'logo' && $photo) {
                $file_id = end($photo)['file_id'];
                $telegram_api = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                $file_path_response = file_get_contents("{$telegram_api}/getFile?file_id={$file_id}");
                $response = json_decode($file_path_response, true);

                if (isset($response['result']['file_path'])) {
                    $file_path = $response['result']['file_path'];
                    $download_url = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/{$file_path}";

                    $image_name = uniqid() . '.jpg';
                    $image_content = file_get_contents($download_url);

                    if ($image_content) {
                        Storage::disk('public')->put("uploads/{$image_name}", $image_content);
                        $image_path = "uploads/{$image_name}";
                    } else {
                        $this->store($chat_id, "Failed to download the image, please try again.");
                        return;
                    }

                    Cache::put("register_logo_{$chat_id}", $image_path);
                    Cache::put("register_step_{$chat_id}", 'address');
                    $this->store($chat_id, "Company address:");
                }
            }

            if (Cache::get("register_step_{$chat_id}") === 'address' && ($text || isset($data['message']['location']))) {
                if (isset($data['message']['location'])) {
                    $latitude = $data['message']['location']['latitude'];
                    $longitude = $data['message']['location']['longitude'];
                } else {
                    $location = explode(",", $text);
                    if (count($location) == 2) {
                        $latitude = trim($location[0]);
                        $longitude = trim($location[1]);
                    } else {
                        $this->store($chat_id, "Please, enter a valid location!");
                        return;
                    }
                }

                Cache::put("register_latitude_{$chat_id}", $latitude);
                Cache::put("register_longitude_{$chat_id}", $longitude);
                Cache::put("register_step_{$chat_id}", 'name');
                $this->store($chat_id, "Please, enter your name:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'name') {
                if (strlen($text) < 2) {
                    $this->store($chat_id, "Name should be at least 2 characters!");
                    return;
                }

                Cache::put("register_name_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'email');
                $this->store($chat_id, "Please, enter your email:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'email') {
                if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $this->store($chat_id, "Please, enter a valid email!");
                    return;
                }

                if (User::where('email', $text)->exists()) {
                    $this->store($chat_id, "This email is already registered!");
                    return;
                }

                Cache::put("register_email_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'password');
                $this->store($chat_id, "Please, enter your password:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'password') {
                if (strlen($text) < 6) {
                    $this->store($chat_id, "Password should be at least 6 characters!");
                    return;
                }

                Cache::put("register_password_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'confirmation_code');

                $confirmation_code = Str::random(6);
                $email = Cache::get("register_email_{$chat_id}");
                $name = Cache::get("register_name_{$chat_id}");

                try {
                    Mail::to($email)->send(new SendCode($name, $confirmation_code));
                    $this->store($chat_id, "We sent you an email. Please, check your inbox.");
                } catch (\Exception $e) {
                    Log::error('Email sending failed: ' . $e->getMessage());
                    $this->store($chat_id, "Email sending failed!");
                }

                Cache::put("confirmation_code_{$chat_id}", $confirmation_code);
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'confirmation_code') {
                if ($text === Cache::get("confirmation_code_{$chat_id}")) {
                    Cache::put("register_password_{$chat_id}", bcrypt(Cache::get("register_password_{$chat_id}")));
                    Cache::put("register_step_{$chat_id}", 'image');
                    $this->store($chat_id, "Confirmation code is correct. Please, send me your image.");
                    Cache::forget("confirmation_code_{$chat_id}");
                } else {
                    $this->store($chat_id, "Confirmation code is incorrect!");
                }
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'image') {
                if ($photo) {
                    $file_id = end($photo)['file_id'];

                    $telegram_api = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                    $file_path_response = file_get_contents("{$telegram_api}/getFile?file_id={$file_id}");
                    $response = json_decode($file_path_response, true);

                    if (isset($response['result']['file_path'])) {
                        $file_path = $response['result']['file_path'];
                        $download_url = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/{$file_path}";

                        $image_name = uniqid() . '.jpg';
                        $image_content = file_get_contents($download_url);

                        if ($image_content) {
                            Storage::disk('public')->put("uploads/{$image_name}", $image_content);
                            $image_path = "uploads/{$image_name}";
                        } else {
                            $this->store($chat_id, "Image download failed! Please, try again.");
                            return;
                        }
                        $userComp = Cache::get("register_user_{$chat_id}");
                        $holderComp = Cache::get("register_holder_{$chat_id}");
                        $comp = Company::where('name', $userComp)->first();
                        if (isset($userComp)) {
                            $holder = User::where('company_id', $comp->id)->where('role', 'holder')->first();
                            if ($holder && $comp) {
                                $user = User::create([
                                    'name' => Cache::get("register_name_{$chat_id}"),
                                    'email' => Cache::get("register_email_{$chat_id}"),
                                    'password' => Cache::get("register_password_{$chat_id}"),
                                    'chat_id' => $chat_id,
                                    'image' => "uploads/{$image_name}",
                                    'email_verified_at' => Carbon::now(),
                                    'company_id' => $comp->id,
                                ]);

                                $userData = "User name: " . $user->name . "\n" .
                                    "Email: " . $user->email . "\n" .
                                    "Chat ID: " . $user->chat_id . "\n" .
                                    "Company: " . $user->company->name . "\n" .
                                    "Is it your employee?";

                                $replyMarkup = [
                                    'inline_keyboard' => [
                                        [
                                            ['text' => 'Yes✅', 'callback_data' => "yes_{$chat_id}"],
                                            ['text' => 'No⛔️', 'callback_data' => "no_{$chat_id}"],
                                        ]
                                    ]
                                ];
                                $this->store($holder->chat_id, $userData, $replyMarkup);
                                $this->store($chat_id, "Registration successful!");
                            }
                        } elseif (isset($holderComp)) {
                            $company = Company::create([
                                'name' => $holderComp,
                                'logo' => Cache::get("register_logo_{$chat_id}"),
                                'lang' => Cache::get("register_latitude_{$chat_id}"),
                                'long' => Cache::get("register_longitude_{$chat_id}"),
                            ]);

                            $user = User::create([
                                'name' => Cache::get("register_name_{$chat_id}"),
                                'email' => Cache::get("register_email_{$chat_id}"),
                                'password' => Cache::get("register_password_{$chat_id}"),
                                'chat_id' => $chat_id,
                                'image' => "uploads/{$image_name}",
                                'email_verified_at' => Carbon::now(),
                                'role' => 'holder',
                                'company_id' => $company->id,
                            ]);
                            $admin = User::where('role', 'admin')->first();
                            if ($admin) {
                                $userData = "User name: " . $user->name . "\n" .
                                    "Email: " . $user->email . "\n" .
                                    "Chat ID: " . $user->chat_id . "\n" .
                                    "Company: " . $user->company->name;

                                $replyMarkup = [
                                    'inline_keyboard' => [
                                        [
                                            ['text' => 'Confirm✅', 'callback_data' => "confirm_{$user->chat_id}"],
                                            ['text' => 'Cancel⛔️', 'callback_data' => "cancel_{$user->chat_id}"],
                                        ]
                                    ]
                                ];
                                $this->store($admin->chat_id, $userData, $replyMarkup);
                            } else {
                                $this->store($chat_id, "Admin not found!");
                            }
                            $this->store($chat_id, "Registration successful!");
                        }
                        Cache::forget("register_step_{$chat_id}");
                        Cache::forget("register_name_{$chat_id}");
                        Cache::forget("register_email_{$chat_id}");
                        Cache::forget("register_password_{$chat_id}");
                        Cache::forget("confirmation_code_{$chat_id}");
                        Cache::forget("register_user_{$chat_id}");
                        Cache::forget("register_holder_{$chat_id}");
                    } else {
                        $this->store($chat_id, "Image download failed! Please, try again.");
                    }
                } else {
                    $this->store($chat_id, "Please, send me your image.");
                }
                return;
            }

            if ($text === "/menu") {
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

                $this->store($chat_id, $message, $keyboard);
                return;
            }

            if ($call) {
                $calldata = $call['data'];

                if (Str::startsWith($calldata, 'confirm_')) {
                    $call_id = Str::after($calldata, 'confirm_');
                    $user = User::where('chat_id', $call_id)->first();

                    if ($user) {
                        $user->status = 1;
                        $user->save();
                        $chatId = User::where('role', 'admin')->first()->chat_id;
                        $this->removeInlineKeyboard($callmid, $chatId);
                        $this->store($call_id, "Your profile has been approved.");
                        $this->store(User::where('role', 'admin')->first()->chat_id, "User approved successfully.");
                    } else {
                        $this->store(User::where('role', 'admin')->first()->chat_id, "User not found.");
                    }
                    return;
                }

                if (Str::startsWith($calldata, 'cancel_')) {
                    $call_id = Str::after($calldata, 'cancel_');
                    $user = User::where('chat_id', $call_id)->first();

                    if ($user) {
                        $user->delete();
                        $chatId = User::where('role', 'admin')->first()->chat_id;
                        $this->removeInlineKeyboard($callmid, $chatId);
                        $this->store($call_id, "Your profile has been deleted.");
                        $this->store(User::where('role', 'admin')->first()->chat_id, "User deleted successfully.");
                    } else {
                        $this->store(User::where('role', 'admin')->first()->chat_id, "User not found.");
                    }
                    return;
                }

                if (Str::startsWith($calldata, 'yes_')) {
                    $call_id = Str::after($calldata, 'yes_');
                    $user = User::where('chat_id', $call_id)->first();

                    if ($user) {
                        $admin = User::where('role', 'admin')->first();
                        $chatId = User::where('role', 'holder')->where('company_id', $user->company_id)->first()->chat_id;
                        $this->removeInlineKeyboard($callmid, $chatId);
                        $userData = "User name: " . $user->name . "\n" .
                            "Email: " . $user->email . "\n" .
                            "Chat ID: " . $user->chat_id . "\n" .
                            "Company: " . $user->company->name . "\n";

                        $replyMarkup = [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Confirm✅', 'callback_data' => "confirm_{$user->chat_id}"],
                                    ['text' => 'Cancel⛔️', 'callback_data' => "cancel_{$user->chat_id}"],
                                ]
                            ]
                        ];
                        $this->store($admin->chat_id, $userData, $replyMarkup);
                    } else {
                        $this->store(User::where('role', 'admin')->first()->chat_id, "User not found.");
                    }
                    return;
                }

                if (Str::startsWith($calldata, 'no_')) {
                    $call_id = Str::after($calldata, 'no_');
                    $user = User::where('chat_id', $call_id)->first();

                    if ($user) {
                        $chatId = User::where('role', 'admin')->first()->chat_id;
                        $this->removeInlineKeyboard($callmid, $chatId);
                        $company = User::where('role', 'holder')->where('company', $user->company)->first();
                        $user->delete();
                        $this->store($call_id, "Your profile has been deleted.");
                        $this->store(User::where('role', 'holder')->where('company', $company)->first()->chat_id, "User deleted successfully.");
                    } else {
                        $this->store(User::where('role', 'admin')->first()->chat_id, "User not found.");
                    }
                    return;
                }

                if (Str::startsWith($calldata, 'meal_')) {
                    $meal_id = Str::after($calldata, 'meal_');
                    $meal = Meal::find($meal_id);

                    if ($meal) {
                        $message = "<b>Siz tanlagan ovqat:</b>\n";
                        $message .= "🍽️ <b>{$meal->name}</b>: " . number_format($meal->price) . " so'm\n\n";
                        $message .= "Miqdorni kiriting:";

                        $card = Card::where('user_id', User::where('chat_id', $call_id)->first()->id)->first() ?? null;

                        if ($card == null || $card->date != Carbon::now()->format('Y-m-d')) {
                            $card = Card::create([
                                'user_id' => User::where('chat_id', $call_id)->first()->id,
                                'date' => Carbon::now()->toDateString(),
                            ]);
                        }

                        CardMeal::create([
                            'card_id' => $card->id,
                            'meal_id' => $meal->id
                        ]);

                        $keyboard = [
                            'inline_keyboard' => [
                                [['text' => 'Orqaga', 'callback_data' => 'back_to_menu']]
                            ]
                        ];

                        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                        $payload = [
                            'chat_id' => $call_id,
                            'message_id' => $callmid,
                            'text' => $message,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard),
                        ];
                        Http::post($token . '/editMessageText', $payload);

                        Cache::forever('callmid', $callmid);
                    }
                }

                if ($calldata === 'back_to_menu') {
                    $message = "📋 <b>Menu!</b>\n";
                    $message .= "🆔 <b>Buyurtma berish!</b>\n";
                    $message .= "🍴 <b>Tanlang:</b>\n";

                    $mealButtons = [];

                    $carts = Meal::all();

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
                            [
                                [
                                    [
                                        'text' => '🛒 Savatcha',
                                        'callback_data' => 'cart'
                                    ]
                                ]
                            ]
                        )
                    ];

                    $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                    $payload = [
                        'chat_id' => $call_id,
                        'message_id' => $callmid,
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($keyboard),
                    ];

                    Http::post($token . '/editMessageText', $payload);
                }
                if ($calldata === "cart") {

                    $card = Card::where('user_id', User::where('chat_id', $call_id)->first()->id)->first();

                    $message = "📋 <b>🛒 Savatcha:</b>\n";
                    $message .= "Sana: $card->date\n";
                    foreach ($card->cardmeals as $meal) {
                        $message .= "🍴 <b>{$meal->meal->name}</b>: {$meal->count}\n";
                    }
                    $message .= "💰 <b>Summa:</b> " . number_format($card->sum) . " so'm\n\n";

                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => 'Zakaz berish✅',
                                    'callback_data' => 'accept_order'
                                ],
                                [
                                    'text' => 'Bekor qilish⛔️',
                                    'callback_data' => 'www'
                                ],
                                [
                                    'text' => 'Orqaga',
                                    'callback_data' => 'back_to_menu'
                                ],
                            ]
                        ]
                    ];

                    $this->del($callmid, $call_id);

                    $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                    $payload = [
                        'chat_id' => $call_id,
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($keyboard),
                    ];

                    Http::post($token . '/sendMessage', $payload);
                }

                if ($calldata === "accept_order") {
                    $card = Card::where('user_id', User::where('chat_id', $call_id)->first()->id)->first();

                    $order = Order::create([
                        'date' => Carbon::now()->toDateString(),
                        'sum' => $card->sum,
                        'user_id' => $card->user_id,
                    ]);

                    foreach ($card->cardmeals as $meal) {
                        OrderMeal::create([
                            'order_id' => $order->id,
                            'meal_id' => $meal->meal_id,
                            'count' => $meal->count,
                        ]);
                    }

                    $card->delete();

                    $message = "📋 <b>Buyurtmangiz qabul qilindi!</b>\n";

                    $this->del($callmid, $call_id);
                    $this->store($call_id, $message);
                }
                $call = $data['callback_query'] ?? null;
                $calldata = $call['data'] ?? null;
                if ($calldata === "www") {
                    $user = User::where('chat_id', $call_id)->first();
                    if ($user) {
                        $card = Card::where('user_id', $user->id)->first();

                        if ($card) {
                            $card->delete();
                            $message = "📋 <b>Buyurtmangiz bekor qilindi!</b>\n";
                            $this->del($callmid, $call_id);
                            $this->store($call_id, $message);
                        } else {
                            $message = "📋 Savatchangiz mavjud emas.";
                            $this->store($call_id, $message);
                        }
                    }
                }
            }

            if (is_numeric($text)) {
                $card = Card::where('user_id', User::where('chat_id', $chat_id)->first()->id)->first() ?? null;

                if ($card) {
                    $meal = CardMeal::where('card_id', $card->id)->latest()->first();
                    if ($meal) {
                        $meal->update([
                            'count' => $text
                        ]);
                    }
                }

                $card->update([
                    'sum' => $card->sum + $meal->count * $meal->meal->price
                ]);

                $message = "📋 <b>Menu!</b>\n";
                $message .= "🆔 <b>Buyurtma berish!</b>\n";
                $message .= "🍴 <b>Tanlang:</b>\n";

                $mealButtons = [];

                $carts = Meal::all();

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
                        [
                            [
                                [
                                    'text' => '🛒 Savatcha',
                                    'callback_data' => 'cart'
                                ]
                            ]
                        ]
                    )
                ];

                $call_mid = Cache::get('callmid');

                $this->del($message_id, $chat_id);

                $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
                $payload = [
                    'chat_id' => $chat_id,
                    'message_id' => $call_mid,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard),
                ];

                Http::post($token . '/editMessageText', $payload);
            }
        } catch (\Exception $exception) {
            Log::error($exception);
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage()
            ]);
        }
    }
    public function del($message_id, $chat_id)
    {
        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ];
        Http::post($token . '/deletemessage', $payload);
    }
    public function edit($message_id, $chat_id, $new_message)
    {
        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $new_message,
            'parse_mode' => 'HTML',
        ];
        Http::post($token . '/editMessageText', $payload);
    }

    public function removeInlineKeyboard($message_id, $chat_id)
    {
        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ];
        Http::post($token . '/editMessageReplyMarkup', $payload);
    }

    public function delLocation($message_id, $chat_id)
    {
        $token = "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN');
        $payload = [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ];
        Http::post($token . '/deletemessage', $payload);
    }
}
