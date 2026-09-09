<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** プランの状態遷移が許可されない場合の例外（HTTP 409）。 */
final class PlanInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(?\Throwable $previous = null): self
    {
        return new self('下書きのプランのみ公開できます。', $previous);
    }

    public static function forArchive(?\Throwable $previous = null): self
    {
        return new self('公開中のプランのみアーカイブできます。', $previous);
    }

    public static function forUnarchive(?\Throwable $previous = null): self
    {
        return new self('アーカイブされたプランのみ復元できます。', $previous);
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
