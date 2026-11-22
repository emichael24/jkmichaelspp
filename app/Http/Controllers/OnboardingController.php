<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionResponse;
use App\Models\QuizAttempt;
use App\Models\UserGamification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Show onboarding flow.
     */
    public function index(): Response
    {
        $user = auth()->user();

        if ($user->onboarding_completed) {
            return redirect()->route('dashboard');
        }

        // Fetch diagnostic questions — 10 varied questions across domains
        $diagnosticQuestions = Question::where('status', 'published')
            ->whereNotNull('domain')
            ->inRandomOrder()
            ->limit(10)
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'stem' => $q->stem,
                'domain' => $q->domain,
                'options' => collect($q->options)->values()->map(fn ($o, $i) => [
                    'id' => is_array($o) ? ($o['id'] ?? chr(65 + $i)) : chr(65 + $i),
                    'text' => is_array($o) ? ($o['text'] ?? '') : (string) $o,
                ])->values(),
            ]);

        // Fetch recommended courses for Quick Win
        $courses = Course::where('is_published', true)
            ->select('id', 'title', 'slug', 'description', 'category_id')
            ->with('category:id,name')
            ->limit(12)
            ->get();

        return Inertia::render('Onboarding/Index', [
            'user' => $user->only('id', 'first_name', 'last_name', 'goal', 'exam_date', 'daily_minutes', 'learning_preference', 'experience_level', 'accessibility_prefs'),
            'courses' => $courses,
            'diagnosticQuestions' => $diagnosticQuestions,
        ]);
    }

    /**
     * Save onboarding step data.
     */
    public function saveStep(Request $request)
    {
        $step = $request->input('step');
        $user = auth()->user();

        switch ($step) {
            case 'goal':
                $validated = $request->validate([
                    'goal' => 'required|in:certification,skill,internship',
                ]);
                $user->update($validated);
                break;

            case 'profile':
                $validated = $request->validate([
                    'exam_date' => 'nullable|date|after:today',
                    'daily_minutes' => 'required|integer|in:10,15,20,30,45,60',
                    'learning_preference' => 'required|in:read,watch,listen',
                    'experience_level' => 'required|in:beginner,intermediate,advanced',
                    'accessibility_prefs' => 'nullable|array',
                ]);
                $user->update($validated);
                break;

            case 'diagnostic':
                $request->validate([
                    'responses' => 'required|array',
                    'responses.*.question_id' => 'required|integer|exists:questions,id',
                    'responses.*.selected' => 'required|string',
                ]);

                $correct = 0;
                $total = count($request->responses);
                $domainScores = [];

                $attempt = QuizAttempt::create([
                    'user_id' => $user->id,
                    'quizzable_type' => 'diagnostic',
                    'quizzable_id' => $user->id,
                    'status' => 'completed',
                    'total_count' => $total,
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);

                foreach ($request->responses as $resp) {
                    $question = Question::find($resp['question_id']);
                    $selected = (string) $resp['selected'];
                    $correctIds = collect($question->correct_answer_ids)->map(fn ($id) => (string) $id)->sort()->values();
                    $isCorrect = $correctIds->count() === 1 && $correctIds->first() === $selected;
                    if ($isCorrect) {
                        $correct++;
                    }

                    $domainScores[$question->domain]['total'] = ($domainScores[$question->domain]['total'] ?? 0) + 1;
                    $domainScores[$question->domain]['correct'] = ($domainScores[$question->domain]['correct'] ?? 0) + ($isCorrect ? 1 : 0);

                    QuestionResponse::create([
                        'user_id' => $user->id,
                        'question_id' => $resp['question_id'],
                        'quiz_attempt_id' => $attempt->id,
                        'selected_answer_ids' => [$selected],
                        'is_correct' => $isCorrect,
                        'time_spent_seconds' => 30,
                        'confidence_level' => 'medium',
                    ]);
                }

                $scorePct = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
                $domainScores = collect($domainScores)->map(function ($data) {
                    $totalCount = $data['total'] ?? 0;
                    $correctCount = $data['correct'] ?? 0;

                    return [
                        'correct' => $correctCount,
                        'total' => $totalCount,
                        'pct' => $totalCount > 0 ? round(($correctCount / $totalCount) * 100, 1) : 0,
                    ];
                })->toArray();

                $attempt->update([
                    'score_pct' => $scorePct,
                    'correct_count' => $correct,
                    'time_spent_seconds' => $total * 30,
                    'domain_scores' => $domainScores,
                ]);

                return response()->json([
                    'success' => true,
                    'score' => $total > 0 ? round(($correct / $total) * 100) : 0,
                    'correct' => $correct,
                    'total' => $total,
                ]);

            case 'complete':
                $user->update(['onboarding_completed' => true]);

                // Award 25 XP for completing onboarding
                $gam = UserGamification::forUser($user->id);
                $gam->increment('total_xp', 25);

                break;
        }

        return response()->json(['success' => true]);
    }

    /**
     * Complete onboarding and redirect to dashboard.
     */
    public function complete()
    {
        $user = auth()->user();
        $user->update(['onboarding_completed' => true]);

        return redirect()->route('dashboard')->with('success', 'Welcome to JK Michaels! Your personalized learning journey begins now.');
    }
}
