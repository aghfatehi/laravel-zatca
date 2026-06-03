<?php

namespace Aghfatehi\Zatca\Logging;

use Aghfatehi\Zatca\Enums\LogLevel;
use Illuminate\Support\Facades\Log;

class ZatcaLogger
{
    private bool $enabled;
    private string $channel;
    private string $level;
    private bool $maskPii;

    public function __construct()
    {
        $this->enabled = config('zatca.logging.enabled', true);
        $this->channel = config('zatca.logging.channel', 'stack');
        $this->level = config('zatca.logging.level', 'info');
        $this->maskPii = config('zatca.logging.mask_pii', true);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::Warning, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }

    private function log(LogLevel $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->maskPii) {
            $context = $this->maskSensitiveData($context);
        }

        $context['_zatca'] = true;

        Log::channel($this->channel)->log($level->value, "[ZATCA] {$message}", $context);
    }

    private function maskSensitiveData(array $data): array
    {
        $sensitiveKeys = ['otp', 'secret', 'password', 'token', 'authorization', 'private_key', 'csr'];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                foreach ($sensitiveKeys as $sk) {
                    if (stripos($key, $sk) !== false) {
                        $data[$key] = substr($value, 0, 4) . '****';
                        break;
                    }
                }
                if (strlen($value) > 500 && !isset($data['_truncated'])) {
                    $data[$key] = '[TRUNCATED]';
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
