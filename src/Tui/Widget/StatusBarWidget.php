<?php

declare(strict_types=1);

namespace App\Tui\Widget;

use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Bottom status bar showing model name, token count, and version.
 */
final class StatusBarWidget extends TextWidget
{
    private string $model = 'kimi-k2.5:cloud';
    private int $tokenCount = 0;

    public function __construct()
    {
        parent::__construct($this->buildText(), truncate: true);
        $this->addStyleClass('bg-gray-800');
        $this->addStyleClass('text-cyan-400');
        $this->addStyleClass('p-1');
        $this->addStyleClass('bold');
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
        $this->setText($this->buildText());
    }

    public function setTokenCount(int $count): void
    {
        $this->tokenCount = $count;
        $this->setText($this->buildText());
    }

    private function buildText(): string
    {
        return sprintf(' DevBot v0.1  |  Model: %s  |  Tokens: %d  |  Ctrl+Q quit', $this->model, $this->tokenCount);
    }
}
