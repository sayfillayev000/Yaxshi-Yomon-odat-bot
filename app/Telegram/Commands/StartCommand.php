<?php

namespace App\Telegram\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class StartCommand extends Command
{
    protected string $name = 'start';
    protected string $description = 'Start Command to get you started';

    public function handle()
    {
        // $keyboard = Keyboard::make()->row([Keyboard::button('Yaxshi Odatlar'), Keyboard::button('Yomon Odatlar')])->setResizeKeyboard(true)
        //     ->setOneTimeKeyboard(true);

        // $this->replyWithMessage([
        //     'text' => 'Xush kelibsiz odatlarni tanlang',
        //     'reply_markup' => $keyboard
        // ]);
    }
}
