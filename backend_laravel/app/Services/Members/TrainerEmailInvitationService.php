<?php
namespace App\Services\Members;
use App\Models\Gym;
use App\Models\TrainerEmailInvitation;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Users\ManagedUserService;
use App\Services\Notification\NotificationService;
use App\Services\Notification\TransactionalEmailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class TrainerEmailInvitationService {
    public function __construct(private readonly ManagedUserService $managedUserService, private readonly NotificationService $notificationService, private readonly TransactionalEmailService $transactionalEmailService) {}
    /** @param array<string,mixed> $payload */
    public function invite(User $actor, Gym $gym, array $payload): TrainerEmailInvitation {
        $email = strtolower(trim((string) $payload['email']));
        $existingUser = User::query()->where('email', $email)->first();
        $existingProfile = $existingUser?->managedTrainerProfile;
        if ($existingProfile && (int) $existingProfile->gym_id !== (int) $gym->id) throw ValidationException::withMessages(['email' => ['This trainer is already assigned to another gym. Use a dedicated transfer flow instead.']]);
        if (! $existingUser && ! $this->transactionalEmailService->isEnabled($gym->id)) throw ValidationException::withMessages(['email' => ['Transactional email is disabled for this gym. Enable it in Gym Settings before sending a trainer invitation.']]);
        TrainerEmailInvitation::query()->where('gym_id',$gym->id)->where('invited_email',$email)->where('status','pending')->update(['status'=>'superseded']);
        $invite = TrainerEmailInvitation::query()->create(['token'=>(string) Str::uuid(),'gym_id'=>$gym->id,'branch_id'=>$payload['branch_id'] ?? null,'invited_user_id'=>$existingUser?->id,'invited_by_user_id'=>$actor->id,'invited_name'=>$payload['name'],'invited_email'=>$email,'status'=>'pending','payload'=>$payload,'expires_at'=>now()->addDays(7)]);
        if ($existingUser && $existingUser->hasRole(\App\Enums\RoleName::Trainer->value)) {
            $this->notificationService->create(user:$existingUser,type:'trainer_gym_invitation',title:'Gym trainer invitation',body:$gym->name.' wants to add you as a trainer.',gymId:$gym->id,branchId:$payload['branch_id'] ?? null,createdByUserId:$actor->id,data:['invitation_id'=>$invite->id,'status'=>'pending']);
            return $invite;
        }
        $invite->load('gym');
        $url = URL::temporarySignedRoute('trainer-email-invitations.review', $invite->expires_at, ['invitation'=>$invite->id,'token'=>$invite->token]);
        $this->transactionalEmailService->sendTo(
            $email,
            'Approve your trainer invitation for '.$invite->gym->name,
            $invite->gym->name.' has invited you to join as a trainer.',
            ['Review your invitation: '.$url],
            $gym->id,
            'trainer_enrollment_invitation',
        );
        return $invite;
    }
    public function respond(TrainerEmailInvitation $invite, bool $accept): TrainerEmailInvitation {
        if ($invite->status !== 'pending' || $invite->expires_at->isPast()) throw ValidationException::withMessages(['invitation'=>['This trainer invitation is no longer active.']]);
        if ($accept) DB::transaction(function () use ($invite): void { $payload=$invite->payload; $payload['name']=$invite->invited_name; $payload['email']=$invite->invited_email; $trainer=$this->managedUserService->upsertTrainer(User::query()->where('email',$invite->invited_email)->first(), $invite->gym, $payload); $invite->update(['status'=>'accepted','responded_at'=>now()]); DB::afterCommit(fn () => $this->transactionalEmailService->send($trainer, 'Trainer enrollment confirmed — '.$invite->gym->name, 'Your trainer enrollment has been approved.', [], $invite->gym_id, 'trainer_enrollment_confirmation')); });
        else $invite->update(['status'=>'rejected','responded_at'=>now()]);
        return $invite;
    }

    public function respondForUser(User $user, TrainerEmailInvitation $invite, bool $accept): TrainerEmailInvitation {
        abort_unless((int) $invite->invited_user_id === (int) $user->id, 404);
        return $this->respond($invite, $accept);
    }
}
