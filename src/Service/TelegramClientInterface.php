<?php

declare(strict_types=1);

namespace App\Service;

interface TelegramClientInterface
{
    /**
     * @param array<string, mixed>|null $replyMarkup
     *
     * @return int Telegram message id
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): int;

    /**
     * @param array<string, mixed>|null $replyMarkup
     */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void;

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void;

    /**
     * @return int Telegram message id
     */
    public function sendVideo(int $chatId, string $filePath, string $caption): int;

    /**
     * @return int Telegram message id
     */
    public function sendAudio(int $chatId, string $filePath, string $caption): int;

    /**
     * @return int Telegram message id
     */
    public function sendDocument(int $chatId, string $filePath, string $caption): int;
}
