<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BillingController as AdminBillingController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ContactImportController;
use App\Http\Controllers\Admin\CourseApprovalController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\CourseEnrollmentController;
use App\Http\Controllers\Admin\CourseMediaController;
use App\Http\Controllers\Admin\CRMController;
use App\Http\Controllers\Admin\CrmPipelineController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmailDesignController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\InternshipTrackController as AdminInternshipTrackController;
use App\Http\Controllers\Admin\LaunchChecklistController;
use App\Http\Controllers\Admin\LearningRuleController;
use App\Http\Controllers\Admin\MessagingController;
use App\Http\Controllers\Admin\MockExamController as AdminMockExamController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\OrganizationLicenseController;
use App\Http\Controllers\Admin\PaymentSettingsController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SimulationController as AdminSimulationController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AI\AdminAIController;
use App\Http\Controllers\AI\FacilitatorAIController;
use App\Http\Controllers\AI\StudentAIController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\Api\AiStudioController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\AuthoringCurriculumController;
use App\Http\Controllers\AuthoringMediaController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ContactMessagePreferenceController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseRatingController;
use App\Http\Controllers\CourseRevisionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\Facilitator\AnnouncementController as FacilitatorAnnouncementController;
use App\Http\Controllers\Facilitator\AssignmentController as FacilitatorAssignmentController;
use App\Http\Controllers\Facilitator\CohortController as FacilitatorCohortController;
use App\Http\Controllers\Facilitator\DashboardController as FacilitatorDashboardController;
use App\Http\Controllers\Facilitator\GradebookController as FacilitatorGradebookController;
use App\Http\Controllers\Facilitator\GradingController;
use App\Http\Controllers\Facilitator\InternshipOversightController as FacilitatorInternshipOversightController;
use App\Http\Controllers\Facilitator\InterventionController as FacilitatorInterventionController;
use App\Http\Controllers\Facilitator\MeetingController as FacilitatorMeetingController;
use App\Http\Controllers\Facilitator\WeeklyPlanController as FacilitatorWeeklyPlanController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\GamificationController;
use App\Http\Controllers\InternshipController;
use App\Http\Controllers\InternshipSubmissionController;
use App\Http\Controllers\LearnerAnalyticsController;
use App\Http\Controllers\LessonActivityController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfflineLearningController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Organization\BillingController;
use App\Http\Controllers\Organization\CourseLicenseController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\InvitationController;
use App\Http\Controllers\Organization\PeopleController;
use App\Http\Controllers\Organization\ReportController;
use App\Http\Controllers\PathwayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PrivateMediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProtectedDocumentController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\ScormController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\Sme\ContentReviewController as SmeContentReviewController;
use App\Http\Controllers\Sme\QuestionManagementController as SmeQuestionManagementController;
use App\Http\Controllers\SpacedRepetitionController;
use App\Http\Controllers\Sponsor\MeetingController;
use App\Http\Controllers\SponsorController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\SubscriptionController;
use App\Models\CohortMember;
use App\Models\DirectMessage;
use App\Models\Event;
use App\Services\CommunityAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Redirect root
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }

    return Inertia::render('Welcome');
});
Route::get('/offline', fn () => response()->view('offline'));

Route::get('/organization', App\Http\Controllers\Organization\DashboardController::class)
    ->middleware(['auth', 'role:org_admin'])->name('organization.dashboard');
Route::middleware(['auth', 'role:org_admin', 'throttle:60,1'])->prefix('organization')->group(function () {
    Route::get('/courses', [CourseLicenseController::class, 'index']);
    Route::get('/courses/catalog', [CourseLicenseController::class, 'catalog']);
    Route::post('/courses/{course}/purchase', [CourseLicenseController::class, 'purchase'])->middleware('throttle:10,1');
    Route::get('/licenses/{license}', [CourseLicenseController::class, 'show']);
    Route::get('/licenses/{license}/members', [CourseLicenseController::class, 'members']);
    Route::post('/licenses/{license}/assign', [CourseLicenseController::class, 'assign'])->middleware('throttle:15,1');
    Route::delete('/licenses/{license}/assignments/{assignment}', [CourseLicenseController::class, 'release']);
    Route::get('/billing', [BillingController::class, 'index']);
    Route::get('/billing/{uuid}', [BillingController::class, 'show']);
    Route::get('/people', [PeopleController::class, 'index'])->name('organization.people');
    Route::post('/invitations', [PeopleController::class, 'invite'])->middleware('throttle:10,1');
    Route::post('/people/import', [PeopleController::class, 'import'])->middleware('throttle:5,1');
    Route::delete('/invitations/{invitation}', [PeopleController::class, 'revoke']);
    Route::patch('/people/{member}', [PeopleController::class, 'update']);
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('throttle:8,1');
});
Route::get('/organization-invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1')->name('organization.invitation.show');
Route::post('/organization-invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:10,1')->name('organization.invitation.accept');

/*
|--------------------------------------------------------------------------
| Guest Routes (Auth)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');

    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Auth Required Routes
|--------------------------------------------------------------------------
*/
// Existing routes...

// Existing routes...

