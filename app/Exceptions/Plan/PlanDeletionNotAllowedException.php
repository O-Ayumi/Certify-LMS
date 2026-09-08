<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** 状態または参照によりプランを削除できない場合の例外（HTTP 409）。 */
final class PlanDeletionNotAllowedException extends ConflictHttpException
{
    public static function forNotDraft(?\Throwable $previous = null): self
    {
        return new self('下書きのプランのみ削除できます。', $previous);
    }

    public static function forReferenced(?\Throwable $previous = null): self
    {
        return new self('ユーザーまたはプラン履歴から参照されているため、このプランは削除できません。', $previous);
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
