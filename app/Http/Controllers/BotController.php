<?php

namespace App\Http\Controllers;

use App\Mail\SendCode;
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
                            ['text' => 'Login']
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

            if ($text === 'Company holder') {
                Cache::put("register_step_{$chat_id}", 'company_holder');
                $this->store($chat_id, "Please, enter your company name:", [
                    'remove_keyboard' => true
                ]);
            }

            if ($text === 'Employee of company') {
                Cache::put("register_step_{$chat_id}", 'company_user');
                $this->store($chat_id, "Please, enter your company name:", [
                    'remove_keyboard' => true
                ]);
            }

            if (Cache::get("register_step_{$chat_id}") === 'company_holder') {
                if (strlen($text) < 2) {
                    $this->store($chat_id, "Name should be at least 2 characters!");
                    return;
                }

                Cache::put("register_company_holder_{$chat_id}", $text);
                Cache::put("register_step_{$chat_id}", 'name');
                $this->store($chat_id, "Please, enter your name:");
                return;
            }

            if (Cache::get("register_step_{$chat_id}") === 'company_user') {
                if (strlen($text) < 2) {
                    $this->store($chat_id, "Name should be at least 2 characters!");
                    return;
                }

                Cache::put("register_company_user_{$chat_id}", $text);
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
                    Log::info('Email sent successfully');
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
                        if (isset($register_company_user)) {
                            $holder = User::where('company', Cache::get("register_company_user_{$chat_id}"))->where('role', 'holder')->first();
                            if (User::where('company', Cache::get("register_company_user_{$chat_id}"))->exists() && $holder) {
                                $user = User::create([
                                    'name' => Cache::get("register_name_{$chat_id}"),
                                    'email' => Cache::get("register_email_{$chat_id}"),
                                    'password' => Cache::get("register_password_{$chat_id}"),
                                    'chat_id' => $chat_id,
                                    'image' => "uploads/{$image_name}",
                                    'email_verified_at' => Carbon::now(),
                                    'company' => Cache::get("register_company_user_{$chat_id}"),
                                ]);
                                $userData = "User name: " . Cache::get("register_name_{$chat_id}") . "\n" .
                                    "Email: " . Cache::get("register_email_{$chat_id}") . "\n" .
                                    "Chat ID: " . $chat_id . "\n" .
                                    "Company: " . Cache::get("register_company_user_{$chat_id}") . "\n" .
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
                            }
                        } elseif (isset($register_company_holder)) {
                            $user = User::create([
                                'name' => Cache::get("register_name_{$chat_id}"),
                                'email' => Cache::get("register_email_{$chat_id}"),
                                'password' => Cache::get("register_password_{$chat_id}"),
                                'chat_id' => $chat_id,
                                'image' => "uploads/{$image_name}",
                                'email_verified_at' => Carbon::now(),
                                'company_holder' => Cache::get("register_company_holder_{$chat_id}"),
                                'role' => 'holder',
                            ]);
                            $admin = User::where('role', 'admin')->first();
                            if ($admin) {
                                $userData = "User name: " . Cache::get("register_name_{$chat_id}") . "\n" .
                                    "Email: " . Cache::get("register_email_{$chat_id}") . "\n" .
                                    "Chat ID: " . $chat_id;

                                $replyMarkup = [
                                    'inline_keyboard' => [
                                        [
                                            ['text' => 'Confirm✅', 'callback_data' => "confirm_{$chat_id}"],
                                            ['text' => 'Cancel⛔️', 'callback_data' => "cancel_{$chat_id}"],
                                        ]
                                    ]
                                ];
                                $this->store($admin->chat_id, $userData, $replyMarkup);
                            } else {
                                $this->store($chat_id, "Admin not found!");
                            }
                        }

                        $this->store($chat_id, "Registration successful!");

                        Cache::forget("register_step_{$chat_id}");
                        Cache::forget("register_name_{$chat_id}");
                        Cache::forget("register_email_{$chat_id}");
                        Cache::forget("register_password_{$chat_id}");
                        Cache::forget("confirmation_code_{$chat_id}");
                    } else {
                        $this->store($chat_id, "Image download failed! Please, try again.");
                    }
                } else {
                    $this->store($chat_id, "Please, send me your image.");
                }
                return;
            }

            if ($text === '/profile') {
                $user = User::where('chat_id', $chat_id)->first();

                if ($user) {
                    $profileMessage = "<b>Profile:</b>\n\n" .
                        "<b>Name:</b> {$user->name}\n" .
                        "<b>Email:</b> {$user->email}";

                    $this->store($chat_id, $profileMessage);

                    if ($user->image) {
                        $filePath = storage_path("app/public/{$user->image}");
                        if (file_exists($filePath)) {
                            Http::attach('photo', file_get_contents($filePath), basename($filePath))
                                ->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendPhoto", [
                                    'chat_id' => $chat_id,
                                ]);
                        } else {
                            $this->store($chat_id, "Profile picture not found.");
                        }
                    } else {
                        $this->store($chat_id, "Profile picture is not exists.");
                    }
                } else {
                    $this->store($chat_id, "User not found. Please, register first.");
                }
            }
            if ($text === 'Login') {
                Cache::put("login_step_{$chat_id}", 'email');
                $this->store($chat_id, "Please, enter your email:", [
                    'remove_keyboard' => true,
                ]);

                return;
            }

            if (Cache::get("login_step_{$chat_id}") === 'email') {
                if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $this->store($chat_id, "Please, enter a valid email!");
                    return;
                }

                Cache::put("login_email_{$chat_id}", $text);
                Cache::put("login_step_{$chat_id}", 'password');
                $this->store($chat_id, "Please, enter your password:");
                return;
            }

            if (Cache::get("login_step_{$chat_id}") === 'password') {
                Cache::put("login_password_{$chat_id}", $text);

                $this->del($message_id, $chat_id);

                $email = Cache::get("login_email_{$chat_id}");
                $password = Cache::get("login_password_{$chat_id}");

                $user = User::where('email', $email)->first();

                if ($user && Hash::check($password, $user->password)) {
                    Cache::forget("login_step_{$chat_id}");
                    Cache::forget("login_email_{$chat_id}");
                    Cache::forget("login_password_{$chat_id}");

                    $this->store($chat_id, "Successfully logged in, {$user->name}.");
                    $user->update(['chat_id' => $chat_id]);
                } else {
                    $this->store($chat_id, "Incorrect email or password. Please, try again.");
                }
            }

            if ($text === '/status') {
                if ($chat_id == User::where('role', 'admin')->first()->chat_id) {
                    $this->store($chat_id, "Please, check the users:", [
                        'keyboard' => [
                            [
                                ['text' => 'All users with status 1'],
                                ['text' => 'All users with status 0'],
                            ]
                        ],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true,
                    ]);
                } else {
                    $this->store($chat_id, "Siz admin emassiz");
                }
            }

            if ($text === 'All users with status 1') {
                $users = User::where('role', '!=', 'admin')->where('status', 1)->get();

                if ($users->isEmpty()) {
                    $this->store($chat_id, "There are no users with status 1.");
                } else {
                    $userList = "All users with status 1:\n";

                    foreach ($users as $user) {
                        $userList .= "{$user->id}) User name: {$user->name}, Email: {$user->email}\n";
                    }

                    $this->store($chat_id, $userList . "\nPlease, enter the user number to change their status.");
                }
            }

            if ($text === 'All users with status 0') {
                $users = User::where('role', '!=', 'admin')->where('status', 0)->get();

                if ($users->isEmpty()) {
                    $this->store($chat_id, "There are no users with status 0.");
                } else {
                    $userList = "All users with status 0:\n";

                    foreach ($users as $user) {
                        $userList .= "{$user->id}) User name: {$user->name}, Email: {$user->email}\n";
                    }

                    $this->store($chat_id, $userList . "\nPlease, enter the user number to change their status.");
                }
            }

            if (is_numeric($text) && $text > 0 && $text <= count(User::where('role', '!=', 'admin')->get())) {
                $userIndex = $text;

                $allUsers = User::where('role', '!=', 'admin')->get();
                if (isset($allUsers[$userIndex])) {
                    $user = $allUsers[$userIndex];

                    $user->status = !$user->status;
                    $user->save();

                    $newStatus = $user->status ? '1' : '0';
                    $this->store($chat_id, "User status changed to {$newStatus}.");
                } else {
                    $this->store($chat_id, "There is no user with this number.");
                }
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
                        $chatId = User::where('role', 'admin')->first()->chat_id;
                        $this->removeInlineKeyboard($callmid, $chatId);
                        $userData = "User name: " . $user->name . "\n" .
                            "Email: " . $user->email . "\n" .
                            "Chat ID: " . $user->chat_id . "\n";

                        $replyMarkup = [
                            'inline_keyboard' => [
                                [
                                    ['text' => 'Confirm✅', 'callback_data' => "confirm_{$chat_id}"],
                                    ['text' => 'Cancel⛔️', 'callback_data' => "cancel_{$chat_id}"],
                                ]
                            ]
                        ];
                        $this->store($chatId, $userData, $replyMarkup);
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
