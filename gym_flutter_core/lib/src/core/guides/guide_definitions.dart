/// Bundled guide copy. IDs are versioned independently of app releases.
class GuideStep {
  const GuideStep(this.target, this.title, this.description);
  final String target;
  final String title;
  final String description;
}

class GuideDefinition {
  const GuideDefinition(this.id, this.title, this.destination, this.steps);
  final String id;
  final String title;
  final String destination;
  final List<GuideStep> steps;
  int get version => int.parse(id.split('_v').last);
}

const memberGuides = <GuideDefinition>[
  GuideDefinition('member_home_v1', 'Gym Atlas overview', 'Home', [
    GuideStep(
      'home',
      'Your daily dashboard',
      'Check today’s activity and return here for your next workout.',
    ),
    GuideStep(
      'train',
      'Train at your own pace',
      'Open Train to choose an assigned workout or start a custom session.',
    ),
    GuideStep(
      'progress',
      'See your progress',
      'Track body measurements, steps and strength over time.',
    ),
    GuideStep(
      'coach',
      'Your coaching connections',
      'Open your trainer connection to see assigned plans and send messages.',
    ),
    GuideStep(
      'discover',
      'Find a gym',
      'Explore gyms and manage your trial requests.',
    ),
    GuideStep(
      'settings',
      'You control your account',
      'Open Settings for privacy, consent, communication preferences and Replay guides.',
    ),
  ]),
  GuideDefinition('member_workouts_v1', 'Workout Books', 'Train → Workout Book', [
    GuideStep(
      'books',
      'Find your next workout',
      'Browse Workout Books for plans you can preview before choosing.',
    ),
    GuideStep(
      'browse',
      'Browse and compare',
      'Use the categories and search to find a suitable workout. Load more when available to continue browsing.',
    ),
    GuideStep(
      'select',
      'Preview before you save',
      'Open a workout to review its days and exercises. Choose to save it before it becomes your personal plan.',
    ),
  ]),
  GuideDefinition(
    'member_workout_builder_v1',
    'Build a Workout',
    'Train → Workout Book → Builder',
    [
      GuideStep(
        'details',
        'Name your plan',
        'Set the goal, difficulty, duration and notes before building the weekly structure.',
      ),
      GuideStep(
        'day',
        'Choose the workout day',
        'Select the day you are editing, then add the focus and day notes that explain the session.',
      ),
      GuideStep(
        'exercise',
        'Pick an exercise',
        'Search the exercise library, filter by equipment, choose tracking mode, and set the work target.',
      ),
      GuideStep(
        'grouping',
        'Group advanced work',
        'Use group labels for supersets or circuits, then set rounds and transition time.',
      ),
      GuideStep(
        'save',
        'Save to your plans',
        'Save only after each workout day has exercises. Your saved plan appears in your library.',
      ),
    ],
  ),
  GuideDefinition('member_train_v1', 'Starting a workout', 'Train', [
    GuideStep(
      'books',
      'Explore Workout Books',
      'Open Workout Book to browse, preview and save a plan for your training.',
    ),
    GuideStep(
      'start',
      'Start when you are ready',
      'Choose a workout day if prompted, then start your session. Check in at your gym when required.',
    ),
  ]),
  GuideDefinition(
    'member_active_workout_v1',
    'Active Workout',
    'Train → start a workout',
    [
      GuideStep(
        'active',
        'Your session is running',
        'Keep track of time and your session’s progress here.',
      ),
      GuideStep(
        'exercise',
        'Log each exercise',
        'Enter the reps, weight or duration you actually complete. Add exercises if needed.',
      ),
      GuideStep(
        'finish',
        'Finish and review',
        'Complete your workout when you are done, then review the summary. Your saved results appear in your history.',
      ),
    ],
  ),
  GuideDefinition('member_rest_v1', 'Rest Timer', 'Train → log a set', [
    GuideStep(
      'rest',
      'Take a rest',
      'Use the timer between sets. Add or remove time, or skip when you are ready for the next set.',
    ),
  ]),
  GuideDefinition('member_metrics_v1', 'Body Metrics', 'Body', [
    GuideStep(
      'metrics',
      'Watch your trends',
      'Add regular check-ins to compare your measurements over time. A single reading is only one part of your progress.',
    ),
    GuideStep(
      'preferences',
      'Your training preferences',
      'Adjust your workout preferences here. Manage consent and data requests in Settings → Privacy & consent.',
    ),
  ]),
  GuideDefinition('member_notifications_v1', 'Notifications', 'Notifications', [
    GuideStep(
      'inbox',
      'Review updates',
      'Notifications collect gym invitations, trainer updates, workout reminders and membership alerts in one place.',
    ),
    GuideStep(
      'invitations',
      'Respond to invitations',
      'When a gym or independent trainer invites you, review the request here before accepting or declining.',
    ),
    GuideStep(
      'updates',
      'Open the right follow-up',
      'Unread updates can take you to the related event, trial request or membership action when one is available.',
    ),
  ]),
  GuideDefinition('member_diet_v1', 'Diet Plan', 'Diet plans', [
    GuideStep(
      'overview',
      'Your nutrition plan',
      'See assigned and personal diet plans, targets and daily meal progress from this screen.',
    ),
    GuideStep(
      'create',
      'Create your own plan',
      'Build a personal meal plan or start from an Atlas template without changing trainer-assigned plans.',
    ),
    GuideStep(
      'meals',
      'Track today’s meals',
      'Mark meals complete as you follow the plan. You can still open the full plan for all details.',
    ),
  ]),
  GuideDefinition('member_coach_v1', 'Chats and coaching', 'Chats', [
    GuideStep(
      'overview',
      'Your coaching inbox',
      'Use Chats for private trainer conversations and independent coaching invitations.',
    ),
    GuideStep(
      'invitations',
      'Review coaching invitations',
      'Independent trainer invitations stay separate from your gym membership so you can decide before connecting.',
    ),
    GuideStep(
      'conversation',
      'Open a trainer thread',
      'Tap an active trainer card to message, review shared plans or manage the coaching connection.',
    ),
  ]),
  GuideDefinition('member_discovery_v1', 'Gym discovery', 'Discover', [
    GuideStep(
      'search',
      'Find gyms near you',
      'Search, use your location, or adjust distance to compare public gym listings.',
    ),
    GuideStep(
      'filters',
      'Narrow the list',
      'Use filters and saved gyms to shortlist the places worth visiting.',
    ),
    GuideStep(
      'results',
      'Open a gym profile',
      'Open a gym card to review facilities, plans, contact options and trial availability.',
    ),
  ]),
  GuideDefinition('member_gym_detail_v1', 'Gym details', 'Discover → gym', [
    GuideStep(
      'overview',
      'Review the gym',
      'Check photos, facilities, trainers, membership plans and branch information before you contact the gym.',
    ),
    GuideStep(
      'trial',
      'Request a trial',
      'Use the trial action when the gym accepts public enquiries. Saving a gym only adds it to your shortlist.',
    ),
  ]),
  GuideDefinition(
    'member_trials_v1',
    'Trial requests',
    'Discover → Trial Requests',
    [
      GuideStep(
        'form',
        'Book a trial',
        'Choose a gym, add your contact details and preferred time, then submit when you are ready.',
      ),
      GuideStep(
        'status',
        'Track requests',
        'Switch to status to see whether a gym has received, reviewed or completed your trial request.',
      ),
    ],
  ),
  GuideDefinition('member_logbook_v1', 'Logbook', 'Train → Workout history', [
    GuideStep(
      'overview',
      'Review training history',
      'Your logbook brings together completed workouts, total volume and personal record highlights.',
    ),
    GuideStep(
      'history',
      'Open past sessions',
      'Workout history lets you revisit session summaries and exercise breakdowns.',
    ),
    GuideStep(
      'records',
      'Track personal records',
      'Personal records update from completed workouts so you can see strength progress over time.',
    ),
  ]),
  GuideDefinition('member_membership_v1', 'Membership', 'Settings → Membership', [
    GuideStep(
      'status',
      'Check access',
      'See your current gym, branch, membership status, paid amount and expiry date.',
    ),
    GuideStep(
      'access',
      'Review gym access',
      'Open attendance history from here and see your assigned trainer when your gym provides one.',
    ),
    GuideStep(
      'billing',
      'Understand billing',
      'Payment and plan sections show payable amount, dues, dates and plan details published by the gym.',
    ),
  ]),
  GuideDefinition('member_settings_v1', 'Settings and privacy', 'Settings', [
    GuideStep(
      'guides',
      'Replay help anytime',
      'Replay all guides, replay one feature, or turn automatic guides off without affecting app features.',
    ),
    GuideStep(
      'account',
      'Account and membership',
      'Open profile, membership and activity history from the account section.',
    ),
    GuideStep(
      'privacy',
      'Privacy controls',
      'Manage consent, privacy requests and account deletion from the Help & Legal and Account Controls sections.',
    ),
  ]),
  GuideDefinition('member_events_v1', 'Events', 'Events', [
    GuideStep(
      'summary',
      'See upcoming events',
      'Review available gym and global events plus any spots you have already reserved.',
    ),
    GuideStep(
      'tabs',
      'Switch event views',
      'Use the tabs to move between all upcoming events and your booked events.',
    ),
    GuideStep(
      'event',
      'Open event details',
      'Open an event to review timing, booking status and cancellation options before acting.',
    ),
  ]),
  GuideDefinition('member_profile_v1', 'Profile', 'Settings → Profile', [
    GuideStep(
      'overview',
      'Keep your profile current',
      'Your profile combines account details, completion status and the edit action.',
    ),
    GuideStep(
      'training',
      'Update training context',
      'Training details, goals and safety notes help keep workouts and coaching relevant.',
    ),
    GuideStep(
      'access',
      'Review gym access',
      'See your current gym, branch and assigned trainer. Leaving a gym removes active access but keeps history.',
    ),
  ]),
  GuideDefinition(
    'member_assigned_workout_v1',
    'Assigned Workout',
    'Train → Assigned Workout',
    [
      GuideStep(
        'overview',
        'Review the assigned plan',
        'Check the assigned workout goal, status, workout days and start action before beginning.',
      ),
      GuideStep(
        'schedule',
        'Choose a workout day',
        'Select the day you want to review so the exercise list matches that session.',
      ),
      GuideStep(
        'exercises',
        'Review exercises',
        'Check reps, sets, rest and target load before starting the workout from Train.',
      ),
    ],
  ),
  GuideDefinition(
    'member_attendance_v1',
    'Activity History',
    'Settings → Activity History',
    [
      GuideStep(
        'status',
        'Check attendance access',
        'See whether check-ins are enabled and when your latest gym visit was recorded.',
      ),
      GuideStep(
        'biometric',
        'Understand biometric status',
        'If your gym uses biometric attendance, this card explains whether your profile is enrolled.',
      ),
      GuideStep(
        'history',
        'Review check-ins',
        'Recent verified gym visits appear here, with pagination when more history is available.',
      ),
    ],
  ),
];

