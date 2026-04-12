<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Question;
use App\Models\QuestionResponse;
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
                'id'       => $q->id,
                'stem'     => $q->stem,
                'domain'   => $q->domain,
                'options'  => collect($q->options)->map(fn ($o, $i) => [
                    'index' => $i,
                    'text'  => is_array($o) ? ($o['text'] ?? $o) : $o,
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
            'courses'            => $courses,
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
                    'exam_date'          => 'nullable|date|after:today',
                    'daily_minutes'      => 'required|integer|in:10,15,20,30,45,60',
                    'learning_preference' => 'required|in:read,watch,listen',
                    'experience_level'   => 'required|in:beginner,intermediate,advanced',
                    'accessibility_prefs' => 'nullable|array',
                ]);
                $user->update($validated);
                break;

            case 'diagnostic':
                $request->validate([
                    'responses'        => 'required|array',
                    'responses.*.question_id'  => 'required|integer|exists:questions,id',
                    'responses.*.selected'     => 'required|integer',
                ]);

                $correct = 0;
                $total   = count($request->responses);

                foreach ($request->responses as $resp) {
                    $question = Question::find($resp['question_id']);
                    $isCorrect = $resp['selected'] === $question->correct_option;
                    if ($isCorrect) $correct++;

                    QuestionResponse::create([
                        'user_id'     => $user->id,
                        'question_id' => $resp['question_id'],
                        'quiz_attempt_id' => null,
                        'selected_option' => $resp['selected'],
                        'is_correct'      => $isCorrect,
                        'time_spent_seconds' => 30,
                        'confidence_level'   => 'medium',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'score'   => $total > 0 ? round(($correct / $total) * 100) : 0,
                    'correct' => $correct,
                    'total'   => $total,
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
