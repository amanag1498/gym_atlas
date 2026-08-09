<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Seeder;

class ComprehensiveExerciseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $actor = User::query()->where('email', 'platform.admin@gym.local')->first()
            ?? User::query()->where('active_role', 'platform_admin')->first()
            ?? User::query()->first();

        if (! $actor) {
            return;
        }

        foreach ($this->exercises() as [$name, $primary, $secondary, $equipment, $difficulty, $instructions]) {
            $exercise = Exercise::query()->firstOrCreate(
                ['name' => $name, 'is_global' => true],
                [
                    'created_by_user_id' => $actor->id,
                    'muscle_group' => $primary,
                    'secondary_muscles' => $secondary,
                    'equipment' => $equipment,
                    'difficulty' => $difficulty,
                    'instructions' => $instructions,
                    'status' => 'approved',
                    'is_active' => true,
                ],
            );

            if (blank($exercise->instructions) || $exercise->instructions === 'Maintain controlled tempo and stable posture throughout each rep.') {
                $exercise->update([
                    'muscle_group' => $primary,
                    'secondary_muscles' => $secondary,
                    'equipment' => $equipment,
                    'difficulty' => $difficulty,
                    'instructions' => $instructions,
                ]);
            }
        }
    }

    /** @return array<int, array{string, string, array<int, string>, string, string, string}> */
    private function exercises(): array
    {
        return [
            ['Bodyweight Squat', 'quads', ['glutes', 'core'], 'bodyweight', 'beginner', 'Stand tall, brace, sit the hips down between the knees, then drive through the whole foot to stand.'],
            ['Goblet Squat', 'quads', ['glutes', 'core'], 'dumbbell', 'beginner', 'Hold the weight at chest height, keep the torso tall, squat to a comfortable depth, and stand without the knees collapsing inward.'],
            ['Back Squat', 'quads', ['glutes', 'hamstrings', 'core'], 'barbell', 'intermediate', 'Set the bar securely across the upper back, brace, descend under control, and drive upward while keeping the bar over mid-foot.'],
            ['Front Squat', 'quads', ['core', 'glutes'], 'barbell', 'advanced', 'Rest the bar on the front shoulders, keep elbows high, brace, squat vertically, and stand while maintaining the rack position.'],
            ['Box Squat', 'quads', ['glutes', 'hamstrings'], 'barbell and box', 'intermediate', 'Reach the hips toward the box under control, touch without relaxing, then drive through the feet to stand.'],
            ['Leg Press', 'quads', ['glutes', 'hamstrings'], 'machine', 'beginner', 'Place the feet securely, lower the sled without the pelvis rolling, then press through the full foot without locking the knees hard.'],
            ['Hack Squat', 'quads', ['glutes'], 'machine', 'intermediate', 'Keep the back supported, descend to a controlled depth, and extend the knees and hips without bouncing.'],
            ['Leg Extension', 'quads', [], 'machine', 'beginner', 'Align the knee with the machine pivot, extend smoothly, pause briefly, and lower without dropping the stack.'],
            ['Reverse Lunge', 'quads', ['glutes', 'core'], 'bodyweight', 'beginner', 'Step backward, lower both knees under control, keep the front foot planted, and push through it to return.'],
            ['Walking Lunge', 'quads', ['glutes', 'core'], 'bodyweight', 'intermediate', 'Take a stable forward step, lower with an upright torso, drive through the lead foot, and continue with controlled strides.'],
            ['Bulgarian Split Squat', 'quads', ['glutes', 'core'], 'bench and dumbbells', 'intermediate', 'Elevate the rear foot, keep most pressure on the front leg, descend vertically, and stand through the front foot.'],
            ['Step-Up', 'quads', ['glutes'], 'box', 'beginner', 'Place the whole lead foot on the box, drive through that leg without pushing off excessively from the floor, and lower slowly.'],
            ['Romanian Deadlift', 'hamstrings', ['glutes', 'back'], 'barbell', 'intermediate', 'Brace, soften the knees, push the hips backward while keeping the load close, then squeeze the glutes to stand.'],
            ['Conventional Deadlift', 'back', ['glutes', 'hamstrings', 'quads'], 'barbell', 'advanced', 'Brace with the bar over mid-foot, push the floor away, keep the bar close, and finish tall without leaning back.'],
            ['Sumo Deadlift', 'glutes', ['quads', 'hamstrings', 'back'], 'barbell', 'advanced', 'Use a wide stance with knees tracking over toes, brace, drive the floor apart, and keep the bar close throughout.'],
            ['Trap Bar Deadlift', 'glutes', ['quads', 'hamstrings', 'back'], 'trap bar', 'intermediate', 'Stand centered in the bar, brace, push through the floor, and finish with stacked ribs and hips.'],
            ['Good Morning', 'hamstrings', ['glutes', 'back'], 'barbell', 'advanced', 'With a braced torso and soft knees, hinge the hips backward, stop before spinal position changes, and return by driving the hips forward.'],
            ['Hamstring Curl', 'hamstrings', ['calves'], 'machine', 'beginner', 'Keep the hips anchored, curl through a comfortable range, pause, and lower the pad slowly.'],
            ['Nordic Hamstring Curl', 'hamstrings', ['glutes'], 'bodyweight', 'advanced', 'Keep hips extended, lower the body as one line using the hamstrings, catch safely with the hands, and assist the return as needed.'],
            ['Glute Bridge', 'glutes', ['hamstrings', 'core'], 'bodyweight', 'beginner', 'Brace the abdomen, drive through the heels, extend the hips without arching the lower back, then lower under control.'],
            ['Hip Thrust', 'glutes', ['hamstrings'], 'barbell', 'intermediate', 'Support the upper back, keep the chin tucked, drive the hips up to a neutral torso, pause, and lower smoothly.'],
            ['Cable Kickback', 'glutes', ['hamstrings'], 'cable machine', 'beginner', 'Hold a stable torso and extend one hip backward without rotating the pelvis or arching the lower back.'],
            ['Standing Calf Raise', 'calves', ['ankles'], 'machine', 'beginner', 'Rise onto the balls of the feet, pause at the top, and lower the heels through a comfortable range.'],
            ['Seated Calf Raise', 'calves', ['ankles'], 'machine', 'beginner', 'Keep the knees under the pad, lift the heels fully, pause, and lower slowly without bouncing.'],
            ['Tibialis Raise', 'shins', ['ankles'], 'bodyweight', 'beginner', 'Keep the heels planted, lift the toes toward the shins, pause, and lower with control.'],
            ['Push-Up', 'chest', ['shoulders', 'triceps', 'core'], 'bodyweight', 'beginner', 'Keep a straight body line, lower the chest between the hands, and press away while keeping the elbows controlled.'],
            ['Incline Push-Up', 'chest', ['shoulders', 'triceps'], 'bench', 'beginner', 'Place the hands on a stable elevated surface, keep the body rigid, lower the chest, and press back to the start.'],
            ['Dumbbell Bench Press', 'chest', ['shoulders', 'triceps'], 'dumbbells', 'beginner', 'Set the shoulder blades, lower the dumbbells beside the chest, and press upward without losing wrist alignment.'],
            ['Barbell Bench Press', 'chest', ['shoulders', 'triceps'], 'barbell', 'intermediate', 'Plant the feet, set the shoulder blades, lower the bar to the lower chest, and press it back over the shoulders.'],
            ['Incline Dumbbell Press', 'upper chest', ['shoulders', 'triceps'], 'dumbbells', 'intermediate', 'Use a moderate incline, keep the shoulders set, lower with control, and press without shrugging.'],
            ['Machine Chest Press', 'chest', ['shoulders', 'triceps'], 'machine', 'beginner', 'Adjust the seat for a comfortable pressing line, keep the back supported, press smoothly, and return under control.'],
            ['Cable Fly', 'chest', ['front delts'], 'cable machine', 'beginner', 'Keep a soft elbow bend, bring the hands together in an arc, squeeze the chest, and return without overstretching.'],
            ['Chest Dip', 'chest', ['triceps', 'shoulders'], 'bodyweight', 'intermediate', 'Use stable shoulders, lean slightly forward, lower only as far as comfortable, and press back without bouncing.'],
            ['Seated Cable Row', 'back', ['biceps', 'rear delts'], 'cable machine', 'beginner', 'Sit tall, pull the handle toward the lower ribs, keep the shoulders down, and return without rounding forward.'],
            ['Chest-Supported Row', 'back', ['biceps', 'rear delts'], 'bench and dumbbells', 'beginner', 'Keep the chest supported, row toward the hips, pause with shoulder blades together, and lower fully.'],
            ['Bent-Over Barbell Row', 'back', ['biceps', 'rear delts'], 'barbell', 'intermediate', 'Hold a stable hip hinge, brace the torso, row the bar toward the lower ribs, and lower without changing back position.'],
            ['One-Arm Dumbbell Row', 'back', ['biceps', 'rear delts'], 'dumbbell', 'beginner', 'Brace on a stable support, pull the elbow toward the hip without rotating the torso, and lower to a full reach.'],
            ['Lat Pulldown', 'back', ['biceps'], 'cable machine', 'beginner', 'Keep the chest tall, pull the bar toward the upper chest by driving elbows down, and return without swinging.'],
            ['Neutral-Grip Pulldown', 'back', ['biceps'], 'cable machine', 'beginner', 'Use a neutral handle, keep ribs stacked, pull elbows toward the sides, and control the overhead return.'],
            ['Pull-Up', 'back', ['biceps', 'core'], 'bodyweight', 'advanced', 'Start from a controlled hang, pull the chest toward the bar without kicking, and lower to full arm extension.'],
            ['Assisted Pull-Up', 'back', ['biceps', 'core'], 'assisted pull-up machine', 'beginner', 'Use enough assistance for smooth reps, pull without swinging, and lower to a controlled full stretch.'],
            ['Face Pull', 'rear delts', ['upper back', 'rotator cuff'], 'cable machine', 'beginner', 'Pull the rope toward eye level, separate the hands, keep shoulders down, and return slowly.'],
            ['Straight-Arm Pulldown', 'back', ['triceps', 'core'], 'cable machine', 'beginner', 'Keep arms nearly straight and ribs stacked, sweep the handle toward the thighs, and return under control.'],
            ['Dumbbell Shoulder Press', 'shoulders', ['triceps'], 'dumbbells', 'beginner', 'Brace the torso, press the dumbbells overhead without excessive back arch, and lower to a comfortable depth.'],
            ['Barbell Overhead Press', 'shoulders', ['triceps', 'core'], 'barbell', 'intermediate', 'Brace the glutes and abdomen, press the bar vertically past the face, finish overhead, and lower with control.'],
            ['Landmine Press', 'shoulders', ['chest', 'triceps'], 'landmine', 'beginner', 'Hold the bar end near the shoulder, keep the ribs down, press forward and upward, then return smoothly.'],
            ['Arnold Press', 'shoulders', ['triceps'], 'dumbbells', 'intermediate', 'Rotate from palms-in to palms-forward while pressing overhead, keeping the movement controlled and the torso stable.'],
            ['Lateral Raise', 'shoulders', ['upper traps'], 'dumbbells', 'beginner', 'Raise the arms to about shoulder height with soft elbows, avoid swinging, and lower slowly.'],
            ['Cable Lateral Raise', 'shoulders', ['upper traps'], 'cable machine', 'beginner', 'Stand stable, lift the arm out to the side against cable tension, and lower without leaning.'],
            ['Rear Delt Fly', 'rear delts', ['upper back'], 'dumbbells', 'beginner', 'Hold a supported hinge, sweep the arms outward with soft elbows, and avoid shrugging.'],
            ['Upright Row', 'shoulders', ['upper traps'], 'cable machine', 'intermediate', 'Pull the handle upward with elbows leading only through a pain-free range, then lower under control.'],
            ['Dumbbell Shrug', 'upper traps', ['grip'], 'dumbbells', 'beginner', 'Stand tall, elevate the shoulders straight upward, pause, and lower without rolling them.'],
            ['Bicep Curl', 'biceps', ['forearms'], 'dumbbells', 'beginner', 'Keep the upper arms still, curl without swinging, squeeze briefly, and lower to full extension.'],
            ['Hammer Curl', 'biceps', ['forearms'], 'dumbbells', 'beginner', 'Keep palms facing inward, curl with stable elbows, and lower slowly without torso movement.'],
            ['Incline Dumbbell Curl', 'biceps', ['forearms'], 'dumbbells and bench', 'intermediate', 'Keep the shoulders against the bench, curl without moving the upper arm forward, and lower fully.'],
            ['Cable Curl', 'biceps', ['forearms'], 'cable machine', 'beginner', 'Stand tall, keep elbows near the ribs, curl against constant tension, and return slowly.'],
            ['Triceps Pushdown', 'triceps', ['shoulders'], 'cable machine', 'beginner', 'Keep elbows pinned near the sides, extend fully without leaning, and control the return.'],
            ['Overhead Triceps Extension', 'triceps', ['shoulders'], 'dumbbell', 'beginner', 'Keep the upper arms near the ears, bend the elbows to lower the load, and extend without flaring excessively.'],
            ['Skull Crusher', 'triceps', ['shoulders'], 'ez bar', 'intermediate', 'Keep upper arms stable, bend the elbows to lower the bar safely, and extend without changing shoulder position.'],
            ['Close-Grip Bench Press', 'triceps', ['chest', 'shoulders'], 'barbell', 'intermediate', 'Use a comfortable close grip, keep elbows controlled, lower to the chest, and press while maintaining shoulder position.'],
            ['Plank', 'core', ['shoulders', 'glutes'], 'bodyweight', 'beginner', 'Brace the abdomen and glutes, keep a straight line from head to heels, and breathe without letting the hips sag.'],
            ['Side Plank', 'obliques', ['shoulders', 'glutes'], 'bodyweight', 'beginner', 'Stack the shoulders and hips, lift the body into a straight line, and maintain steady breathing.'],
            ['Dead Bug', 'core', ['hip flexors'], 'bodyweight', 'beginner', 'Keep the lower back gently supported, extend opposite arm and leg slowly, then return without losing trunk position.'],
            ['Bird Dog', 'core', ['glutes', 'back'], 'bodyweight', 'beginner', 'From hands and knees, extend opposite arm and leg without rotating the pelvis, pause, and return.'],
            ['Hanging Knee Raise', 'core', ['hip flexors', 'grip'], 'pull-up bar', 'intermediate', 'Hang without swinging, curl the knees toward the torso, and lower slowly to a stable hang.'],
            ['Cable Crunch', 'core', ['hip flexors'], 'cable machine', 'intermediate', 'Keep the hips mostly fixed, curl the ribs toward the pelvis against resistance, and return under control.'],
            ['Cable Wood Chop', 'core', ['obliques'], 'cable machine', 'beginner', 'Rotate through the upper torso and hips as appropriate while keeping the cable path controlled and the spine tall.'],
            ['Pallof Press', 'core', ['obliques', 'glutes'], 'cable machine', 'beginner', 'Stand perpendicular to the cable, brace, press the handle away without rotating, pause, and return.'],
            ['Ab Wheel Rollout', 'core', ['shoulders', 'lats'], 'ab wheel', 'advanced', 'Brace the trunk, roll forward only while the spine remains stable, then pull back using the abdomen and lats.'],
            ['Farmer Carry', 'full body', ['core', 'grip', 'upper traps'], 'dumbbells', 'intermediate', 'Stand tall with heavy loads at the sides, walk with short controlled steps, and avoid leaning.'],
            ['Suitcase Carry', 'core', ['grip', 'obliques'], 'dumbbell', 'intermediate', 'Carry one load at the side while staying upright and resisting lateral bending.'],
            ['Kettlebell Swing', 'glutes', ['hamstrings', 'core'], 'kettlebell', 'intermediate', 'Hinge the hips, snap them forward to float the bell, keep arms relaxed, and guide it back into the next hinge.'],
            ['Turkish Get-Up', 'full body', ['core', 'shoulders', 'glutes'], 'kettlebell', 'advanced', 'Move through each position slowly while keeping the loaded arm vertical and the shoulder stable.'],
            ['Sled Push', 'conditioning', ['quads', 'glutes', 'calves'], 'sled', 'intermediate', 'Brace into the handles, take powerful short steps, and keep the hips and shoulders moving together.'],
            ['Battle Rope Slam', 'conditioning', ['shoulders', 'core'], 'battle rope', 'intermediate', 'Brace the torso, raise the ropes, and drive them down forcefully while keeping a stable athletic stance.'],
            ['Jump Rope', 'conditioning', ['calves', 'coordination'], 'jump rope', 'beginner', 'Use small quiet jumps, rotate the rope mainly from the wrists, and maintain a relaxed upright posture.'],
            ['Mountain Climber', 'conditioning', ['core', 'shoulders', 'hip flexors'], 'bodyweight', 'beginner', 'Hold a strong plank and alternate driving the knees forward without bouncing the hips.'],
            ['Bike Sprint', 'conditioning', ['quads', 'glutes'], 'stationary bike', 'beginner', 'Set a safe resistance, accelerate with control, maintain posture during the interval, and recover fully between efforts.'],
            ['Rowing Ergometer', 'conditioning', ['back', 'legs', 'core'], 'rowing machine', 'beginner', 'Drive with the legs, then lean slightly and pull; reverse the sequence smoothly on the recovery.'],
            ['Burpee', 'conditioning', ['full body'], 'bodyweight', 'intermediate', 'Move from standing to a stable plank and back with control; use a step-back variation when impact needs to be reduced.'],
            ['Cat Cow', 'mobility', ['spine'], 'bodyweight', 'beginner', 'On hands and knees, alternate gentle spinal extension and flexion while moving with the breath.'],
            ['Worlds Greatest Stretch', 'mobility', ['hips', 'thoracic spine'], 'bodyweight', 'beginner', 'Step into a long lunge, support the body, rotate the chest toward the lead leg, and move only through a comfortable range.'],
            ['90/90 Hip Rotation', 'mobility', ['hips', 'glutes'], 'bodyweight', 'beginner', 'Sit with both knees bent and rotate them side to side under control without forcing the range.'],
            ['Thoracic Rotation', 'mobility', ['upper back', 'shoulders'], 'bodyweight', 'beginner', 'Keep the hips stable while rotating through the upper back, following the moving hand with the eyes.'],
            ['Ankle Dorsiflexion Mobilization', 'mobility', ['calves', 'ankles'], 'bodyweight', 'beginner', 'Keep the heel down and guide the knee forward over the toes through a comfortable range.'],
            ['Band Pull-Apart', 'upper back', ['rear delts', 'rotator cuff'], 'resistance band', 'beginner', 'Hold the band at chest height, separate the hands by squeezing the shoulder blades, and return slowly.'],
            ['Band Row', 'back', ['biceps', 'rear delts'], 'resistance band', 'beginner', 'Anchor the band securely, sit or stand tall, pull elbows behind the body, and control the return.'],
            ['Band Chest Press', 'chest', ['shoulders', 'triceps'], 'resistance band', 'beginner', 'Anchor the band behind the body, brace, press forward evenly, and return with control.'],
            ['Band Romanian Deadlift', 'hamstrings', ['glutes', 'back'], 'resistance band', 'beginner', 'Stand on the band, hinge with a neutral torso, and drive the hips forward against the resistance.'],
        ];
    }
}
