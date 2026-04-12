<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\CourseEnrollment;
use App\Models\Badge;
use App\Models\Mission;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Cohort;
use App\Models\CohortMember;
use App\Models\InternshipTrack;
use App\Models\InternshipMilestone;
use App\Models\InternshipTask;
use App\Models\InternshipEnrollment;
use App\Models\MockExam;
use App\Models\Forum;
use App\Models\ForumThread;
use App\Models\ForumReply;
use App\Models\StudyGroup;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\CareerPathway;
use App\Models\Announcement;
use App\Models\Flashcard;
use App\Models\SpacedRepetitionItem;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏢 Creating organizations & departments...');
        $this->seedOrganizations();

        $this->command->info('👤 Creating users...');
        $this->seedUsers();

        $this->command->info('📚 Creating courses with modules & lessons...');
        $this->seedCourses();

        $this->command->info('📝 Creating quizzes & questions...');
        $this->seedQuizzes();

        $this->command->info('🎓 Creating enrollments...');
        $this->seedEnrollments();

        $this->command->info('👥 Creating cohorts...');
        $this->seedCohorts();

        $this->command->info('💼 Creating internship tracks...');
        $this->seedInternships();

        $this->command->info('📋 Creating mock exams...');
        $this->seedMockExams();

        $this->command->info('🧠 Expanding question bank for mock exams...');
        $this->seedMockExamQuestions();

        $this->command->info('🃏 Creating flashcards & practice data...');
        $this->seedPracticeData();

        $this->command->info('💬 Creating forums & threads...');
        $this->seedForums();

        $this->command->info('👫 Creating study groups...');
        $this->seedStudyGroups();

        $this->command->info('📅 Creating events...');
        $this->seedEvents();

        $this->command->info('🗺️ Creating career pathways...');
        $this->seedCareerPathways();

        $this->command->info('📢 Creating announcements...');
        $this->seedAnnouncements();

        $this->command->info('✅ Demo data seeding complete!');
    }

    private function seedOrganizations(): void
    {
        $orgs = [
            ['name' => 'JK Michaels Institute', 'slug' => 'jk-michaels', 'contact_email' => 'info@jkmichaels.com', 'subscription_plan' => 'enterprise'],
            ['name' => 'Vertex Consulting', 'slug' => 'vertex', 'contact_email' => 'admin@vertex.com', 'subscription_plan' => 'pro'],
            ['name' => 'NovaTech Solutions', 'slug' => 'novatech', 'contact_email' => 'admin@novatech.com', 'subscription_plan' => 'pro'],
        ];

        foreach ($orgs as $org) {
            Organization::firstOrCreate(['slug' => $org['slug']], array_merge($org, [
                'uuid' => (string) Str::uuid(),
            ]));
        }

        $depts = ['Engineering', 'Human Resources', 'Finance', 'Marketing', 'Operations', 'Product Management'];
        $orgId = Organization::where('slug', 'jk-michaels')->first()?->id ?? 1;
        foreach ($depts as $dept) {
            Department::firstOrCreate(['name' => $dept, 'organization_id' => $orgId]);
        }
    }

    private function seedUsers(): void
    {
        $defaultPassword = Hash::make('password');
        $orgId = Organization::where('slug', 'jk-michaels')->first()?->id ?? 1;

        // Facilitators
        $facilitators = [
            ['first_name' => 'Dr. Adaeze', 'last_name' => 'Okwu', 'email' => 'adaeze@jkmichaels.com'],
            ['first_name' => 'Prof. Chukwuemeka', 'last_name' => 'Nwosu', 'email' => 'chukwuemeka@jkmichaels.com'],
            ['first_name' => 'Eng. Fatima', 'last_name' => 'Ibrahim', 'email' => 'fatima@jkmichaels.com'],
        ];
        foreach ($facilitators as $f) {
            $user = User::firstOrCreate(['email' => $f['email']], array_merge($f, [
                'uuid' => (string) Str::uuid(),
                'password' => $defaultPassword,
                'is_active' => true,
                'email_verified_at' => now(),
                'organization_id' => $orgId,
            ]));
            $user->assignRole('facilitator');
        }

        // SME Reviewers
        $smes = [
            ['first_name' => 'Dr. Emeka', 'last_name' => 'Obi', 'email' => 'emeka.obi@jkmichaels.com'],
            ['first_name' => 'Mrs. Grace', 'last_name' => 'Adeyemi', 'email' => 'grace.a@jkmichaels.com'],
        ];
        foreach ($smes as $sme) {
            $user = User::firstOrCreate(['email' => $sme['email']], array_merge($sme, [
                'uuid' => (string) Str::uuid(),
                'password' => $defaultPassword,
                'is_active' => true,
                'email_verified_at' => now(),
                'organization_id' => $orgId,
            ]));
            $user->assignRole('sme_reviewer');
        }

        // Sponsors
        $org2 = Organization::where('slug', 'vertex')->first()?->id ?? 2;
        $org3 = Organization::where('slug', 'novatech')->first()?->id ?? 3;
        $sponsors = [
            ['first_name' => 'Chief', 'last_name' => 'Okonkwo', 'email' => 'sponsor@vertex.com', 'organization_id' => $org2],
            ['first_name' => 'Mrs. Nneka', 'last_name' => 'Eze', 'email' => 'sponsor@novatech.com', 'organization_id' => $org3],
        ];
        foreach ($sponsors as $s) {
            $user = User::firstOrCreate(['email' => $s['email']], array_merge($s, [
                'uuid' => (string) Str::uuid(),
                'password' => $defaultPassword,
                'is_active' => true,
                'email_verified_at' => now(),
            ]));
            $user->assignRole('sponsor');
        }

        // Learners
        $firstNames = ['Tunde', 'Amara', 'Chidi', 'Kemi', 'Obinna', 'Zainab', 'Yusuf', 'Blessing', 'Ade', 'Ifeoma',
            'Hassan', 'Ngozi', 'Bola', 'Uche', 'Dayo', 'Aisha', 'Segun', 'Chinwe', 'Femi', 'Halima'];
        $lastNames = ['Adeleke', 'Okafor', 'Bello', 'Adekunle', 'Nwankwo', 'Mohammed', 'Oyinlola', 'Igwe', 'Fashola', 'Amadi',
            'Suleiman', 'Chukwu', 'Oloyede', 'Egwu', 'Babangida', 'Okeke', 'Afolabi', 'Maduka', 'Ogunyemi', 'Musa'];
        $orgIds = [$orgId, $org2, $org3];

        for ($i = 0; $i < 20; $i++) {
            $fn = $firstNames[$i];
            $ln = $lastNames[$i];
            $email = strtolower($fn) . '.' . strtolower($ln) . '@example.com';
            $user = User::firstOrCreate(['email' => $email], [
                'uuid' => (string) Str::uuid(),
                'first_name' => $fn,
                'last_name' => $ln,
                'email' => $email,
                'password' => $defaultPassword,
                'is_active' => true,
                'email_verified_at' => now(),
                'organization_id' => $orgIds[array_rand($orgIds)],
            ]);
            $user->assignRole('learner');
        }
    }

    private function seedCourses(): void
    {
        $courses = [
            ['title' => 'Project Management Professional (PMP) Prep', 'category' => 'project-management', 'level' => 'advanced', 'price' => 150000, 'modules' => 5],
            ['title' => 'Certified Associate in PM (CAPM)', 'category' => 'project-management', 'level' => 'beginner', 'price' => 75000, 'modules' => 4],
            ['title' => 'Risk Management Essentials', 'category' => 'risk-management', 'level' => 'intermediate', 'price' => 85000, 'modules' => 4],
            ['title' => 'Agile & Scrum Mastery', 'category' => 'agile-scrum', 'level' => 'intermediate', 'price' => 95000, 'modules' => 4],
            ['title' => 'Business Analysis Fundamentals', 'category' => 'business-analysis', 'level' => 'beginner', 'price' => 65000, 'modules' => 3],
            ['title' => 'Quality Management Systems (ISO 9001)', 'category' => 'quality-management', 'level' => 'intermediate', 'price' => 90000, 'modules' => 4],
            ['title' => 'Data Analytics for Managers', 'category' => 'data-analytics', 'level' => 'beginner', 'price' => 0, 'modules' => 3],
            ['title' => 'Executive Leadership Program', 'category' => 'leadership', 'level' => 'advanced', 'price' => 200000, 'modules' => 6],
            ['title' => 'PRINCE2 Foundation', 'category' => 'project-management', 'level' => 'beginner', 'price' => 80000, 'modules' => 4],
            ['title' => 'Professional Communication Skills', 'category' => 'professional-development', 'level' => 'beginner', 'price' => 0, 'modules' => 3],
        ];

        $authorIds = User::whereHas('roles', fn($q) => $q->where('name', 'facilitator'))
            ->pluck('id')->toArray();
        if (empty($authorIds)) {
            $authorIds = [User::first()?->id ?? 1];
        }

        $lessonTopics = ['Introduction & Overview', 'Core Concepts', 'Frameworks & Methodologies', 'Tools & Techniques', 'Case Studies', 'Best Practices', 'Assessment Preparation', 'Real-World Applications'];
        $contentTypes = ['video', 'text', 'audio', 'pdf', 'mixed'];

        foreach ($courses as $c) {
            $category = Category::where('slug', $c['category'])->first();
            if (!$category) continue;

            $slug = Str::slug($c['title']);
            $course = Course::firstOrCreate(['slug' => $slug], [
                'uuid' => (string) Str::uuid(),
                'title' => $c['title'],
                'slug' => $slug,
                'description' => "Comprehensive course covering {$c['title']}. Master the essential skills and frameworks needed for professional excellence.",
                'category_id' => $category->id,
                'author_id' => $authorIds[array_rand($authorIds)],
                'level' => $c['level'],
                'price' => $c['price'],
                'is_published' => true,
                'published_at' => now()->subDays(rand(1, 30)),
                'duration_hours' => rand(20, 60),
            ]);

            for ($m = 1; $m <= $c['modules']; $m++) {
                $module = Module::firstOrCreate([
                    'course_id' => $course->id,
                    'sort_order' => $m,
                ], [
                    'course_id' => $course->id,
                    'title' => "Module {$m}: " . $lessonTopics[($m - 1) % count($lessonTopics)],
                    'sort_order' => $m,
                ]);

                for ($l = 1; $l <= rand(3, 5); $l++) {
                    Lesson::firstOrCreate([
                        'module_id' => $module->id,
                        'sort_order' => $l,
                    ], [
                        'module_id' => $module->id,
                        'title' => $lessonTopics[($l - 1) % count($lessonTopics)] . " - Part {$l}",
                        'text_content' => "Detailed lesson content for Module {$m}, Lesson {$l}. This covers key concepts and practical applications.",
                        'content_type' => $contentTypes[array_rand($contentTypes)],
                        'duration_minutes' => rand(10, 45),
                        'sort_order' => $l,
                        'is_published' => true,
                    ]);
                }
            }
        }
    }

    private function seedQuizzes(): void
    {
        $courses = Course::with('modules')->get();
        $domains = ['Planning', 'Execution', 'Monitoring', 'Closing', 'Fundamentals'];

        foreach ($courses as $course) {
            $module = $course->modules->first();
            if (!$module) continue;

            // Create questions first
            $questionIds = [];
            $questionTemplates = [
                ['stem' => 'Which of the following best describes the primary objective?', 'type' => 'multiple_choice'],
                ['stem' => 'Select all applicable principles from the list below.', 'type' => 'multiple_select'],
                ['stem' => 'Is the following statement true or false: The process group includes initiation.', 'type' => 'true_false'],
                ['stem' => 'What is the correct sequence of steps in the process?', 'type' => 'multiple_choice'],
                ['stem' => 'A project manager discovers a risk. What is the best course of action?', 'type' => 'scenario'],
            ];

            foreach ($questionTemplates as $qi => $qt) {
                $options = json_encode([
                    ['id' => 'a', 'text' => 'Option A - Correct Answer'],
                    ['id' => 'b', 'text' => 'Option B'],
                    ['id' => 'c', 'text' => 'Option C'],
                    ['id' => 'd', 'text' => 'Option D'],
                ]);

                $question = Question::firstOrCreate([
                    'course_id' => $course->id,
                    'stem' => $qt['stem'] . " ({$course->title})",
                ], [
                    'uuid' => (string) Str::uuid(),
                    'course_id' => $course->id,
                    'domain' => $domains[$qi % count($domains)],
                    'type' => $qt['type'],
                    'difficulty' => ['easy', 'medium', 'hard', 'expert'][rand(0, 3)],
                    'stem' => $qt['stem'] . " ({$course->title})",
                    'options' => $options,
                    'correct_answer_ids' => json_encode(['a']),
                    'explanation_correct' => 'Option A is correct because it aligns with the core framework principles.',
                    'status' => 'published',
                ]);

                $questionIds[] = $question->id;
            }

            // Create quiz and link questions via pivot
            $quiz = Quiz::firstOrCreate([
                'course_id' => $course->id,
                'title' => "{$course->title} - Module Quiz",
            ], [
                'course_id' => $course->id,
                'module_id' => $module->id,
                'title' => "{$course->title} - Module Quiz",
                'type' => 'module_quiz',
                'question_count' => count($questionIds),
                'time_limit_minutes' => 30,
                'passing_score_pct' => 70,
                'max_attempts' => 3,
                'is_published' => true,
            ]);

            // Attach questions to quiz via pivot
            foreach ($questionIds as $sort => $qId) {
                DB::table('quiz_questions')->insertOrIgnore([
                    'quiz_id' => $quiz->id,
                    'question_id' => $qId,
                    'sort_order' => $sort + 1,
                ]);
            }
        }
    }

    private function seedEnrollments(): void
    {
        $learners = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))->get();
        $courses = Course::all();

        foreach ($learners as $learner) {
            $enrollCount = rand(1, 4);
            $selectedCourses = $courses->random(min($enrollCount, $courses->count()));

            foreach ($selectedCourses as $course) {
                CourseEnrollment::firstOrCreate([
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ], [
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                    'status' => ['active', 'active', 'active', 'completed'][rand(0, 3)],
                    'progress_pct' => rand(0, 100),
                    'enrolled_at' => Carbon::now()->subDays(rand(1, 120)),
                ]);
            }
        }
    }

    private function seedCohorts(): void
    {
        $courses = Course::take(3)->get();
        $facilitators = User::whereHas('roles', fn($q) => $q->where('name', 'facilitator'))
            ->pluck('id')->toArray();
        if (empty($facilitators)) {
            $facilitators = [User::first()?->id ?? 1];
        }

        $cohortData = [
            ['name' => 'PMP Prep Cohort - Q1 2026', 'start_date' => '2026-01-15', 'end_date' => '2026-03-15'],
            ['name' => 'Agile Champions Cohort', 'start_date' => '2026-02-01', 'end_date' => '2026-04-01'],
            ['name' => 'Leadership Development Cohort', 'start_date' => '2026-03-01', 'end_date' => '2026-05-30'],
        ];

        foreach ($cohortData as $i => $data) {
            $course = $courses[$i] ?? $courses->first();
            $cohort = Cohort::firstOrCreate(['name' => $data['name']], [
                'uuid' => (string) Str::uuid(),
                'name' => $data['name'],
                'course_id' => $course->id,
                'facilitator_id' => $facilitators[array_rand($facilitators)],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'max_learners' => 25,
                'status' => 'active',
            ]);

            // Add some learners
            $learners = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))
                ->inRandomOrder()->take(rand(5, 12))->pluck('id');
            foreach ($learners as $learnerId) {
                CohortMember::firstOrCreate([
                    'cohort_id' => $cohort->id,
                    'user_id' => $learnerId,
                ], [
                    'cohort_id' => $cohort->id,
                    'user_id' => $learnerId,
                    'role' => 'learner',
                ]);
            }
        }
    }

    private function seedInternships(): void
    {
        $tracks = [
            [
                'title' => 'Project Management Internship',
                'description' => 'Hands-on experience in project management across real-world scenarios',
                'duration_weeks' => 12,
                'price' => 50000,
                'milestones' => ['Project Charter Creation', 'Stakeholder Analysis', 'WBS Development', 'Risk Register'],
            ],
            [
                'title' => 'Business Analysis Internship',
                'description' => 'Develop BA skills through practical requirement gathering and process mapping',
                'duration_weeks' => 8,
                'price' => 35000,
                'milestones' => ['Requirements Document', 'Process Map', 'User Stories'],
            ],
        ];

        foreach ($tracks as $trackData) {
            $track = InternshipTrack::firstOrCreate(['title' => $trackData['title']], [
                'title' => $trackData['title'],
                'slug' => Str::slug($trackData['title']),
                'description' => $trackData['description'],
                'duration_weeks' => $trackData['duration_weeks'],
                'price' => $trackData['price'],
                'is_published' => true,
            ]);

            foreach ($trackData['milestones'] as $mi => $milestone) {
                InternshipMilestone::firstOrCreate([
                    'track_id' => $track->id,
                    'title' => $milestone,
                ], [
                    'track_id' => $track->id,
                    'title' => $milestone,
                    'description' => "Complete {$milestone} deliverable",
                    'week_number' => ($mi + 1) * 3,
                    'sort_order' => $mi + 1,
                ]);
            }

            // Enroll some learners
            $learners = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))
                ->inRandomOrder()->take(5)->pluck('id');

            foreach ($learners as $learnerId) {
                $enrollment = InternshipEnrollment::firstOrCreate([
                    'user_id' => $learnerId,
                    'track_id' => $track->id,
                ], [
                    'user_id' => $learnerId,
                    'track_id' => $track->id,
                    'status' => ['active', 'active', 'completed'][rand(0, 2)],
                    'start_date' => Carbon::now()->subDays(rand(10, 60)),
                    'end_date' => Carbon::now()->addDays(rand(30, 90)),
                ]);

                // Create tasks for each enrollment
                $taskTemplates = [
                    ['title' => 'Draft project charter', 'type' => 'weekly'],
                    ['title' => 'Identify stakeholders', 'type' => 'weekly'],
                    ['title' => 'Weekly standup notes', 'type' => 'daily'],
                ];

                foreach ($taskTemplates as $ti => $task) {
                    InternshipTask::firstOrCreate([
                        'enrollment_id' => $enrollment->id,
                        'title' => $task['title'],
                    ], [
                        'enrollment_id' => $enrollment->id,
                        'title' => $task['title'],
                        'description' => "Detailed instructions for: {$task['title']}",
                        'type' => $task['type'],
                        'sort_order' => $ti + 1,
                    ]);
                }
            }
        }
    }

    private function seedMockExams(): void
    {
        $course = Course::first();
        if (!$course) return;

        $exams = [
            ['title' => 'PMP Practice Exam 1', 'description' => 'Full-length PMP simulation exam', 'question_count' => 50, 'time_limit_minutes' => 60, 'passing_score_pct' => 65, 'type' => 'full_length'],
            ['title' => 'CAPM Practice Exam', 'description' => 'CAPM preparation exam', 'question_count' => 40, 'time_limit_minutes' => 50, 'passing_score_pct' => 60, 'type' => 'full_length'],
            ['title' => 'Risk Management Mini Mock', 'description' => 'Focused risk management assessment', 'question_count' => 20, 'time_limit_minutes' => 25, 'passing_score_pct' => 70, 'type' => 'mini_mock'],
        ];

        foreach ($exams as $exam) {
            MockExam::updateOrCreate(['title' => $exam['title']], array_merge($exam, [
                'course_id' => $course->id,
                'domain_distribution' => json_encode(['Planning' => 30, 'Execution' => 30, 'Monitoring' => 20, 'Closing' => 20]),
                'is_published' => true,
            ]));
        }
    }

    private function seedForums(): void
    {
        $forums = [
            ['title' => 'General Discussion', 'description' => 'Open discussion for all learners'],
            ['title' => 'Career Advice', 'description' => 'Get guidance on career paths and professional growth'],
            ['title' => 'Study Tips', 'description' => 'Share study strategies and exam preparation tips'],
        ];

        foreach ($forums as $f) {
            $forum = Forum::firstOrCreate(['title' => $f['title']], array_merge($f, ['is_general' => true]));

            // Add threads
            $learners = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))
                ->inRandomOrder()->take(3)->get();
            $threadTopics = [
                'Tips for passing the PMP exam on first attempt',
                'Best study schedule for working professionals',
                'How to apply agile in non-IT projects',
            ];

            foreach ($learners as $li => $learner) {
                $thread = ForumThread::firstOrCreate([
                    'forum_id' => $forum->id,
                    'title' => $threadTopics[$li] ?? "Discussion topic {$li}",
                ], [
                    'forum_id' => $forum->id,
                    'user_id' => $learner->id,
                    'title' => $threadTopics[$li] ?? "Discussion topic {$li}",
                    'body' => "This is a discussion about {$threadTopics[$li]}. Share your thoughts and experiences!",
                    'is_pinned' => $li === 0,
                ]);

                // Add replies
                $repliers = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))
                    ->where('id', '!=', $learner->id)
                    ->inRandomOrder()->take(2)->get();
                foreach ($repliers as $replier) {
                    ForumReply::firstOrCreate([
                        'thread_id' => $thread->id,
                        'user_id' => $replier->id,
                    ], [
                        'thread_id' => $thread->id,
                        'user_id' => $replier->id,
                        'body' => 'Great discussion! I agree with the point about structured study plans. Consistency is key.',
                    ]);
                }
            }
        }
    }

    private function seedStudyGroups(): void
    {
        $groups = [
            ['name' => 'PMP Study Squad', 'max_members' => 10, 'is_public' => true],
            ['name' => 'Agile Enthusiasts', 'max_members' => 8, 'is_public' => true],
            ['name' => 'Weekend Warriors', 'max_members' => 6, 'is_public' => false],
        ];

        $learners = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))->get();
        if ($learners->isEmpty()) return;

        foreach ($groups as $gi => $g) {
            $creator = $learners[$gi] ?? $learners->first();
            $group = StudyGroup::firstOrCreate(['name' => $g['name']], array_merge($g, [
                'created_by' => $creator->id,
                'course_id' => Course::inRandomOrder()->first()?->id,
            ]));

            // Add members
            $otherLearners = $learners->where('id', '!=', $creator->id);
            if ($otherLearners->isNotEmpty()) {
                $members = $otherLearners->random(min(rand(2, 5), $otherLearners->count()));
                $group->members()->syncWithoutDetaching([$creator->id, ...$members->pluck('id')->toArray()]);
            }
        }
    }

    private function seedEvents(): void
    {
        $events = [
            ['title' => 'PMP Exam Strategy Webinar', 'type' => 'webinar', 'days' => 7, 'price' => 0, 'speaker_name' => 'Dr. Adaeze Okwu'],
            ['title' => 'Agile Leadership Bootcamp', 'type' => 'bootcamp', 'days' => 14, 'price' => 25000, 'speaker_name' => 'Prof. Chukwuemeka Nwosu'],
            ['title' => 'Industry Trends in Project Management', 'type' => 'industry_session', 'days' => 21, 'price' => 0, 'speaker_name' => 'Eng. Fatima Ibrahim'],
            ['title' => 'Risk Management Workshop', 'type' => 'workshop', 'days' => -7, 'price' => 15000, 'speaker_name' => 'Dr. Emeka Obi'],
        ];

        foreach ($events as $e) {
            $event = Event::firstOrCreate(['title' => $e['title']], [
                'title' => $e['title'],
                'description' => "Join us for {$e['title']}. An interactive session with industry experts.",
                'type' => $e['type'],
                'platform' => ['zoom', 'teams', 'google_meet'][rand(0, 2)],
                'scheduled_at' => Carbon::now()->addDays($e['days']),
                'duration_minutes' => rand(60, 180),
                'max_registrations' => rand(50, 200),
                'price' => $e['price'],
                'speaker_name' => $e['speaker_name'],
                'speaker_bio' => 'Expert professional with 15+ years of industry experience.',
                'meeting_url' => 'https://zoom.us/j/' . rand(10000000, 99999999),
                'is_published' => true,
            ]);

            // Register some users
            $users = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))
                ->inRandomOrder()->take(rand(5, 15))->pluck('id');
            foreach ($users as $userId) {
                EventRegistration::firstOrCreate([
                    'event_id' => $event->id,
                    'user_id' => $userId,
                ]);
            }
        }
    }

    private function seedCareerPathways(): void
    {
        $pathways = [
            ['title' => 'Certified Project Manager Track', 'description' => 'Go from CAPM to PMP with structured learning', 'courses' => ['certified-associate-in-pm-capm', 'project-management-professional-pmp-prep']],
            ['title' => 'Agile Transformation Lead', 'description' => 'Master agile and lead organizational transformation', 'courses' => ['agile-scrum-mastery', 'executive-leadership-program']],
            ['title' => 'Business Excellence Path', 'description' => 'Combine BA, QM, and leadership for business excellence', 'courses' => ['business-analysis-fundamentals', 'quality-management-systems-iso-9001', 'executive-leadership-program']],
        ];

        foreach ($pathways as $p) {
            $slug = Str::slug($p['title']);
            $pathway = CareerPathway::firstOrCreate(['slug' => $slug], [
                'title' => $p['title'],
                'slug' => $slug,
                'description' => $p['description'],
                'skills_required' => json_encode(['Critical Thinking', 'Communication', 'Leadership']),
            ]);

            foreach ($p['courses'] as $ci => $courseSlug) {
                $course = Course::where('slug', 'LIKE', "%{$courseSlug}%")->first();
                if ($course) {
                    $pathway->courses()->syncWithoutDetaching([$course->id => ['sort_order' => $ci + 1, 'is_required' => true]]);
                }
            }
        }
    }

    private function seedAnnouncements(): void
    {
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'platform_admin'))->first();

        $announcements = [
            ['title' => 'Welcome to JK Michaels Learning Platform!', 'body' => 'We are thrilled to launch our professional mastery platform. Explore courses, join study groups, and accelerate your career.'],
            ['title' => 'New PMP Prep Course Available', 'body' => 'Our comprehensive PMP preparation course is now live. Enroll today and start your journey to PMP certification.'],
            ['title' => 'Upcoming Webinar: Agile Leadership', 'body' => 'Join us for an interactive session on applying agile principles to leadership. Register now!'],
            ['title' => 'Platform Maintenance - Saturday 2 AM', 'body' => 'Scheduled maintenance window. The platform may be briefly unavailable. Thank you for your patience.'],
        ];

        foreach ($announcements as $a) {
            Announcement::firstOrCreate(['title' => $a['title']], array_merge($a, [
                'author_id' => $admin?->id ?? 1,
                'published_at' => Carbon::now()->subDays(rand(0, 30)),
            ]));
        }
    }

    /**
     * Seed a large question bank for mock exams (60+ questions per course).
     */
    private function seedMockExamQuestions(): void
    {
        $courses = Course::take(3)->get();
        $domains = ['Planning', 'Execution', 'Monitoring', 'Closing', 'Fundamentals'];
        $difficulties = ['easy', 'medium', 'hard', 'expert'];

        $stemBank = [
            'What is the PRIMARY purpose of %s in a project context?',
            'A project manager is experiencing %s. Which is the BEST course of action?',
            'Which of the following is NOT a characteristic of %s?',
            'During %s, the project manager should focus on:',
            'The main benefit of implementing %s is:',
            'According to best practices, %s should be performed:',
            'Which tool is MOST appropriate for %s?',
            'The key output of %s includes:',
            'When a stakeholder requests changes during %s, the PM should first:',
            'Which statement is TRUE regarding %s?',
            'A team member disagrees about %s. The project manager should:',
            'What technique is recommended for effective %s?',
        ];

        $topics = [
            'scope management', 'schedule development', 'cost estimation', 'quality assurance',
            'risk identification', 'stakeholder engagement', 'resource allocation', 'procurement',
            'change management', 'communication planning', 'team building', 'conflict resolution',
            'earned value analysis', 'critical path method', 'work breakdown structure',
            'project charter development', 'requirements gathering', 'status reporting',
        ];

        foreach ($courses as $course) {
            $existing = Question::where('course_id', $course->id)->count();
            $needed = max(0, 60 - $existing); // Aim for 60 questions per course

            for ($i = 0; $i < $needed; $i++) {
                $topic = $topics[array_rand($topics)];
                $stemTemplate = $stemBank[array_rand($stemBank)];
                $stem = sprintf($stemTemplate, $topic);
                $domain = $domains[array_rand($domains)];
                $difficulty = $difficulties[array_rand($difficulties)];

                // Randomize correct answer position
                $correctPos = rand(0, 3);
                $letters = ['a', 'b', 'c', 'd'];
                $options = [];
                foreach ($letters as $idx => $letter) {
                    $options[] = [
                        'id' => $letter,
                        'text' => $idx === $correctPos
                            ? "Apply the appropriate {$topic} technique aligned with organizational standards"
                            : "Option {$letter} — " . ['Escalate to senior management immediately', 'Document the issue and wait', 'Skip this step to save time', 'Defer to the next project phase'][($idx + $i) % 4],
                    ];
                }

                Question::firstOrCreate([
                    'course_id' => $course->id,
                    'stem' => $stem . " (Q" . ($existing + $i + 1) . ")",
                ], [
                    'uuid' => (string) Str::uuid(),
                    'course_id' => $course->id,
                    'domain' => $domain,
                    'type' => ['multiple_choice', 'multiple_choice', 'scenario', 'multiple_select'][rand(0, 3)],
                    'difficulty' => $difficulty,
                    'stem' => $stem . " (Q" . ($existing + $i + 1) . ")",
                    'options' => json_encode($options),
                    'correct_answer_ids' => json_encode([$letters[$correctPos]]),
                    'explanation_correct' => "The correct approach to {$topic} involves applying structured techniques that align with industry standards and organizational processes.",
                    'explanation_incorrect' => "The other options represent common misconceptions about {$topic}. Effective practice requires systematic application of proven methodologies.",
                    'status' => 'published',
                ]);
            }
        }
    }

    /**
     * Seed flashcards and spaced repetition items for demo learners.
     */
    private function seedPracticeData(): void
    {
        $learners = User::whereHas('roles', fn($q) => $q->where('name', 'learner'))
            ->take(5)
            ->get();

        $courses = Course::take(3)->get();

        $flashcardBank = [
            ['front' => 'What is the triple constraint in project management?', 'back' => 'The triple constraint refers to Scope, Time, and Cost — the three key factors that must be balanced in every project.', 'domain' => 'Fundamentals'],
            ['front' => 'Define Work Breakdown Structure (WBS)', 'back' => 'A WBS is a hierarchical decomposition of the total scope of work into manageable sections, typically organized by deliverables.', 'domain' => 'Planning'],
            ['front' => 'What is Earned Value Management?', 'back' => 'EVM is a technique that integrates scope, schedule, and cost data to assess project performance. Key metrics: PV, EV, AC, SPI, CPI.', 'domain' => 'Monitoring'],
            ['front' => 'Name the 5 process groups in project management', 'back' => 'Initiating, Planning, Executing, Monitoring & Controlling, and Closing.', 'domain' => 'Fundamentals'],
            ['front' => 'What is a RACI matrix?', 'back' => 'A responsibility assignment matrix: Responsible (does the work), Accountable (ultimate authority), Consulted (provides input), Informed (kept updated).', 'domain' => 'Execution'],
            ['front' => 'Define Critical Path Method (CPM)', 'back' => 'CPM identifies the longest sequence of dependent tasks that determines the minimum project duration. Tasks on this path have zero float.', 'domain' => 'Planning'],
            ['front' => 'What are the risk response strategies?', 'back' => 'For threats: Avoid, Transfer, Mitigate, Accept. For opportunities: Exploit, Share, Enhance, Accept.', 'domain' => 'Planning'],
            ['front' => 'What is a stakeholder register?', 'back' => 'A document identifying all stakeholders, their interests, involvement, influence, and potential impact on the project.', 'domain' => 'Execution'],
            ['front' => 'Define the difference between Quality Assurance and Quality Control', 'back' => 'QA focuses on process improvement (preventive), while QC focuses on inspecting deliverables to find defects (reactive).', 'domain' => 'Monitoring'],
            ['front' => 'What is a project charter?', 'back' => 'A formal document that authorizes the project, names the PM, defines high-level requirements, milestones, budget, and stakeholders.', 'domain' => 'Fundamentals'],
            ['front' => 'Explain the Delphi technique', 'back' => 'An anonymous iterative expert consultation method used for estimation and risk identification. Experts respond in rounds until consensus is reached.', 'domain' => 'Planning'],
            ['front' => 'What are the stages of team development?', 'back' => 'Tuckman model: Forming → Storming → Norming → Performing → Adjourning.', 'domain' => 'Execution'],
        ];

        $statuses = ['new', 'learning', 'review', 'graduated'];

        foreach ($learners as $learner) {
            $course = $courses->random();

            // Create 6-10 flashcards per learner
            $count = rand(6, min(10, count($flashcardBank)));
            $selected = collect($flashcardBank)->random($count);

            foreach ($selected as $i => $fc) {
                $flashcard = Flashcard::firstOrCreate([
                    'user_id' => $learner->id,
                    'front' => $fc['front'],
                ], [
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                    'front' => $fc['front'],
                    'back' => $fc['back'],
                    'domain' => $fc['domain'],
                    'source' => 'user_created',
                ]);

                // Create matching SR item
                $status = $statuses[array_rand($statuses)];
                $intervalDays = match ($status) {
                    'new' => 0,
                    'learning' => rand(1, 3),
                    'review' => rand(4, 14),
                    'graduated' => rand(30, 90),
                };

                SpacedRepetitionItem::firstOrCreate([
                    'user_id' => $learner->id,
                    'reviewable_type' => Flashcard::class,
                    'reviewable_id' => $flashcard->id,
                ], [
                    'user_id' => $learner->id,
                    'reviewable_type' => Flashcard::class,
                    'reviewable_id' => $flashcard->id,
                    'ease_factor' => match ($status) {
                        'new' => 2.50,
                        'learning' => 2.20 + (rand(0, 30) / 100),
                        'review' => 2.30 + (rand(0, 40) / 100),
                        'graduated' => 2.50 + (rand(0, 50) / 100),
                    },
                    'interval_days' => $intervalDays,
                    'repetitions' => match ($status) {
                        'new' => 0,
                        'learning' => rand(1, 3),
                        'review' => rand(4, 8),
                        'graduated' => rand(9, 20),
                    },
                    'next_review_at' => match ($status) {
                        'new' => now(),
                        'learning' => now()->subHours(rand(0, 12)),
                        'review' => now()->addDays(rand(-2, 3)),
                        'graduated' => now()->addDays(rand(10, 60)),
                    },
                    'last_reviewed_at' => $status === 'new' ? null : now()->subDays($intervalDays),
                    'status' => $status,
                ]);
            }
        }
    }
}
