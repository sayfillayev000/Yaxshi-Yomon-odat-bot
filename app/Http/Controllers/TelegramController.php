<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $update = Telegram::commandsHandler(true);
            $update = Telegram::getWebhookUpdate();
            $message = $update->getMessage();
            $text = $message->text;
            $chat_id = $message->chat->id;
            $addHabitType = "user_habit_add_{$chat_id}";
            $editHabitType = "user_habit_edit_{$chat_id}";
            $habitKey = "user_habit_type_{$chat_id}";
            switch ($text) {
                case '/start':
                    $this->habit($chat_id);
                    break;
                case 'Yaxshi Odatlar':
                    $this->goodHabit($chat_id);
                    break;
                case 'Yomon Odatlar':
                    $this->badHabit($chat_id);
                    break;
                case '⬅️ Ortga':
                    $this->habit($chat_id);
                    break;
                case "Yaxshi Odat qo'shish 🟢":
                    $this->addHabit($chat_id, 'good', $addHabitType, $habitKey);
                    break;
                case "Yomon Odat qo'shish 🟢":
                    $this->addHabit($chat_id, 'bad', $addHabitType, $habitKey);
                    break;
                case "yaxshi odatlarni ko'rish 👁️":
                    $this->showGoodHabits($chat_id);
                    break;
                case "Yomon odatlarni ko'rish 👁️":
                    $this->showBadHabits($chat_id);
                    break;
                default:
                    if (cache($habitKey) == 'adding_habit') {

                        $this->addinghabit($chat_id, $text,  $addHabitType,  $habitKey);
                    } elseif (cache($editHabitType) == 'edit_habit') {

                        $this->editingHabit($chat_id, $text, $editHabitType, $habitKey);
                    } elseif ($update->has('callback_query')) {

                        $callbackQuery = $update->getCallbackQuery();

                        $callbackData = $callbackQuery->getData();

                        if (str_starts_with($callbackData, 'delete_')) {

                            $habit_id = str_replace('delete_', '', $callbackData);

                            $this->destroyHabit($chat_id, $habit_id);
                        } elseif (str_starts_with($callbackData, 'edit_')) {

                            $habit_id = str_replace('edit_', '', $callbackData);

                            $this->editHabit($chat_id, $habit_id, $editHabitType, $habitKey);
                        }
                    }
            }
        } catch (\Exception $exception) {
            report($exception);
            Log::error($exception->getMessage());
            return response('Error', 200);
        }
    }
    public function editHabit(string $chat_id, string $habit_id, string $editHabitType, string $habitKey)
    {
        cache([$editHabitType => 'edit_habit'], now()->addMinutes(10));
        cache([$habitKey => $habit_id], now()->addMinutes(10));

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Iltimos ushbu odat uchun yangi nomni kiriting:',
        ]);
    }
    public function editingHabit(string $chat_id, string $text, string $editHabitType, string $habitKey)
    {
        Habit::find(cache($habitKey))->update([
            'user_id' => $chat_id,
            'name' => $text
        ]);

        cache()->forget($habitKey);
        cache()->forget($editHabitType);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => "Yangiz odat muvaffaqiyatli o'zgartirildi",
        ]);
    }
    public function destroyHabit(string $chat_id, string $habit_id)
    {
        Habit::destroy($habit_id);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Odat ochirildi',
        ]);
    }
    public function showBadHabits(string $chat_id)
    {

        $badHabits = Habit::where('user_id', $chat_id)->where('is_bad_habit', true)->get();

        if ($badHabits->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chat_id,
                'text' => "Siz hali yomon odatlarni qo'shmadingiz.",
            ]);
        } else {

            foreach ($badHabits as $badHabit) {
                $inlineKeyboard = [
                    [
                        ['text' => "✏️ O'zgartirish", 'callback_data' => 'edit_' . $badHabit->id],
                        ['text' => "🗑️ O'chirish", 'callback_data' => 'delete_' . $badHabit->id],
                    ]
                ];

                Telegram::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => '❌ ' . $badHabit->name,
                    'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
                ]);
            }
        }
    }
    public function showGoodHabits(string $chat_id)
    {

        $goodHabits = Habit::where('user_id', $chat_id)->where('is_bad_habit', false)->get();

        if ($goodHabits->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chat_id,
                'text' => "Siz hali yaxshi odatlarni qo'shmadingiz.",
            ]);
        } else {

            foreach ($goodHabits as $goodHabit) {
                $inlineKeyboard = [
                    [
                        ['text' => '✏️ Ozgartirish', 'callback_data' => 'edit_' . $goodHabit->id],
                        ['text' => '🗑️ Ochirish', 'callback_data' => 'delete_' . $goodHabit->id],
                    ]
                ];

                Telegram::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => '✅ ' . $goodHabit->name,
                    'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
                ]);
            }
        }
    }

    public function habit(string $chat_id)
    {
        $keyboard = Keyboard::make()->row([Keyboard::button('Yaxshi Odatlar'), Keyboard::button('Yomon Odatlar')])->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true);

        return Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Xush kelibsiz odatlarni tanlang',
            'reply_markup' => $keyboard
        ]);
    }

    public function goodHabit(string $chat_id)
    {
        $keyboard = Keyboard::make()->row([Keyboard::button("yaxshi odatlarni ko'rish 👁️"), Keyboard::button("Yaxshi Odat qo'shish 🟢")])->row([Keyboard::button('⬅️ Ortga')])->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true);

        return Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Yaxshi Odatlar',
            'reply_markup' => $keyboard
        ]);
    }

    public function badHabit(string $chat_id)
    {
        $keyboard = Keyboard::make()->row([Keyboard::button("Yomon odatlarni ko'rish 👁️"), Keyboard::button("Yomon Odat qo'shish 🟢")])->row([Keyboard::button('⬅️ Ortga')])->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true);

        return Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Yomon Odatlar',
            'reply_markup' => $keyboard
        ]);
    }

    public function addhabit(string $chat_id, string $habitType, string $addHabitType, string $habitKey)
    {
        cache([$addHabitType => $habitType], now()->addMinutes(10));
        cache([$habitKey => 'adding_habit'], now()->addMinutes(10));

        $keyboard = Keyboard::make()->row([Keyboard::button('⬅️ Ortga')])->setResizeKeyboard(true)
            ->setOneTimeKeyboard(true);
        $habitType = $habitType == 'good' ? 'yaxshi' : 'yomon';

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => "iltimos " . $habitType .  " odat nomini yozing",
            'reply_markup' => $keyboard
        ]);
    }
    public function addinghabit(string $chat_id, $text, string $addHabitType, string $habitKey)
    {
        Habit::create([
            'user_id' => $chat_id,
            'name' => $text,
            'is_bad_habit' =>  cache($addHabitType) == 'bad' ? true : false
        ]);

        Telegram::sendMessage([
            'chat_id' => $chat_id,
            'text' => "Yangi odat qo'shildi!🎆",
        ]);

        if (cache($addHabitType) == 'bad') {
            $this->badHabit($chat_id);
        } else {
            $this->goodHabit($chat_id);
        }
        cache()->forget($addHabitType);
        cache()->forget($habitKey);
    }
}
