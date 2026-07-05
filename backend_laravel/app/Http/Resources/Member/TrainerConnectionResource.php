<?php

namespace App\Http\Resources\Member;

use App\Support\Profiles\TrainerProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainerConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $chatEnabled = $this->assignedTrainer !== null;
        $trainerSummary = $this->assignedTrainer
            ? TrainerProfilePresenter::present(
                $this->assignedTrainer->managedTrainerProfile,
                $this->assignedTrainer,
                [
                    'include_client_count' => true,
                    'contact_enabled' => $chatEnabled,
                    'contact_mode' => 'chat',
                    'contact_label' => 'Message Trainer',
                    'request_enabled' => false,
                ],
            )
            : null;

        return [
            'assigned_trainer' => $trainerSummary,
            'trainer_chat_placeholder' => [
                'enabled' => $chatEnabled,
                'recipient_user_id' => $this->assignedTrainer?->id,
                'message' => $chatEnabled
                    ? 'Realtime trainer chat is available for your assigned trainer.'
                    : 'Assign a trainer to enable realtime chat.',
            ],
            'assigned_workout_shortcut' => [
                'enabled' => $chatEnabled,
                'label' => 'Assigned Workout',
                'destination' => 'workout',
            ],
            'trainer_reminders_placeholder' => [
                'enabled' => false,
                'message' => 'Trainer reminders will be enabled in a later phase.',
            ],
            'workout_feedback_placeholder' => [
                'enabled' => false,
                'message' => 'Workout feedback will be enabled in a later phase.',
            ],
        ];
    }
}
