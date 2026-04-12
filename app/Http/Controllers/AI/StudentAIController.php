<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiChatSession;
use App\Models\AiJob;
use App\Models\AiLessonSummary;
use App\Models\AiGeneratedQuiz;
use App\Models\Lesson;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentAIController extends Controller
{
    public function __construct(protected AIService $ai) {}

    // ─── AI Tutor Chat ───────────────────────────────────────────────────────────
    public function tutorIndex()
    {
        $sessions = AiChatSession::where('user_id', auth()->id())
            ->where('is_archived', false)
            ->latest()
            ->limit(20)
            ->get(['id', 'uuid', 'title', 'context_type', 'updated_at']);

        return Inertia::render('AI/StudentTutor', [
            'sessions' => $sessions,
        ]);
    }

    public function createSession(Request $request)
    {
        $request->validate([
            'title'        => 'nullable|string|max:255',
            'context_type' => 'nullable|in:general,lesson,course,exam_prep',
            'context_id'   => 'nullable|integer',
        ]);

        $session = AiChatSession::create([
            'uuid'         => (string) Str::uuid(),
            'user_id'      => auth()->id(),
            'title'        => $request->input('title', 'New Chat'),
            'context_type' => $request->input('context_type', 'general'),
            'context_id'   => $request->input('context_id'),
            'messages'     => [],
        ]);

        return response()->json(['session' => $session]);
    }

    public function chat(Request $request, AiChatSession $session)
    {
        abort_unless($session->user_id === auth()->id(), 403);

        $request->validate([
            'message' => 'required|string|max:8000',
        ]);

        $userMessage = $request->input('message');
        $messages = $session->messages ?? [];

        // Build system prompt based on context
        $systemPrompt = $this->buildSystemPrompt($session);

        // Append last 20 turns to context for continuity (simple approach)
        $contextTurns = array_slice($messages, -20);
        $contextStr = '';
        foreach ($contextTurns as $turn) {
            $contextStr .= ($turn['role'] === 'user' ? "Student: " : "Tutor: ") . $turn['content'] . "\n";
        }

        $fullMessage = $contextStr ? "Previous conversation:\n{$contextStr}\n\nNew question: {$userMessage}" : $userMessage;

        $aiResponse = $this->ai->chat($fullMessage, $systemPrompt, 0.7, 1500, true);

        // Persist messages
        $messages[] = ['role' => 'user', 'content' => $userMessage, 'ts' => now()->toIso8601String()];
        $messages[] = ['role' => 'assistant', 'content' => $aiResponse, 'ts' => now()->toIso8601String()];

        // Auto-title after first exchange
        if (count($messages) === 2) {
            $session->title = Str::limit($userMessage, 60);
        }

        $session->update(['messages' => $messages]);

        return response()->json(['response' => $aiResponse]);
    }

    public function archiveSession(AiChatSession $session)
    {
        abort_unless($session->user_id === auth()->id(), 403);
        $session->update(['is_archived' => true]);
        return response()->json(['success' => true]);
    }

    // ─── Lesson Summary ───────────────────────────────────────────────────────────
    public function getLessonSummary(Request $request, Lesson $lesson)
    {
        $request->validate([
            'type' => 'nullable|in:brief,detailed,key_points,study_guide',
        ]);

        $type = $request->input('type', 'key_points');

        // Return cached if exists
        $cached = AiLessonSummary::where('lesson_id', $lesson->id)
            ->where('summary_type', $type)
            ->first();

        if ($cached) {
            return response()->json(['summary' => $cached->content, 'cached' => true]);
        }

        // Generate
        $content = $this->ai->summarizeLesson(
            strip_tags($lesson->text_content ?? ''),
            $lesson->title,
            $type
        );

        AiLessonSummary::create([
            'lesson_id'    => $lesson->id,
            'summary_type' => $type,
            'content'      => $content,
        ]);

        return response()->json(['summary' => $content, 'cached' => false]);
    }

    // ─── AI-Generated Quiz ────────────────────────────────────────────────────────
    public function generateQuiz(Request $request, Lesson $lesson)
    {
        $request->validate([
            'question_count' => 'nullable|integer|min:3|max:20',
        ]);

        $count = $request->input('question_count', 5);

        $questions = $this->ai->generateQuizFromLesson(
            strip_tags($lesson->text_content ?? ''),
            $lesson->title,
            $count
        );

        $quiz = AiGeneratedQuiz::create([
            'lesson_id'      => $lesson->id,
            'created_by'     => auth()->id(),
            'title'          => "AI Quiz: {$lesson->title}",
            'questions'      => $questions,
            'question_count' => count($questions),
        ]);

        return response()->json(['quiz' => $quiz]);
    }

    // ─── Personalized Recommendations ────────────────────────────────────────────
    public function getRecommendations()
    {
        $user = auth()->user();

        $profile = [
            'name'              => $user->first_name,
            'goal'              => $user->goal,
            'experience_level'  => $user->experience_level,
            'daily_minutes'     => $user->daily_minutes,
            'exam_date'         => $user->exam_date,
            'streak_days'       => 0, // TODO: pull from gamification
            'enrolled_courses'  => $user->enrollments()->pluck('courses.title')->take(5)->toArray(),
        ];

        $recommendations = $this->ai->getPersonalizedRecommendations($profile);

        return response()->json(['recommendations' => $recommendations]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    protected function buildSystemPrompt(AiChatSession $session): string
    {
        $base = "You are an expert learning coach at JK Michaels Professional Mastery Platform. " .
            "You help working professionals and exam candidates learn effectively. " .
            "Be concise, practical, and encouraging. Use clear examples.";

        if ($session->context_type === 'exam_prep') {
            $base .= " The student is preparing for a professional exam. Focus on exam-relevant concepts, practice techniques, and confidence building.";
        }

        return $base;
    }
}