Route::middleware('auth')->group(function () {
    // Analytics & Readiness
    Route::get('/courses/{course}/analytics', [LearnerAnalyticsController::class, 'showDashboard'])->name('learner.analytics.show');

    // SCORM API Bridge
    Route::get('/media/{asset}', [PrivateMediaController::class, 'show'])->name('media.show');
    Route::get('/lessons/{lesson}/documents/{type}', [ProtectedDocumentController::class, 'lesson'])->whereIn('type', ['pdf', 'slides'])->middleware('throttle:120,1');
    Route::get('/lessons/{lesson}/documents/{type}/pages/{page}', [ProtectedDocumentController::class, 'lessonPage'])->whereIn('type', ['pdf', 'slides'])->whereNumber('page')->middleware('throttle:120,1');
    Route::get('/lessons/{lesson}/documents/{type}/source', [ProtectedDocumentController::class, 'lessonSource'])->whereIn('type', ['pdf', 'slides'])->middleware('throttle:30,1');
    Route::post('/lessons/{lesson}/submission', [LessonActivityController::class, 'submit'])->middleware('throttle:20,1')->name('lessons.submission');
    Route::get('/assignment-submissions/{submission}/files/{index}', [LessonActivityController::class, 'file'])->whereNumber('index')->middleware('throttle:60,1')->name('assignment-submissions.file');
    Route::get('/lessons/{lesson}/scorm-launch', [PrivateMediaController::class, 'launch'])->name('scorm.launch');
    Route::post('/api/scorm/commit/{lesson_id}', [ScormController::class, 'commit'])->name('scorm.commit');

    // Advanced Assessment / Mock Exam Engine Endpoints
    Route::post('/api/exams/mock/{id}/generate', [ExamController::class, 'generate']);
    Route::post('/api/exams/attempts/{id}/pause', [ExamController::class, 'pause']);
    Route::post('/api/exams/attempts/{id}/submit', [ExamController::class, 'submit']);

    // Logout
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Email Verification
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');

    // Courses (Learner)
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::post('/courses/{course}/enroll', [CourseController::class, 'enroll'])->name('courses.enroll');
    Route::get('/courses/{course:slug}/resources/{resource}/download', [CourseController::class, 'downloadResource'])
        ->name('courses.resources.download');

    // My Courses / Enrollments
    Route::get('/my-courses', [EnrollmentController::class, 'index'])->name('enrollments.index');

    // Lesson Viewer & Progress
    Route::get('/courses/{course:slug}/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::post('/lessons/{lesson}/progress', [LessonController::class, 'updateProgress'])->name('lessons.progress');
    Route::post('/lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
    Route::post('/lessons/{lesson}/bookmark', [LessonController::class, 'toggleBookmark'])->name('lessons.bookmark');
    Route::post('/lessons/{lesson}/flashcards', [LessonController::class, 'createFlashcards'])->name('lessons.flashcards');
    Route::get('/lessons/{lesson}/download/{type}', [LessonController::class, 'download'])->name('lessons.download');
    Route::get('/lessons/{lesson}/offline', [OfflineLearningController::class, 'download'])->middleware('throttle:30,1');
    Route::post('/api/progress/sync', [OfflineLearningController::class, 'sync'])->middleware('throttle:60,1');
    Route::get('/api/offline/session', [OfflineLearningController::class, 'session']);

    // Bookmarks
    Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');

    // Notes
    Route::get('/lessons/{lesson}/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/lessons/{lesson}/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Quizzes (Learner)
    Route::get('/quizzes/{quiz}', [QuizController::class, 'show'])->name('quiz.show');
    Route::post('/quizzes/{quiz}/start', [QuizController::class, 'start'])->name('quiz.start');
    Route::post('/quiz-attempts/{attempt}/answer', [QuizController::class, 'answer'])->name('quiz.answer');
    Route::post('/quiz-attempts/{attempt}/flag', [QuizController::class, 'flag'])->name('quiz.flag');
    Route::post('/quiz-attempts/{attempt}/complete', [QuizController::class, 'complete'])->name('quiz.complete');
    Route::get('/quiz-attempts/{attempt}/review', [QuizController::class, 'review'])->name('quiz.review');
    Route::get('/quiz-attempts/{attempt}', [QuizController::class, 'play'])->whereNumber('attempt')->name('quiz.play');

    // Mock Exams (Learner)
    Route::get('/mock-exams', [MockExamController::class, 'index'])->name('mock-exams.index');
    Route::get('/mock-exams/{mockExam}', [MockExamController::class, 'show'])->name('mock-exams.show');
    Route::post('/mock-exams/{mockExam}/start', [MockExamController::class, 'start'])->name('mock-exams.start');
    Route::post('/mock-attempts/{attempt}/pause', [MockExamController::class, 'pause'])->name('mock-exams.pause');
    Route::post('/mock-attempts/{attempt}/resume', [MockExamController::class, 'resume'])->name('mock-exams.resume');
    Route::post('/mock-attempts/{attempt}/answer', [MockExamController::class, 'answer'])->name('mock-exams.answer');
    Route::post('/mock-attempts/{attempt}/complete', [MockExamController::class, 'complete'])->name('mock-exams.complete');
    Route::get('/mock-attempts/{attempt}/report', [MockExamController::class, 'report'])->name('mock-exams.report');

    // Practice & Spaced Repetition
    Route::get('/practice', [SpacedRepetitionController::class, 'dashboard'])->name('practice.dashboard');
    Route::get('/practice/review', [SpacedRepetitionController::class, 'review'])->name('practice.review');
    Route::post('/practice/review/{item}', [SpacedRepetitionController::class, 'submitReview'])->name('practice.submitReview');
    Route::get('/practice/due-count', [SpacedRepetitionController::class, 'dueCount'])->name('practice.dueCount');

    // Flashcards
    Route::get('/flashcards', [SpacedRepetitionController::class, 'flashcards'])->name('practice.flashcards');
    Route::post('/flashcards', [SpacedRepetitionController::class, 'storeFlashcard'])->name('practice.flashcards.store');
    Route::put('/flashcards/{flashcard}', [SpacedRepetitionController::class, 'updateFlashcard'])->name('practice.flashcards.update');
    Route::delete('/flashcards/{flashcard}', [SpacedRepetitionController::class, 'destroyFlashcard'])->name('practice.flashcards.destroy');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/weekly-report', [AnalyticsController::class, 'weeklyReport'])->name('analytics.weeklyReport');
    Route::get('/analytics/mastery', [AnalyticsController::class, 'mastery'])->name('analytics.mastery');

    // Gamification
    Route::get('/badges', [GamificationController::class, 'badges'])->name('gamification.badges');
    Route::get('/missions', [GamificationController::class, 'missions'])->name('gamification.missions');
    Route::get('/leaderboard', [GamificationController::class, 'leaderboard'])->name('gamification.leaderboard');
    Route::get('/gamification/summary', [GamificationController::class, 'summary'])->name('gamification.summary');

    // Internship (Learner)
    Route::get('/internship', [InternshipController::class, 'dashboard'])->name('internship.dashboard');
    Route::get('/internship/{enrollment}/tasks', [InternshipController::class, 'taskBoard'])->name('internship.taskBoard');
    Route::put('/internship/tasks/{task}/status', [InternshipController::class, 'updateTaskStatus'])->name('internship.task.updateStatus');

    // Internship Submissions (Learner)
    Route::get('/internship/submissions', [InternshipSubmissionController::class, 'index'])->name('internship.submissions.index');
    Route::post('/internship/tasks/{task}/submit', [InternshipSubmissionController::class, 'store'])->name('internship.submissions.store');
    Route::get('/internship/submissions/{submission}', [InternshipSubmissionController::class, 'show'])->name('internship.submissions.show');
    Route::post('/internship/submissions/{submission}/resubmit', [InternshipSubmissionController::class, 'resubmit'])->name('internship.submissions.resubmit');

    // Portfolio (Learner)
    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio.index');
    Route::post('/portfolio', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::put('/portfolio/{portfolioItem}', [PortfolioController::class, 'update'])->name('portfolio.update');
    Route::delete('/portfolio/{portfolioItem}', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');
    Route::get('/portfolio/export', [PortfolioController::class, 'export'])->name('portfolio.export');

    // Certificates (Learner)
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/{uuid}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{uuid}/download', [CertificateController::class, 'download'])->name('certificates.download');

    // Simulations (Learner)
    Route::get('/simulations', [SimulationController::class, 'index'])->name('simulations.index');
    Route::get('/simulations/{simulation}', [SimulationController::class, 'show'])->name('simulations.show');
    Route::post('/simulations/{simulation}/start', [SimulationController::class, 'start'])->name('simulations.start');
    Route::post('/simulation-attempts/{attempt}/resume', [SimulationController::class, 'resume'])->name('simulations.resume');
    Route::post('/simulation-attempts/{attempt}/choose', [SimulationController::class, 'choose'])->name('simulations.choose');
    Route::post('/simulation-attempts/{attempt}/complete', [SimulationController::class, 'complete'])->name('simulations.complete');
    Route::get('/simulation-attempts/{attempt}/report', [SimulationController::class, 'report'])->name('simulations.report');

    // My Cohorts (Learner)
    Route::get('/my-cohorts', function () {
        $user = auth()->user();
        $cohorts = CohortMember::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['cohort.course', 'cohort.facilitator'])
            ->get()
            ->pluck('cohort')
            ->filter();

        return Inertia::render('MyCohorts', ['cohorts' => $cohorts->values()]);
    })->name('my-cohorts');

    // Storefront
    Route::get('/storefront', [StorefrontController::class, 'index'])->name('storefront.index');
    Route::get('/storefront/{slug}', [StorefrontController::class, 'show'])->name('storefront.show');

    // Orders & Checkout
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{uuid}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{uuid}/resume', [OrderController::class, 'resume'])->middleware('throttle:10,1')->name('orders.resume');
    Route::post('/orders/{uuid}/refresh-payment', [OrderController::class, 'refreshPayment'])->middleware('throttle:10,1')->name('orders.refresh-payment');
    Route::post('/cart/validate-discount', [OrderController::class, 'validateDiscount'])->name('cart.validateDiscount');

    // Subscriptions (Learner)
    Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions/{plan}/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscriptions.subscribe');
    Route::post('/subscriptions/{plan}/paystack/initialize', [SubscriptionController::class, 'initializePaystack'])->name('subscriptions.paystack.initialize');
    Route::get('/subscriptions/paystack/callback', [SubscriptionController::class, 'paystackCallback'])->name('subscriptions.paystack.callback');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.updatePreferences');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');

    // Community Hub
    Route::get('/community', function () {
        $user = auth()->user();
        $forumCount = app(CommunityAccessService::class)->forums($user)->count();
        $studyGroupCount = app(CommunityAccessService::class)->groups($user)->count();
        $upcomingEvents = Event::published()->upcoming()->count();
        $unreadMessages = DirectMessage::unreadCountFor($user->id);

        return Inertia::render('Community/Dashboard', [
            'stats' => [
                'forums' => $forumCount,
                'studyGroups' => $studyGroupCount,
                'upcomingEvents' => $upcomingEvents,
                'unreadMessages' => $unreadMessages,
            ],
        ]);
    })->name('community.dashboard');

    // Forums
    Route::get('/forums', [ForumController::class, 'index'])->name('forums.index');
    Route::get('/forums/search', [ForumController::class, 'search'])->name('forums.search');
    Route::get('/forums/{forum}', [ForumController::class, 'show'])->name('forums.show');
    Route::post('/forums/{forum}/threads', [ForumController::class, 'storeThread'])->name('forums.threads.store');
    Route::get('/threads/{thread}', [ForumController::class, 'thread'])->name('forums.threads.show');
    Route::post('/threads/{thread}/replies', [ForumController::class, 'storeReply'])->name('forums.replies.store');
    Route::put('/threads/{thread}/pin', [ForumController::class, 'togglePin'])->name('forums.threads.pin');
    Route::put('/threads/{thread}/lock', [ForumController::class, 'toggleLock'])->name('forums.threads.lock');
    Route::delete('/threads/{thread}', [ForumController::class, 'destroyThread'])->name('forums.threads.destroy');
    Route::delete('/replies/{reply}', [ForumController::class, 'destroyReply'])->name('forums.replies.destroy');
    Route::put('/replies/{reply}/accept', [ForumController::class, 'acceptAnswer'])->name('forums.replies.accept');

    // Direct Messages
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/users/search', [MessageController::class, 'searchUsers'])->name('messages.searchUsers');
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount'])->name('messages.unreadCount');
    Route::get('/messages/{partner}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

    // Study Groups
    Route::get('/study-groups', [StudyGroupController::class, 'index'])->name('study-groups.index');
    Route::post('/study-groups', [StudyGroupController::class, 'store'])->name('study-groups.store');
    Route::get('/study-groups/{group}', [StudyGroupController::class, 'show'])->name('study-groups.show');
    Route::post('/study-groups/{group}/join', [StudyGroupController::class, 'join'])->name('study-groups.join');
    Route::post('/study-groups/{group}/leave', [StudyGroupController::class, 'leave'])->name('study-groups.leave');
    Route::delete('/study-groups/{group}', [StudyGroupController::class, 'destroy'])->name('study-groups.destroy');

    // Events
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events/{event}/register', [EventController::class, 'register'])->name('events.register');
    Route::delete('/events/{event}/register', [EventController::class, 'cancelRegistration'])->name('events.cancelRegistration');

    // Career Pathways
    Route::get('/pathways', [PathwayController::class, 'index'])->name('pathways.index');
    Route::get('/pathways/{slug}', [PathwayController::class, 'show'])->name('pathways.show');

    // Course Ratings
    Route::post('/course-ratings', [CourseRatingController::class, 'store'])->name('course-ratings.store');
    Route::delete('/course-ratings/{course}', [CourseRatingController::class, 'destroy'])->name('course-ratings.destroy');
    Route::get('/courses/{course}/ratings', [CourseRatingController::class, 'getRatings'])->name('course-ratings.get');

    // Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/step', [OnboardingController::class, 'saveStep'])->name('onboarding.saveStep');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    // Downloads / Offline
    Route::get('/downloads', fn () => Inertia::render('Downloads/Index'))->name('downloads.index');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:platform_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/organizations/administrator-candidates', [OrganizationController::class, 'candidates'])->middleware('throttle:60,1')->name('admin.organizations.candidates');
        Route::resource('organizations', OrganizationController::class)->only(['index', 'create', 'store', 'edit', 'update'])->names('admin.organizations');
        Route::get('/organization-license-courses', [OrganizationLicenseController::class, 'courses']);
        Route::get('/organizations/{organization}/licenses', [OrganizationLicenseController::class, 'index']);
        Route::post('/organizations/{organization}/licenses', [OrganizationLicenseController::class, 'store']);
        Route::patch('/organizations/{organization}/licenses/{license}', [OrganizationLicenseController::class, 'update']);

        // B2B CRM Pipelines
        Route::get('/crm/pipeline', [CrmPipelineController::class, 'index'])->name('admin.crm.pipeline');
        Route::post('/crm/pipeline/{deal}/stage', [CrmPipelineController::class, 'updateDealStage'])->name('admin.crm.pipeline.stage');

        // Subscriptions
        Route::get('/subscriptions/plans', [SubscriptionPlanController::class, 'index'])->name('admin.subscriptions.plans');
        Route::post('/subscriptions/invoice-link', [SubscriptionPlanController::class, 'generateInvoiceLink'])->name('admin.subscriptions.invoice');
        Route::put('/subscriptions/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('admin.subscriptions.plans.update');

        // Course Media Uploads (Chunked Video & SCORM)
        Route::post('/media/video', [CourseMediaController::class, 'uploadVideo'])->name('admin.media.video.upload');
        Route::post('/media/scorm', [CourseMediaController::class, 'uploadScorm'])->name('admin.media.scorm.upload');

        // User Management
        Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('admin.users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::get('/users/import', [AdminUserController::class, 'importForm'])->name('admin.users.import');
        Route::post('/users/import', [AdminUserController::class, 'import'])->name('admin.users.import.store');

        // Category Management
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('admin.categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');
        Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder'])->name('admin.categories.reorder');

        // Course Management
        Route::get('/courses', [AdminCourseController::class, 'index'])->name('admin.courses.index');
        Route::get('/courses/create', [AdminCourseController::class, 'create'])->name('admin.courses.create');
        Route::post('/courses', [AdminCourseController::class, 'store'])->name('admin.courses.store');
        Route::get('/courses/{course}/edit', [AdminCourseController::class, 'edit'])->name('admin.courses.edit');
        Route::get('/courses/{course}/learners', [CourseEnrollmentController::class, 'index'])->name('admin.courses.learners.index');
        Route::get('/courses/{course}/learners/search', [CourseEnrollmentController::class, 'search'])->middleware('throttle:60,1')->name('admin.courses.learners.search');
        Route::post('/courses/{course}/enrollments', [CourseEnrollmentController::class, 'store'])->middleware('throttle:60,1')->name('admin.courses.enrollments.store');
        Route::put('/courses/{course}', [AdminCourseController::class, 'update'])->name('admin.courses.update');
        Route::delete('/courses/{course}', [AdminCourseController::class, 'destroy'])->name('admin.courses.destroy');
        Route::put('/courses/{course}/publish', [AdminCourseController::class, 'publish'])->name('admin.courses.publish');
        Route::put('/courses/{course}/unpublish', [AdminCourseController::class, 'unpublish'])->name('admin.courses.unpublish');

        // Module Management
        Route::post('/courses/{course}/modules', [AdminCourseController::class, 'storeModule'])->name('admin.modules.store');
        Route::put('/modules/{module}', [AdminCourseController::class, 'updateModule'])->name('admin.modules.update');
        Route::delete('/modules/{module}', [AdminCourseController::class, 'destroyModule'])->name('admin.modules.destroy');
        Route::post('/courses/{course}/modules/reorder', [AdminCourseController::class, 'reorderModules'])->name('admin.modules.reorder');

        // Lesson Management
        Route::get('/modules/{module}/lessons/create', [AdminCourseController::class, 'createLesson'])->name('admin.lessons.create');
        Route::post('/modules/{module}/lessons', [AdminCourseController::class, 'storeLesson'])->name('admin.lessons.store');
        Route::get('/lessons/{lesson}/edit', [AdminCourseController::class, 'editLesson'])->name('admin.lessons.edit');
        Route::put('/lessons/{lesson}', [AdminCourseController::class, 'updateLesson'])->name('admin.lessons.update');
        Route::delete('/lessons/{lesson}', [AdminCourseController::class, 'destroyLesson'])->name('admin.lessons.destroy');
        Route::post('/modules/{module}/lessons/reorder', [AdminCourseController::class, 'reorderLessons'])->name('admin.lessons.reorder');

        // Resource Management
        Route::post('/courses/{course}/resources', [AdminCourseController::class, 'storeResource'])->name('admin.resources.store');
        Route::delete('/resources/{resource}', [AdminCourseController::class, 'destroyResource'])->name('admin.resources.destroy');

        // Question Bank
        Route::get('/questions', [AdminQuestionController::class, 'index'])->name('admin.questions.index');
        Route::get('/questions/create', [AdminQuestionController::class, 'create'])->name('admin.questions.create');
        Route::post('/questions', [AdminQuestionController::class, 'store'])->name('admin.questions.store');
        Route::get('/questions/{question}/edit', [AdminQuestionController::class, 'edit'])->name('admin.questions.edit');
        Route::put('/questions/{question}', [AdminQuestionController::class, 'update'])->name('admin.questions.update');
        Route::delete('/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('admin.questions.destroy');
        Route::get('/courses/{course}/lessons-json', [AdminQuestionController::class, 'lessonsForCourse'])->name('admin.courses.lessons');

        // Quiz Builder
        Route::get('/quizzes', [AdminQuizController::class, 'index'])->name('admin.quizzes.index');
        Route::get('/quizzes/create', [AdminQuizController::class, 'create'])->name('admin.quizzes.create');
        Route::post('/quizzes', [AdminQuizController::class, 'store'])->name('admin.quizzes.store');
        Route::get('/quizzes/{quiz}/edit', [AdminQuizController::class, 'edit'])->name('admin.quizzes.edit');
        Route::put('/quizzes/{quiz}', [AdminQuizController::class, 'update'])->name('admin.quizzes.update');
        Route::delete('/quizzes/{quiz}', [AdminQuizController::class, 'destroy'])->name('admin.quizzes.destroy');
        Route::post('/quizzes/{quiz}/questions', [AdminQuizController::class, 'addQuestions'])->name('admin.quizzes.addQuestions');
        Route::delete('/quizzes/{quiz}/questions/{question}', [AdminQuizController::class, 'removeQuestion'])->name('admin.quizzes.removeQuestion');
        Route::post('/quizzes/{quiz}/questions/reorder', [AdminQuizController::class, 'reorderQuestions'])->name('admin.quizzes.reorderQuestions');
        Route::put('/quizzes/{quiz}/publish', [AdminQuizController::class, 'publish'])->name('admin.quizzes.publish');
        Route::put('/quizzes/{quiz}/unpublish', [AdminQuizController::class, 'unpublish'])->name('admin.quizzes.unpublish');

        // Mock Exam Management
        Route::get('/mock-exams', [AdminMockExamController::class, 'index'])->name('admin.mock-exams.index');
        Route::get('/mock-exams/create', [AdminMockExamController::class, 'create'])->name('admin.mock-exams.create');
        Route::post('/mock-exams', [AdminMockExamController::class, 'store'])->name('admin.mock-exams.store');
        Route::get('/mock-exams/{mockExam}/edit', [AdminMockExamController::class, 'edit'])->name('admin.mock-exams.edit');
        Route::put('/mock-exams/{mockExam}', [AdminMockExamController::class, 'update'])->name('admin.mock-exams.update');
        Route::delete('/mock-exams/{mockExam}', [AdminMockExamController::class, 'destroy'])->name('admin.mock-exams.destroy');
        Route::put('/mock-exams/{mockExam}/publish', [AdminMockExamController::class, 'publish'])->name('admin.mock-exams.publish');
        Route::put('/mock-exams/{mockExam}/unpublish', [AdminMockExamController::class, 'unpublish'])->name('admin.mock-exams.unpublish');

        // Reports
        Route::get('/reports', [AdminReportController::class, 'index'])->name('admin.reports.index');
        Route::get('/reports/executive', [AdminReportController::class, 'executive'])->name('admin.reports.executive');
        Route::get('/reports/compliance', [AdminReportController::class, 'compliance'])->name('admin.reports.compliance');
        Route::get('/reports/department', [AdminReportController::class, 'department'])->name('admin.reports.department');
        Route::get('/reports/internship', [AdminReportController::class, 'internship'])->name('admin.reports.internship');
        Route::get('/launch-checklist', [LaunchChecklistController::class, 'index'])->name('admin.launch.checklist');
        Route::put('/launch-checklist/{item}', [LaunchChecklistController::class, 'update'])->name('admin.launch.checklist.update');
        Route::put('/enrollments/{enrollment}/status', [App\Http\Controllers\Admin\EnrollmentController::class, 'updateStatus'])->name('admin.enrollments.status');
        Route::post('/enrollments/auto-expire', [App\Http\Controllers\Admin\EnrollmentController::class, 'autoExpireByDeadline'])->name('admin.enrollments.auto-expire');
        Route::put('/enrollments/bulk', [App\Http\Controllers\Admin\EnrollmentController::class, 'bulkUpdate'])->name('admin.enrollments.bulk');

        // Settings
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
        Route::get('/settings/payments', [PaymentSettingsController::class, 'index'])->name('admin.settings.payments');
        Route::put('/settings/payments', [PaymentSettingsController::class, 'update'])->middleware('throttle:5,1')->name('admin.settings.payments.update');
        // WhatsApp Number Pairings
        Route::get('/settings/whatsapp-pairings', [AdminSettingsController::class, 'waPairings'])->name('admin.settings.wa-pairings');
        Route::patch('/settings/whatsapp-pairings/{user}/pair', [AdminSettingsController::class, 'pairUser'])->name('admin.settings.wa-pair');
        Route::delete('/settings/whatsapp-pairings/{user}/unpair', [AdminSettingsController::class, 'unpairUser'])->name('admin.settings.wa-unpair');
        Route::post('/settings/whatsapp-pairings/bulk', [AdminSettingsController::class, 'bulkPairUsers'])->name('admin.settings.wa-bulk-pair');
        Route::post('/settings/whatsapp-pairings/auto-pair', [AdminSettingsController::class, 'autoPairFromPhone'])->name('admin.settings.wa-auto-pair');

        // Preserve bookmarks and links from the previous Commerce navigation.
        Route::redirect('/commerce', '/admin/billing')->name('admin.commerce');

        // Billing
        Route::get('/billing', [AdminBillingController::class, 'index'])->name('admin.billing.index');
        Route::post('/billing/refund/{payment}', [AdminBillingController::class, 'refund'])->name('admin.billing.refund');
        Route::post('/billing/refund/{payment}/reconcile', [AdminBillingController::class, 'reconcileRefund'])->middleware('throttle:10,1')->name('admin.billing.refund.reconcile');

        // Audit Logs
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('admin.audit-logs.index');
        Route::get('/audit-logs/export', [AdminAuditLogController::class, 'export'])->name('admin.audit-logs.export');
        Route::get('/audit-logs/{log}', [AdminAuditLogController::class, 'show'])->name('admin.audit-logs.show');

        // Events Management
        Route::get('/events', [AdminEventController::class, 'index'])->name('admin.events.index');
        Route::get('/events/create', [AdminEventController::class, 'create'])->name('admin.events.create');
        Route::post('/events', [AdminEventController::class, 'store'])->name('admin.events.store');
        Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])->name('admin.events.edit');
        Route::put('/events/{event}', [AdminEventController::class, 'update'])->name('admin.events.update');
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])->name('admin.events.destroy');
        Route::get('/events/{event}/registrations', [AdminEventController::class, 'registrations'])->name('admin.events.registrations');
        Route::post('/events/{event}/attendance', [AdminEventController::class, 'markAttendance'])->name('admin.events.attendance');
    });

/*
|--------------------------------------------------------------------------
| Facilitator Routes
|--------------------------------------------------------------------------
*/

Route::post('/authoring/media/{type}', [AuthoringMediaController::class, 'store'])
    ->middleware(['auth', 'role:platform_admin|facilitator', 'throttle:120,1'])->name('authoring.media.store');
Route::get('/authoring/documents/{asset}', [ProtectedDocumentController::class, 'preview'])
    ->middleware(['auth', 'throttle:120,1']);
Route::get('/authoring/documents/{asset}/pages/{page}', [ProtectedDocumentController::class, 'previewPage'])
    ->whereNumber('page')->middleware(['auth', 'throttle:120,1']);
Route::get('/authoring/documents/{asset}/source', [ProtectedDocumentController::class, 'previewSource'])
    ->middleware(['auth', 'throttle:30,1']);
Route::middleware(['auth', 'role:platform_admin|facilitator'])->group(function () {
    Route::put('/authoring/courses/{course}/drip', [AuthoringCurriculumController::class, 'drip'])->name('authoring.curriculum.drip');
    Route::get('/authoring/lessons/{lesson}/submissions', [LessonActivityController::class, 'submissions'])->name('authoring.lessons.submissions');
    Route::put('/authoring/submissions/{submission}/grade', [LessonActivityController::class, 'grade'])->name('authoring.submissions.grade');
});
Route::post('/authoring/courses/{course}/reorder', [AuthoringCurriculumController::class, 'reorder'])
    ->middleware(['auth', 'role:platform_admin|facilitator'])->name('authoring.curriculum.reorder');

Route::middleware(['auth', 'role:facilitator'])
    ->prefix('facilitator')
    ->group(function () {
        // Dashboard
        Route::get('/', FacilitatorDashboardController::class)->name('facilitator.dashboard');

        // Grading Portal
        Route::get('/grading', [GradingController::class, 'index'])->name('facilitator.grading.index');
        Route::post('/grading/{id}', [GradingController::class, 'storeGrade'])->name('facilitator.grading.store');

        // AI Studio Endpoint
        Route::post('/ai-studio/generate-quiz', [AiStudioController::class, 'generateQuiz'])->middleware('throttle:10,1')->name('facilitator.ai.generate');

        // Cohorts CRUD
        Route::get('/cohorts', [FacilitatorCohortController::class, 'index'])->name('facilitator.cohorts.index');
        Route::get('/cohorts/create', [FacilitatorCohortController::class, 'create'])->name('facilitator.cohorts.create');
        Route::post('/cohorts', [FacilitatorCohortController::class, 'store'])->name('facilitator.cohorts.store');
        Route::get('/cohorts/{cohort}', [FacilitatorCohortController::class, 'show'])->name('facilitator.cohorts.show');
        Route::get('/cohorts/{cohort}/edit', [FacilitatorCohortController::class, 'edit'])->name('facilitator.cohorts.edit');
        Route::put('/cohorts/{cohort}', [FacilitatorCohortController::class, 'update'])->name('facilitator.cohorts.update');
        Route::get('/cohorts/{cohort}/gradebook', [FacilitatorGradebookController::class, 'index'])->name('facilitator.gradebook.index');

        // Cohort Members
        Route::post('/cohorts/{cohort}/members', [FacilitatorCohortController::class, 'addMember'])->name('facilitator.cohorts.addMember');
        Route::delete('/cohorts/{cohort}/members/{member}', [FacilitatorCohortController::class, 'removeMember'])->name('facilitator.cohorts.removeMember');

        // Learner 360
        Route::get('/cohorts/{cohort}/learners/{user}', [FacilitatorCohortController::class, 'learner360'])->name('facilitator.cohorts.learner360');

        // Weekly Plans
        Route::get('/cohorts/{cohort}/plans', [FacilitatorWeeklyPlanController::class, 'index'])->name('facilitator.plans.index');
        Route::post('/cohorts/{cohort}/plans', [FacilitatorWeeklyPlanController::class, 'storeWeek'])->name('facilitator.plans.storeWeek');
        Route::put('/cohorts/{cohort}/plans/{plan}', [FacilitatorWeeklyPlanController::class, 'updateWeek'])->name('facilitator.plans.updateWeek');
        Route::delete('/cohorts/{cohort}/plans/{plan}', [FacilitatorWeeklyPlanController::class, 'destroyWeek'])->name('facilitator.plans.destroyWeek');
        Route::post('/cohorts/{cohort}/plans/{plan}/items', [FacilitatorWeeklyPlanController::class, 'addItem'])->name('facilitator.plans.addItem');
        Route::delete('/cohorts/{cohort}/items/{item}', [FacilitatorWeeklyPlanController::class, 'removeItem'])->name('facilitator.plans.removeItem');
        Route::post('/cohorts/{cohort}/plans/{plan}/reorder', [FacilitatorWeeklyPlanController::class, 'reorder'])->name('facilitator.plans.reorder');

        // Announcements
        Route::get('/cohorts/{cohort}/announcements', [FacilitatorAnnouncementController::class, 'index'])->name('facilitator.announcements.index');
        Route::post('/cohorts/{cohort}/announcements', [FacilitatorAnnouncementController::class, 'store'])->name('facilitator.announcements.store');
        Route::put('/cohorts/{cohort}/announcements/{announcement}', [FacilitatorAnnouncementController::class, 'update'])->name('facilitator.announcements.update');
        Route::delete('/cohorts/{cohort}/announcements/{announcement}', [FacilitatorAnnouncementController::class, 'destroy'])->name('facilitator.announcements.destroy');

        // Interventions
        Route::get('/cohorts/{cohort}/interventions', [FacilitatorInterventionController::class, 'index'])->name('facilitator.interventions.index');
        Route::post('/cohorts/{cohort}/interventions', [FacilitatorInterventionController::class, 'store'])->name('facilitator.interventions.store');
        Route::put('/cohorts/{cohort}/interventions/{intervention}/status', [FacilitatorInterventionController::class, 'updateStatus'])->name('facilitator.interventions.updateStatus');

        // Nudge
        Route::post('/cohorts/{cohort}/nudge', [FacilitatorInterventionController::class, 'sendNudge'])->name('facilitator.nudge.send');
        Route::get('/nudge-templates', [FacilitatorInterventionController::class, 'templates'])->name('facilitator.nudge.templates');

        // Internship Oversight
        Route::get('/internship-oversight/health', [FacilitatorInternshipOversightController::class, 'health'])->name('facilitator.internshipOversight.health');
        Route::get('/internship-oversight/portfolios', [FacilitatorInternshipOversightController::class, 'portfolios'])->name('facilitator.internshipOversight.portfolios');

        // Assignments
        Route::get('/cohorts/{cohort}/assignments', [FacilitatorAssignmentController::class, 'index'])->name('facilitator.assignments.index');
        Route::get('/cohorts/{cohort}/assignments/create', [FacilitatorAssignmentController::class, 'create'])->name('facilitator.assignments.create');
        Route::post('/cohorts/{cohort}/assignments', [FacilitatorAssignmentController::class, 'store'])->name('facilitator.assignments.store');
        Route::get('/cohorts/{cohort}/assignments/{assignment}/edit', [FacilitatorAssignmentController::class, 'edit'])->name('facilitator.assignments.edit');
        Route::put('/cohorts/{cohort}/assignments/{assignment}', [FacilitatorAssignmentController::class, 'update'])->name('facilitator.assignments.update');
        Route::delete('/cohorts/{cohort}/assignments/{assignment}', [FacilitatorAssignmentController::class, 'destroy'])->name('facilitator.assignments.destroy');
        Route::get('/cohorts/{cohort}/assignments/{assignment}/submissions', [FacilitatorAssignmentController::class, 'submissions'])->name('facilitator.assignments.submissions');
        Route::put('/cohorts/{cohort}/submissions/{submission}/grade', [FacilitatorAssignmentController::class, 'grade'])->name('facilitator.assignments.grade');

        // Meetings / Live Sessions
        Route::get('/cohorts/{cohort}/meetings', [FacilitatorMeetingController::class, 'index'])->name('facilitator.meetings.index');
        Route::post('/cohorts/{cohort}/meetings', [FacilitatorMeetingController::class, 'store'])->name('facilitator.meetings.store');
        Route::put('/cohorts/{cohort}/meetings/{meeting}', [FacilitatorMeetingController::class, 'update'])->name('facilitator.meetings.update');
        Route::delete('/cohorts/{cohort}/meetings/{meeting}', [FacilitatorMeetingController::class, 'destroy'])->name('facilitator.meetings.destroy');
        Route::post('/cohorts/{cohort}/meetings/{meeting}/attendance', [FacilitatorMeetingController::class, 'markAttendance'])->name('facilitator.meetings.attendance');
        Route::post('/cohorts/{cohort}/meetings/{meeting}/notes', [FacilitatorMeetingController::class, 'saveNotes'])->name('facilitator.meetings.notes');
    });

/*
|--------------------------------------------------------------------------
| Admin — Internship Track Management
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:platform_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/internships', [AdminInternshipTrackController::class, 'index'])->name('admin.internships.index');
        Route::get('/internships/create', [AdminInternshipTrackController::class, 'create'])->name('admin.internships.create');
        Route::post('/internships', [AdminInternshipTrackController::class, 'store'])->name('admin.internships.store');
        Route::get('/internships/{track}', [AdminInternshipTrackController::class, 'show'])->name('admin.internships.show');
        Route::put('/internships/{track}', [AdminInternshipTrackController::class, 'update'])->name('admin.internships.update');
        Route::delete('/internships/{track}', [AdminInternshipTrackController::class, 'destroy'])->name('admin.internships.destroy');

        // Milestones
        Route::post('/internships/{track}/milestones', [AdminInternshipTrackController::class, 'storeMilestone'])->name('admin.internships.milestones.store');
        Route::delete('/internships/{track}/milestones/{milestone}', [AdminInternshipTrackController::class, 'destroyMilestone'])->name('admin.internships.milestones.destroy');

        // Enrollments
        Route::post('/internships/{track}/enrollments', [AdminInternshipTrackController::class, 'storeEnrollment'])->name('admin.internships.enrollments.store');
        Route::put('/enrollments/{enrollment}', [AdminInternshipTrackController::class, 'updateEnrollment'])->name('admin.internships.enrollments.update');

        // Enrollment Tasks
        Route::get('/enrollments/{enrollment}/tasks', [AdminInternshipTrackController::class, 'enrollmentTasks'])->name('admin.internships.enrollments.tasks');
        Route::post('/enrollments/{enrollment}/tasks', [AdminInternshipTrackController::class, 'storeTask'])->name('admin.internships.enrollments.tasks.store');
        Route::put('/tasks/{task}', [AdminInternshipTrackController::class, 'updateTask'])->name('admin.internships.tasks.update');
        Route::delete('/tasks/{task}', [AdminInternshipTrackController::class, 'destroyTask'])->name('admin.internships.tasks.destroy');

        // Simulation Management
        Route::get('/simulations', [AdminSimulationController::class, 'index'])->name('admin.simulations.index');
        Route::get('/simulations/create', [AdminSimulationController::class, 'create'])->name('admin.simulations.create');
        Route::post('/simulations', [AdminSimulationController::class, 'store'])->name('admin.simulations.store');
        Route::get('/simulations/{simulation}', [AdminSimulationController::class, 'show'])->name('admin.simulations.show');
        Route::get('/simulations/{simulation}/edit', [AdminSimulationController::class, 'edit'])->name('admin.simulations.edit');
        Route::put('/simulations/{simulation}', [AdminSimulationController::class, 'update'])->name('admin.simulations.update');
        Route::delete('/simulations/{simulation}', [AdminSimulationController::class, 'destroy'])->name('admin.simulations.destroy');
        Route::put('/simulations/{simulation}/publish', [AdminSimulationController::class, 'publish'])->name('admin.simulations.publish');
        Route::put('/simulations/{simulation}/unpublish', [AdminSimulationController::class, 'unpublish'])->name('admin.simulations.unpublish');

        // Simulation Nodes & Choices
        Route::post('/simulations/{simulation}/nodes', [AdminSimulationController::class, 'storeNode'])->name('admin.simulations.nodes.store');
        Route::put('/simulation-nodes/{node}', [AdminSimulationController::class, 'updateNode'])->name('admin.simulations.nodes.update');
        Route::delete('/simulation-nodes/{node}', [AdminSimulationController::class, 'destroyNode'])->name('admin.simulations.nodes.destroy');
    });

/*
|--------------------------------------------------------------------------
| Admin — Course Approval Workflow (Platform Admin only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:platform_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/course-approvals', [CourseApprovalController::class, 'index'])->name('admin.course-approvals.index');
        Route::get('/course-approvals/{course}', [CourseApprovalController::class, 'show'])->name('admin.course-approvals.show');
        Route::post('/course-approvals/{course}/approve', [CourseApprovalController::class, 'approve'])->name('admin.course-approvals.approve');
        Route::post('/course-approvals/{course}/reject', [CourseApprovalController::class, 'reject'])->name('admin.course-approvals.reject');
        Route::put('/course-approvals/{course}/unpublish', [CourseApprovalController::class, 'unpublish'])->name('admin.course-approvals.unpublish');
    });

/*
|--------------------------------------------------------------------------
| Admin — ERP / CRM
|--------------------------------------------------------------------------
*/
// Platform CRM contains global contacts and campaigns; organization roles must not access it.
Route::middleware(['auth', 'role:platform_admin'])->group(function () {
    Route::get('/admin/learning-rules', [LearningRuleController::class, 'index'])->name('admin.learning-rules.index');
    Route::put('/admin/learning-rules/{course}', [LearningRuleController::class, 'update'])->name('admin.learning-rules.update');
});
Route::middleware(['auth', 'role:platform_admin'])
    ->prefix('admin/erp')
    ->group(function () {
        // CRM Dashboard
        Route::get('/', [CRMController::class, 'dashboard'])->name('admin.erp.dashboard');

        // Contacts
        Route::get('/contacts', [CRMController::class, 'contactsIndex'])->name('admin.crm.contacts.index');
        Route::post('/contacts', [CRMController::class, 'contactStore'])->name('admin.crm.contacts.store');
        Route::get('/contacts/import/template', [CRMController::class, 'contactImportTemplate'])->name('admin.crm.contacts.import.template');
        Route::get('/contacts/imports', [ContactImportController::class, 'index'])->name('admin.crm.contact-imports.index');
        Route::post('/contacts/imports', [ContactImportController::class, 'store'])->middleware('throttle:10,1')->name('admin.crm.contact-imports.store');
        Route::post('/contacts/imports/{id}/retry', [ContactImportController::class, 'retry'])->middleware('throttle:10,1');
        Route::post('/contacts/imports/{id}/cancel', [ContactImportController::class, 'cancel']);
        Route::get('/contacts/imports/{id}/errors', [ContactImportController::class, 'errors']);
        Route::post('/contacts/import/preview', [CRMController::class, 'contactImportPreview'])->middleware('throttle:10,1')->name('admin.crm.contacts.import.preview');
        Route::post('/contacts/import', [CRMController::class, 'contactImport'])->middleware('throttle:10,1')->name('admin.crm.contacts.import');
        Route::get('/contacts/{contact}', [CRMController::class, 'contactShow'])->name('admin.crm.contacts.show');
        Route::put('/contacts/{contact}', [CRMController::class, 'contactUpdate'])->name('admin.crm.contacts.update');
        Route::delete('/contacts/{contact}', [CRMController::class, 'contactDestroy'])->name('admin.crm.contacts.destroy');

        // Deals / Pipeline
        Route::get('/deals', [CRMController::class, 'dealsIndex'])->name('admin.crm.deals.index');
        Route::post('/deals', [CRMController::class, 'dealStore'])->name('admin.crm.deals.store');
        Route::put('/deals/{deal}', [CRMController::class, 'dealUpdate'])->name('admin.crm.deals.update');

        // Activities
        Route::post('/activities', [CRMController::class, 'activityStore'])->name('admin.crm.activities.store');
        Route::put('/activities/{activity}/complete', [CRMController::class, 'activityComplete'])->name('admin.crm.activities.complete');

        // Messaging Campaigns
        Route::get('/messaging', [MessagingController::class, 'index'])->name('admin.messaging.index');
        Route::post('/messaging', [MessagingController::class, 'store'])->name('admin.messaging.store');
        Route::post('/messaging/audience-preview', [MessagingController::class, 'audiencePreview'])->middleware('throttle:30,1')->name('admin.messaging.audiencePreview');
        Route::get('/email-design', [EmailDesignController::class, 'index'])->name('admin.email-design');
        Route::post('/email-design/branding', [EmailDesignController::class, 'updateBranding'])->name('admin.email-design.branding');
        Route::post('/email-design/preview', [EmailDesignController::class, 'preview'])->middleware('throttle:60,1')->name('admin.email-design.preview');
        Route::post('/email-design/test', [EmailDesignController::class, 'sendTest'])->middleware('throttle:6,1')->name('admin.email-design.test');
        Route::get('/email-templates', [EmailDesignController::class, 'templatesJson'])->name('admin.email-templates.index');
        Route::post('/email-templates', [EmailDesignController::class, 'storeTemplate'])->name('admin.email-templates.store');
        Route::put('/email-templates/{template}', [EmailDesignController::class, 'updateTemplate'])->name('admin.email-templates.update');
        Route::delete('/email-templates/{template}', [EmailDesignController::class, 'destroyTemplate'])->name('admin.email-templates.destroy');
        Route::get('/messaging/{campaign}', [MessagingController::class, 'show'])->name('admin.messaging.show');
        Route::post('/messaging/{campaign}/cancel', [MessagingController::class, 'cancel'])->name('admin.messaging.cancel');
        Route::post('/messaging/{campaign}/send', [MessagingController::class, 'send'])->name('admin.messaging.send');
        Route::post('/messaging/quick-send', [MessagingController::class, 'quickSend'])->name('admin.messaging.quickSend');

        // WhatsApp Sessions
        Route::get('/whatsapp', [MessagingController::class, 'waSessionsIndex'])->name('admin.whatsapp.sessions');
        Route::post('/whatsapp/sessions', [MessagingController::class, 'createWaSession'])->name('admin.whatsapp.sessions.create');
        Route::get('/whatsapp/sessions/{sessionId}/qr', [MessagingController::class, 'getWaQrCode'])->name('admin.whatsapp.qr');
        Route::get('/whatsapp/sessions/{sessionId}/status', [MessagingController::class, 'waSessionStatus'])->name('admin.whatsapp.status');
        Route::post('/whatsapp/sessions/{sessionId}/logout', [MessagingController::class, 'logoutWaSession'])->name('admin.whatsapp.logout');

        // Admin AI Insights
        Route::get('/ai', [AdminAIController::class, 'index'])->name('admin.ai.index');
        Route::post('/ai/platform-insights', [AdminAIController::class, 'generatePlatformInsights'])->middleware('throttle:10,1')->name('admin.ai.platformInsights');
        Route::post('/ai/cohort-insights', [AdminAIController::class, 'generateCohortInsights'])->middleware('throttle:10,1')->name('admin.ai.cohortInsights');
        Route::post('/ai/generate-content', [AdminAIController::class, 'generateContent'])->middleware('throttle:10,1')->name('admin.ai.generateContent');
    });

/*
|--------------------------------------------------------------------------
| Facilitator — Course Creation & AI Content Studio
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:facilitator|platform_admin'])
    ->prefix('facilitator')
    ->group(function () {
        // Facilitator Course CRUD
        Route::get('/courses', [App\Http\Controllers\Facilitator\CourseController::class, 'index'])->name('facilitator.courses.index');
        Route::get('/courses/create', [App\Http\Controllers\Facilitator\CourseController::class, 'create'])->name('facilitator.courses.create');
        Route::post('/courses', [App\Http\Controllers\Facilitator\CourseController::class, 'store'])->name('facilitator.courses.store');
        Route::get('/courses/{course}/edit', [App\Http\Controllers\Facilitator\CourseController::class, 'edit'])->name('facilitator.courses.edit');
        Route::put('/courses/{course}', [App\Http\Controllers\Facilitator\CourseController::class, 'update'])->name('facilitator.courses.update');
        Route::post('/courses/{course}/submit-review', [App\Http\Controllers\Facilitator\CourseController::class, 'submitForReview'])->name('facilitator.courses.submitReview');
        Route::delete('/courses/{course}', [App\Http\Controllers\Facilitator\CourseController::class, 'destroy'])->name('facilitator.courses.destroy');

        // Modules
        Route::post('/courses/{course}/modules', [App\Http\Controllers\Facilitator\CourseController::class, 'storeModule'])->name('facilitator.courses.modules.store');
        Route::put('/courses/{course}/modules/{module}', [App\Http\Controllers\Facilitator\CourseController::class, 'updateModule'])->name('facilitator.courses.modules.update');
        Route::delete('/courses/{course}/modules/{module}', [App\Http\Controllers\Facilitator\CourseController::class, 'destroyModule'])->name('facilitator.courses.modules.destroy');

        // Lessons
        Route::get('/courses/{course}/modules/{module}/lessons/create', [App\Http\Controllers\Facilitator\CourseController::class, 'createLesson'])->name('facilitator.courses.lessons.create');
        Route::get('/courses/{course}/modules/{module}/lessons/{lesson}/edit', [App\Http\Controllers\Facilitator\CourseController::class, 'editLesson'])->name('facilitator.courses.lessons.edit');
        Route::post('/courses/{course}/thumbnail', [App\Http\Controllers\Facilitator\CourseController::class, 'updateThumbnail'])->name('facilitator.courses.thumbnail');
        Route::post('/courses/{course}/modules/{module}/lessons', [App\Http\Controllers\Facilitator\CourseController::class, 'storeLesson'])->name('facilitator.courses.lessons.store');
        Route::put('/courses/{course}/modules/{module}/lessons/{lesson}', [App\Http\Controllers\Facilitator\CourseController::class, 'updateLesson'])->name('facilitator.courses.lessons.update');
        Route::delete('/courses/{course}/modules/{module}/lessons/{lesson}', [App\Http\Controllers\Facilitator\CourseController::class, 'destroyLesson'])->name('facilitator.courses.lessons.destroy');

        // AI Content Studio
        Route::get('/ai-studio', [FacilitatorAIController::class, 'index'])->name('facilitator.ai.studio');
        Route::post('/ai-studio/tts', [FacilitatorAIController::class, 'generateTTS'])->middleware('throttle:10,1')->name('facilitator.ai.tts');
        Route::post('/ai-studio/voice-clone', [FacilitatorAIController::class, 'cloneVoice'])->middleware('throttle:10,1')->name('facilitator.ai.cloneVoice');
        Route::post('/ai-studio/avatar', [FacilitatorAIController::class, 'generateAvatar'])->middleware('throttle:10,1')->name('facilitator.ai.avatar');
        Route::post('/ai-studio/repurpose', [FacilitatorAIController::class, 'repurposeContent'])->middleware('throttle:10,1')->name('facilitator.ai.repurpose');
        Route::post('/ai-studio/transcribe', [FacilitatorAIController::class, 'transcribeAudio'])->middleware('throttle:10,1')->name('facilitator.ai.transcribe');
        Route::get('/ai-studio/jobs/{job}', [FacilitatorAIController::class, 'jobStatus'])->name('facilitator.ai.jobStatus');
    });

/*
|--------------------------------------------------------------------------
| AI Tools for Students (auth required)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('ai')->group(function () {
    // AI Tutor Chat
    Route::get('/tutor', [StudentAIController::class, 'tutorIndex'])->name('ai.tutor');
    Route::post('/tutor/sessions', [StudentAIController::class, 'createSession'])->middleware('throttle:10,1')->name('ai.tutor.newSession');
    Route::get('/tutor/sessions/{session}', [StudentAIController::class, 'showSession'])->name('ai.tutor.session');
    Route::post('/tutor/sessions/{session}/chat', [StudentAIController::class, 'chat'])->middleware('throttle:10,1')->name('ai.tutor.chat');
    Route::put('/tutor/sessions/{session}/archive', [StudentAIController::class, 'archiveSession'])->name('ai.tutor.archive');

    // Lesson AI Tools
    Route::post('/lesson-summary', [StudentAIController::class, 'summarizeText'])->middleware('throttle:10,1')->name('ai.text.summary');
    Route::post('/quiz', [StudentAIController::class, 'quizFromText'])->middleware('throttle:10,1')->name('ai.text.quiz');
    Route::get('/lessons/{lesson}/summary', [StudentAIController::class, 'getLessonSummary'])->middleware('throttle:10,1')->name('ai.lesson.summary');
    Route::post('/lessons/{lesson}/generate-quiz', [StudentAIController::class, 'generateQuiz'])->middleware('throttle:10,1')->name('ai.lesson.quiz');

    // Personalized Recommendations
    Route::get('/recommendations', [StudentAIController::class, 'getRecommendations'])->middleware('throttle:10,1')->name('ai.recommendations');
});

/*
|--------------------------------------------------------------------------
| Paystack Payment Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/payments/paystack/initialize', [PaymentController::class, 'initializePaystack'])->name('payments.paystack.initialize');
    Route::get('/payments/paystack/callback', [PaymentController::class, 'paystackCallback'])->name('payments.paystack.callback');
});

// Paystack Webhook (no auth — verified via signature)
Route::post('/webhooks/paystack', [PaymentWebhookController::class, 'paystack'])->name('webhooks.paystack');

// WhatsApp Webhook (for incoming messages)
Route::post('/webhooks/whatsapp', function (Request $request) {
    // Log incoming WA messages for CRM activities
    Log::info('WhatsApp webhook', $request->all());

    return response()->json(['status' => 'ok']);
})->name('webhooks.whatsapp');

// ─── Simulation choices — part of admin group ──────────────────────────────
Route::middleware(['auth', 'role:platform_admin'])
    ->prefix('admin')
    ->group(function () {
        Route::post('/simulation-nodes/{node}/choices', [AdminSimulationController::class, 'storeChoice'])->name('admin.simulations.choices.store');
        Route::put('/simulation-choices/{choice}', [AdminSimulationController::class, 'updateChoice'])->name('admin.simulations.choices.update');
        Route::delete('/simulation-choices/{choice}', [AdminSimulationController::class, 'destroyChoice'])->name('admin.simulations.choices.destroy');
    });

/*
|--------------------------------------------------------------------------
| SME Governance Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:sme_reviewer'])
    ->prefix('sme')
    ->group(function () {
        // SME Dashboard
        Route::get('/dashboard', [App\Http\Controllers\Sme\DashboardController::class, '__invoke'])->name('sme.dashboard');

        // Content Library
        Route::get('/', [SmeContentReviewController::class, 'library'])->name('sme.library');
        Route::get('/pending', [SmeContentReviewController::class, 'pendingQueue'])->name('sme.pending');

        // Content Review
        Route::get('/review/{approval}', [SmeContentReviewController::class, 'review'])->name('sme.review');
        Route::post('/review/{approval}/approve', [SmeContentReviewController::class, 'approve'])->name('sme.approve');
        Route::post('/review/{approval}/reject', [SmeContentReviewController::class, 'reject'])->name('sme.reject');
        Route::post('/review/{approval}/request-changes', [SmeContentReviewController::class, 'requestChanges'])->name('sme.requestChanges');

        // Submit for Review
        Route::post('/submit/{type}/{id}', [SmeContentReviewController::class, 'submit'])->name('sme.submit');

        // Version History
        Route::get('/versions/{type}/{id}', [SmeContentReviewController::class, 'versions'])->name('sme.versions');
        Route::post('/versions/{type}/{id}/revert/{versionId}', [SmeContentReviewController::class, 'revert'])->name('sme.revert');

        // Question Bank Management
        Route::get('/questions', [SmeQuestionManagementController::class, 'index'])->name('sme.questions.index');
        Route::get('/questions/{question}/analytics', [SmeQuestionManagementController::class, 'analytics'])->name('sme.questions.analytics');
        Route::get('/questions/analytics-dashboard', [SmeQuestionManagementController::class, 'analyticsDashboard'])->name('sme.questions.analyticsDashboard');
        Route::get('/questions/blueprint-coverage', [SmeQuestionManagementController::class, 'blueprintCoverage'])->name('sme.questions.blueprintCoverage');

        // Flag Queue
        Route::get('/flags', [SmeQuestionManagementController::class, 'flagQueue'])->name('sme.flags');
        Route::put('/flags/{flag}', [SmeQuestionManagementController::class, 'resolveFlag'])->name('sme.flags.resolve');
    });

/*
|--------------------------------------------------------------------------
| Sponsor Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:sponsor'])
    ->prefix('sponsor')
    ->group(function () {
        Route::get('/', [App\Http\Controllers\Sponsor\DashboardController::class, '__invoke'])->name('sponsor.dashboard');
        Route::get('/interns', [SponsorController::class, 'myInterns'])->name('sponsor.interns');
        Route::get('/scorecards', [SponsorController::class, 'myScorecards'])->name('sponsor.scorecards');
        Route::get('/review-queue', [SponsorController::class, 'reviewQueue'])->name('sponsor.reviewQueue');
        Route::get('/submissions/{submission}', [SponsorController::class, 'showSubmission'])->name('sponsor.submissions.show');
        Route::post('/submissions/{submission}/review', [SponsorController::class, 'review'])->name('sponsor.submissions.review');
        Route::get('/interns/{enrollment}/progress', [SponsorController::class, 'internProgress'])->name('sponsor.internProgress');
        Route::get('/interns/{enrollment}/scorecard', [SponsorController::class, 'scorecardForm'])->name('sponsor.scorecard.form');
        Route::post('/scorecards', [SponsorController::class, 'storeScorecard'])->name('sponsor.scorecards.store');

        // Meeting Hub
        Route::get('/meetings', [MeetingController::class, 'index'])->name('sponsor.meetings.index');
        Route::post('/meetings', [MeetingController::class, 'store'])->name('sponsor.meetings.store');
        Route::post('/meetings/{meeting}/attendance', [MeetingController::class, 'markAttendance'])->name('sponsor.meetings.attendance');
        Route::post('/meetings/{meeting}/notes', [MeetingController::class, 'saveNotes'])->name('sponsor.meetings.notes');
    });

/*
|--------------------------------------------------------------------------
| Payment Webhooks (no auth, CSRF exempt)
|--------------------------------------------------------------------------
*/
Route::post('/payments/webhook/flutterwave', [PaymentWebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');
Route::post('/payments/webhook/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');

/*
|--------------------------------------------------------------------------
| Public Routes (no auth required)
|--------------------------------------------------------------------------
*/
Route::get('/certificates/{uuid}/verify', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('/portfolio/{userUuid}', [PortfolioController::class, 'publicView'])->name('portfolio.public');

Route::get('/scorm-assets/{token}/{path}', [PrivateMediaController::class, 'scorm'])->where('path', '.*')->name('scorm.asset');

Route::middleware(['auth', 'role:platform_admin|facilitator'])->group(function () {
    foreach (['admin', 'facilitator'] as $workspace) {
        Route::post('/'.$workspace.'/courses/{course}/revisions', [CourseRevisionController::class, 'store'])->name($workspace.'.courses.revisions');
        Route::post('/'.$workspace.'/courses/{course}/recall', [CourseRevisionController::class, 'recall'])->name($workspace.'.courses.recall');
    }
});

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/api/push/key', [PushSubscriptionController::class, 'key']);
    Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::delete('/api/push/subscribe', [PushSubscriptionController::class, 'destroy']);
});

// Signed preference links for CRM recipients who do not have a platform login.
Route::get('/message-preferences/{contact}/{channel}', [ContactMessagePreferenceController::class, 'show'])->middleware(['signed', 'throttle:30,1'])->name('messaging.unsubscribe');
Route::post('/message-preferences/{contact}/{channel}', [ContactMessagePreferenceController::class, 'update'])->middleware(['signed', 'throttle:30,1']);
