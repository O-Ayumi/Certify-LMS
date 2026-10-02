<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => (string) $this->id,
            'title' => (string) ($data['title'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'url' => (string) ($data['url'] ?? route('notifications.show', $this->id)),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'notification_type' => (string) ($data['notification_type'] ?? $this->type),
            'is_read' => $this->read_at !== null,
        ];
    }
}