const trainerGuides = <GuideDefinition>[
  GuideDefinition('trainer_home_v1', 'Gym Atlas Coach overview', 'Home', [
    GuideStep(
      'home',
      'Your coaching dashboard',
      'See today’s clients and follow-ups at a glance.',
    ),
    GuideStep(
      'members',
      'Your members',
      'Open Clients to review a profile, shared activity and coaching permissions.',
    ),
    GuideStep(
      'builder',
      'Build a workout',
      'Choose a member, select exercises and arrange their workout in the planner.',
    ),
    GuideStep(
      'messages',
      'Stay in touch',
      'Open Chat to continue a coaching conversation.',
    ),
    GuideStep(
      'notifications',
      'Keep up with updates',
      'Review member activity and other coaching updates here.',
    ),
    GuideStep(
      'settings',
      'Settings and privacy',
      'Manage your account and consent, or choose Replay guides whenever you need a reminder.',
    ),
  ]),
  GuideDefinition(
    'trainer_assignment_v1',
    'Assigning a workout',
    'Clients → member → Assign workout',
    [
      GuideStep(
        'member',
        'Check the member',
        'Check that this is the member you want to coach. Choose a library workout and set a start date below.',
      ),
      GuideStep(
        'assign',
        'Review and assign',
        'Review your selected plan before assigning it to this member.',
      ),
    ],
  ),
  GuideDefinition('trainer_builder_v1', 'Workout Builder', 'Plans → Workouts', [
    GuideStep(
      'picker',
      'Choose exercises',
      'Search the exercise library and select an exercise. Continue loading results inside the picker when more are available.',
    ),
    GuideStep(
      'groups',
      'Organize the session',
      'Choose a group type, set rounds and transition seconds, and arrange exercises in the order the member should perform them.',
    ),
    GuideStep(
      'save',
      'Review and save',
      'Review the workout days and exercise order before saving your library plan. You can then assign it to a selected member.',
    ),
  ]),
  GuideDefinition(
    'trainer_member_v1',
    'Member Profile',
    'Clients → open a member',
    [
      GuideStep(
        'profile',
        'Understand the member',
        'Review the member’s activity and the information they have chosen to share.',
      ),
      GuideStep(
        'permissions',
        'Respect sharing choices',
        'Access depends on the member’s current consent and coaching relationship. Restricted information stays private; manage your own data requests in Settings.',
      ),
      GuideStep(
        'assign',
        'Plan their next workout',
        'Use the workout action to prepare or assign a plan for this member.',
      ),
    ],
  ),
  GuideDefinition('trainer_notifications_v1', 'Notifications', 'Alerts', [
    GuideStep(
      'updates',
      'Review member updates',
      'Check new activity and coaching updates here. Open an update to follow up.',
    ),
    GuideStep(
      'preferences',
      'Choose your notifications',
      'Set your communication preferences here. Turning guides off does not change these preferences.',
    ),
  ]),
  GuideDefinition('trainer_diet_v1', 'Diet Plans', 'Diet plans', [
    GuideStep(
      'library',
      'Build diet plans',
      'Use the diet library to create meal plans, review templates and assign nutrition work to members.',
    ),
    GuideStep(
      'details',
      'Set nutrition details',
      'In Diet Studio, define goals, calories, macros and preferences before adding meals.',
    ),
    GuideStep(
      'meals',
      'Build meals',
      'Add timings, foods, portions and macros so the member can follow the plan clearly.',
    ),
    GuideStep(
      'save',
      'Save or assign',
      'Save to your library or assign the finished diet plan to linked members when ready.',
    ),
  ]),
  GuideDefinition('trainer_events_v1', 'Events', 'Events', [
    GuideStep(
      'tabs',
      'Switch event views',
      'Use Schedule, Hosting and Manage to separate public events from the events you host or manage.',
    ),
    GuideStep(
      'event',
      'Open event details',
      'Open an event to review bookings, roster actions and host controls before making changes.',
    ),
  ]),
  GuideDefinition('trainer_tasks_v1', 'Follow Ups', 'Follow Ups', [
    GuideStep(
      'summary',
      'Prioritize follow-ups',
      'See today, overdue and pending follow-ups before adding a note or completing a task.',
    ),
    GuideStep(
      'composer',
      'Add a trainer note',
      'Create a follow-up for a member with a note and date so it returns to your timeline.',
    ),
    GuideStep(
      'timeline',
      'Work the timeline',
      'Review pending follow-ups, add context and mark them complete after you act.',
    ),
  ]),
  GuideDefinition('trainer_trials_v1', 'Trial Leads', 'Trial leads', [
    GuideStep(
      'filters',
      'Find assigned leads',
      'Search by contact details and filter by lead status to focus on the next trial follow-up.',
    ),
    GuideStep(
      'lead',
      'Open a lead',
      'Open a trial lead to review details, update the outcome and record follow-up notes.',
    ),
  ]),
  GuideDefinition('trainer_profile_v1', 'Trainer Profile', 'Profile', [
    GuideStep(
      'overview',
      'Keep your profile complete',
      'Your profile card shows completion and opens the editor for changes.',
    ),
    GuideStep(
      'coaching',
      'Review coaching details',
      'Specializations, experience, certifications and languages help gyms and members understand your coaching.',
    ),
    GuideStep(
      'verification',
      'Track verification',
      'Personal coaching verification determines whether you can coach members independently from gym assignment.',
    ),
  ]),
  GuideDefinition('trainer_profile_edit_v1', 'Edit Profile', 'Profile → Edit', [
    GuideStep(
      'basic',
      'Update basic details',
      'Keep your name, phone and optional demographic details accurate.',
    ),
    GuideStep(
      'coaching',
      'Update coaching details',
      'Add a clear bio, specializations, experience, certifications and languages.',
    ),
    GuideStep(
      'verification',
      'Submit verification',
      'Submit for personal coaching verification only after required coaching details and certification proof are ready.',
    ),
    GuideStep(
      'save',
      'Save changes',
      'Save profile changes before leaving this screen.',
    ),
  ]),
  GuideDefinition('trainer_settings_v1', 'Settings and privacy', 'Settings', [
    GuideStep(
      'guides',
      'Replay help anytime',
      'Replay all guides, replay one feature, or turn automatic guides off without affecting app features.',
    ),
    GuideStep(
      'profile',
      'Open your profile',
      'Use the profile card to review and update your public coaching details.',
    ),
    GuideStep(
      'privacy',
      'Privacy controls',
      'Manage consent, privacy requests and account deletion from Settings.',
    ),
  ]),
];
