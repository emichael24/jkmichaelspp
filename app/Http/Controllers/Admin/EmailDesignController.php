<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Services\EmailBranding;
use App\Services\EmailContent;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Email branding (logo, colours, footer) and reusable templates for campaigns and CRM email. */
class EmailDesignController extends Controller
{
    public function index(EmailBranding $branding)
    {
        return Inertia::render('Admin/ERP/Messaging/EmailDesign', [
            'branding' => $branding->get(),
            'templates' => $this->templates(),
            'categories' => EmailTemplate::CATEGORIES,
        ]);
    }

    public function updateBranding(Request $request, EmailBranding $branding)
    {
        $data = $request->validate([
            'brand_name' => 'required|string|max:120',
            'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer_text' => 'nullable|string|max:1000',
            // Email clients do not render SVG; keep logos small so messages load quickly.
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:1024|dimensions:max_width=2400,max_height=2400',
            'remove_logo' => 'boolean',
        ]);
        $branding->update($data, $request->file('logo'), $request->boolean('remove_logo'));

        return back()->with('success', 'Email branding saved. New emails use it immediately.');
    }

    public function templatesJson()
    {
        return response()->json(['templates' => $this->templates()]);
    }

    public function storeTemplate(Request $request)
    {
        EmailTemplate::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        return back()->with('success', 'Template saved.');
    }

    public function updateTemplate(Request $request, EmailTemplate $template)
    {
        $template->update($this->validated($request));

        return back()->with('success', 'Template updated.');
    }

    public function destroyTemplate(EmailTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    /** Branded preview of a draft, personalized with the author's own name. */
    public function preview(Request $request, EmailBranding $branding)
    {
        $data = $request->validate(['body' => 'nullable|string|max:100000', 'brand' => 'nullable|array']);
        $user = $request->user();
        $html = EmailBranding::personalize((string) ($data['body'] ?? ''), $user->first_name, $user->full_name ?? null, true);
        $brand = $branding->get();
        // The branding page previews unsaved colours and text before they are applied.
        foreach (['brand_name', 'accent_color', 'header_background', 'footer_text'] as $key) {
            $value = $data['brand'][$key] ?? null;
            if (is_string($value) && ($key === 'footer_text' || $key === 'brand_name' ? mb_strlen($value) <= 1000 : preg_match('/^#[0-9a-fA-F]{6}$/', $value))) {
                $brand[$key] = $value;
            }
        }

        return response()->json(['html' => $branding->render(app(EmailContent::class)->sanitize($html) ?: '<p style="color:#94a3b8">Your message will appear here.</p>', '#', null, $brand)]);
    }

    /** Send the draft to the signed-in administrator only. */
    public function sendTest(Request $request, EmailBranding $branding)
    {
        $data = $request->validate(['subject' => 'required|string|max:255', 'body' => 'required|string|max:100000']);
        abort_unless(app(NotificationDeliveryService::class)->configured('mail'), 422, 'Email delivery is not configured yet.');
        $user = $request->user();
        $content = app(EmailContent::class);
        $body = $content->prepare(EmailBranding::personalize($data['body'], $user->first_name, $user->full_name ?? null, true), 'html');
        $subject = '[Test] '.EmailBranding::personalize($data['subject'], $user->first_name, $user->full_name ?? null);
        Mail::html($branding->render($body, null), function ($message) use ($user, $subject, $content, $body) {
            $message->to($user->email)->subject($subject);
            $message->getSymfonyMessage()->text($content->text($body));
        });

        return response()->json(['message' => 'Test email sent to '.$user->email.'.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'category' => ['required', Rule::in(EmailTemplate::CATEGORIES)],
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:100000',
        ]);
        $data['body'] = app(EmailContent::class)->prepare($data['body'], 'html');

        return $data;
    }

    private function templates(): array
    {
        return EmailTemplate::orderBy('category')->orderBy('name')->get(['id', 'name', 'category', 'subject', 'body', 'updated_at'])->toArray();
    }
}
