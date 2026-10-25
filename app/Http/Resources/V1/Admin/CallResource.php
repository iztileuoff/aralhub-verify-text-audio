<?php

namespace App\Http\Resources\V1\Admin;

use App\Models\Audio;
use App\Models\CallRecording;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CallRecording */
class CallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->visibleFilename($request),
            'source_url' => $this->sourceUrl(),
            'source_url_expires_at' => $this->urlExpiresAt()->toIso8601String(),
            'source_duration_ms' => $this->source_duration_ms,
            'trimmed_url' => $this->trimmedUrl(),
            'trim_start_ms' => $this->trim_start_ms,
            'trim_end_ms' => $this->trim_end_ms,
            'trimmed_duration' => $this->trimmed_duration,
            'trimmed_duration_seconds' => $this->trimmed_duration === null
                ? null
                : round($this->trimmed_duration / Audio::SAMPLE_RATE, 2),
            'transcript' => $this->transcript,
            'status' => $this->status,
            'reject_reason' => $this->reject_reason,
            'rejections_count' => $this->rejections_count,
            'skip_reason' => $this->skip_reason,
            'skip_comment' => $this->skip_comment,
            'trimmer' => $this->person($this->trimmer),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'moderator' => $this->person($this->moderator),
            'moderated_at' => $this->moderated_at?->toIso8601String(),
        ];
    }

    /**
     * The customer's file names carry the caller's phone number and the
     * operator's name. Admins see them; trimmers get the neutral name the
     * file has in the bucket (e.g. "call-000123.mp3").
     */
    private function visibleFilename(Request $request): string
    {
        $isAdmin = in_array('admin', $request->user()?->role?->getPermissionsArray() ?? [], true);

        return $isAdmin ? $this->original_filename : basename($this->source_path);
    }

    /**
     * @return array{id: int, first_name: string, last_name: string}|null
     */
    private function person(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ] : null;
    }
}
