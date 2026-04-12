<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CRMController extends Controller
{
    // ─── CRM Dashboard ────────────────────────────────────────────────────────────
    public function dashboard()
    {
        return Inertia::render('Admin/ERP/Dashboard', [
            'stats' => [
                'total_contacts'   => CrmContact::count(),
                'leads'            => CrmContact::where('type', 'lead')->count(),
                'prospects'        => CrmContact::where('type', 'prospect')->count(),
                'students'         => CrmContact::where('type', 'student')->count(),
                'open_deals'       => CrmDeal::where('status', 'open')->count(),
                'won_deals'        => CrmDeal::where('status', 'won')->count(),
                'pipeline_value'   => CrmDeal::where('status', 'open')->sum('value'),
                'activities_today' => CrmActivity::whereDate('created_at', today())->count(),
            ],
            'recentContacts' => CrmContact::with('assignedTo')
                ->latest()
                ->limit(10)
                ->get(),
            'recentActivities' => CrmActivity::with(['contact', 'user'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    // ─── Contacts ────────────────────────────────────────────────────────────────
    public function contactsIndex(Request $request)
    {
        $query = CrmContact::with('assignedTo')->withCount('activities');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($request->get('assigned_to_me')) {
            $query->where('assigned_to', auth()->id());
        }

        $contacts = $query->latest()->paginate(30);

        return Inertia::render('Admin/ERP/Contacts/Index', [
            'contacts' => $contacts,
            'filters'  => $request->only(['search', 'type', 'status', 'assigned_to_me']),
        ]);
    }

    public function contactShow(CrmContact $contact)
    {
        $contact->load(['linkedUser', 'assignedTo', 'deals', 'activities.user']);

        return Inertia::render('Admin/ERP/Contacts/Show', [
            'contact' => $contact,
        ]);
    }

    public function contactStore(Request $request)
    {
        $validated = $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'whatsapp'     => 'nullable|string|max:30',
            'company'      => 'nullable|string|max:255',
            'job_title'    => 'nullable|string|max:255',
            'type'         => 'required|in:lead,prospect,student,alumni,partner',
            'source'       => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
            'tags'         => 'nullable|array',
            'assigned_to'  => 'nullable|exists:users,id',
        ]);

        $contact = CrmContact::create(array_merge($validated, [
            'uuid'   => (string) Str::uuid(),
            'status' => 'new',
        ]));

        return redirect()->route('admin.crm.contacts.show', $contact)
            ->with('success', 'Contact created.');
    }

    public function contactUpdate(Request $request, CrmContact $contact)
    {
        $validated = $request->validate([
            'first_name'   => 'required|string|max:100',
            'last_name'    => 'required|string|max:100',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'whatsapp'     => 'nullable|string|max:30',
            'company'      => 'nullable|string|max:255',
            'job_title'    => 'nullable|string|max:255',
            'type'         => 'required|in:lead,prospect,student,alumni,partner',
            'status'       => 'required|in:new,contacted,qualified,converted,lost,inactive',
            'source'       => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
            'tags'         => 'nullable|array',
            'assigned_to'  => 'nullable|exists:users,id',
        ]);

        $contact->update($validated);
        return back()->with('success', 'Contact updated.');
    }

    public function contactDestroy(CrmContact $contact)
    {
        $contact->delete();
        return redirect()->route('admin.crm.contacts.index')->with('success', 'Contact deleted.');
    }

    // ─── Deals / Pipeline ─────────────────────────────────────────────────────────
    public function dealsIndex(Request $request)
    {
        $deals = CrmDeal::with(['contact', 'assignedTo'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->get('stage'), fn ($q, $s) => $q->where('stage', $s))
            ->latest()
            ->paginate(30);

        // Pipeline kanban data
        $pipelineData = CrmDeal::where('status', 'open')
            ->with('contact')
            ->get()
            ->groupBy('stage');

        return Inertia::render('Admin/ERP/Deals/Index', [
            'deals'        => $deals,
            'pipelineData' => $pipelineData,
            'filters'      => $request->only(['status', 'stage']),
        ]);
    }

    public function dealStore(Request $request)
    {
        $validated = $request->validate([
            'contact_id'          => 'required|exists:crm_contacts,id',
            'title'               => 'required|string|max:255',
            'value'               => 'required|numeric|min:0',
            'currency'            => 'nullable|string|size:3',
            'stage'               => 'required|string|max:100',
            'expected_close_date' => 'nullable|date',
            'notes'               => 'nullable|string',
            'assigned_to'         => 'nullable|exists:users,id',
        ]);

        $deal = CrmDeal::create(array_merge($validated, [
            'uuid'        => (string) Str::uuid(),
            'status'      => 'open',
            'assigned_to' => $validated['assigned_to'] ?? auth()->id(),
        ]));

        return back()->with(['success' => 'Deal created.', 'deal' => $deal]);
    }

    public function dealUpdate(Request $request, CrmDeal $deal)
    {
        $deal->update($request->validate([
            'title'               => 'required|string|max:255',
            'value'               => 'required|numeric|min:0',
            'stage'               => 'required|string|max:100',
            'status'              => 'required|in:open,won,lost',
            'expected_close_date' => 'nullable|date',
            'closed_date'         => 'nullable|date',
            'notes'               => 'nullable|string',
        ]));

        return back()->with('success', 'Deal updated.');
    }

    // ─── Activities ───────────────────────────────────────────────────────────────
    public function activityStore(Request $request)
    {
        $validated = $request->validate([
            'contact_id'  => 'required|exists:crm_contacts,id',
            'deal_id'     => 'nullable|exists:crm_deals,id',
            'type'        => 'required|in:call,email,whatsapp,meeting,note,task',
            'subject'     => 'required|string|max:255',
            'description' => 'nullable|string',
            'direction'   => 'nullable|in:inbound,outbound',
            'outcome'     => 'nullable|in:no_answer,left_voicemail,interested,not_interested,follow_up,completed',
            'due_at'      => 'nullable|date',
        ]);

        $activity = CrmActivity::create(array_merge($validated, [
            'user_id'      => auth()->id(),
            'completed_at' => $validated['type'] === 'note' ? now() : null,
        ]));

        // Update contact's last_contacted_at
        CrmContact::find($validated['contact_id'])->update([
            'last_contacted_at' => now(),
        ]);

        return back()->with(['success' => 'Activity logged.', 'activity' => $activity->load('user')]);
    }

    public function activityComplete(CrmActivity $activity)
    {
        $activity->update(['completed_at' => now()]);
        return back()->with('success', 'Activity marked complete.');
    }
}
