<?php
namespace App\Services;

use Core\Container;

class TestMailer
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function sendTest(string $to, string $body): void
    {
        // stub: integrate with actual mailer in project
        @mail($to, 'Test email', $body, 'From: ' . ($this->fromHeader()));
    }

    private function fromHeader(): string
    {
        $settings = $this->container->get(SettingsService::class);
        $from = $settings->get('mail_from', 'no-reply@example.com');
        $name = $settings->get('mail_from_name', 'SteelRoot');
        return "{$name} <{$from}>";
    }
}
